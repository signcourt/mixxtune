<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\System\UserPanelPermission;
use App\Models\User;
use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        $roleFilter = trim(
            (string) $request->input(
                'role',
                ''
            )
        );

        $status = trim(
            (string) $request->input(
                'status',
                ''
            )
        );

        $query = User::query()
            ->with([
                'assignedArtists:id,stage_name',
                'assignedLabels:id,name',
            ]);

        if ($search !== '') {
            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'phone',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'label_name',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if ($roleFilter !== '') {
            $query->where(
                'role',
                $roleFilter
            );
        }

        if ($status !== '') {
            $query->where(
                'account_status',
                $status
            );
        }

        $summary = [
            'total' =>
                User::query()->count(),

            'super_admins' =>
                User::query()
                    ->where(
                        'role',
                        'super_admin'
                    )
                    ->count(),

            'admins' =>
                User::query()
                    ->where(
                        'role',
                        'admin'
                    )
                    ->count(),

            'labels' =>
                User::query()
                    ->where(
                        'role',
                        'label'
                    )
                    ->count(),

            'artists' =>
                User::query()
                    ->where(
                        'role',
                        'artist'
                    )
                    ->count(),

            'pending_invitations' =>
                User::query()
                    ->where(
                        'invitation_status',
                        'pending'
                    )
                    ->count(),

            'suspended' =>
                User::query()
                    ->where(
                        'account_status',
                        'suspended'
                    )
                    ->count(),
        ];

        return Inertia::render(
            'V2/Admin/Users/Index',
            [
                'role' => 'super_admin',

                'summary' =>
                    $summary,

                'filters' => [
                    'search' => $search,
                    'role' => $roleFilter,
                    'status' => $status,
                ],

                'users' =>
                    $query
                        ->orderByDesc('id')
                        ->paginate(30)
                        ->withQueryString(),
            ]
        );
    }

    public function create(
        Request $request,
        PermissionService $permissions
    ): Response {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        return Inertia::render(
            'V2/Admin/Users/Create',
            [
                'role' => 'super_admin',

                'artists' =>
                    Artist::query()
                        ->orderBy('stage_name')
                        ->get([
                            'id',
                            'stage_name',
                            'email',
                        ]),

                'labels' =>
                    Label::query()
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'email',
                        ]),
            ]
        );
    }

    public function store(
        Request $request,
        PermissionService $permissions,
        AuditLogService $audit
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique('users', 'email'),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'country' => [
                'nullable',
                'string',
                'max:100',
            ],

            'role' => [
                'required',
                'string',
                'in:admin,label,artist',
            ],

            'account_status' => [
                'required',
                'string',
                'in:active,pending,suspended',
            ],

            'send_invitation' => [
                'required',
                'boolean',
            ],

            'artist_ids' => [
                'nullable',
                'array',
            ],

            'artist_ids.*' => [
                'integer',
                'distinct',
                'exists:artists,id',
            ],

            'label_ids' => [
                'nullable',
                'array',
            ],

            'label_ids.*' => [
                'integer',
                'distinct',
                'exists:labels,id',
            ],

            'permissions' => [
                'nullable',
                'array',
            ],
        ]);

        $user = DB::transaction(
            function () use (
                $validated,
                $request
            ) {
                $sendInvitation =
                    (bool) $validated[
                        'send_invitation'
                    ];

                $token = $sendInvitation
                    ? Str::random(64)
                    : null;

                $user = User::query()->create([
                    'name' =>
                        $validated['name'],

                    'email' =>
                        strtolower(
                            $validated['email']
                        ),

                    'phone' =>
                        $validated['phone']
                        ?? null,

                    'country' =>
                        $validated['country']
                        ?? null,

                    'role' =>
                        $validated['role'],

                    'account_status' =>
                        $validated[
                            'account_status'
                        ],

                    'password' =>
                        Hash::make(
                            Str::random(32)
                        ),

                    'email_verified_at' =>
                        $sendInvitation
                            ? null
                            : now(),

                    'invitation_status' =>
                        $sendInvitation
                            ? 'pending'
                            : 'not_required',

                    'invitation_token' =>
                        $token,

                    'invitation_sent_at' =>
                        $sendInvitation
                            ? now()
                            : null,

                    'invitation_expires_at' =>
                        $sendInvitation
                            ? now()->addDays(7)
                            : null,

                    'invitation_count' =>
                        $sendInvitation
                            ? 1
                            : 0,
                ]);

                if (
                    $validated['role'] ===
                    'admin'
                ) {
                    $pivot = [
                        'assignment_role' =>
                            'manager',

                        'can_view' => true,
                        'can_edit' => true,

                        'can_manage_releases' =>
                            true,

                        'can_manage_team' =>
                            false,

                        'can_manage_splits' =>
                            false,

                        'assigned_by' =>
                            $request->user()->id,
                    ];

                    $artistSync = [];

                    foreach (
                        $validated[
                            'artist_ids'
                        ] ?? []
                        as $artistId
                    ) {
                        $artistSync[
                            $artistId
                        ] = $pivot;
                    }

                    $labelSync = [];

                    foreach (
                        $validated[
                            'label_ids'
                        ] ?? []
                        as $labelId
                    ) {
                        $labelSync[
                            $labelId
                        ] = $pivot;
                    }

                    $user
                        ->assignedArtists()
                        ->sync($artistSync);

                    $user
                        ->assignedLabels()
                        ->sync($labelSync);
                }

                $permissionValues = [
                    'user_id' =>
                        $user->id,

                    'updated_by' =>
                        $request->user()->id,
                ];

                foreach (
                    $this->permissionFields()
                    as $field
                ) {
                    $permissionValues[$field] =
                        (bool) data_get(
                            $validated,
                            "permissions.{$field}",
                            false
                        );
                }

                UserPanelPermission::query()
                    ->updateOrCreate(
                        [
                            'user_id' =>
                                $user->id,
                        ],
                        $permissionValues
                    );

                return $user;
            }
        );

        $audit->record(
            'user.created',
            'users',
            "User {$user->email} created.",
            [],
            $user->only([
                'id',
                'name',
                'email',
                'role',
                'account_status',
            ]),
            $user,
            $request
        );

        return redirect()
            ->route(
                'v2.admin.users.index'
            )
            ->with(
                'success',
                'User created successfully.'
            );
    }

    public function toggleStatus(
        Request $request,
        User $user,
        PermissionService $permissions,
        AuditLogService $audit
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        abort_if(
            $user->id ===
                $request->user()->id,
            422,
            'You cannot suspend your own account.'
        );

        abort_if(
            $user->role ===
                'super_admin',
            422,
            'Super Admin account cannot be suspended here.'
        );

        $oldStatus =
            $user->account_status;

        $newStatus =
            $oldStatus === 'active'
                ? 'suspended'
                : 'active';

        $user->update([
            'account_status' =>
                $newStatus,
        ]);

        $audit->record(
            'user.status_updated',
            'users',
            "User status changed to {$newStatus}.",
            [
                'account_status' =>
                    $oldStatus,
            ],
            [
                'account_status' =>
                    $newStatus,
            ],
            $user,
            $request
        );

        return back()->with(
            'success',
            'User status updated.'
        );
    }

    public function resendInvitation(
        Request $request,
        User $user,
        PermissionService $permissions,
        AuditLogService $audit
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $user->update([
            'invitation_status' =>
                'pending',

            'invitation_token' =>
                Str::random(64),

            'invitation_sent_at' =>
                now(),

            'invitation_expires_at' =>
                now()->addDays(7),

            'invitation_count' =>
                ((int) $user
                    ->invitation_count) + 1,

            'invitation_error' =>
                null,
        ]);

        $audit->record(
            'user.invitation_resent',
            'users',
            "Invitation regenerated for {$user->email}.",
            [],
            [
                'invitation_status' =>
                    'pending',
            ],
            $user,
            $request
        );

        return back()->with(
            'success',
            'Invitation link regenerated.'
        );
    }

    private function authorizeSuperAdmin(
        Request $request,
        PermissionService $permissions
    ): void {
        abort_unless(
            $permissions->role(
                $request->user()
            ) === 'super_admin',
            403,
            'Super Admin access required.'
        );
    }

    private function permissionFields(): array
    {
        return [
            'can_view_catalogue',
            'can_create_releases',
            'can_manage_releases',
            'can_view_reports',
            'can_view_royalties',
            'can_manage_wallet',
            'can_manage_withdrawals',
            'can_manage_users',
            'can_manage_support',
            'can_manage_settings',
            'can_manage_delivery',
            'can_manage_identifiers',
        ];
    }
}
