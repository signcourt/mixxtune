<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginEmailOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public string $userName
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your MIXX TUNE Login OTP'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login-email-otp'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
