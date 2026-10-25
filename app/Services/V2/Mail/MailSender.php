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
use Symfony\Component\Mime\Part\DataPart;
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
     * @param array{to:string[], cc?:string[], bcc?:string[], from?:string, subject:string, body:string} $data
     * @param UploadedFile[] $files
     * @param MailAttachment[] $carried attachments of a forwarded mail that go along (W96)
     */
    public function send(MailAccount $account, User $by, array $data, array $files = [], ?MailMessage $replyTo = null, array $carried = []): MailMessage
    {
        /* W101: send from the mailbox itself or one of its aliases */
        $from = collect($account->fromAddresses())->firstWhere('address', strtolower((string) ($data['from'] ?? $account->address))) ?? $account->fromAddresses()[0];
        $domain = Str::after($from['address'], '@') ?: 'mixxtune.com';
        $messageId = '<'.Str::uuid()->toString().'@'.$domain.'>';
        /* W103: the rich editor sends HTML - cleaned here like a pasted signature (no scripts, forms, ...) */
        $userHtml = SignatureHtml::clean($data['html'] ?? null);
        $text = rtrim(str_replace("\u{00A0}", ' ', (string) ($data['body'] ?? '')));
        if (trim($text) === '' && $userHtml) {
            $text = SignatureHtml::text($userHtml);
        }
        $wrap = fn (string $inner): string => '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5">'.$inner.'</div>';
        $main = $userHtml ? $wrap($userHtml) : self::html($text);
        $withSignature = ($data['signature'] ?? true) !== false;
        $body = $text;
        $html = $main;
        /* W102: a designed signature (HTML source code) goes as it is; the text version gets its lines */
        $sigHtml = $withSignature ? SignatureHtml::scale(SignatureHtml::clean($account->signature_html), $account->signature_scale) : null; /* W104: at the chosen size */
        if ($sigHtml) {
            $body .= "\n\n-- \n".SignatureHtml::text($sigHtml);
            $html = $wrap($main.'<br><div class="mx-signature">'.$sigHtml.'</div>');
        } elseif ($withSignature && $account->signature) {
            $body .= "\n\n-- \n".$account->signature;
            $html = $userHtml ? $wrap($main.'<br><div>-- <br>'.nl2br(e($account->signature)).'</div>') : self::html($body);
        }
        $keepHtml = $userHtml !== null || $sigHtml !== null;

        $email = (new Email())
            ->from(new Address($from['address'], $from['name']))
            ->to(...array_map(fn ($a) => new Address($a), $data['to']))
            ->subject($data['subject'])
            ->text($body);
        $email->html($this->embedPictures($email, $html, $domain));
        if (!empty($data['cc'])) {
            $email->cc(...array_map(fn ($a) => new Address($a), $data['cc']));
        }
        if (!empty($data['bcc'])) {
            $email->bcc(...array_map(fn ($a) => new Address($a), $data['bcc']));
        }
        $email->getHeaders()->addIdHeader('Message-ID', trim($messageId, '<>'));
        if ($replyTo && $replyTo->message_id) {
            $email->getHeaders()->addTextHeader('In-Reply-To', $replyTo->message_id);
            $email->getHeaders()->addTextHeader('References', trim(($replyTo->references_header ? $replyTo->references_header.' ' : '').$replyTo->message_id));
        }
        foreach ($files as $f) {
            $email->attachFromPath($f->getRealPath(), $f->getClientOriginalName(), $f->getMimeType() ?: 'application/octet-stream');
        }
        foreach ($carried as $c) {
            $email->attachFromPath(Storage::disk('local')->path($c->path), $c->filename, $c->mime ?: 'application/octet-stream');
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
            'from_name' => $from['name'] ?: null,
            'from_email' => $from['address'],
            'to' => array_map(fn ($a) => ['name' => '', 'email' => strtolower($a)], $data['to']),
            'cc' => array_map(fn ($a) => ['name' => '', 'email' => strtolower($a)], $data['cc'] ?? []),
            'bcc' => array_map(fn ($a) => ['name' => '', 'email' => strtolower($a)], $data['bcc'] ?? []),
            'sent_at' => now(),
            'snippet' => MailSync::snippet($body, null),
            'body_text' => $body,
            'body_html' => $keepHtml ? $html : null,
            'has_attachments' => $files !== [] || $carried !== [],
            'seen' => true,
            'size' => strlen($email->toString()),
            'sent_by' => $by->id,
        ]);
        foreach (array_values($files) as $i => $f) {
            $name = preg_replace('/[\x00-\x1F\/\\\\:*?"<>|]+/u', '_', $f->getClientOriginalName()) ?: 'attachment';
            $path = $f->storeAs('mail/'.$account->id.'/'.$msg->id, ($i + 1).'-'.$name, 'local');
            MailAttachment::query()->create(['mail_message_id' => $msg->id, 'filename' => mb_substr($name, 0, 190), 'mime' => $f->getMimeType(), 'size' => (int) $f->getSize(), 'path' => $path]);
        }
        foreach (array_values($carried) as $i => $c) {
            $path = 'mail/'.$account->id.'/'.$msg->id.'/f'.($i + 1).'-'.basename($c->path);
            Storage::disk('local')->copy($c->path, $path);
            MailAttachment::query()->create(['mail_message_id' => $msg->id, 'filename' => $c->filename, 'mime' => $c->mime, 'size' => (int) $c->size, 'path' => $path]);
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

    /*
     * W103: pictures put inside the text (data:image/...;base64) go along as inline parts
     * (cid:), which every mail app shows; the panel keeps its own copy as it is.
     */
    private function embedPictures(Email $email, string $html, string $domain): string
    {
        $n = 0;

        return preg_replace_callback('#src=(["\'])data:image/(png|jpe?g|gif|webp);base64,([A-Za-z0-9+/=\s]+)\1#i', function (array $m) use ($email, $domain, &$n) {
            $bytes = base64_decode(preg_replace('/\s+/', '', $m[3]), true);
            if ($bytes === false || $bytes === '') {
                return 'src=""';
            }
            $n++;
            $ext = strtolower($m[2]) === 'jpg' ? 'jpeg' : strtolower($m[2]);
            $part = (new DataPart($bytes, 'picture'.$n.'.'.($ext === 'jpeg' ? 'jpg' : $ext), 'image/'.$ext))->asInline();
            $part->setContentId('pic'.$n.'.'.Str::lower(Str::random(10)).'@'.$domain);
            $email->addPart($part);

            return 'src='.$m[1].'cid:'.$part->getContentId().$m[1];
        }, $html) ?? $html;
    }

    /*
     * W96: the HTML version of the text. Simple marks from the compose toolbar:
     * **bold**, _italic_, lines starting with "- " become a list, "> " lines a grey quote, links clickable.
     */
    public static function html(string $body): string
    {
        $inline = function (string $s): string {
            $s = e($s);
            $s = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<b>$1</b>', $s);
            $s = preg_replace('/(?<![\w])_(?=\S)(.+?)(?<=\S)_(?![\w])/u', '<i>$1</i>', $s);

            return preg_replace('~\b(https?://[^\s<>"\']+[^\s<>"\'.,;:!?)])~i', '<a href="$1">$1</a>', $s);
        };
        $out = '';
        $list = false;
        $quote = false;
        foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
            $isItem = (bool) preg_match('/^\s*[-•]\s+(.*)$/u', $line, $mi);
            $isQuote = str_starts_with($line, '>');
            if ($list && !$isItem) {
                $out .= '</ul>';
                $list = false;
            }
            if ($quote && !$isQuote) {
                $out .= '</div>';
                $quote = false;
            }
            if ($isItem) {
                if (!$list) {
                    $out .= '<ul style="margin:4px 0 4px 20px;padding:0">';
                    $list = true;
                }
                $out .= '<li>'.$inline($mi[1]).'</li>';
            } elseif ($isQuote) {
                if (!$quote) {
                    $out .= '<div style="border-left:3px solid #ccc;padding-left:10px;color:#555">';
                    $quote = true;
                }
                $out .= $inline(ltrim(substr($line, 1))).'<br>';
            } else {
                $out .= $inline($line).'<br>';
            }
        }
        $out .= ($list ? '</ul>' : '').($quote ? '</div>' : '');

        return '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5">'.$out.'</div>';
    }
}
