<?php

namespace App\Services\V2\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAttachment;
use App\Models\Mail\MailMessage;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

/*
 * W113 MIXXTUNE_MAIL_ACTIONS
 * Delete (Trash, 30 days), put back, delete for ever, Archive, Spam, read / unread, Snooze -
 * for one mail or many at once. The panel changes at once; the same move is then made in the
 * mailbox at Hostinger (Trash / Junk / Archive folders), so webmail and phones match.
 */
class MailActions
{
    public const ACTIONS = ['trash', 'restore', 'delete_forever', 'archive', 'inbox', 'spam', 'not_spam', 'read', 'unread', 'snooze', 'unsnooze'];

    public const TRASH_DAYS = 30;

    private const NAMES = [
        'trash' => ['\\Trash', ['INBOX.Trash', 'Trash', 'INBOX/Trash', 'Deleted Items', 'INBOX.Deleted Items', 'Deleted Messages', 'Bin']],
        'spam' => ['\\Junk', ['INBOX.Junk', 'Junk', 'INBOX/Junk', 'INBOX.Spam', 'Spam', 'Junk E-mail', 'Junk Email']],
        'archive' => ['\\Archive', ['INBOX.Archive', 'Archive', 'INBOX/Archive', 'Archives', 'INBOX.Archives']],
    ];

    public function __construct(private readonly MailConnector $connector)
    {
    }

    /** where the mail is now in the mailbox at Hostinger: [folder, uid] or null */
    public static function location(MailAccount $account, MailMessage $m): ?array
    {
        $uid = $m->server_uid ?? $m->uid;
        if (!$uid) {
            return null;
        }
        $box = $m->server_folder ?: (($m->orig_folder ?: $m->folder) === 'sent' ? $account->sent_folder : 'INBOX');

        return $box ? [$box, (int) $uid] : null;
    }

    /**
     * @param Collection<int, MailMessage> $messages (of this mailbox)
     * @return array{done:int, skipped:int}
     */
    public function apply(MailAccount $account, Collection $messages, string $action, ?CarbonInterface $until = null): array
    {
        $done = 0;
        $skipped = 0;
        $server = [];
        foreach ($messages as $m) {
            if ($m->folder === 'drafts') {
                if ($action === 'trash' || $action === 'delete_forever') {
                    $this->wipe($m);
                    $done++;
                } else {
                    $skipped++;
                }
                continue;
            }
            /* the first time a mail is touched, remember where it is at Hostinger */
            if (!$m->server_folder && ($loc = self::location($account, $m))) {
                $m->server_folder = $loc[0];
                $m->server_uid = $loc[1];
            }
            $f = $m->folder;
            $op = null;
            switch ($action) {
                case 'trash':
                    if ($f === 'trash') {
                        $skipped++;
                        continue 2;
                    }
                    $m->forceFill(['orig_folder' => $f, 'folder' => 'trash', 'trashed_at' => now(), 'snooze_until' => null]);
                    $op = ['move', 'trash'];
                    break;
                case 'restore':
                    if ($f !== 'trash') {
                        $skipped++;
                        continue 2;
                    }
                    $back = in_array($m->orig_folder, ['inbox', 'sent', 'archive', 'spam'], true) ? $m->orig_folder : 'inbox';
                    $m->forceFill(['folder' => $back, 'trashed_at' => null, 'orig_folder' => $back === 'sent' ? 'sent' : $m->orig_folder]);
                    $op = ['move', $back];
                    break;
                case 'delete_forever':
                    if (!in_array($f, ['trash', 'spam'], true)) {
                        $skipped++;
                        continue 2;
                    }
                    if ($loc = self::location($account, $m)) {
                        $server[] = ['delete', $loc[0], $loc[1], null, $m->message_id, null];
                    }
                    $this->wipe($m);
                    $done++;
                    continue 2;
                case 'archive':
                    if ($f !== 'inbox') {
                        $skipped++;
                        continue 2;
                    }
                    $m->forceFill(['orig_folder' => 'inbox', 'folder' => 'archive', 'snooze_until' => null]);
                    $op = ['move', 'archive'];
                    break;
                case 'inbox':
                case 'not_spam':
                    if (!in_array($f, ['archive', 'spam', 'trash'], true)) {
                        $skipped++;
                        continue 2;
                    }
                    $m->forceFill(['orig_folder' => 'inbox', 'folder' => 'inbox', 'trashed_at' => null]);
                    $op = ['move', 'inbox'];
                    break;
                case 'spam':
                    if (!in_array($f, ['inbox', 'archive'], true)) {
                        $skipped++;
                        continue 2;
                    }
                    $m->forceFill(['orig_folder' => 'inbox', 'folder' => 'spam', 'snooze_until' => null, 'starred' => false]);
                    $op = ['move', 'spam'];
                    break;
                case 'read':
                case 'unread':
                    $m->forceFill(['seen' => $action === 'read']);
                    $op = ['flag', $action === 'read' ? '+' : '-'];
                    break;
                case 'snooze':
                    if (!in_array($f, ['inbox', 'archive'], true) || !$until) {
                        $skipped++;
                        continue 2;
                    }
                    $m->forceFill(['folder' => 'inbox', 'orig_folder' => 'inbox', 'snooze_until' => $until, 'woke_at' => null]);
                    $op = $f === 'archive' ? ['move', 'inbox'] : null;
                    break;
                case 'unsnooze':
                    if (!$m->snooze_until) {
                        $skipped++;
                        continue 2;
                    }
                    $m->forceFill(['snooze_until' => null, 'woke_at' => now()]);
                    break;
                default:
                    $skipped++;
                    continue 2;
            }
            $m->save();
            $done++;
            if ($op && $m->server_folder && $m->server_uid) {
                $server[] = [$op[0], $m->server_folder, (int) $m->server_uid, $op[1], $m->message_id, $m->id];
            }
        }
        if ($server !== []) {
            dispatch(fn () => $this->onServer($account, $server))->afterResponse();
        }

        return ['done' => $done, 'skipped' => $skipped];
    }

    /** the same changes in the mailbox at Hostinger (best effort - the panel is already right) */
    public function onServer(MailAccount $account, array $ops): void
    {
        try {
            $imap = $this->connector->imap($account);
        } catch (Throwable $e) {
            report($e);

            return;
        }
        try {
            $special = $this->specialFolders($imap);
            foreach ($ops as [$kind, $box, $uid, $arg, $messageId, $rowId]) {
                try {
                    $imap->select($box);
                    if ($kind === 'delete') {
                        $imap->deleteForever($uid);
                        continue;
                    }
                    if ($kind === 'flag') {
                        $arg === '+' ? $imap->addFlags($uid, '\\Seen') : $imap->removeFlags($uid, '\\Seen');
                        continue;
                    }
                    $dest = match ($arg) {
                        'inbox' => 'INBOX',
                        'sent' => $account->sent_folder,
                        default => $special[$arg] ?? null,
                    };
                    if (!$dest || strcasecmp($dest, $box) === 0) {
                        continue;
                    }
                    $new = $imap->move($uid, $dest);
                    if (!$new && $messageId) {
                        $imap->select($dest);
                        $new = $imap->uidByMessageId($messageId);
                    }
                    MailMessage::query()->whereKey($rowId)->update(['server_folder' => $dest, 'server_uid' => $new]);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        } finally {
            $imap->logout();
        }
    }

    /** Trash / Junk / Archive folder of the mailbox (by its flag, else by its usual name) */
    public function specialFolders(ImapClient $imap): array
    {
        $folders = $imap->listFolders();
        $out = [];
        foreach (self::NAMES as $key => [$flag, $names]) {
            foreach ($folders as $f) {
                if (stripos($f['flags'], $flag) !== false) {
                    $out[$key] = $f['name'];
                    continue 2;
                }
            }
            foreach ($names as $n) {
                foreach ($folders as $f) {
                    if (strcasecmp($f['name'], $n) === 0) {
                        $out[$key] = $f['name'];
                        continue 3;
                    }
                }
            }
        }

        return $out;
    }

    /** snoozed mails whose time has come go back to the top of the Inbox, as new */
    public static function wake(?int $accountId = null): int
    {
        $q = MailMessage::query()->where('folder', 'inbox')->whereNotNull('snooze_until')->where('snooze_until', '<=', now());
        if ($accountId) {
            $q->where('mail_account_id', $accountId);
        }
        $n = 0;
        foreach ($q->get(['id', 'snooze_until']) as $m) {
            MailMessage::query()->whereKey($m->id)->update(['woke_at' => $m->snooze_until, 'snooze_until' => null, 'seen' => false]);
            $n++;
        }

        return $n;
    }

    /** mails in Trash for more than 30 days are deleted for ever (also at Hostinger) */
    public function purgeTrash(MailAccount $account): int
    {
        $old = MailMessage::query()->where('mail_account_id', $account->id)->where('folder', 'trash')->where('trashed_at', '<', now()->subDays(self::TRASH_DAYS))->limit(500)->get();
        if ($old->isEmpty()) {
            return 0;
        }
        $ops = [];
        foreach ($old as $m) {
            if ($loc = self::location($account, $m)) {
                $ops[] = ['delete', $loc[0], $loc[1], null, $m->message_id, null];
            }
            $this->wipe($m);
        }
        if ($ops !== []) {
            $this->onServer($account, $ops);
        }

        return $old->count();
    }

    private function wipe(MailMessage $m): void
    {
        Storage::disk('local')->deleteDirectory('mail/'.$m->mail_account_id.'/'.$m->id);
        MailAttachment::query()->where('mail_message_id', $m->id)->delete();
        $m->delete();
    }
}
