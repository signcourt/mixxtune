<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $plainToken,
        public string $role
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl =
            rtrim(config('app.url'), '/')
            . '/invitation/'
            . urlencode($this->plainToken);

        $roleName = match ($this->role) {
            'artist' => 'Artist',
            'label' => 'Label',
            'admin' => 'Admin',
            default => ucfirst($this->role),
        };

        return (new MailMessage)
            ->subject("You're invited to Mixx Tune")
            ->greeting('Hello '.$notifiable->name.',')
            ->line(
                "You have been invited to join Mixx Tune as a {$roleName}."
            )
            ->line(
                'Username: @'.$notifiable->username
            )
            ->line(
                'Email: '.$notifiable->email
            )
            ->line(
                'Please verify your invitation and create your password.'
            )
            ->action(
                'Accept Invitation & Set Password',
                $acceptUrl
            )
            ->line(
                'This secure invitation link will expire in 7 days.'
            )
            ->line(
                'If you were not expecting this invitation, you may ignore this email.'
            );
    }
}
