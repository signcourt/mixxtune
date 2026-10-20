<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/* MIXXTUNE_TWO_STEP_ACTIONS_V1: confirmation code for a money-sensitive action. */
class ActionOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $otp,
        public string $actorName,
        public string $what
    ) {
    }

    public function build()
    {
        return $this
            ->subject(config('app.name').': confirmation code')
            ->html(
                '<p>Hello '.e($this->actorName).',</p>'
                .'<p>Your code to <strong>'.e($this->what).'</strong> is:</p>'
                .'<p style="font-size:26px;font-weight:bold;letter-spacing:6px">'.e($this->otp).'</p>'
                .'<p>It expires in 10 minutes and works only for this action.</p>'
                .'<p>If you did not try to do this, do not share this code and change your password now.</p>'
            );
    }
}
