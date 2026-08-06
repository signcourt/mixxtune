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

class UserInvitationPasswordController extends Controller
{
    public function create(
        Request $request
    ): Response|RedirectResponse {
        $userId = $request->session()->get(
            'invited_user_id'
        );

        if (! $userId) {
            return redirect()->route(
                'user.invitation.invalid'
            );
        }

        $user = User::query()
            ->where('id', $userId)
            ->whereIn(
                'role',
                ['admin', 'label', 'artist']
            )
            ->first();

        if (! $user) {
            $request->session()->forget(
                'invited_user_id'
            );

            return redirect()->route(
                'user.invitation.invalid'
            );
        }

        return Inertia::render(
            'Auth/UserSetPassword',
            [
                'invitedUser' => [
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $userId = $request->session()->get(
            'invited_user_id'
        );

        if (! $userId) {
            return redirect()->route(
                'user.invitation.invalid'
            );
        }

        $validated = $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers(),
            ],
        ]);

        $user = User::query()
            ->where('id', $userId)
            ->whereIn(
                'role',
                ['admin', 'label', 'artist']
            )
            ->firstOrFail();

        $user->forceFill([
            'password' =>
                $validated['password'],
            'email_verified_at' =>
                $user->email_verified_at ?? now(),
            'invitation_status' =>
                'accepted',
            'invitation_accepted_at' =>
                $user->invitation_accepted_at
                ?? now(),
            'invitation_token' =>
                null,
            'invitation_error' =>
                null,
            'password_set_at' =>
                now(),
            'account_status' =>
                'active',
        ])->save();

        $request->session()->forget(
            'invited_user_id'
        );

        Auth::login(
            $user,
            true
        );

        $request->session()->regenerate();

        $dashboardUrl = match ($user->role) {
            'super_admin' =>
                '/super-admin/dashboard',

            'admin' =>
                '/admin/dashboard',

            'label' =>
                '/label/dashboard',

            'artist' =>
                '/artist/dashboard',

            default =>
                '/',
        };

        return redirect()->to(
            $dashboardUrl
        );
    }
}
