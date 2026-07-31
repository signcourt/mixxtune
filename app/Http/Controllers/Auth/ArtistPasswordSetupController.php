<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ArtistPasswordSetupController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $artistId = $request->session()->get('invited_artist_id');

        if (! $artistId) {
            return redirect()
                ->route('artist.login')
                ->withErrors([
                    'email' => 'Invitation session expired. Please open the invitation link again.',
                ]);
        }

        $artist = User::query()
            ->where('id', $artistId)
            ->where('role', 'artist')
            ->first();

        if (! $artist) {
            $request->session()->forget('invited_artist_id');

            return redirect()
                ->route('artist.login')
                ->withErrors([
                    'email' => 'Artist invitation could not be found.',
                ]);
        }

        return Inertia::render('Auth/ArtistSetPassword', [
            'artist' => [
                'name' => $artist->name,
                'email' => $artist->email,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $artistId = $request->session()->get('invited_artist_id');

        if (! $artistId) {
            return redirect()
                ->route('artist.login')
                ->withErrors([
                    'email' => 'Invitation session expired.',
                ]);
        }

        $validated = $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],
        ]);

        $artist = User::query()
            ->where('id', $artistId)
            ->where('role', 'artist')
            ->firstOrFail();

        $artist->update([
            'password' => $validated['password'],
            'email_verified_at' => $artist->email_verified_at ?? now(),
            'invitation_status' => 'accepted',
            'invitation_accepted_at' => $artist->invitation_accepted_at ?? now(),
            'invitation_token' => null,
            'invitation_error' => null,
            'password_set_at' => now(),
            'account_status' => 'active',
        ]);

        $request->session()->forget('invited_artist_id');

        Auth::login($artist);
        $request->session()->regenerate();

        return redirect()->route('artist.dashboard');
    }
}
