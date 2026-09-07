<?php

namespace App\Services\V2;

use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class UserInvitationService
{
    /**
     * Generate and store a secure invitation token.
     *
     * Only the SHA-256 hash is stored in the database.
     * The plain token is used only for the invitation link.
     */
    public function issue(User $user): string
    {
        $plainToken = Str::random(64);

        $user->forceFill([
            'invitation_status' => 'pending',
            'invitation_token' => hash(
                'sha256',
                $plainToken
            ),
            'invitation_sent_at' => now(),
            'invitation_expires_at' => now()->addDays(7),
            'invitation_count' =>
                ((int) $user->invitation_count) + 1,
            'invitation_error' => null,
            'email_verified_at' => null,
        ])->save();

        return $plainToken;
    }

    /**
     * Generate a new invitation link without sending email.
     *
     * Only the SHA-256 hash is stored in the database.
     * The plain token is returned only to the caller that
     * immediately needs to build the invitation link.
     */
    public function issueLink(User $user): string
    {
        $plainToken = Str::random(64);

        $user->forceFill([
            'invitation_status' => 'sent',
            'invitation_token' => hash(
                'sha256',
                $plainToken
            ),
            'invitation_sent_at' => now(),
            'invitation_expires_at' =>
                now()->addDays(7),
            'invitation_count' =>
                ((int) $user->invitation_count) + 1,
            'invitation_error' => null,
            'email_verified_at' => null,
        ])->save();

        return $plainToken;
    }

    /**
     * Generate a new invitation and send it immediately.
     */
    public function send(User $user): string
    {
        return DB::transaction(function () use ($user) {
            $plainToken = $this->issue($user);

            try {
                $user->notify(
                    new UserInvitationNotification(
                        $plainToken,
                        (string) $user->role
                    )
                );

                $user->forceFill([
                    'invitation_status' => 'sent',
                    'invitation_error' => null,
                ])->save();
            } catch (Throwable $exception) {
                $user->forceFill([
                    'invitation_status' => 'failed',
                    'invitation_error' =>
                        mb_substr(
                            $exception->getMessage(),
                            0,
                            1000
                        ),
                ])->save();

                throw $exception;
            }

            return $plainToken;
        });
    }
}
