<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcceptArtistInvitationController extends Controller
{
    public function __invoke(
        Request $request,
        string $token
    ): RedirectResponse {
        $artist = User::query()
            ->where('role', 'artist')
            ->where('invitation_token', hash('sha256', $token))
            ->first();

        if (! $artist) {
            return redirect()
                ->route('artist.login')
                ->withErrors([
                    'email' => 'This invitation link is invalid or has already been used.',
                ]);
        }

        if (
            ! $artist->invitation_expires_at ||
            $artist->invitation_expires_at->isPast()
        ) {
            $artist->update([
                'invitation_status' => 'expired',
                'invitation_error' => 'Invitation link expired.',
            ]);

            return redirect()
                ->route('artist.login')
                ->withErrors([
                    'email' => 'This invitation has expired. Please ask the administrator to resend it.',
                ]);
        }

        $artist->update([
            'email_verified_at' => $artist->email_verified_at ?? now(),
            'invitation_status' => 'password_pending',
            'invitation_accepted_at' => now(),
            'invitation_error' => null,
        ]);

        $request->session()->put(
            'invited_artist_id',
            $artist->id
        );

        return redirect()->route('artist.password.create');
    }
}
