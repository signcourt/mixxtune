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

        return [
            ...parent::share($request),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
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
