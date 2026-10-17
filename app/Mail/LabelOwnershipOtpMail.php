<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/*
 * MIXXTUNE_LABEL_OWNERSHIP_OTP_V1
 * One-time code e-mailed to the acting super admin before a label
 * ownership assignment / transfer is applied.
 */
class LabelOwnershipOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $otp,
        public string $adminName,
        public int $labelCount,
        public string $targetDescription
    ) {
    }

    public function build()
    {
        return $this
            ->subject('Mixx Tune: label ownership confirmation code')
            ->html(
                '<p>Hello '.e($this->adminName).',</p>'
                .'<p>Your confirmation code to assign / transfer <strong>'
                .$this->labelCount.' label(s)</strong> to <strong>'
                .e($this->targetDescription).'</strong> is:</p>'
                .'<p style="font-size:26px;font-weight:bold;letter-spacing:6px">'
                .e($this->otp).'</p>'
                .'<p>This code expires in 5 minutes and works only for this exact change.</p>'
                .'<p>If you did not request this, ignore this e-mail and change your password.</p>'
            );
    }
}
