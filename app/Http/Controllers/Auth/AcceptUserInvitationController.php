<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcceptUserInvitationController extends Controller
{
    public function __invoke(
        Request $request,
        string $token
    ): RedirectResponse {
        $user = User::query()
            ->whereIn(
                'role',
                ['admin', 'label', 'artist']
            )
            ->where(
                'invitation_token',
                hash('sha256', $token)
            )
            ->first();

        if (! $user) {
            return redirect()
                ->route('user.invitation.invalid')
                ->withErrors([
                    'invitation' =>
                        'This invitation link is invalid or has already been used.',
                ]);
        }

        if (
            ! $user->invitation_expires_at
            || $user->invitation_expires_at->isPast()
        ) {
            $user->forceFill([
                'invitation_status' => 'expired',
                'invitation_error' =>
                    'Invitation link expired.',
            ])->save();

            return redirect()
                ->route('user.invitation.invalid')
                ->withErrors([
                    'invitation' =>
                        'This invitation has expired. Please ask the administrator to resend it.',
                ]);
        }

        $user->forceFill([
            'email_verified_at' =>
                $user->email_verified_at ?? now(),
            'invitation_status' =>
                'password_pending',
            'invitation_accepted_at' =>
                now(),
            'invitation_error' =>
                null,
        ])->save();

        $request->session()->put(
            'invited_user_id',
            $user->id
        );

        $request->session()->save();

        return redirect(
            '/invitation-set-password'
        );
    }
}
