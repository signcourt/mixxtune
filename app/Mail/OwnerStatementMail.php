<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/*
 * MIXXTUNE_OWNER_STATEMENT_V1
 * Monthly statement e-mail to a label / artist owner with the PDF attached.
 */
class OwnerStatementMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public array $owner,
        public string $monthLabel,
        public float $netPayable,
        public string $currency,
        public string $pdfBytes,
        public string $pdfName
    ) {
    }

    public function build()
    {
        $name = e($this->owner['name'] ?: $this->owner['title']);
        $title = e($this->owner['title']);
        $amount = e($this->currency.' '.number_format($this->netPayable, 2));
        $app = e(config('app.name'));
        $login = e(rtrim((string) config('app.url'), '/').'/login');

        return $this
            ->subject("{$app}: royalty statement for {$this->monthLabel} ({$this->owner['title']})")
            ->html(
                "<p>Hello {$name},</p>"
                ."<p>Your royalty statement for <strong>".e($this->monthLabel)."</strong> for <strong>{$title}</strong> is attached as a PDF.</p>"
                ."<p>Net payable for this statement: <strong>{$amount}</strong></p>"
                ."<p>You can also see it any time after logging in: <a href=\"{$login}\">{$login}</a> → Statements.</p>"
                ."<p>If anything looks wrong, simply reply to this e-mail.</p>"
                ."<p>Regards,<br>{$app}</p>"
            )
            ->attachData($this->pdfBytes, $this->pdfName, ['mime' => 'application/pdf']);
    }
}
