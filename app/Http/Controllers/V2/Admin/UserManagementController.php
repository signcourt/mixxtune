<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\System\UserPanelPermission;
use App\Models\User;
use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use App\Services\V2\UserInvitationService;
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
                    ->whereIn(
                        'invitation_status',
                        [
                            'pending',
                            'sent',
                            'password_pending',
                            'failed',
                            'expired',
                        ]
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

            'username' => [
                'nullable',
                'string',
                'max:40',
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

        $usernameService = app(
            \App\Services\V2\UsernameService::class
        );

        $requestedUsername = trim(
            (string) (
                $validated['username']
                ?? ''
            )
        );

        $username = $requestedUsername === ''
            ? $usernameService->generate(
                $validated['name'],
                $validated['email']
            )
            : $usernameService->normalize(
                $requestedUsername
            );

        if (
            $requestedUsername !== ''
            && strtolower($requestedUsername)
                !== $username
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' =>
                    'Username may contain lowercase letters, numbers and hyphens only.',
            ]);
        }

        if (
            ! $usernameService->validateFormat(
                $username
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' =>
                    'Username must be between 4 and 40 characters.',
            ]);
        }

        if (
            $usernameService->isReserved(
                $username
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' =>
                    'This username is reserved.',
            ]);
        }

        if (
            $usernameService->exists(
                $username
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' =>
                    'This username is already in use.',
            ]);
        }

        $user = DB::transaction(
            function () use (
                $validated,
                $request,
                $username
            ) {
                $sendInvitation =
                    (bool) $validated[
                        'send_invitation'
                    ];

                $token = null;

                $user = User::query()->create([
                    'name' =>
                        $validated['name'],

                    'username' =>
                        $username,

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

                /*
                 * Every artist login requires a linked artists row.
                 * Create it in the same transaction as the user so an
                 * invited artist can open the Artist Dashboard immediately.
                 */
                if (
                    $validated['role'] ===
                    'artist'
                ) {
                    $slugBase = Str::slug(
                        $validated['name']
                    );

                    if ($slugBase === '') {
                        $slugBase = 'artist';
                    }

                    $slug =
                        $slugBase.'-'.$user->id;

                    while (
                        Artist::withTrashed()
                            ->where(
                                'slug',
                                $slug
                            )
                            ->exists()
                    ) {
                        $slug =
                            $slugBase
                            .'-'
                            .$user->id
                            .'-'
                            .Str::lower(
                                Str::random(5)
                            );
                    }

                    do {
                        $publicId =
                            'ART-'
                            .Str::upper(
                                Str::random(12)
                            );
                    } while (
                        Artist::withTrashed()
                            ->where(
                                'public_id',
                                $publicId
                            )
                            ->exists()
                    );

                    Artist::query()->create([
                        'public_id' =>
                            $publicId,

                        'user_id' =>
                            $user->id,

                        'label_id' =>
                            null,

                        'stage_name' =>
                            $validated['name'],

                        'legal_name' =>
                            $validated['name'],

                        'slug' =>
                            $slug,

                        'email' =>
                            strtolower(
                                $validated['email']
                            ),

                        'phone' =>
                            $validated['phone']
                            ?? null,

                        'country' =>
                            $validated['country']
                            ?? 'India',

                        'timezone' =>
                            'Asia/Kolkata',

                        'currency' =>
                            'INR',

                        'account_status' =>
                            $validated[
                                'account_status'
                            ],

                        'kyc_status' =>
                            'pending',

                        'can_receive_splits' =>
                            true,

                        'can_create_releases' =>
                            true,

                        'created_by' =>
                            $request->user()->id,

                        'updated_by' =>
                            $request->user()->id,
                    ]);
                }

                /*
                 * Every label login requires a linked labels row.
                 */
                if (
                    $validated['role'] ===
                    'label'
                ) {

                    $slugBase = Str::slug(
                        $validated['name']
                    );

                    if ($slugBase === '') {
                        $slugBase = 'label';
                    }

                    $slug =
                        $slugBase.'-'.$user->id;

                    while (
                        Label::withTrashed()
                            ->where('slug',$slug)
                            ->exists()
                    ) {

                        $slug =
                            $slugBase
                            .'-'
                            .$user->id
                            .'-'
                            .Str::lower(
                                Str::random(5)
                            );
                    }

                    do {

                        $publicId =
                            'LBL-'
                            .Str::upper(
                                Str::random(12)
                            );

                    } while (

                        Label::withTrashed()
                            ->where(
                                'public_id',
                                $publicId
                            )
                            ->exists()

                    );

                    Label::create([

                        'user_id' =>
                            $user->id,

                        'public_id' =>
                            $publicId,

                        'name' =>
                            $validated['name'],

                        'legal_name' =>
                            $validated['name'],

                        'slug' =>
                            $slug,

                        'email' =>
                            strtolower(
                                $validated['email']
                            ),

                        'phone' =>
                            $validated['phone']
                            ?? null,

                        'country' =>
                            $validated['country']
                            ?? 'India',

                        'timezone' =>
                            'Asia/Kolkata',

                        'currency' =>
                            'INR',

                        'status' =>
                            'active',

                        'created_by' =>
                            $request->user()->id,

                        'updated_by' =>
                            $request->user()->id,

                    ]);

                }


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

        if (
            (bool) $validated['send_invitation']
        ) {
            try {
                app(
                    UserInvitationService::class
                )->send($user);
            } catch (\Throwable $exception) {
                report($exception);

                return redirect()
                    ->route('v2.admin.users.index')
                    ->with(
                        'warning',
                        'User was created, but the invitation email could not be sent: '
                        .$exception->getMessage()
                    );
            }
        }

        $audit->record(
            'user.created',
            'users',
            "User {$user->email} created.",
            [],
            $user->only([
                'id',
                'name',
                'username',
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

        try {
            app(
                UserInvitationService::class
            )->send($user);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'Invitation email failed: '
                .$exception->getMessage()
            );
        }

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
            'Invitation email sent successfully.'
        );
    }

    public function edit(
        Request $request,
        User $user,
        PermissionService $permissions
    ): Response {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $user->load([
            'assignedArtists:id,stage_name,email',
            'assignedLabels:id,name,email',
        ]);

        $panelPermissions =
            UserPanelPermission::query()
                ->firstOrCreate([
                    'user_id' => $user->id,
                ]);

        return Inertia::render(
            'V2/Admin/Users/Edit',
            [
                'role' => 'super_admin',

                'managedUser' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'country' => $user->country,
                    'role' => $user->role,
                    'account_status' =>
                        $user->account_status,
                    'kyc_status' =>
                        $user->kyc_status,
                    'invitation_status' =>
                        $user->invitation_status,
                    'invitation_token' =>
                        $user->invitation_token,
                    'invitation_expires_at' =>
                        $user->invitation_expires_at,
                    'last_login_at' =>
                        $user->last_login_at,
                ],

                'permissions' =>
                    $panelPermissions->only(
                        $this->permissionFields()
                    ),

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

                'assignedArtistIds' =>
                    $user->assignedArtists
                        ->pluck('id'),

                'assignedLabelIds' =>
                    $user->assignedLabels
                        ->pluck('id'),
            ]
        );
    }

    public function update(
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
            $user->id === $request->user()->id
            && $request->input('role') !== 'super_admin',
            422,
            'You cannot remove your own Super Admin role.'
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'username' => [
                'required',
                'string',
                'max:40',
            ],

            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique(
                    'users',
                    'email'
                )->ignore($user->id),
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
                'in:super_admin,admin,label,artist',
            ],

            'account_status' => [
                'required',
                'string',
                'in:active,pending,suspended',
            ],

            'kyc_status' => [
                'nullable',
                'string',
                'in:pending,submitted,verified,rejected',
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

        $usernameService = app(
            \App\Services\V2\UsernameService::class
        );

        $requestedUsername = trim(
            (string) $validated['username']
        );

        $username =
            $usernameService->normalize(
                $requestedUsername
            );

        if (
            strtolower($requestedUsername)
                !== $username
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' =>
                    'Username may contain lowercase letters, numbers and hyphens only.',
            ]);
        }

        if (
            ! $usernameService->validateFormat(
                $username
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' =>
                    'Username must be between 4 and 40 characters.',
            ]);
        }

        if (
            $usernameService->isReserved(
                $username
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' =>
                    'This username is reserved.',
            ]);
        }

        if (
            $usernameService->exists(
                $username,
                $user->id
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' =>
                    'This username is already in use.',
            ]);
        }

        $oldValues = [
            'user' => $user->only([
                'name',
                'username',
                'email',
                'phone',
                'country',
                'role',
                'account_status',
                'kyc_status',
            ]),

            'artist_ids' =>
                $user->assignedArtists()
                    ->pluck('artists.id')
                    ->all(),

            'label_ids' =>
                $user->assignedLabels()
                    ->pluck('labels.id')
                    ->all(),
        ];

        DB::transaction(
            function () use (
                $validated,
                $request,
                $user,
                $username
            ) {
                $user->update([
                    'name' =>
                        $validated['name'],

                    'username' =>
                        $username,

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

                    'kyc_status' =>
                        $validated['kyc_status']
                        ?? $user->kyc_status,
                ]);

                if (
                    $validated['role'] === 'admin'
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
                        $validated['artist_ids']
                        ?? []
                        as $artistId
                    ) {
                        $artistSync[
                            $artistId
                        ] = $pivot;
                    }

                    $labelSync = [];

                    foreach (
                        $validated['label_ids']
                        ?? []
                        as $labelId
                    ) {
                        $labelSync[
                            $labelId
                        ] = $pivot;
                    }

                    $user->assignedArtists()
                        ->sync($artistSync);

                    $user->assignedLabels()
                        ->sync($labelSync);
                } else {
                    $user->assignedArtists()
                        ->detach();

                    $user->assignedLabels()
                        ->detach();
                }

                $permissionValues = [
                    'user_id' => $user->id,
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
            }
        );

        $user->refresh();

        $newValues = [
            'user' => $user->only([
                'name',
                'username',
                'email',
                'phone',
                'country',
                'role',
                'account_status',
                'kyc_status',
            ]),

            'artist_ids' =>
                $user->assignedArtists()
                    ->pluck('artists.id')
                    ->all(),

            'label_ids' =>
                $user->assignedLabels()
                    ->pluck('labels.id')
                    ->all(),
        ];

        $audit->record(
            'user.updated',
            'users',
            "User {$user->email} updated.",
            $oldValues,
            $newValues,
            $user,
            $request
        );

        return back()->with(
            'success',
            'User updated successfully.'
        );
    }

    public function resetPassword(
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
            $user->id === $request->user()->id,
            422,
            'Use your profile page to change your own password.'
        );

        $temporaryPassword =
            Str::password(
                length: 14,
                letters: true,
                numbers: true,
                symbols: true
            );

        $user->update([
            'password' =>
                Hash::make(
                    $temporaryPassword
                ),

            'password_set_at' =>
                null,

            'invitation_status' =>
                'password_reset',

            'invitation_error' =>
                null,
        ]);

        $audit->record(
            'user.password_reset',
            'users',
            "Temporary password generated for {$user->email}.",
            [],
            [
                'password_reset' => true,
            ],
            $user,
            $request
        );

        return back()->with(
            'temporary_password',
            $temporaryPassword
        );
    }

    public function invitationLink(
        Request $request,
        User $user,
        PermissionService $permissions
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        abort_unless(
            $user->invitation_token,
            422,
            'This user does not have an active invitation.'
        );

        abort_if(
            $user->invitation_expires_at
            && now()->greaterThan(
                $user->invitation_expires_at
            ),
            422,
            'Invitation has expired. Resend it first.'
        );

        $link = url(
            '/invitation/'
            . $user->invitation_token
        );

        return back()->with(
            'invitation_link',
            $link
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
