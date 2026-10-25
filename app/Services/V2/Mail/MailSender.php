<?php

namespace App\Services\V2\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAttachment;
use App\Models\Mail\MailMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/*
 * W95 MIXXTUNE_PANEL_MAIL
 * Sends a mail from a domain mailbox (SMTP), keeps it in the panel's Sent list
 * and saves a copy in the mailbox's own Sent folder (so webmail shows it too).
 */
class MailSender
{
    public function __construct(private readonly MailConnector $connector)
    {
    }

    /**
     * @param array{to:string[], cc?:string[], subject:string, body:string} $data
     * @param UploadedFile[] $files
     */
    public function send(MailAccount $account, User $by, array $data, array $files = [], ?MailMessage $replyTo = null): MailMessage
    {
        $domain = Str::after($account->address, '@') ?: 'mixxtune.com';
        $messageId = '<'.Str::uuid()->toString().'@'.$domain.'>';
        $body = rtrim($data['body']);
        if ($account->signature) {
            $body .= "\n\n-- \n".$account->signature;
        }

        $email = (new Email())
            ->from(new Address($account->address, (string) ($account->display_name ?? '')))
            ->to(...array_map(fn ($a) => new Address($a), $data['to']))
            ->subject($data['subject'])
            ->text($body)
            ->html('<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5;white-space:pre-wrap">'.e($body).'</div>');
        if (!empty($data['cc'])) {
            $email->cc(...array_map(fn ($a) => new Address($a), $data['cc']));
        }
        $email->getHeaders()->addIdHeader('Message-ID', trim($messageId, '<>'));
        if ($replyTo && $replyTo->message_id) {
            $email->getHeaders()->addTextHeader('In-Reply-To', $replyTo->message_id);
            $email->getHeaders()->addTextHeader('References', trim(($replyTo->references_header ? $replyTo->references_header.' ' : '').$replyTo->message_id));
        }
        foreach ($files as $f) {
            $email->attachFromPath($f->getRealPath(), $f->getClientOriginalName(), $f->getMimeType() ?: 'application/octet-stream');
        }

        $this->connector->transport($account)->send($email);

        $msg = MailMessage::query()->create([
            'mail_account_id' => $account->id,
            'folder' => 'sent',
            'uid' => null,
            'message_id' => $messageId,
            'in_reply_to' => $replyTo?->message_id,
            'references_header' => $replyTo ? trim(($replyTo->references_header ? $replyTo->references_header.' ' : '').$replyTo->message_id) : null,
            'subject' => mb_substr($data['subject'], 0, 998),
            'from_name' => $account->display_name,
            'from_email' => $account->address,
            'to' => array_map(fn ($a) => ['name' => '', 'email' => strtolower($a)], $data['to']),
            'cc' => array_map(fn ($a) => ['name' => '', 'email' => strtolower($a)], $data['cc'] ?? []),
            'sent_at' => now(),
            'snippet' => MailSync::snippet($body, null),
            'body_text' => $body,
            'has_attachments' => $files !== [],
            'seen' => true,
            'size' => strlen($email->toString()),
            'sent_by' => $by->id,
        ]);
        foreach (array_values($files) as $i => $f) {
            $name = preg_replace('/[\x00-\x1F\/\\\\:*?"<>|]+/u', '_', $f->getClientOriginalName()) ?: 'attachment';
            $path = $f->storeAs('mail/'.$account->id.'/'.$msg->id, ($i + 1).'-'.$name, 'local');
            MailAttachment::query()->create(['mail_message_id' => $msg->id, 'filename' => mb_substr($name, 0, 190), 'mime' => $f->getMimeType(), 'size' => (int) $f->getSize(), 'path' => $path]);
        }
        if ($replyTo && $replyTo->folder === 'inbox' && !$replyTo->seen) {
            $replyTo->forceFill(['seen' => true])->save();
        }

        /* copy into the mailbox's Sent folder - best effort, the mail is already sent */
        try {
            if ($account->sent_folder) {
                $imap = $this->connector->imap($account);
                try {
                    $imap->append($account->sent_folder, $email->toString());
                } finally {
                    $imap->logout();
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $msg;
    }
}
