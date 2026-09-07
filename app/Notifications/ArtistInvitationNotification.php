<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ArtistInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $invitationToken,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('single.invitation.accept', [
            'token' => $this->invitationToken,
        ]);

        return (new MailMessage)
            ->subject("You're invited to Mixx Tune")
            ->view('emails.invitation', [
                'subject' => "You're invited to Mixx Tune",
                'name' => $notifiable->name,
                'roleName' => 'Artist',
                'username' => null,
                'email' => $notifiable->email,
                'actionUrl' => $url,
                'actionText' => 'Verify Email & Continue',
            ]);
    }
}
