<?php

namespace App\Services\V2\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAttachment;
use App\Models\Mail\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/*
 * W95 MIXXTUNE_PANEL_MAIL
 * Copies new mail of the Inbox and Sent folders into the panel. Only new UIDs are read.
 * The first time only the newest FIRST_SYNC messages of each folder are taken.
 * Messages bigger than MAX_BYTES keep their headers only ("open in webmail").
 */
class MailSync
{
    public const FIRST_SYNC = 200;
    public const BATCH = 20;
    public const MAX_BYTES = 30 * 1024 * 1024;
    public const SENT_NAMES = ['Sent', 'INBOX.Sent', 'Sent Items', 'Sent Messages', 'INBOX.Sent Items', 'Sent Mail'];

    public function __construct(private readonly MailConnector $connector, private readonly MimeParser $parser)
    {
    }

    /** @return array{inbox:int, sent:int} new messages */
    public function sync(MailAccount $account): array
    {
        $imap = $this->connector->imap($account);
        try {
            if (!$account->sent_folder) {
                $account->forceFill(['sent_folder' => $this->findSentFolder($imap)])->save();
            }
            $new = ['inbox' => $this->syncFolder($account, $imap, 'INBOX', 'inbox'), 'sent' => 0];
            if ($account->sent_folder) {
                $new['sent'] = $this->syncFolder($account, $imap, $account->sent_folder, 'sent');
            }
            $account->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();

            return $new;
        } finally {
            $imap->logout();
        }
    }

    public function findSentFolder(ImapClient $imap): ?string
    {
        $folders = $imap->listFolders();
        foreach ($folders as $f) {
            if (stripos($f['flags'], '\\Sent') !== false) {
                return $f['name'];
            }
        }
        $names = array_column($folders, 'name');
        foreach (self::SENT_NAMES as $n) {
            foreach ($names as $name) {
                if (strcasecmp($name, $n) === 0) {
                    return $name;
                }
            }
        }

        return null;
    }

    private function syncFolder(MailAccount $account, ImapClient $imap, string $name, string $kind): int
    {
        $info = $imap->select($name, true);
        $folder = DB::table('mail_folders')->where('mail_account_id', $account->id)->where('kind', $kind)->first();
        if (!$folder) {
            DB::table('mail_folders')->insert(['mail_account_id' => $account->id, 'name' => $name, 'kind' => $kind, 'uidvalidity' => $info['uidvalidity'], 'last_uid' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $folder = DB::table('mail_folders')->where('mail_account_id', $account->id)->where('kind', $kind)->first();
        }
        $first = (int) $folder->last_uid === 0;
        if ((int) $folder->uidvalidity !== (int) $info['uidvalidity'] && (int) $folder->uidvalidity !== 0) {
            /* the server renumbered this folder: read it again */
            $old = MailMessage::query()->where('mail_account_id', $account->id)->where('folder', $kind)->whereNotNull('uid');
            foreach ((clone $old)->pluck('id') as $id) {
                Storage::disk('local')->deleteDirectory('mail/'.$account->id.'/'.$id);
                MailAttachment::query()->where('mail_message_id', $id)->delete();
            }
            $old->delete();
            $first = true;
            $folder->last_uid = 0;
        }

        $uids = $imap->uidsAfter((int) $folder->last_uid);
        if ($first && count($uids) > self::FIRST_SYNC) {
            $uids = array_slice($uids, -self::FIRST_SYNC);
        }
        $count = 0;
        $last = (int) $folder->last_uid;
        foreach (array_chunk($uids, self::BATCH) as $chunk) {
            $sizes = $imap->fetch($chunk, false);
            $small = array_column(array_filter($sizes, fn ($s) => $s['size'] <= self::MAX_BYTES), 'uid');
            $big = array_column(array_filter($sizes, fn ($s) => $s['size'] > self::MAX_BYTES), 'uid');
            $rows = array_merge($imap->fetch($small), $imap->fetch($big, true, true));
            usort($rows, fn ($a, $b) => $a['uid'] <=> $b['uid']);
            foreach ($rows as $row) {
                try {
                    if ($this->store($account, $kind, $row, in_array($row['uid'], $big, true))) {
                        $count++;
                    }
                } catch (Throwable $e) {
                    report($e);
                }
                $last = max($last, $row['uid']);
            }
            DB::table('mail_folders')->where('id', $folder->id)->update(['name' => $name, 'uidvalidity' => $info['uidvalidity'], 'last_uid' => $last, 'updated_at' => now()]);
        }
        if ($uids === []) {
            DB::table('mail_folders')->where('id', $folder->id)->update(['name' => $name, 'uidvalidity' => $info['uidvalidity'], 'updated_at' => now()]);
        }

        return $count;
    }

    private function store(MailAccount $account, string $kind, array $row, bool $headersOnly): bool
    {
        if (MailMessage::query()->where('mail_account_id', $account->id)->where('folder', $kind)->where('uid', $row['uid'])->exists()) {
            return false;
        }
        $m = $this->parser->parse((string) $row['raw']);
        $seen = in_array('\\Seen', $row['flags'], true);

        /* a mail sent from the panel is already here (no uid yet): just link it */
        if ($kind === 'sent' && $m['message_id']) {
            $own = MailMessage::query()->where('mail_account_id', $account->id)->where('folder', 'sent')->whereNull('uid')->where('message_id', $m['message_id'])->first();
            if ($own) {
                $own->forceFill(['uid' => $row['uid']])->save();

                return false;
            }
        }

        $text = $headersOnly ? "This message is too big to show here (".round($row['size'] / 1048576, 1)." MB). Open it in webmail: https://mail.hostinger.com" : $m['text'];

        return DB::transaction(function () use ($account, $kind, $row, $m, $seen, $text, $headersOnly) {
            $msg = MailMessage::query()->create([
                'mail_account_id' => $account->id,
                'folder' => $kind,
                'uid' => $row['uid'],
                'message_id' => $m['message_id'] ? mb_substr($m['message_id'], 0, 500) : null,
                'in_reply_to' => $m['in_reply_to'] ? mb_substr($m['in_reply_to'], 0, 500) : null,
                'references_header' => $m['references'],
                'subject' => mb_substr($m['subject'], 0, 998),
                'from_name' => mb_substr($m['from']['name'], 0, 190),
                'from_email' => mb_substr($m['from']['email'], 0, 190),
                'to' => $m['to'],
                'cc' => $m['cc'],
                'reply_to' => $m['reply_to'],
                'sent_at' => $m['date'] ?? now(),
                'snippet' => self::snippet($text, $m['html']),
                'body_text' => $text,
                'body_html' => $headersOnly ? null : ($m['html'] !== '' ? $m['html'] : null),
                'has_attachments' => collect($m['attachments'])->contains(fn ($a) => !$a['inline']),
                'seen' => $kind === 'sent' ? true : $seen,
                /* W110: a mail starred in webmail / Gmail app comes in starred */
                'starred' => in_array('\\Flagged', $row['flags'], true),
                'size' => (int) $row['size'],
            ]);
            foreach ($m['attachments'] as $i => $a) {
                $path = 'mail/'.$account->id.'/'.$msg->id.'/'.($i + 1).'-'.$a['filename'];
                Storage::disk('local')->put($path, $a['content']);
                MailAttachment::query()->create(['mail_message_id' => $msg->id, 'filename' => $a['filename'], 'mime' => mb_substr($a['mime'], 0, 120), 'size' => strlen($a['content']), 'content_id' => $a['cid'] ? mb_substr($a['cid'], 0, 190) : null, 'inline' => $a['inline'], 'path' => $path]);
            }

            return true;
        });
    }

    public static function snippet(?string $text, ?string $html): string
    {
        $s = trim((string) $text) !== '' ? (string) $text : html_entity_decode(strip_tags(preg_replace('#<(style|script)[^>]*>.*?</\1>#si', ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $s)), 0, 280);
    }
}
