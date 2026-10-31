<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PanelRedirectController extends Controller
{
    public function __invoke(
        Request $request
    ): RedirectResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401
        );

        $role = strtolower(
            str_replace(
                '-',
                '_',
                (string) $user->role
            )
        );

        /* W128 MIXXTUNE_OFFICE_MONITOR: logging in is clocking in for office staff */
        if ($role === 'staff') {
            try {
                app(\App\Services\V2\Office\OfficeService::class)->clockIn($user);
            } catch (\Throwable) {
            }
        }

        return match ($role) {
            /* W127 MIXXTUNE_OFFICE: office staff land on My Office */
            'staff' => redirect('/office'),

            'artist' => redirect()->route(
                'single.artist.dashboard'
            ),

            'label' => redirect()->route(
                'single.label.dashboard'
            ),

            'admin' => redirect()->route(
                'single.admin.dashboard'
            ),

            'super_admin' => redirect()->route(
                'single.super-admin.dashboard'
            ),

            default => redirect()
                ->route('home')
                ->with(
                    'error',
                    'Your account role is not configured.'
                ),
        };
    }
}
