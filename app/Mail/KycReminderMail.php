<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/* W197 MIXXTUNE_KYC_REMINDER: self-contained HTML mail (no blade view) that links the owner to
 * the login page with ?next=/kyc-profile, so after login they land straight on their KYC page. */
class KycReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $name,
        public string $url,
        public bool $hasProfile
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Complete your KYC to receive payouts - MixxTune',
        );
    }

    public function content(): Content
    {
        $name = e($this->name !== '' ? $this->name : 'there');
        $url = e($this->url);

        $line = $this->hasProfile
            ? 'Please review your payout details and press "Submit for Verification" so we can verify your KYC and release your payouts.'
            : 'Please add your payout details (PAN, bank account number, IFSC and address) so we can set up your payouts.';

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#1e293b;">'
            . '<h2 style="color:#6d28d9;margin:0 0 12px;">Complete your KYC</h2>'
            . '<p>Hello ' . $name . ',</p>'
            . '<p>' . e($line) . '</p>'
            . '<p style="margin:24px 0;">'
            . '<a href="' . $url . '" style="background:#6d28d9;color:#ffffff;text-decoration:none;'
            . 'padding:12px 22px;border-radius:10px;font-weight:bold;display:inline-block;">Open my KYC page</a>'
            . '</p>'
            . '<p style="font-size:13px;color:#64748b;">You will be asked to log in first, then your KYC page opens automatically. '
            . 'If the button does not work, copy this link into your browser:<br>' . $url . '</p>'
            . '<p style="font-size:13px;color:#64748b;">- Team MixxTune</p>'
            . '</div>';

        return new Content(
            htmlString: $html,
        );
    }
}
