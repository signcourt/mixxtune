<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/* MIXXTUNE_BANK_CHANGE_PROTECTION_V1: notice to the owner whose bank / UPI details changed. */
class BankDetailsChangedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $ownerName,
        public array $old,
        public array $new,
        public CarbonInterface $changedAt,
        public CarbonInterface $holdUntil
    ) {
    }

    private function row(string $label, $old, $new): string
    {
        $old = $old === null || $old === '' ? '—' : (string) $old;
        $new = $new === null || $new === '' ? '—' : (string) $new;

        return '<tr><td style="padding:4px 10px 4px 0;color:#555">'.e($label).'</td>'
            .'<td style="padding:4px 10px">'.e($old).'</td>'
            .'<td style="padding:4px 10px"><strong>'.e($new).'</strong></td></tr>';
    }

    public function build()
    {
        $tz = config('app.timezone');

        return $this
            ->subject(config('app.name').': your payout bank details were changed')
            ->html(
                '<p>Hello '.e($this->ownerName).',</p>'
                .'<p>The bank / UPI details used for your royalty payouts were changed on <strong>'
                .e($this->changedAt->copy()->timezone($tz)->format('d M Y, h:i A')).'</strong>.</p>'
                .'<table style="border-collapse:collapse;font-size:14px">'
                .'<tr><th></th><th style="padding:4px 10px;text-align:left">Before</th><th style="padding:4px 10px;text-align:left">Now</th></tr>'
                .$this->row('Account holder', $this->old['account_holder_name'] ?? null, $this->new['account_holder_name'] ?? null)
                .$this->row('Bank account', $this->old['bank_account'] ?? null, $this->new['bank_account'] ?? null)
                .$this->row('IFSC', $this->old['ifsc_code'] ?? null, $this->new['ifsc_code'] ?? null)
                .$this->row('UPI', $this->old['upi_id'] ?? null, $this->new['upi_id'] ?? null)
                .'</table>'
                .'<p>For your safety, no payout will be sent before <strong>'
                .e($this->holdUntil->copy()->timezone($tz)->format('d M Y, h:i A')).'</strong>, and the new details will be verified again.</p>'
                .'<p><strong>If you did not ask for this change, reply to this e-mail or contact support immediately.</strong></p>'
            );
    }
}
