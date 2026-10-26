<?php

namespace App\Services\V2\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAttachment;
use App\Models\Mail\MailMessage;
use App\Models\User;
use App\Services\V2\StaffAlerts;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/*
 * W117 MIXXTUNE_MAIL_SCHEDULE_SEND
 * "Schedule send" like Gmail: the mail (with its attachments) waits in the panel in "Scheduled"
 * (only its writer sees it) and `mail:send-scheduled` sends it at that time - the same way as Send.
 * Cancel puts it back into Drafts; "Send now" sends at once.
 */
class MailScheduler
{
    public const TRIES = 3;

    public function __construct(private readonly MailSender $sender)
    {
    }

    /** @param UploadedFile[] $files */
    public function schedule(MailAccount $acc, User $by, array $data, array $files, CarbonInterface $at): MailMessage
    {
        $key = (string) Str::uuid();
        $stored = [];
        foreach (array_values($files) as $i => $f) {
            $name = preg_replace('/[\x00-\x1F\/\\\\:*?"<>|]+/u', '_', $f->getClientOriginalName()) ?: 'attachment';
            $path = $f->storeAs('mail-scheduled/'.$acc->id.'/'.$key, ($i + 1).'-'.$name, 'local');
            $stored[] = ['path' => $path, 'name' => mb_substr($name, 0, 190), 'mime' => $f->getMimeType() ?: 'application/octet-stream', 'size' => (int) $f->getSize()];
        }
        $people = fn (array $l) => array_map(fn ($e) => ['name' => '', 'email' => strtolower($e)], $l);
        $text = (string) ($data['body'] ?? '');

        return MailMessage::query()->create([
            'mail_account_id' => $acc->id,
            'folder' => 'scheduled',
            'seen' => true,
            'sent_by' => $by->id,
            'subject' => mb_substr((string) $data['subject'], 0, 998),
            'from_email' => $data['from'] ?? $acc->address,
            'to' => $people($data['to_list']),
            'cc' => $people($data['cc_list'] ?? []),
            'bcc' => $people($data['bcc_list'] ?? []),
            'snippet' => MailSync::snippet($text, null),
            'body_text' => $text,
            'body_html' => $data['html'] ?? null,
            'has_attachments' => $stored !== [] || !empty($data['keep_attachments']),
            'sent_at' => $at,
            'scheduled_at' => $at,
            'draft' => [
                'to' => (string) ($data['to'] ?? implode(', ', $data['to_list'])),
                'cc' => implode(', ', $data['cc_list'] ?? []),
                'bcc' => implode(', ', $data['bcc_list'] ?? []),
                'from' => $data['from'] ?? null,
                'signature' => !array_key_exists('signature', $data) || $data['signature'] === null || (bool) $data['signature'],
                'reply_to_id' => $data['reply_to_id'] ?? null,
                'forward_of' => $data['forward_of'] ?? null,
                'keep_attachments' => array_values(array_map('intval', $data['keep_attachments'] ?? [])),
                'files' => $stored,
                'tries' => 0,
            ],
        ]);
    }

    /** send every scheduled mail whose time has come */
    public function sendDue(int $limit = 50): array
    {
        $sent = 0;
        $failed = 0;
        /* a send that stopped half-way (server restart): not sent again blindly - back to Drafts, writer told */
        foreach (MailMessage::query()->where('folder', 'sending')->where('updated_at', '<', now()->subMinutes(30))->get() as $stuck) {
            $stuck->forceFill(['folder' => 'drafts', 'scheduled_at' => null, 'draft' => array_merge($stuck->draft ?? [], ['error' => 'Sending stopped half-way - check Sent before sending it again.'])])->save();
            if ($u = User::query()->find($stuck->sent_by)) {
                try {
                    app(StaffAlerts::class)->bell([$u], 'mail.schedule_failed', 'Check a scheduled mail', '"'.$stuck->subject.'": sending stopped half-way. Look in Sent - if it is not there, send it again from Drafts.', '/mail?account='.$stuck->mail_account_id.'&folder=sent', 'warning');
                } catch (Throwable) {
                }
            }
        }
        $ids = MailMessage::query()->where('folder', 'scheduled')->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            $r = $this->sendOne((int) $id);
            $r === true ? $sent++ : ($r === false ? $failed++ : null);
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /** true = sent, false = failed (stays / gives up), null = someone else took it */
    public function sendOne(int $id): ?bool
    {
        /* take it: only one process may send it (scheduled -> sending) */
        $took = MailMessage::query()->whereKey($id)->where('folder', 'scheduled')->update(['folder' => 'sending']);
        if ($took !== 1) {
            return null;
        }
        $m = MailMessage::query()->findOrFail($id);
        $d = $m->draft ?? [];
        $acc = MailAccount::query()->find($m->mail_account_id);
        $by = User::query()->find($m->sent_by);
        try {
            if (!$acc || $acc->status !== 'active' || !$by) {
                throw new \RuntimeException('The mailbox or the writer is not active any more.');
            }
            $files = [];
            foreach ($d['files'] ?? [] as $f) {
                if (!Storage::disk('local')->exists($f['path'])) {
                    throw new \RuntimeException('An attachment is missing: '.$f['name']);
                }
                $files[] = new UploadedFile(Storage::disk('local')->path($f['path']), $f['name'], $f['mime'], null, true);
            }
            $carried = [];
            if (!empty($d['forward_of']) && !empty($d['keep_attachments'])) {
                $orig = MailMessage::query()->where('mail_account_id', $acc->id)->find((int) $d['forward_of']);
                $carried = $orig ? $orig->attachments()->whereIn('id', $d['keep_attachments'])->where('inline', false)->get()
                    ->filter(fn (MailAttachment $a) => Storage::disk('local')->exists($a->path))->values()->all() : [];
            }
            $reply = !empty($d['reply_to_id']) ? MailMessage::query()->where('mail_account_id', $acc->id)->find((int) $d['reply_to_id']) : null;
            $this->sender->send($acc, $by, [
                'to' => collect($m->to ?? [])->pluck('email')->all(),
                'cc' => collect($m->cc ?? [])->pluck('email')->all(),
                'bcc' => collect($m->bcc ?? [])->pluck('email')->all(),
                'from' => $d['from'] ?? null,
                'subject' => (string) $m->subject,
                'body' => (string) $m->body_text,
                'html' => $m->body_html,
                'signature' => ($d['signature'] ?? true) !== false,
            ], $files, $reply, $carried);
        } catch (Throwable $e) {
            report($e);
            $tries = (int) ($d['tries'] ?? 0) + 1;
            $giveUp = $tries >= self::TRIES;
            /* try again in 5 minutes; after 3 tries it goes back to Drafts and the writer is told */
            $m->forceFill([
                'folder' => $giveUp ? 'drafts' : 'scheduled',
                'scheduled_at' => $giveUp ? null : now()->addMinutes(5),
                'draft' => array_merge($d, ['tries' => $tries, 'error' => mb_substr($e->getMessage(), 0, 300)]),
            ])->save();
            if ($giveUp && $by) {
                try {
                    app(StaffAlerts::class)->bell([$by], 'mail.schedule_failed', 'Scheduled mail NOT sent', '"'.$m->subject.'" could not be sent: '.$e->getMessage().' It is back in Drafts (attachments added in the Write window are not kept there).', '/mail?account='.$m->mail_account_id.'&folder=drafts', 'warning');
                } catch (Throwable) {
                }
                $this->removeFiles($d);
            }

            return false;
        }
        $this->removeFiles($d);
        $m->delete();

        return true;
    }

    /** back into Drafts (attachments of the Write window are not kept in drafts) */
    public function cancel(MailMessage $m): MailMessage
    {
        $d = $m->draft ?? [];
        DB::transaction(function () use ($m, $d) {
            $took = MailMessage::query()->whereKey($m->id)->where('folder', 'scheduled')->update(['folder' => 'drafts', 'scheduled_at' => null]);
            if ($took !== 1) {
                throw \Illuminate\Validation\ValidationException::withMessages(['mail' => 'This mail is being sent right now - it cannot be stopped any more.']);
            }
            $m->refresh()->forceFill(['draft' => array_diff_key($d, ['files' => 1, 'tries' => 1, 'error' => 1])])->save();
        });
        $this->removeFiles($d);

        return $m->fresh();
    }

    private function removeFiles(array $d): void
    {
        foreach ($d['files'] ?? [] as $f) {
            Storage::disk('local')->delete($f['path']);
        }
    }
}
