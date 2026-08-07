<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserImpersonationController extends Controller
{
    public function start(
        Request $request,
        User $user,
        PermissionService $permissions,
        AuditLogService $audit
    ): RedirectResponse {
        abort_unless(
            $permissions->role(
                $request->user()
            ) === 'super_admin',
            403,
            'Super Admin access required.'
        );

        abort_if(
            $request->session()->has(
                'impersonator_user_id'
            ),
            422,
            'An impersonation session is already active.'
        );

        abort_if(
            $user->id === $request->user()->id,
            422,
            'You cannot impersonate your own account.'
        );

        abort_if(
            $user->role === 'super_admin',
            422,
            'Another Super Admin cannot be impersonated.'
        );

        abort_if(
            $user->account_status !== 'active',
            422,
            'Only active users can be impersonated.'
        );

        $superAdmin =
            $request->user();

        $request->session()->put([
            'impersonator_user_id' =>
                $superAdmin->id,

            'impersonator_name' =>
                $superAdmin->name,

            'impersonator_email' =>
                $superAdmin->email,

            'impersonated_user_id' =>
                $user->id,

            'impersonation_started_at' =>
                now()->toIso8601String(),
        ]);

        $audit->record(
            'user.impersonation_started',
            'users',
            "{$superAdmin->email} started impersonating {$user->email}.",
            [],
            [
                'impersonator_user_id' =>
                    $superAdmin->id,

                'impersonated_user_id' =>
                    $user->id,

                'impersonated_role' =>
                    $user->role,
            ],
            $user,
            $request
        );

        Auth::login(
            $user,
            false
        );

        $request->session()->regenerate();

        return redirect(
            $this->dashboardFor(
                $user
            )
        )->with(
            'success',
            "You are now viewing the panel as {$user->name}."
        );
    }

    public function stop(
        Request $request,
        AuditLogService $audit
    ): RedirectResponse {
        $originalUserId =
            $request->session()->get(
                'impersonator_user_id'
            );

        abort_unless(
            $originalUserId,
            403,
            'No impersonation session is active.'
        );

        $impersonatedUser =
            $request->user();

        $superAdmin =
            User::query()->findOrFail(
                $originalUserId
            );

        abort_unless(
            $superAdmin->role ===
                'super_admin',
            403,
            'Original Super Admin account is invalid.'
        );

        Auth::login(
            $superAdmin,
            false
        );

        $audit->record(
            'user.impersonation_stopped',
            'users',
            "{$superAdmin->email} stopped impersonating {$impersonatedUser?->email}.",
            [
                'impersonated_user_id' =>
                    $impersonatedUser?->id,
            ],
            [
                'restored_user_id' =>
                    $superAdmin->id,
            ],
            $impersonatedUser,
            $request
        );

        $request->session()->forget([
            'impersonator_user_id',
            'impersonator_name',
            'impersonator_email',
            'impersonated_user_id',
            'impersonation_started_at',
        ]);

        $request->session()->regenerate();

        return redirect(
            '/v2/admin/users'
        )->with(
            'success',
            'Returned to Super Admin account.'
        );
    }

    private function dashboardFor(
        User $user
    ): string {
        return match ($user->role) {
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
    }
}
