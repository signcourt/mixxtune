<?php

namespace App\Http\Middleware;

use App\Services\V2\PermissionService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $permissionService = app(
            PermissionService::class
        );

        $permissionData =
            $permissionService->frontend(
                $request->user()
            );

        $branding = app(
            \App\Services\V2\SystemSettingService::class
        )->branding();

        return [
            ...parent::share($request),

            'brand' => $branding,

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                /* W59: one-time values the user pages show (were never sent, so a reset password was lost) */
                'temporary_password' => fn () => $request->session()->get('temporary_password'),
                'invitation_link' => fn () => $request->session()->get('invitation_link'),
            ],
            'impersonation' => fn () => [
                'active' => $request->session()->has(
                    'impersonator_user_id'
                ),

                'impersonator_user_id' =>
                    $request->session()->get(
                        'impersonator_user_id'
                    ),

                'impersonator_name' =>
                    $request->session()->get(
                        'impersonator_name'
                    ),

                'impersonator_email' =>
                    $request->session()->get(
                        'impersonator_email'
                    ),

                'impersonated_user_id' =>
                    $request->session()->get(
                        'impersonated_user_id'
                    ),

                'started_at' =>
                    $request->session()->get(
                        'impersonation_started_at'
                    ),
            ],

            'role' =>
                $permissionData['role'],

            'permissions' =>
                $permissionData[
                    'permissions'
                ],

            'panelPermissions' =>
                $permissionData[
                    'panelPermissions'
                ],

            'notificationCount' => fn () =>
                $request->user()
                    ? \App\Models\Support\PanelNotification::query()
                        ->where('user_id', $request->user()->id)
                        ->whereNull('read_at')
                        ->whereNull('dismissed_at')
                        ->count()
                    : 0,

            'headerNotifications' => fn () =>
                $request->user()
                    ? \App\Models\Support\PanelNotification::query()
                        ->where('user_id', $request->user()->id)
                        ->whereNull('dismissed_at')
                        ->latest('id')
                        ->limit(5)
                        ->get([
                            'id',
                            'title',
                            'message',
                            'severity',
                            'action_url',
                            'read_at',
                            'created_at',
                        ])
                        ->map(fn ($notification) => [
                            'id' => $notification->id,
                            'title' => $notification->title,
                            'message' => $notification->message,
                            'severity' => $notification->severity,
                            'action_url' => $notification->action_url,
                            'read_at' => $notification->read_at,
                            'created_at' => optional(
                                $notification->created_at
                            )->diffForHumans(),
                        ])
                        ->values()
                    : [],

            'maintenance' => \App\Http\Middleware\EnforceMaintenanceMode::state($request),

            /* MIXXTUNE_NOTIFY_TARGETED_V1: newest unread highlighted notice -> banner at the top of the panel */
            'highlightNotice' => fn () => $request->user()
                ? \App\Models\Support\PanelNotification::query()
                    ->where('user_id', $request->user()->id)
                    ->where('type', 'broadcast.manual')
                    ->whereNull('read_at')
                    ->whereNull('dismissed_at')
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get(['id', 'title', 'message', 'action_url', 'severity', 'data'])
                    ->first(fn ($n) => (bool) data_get($n->data, 'highlight'))
                    ?->only(['id', 'title', 'message', 'action_url', 'severity'])
                : null,

            /* MIXXTUNE_SETTINGS_WIRED_V1: limits from Master Settings for upload screens */
            'platformRules' => fn () => $request->user() ? \App\Services\V2\PlatformRules::shared() : null,

            'auth' => [
                'user' =>
                    $request->user(),

                'role' =>
                    $permissionData['role'],

                'permissions' =>
                    $permissionData[
                        'permissions'
                    ],

                'panel_permissions' =>
                    $permissionData[
                        'panelPermissions'
                    ],

                'is_super_admin' =>
                    $permissionData[
                        'isSuperAdmin'
                    ],
            ],
        ];
    }
}
