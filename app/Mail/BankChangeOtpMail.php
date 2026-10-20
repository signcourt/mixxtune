<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/* MIXXTUNE_BANK_CHANGE_PROTECTION_V1: code for the person changing an owner's bank details. */
class BankChangeOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $otp,
        public string $actorName,
        public string $ownerName
    ) {
    }

    public function build()
    {
        return $this
            ->subject(config('app.name').': confirm bank details change')
            ->html(
                '<p>Hello '.e($this->actorName).',</p>'
                .'<p>Your code to confirm changing the <strong>bank / UPI details of '.e($this->ownerName).'</strong> is:</p>'
                .'<p style="font-size:26px;font-weight:bold;letter-spacing:6px">'.e($this->otp).'</p>'
                .'<p>It expires in 10 minutes and works only for this owner.</p>'
                .'<p>If you did not try to change bank details, do not share this code and change your password now.</p>'
            );
    }
}
