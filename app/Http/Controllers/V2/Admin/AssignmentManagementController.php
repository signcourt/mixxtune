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
            ->whereIn(
                'role',
                ['admin', 'super_admin']
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
            in_array(
                $admin->role,
                ['admin', 'super_admin'],
                true
            ),
            404,
            'Admin not found.'
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
            in_array(
                $admin->role,
                ['admin', 'super_admin'],
                true
            ),
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

        DB::transaction(function () use (
            $label,
            $adminId,
            $request
        ) {
            DB::table('admin_label_assignments')
                ->where('label_id', $label->id)
                ->delete();

            if ($adminId) {
                DB::table('admin_label_assignments')
                    ->insert([
                        'user_id' => $adminId,
                        'label_id' => $label->id,
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
                ? 'Label admin assigned.'
                : 'Label admin unassigned.'
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
            DB::table('admin_artist_assignments')
                ->where('artist_id', $artist->id)
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
            if ($role === 'admin') {
                $allowedLabel = DB::table(
                    'admin_label_assignments'
                )
                    ->where(
                        'user_id',
                        $request->user()->id
                    )
                    ->where(
                        'label_id',
                        $labelId
                    )
                    ->exists();

                abort_unless(
                    $allowedLabel,
                    403,
                    'Label access denied.'
                );
            }

            $query->where(
                'label_id',
                $labelId
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
                'exists:labels,id',
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
                'in:pending,verified,rejected',
            ],
            'can_create_releases' => [
                'required',
                'boolean',
            ],
            'can_receive_splits' => [
                'required',
                'boolean',
            ],
        ]);

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

        abort_if(
            $artist->releases()->exists(),
            422,
            'Artist with releases cannot be deleted.'
        );

        $artist->delete();

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

    public function labels(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $this->authorizeAdmin(
            $request,
            $permissions
        );

        $query = Label::query()
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

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

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

        return Inertia::render(
            'V2/Admin/Labels/Index',
            [
                'role' => $role,
                'search' => $search,

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

                'labels' =>
                    $query
                        ->orderBy('name')
                        ->paginate(25)
                        ->withQueryString(),
            ]
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
