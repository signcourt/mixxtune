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
            ->subject('You are invited to Mixx Tune')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('You have been invited to join the Mixx Tune Artist Portal.')
            ->line('First verify your email address. After verification, you will create your own password.')
            ->action('Verify Email & Create Password', $url)
            ->line('This invitation link will expire in 7 days.')
            ->line('If you were not expecting this invitation, you may ignore this email.');
    }
}
