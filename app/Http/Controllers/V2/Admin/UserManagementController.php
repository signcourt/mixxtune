<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\System\UserPanelPermission;
use App\Models\User;
use App\Services\V2\ClientIdService;
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

            'state_code' => [
                'nullable',
                'required_if:role,label,artist',
                'string',
                'max:10',
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

            'revenue_share_percentage' => [
                'nullable',
                'required_if:role,label,artist',
                'numeric',
                'min:0',
                'max:100',
            ],

            'send_invitation' => [
                'required',
                'boolean',
            ],

            'account_holder_name' => [
                'nullable',
                'required_if:role,label,artist',
                'string',
                'max:190',
            ],

            'bank_account_number' => [
                'nullable',
                'required_if:role,label,artist',
                'string',
                'max:60',
            ],

            'bank_name' => [
                'nullable',
                'required_if:role,label,artist',
                'string',
                'max:190',
            ],

            'ifsc_code' => [
                'nullable',
                'required_if:role,label,artist',
                'string',
                'max:30',
            ],

            'branch_name' => [
                'nullable',
                'string',
                'max:190',
            ],

            'upi_id' => [
                'nullable',
                'string',
                'max:190',
            ],

            'pan_number' => [
                'nullable',
                'required_if:role,label,artist',
                'string',
                'max:30',
            ],

            'gst_number' => [
                'nullable',
                'string',
                'max:40',
            ],

            'address_line_1' => [
                'nullable',
                'required_if:role,label,artist',
                'string',
                'max:255',
            ],

            'address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],

            'city' => [
                'nullable',
                'required_if:role,label,artist',
                'string',
                'max:120',
            ],

            'state' => [
                'nullable',
                'required_if:role,label,artist',
                'string',
                'max:120',
            ],

            'postal_code' => [
                'nullable',
                'required_if:role,label,artist',
                'string',
                'max:30',
            ],

            'country_code' => [
                'nullable',
                'required_if:role,label,artist',
                'string',
                'size:2',
                'regex:/^[A-Za-z]{2}$/',
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

            'new_labels' => [
                'nullable',
                'array',
            ],

            'new_labels.*' => [
                'nullable',
                'string',
                'max:255',
            ],

            'permissions' => [
                'nullable',
                'array',
            ],
        ]);

        if (
            $validated['role'] === 'label'
        ) {
            $existingLabelIds =
                $validated['label_ids']
                ?? [];

            $newLabelNames =
                collect(
                    $validated['new_labels']
                    ?? []
                )
                    ->map(
                        fn ($name) =>
                            trim((string) $name)
                    )
                    ->filter()
                    ->values();

            if (
                empty($existingLabelIds)
                && $newLabelNames->isEmpty()
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'label_ids' => [
                        'Select an existing label or add one new label.',
                    ],
                ]);
            }

            /*
             * A Label login represents exactly one financial
             * label identity. Admin users may still manage
             * multiple labels through manager assignments.
             */
            $requestedLabelCount =
                count($existingLabelIds)
                + $newLabelNames->count();

            if ($requestedLabelCount !== 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'label_ids' => [
                        'A Label account must be linked to exactly one label.',
                    ],
                    'new_labels' => [
                        'Select one existing label OR add one new label, not multiple labels.',
                    ],
                ]);
            }

            $duplicateNames =
                $newLabelNames
                    ->map(
                        fn ($name) =>
                            mb_strtolower($name)
                    )
                    ->duplicates();

            if ($duplicateNames->isNotEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'new_labels' => [
                        'Duplicate label names are not allowed.',
                    ],
                ]);
            }

            foreach (
                $existingLabelIds
                as $labelId
            ) {
                $label =
                    \App\Models\Core\Label::query()
                        ->find(
                            (int) $labelId
                        );

                if (! $label) {
                    continue;
                }

                if (
                    $label->user_id
                    && (int) $label->user_id
                        !== 0
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'label_ids' => [
                            "Label '{$label->name}' is already owned by another label user.",
                        ],
                    ]);
                }
            }
        }


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

                    'state_code' =>
                        isset($validated['state_code'])
                        && trim(
                            (string) $validated['state_code']
                        ) !== ''
                            ? strtoupper(
                                trim(
                                    $validated['state_code']
                                )
                            )
                            : null,

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
                 * Permanent Client ID + Profile/KYC/Payout
                 * are created together with the account.
                 */
                if (
                    in_array(
                        $validated['role'],
                        ['label', 'artist'],
                        true
                    )
                ) {
                    $countryCode =
                        strtoupper(
                            $validated['country_code']
                            ?? 'IN'
                        );

                    $stateCode =
                        strtoupper(
                            $validated['state_code']
                            ?? 'NA'
                        );

                    $user->forceFill([
                        'client_id' =>
                            app(
                                \App\Services\V2\ClientIdService::class
                            )->generate(
                                $validated['role'],
                                $countryCode,
                                $stateCode
                            ),
                    ])->save();

                    \App\Models\Finance\PayoutProfile::query()
                        ->create([
                            'public_id' =>
                                (string) \Illuminate\Support\Str::ulid(),

                            'user_id' =>
                                $user->id,

                            'account_holder_name' =>
                                $validated[
                                    'account_holder_name'
                                ],

                            'bank_account_number' =>
                                $validated[
                                    'bank_account_number'
                                ],

                            'bank_name' =>
                                $validated[
                                    'bank_name'
                                ],

                            'ifsc_code' =>
                                strtoupper(
                                    trim(
                                        $validated[
                                            'ifsc_code'
                                        ]
                                    )
                                ),

                            'branch_name' =>
                                $validated[
                                    'branch_name'
                                ]
                                ?? null,

                            'upi_id' =>
                                $validated[
                                    'upi_id'
                                ]
                                ?? null,

                            'pan_number' =>
                                strtoupper(
                                    trim(
                                        $validated[
                                            'pan_number'
                                        ]
                                    )
                                ),

                            'gst_number' =>
                                !empty(
                                    $validated[
                                        'gst_number'
                                    ]
                                )
                                    ? strtoupper(
                                        trim(
                                            $validated[
                                                'gst_number'
                                            ]
                                        )
                                    )
                                    : null,

                            'address_line_1' =>
                                $validated[
                                    'address_line_1'
                                ],

                            'address_line_2' =>
                                $validated[
                                    'address_line_2'
                                ]
                                ?? null,

                            'city' =>
                                $validated['city'],

                            'state' =>
                                $validated['state'],

                            'postal_code' =>
                                $validated[
                                    'postal_code'
                                ],

                            'country_code' =>
                                $countryCode,

                            'kyc_status' =>
                                'pending',
                        ]);
                }

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

                        'revenue_share_percentage' =>
                            (float) $validated[
                                'revenue_share_percentage'
                            ],

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
                 *
                 * Prefer an existing orphan label before creating
                 * a new label record.
                 */
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

                if (
                    $validated['role'] ===
                    'label'
                ) {
                    $labelPivot = [
                        'assignment_role' =>
                            'owner',

                        'can_view' =>
                            true,

                        'can_edit' =>
                            true,

                        'can_manage_releases' =>
                            true,

                        'can_manage_team' =>
                            false,

                        'can_manage_splits' =>
                            false,

                        'assigned_by' =>
                            $request->user()->id,
                    ];

                    $labelSync = [];

                    foreach (
                        $validated['label_ids']
                            ?? []
                        as $labelId
                    ) {
                        $labelSync[
                            (int) $labelId
                        ] = $labelPivot;
                    }

                    foreach (
                        collect(
                            $validated['new_labels']
                                ?? []
                        )
                            ->map(
                                fn ($name) =>
                                    trim((string) $name)
                            )
                            ->filter()
                            ->unique(
                                fn ($name) =>
                                    mb_strtolower($name)
                            )
                        as $labelName
                    ) {
                        $existingLabel =
                            Label::query()
                                ->whereRaw(
                                    'LOWER(name) = ?',
                                    [
                                        mb_strtolower(
                                            $labelName
                                        ),
                                    ]
                                )
                                ->first();

                        if (
                            $existingLabel
                            && $existingLabel->user_id
                            && (int) $existingLabel->user_id
                                !== (int) $user->id
                        ) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'new_labels' => [
                                    "Label '{$labelName}' already belongs to another label user.",
                                ],
                            ]);
                        }

                        if ($existingLabel) {
                            $label =
                                $existingLabel;

                            if (! $label->user_id) {
                                $label->forceFill([
                                    'user_id' =>
                                        $user->id,

                                    'revenue_share_percentage' =>
                                        (float) $validated[
                                            'revenue_share_percentage'
                                        ],

                                    'updated_by' =>
                                        $request
                                            ->user()
                                            ->id,
                                ]);

                                $label->save();
                            }
                        } else {
                            $slugBase =
                                Str::slug(
                                    $labelName
                                );

                            if ($slugBase === '') {
                                $slugBase = 'label';
                            }

                            $slug =
                                $slugBase
                                .'-'
                                .$user->id;

                            while (
                                Label::withTrashed()
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

                            $label =
                                Label::create([
                                    'user_id' =>
                                        $user->id,

                                    'public_id' =>
                                        $publicId,

                                    'name' =>
                                        $labelName,

                                    'legal_name' =>
                                        $labelName,

                                    'slug' =>
                                        $slug,

                                    'email' =>
                                        strtolower(
                                            trim(
                                                $validated[
                                                    'email'
                                                ]
                                            )
                                        ),

                                    'phone' =>
                                        $validated[
                                            'phone'
                                        ]
                                        ?? null,

                                    'country' =>
                                        $validated[
                                            'country'
                                        ]
                                        ?? 'India',

                                    'timezone' =>
                                        'Asia/Kolkata',

                                    'currency' =>
                                        'INR',

                                    'revenue_share_percentage' =>
                                        (float) $validated[
                                            'revenue_share_percentage'
                                        ],

                                    'status' =>
                                        'active',

                                    'created_by' =>
                                        $request
                                            ->user()
                                            ->id,

                                    'updated_by' =>
                                        $request
                                            ->user()
                                            ->id,
                                ]);
                        }

                        $labelSync[
                            (int) $label->id
                        ] = $labelPivot;
                    }

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

                $payoutProfile =
            \App\Models\Finance\PayoutProfile::query()
                ->where('user_id', $user->id)
                ->first();

        $assignedRevenueRate = null;

        if ($user->role === 'artist') {
            $assignedRevenueRate =
                Artist::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->value(
                        'revenue_share_percentage'
                    );
        } elseif ($user->role === 'label') {
            $assignedRevenueRate =
                Label::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->orderBy('id')
                    ->value(
                        'revenue_share_percentage'
                    );
        }

return Inertia::render(
            'V2/Admin/Users/Edit',
            [
                'role' => 'super_admin',

                'managedUser' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'client_id' => $user->client_id,
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

                    'revenue_share_percentage' =>
                        $assignedRevenueRate !== null
                            ? (float) $assignedRevenueRate
                            : null,
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

                'payoutProfile' =>
                    $payoutProfile
                        ? [
                            'public_id' =>
                                $payoutProfile->public_id,

                            'account_holder_name' =>
                                $payoutProfile->account_holder_name,

                            /*
                             * Sensitive encrypted values are
                             * intentionally NOT returned in full.
                             */
                            'bank_account_number' => '',
                            'masked_bank_account' =>
                                $payoutProfile->masked_bank_account,

                            'bank_name' =>
                                $payoutProfile->bank_name,

                            'ifsc_code' =>
                                $payoutProfile->ifsc_code,

                            'branch_name' =>
                                $payoutProfile->branch_name,

                            'upi_id' =>
                                $payoutProfile->upi_id,

                            'pan_number' => '',
                            'masked_pan' =>
                                $payoutProfile->masked_pan,

                            'gst_number' =>
                                $payoutProfile->gst_number,

                            'address_line_1' =>
                                $payoutProfile->address_line_1,

                            'address_line_2' =>
                                $payoutProfile->address_line_2,

                            'city' =>
                                $payoutProfile->city,

                            'state' =>
                                $payoutProfile->state,

                            'postal_code' =>
                                $payoutProfile->postal_code,

                            'country_code' =>
                                $payoutProfile->country_code,

                            'kyc_status' =>
                                $payoutProfile->kyc_status,

                            'verified_at' =>
                                optional(
                                    $payoutProfile->verified_at
                                )?->toDateTimeString(),
                        ]
                        : null,

                'assignedArtistIds' =>
                    $user->assignedArtists
                        ->pluck('id'),

                'assignedLabelIds' =>
                    $user->assignedLabels
                        ->pluck('id')
                        ->merge(
                            Label::query()
                                ->where(
                                    'user_id',
                                    $user->id
                                )
                                ->pluck('id')
                        )
                        ->map(
                            fn ($id) =>
                                (int) $id
                        )
                        ->unique()
                        ->values(),
            ]
        );
    }

    public function update(
        Request $request,
        User $user,
        PermissionService $permissions,
        AuditLogService $audit
    ): RedirectResponse {
        file_put_contents(
            storage_path('logs/label-save-debug.log'),
            now()->toDateTimeString()
            ." UPDATE_ENTER user={$user->id}"
            ." role=".($request->input('role') ?? 'NULL')
            ." label_ids=".json_encode($request->input('label_ids', []))
            ." new_labels=".json_encode($request->input('new_labels', []))
            .PHP_EOL,
            FILE_APPEND
        );
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

                file_put_contents(
            storage_path('logs/user-update-debug.log'),
            now()->toDateTimeString()
            ." UPDATE_REQUEST_PAYLOAD "
            .json_encode(
                $request->all(),
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
            .PHP_EOL,
            FILE_APPEND
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

            'revenue_share_percentage' => [
                'nullable',
                'required_if:role,label,artist',
                'numeric',
                'min:0',
                'max:100',
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

            'new_labels' => [
                'nullable',
                'array',
            ],

            'new_labels.*' => [
                'nullable',
                'string',
                'max:255',
            ],

            'permissions' => [
                'nullable',
                'array',
            ],
            'account_holder_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'bank_account_number' => [
                'nullable',
                'string',
                'min:6',
                'max:40',
            ],

            'bank_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'ifsc_code' => [
                'nullable',
                'string',
                'max:30',
            ],

            'branch_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'upi_id' => [
                'nullable',
                'string',
                'max:255',
            ],

            'pan_number' => [
                'nullable',
                'string',
                'max:20',
            ],

            'gst_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'address_line_1' => [
                'nullable',
                'string',
                'max:255',
            ],

            'address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],

            'city' => [
                'nullable',
                'string',
                'max:100',
            ],

            'state' => [
                'nullable',
                'string',
                'max:100',
            ],

            'postal_code' => [
                'nullable',
                'string',
                'max:20',
            ],

            'country_code' => [
                'nullable',
                'string',
                'size:2',
            ],

        ]);

        if (
            $validated['role'] === 'label'
        ) {
            $existingLabelIds =
                array_map(
                    'intval',
                    $validated['label_ids']
                        ?? []
                );

            $newLabelNames =
                collect(
                    $validated['new_labels']
                        ?? []
                )
                    ->map(
                        fn ($name) =>
                            trim((string) $name)
                    )
                    ->filter()
                    ->values();

            if (
                empty($existingLabelIds)
                && $newLabelNames->isEmpty()
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'label_ids' => [
                        'A Label account must have exactly one label.',
                    ],
                ]);
            }

            /*
             * A Label login represents exactly one financial
             * label identity. Admin users may still manage
             * multiple labels through manager assignments.
             */
            $requestedLabelCount =
                count($existingLabelIds)
                + $newLabelNames->count();

            if ($requestedLabelCount !== 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'label_ids' => [
                        'A Label account must be linked to exactly one label.',
                    ],
                    'new_labels' => [
                        'Select one existing label OR add one new label, not multiple labels.',
                    ],
                ]);
            }

            $duplicateNames =
                $newLabelNames
                    ->map(
                        fn ($name) =>
                            mb_strtolower($name)
                    )
                    ->duplicates();

            if (
                $duplicateNames->isNotEmpty()
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'new_labels' => [
                        'Duplicate label names are not allowed.',
                    ],
                ]);
            }

            foreach (
                $existingLabelIds
                as $labelId
            ) {
                $label =
                    Label::query()
                        ->findOrFail(
                            $labelId
                        );

                if (
                    $label->user_id
                    && (int) $label->user_id
                        !== (int) $user->id
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'label_ids' => [
                            "Label '{$label->name}' already belongs to another Label account.",
                        ],
                    ]);
                }
            }
        }


                file_put_contents(
            storage_path('logs/label-save-debug.log'),
            now()->toDateTimeString()
            ." VALIDATION_PASSED"
            ." role=".($validated['role'] ?? 'NULL')
            ." label_ids=".json_encode($validated['label_ids'] ?? [])
            ." new_labels=".json_encode($validated['new_labels'] ?? [])
            .PHP_EOL,
            FILE_APPEND
        );

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
                $user
            ) {
                $user->update([
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

                    'kyc_status' =>
                        $validated['kyc_status']
                        ?? $user->kyc_status,
                ]);

                /*
                 * Assigned Revenue Rate is commercial
                 * configuration controlled only through
                 * this Super Admin endpoint.
                 */
                if ($validated['role'] === 'artist') {
                    Artist::query()
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->update([
                            'revenue_share_percentage' =>
                                (float) $validated[
                                    'revenue_share_percentage'
                                ],

                            'updated_by' =>
                                $request->user()->id,
                        ]);
                } elseif ($validated['role'] === 'label') {
                    Label::query()
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->update([
                            'revenue_share_percentage' =>
                                (float) $validated[
                                    'revenue_share_percentage'
                                ],

                            'updated_by' =>
                                $request->user()->id,
                        ]);
                }

                if (
                    $validated['role'] === 'admin'
                ) {
                    $pivot = [
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
                            (int) $artistId
                        ] = $pivot;
                    }

                    $labelSync = [];

                    foreach (
                        $validated['label_ids']
                            ?? []
                        as $labelId
                    ) {
                        $labelSync[
                            (int) $labelId
                        ] = $pivot;
                    }

                    $user
                        ->assignedArtists()
                        ->sync(
                            $artistSync
                        );

                    file_put_contents(
                        storage_path('logs/label-save-debug.log'),
                        now()->toDateTimeString()
                        ." BEFORE_SYNC"
                        ." user={$user->id}"
                        ." labelSync=".json_encode($labelSync)
                        .PHP_EOL,
                        FILE_APPEND
                    );

                    $user
                        ->assignedLabels()
                        ->sync(
                            $labelSync
                        );

                    file_put_contents(
                        storage_path('logs/label-save-debug.log'),
                        now()->toDateTimeString()
                        ." AFTER_SYNC"
                        ." pivot_count="
                        .$user->assignedLabels()
                            ->count()
                        .PHP_EOL,
                        FILE_APPEND
                    );
                } elseif (
                    $validated['role'] === 'label'
                ) {
                    /*
                     * Label accounts do not use
                     * artist assignments.
                     */
                    $user
                        ->assignedArtists()
                        ->detach();

                    $ownerPivot = [
                        'assignment_role' =>
                            'owner',

                        'can_view' =>
                            true,

                        'can_edit' =>
                            true,

                        'can_manage_releases' =>
                            true,

                        'can_manage_team' =>
                            false,

                        'can_manage_splits' =>
                            false,

                        'assigned_by' =>
                            $request->user()->id,
                    ];

                    /*
                     * Remember current labels so
                     * removed labels can be released
                     * from this account without
                     * deleting the label itself.
                     */
                    $previousLabelIds =
                        $user
                            ->assignedLabels()
                            ->pluck(
                                'labels.id'
                            )
                            ->map(
                                fn ($id) =>
                                    (int) $id
                            )
                            ->all();

                    $labelSync = [];

                    /*
                     * Existing selected labels.
                     */
                    foreach (
                        $validated['label_ids']
                            ?? []
                        as $labelId
                    ) {
                        $label =
                            Label::query()
                                ->lockForUpdate()
                                ->findOrFail(
                                    (int) $labelId
                                );

                        if (
                            $label->user_id
                            && (int) $label->user_id
                                !== (int) $user->id
                        ) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'label_ids' => [
                                    "Label '{$label->name}' already belongs to another Label account.",
                                ],
                            ]);
                        }

                        if (
                            ! $label->user_id
                        ) {
                            $label->forceFill([
                                'user_id' =>
                                    $user->id,

                                'updated_by' =>
                                    $request
                                        ->user()
                                        ->id,
                            ]);

                            $label->save();
                        }

                        $label->forceFill([
                            'revenue_share_percentage' =>
                                (float) $validated[
                                    'revenue_share_percentage'
                                ],

                            'updated_by' =>
                                $request->user()->id,
                        ])->save();

                        $labelSync[
                            (int) $label->id
                        ] = $ownerPivot;
                    }

                    /*
                     * Unlimited new labels.
                     */
                    $newLabelNames =
                        collect(
                            $validated[
                                'new_labels'
                            ] ?? []
                        )
                            ->map(
                                fn ($name) =>
                                    trim(
                                        (string) $name
                                    )
                            )
                            ->filter()
                            ->unique(
                                fn ($name) =>
                                    mb_strtolower(
                                        $name
                                    )
                            )
                            ->values();

                    foreach (
                        $newLabelNames
                        as $labelName
                    ) {
                        $label =
                            Label::query()
                                ->whereRaw(
                                    'LOWER(name) = ?',
                                    [
                                        mb_strtolower(
                                            $labelName
                                        ),
                                    ]
                                )
                                ->first();

                        if (
                            $label
                            && $label->user_id
                            && (int) $label->user_id
                                !== (int) $user->id
                        ) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'new_labels' => [
                                    "Label '{$labelName}' already belongs to another Label account.",
                                ],
                            ]);
                        }

                        if (! $label) {
                            $slugBase =
                                Str::slug(
                                    $labelName
                                );

                            if (
                                $slugBase === ''
                            ) {
                                $slugBase =
                                    'label';
                            }

                            $slug =
                                $slugBase
                                .'-'
                                .$user->id;

                            while (
                                Label::withTrashed()
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
                                        Str::random(
                                            5
                                        )
                                    );
                            }

                            do {
                                $publicId =
                                    'LBL-'
                                    .Str::upper(
                                        Str::random(
                                            12
                                        )
                                    );
                            } while (
                                Label::withTrashed()
                                    ->where(
                                        'public_id',
                                        $publicId
                                    )
                                    ->exists()
                            );

                            $label =
                                Label::create([
                                    'user_id' =>
                                        $user->id,

                                    'public_id' =>
                                        $publicId,

                                    'name' =>
                                        $labelName,

                                    'legal_name' =>
                                        $labelName,

                                    'slug' =>
                                        $slug,

                                    'email' =>
                                        strtolower(
                                            trim(
                                                $validated[
                                                    'email'
                                                ]
                                            )
                                        ),

                                    'phone' =>
                                        $validated[
                                            'phone'
                                        ]
                                        ?? null,

                                    'country' =>
                                        $validated[
                                            'country'
                                        ]
                                        ?? 'India',

                                    'timezone' =>
                                        'Asia/Kolkata',

                                    'currency' =>
                                        'INR',

                                    'revenue_share_percentage' =>
                                        (float) $validated[
                                            'revenue_share_percentage'
                                        ],

                                    'status' =>
                                        'active',

                                    'created_by' =>
                                        $request
                                            ->user()
                                            ->id,

                                    'updated_by' =>
                                        $request
                                            ->user()
                                            ->id,
                                ]);
                        } elseif (
                            ! $label->user_id
                        ) {
                            $label->forceFill([
                                'user_id' =>
                                    $user->id,

                                'updated_by' =>
                                    $request
                                        ->user()
                                        ->id,
                            ]);

                            $label->save();
                        }

                        $label->forceFill([
                            'revenue_share_percentage' =>
                                (float) $validated[
                                    'revenue_share_percentage'
                                ],

                            'updated_by' =>
                                $request->user()->id,
                        ])->save();

                        $labelSync[
                            (int) $label->id
                        ] = $ownerPivot;
                    }

                    /*
                     * Labels removed in Edit:
                     * do not delete the label.
                     * Only release account ownership.
                     */
                    $finalLabelIds =
                        array_map(
                            'intval',
                            array_keys(
                                $labelSync
                            )
                        );

                    $removedLabelIds =
                        array_values(
                            array_diff(
                                $previousLabelIds,
                                $finalLabelIds
                            )
                        );

                    if (
                        ! empty(
                            $removedLabelIds
                        )
                    ) {
                        Label::query()
                            ->whereIn(
                                'id',
                                $removedLabelIds
                            )
                            ->where(
                                'user_id',
                                $user->id
                            )
                            ->update([
                                'user_id' =>
                                    null,

                                'updated_by' =>
                                    $request
                                        ->user()
                                        ->id,

                                'updated_at' =>
                                    now(),
                            ]);
                    }

                    $user
                        ->assignedLabels()
                        ->sync(
                            $labelSync
                        );
                } else {
                    /*
                     * Artist / other role:
                     * remove assignment access.
                     */
                    $user
                        ->assignedArtists()
                        ->detach();

                    $oldLabelIds =
                        $user
                            ->assignedLabels()
                            ->pluck(
                                'labels.id'
                            )
                            ->all();

                    if (
                        ! empty(
                            $oldLabelIds
                        )
                    ) {
                        Label::query()
                            ->whereIn(
                                'id',
                                $oldLabelIds
                            )
                            ->where(
                                'user_id',
                                $user->id
                            )
                            ->update([
                                'user_id' =>
                                    null,

                                'updated_by' =>
                                    $request
                                        ->user()
                                        ->id,

                                'updated_at' =>
                                    now(),
                            ]);
                    }

                    $user
                        ->assignedLabels()
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
                /*
                 * ADMIN_MANAGED_PAYOUT_PROFILE
                 *
                 * Super Admin manages the target user's KYC/Payout
                 * profile. Blank sensitive fields preserve existing
                 * encrypted Bank Account and PAN values.
                 */
                $payoutFields = [
                    'account_holder_name',
                    'bank_account_number',
                    'bank_name',
                    'ifsc_code',
                    'branch_name',
                    'upi_id',
                    'pan_number',
                    'gst_number',
                    'address_line_1',
                    'address_line_2',
                    'city',
                    'state',
                    'postal_code',
                    'country_code',
                ];
                
                $hasPayoutInput = collect(
                    $payoutFields
                )->contains(
                    fn (string $field): bool =>
                        $request->exists($field)
                );
                
                if ($hasPayoutInput) {
                    $existingPayout =
                        \App\Models\Finance\PayoutProfile::query()
                            ->where(
                                'user_id',
                                $user->id
                            )
                            ->first();
                
                    $payoutData = [];
                
                    foreach ($payoutFields as $field) {
                        if (
                            array_key_exists(
                                $field,
                                $validated
                            )
                        ) {
                            $payoutData[$field] =
                                $validated[$field];
                        }
                    }
                
                    /*
                     * Empty sensitive values mean:
                     * keep the current encrypted value.
                     */
                    if (
                        empty(
                            $payoutData[
                                'bank_account_number'
                            ] ?? null
                        )
                        && $existingPayout
                    ) {
                        unset(
                            $payoutData[
                                'bank_account_number'
                            ]
                        );
                    }
                
                    if (
                        empty(
                            $payoutData[
                                'pan_number'
                            ] ?? null
                        )
                        && $existingPayout
                    ) {
                        unset(
                            $payoutData[
                                'pan_number'
                            ]
                        );
                    }
                
                    if (
                        isset(
                            $payoutData[
                                'country_code'
                            ]
                        )
                    ) {
                        $payoutData['country_code'] =
                            strtoupper(
                                $payoutData[
                                    'country_code'
                                ]
                            );
                    }
                
                    if (
                        isset(
                            $payoutData[
                                'ifsc_code'
                            ]
                        )
                    ) {
                        $payoutData['ifsc_code'] =
                            strtoupper(
                                $payoutData[
                                    'ifsc_code'
                                ]
                            );
                    }
                
                    if (
                        isset(
                            $payoutData[
                                'pan_number'
                            ]
                        )
                    ) {
                        $payoutData['pan_number'] =
                            strtoupper(
                                $payoutData[
                                    'pan_number'
                                ]
                            );
                    }
                
                    /*
                     * IMPORTANT:
                     * payout_profiles.kyc_status is controlled only by
                     * the dedicated KYC submission/review workflow.
                     *
                     * Editing a user must never reset Submitted,
                     * Verified or Rejected payout KYC state.
                     */
                
                    \App\Models\Finance\PayoutProfile::query()
                        ->updateOrCreate(
                            [
                                'user_id' =>
                                    $user->id,
                            ],
                            [
                                'public_id' =>
                                    $existingPayout?->public_id
                                    ?: (string)
                                        \Illuminate\Support\Str::ulid(),
                
                                ...$payoutData,
                            ]
                        );
                }

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
