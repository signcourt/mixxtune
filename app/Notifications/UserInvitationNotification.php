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
        public string $role,
        public bool $reminder = false
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
            'admin' => 'Manager',
            default => ucfirst($this->role),
        };

        /* MIXXTUNE_INVITATION_REMINDERS_V1 */
        $subject = $this->reminder
            ? 'Reminder: your Mixx Tune invitation is waiting'
            : "You're invited to Mixx Tune";

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.invitation', [
                'subject' => $subject,
                'reminder' => $this->reminder,
                'name' => $notifiable->name,
                'roleName' => $roleName,
                'username' => $notifiable->username,
                'email' => $notifiable->email,
                'actionUrl' => $acceptUrl,
                'actionText' => 'Accept Invitation',
            ]);
    }
}
