<?php

namespace App\Http\Controllers\V2\Admin;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Services\V2\UsernameService;
use App\Services\V2\UserInvitationService;
use App\Services\V2\ClientIdService;
use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\User;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportEarningsRecalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentManagementController extends Controller
{
    public function admins(
        Request $request,
        PermissionService $permissions
    ): Response {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $admins = User::query()
            ->where(
                'role',
                'admin'
            )
            ->withCount([
                'assignedArtists',
                'assignedLabels',
            ])
            ->orderBy('name')
            ->paginate(25);

        return Inertia::render(
            'V2/Admin/Admins/Index',
            [
                'role' => 'super_admin',
                'admins' => $admins,
            ]
        );
    }

    public function showAdmin(
        Request $request,
        User $admin,
        PermissionService $permissions
    ): Response {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        abort_unless(
            $admin->role === 'admin',
            404,
            'Manager not found.'
        );

        $admin->load([
            'assignedArtists:id,stage_name,email,account_status',
            'assignedLabels:id,name,email,status',
        ]);

        return Inertia::render(
            'V2/Admin/Admins/Show',
            [
                'role' => 'super_admin',

                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'account_status' =>
                        $admin->account_status,
                ],

                'artists' =>
                    Artist::query()
                        ->select([
                            'id',
                            'stage_name',
                            'email',
                            'account_status',
                        ])
                        ->orderBy('stage_name')
                        ->get(),

                'labels' =>
                    Label::query()
                        ->select([
                            'id',
                            'name',
                            'email',
                            'status',
                        ])
                        ->orderBy('name')
                        ->get(),

                'assignedArtistIds' =>
                    $admin
                        ->assignedArtists
                        ->pluck('id'),

                'assignedLabelIds' =>
                    $admin
                        ->assignedLabels
                        ->pluck('id'),
            ]
        );
    }

    public function updateAssignments(
        Request $request,
        User $admin,
        PermissionService $permissions
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        abort_unless(
            $admin->role === 'admin',
            404
        );

        $validated = $request->validate([
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

            'assignment_role' => [
                'required',
                'string',
                'in:manager,reviewer,finance,support,custom',
            ],

            'can_view' => [
                'required',
                'boolean',
            ],

            'can_edit' => [
                'required',
                'boolean',
            ],

            'can_manage_releases' => [
                'required',
                'boolean',
            ],

            'can_manage_team' => [
                'required',
                'boolean',
            ],

            'can_manage_splits' => [
                'required',
                'boolean',
            ],
        ]);

        $pivot = [
            'assignment_role' =>
                $validated['assignment_role'],

            'can_view' =>
                $validated['can_view'],

            'can_edit' =>
                $validated['can_edit'],

            'can_manage_releases' =>
                $validated[
                    'can_manage_releases'
                ],

            'can_manage_team' =>
                $validated[
                    'can_manage_team'
                ],

            'can_manage_splits' =>
                $validated[
                    'can_manage_splits'
                ],

            'assigned_by' =>
                $request->user()->id,
        ];

        $artistSync = [];

        foreach (
            $validated['artist_ids'] ?? []
            as $artistId
        ) {
            $artistSync[$artistId] = $pivot;
        }

        $labelSync = [];

        foreach (
            $validated['label_ids'] ?? []
            as $labelId
        ) {
            $labelSync[$labelId] = $pivot;
        }

        DB::transaction(
            function () use (
                $admin,
                $artistSync,
                $labelSync
            ) {
                $admin
                    ->assignedArtists()
                    ->sync($artistSync);

                $admin
                    ->assignedLabels()
                    ->sync($labelSync);
            }
        );

        return back()->with(
            'success',
            'Admin assignments updated.'
        );
    }

    public function assignLabelAdmin(
        Request $request,
        Label $label,
        PermissionService $permissions
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'admin_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
        ]);

        $adminId = isset($validated['admin_id'])
            && $validated['admin_id']
            ? (int) $validated['admin_id']
            : null;

        if ($adminId) {
            $validAdmin = User::query()
                ->whereKey($adminId)
                ->where('role', 'admin')
                ->where('account_status', 'active')
                ->exists();

            if (! $validAdmin) {
                throw ValidationException::withMessages([
                    'admin_id' =>
                        'Selected user is not an active Admin.',
                ]);
            }
        }

        $normalizedName = $this->labelGroupKey($label);

        $groupLabelIds = Label::query()
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'user_id'])
            ->filter(
                fn ($candidate) =>
                    $this->labelGroupKey($candidate) === $normalizedName
            )
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        DB::transaction(function () use (
            $groupLabelIds,
            $adminId,
            $request
        ) {
            /*
             * IMPORTANT:
             * Remove only Manager assignments.
             * Owner assignments must remain untouched.
             */
            DB::table('admin_label_assignments')
                ->whereIn('label_id', $groupLabelIds)
                ->where('assignment_role', 'manager')
                ->delete();

            if ($adminId) {
                $now = now();

                $rows = $groupLabelIds
                    ->map(
                        fn ($labelId) => [
                            'user_id' => $adminId,
                            'label_id' => $labelId,
                            'assignment_role' => 'manager',
                            'can_view' => true,
                            'can_edit' => true,
                            'can_manage_releases' => true,
                            'can_manage_team' => false,
                            'can_manage_splits' => true,
                            'assigned_by' =>
                                $request->user()->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    )
                    ->all();

                if ($rows !== []) {
                    DB::table('admin_label_assignments')
                        ->insert($rows);
                }
            }
        });

        return back()->with(
            'success',
            'Manager assignment updated for the consolidated label account.'
        );
    }

    public function assignArtistAdmin(
        Request $request,
        Artist $artist,
        PermissionService $permissions
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'admin_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
        ]);

        $adminId = isset($validated['admin_id'])
            && $validated['admin_id']
            ? (int) $validated['admin_id']
            : null;

        if ($adminId) {
            $validAdmin = User::query()
                ->whereKey($adminId)
                ->where('role', 'admin')
                ->where('account_status', 'active')
                ->exists();

            if (! $validAdmin) {
                throw ValidationException::withMessages([
                    'admin_id' =>
                        'Selected user is not an active Admin.',
                ]);
            }
        }

        DB::transaction(function () use (
            $artist,
            $adminId,
            $request
        ) {
            /* W59: only manager rows are replaced (other assignment kinds stay) */
            DB::table('admin_artist_assignments')
                ->where('artist_id', $artist->id)
                ->where('assignment_role', 'manager')
                ->delete();

            if ($adminId) {
                DB::table('admin_artist_assignments')
                    ->insert([
                        'user_id' => $adminId,
                        'artist_id' => $artist->id,
                        'assignment_role' => 'manager',
                        'can_view' => true,
                        'can_edit' => true,
                        'can_manage_releases' => true,
                        'can_manage_team' => false,
                        'can_manage_splits' => true,
                        'assigned_by' =>
                            $request->user()->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        });

        return back()->with(
            'success',
            $adminId
                ? 'Artist admin assigned.'
                : 'Artist admin unassigned.'
        );
    }

    public function artists(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $this->authorizeAdmin(
            $request,
            $permissions
        );

        $query = Artist::query()
            ->with([
                'label:id,name',
                'assignedAdmins:id,name,email',
            ])
            ->withCount('releases');

        if ($role === 'admin') {
            $query->whereHas(
                'assignedAdmins',
                fn ($builder) =>
                    $builder->where(
                        'users.id',
                        $request->user()->id
                    )
            );
        }

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        $labelId = $request->integer('label_id');

        if ($labelId > 0) {
            $requestedLabel = Label::query()
                ->whereKey($labelId)
                ->whereNull('deleted_at')
                ->first(['id', 'name', 'user_id']);

            abort_unless(
                $requestedLabel,
                404,
                'Label not found.'
            );

            $normalizedName = $this->labelGroupKey($requestedLabel);

            $logicalLabelIds = Label::query()
                ->whereNull('deleted_at')
                ->get(['id', 'name', 'user_id'])
                ->filter(
                    fn ($candidate) =>
                        $this->labelGroupKey($candidate) === $normalizedName
                )
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($role === 'admin') {
                /*
                 * Manager must have access to this logical
                 * label group. FF361D assigns every physical
                 * member of the group together.
                 */
                $allowedLabel = DB::table(
                    'admin_label_assignments'
                )
                    ->where(
                        'user_id',
                        $request->user()->id
                    )
                    ->whereIn(
                        'label_id',
                        $logicalLabelIds
                    )
                    ->exists();

                abort_unless(
                    $allowedLabel,
                    403,
                    'Label access denied.'
                );
            }

            /*
             * Existing role scope above remains in place,
             * so this filter cannot widen Manager access.
             */
            $query->whereIn(
                'label_id',
                $logicalLabelIds
            );
        }

        if ($search !== '') {
            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'stage_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'legal_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        return Inertia::render(
            'V2/Admin/Artists/Index',
            [
                'role' => $role,
                'search' => $search,
                'labelId' => $labelId > 0
                    ? $labelId
                    : null,

                'admins' =>
                    $role === 'super_admin'
                        ? User::query()
                            ->where('role', 'admin')
                            ->where(
                                'account_status',
                                'active'
                            )
                            ->orderBy('name')
                            ->get([
                                'id',
                                'name',
                                'email',
                            ])
                        : collect(),

                'artists' =>
                    $query
                        ->orderBy('stage_name')
                        ->paginate(25)
                        ->withQueryString(),
            ]
        );
    }

    public function createArtist(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $this->authorizeAdmin(
            $request,
            $permissions
        );

        $user = $request->user();

        $labelsQuery = DB::table('labels')
            ->whereNull('deleted_at')
            ->orderBy('name');

        if ($role !== 'super_admin') {
            $labelsQuery->whereIn(
                'id',
                DB::table(
                    'admin_label_assignments'
                )
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->pluck('label_id')
            );
        }

        $labels = $labelsQuery
            ->get([
                'id',
                'public_id',
                'name',
            ]);

        $admins = collect();

        if ($role === 'super_admin') {
            $admins = User::query()
                ->where('role', 'admin')
                ->where(
                    'account_status',
                    'active'
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'email',
                ]);
        }

        return Inertia::render(
            'V2/Admin/Artists/Create',
            [
                'role' => $role,
                'labels' => $labels,
                'admins' => $admins,
            ]
        );
    }

    public function storeArtist(
        Request $request,
        PermissionService $permissions,
        UsernameService $usernameService,
        UserInvitationService $invitationService
    ): RedirectResponse {
        $role = $this->authorizeAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'stage_name' => [
                'required',
                'string',
                'max:150',
            ],
            'legal_name' => [
                'nullable',
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
                'unique:users,email',
                'unique:artists,email',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],
            'country' => [
                'required',
                'string',
                'max:100',
            ],
            'state_code' => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Za-z]{2}$/',
            ],
            'timezone' => [
                'required',
                'string',
                'max:100',
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
            ],
            'account_status' => [
                'required',
                'string',
                'in:active,pending',
            ],
            'label_id' => [
                'nullable',
                'integer',
                /* W59: never attach a new artist to a deleted label */
                \Illuminate\Validation\Rule::exists('labels', 'id')->whereNull('deleted_at'),
            ],
            'admin_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'can_create_releases' => [
                'required',
                'boolean',
            ],
            'can_receive_splits' => [
                'required',
                'boolean',
            ],
            'send_invitation' => [
                'required',
                'boolean',
            ],
        ]);

        $operator = $request->user();

        $labelId = isset(
            $validated['label_id']
        )
            ? (int) $validated['label_id']
            : null;

        /*
         * Normal Admin may only attach the Artist
         * to a Label assigned to that Admin.
         */
        if (
            $role !== 'super_admin'
            && $labelId
        ) {
            abort_unless(
                DB::table(
                    'admin_label_assignments'
                )
                    ->where(
                        'user_id',
                        $operator->id
                    )
                    ->where(
                        'label_id',
                        $labelId
                    )
                    ->exists(),
                403,
                'Label access denied.'
            );
        }

        /*
         * Only Super Admin may explicitly select
         * another Admin.
         */
        $assignedAdminId =
            $role === 'super_admin'
                ? (
                    isset($validated['admin_id'])
                    && $validated['admin_id']
                        ? (int) $validated['admin_id']
                        : null
                )
                : (int) $operator->id;

        if ($assignedAdminId) {
            $validAdmin = User::query()
                ->whereKey($assignedAdminId)
                ->where('role', 'admin')
                ->exists();

            if (! $validAdmin) {
                throw ValidationException::withMessages([
                    'admin_id' =>
                        'Selected user is not a valid Admin.',
                ]);
            }
        }

        $email = Str::lower(
            trim($validated['email'])
        );

        $requestedUsername = trim(
            (string) (
                $validated['username']
                ?? ''
            )
        );

        $username =
            $requestedUsername === ''
                ? $usernameService->generate(
                    $validated['stage_name'],
                    $email
                )
                : $usernameService->normalize(
                    $requestedUsername
                );

        if (
            $requestedUsername !== ''
            && Str::lower(
                $requestedUsername
            ) !== $username
        ) {
            throw ValidationException::withMessages([
                'username' =>
                    'Username may contain lowercase letters, numbers and hyphens only.',
            ]);
        }

        if (
            ! $usernameService->validateFormat(
                $username
            )
        ) {
            throw ValidationException::withMessages([
                'username' =>
                    'Username must be between 4 and 40 characters.',
            ]);
        }

        if (
            $usernameService->isReserved(
                $username
            )
        ) {
            throw ValidationException::withMessages([
                'username' =>
                    'This username is reserved.',
            ]);
        }

        if (
            $usernameService->exists(
                $username
            )
        ) {
            throw ValidationException::withMessages([
                'username' =>
                    'This username is already in use.',
            ]);
        }

        $sendInvitation =
            (bool) $validated[
                'send_invitation'
            ];

        $result = DB::transaction(
            function () use (
                $validated,
                $operator,
                $username,
                $email,
                $sendInvitation,
                $labelId,
                $assignedAdminId
            ) {
                $user = User::query()->create([
                    'name' =>
                        $validated['stage_name'],

                    'username' => $username,

                    'email' => $email,

                    'phone' =>
                        $validated['phone']
                        ?? null,

                    'country' =>
                        $validated['country'],

                    'state_code' =>
                        strtoupper(
                            $validated[
                                'state_code'
                            ]
                        ),

                    'role' => 'artist',

                    'account_status' =>
                        $validated[
                            'account_status'
                        ],

                    'kyc_status' => 'pending',

                    'password' =>
                        Hash::make(
                            Str::random(40)
                        ),

                    'email_verified_at' =>
                        $sendInvitation
                            ? null
                            : now(),

                    'invitation_status' =>
                        $sendInvitation
                            ? 'pending'
                            : 'not_required',

                    'invitation_sent_at' =>
                        null,

                    'invitation_expires_at' =>
                        null,

                    'invitation_count' => 0,
                ]);

                $countryCode =
                    strcasecmp(
                        trim(
                            $validated['country']
                        ),
                        'India'
                    ) === 0
                        ? 'IN'
                        : strtoupper(
                            substr(
                                preg_replace(
                                    '/[^A-Za-z]/',
                                    '',
                                    $validated[
                                        'country'
                                    ]
                                ),
                                0,
                                2
                            )
                        );

                if (
                    strlen($countryCode) !== 2
                ) {
                    throw ValidationException::withMessages([
                        'country' => [
                            'A valid country is required to generate the Client ID.',
                        ],
                    ]);
                }

                $user->forceFill([
                    'client_id' =>
                        app(
                            ClientIdService::class
                        )->generate(
                            'artist',
                            $countryCode,
                            strtoupper(
                                $validated[
                                    'state_code'
                                ]
                            )
                        ),
                ])->save();

                $slugBase = Str::slug(
                    $validated['stage_name']
                );

                if ($slugBase === '') {
                    $slugBase =
                        'artist-'.$user->id;
                }

                $slug = $slugBase;
                $suffix = 2;

                while (
                    DB::table('artists')
                        ->where(
                            'slug',
                            $slug
                        )
                        ->exists()
                ) {
                    $slug =
                        $slugBase
                        .'-'
                        .$suffix;

                    $suffix++;
                }

                do {
                    $publicId =
                        'ART'
                        .strtoupper(
                            Str::random(16)
                        );
                } while (
                    DB::table('artists')
                        ->where(
                            'public_id',
                            $publicId
                        )
                        ->exists()
                );

                $artistId = DB::table(
                    'artists'
                )->insertGetId([
                    'public_id' => $publicId,
                    'user_id' => $user->id,
                    'label_id' => $labelId,

                    'stage_name' =>
                        $validated[
                            'stage_name'
                        ],

                    'legal_name' =>
                        $validated[
                            'legal_name'
                        ] ?? null,

                    'slug' => $slug,
                    'email' => $email,

                    'phone' =>
                        $validated['phone']
                        ?? null,

                    'country' =>
                        $validated['country'],

                    'timezone' =>
                        $validated['timezone'],

                    'currency' =>
                        Str::upper(
                            $validated[
                                'currency'
                            ]
                        ),

                    'account_status' =>
                        $validated[
                            'account_status'
                        ],

                    'kyc_status' => 'pending',

                    'can_receive_splits' =>
                        (bool) $validated[
                            'can_receive_splits'
                        ],

                    'can_create_releases' =>
                        (bool) $validated[
                            'can_create_releases'
                        ],

                    'created_by' =>
                        $operator->id,

                    'updated_by' =>
                        $operator->id,

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($assignedAdminId) {
                    DB::table(
                        'admin_artist_assignments'
                    )->updateOrInsert(
                        [
                            'user_id' =>
                                $assignedAdminId,
                            'artist_id' =>
                                $artistId,
                        ],
                        [
                            'assignment_role' =>
                                'manager',

                            'can_view' => true,
                            'can_edit' => true,

                            'can_manage_releases' =>
                                true,

                            'can_manage_team' =>
                                false,

                            'can_manage_splits' =>
                                true,

                            'assigned_by' =>
                                $operator->id,

                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }

                return [
                    'user' => $user,
                    'artist_id' => $artistId,
                ];
            }
        );

        if ($sendInvitation) {
            try {
                $invitationService->send(
                    $result['user']
                );
            } catch (\Throwable $exception) {
                report($exception);

                return redirect()
                    ->route(
                        'v2.admin.artists.index'
                    )
                    ->with(
                        'warning',
                        'Artist was created, but invitation email could not be sent: '
                        .$exception->getMessage()
                    );
            }
        }

        return redirect()
            ->route(
                'v2.admin.artists.index'
            )
            ->with(
                'success',
                'Artist account created successfully.'
            );
    }

    public function showArtist(
        Request $request,
        Artist $artist,
        PermissionService $permissions
    ): Response {
        $role = $this->authorizeAdmin(
            $request,
            $permissions
        );

        $this->authorizeArtistAccess(
            $request,
            $artist,
            $role
        );

        $artist->load([
            'label:id,name',
            'assignedAdmins:id,name,email',
        ]);

        $artist->loadCount('releases');

        return Inertia::render(
            'V2/Admin/Artists/Show',
            [
                'role' => $role,
                'artist' => $artist,
            ]
        );
    }

    public function editArtist(
        Request $request,
        Artist $artist,
        PermissionService $permissions
    ): Response {
        $role = $this->authorizeAdmin(
            $request,
            $permissions
        );

        $this->authorizeArtistAccess(
            $request,
            $artist,
            $role
        );

        $artist->load([
            'label:id,name',
            'assignedAdmins:id,name,email',
        ]);

        return Inertia::render(
            'V2/Admin/Artists/Edit',
            [
                'role' => $role,
                'artist' => $artist,
            ]
        );
    }

    public function updateArtist(
        Request $request,
        Artist $artist,
        PermissionService $permissions
    ): RedirectResponse {
        $role = $this->authorizeAdmin(
            $request,
            $permissions
        );

        $this->authorizeArtistAccess(
            $request,
            $artist,
            $role
        );

        $validated = $request->validate([
            'stage_name' => [
                'required',
                'string',
                'max:255',
            ],
            'legal_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'country' => [
                'required',
                'string',
                'max:100',
            ],
            'timezone' => [
                'required',
                'string',
                'max:100',
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
            ],
            'account_status' => [
                'required',
                'string',
                'in:active,pending,suspended',
            ],
            'kyc_status' => [
                'required',
                'string',
                'in:pending,submitted,verified,rejected',
            ],
            'can_create_releases' => [
                'required',
                'boolean',
            ],
            'can_receive_splits' => [
                'required',
                'boolean',
            ],
            'can_withdraw' => [
                'sometimes',
                'boolean',
            ],
        ]);

        if ($role !== 'super_admin') {
            unset($validated['can_withdraw']);

            /* W59: only the super admin decides KYC and account status (managers could set "verified") */
            $validated['kyc_status'] = $artist->kyc_status;
            $validated['account_status'] = $artist->account_status;
        }

        /* W59: the same e-mail cannot belong to two artists */
        if (
            strtolower(trim((string) $validated['email'])) !== strtolower((string) $artist->email)
            && Artist::query()->whereNull('deleted_at')->where('id', '!=', $artist->id)->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $validated['email']))])->exists()
        ) {
            throw ValidationException::withMessages(['email' => 'Another artist already uses this e-mail.']);
        }

        $withdrawalStatus = array_key_exists(
            'can_withdraw',
            $validated
        )
            ? (bool) $validated['can_withdraw']
            : null;

        /*
         * Withdrawal eligibility belongs to the financial
         * owner account because wallet / payout profile are
         * User-level.
         *
         * Keep every non-deleted Label and Artist belonging
         * to that owner synchronized.
         */
        if (
            $role === 'super_admin'
            && $withdrawalStatus !== null
            && $artist->user_id
        ) {
            Label::query()
                ->where(
                    'user_id',
                    $artist->user_id
                )
                ->whereNull('deleted_at')
                ->update([
                    'can_withdraw' =>
                        $withdrawalStatus,
                    'updated_at' => now(),
                ]);

            Artist::query()
                ->where(
                    'user_id',
                    $artist->user_id
                )
                ->whereNull('deleted_at')
                ->update([
                    'can_withdraw' =>
                        $withdrawalStatus,
                    'updated_at' => now(),
                ]);
        }

        $validated['updated_by'] =
            $request->user()->id;

        $artist->update($validated);

        return redirect()
            ->route('v2.admin.artists.index')
            ->with(
                'success',
                'Artist updated successfully.'
            );
    }

    public function destroyArtist(
        Request $request,
        Artist $artist,
        PermissionService $permissions
    ): RedirectResponse {
        $role = $this->authorizeAdmin(
            $request,
            $permissions
        );

        $this->authorizeArtistAccess(
            $request,
            $artist,
            $role
        );

        /*
         * W59: an artist is deleted only when nothing depends on it -
         * releases, revenue rows, money in the wallet, credits on tracks,
         * a revenue share - and only by the super admin.
         */
        if ($role !== 'super_admin') {
            return back()->withErrors(['artist' => 'Only the super admin can delete an artist.']);
        }

        $blocks = [];
        if ($artist->releases()->exists()) {
            $blocks[] = 'releases';
        }
        foreach ([
            'report_rows' => 'revenue report rows',
            'royalty_statements' => 'statements',
            'royalty_ledgers' => 'royalty ledger lines',
            'track_contributors' => 'track credits',
        ] as $table => $what) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table) && \Illuminate\Support\Facades\Schema::hasColumn($table, 'artist_id') && DB::table($table)->where('artist_id', $artist->id)->exists()) {
                $blocks[] = $what;
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('label_revenue_shares') && DB::table('label_revenue_shares')->where('beneficiary_type', 'artist')->where('beneficiary_id', $artist->id)->where('is_active', true)->exists()) {
            $blocks[] = 'an active revenue share';
        }

        if ($blocks !== []) {
            return back()->withErrors(['artist' => '"'.($artist->stage_name ?: $artist->legal_name).'" has '.implode(', ', $blocks).' - it cannot be deleted. Edit it or set it inactive instead.']);
        }

        $artist->delete();

        try {
            app(\App\Services\V2\AuditLogService::class)->record('artist.deleted', 'artists', 'Artist "'.($artist->stage_name ?: $artist->legal_name).'" deleted (nothing linked).', ['id' => $artist->id], [], $artist, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()
            ->route('v2.admin.artists.index')
            ->with(
                'success',
                'Artist deleted successfully.'
            );
    }

    private function authorizeArtistAccess(
        Request $request,
        Artist $artist,
        string $role
    ): void {
        if ($role === 'super_admin') {
            return;
        }

        abort_unless(
            $artist
                ->assignedAdmins()
                ->where(
                    'users.id',
                    $request->user()->id
                )
                ->exists(),
            403,
            'Artist access denied.'
        );
    }

    public function updateLabelWithdrawalStatus(
        Request $request,
        Label $label,
        PermissionService $permissions
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'can_withdraw' => [
                'required',
                'boolean',
            ],
        ]);

        $ownerId = (int) $label->user_id;

        abort_if(
            $ownerId <= 0,
            422,
            'The label financial owner could not be resolved.'
        );

        $withdrawalStatus =
            (bool) $validated['can_withdraw'];

        /*
         * Wallet / payout profile are User-level.
         * Synchronize withdrawal status across every
         * non-deleted Label and Artist owned by the same
         * financial account.
         */
        Label::query()
            ->where('user_id', $ownerId)
            ->whereNull('deleted_at')
            ->update([
                'can_withdraw' =>
                    $withdrawalStatus,
                'updated_at' => now(),
            ]);

        Artist::query()
            ->where('user_id', $ownerId)
            ->whereNull('deleted_at')
            ->update([
                'can_withdraw' =>
                    $withdrawalStatus,
                'updated_at' => now(),
            ]);

        return back()->with(
            'success',
            $withdrawalStatus
                ? 'Withdrawals enabled for this account.'
                : 'Withdrawals suspended for this account.'
        );
    }

    /**
     * FF419-C
     *
     * Update the canonical commercial revenue rate for a label.
     *
     * Only review_pending report imports may be recalculated by
     * ReportEarningsRecalculationService. Approved/published
     * financial snapshots remain immutable.
     */
    public function updateLabelRevenueRate(
        Request $request,
        Label $label,
        PermissionService $permissions,
        ReportEarningsRecalculationService $recalculator
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        abort_if(
            $label->deleted_at !== null,
            404,
            'Label not found.'
        );

        $validated = $request->validate([
            'revenue_share_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
        ]);

        $rate = round(
            (float) $validated['revenue_share_percentage'],
            2
        );

        DB::transaction(
            function () use (
                $label,
                $rate,
                $recalculator
            ): void {
                $lockedLabel = Label::query()
                    ->whereKey($label->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedLabel->revenue_share_percentage =
                    $rate;

                $lockedLabel->save();

                /*
                 * W59: the row on the Labels page stands for every record
                 * with the same name and owner - all of them get the %
                 * (before, only one did and the others kept an old %).
                 */
                $key = $this->labelGroupKey($lockedLabel);
                $siblings = Label::query()
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $lockedLabel->id)
                    ->get(['id', 'name', 'user_id'])
                    ->filter(fn ($c) => $this->labelGroupKey($c) === $key);

                foreach ($siblings as $sibling) {
                    $twin = Label::query()->whereKey($sibling->id)->lockForUpdate()->first();
                    if ($twin && round((float) $twin->revenue_share_percentage, 2) !== $rate) {
                        $twin->revenue_share_percentage = $rate;
                        $twin->save();
                        $recalculator->recalculateForLabel($twin);
                    }
                }

                /*
                 * Canonical recalculation.
                 *
                 * The service itself enforces the financial
                 * lifecycle freeze and touches only mapped rows
                 * belonging to review_pending imports.
                 *
                 * No wallet/publication operation occurs here.
                 */
                $recalculator->recalculateForLabel(
                    $lockedLabel
                );
            }
        );

        return back()->with(
            'success',
            'Label revenue rate updated to '
                .number_format($rate, 2)
                .'%. Pending review earnings recalculated.'
        );
    }


    /**
     * MIXXTUNE_LABEL_BULK_ACTIONS_V1
     *
     * Bulk operations for labels selected from the consolidated
     * Labels administration table.
     *
     * Financial safety:
     * - Revenue Rate uses the canonical recalculation service.
     * - That service only recalculates review_pending imports.
     * - No wallet/publication operation occurs here.
     *
     * Withdrawal safety:
     * - Withdrawal permission is financial-account/User level.
     * - All Labels + Artists owned by the selected financial
     *   account are synchronized, matching the single-row action.
     */
    public function bulkLabelAction(
        Request $request,
        PermissionService $permissions,
        ReportEarningsRecalculationService $recalculator
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'label_ids' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],
            'label_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:labels,id',
            ],
            'action' => [
                'required',
                'string',
                'in:set_rate,assign_manager,unassign_manager,enable_withdrawal,suspend_withdrawal',
            ],
            'revenue_share_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'admin_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
        ]);

        $labelIds = collect(
            $validated['label_ids']
        )
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        abort_if(
            $labelIds->isEmpty(),
            422,
            'Select at least one label.'
        );

        $action = $validated['action'];

        if ($action === 'set_rate') {
            abort_unless(
                array_key_exists(
                    'revenue_share_percentage',
                    $validated
                )
                && $validated[
                    'revenue_share_percentage'
                ] !== null,
                422,
                'Revenue Rate is required.'
            );

            $rate = round(
                (float) $validated[
                    'revenue_share_percentage'
                ],
                2
            );

            /*
             * W59: a bulk % - also on labels that had no % yet - needs the
             * e-mailed code; it covers every record shown in those rows.
             */
            $wantedKeys = Label::query()->whereIn('id', $labelIds->all())->get(['id', 'name', 'user_id'])->map(fn ($l) => $this->labelGroupKey($l))->unique();
            $labelIds = Label::query()->whereNull('deleted_at')->get(['id', 'name', 'user_id'])
                ->filter(fn ($l) => $wantedKeys->contains($this->labelGroupKey($l)))
                ->pluck('id')->map(fn ($v) => (int) $v)->values();
            $withoutRate = Label::query()->whereIn('id', $labelIds->all())->whereNull('revenue_share_percentage')->count();

            app(\App\Services\V2\ActionOtpGuard::class)->require(
                $request->user(),
                'label.bulk_rate:'.sha1($labelIds->sort()->implode(',')).':'.$rate,
                'set '.number_format($rate, 2).'% on '.$labelIds->count().' label record(s) ('.$withoutRate.' of them had no % yet)'
            );

            /* the bulk code already confirmed these % changes - no second code from the label observer */
            app()->instance('mixxtune.label_rate_confirmed', true);

            DB::transaction(
                function () use (
                    $labelIds,
                    $rate,
                    $recalculator
                ): void {
                    $labels = Label::query()
                        ->whereIn(
                            'id',
                            $labelIds->all()
                        )
                        ->whereNull('deleted_at')
                        ->lockForUpdate()
                        ->get();

                    abort_unless(
                        $labels->count()
                            === $labelIds->count(),
                        422,
                        'One or more selected labels are unavailable.'
                    );

                    foreach ($labels as $label) {
                        $label
                            ->revenue_share_percentage =
                                $rate;

                        $label->save();

                        /*
                         * Canonical calculation path.
                         * Lifecycle freeze remains enforced
                         * inside this service.
                         */
                        $recalculator
                            ->recalculateForLabel(
                                $label
                            );
                    }
                }
            );

            return back()->with(
                'success',
                $labelIds->count()
                    .' label(s) updated to '
                    .number_format($rate, 2)
                    .'%. Pending review earnings recalculated.'
            );
        }

        /*
         * MIXXTUNE_LABEL_BULK_MANAGER_WITHDRAWAL_FIX_V1
         *
         * Manager assignment mirrors assignLabelAdmin():
         * - consolidated same-name physical labels
         * - delete MANAGER assignments only
         * - preserve OWNER assignments
         * - admin_label_assignments uses user_id, not admin_id
         *
         * Withdrawal mirrors updateLabelWithdrawalStatus():
         * - financial account/User-level permission
         * - synchronize all non-deleted Labels + Artists
         *   belonging to the selected owner account.
         */
        if (
            $action === 'assign_manager'
            || $action === 'unassign_manager'
        ) {
            $adminId = null;

            if ($action === 'assign_manager') {
                $adminId = (int) (
                    $validated['admin_id'] ?? 0
                );

                if ($adminId <= 0) {
                    throw ValidationException::withMessages([
                        'admin_id' =>
                            'Select a Manager.',
                    ]);
                }

                $validAdmin = User::query()
                    ->whereKey($adminId)
                    ->where('role', 'admin')
                    ->where(
                        'account_status',
                        'active'
                    )
                    ->exists();

                if (! $validAdmin) {
                    throw ValidationException::withMessages([
                        'admin_id' =>
                            'Selected user is not an active Admin.',
                    ]);
                }
            }

            /*
             * UI rows are consolidated by normalized label name.
             * Expand every selected representative ID back to all
             * physical labels belonging to its consolidated group.
             */
            $selectedLabels = Label::query()
                ->whereIn(
                    'id',
                    $labelIds->all()
                )
                ->whereNull('deleted_at')
                ->get([
                    'id',
                    'name',
                    'user_id',
                ]);

            abort_unless(
                $selectedLabels->count()
                    === $labelIds->count(),
                422,
                'One or more selected labels are unavailable.'
            );

            $normalizedNames = $selectedLabels
                ->map(
                    fn ($label) =>
                        $this->labelGroupKey($label)
                )
                ->filter()
                ->unique()
                ->values();

            $allLabels = Label::query()
                ->whereNull('deleted_at')
                ->get([
                    'id',
                    'name',
                    'user_id',
                ]);

            $groupLabelIds = $allLabels
                ->filter(
                    function ($candidate) use (
                        $normalizedNames
                    ) {
                        $normalized =
                            $this->labelGroupKey($candidate);

                        return $normalizedNames
                            ->contains($normalized);
                    }
                )
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            abort_if(
                $groupLabelIds->isEmpty(),
                422,
                'No consolidated labels could be resolved.'
            );

            DB::transaction(
                function () use (
                    $groupLabelIds,
                    $adminId,
                    $request
                ): void {
                    /*
                     * CRITICAL:
                     * Never delete owner assignments.
                     */
                    DB::table(
                        'admin_label_assignments'
                    )
                        ->whereIn(
                            'label_id',
                            $groupLabelIds->all()
                        )
                        ->where(
                            'assignment_role',
                            'manager'
                        )
                        ->delete();

                    if ($adminId !== null) {
                        $now = now();

                        $rows = $groupLabelIds
                            ->map(
                                fn ($labelId) => [
                                    'user_id' =>
                                        $adminId,

                                    'label_id' =>
                                        $labelId,

                                    'assignment_role' =>
                                        'manager',

                                    'can_view' =>
                                        true,

                                    'can_edit' =>
                                        true,

                                    'can_manage_releases' =>
                                        true,

                                    'can_manage_team' =>
                                        false,

                                    'can_manage_splits' =>
                                        true,

                                    'assigned_by' =>
                                        $request
                                            ->user()
                                            ->id,

                                    'created_at' =>
                                        $now,

                                    'updated_at' =>
                                        $now,
                                ]
                            )
                            ->all();

                        if ($rows !== []) {
                            DB::table(
                                'admin_label_assignments'
                            )->insert($rows);
                        }
                    }
                }
            );

            return back()->with(
                'success',
                $action === 'assign_manager'
                    ? $selectedLabels->count()
                        .' consolidated label account(s) assigned to Manager.'
                    : $selectedLabels->count()
                        .' consolidated label account(s) unassigned from Manager.'
            );
        }

        if (
            $action === 'enable_withdrawal'
            || $action === 'suspend_withdrawal'
        ) {
            $withdrawalStatus =
                $action === 'enable_withdrawal';

            $selectedLabels = Label::query()
                ->whereIn(
                    'id',
                    $labelIds->all()
                )
                ->whereNull('deleted_at')
                ->get([
                    'id',
                    'user_id',
                ]);

            abort_unless(
                $selectedLabels->count()
                    === $labelIds->count(),
                422,
                'One or more selected labels are unavailable.'
            );

            $ownerIds = $selectedLabels
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            abort_if(
                $ownerIds->isEmpty(),
                422,
                'The selected label financial owner could not be resolved.'
            );

            DB::transaction(
                function () use (
                    $ownerIds,
                    $withdrawalStatus
                ): void {
                    /*
                     * Same canonical behavior as the
                     * working single-label action.
                     */
                    Label::query()
                        ->whereIn(
                            'user_id',
                            $ownerIds->all()
                        )
                        ->whereNull('deleted_at')
                        ->update([
                            'can_withdraw' =>
                                $withdrawalStatus,
                            'updated_at' =>
                                now(),
                        ]);

                    Artist::query()
                        ->whereIn(
                            'user_id',
                            $ownerIds->all()
                        )
                        ->whereNull('deleted_at')
                        ->update([
                            'can_withdraw' =>
                                $withdrawalStatus,
                            'updated_at' =>
                                now(),
                        ]);
                }
            );

            return back()->with(
                'success',
                $ownerIds->count()
                    .' financial account(s): withdrawals '
                    .(
                        $withdrawalStatus
                            ? 'enabled.'
                            : 'suspended.'
                    )
            );
        }

        abort(
            422,
            'Unsupported bulk action.'
        );
    }


    public function labels(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $this->authorizeAdmin(
            $request,
            $permissions
        );

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        $status = trim(
            (string) $request->input(
                'status',
                ''
            )
        );

        $withdrawal = trim(
            (string) $request->input(
                'withdrawal',
                ''
            )
        );

        /*
         * MIXXTUNE_LABEL_ADVANCED_FILTERS_V2
         *
         * Filters involving consolidated counts / managers /
         * rates are applied after same-name label grouping.
         */
        $typeFilter = trim(
            (string) $request->input('type', '')
        );

        $artistsFilter = trim(
            (string) $request->input('artists', '')
        );

        $releasesFilter = trim(
            (string) $request->input('releases', '')
        );

        $rateFilter = trim(
            (string) $request->input('rate', '')
        );

        $managerFilter = trim(
            (string) $request->input('manager', '')
        );

        $sortFilter = trim(
            (string) $request->input(
                'sort',
                'label_asc'
            )
        );

        $query = Label::query()
            ->whereNull('deleted_at')
            ->with([
                'assignedAdmins:id,name,email',
            ])
            ->withCount([
                'artists',
                'releases',
            ]);

        if ($role === 'admin') {
            $query->whereHas(
                'assignedAdmins',
                fn ($builder) =>
                    $builder->where(
                        'users.id',
                        $request->user()->id
                    )
            );
        }

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
                            'legal_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if (
            in_array(
                $status,
                ['active', 'inactive'],
                true
            )
        ) {
            $query->where(
                'status',
                $status
            );
        }

        if (
            in_array(
                $withdrawal,
                ['enabled', 'suspended'],
                true
            )
        ) {
            $query->where(
                'can_withdraw',
                $withdrawal === 'enabled'
            );
        }

        $physicalLabels = $query
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        /* W59: managers of all labels in two queries (before: 2 per row, ~600) */
        $managerRows = DB::table('admin_label_assignments')
            ->where('assignment_role', 'manager')
            ->whereIn('label_id', $physicalLabels->pluck('id'))
            ->get(['label_id', 'user_id']);
        $managerUsers = User::query()
            ->whereIn('id', $managerRows->pluck('user_id')->unique()->values())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $groups = $physicalLabels
            ->groupBy(
                fn ($label) =>
                    $this->labelGroupKey($label)
            )
            ->filter(
                fn ($group, $key) =>
                    $key !== ''
            )
            ->map(function ($group, $key) use ($managerRows, $managerUsers) {
                /*
                 * Prefer an active record as representative.
                 * No physical records are merged or deleted.
                 */
                $representative =
                    $group
                        ->where('status', 'active')
                        ->sortBy('id')
                        ->first()
                    ?? $group
                        ->sortBy('id')
                        ->first();

                $labelIds = $group
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values();

                /*
                 * Assigned Managers only.
                 * Owner pivot rows are intentionally excluded.
                 */
                $managers = $managerRows
                    ->whereIn('label_id', $labelIds->all())
                    ->pluck('user_id')
                    ->unique()
                    ->map(fn ($id) => $managerUsers->get($id))
                    ->filter()
                    ->values();

                return [
                    'id' =>
                        (int) $representative->id,

                    'name' =>
                        $representative->name,

                    'email' =>
                        $representative->email,

                    'label_type' =>
                        $representative->label_type,

                    /*
                     * FF419-C:
                     * canonical commercial revenue rate.
                     */
                    'revenue_share_percentage' =>
                        $representative->revenue_share_percentage !== null
                            ? (float) $representative->revenue_share_percentage
                            : null,

                    'status' =>
                        $group->contains(
                            fn ($item) =>
                                $item->status === 'active'
                        )
                            ? 'active'
                            : $representative->status,

                    'artists_count' =>
                        (int) $group->sum(
                            'artists_count'
                        ),

                    'releases_count' =>
                        (int) $group->sum(
                            'releases_count'
                        ),

                    'assigned_admins' =>
                        $managers,

                    'label_ids' =>
                        $labelIds,

                    'physical_count' =>
                        $group->count(),

                    /* W59: same-name records with different % */
                    'rates_differ' =>
                        $group->pluck('revenue_share_percentage')
                            ->map(fn ($v) => $v === null ? 'none' : (string) round((float) $v, 2))
                            ->unique()
                            ->count() > 1,

                    'can_withdraw' =>
                        $group->every(
                            fn ($item) =>
                                (bool) $item->can_withdraw
                        ),

                    'normalized_key' =>
                        $key,
                ];
            })
            ->sortBy(
                fn ($label) =>
                    strtolower(
                        (string) $label['name']
                    )
            )
            ->values();

        /*
         * MIXXTUNE_LABEL_GROUP_FILTER_PIPELINE_V2
         *
         * $groups now represents exactly what the table displays.
         * Apply advanced filters here so duplicate physical label
         * records cannot distort Artists / Releases / Manager data.
         */

        if ($typeFilter !== '') {
            $groups = $groups
                ->filter(
                    fn ($label) =>
                        strtolower(
                            (string) (
                                $label['label_type'] ?? ''
                            )
                        )
                        === strtolower($typeFilter)
                )
                ->values();
        }

        if ($artistsFilter === 'zero') {
            $groups = $groups
                ->filter(
                    fn ($label) =>
                        (int) (
                            $label['artists_count'] ?? 0
                        ) === 0
                )
                ->values();
        } elseif ($artistsFilter === 'has') {
            $groups = $groups
                ->filter(
                    fn ($label) =>
                        (int) (
                            $label['artists_count'] ?? 0
                        ) > 0
                )
                ->values();
        }

        if ($releasesFilter === 'zero') {
            $groups = $groups
                ->filter(
                    fn ($label) =>
                        (int) (
                            $label['releases_count'] ?? 0
                        ) === 0
                )
                ->values();
        } elseif ($releasesFilter === 'has') {
            $groups = $groups
                ->filter(
                    fn ($label) =>
                        (int) (
                            $label['releases_count'] ?? 0
                        ) > 0
                )
                ->values();
        }

        if ($rateFilter === 'not_set') {
            $groups = $groups
                ->filter(
                    fn ($label) =>
                        (
                            $label[
                                'revenue_share_percentage'
                            ] ?? null
                        ) === null
                )
                ->values();
        } elseif (
            $rateFilter !== ''
            && is_numeric($rateFilter)
        ) {
            $requestedRate =
                (float) $rateFilter;

            if (
                $requestedRate >= 0
                && $requestedRate <= 100
            ) {
                $groups = $groups
                    ->filter(
                        function ($label) use (
                            $requestedRate
                        ) {
                            $rate =
                                $label[
                                    'revenue_share_percentage'
                                ] ?? null;

                            return $rate !== null
                                && abs(
                                    (float) $rate
                                    - $requestedRate
                                ) < 0.00001;
                        }
                    )
                    ->values();
            }
        }

        if ($managerFilter === 'assigned') {
            $groups = $groups
                ->filter(
                    fn ($label) =>
                        collect(
                            $label[
                                'assigned_admins'
                            ] ?? []
                        )->isNotEmpty()
                )
                ->values();
        } elseif (
            $managerFilter === 'unassigned'
        ) {
            $groups = $groups
                ->filter(
                    fn ($label) =>
                        collect(
                            $label[
                                'assigned_admins'
                            ] ?? []
                        )->isEmpty()
                )
                ->values();
        } elseif (
            str_starts_with(
                $managerFilter,
                'manager:'
            )
        ) {
            $managerId = (int) substr(
                $managerFilter,
                strlen('manager:')
            );

            if ($managerId > 0) {
                $groups = $groups
                    ->filter(
                        fn ($label) =>
                            collect(
                                $label[
                                    'assigned_admins'
                                ] ?? []
                            )
                                ->pluck('id')
                                ->map(
                                    fn ($id) =>
                                        (int) $id
                                )
                                ->contains(
                                    $managerId
                                )
                    )
                    ->values();
            }
        }

        switch ($sortFilter) {
            case 'label_desc':
                $groups = $groups->sort(
                    fn ($a, $b) =>
                        strcasecmp(
                            (string) (
                                $b['name'] ?? ''
                            ),
                            (string) (
                                $a['name'] ?? ''
                            )
                        )
                );
                break;

            case 'artists_high':
                $groups = $groups->sort(
                    fn ($a, $b) =>
                        (int) (
                            $b['artists_count'] ?? 0
                        )
                        <=>
                        (int) (
                            $a['artists_count'] ?? 0
                        )
                );
                break;

            case 'artists_low':
                $groups = $groups->sort(
                    fn ($a, $b) =>
                        (int) (
                            $a['artists_count'] ?? 0
                        )
                        <=>
                        (int) (
                            $b['artists_count'] ?? 0
                        )
                );
                break;

            case 'releases_high':
                $groups = $groups->sort(
                    fn ($a, $b) =>
                        (int) (
                            $b['releases_count'] ?? 0
                        )
                        <=>
                        (int) (
                            $a['releases_count'] ?? 0
                        )
                );
                break;

            case 'releases_low':
                $groups = $groups->sort(
                    fn ($a, $b) =>
                        (int) (
                            $a['releases_count'] ?? 0
                        )
                        <=>
                        (int) (
                            $b['releases_count'] ?? 0
                        )
                );
                break;

            case 'rate_high':
                $groups = $groups->sort(
                    function ($a, $b) {
                        $ar =
                            $a[
                                'revenue_share_percentage'
                            ] ?? null;

                        $br =
                            $b[
                                'revenue_share_percentage'
                            ] ?? null;

                        if (
                            $ar === null
                            && $br === null
                        ) {
                            return 0;
                        }

                        if ($ar === null) {
                            return 1;
                        }

                        if ($br === null) {
                            return -1;
                        }

                        return (float) $br
                            <=>
                            (float) $ar;
                    }
                );
                break;

            case 'rate_low':
                $groups = $groups->sort(
                    function ($a, $b) {
                        $ar =
                            $a[
                                'revenue_share_percentage'
                            ] ?? null;

                        $br =
                            $b[
                                'revenue_share_percentage'
                            ] ?? null;

                        /*
                         * Keep Not Set at the bottom for both
                         * directions.
                         */
                        if (
                            $ar === null
                            && $br === null
                        ) {
                            return 0;
                        }

                        if ($ar === null) {
                            return 1;
                        }

                        if ($br === null) {
                            return -1;
                        }

                        return (float) $ar
                            <=>
                            (float) $br;
                    }
                );
                break;

            case 'label_asc':
            default:
                $groups = $groups->sort(
                    fn ($a, $b) =>
                        strcasecmp(
                            (string) (
                                $a['name'] ?? ''
                            ),
                            (string) (
                                $b['name'] ?? ''
                            )
                        )
                );
                break;
        }

        $groups = $groups->values();

        $perPage = 25;

        $page = max(
            1,
            (int) $request->input(
                'page',
                1
            )
        );

        $pageItems = $groups
            ->forPage(
                $page,
                $perPage
            )
            ->values();

        $labels = new \Illuminate\Pagination\LengthAwarePaginator(
            $pageItems,
            $groups->count(),
            $perPage,
            $page,
            [
                'path' =>
                    $request->url(),

                'query' =>
                    $request->query(),
            ]
        );

        return Inertia::render(
            'V2/Admin/Labels/Index',
            [
                'role' => $role,
                'search' => $search,
                'statusFilter' => $status,
                'withdrawalFilter' => $withdrawal,
                'typeFilter' => $typeFilter,
                'artistsFilter' => $artistsFilter,
                'releasesFilter' => $releasesFilter,
                'rateFilter' => $rateFilter,
                'managerFilter' => $managerFilter,
                'sortFilter' => $sortFilter,

                'admins' =>
                    $role === 'super_admin'
                        ? User::query()
                            ->where(
                                'role',
                                'admin'
                            )
                            ->where(
                                'account_status',
                                'active'
                            )
                            ->orderBy('name')
                            ->get([
                                'id',
                                'name',
                                'email',
                            ])
                        : collect(),

                'labels' =>
                    $labels,
            ]
        );
    }

    /*
     * W59 MIXXTUNE_LABEL_GROUP_KEY_V2: labels shown as one row = same name
     * (any language, spaces / case / dashes ignored) AND same owner login.
     * A name with no letters at all is never grouped with others.
     */
    private function labelGroupKey($label): string
    {
        $name = (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim((string) ($label->name ?? ''))));

        if ($name === '') {
            return 'id:'.(int) $label->id;
        }

        return $name.'#'.(int) ($label->user_id ?? 0);
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

    private function authorizeAdmin(
        Request $request,
        PermissionService $permissions
    ): string {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                ['admin', 'super_admin'],
                true
            ),
            403,
            'Admin access required.'
        );

        return $role;
    }
}
