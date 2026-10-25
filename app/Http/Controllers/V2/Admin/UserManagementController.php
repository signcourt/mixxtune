<?php

namespace App\Http\Controllers\V2\Admin;

use App\Services\V2\ReportEarningsRecalculationService;
use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\System\UserPanelPermission;
use App\Models\User;
use App\Services\V2\ClientIdService;
use App\Services\V2\LabelIdentityService;
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

        /*
         * MIXXTUNE_QUICK_EDIT_INDEX_CONFIG_V1
         *
         * users.quick_edit_fields is included with the normal User model
         * payload and cast to an array by the User model.
         */

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

        // MIXXTUNE_USER_STATS_INVITATION_FILTER_V1
        $invitationFilter = trim(
            (string) $request->input(
                'invitation',
                ''
            )
        );

        $query = User::query()
            ->with([
                'assignedArtists:id,stage_name',

                'assignedLabels' => function ($query) {
                    $query
                        ->select([
                            'labels.id',
                            'labels.name',
                            'labels.user_id',
                            'labels.parent_label_id',
                            'labels.revenue_share_percentage',
                            'labels.status',
                            'labels.can_withdraw',
                        ]);
                },
            ])

            // MIXXTUNE_QUICK_EDIT_INDEX_HYDRATION_V1
            ->addSelect([
                'quick_edit_permissions' =>
                    UserPanelPermission::query()
                        ->select(
                            DB::raw(
                                "JSON_OBJECT(
                                    'can_view_catalogue', can_view_catalogue,
                                    'can_create_releases', can_create_releases,
                                    'can_manage_releases', can_manage_releases,
                                    'can_view_reports', can_view_reports,
                                    'can_view_royalties', can_view_royalties,
                                    'can_manage_wallet', can_manage_wallet,
                                    'can_manage_withdrawals', can_manage_withdrawals,
                                    'can_manage_users', can_manage_users,
                                    'can_manage_support', can_manage_support,
                                    'can_manage_settings', can_manage_settings,
                                    'can_manage_delivery', can_manage_delivery,
                                    'can_manage_identifiers', can_manage_identifiers
                                )"
                            )
                        )
                        ->whereColumn(
                            'user_panel_permissions.user_id',
                            'users.id'
                        )
                        ->limit(1),

                'address_line_1' =>
                    DB::table('payout_profiles')
                        ->select('address_line_1')
                        ->whereColumn(
                            'payout_profiles.user_id',
                            'users.id'
                        )
                        ->limit(1),

                'address_line_2' =>
                    DB::table('payout_profiles')
                        ->select('address_line_2')
                        ->whereColumn(
                            'payout_profiles.user_id',
                            'users.id'
                        )
                        ->limit(1),

                'city' =>
                    DB::table('payout_profiles')
                        ->select('city')
                        ->whereColumn(
                            'payout_profiles.user_id',
                            'users.id'
                        )
                        ->limit(1),

                'state' =>
                    DB::table('payout_profiles')
                        ->select('state')
                        ->whereColumn(
                            'payout_profiles.user_id',
                            'users.id'
                        )
                        ->limit(1),

                'postal_code' =>
                    DB::table('payout_profiles')
                        ->select('postal_code')
                        ->whereColumn(
                            'payout_profiles.user_id',
                            'users.id'
                        )
                        ->limit(1),

                'country_code' =>
                    DB::table('payout_profiles')
                        ->select('country_code')
                        ->whereColumn(
                            'payout_profiles.user_id',
                            'users.id'
                        )
                        ->limit(1),

                /*
                 * Sensitive encrypted values intentionally excluded:
                 * - pan_number
                 * - bank_account_number
                 */

                'gst_number' =>
                    DB::table('payout_profiles')
                        ->select('gst_number')
                        ->whereColumn(
                            'payout_profiles.user_id',
                            'users.id'
                        )
                        ->limit(1),

                'account_holder_name' =>
                    DB::table('payout_profiles')
                        ->select('account_holder_name')
                        ->whereColumn(
                            'payout_profiles.user_id',
                            'users.id'
                        )
                        ->limit(1),

                'bank_name' =>
                    DB::table('payout_profiles')
                        ->select('bank_name')
                        ->whereColumn(
                            'payout_profiles.user_id',
                            'users.id'
                        )
                        ->limit(1),

                'ifsc_code' =>
                    DB::table('payout_profiles')
                        ->select('ifsc_code')
                        ->whereColumn(
                            'payout_profiles.user_id',
                            'users.id'
                        )
                        ->limit(1),

                'branch_name' =>
                    DB::table('payout_profiles')
                        ->select('branch_name')
                        ->whereColumn(
                            'payout_profiles.user_id',
                            'users.id'
                        )
                        ->limit(1),

                'upi_id' =>
                    DB::table('payout_profiles')
                        ->select('upi_id')
                        ->whereColumn(
                            'payout_profiles.user_id',
                            'users.id'
                        )
                        ->limit(1),
            ])
            ->addSelect([
                'owned_labels_count' => Label::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('labels.user_id', 'users.id'),
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
                            'username',
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

        if ($invitationFilter === 'pending') {
            $query->whereIn(
                'invitation_status',
                [
                    'pending',
                    'sent',
                    'password_pending',
                    'failed',
                    'expired',
                ]
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

                'searchSuggestions' =>
                    User::query()
                        ->select([
                            'id',
                            'name',
                            'username',
                            'email',
                            'phone',
                            'label_name',
                            'role',
                        ])
                        ->orderBy('name')
                        ->limit(500)
                        ->get(),

                'filters' => [
                    'search' => $search,
                    'role' => $roleFilter,
                    'status' => $status,
                    'invitation' => $invitationFilter,
                ],

                /*
                 * MIXXTUNE_USER_QUICK_EDIT_PAYLOAD_V1
                 */
                'quickEditManagers' =>
                    User::query()
                        ->where('role', 'admin')
                        ->where(
                            'account_status',
                            'active'
                        )
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'username',
                            'email',
                        ]),

                /*
                 * MIXXTUNE_QUICK_AVAILABLE_LABELS_V1
                 *
                 * Existing labels eligible for Quick Add.
                 * Do not expose labels owned by another account.
                 */
                // MIXXTUNE_QUICK_EDIT_AVAILABLE_ARTISTS_V1
                'quickEditAvailableArtists' =>
                    Artist::query()
                        ->whereNull('deleted_at')
                        ->orderBy('stage_name')
                        ->get([
                            'id',
                            'stage_name',
                            'user_id',
                            'label_id',
                        ]),

                'quickEditAvailableLabels' =>
                    Label::query()
                        ->whereNull('deleted_at')
                        ->whereNull('user_id')
                        ->where('status', 'active')
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'parent_label_id',
                            'revenue_share_percentage',
                        ]),

                'quickEditMasterLabels' =>
                    Label::query()
                        ->whereNull('deleted_at')
                        ->whereNull('parent_label_id')
                        ->where('status', 'active')
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'user_id',
                            'revenue_share_percentage',
                        ]),

                'quickEditLabelManagers' =>
                    \Illuminate\Support\Facades\DB::table(
                        'admin_label_assignments as ala'
                    )
                        ->join(
                            'users as manager_users',
                            'manager_users.id',
                            '=',
                            'ala.user_id'
                        )
                        ->where(
                            'ala.assignment_role',
                            'manager'
                        )
                        ->where(
                            'manager_users.role',
                            'admin'
                        )
                        ->where(
                            'manager_users.account_status',
                            'active'
                        )
                        ->get([
                            'ala.label_id',
                            'ala.user_id as manager_id',
                            'manager_users.name as manager_name',
                        ]),

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
                /* MIXXTUNE_USER_REQUIRED_FIELDS_V1 */
                'requiredFields' => app(\App\Services\V2\UserFieldRequirementService::class)->all(),
                'requiredFieldOptions' => \App\Services\V2\UserFieldRequirementService::options(),
                'canEditRequiredFields' => request()->user()?->role === 'super_admin',
                'accountType' => request()->query('type') === 'manager' ? 'manager' : 'user',

                'artists' =>
                    Artist::query()
                        ->orderBy('stage_name')
                        ->get([
                            'id',
                            'stage_name',
                            'email',
                        ]),

                /* W79: owner + parent so the form only offers labels that are still free */
                'labels' =>
                    Label::query()
                        ->whereNull('deleted_at')
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'email',
                            'user_id',
                            'parent_label_id',
                            'revenue_share_percentage',
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

        /* MIXXTUNE_USER_REQUIRED_FIELDS_V1: per-role required fields chosen by super admin */
        $fieldRequirements = app(\App\Services\V2\UserFieldRequirementService::class);
        /* W80: "owner fills KYC & bank after login" - those fields are not required now */
        $kycByOwner = $request->boolean('kyc_by_owner');
        $ownerFilled = ['pan_number', 'gst_number', 'account_holder_name', 'bank_account_number', 'bank_name', 'ifsc_code', 'branch_name', 'upi_id'];
        $fieldReq = fn (string $field) => \Illuminate\Validation\Rule::requiredIf(
            fn () => ! ($kycByOwner && in_array($field, $ownerFilled, true))
                && $fieldRequirements->isRequired((string) $request->input('role'), $field)
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            /* W79: account name = name; the person behind it = full_name */
            /* nullable here: ownership transfer / bulk import also create users through this method */
            'full_name' => [
                'nullable',
                'string',
                'max:150',
            ],
            'label_rates' => [
                'nullable',
                'array',
            ],
            'label_rates.*' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'new_label_rates' => [
                'nullable',
                'array',
            ],
            'new_label_rates.*' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'username' => [
                'nullable',
                $fieldReq('username'),
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
                $fieldReq('phone'),
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
                $fieldReq('state'),
                'string',
                'max:10',
            ],

            'role' => [
                'required',
                'string',
                'in:admin,label,artist',
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
                $fieldReq('account_holder_name'),
                'string',
                'max:190',
            ],

            'bank_account_number' => [
                'nullable',
                $fieldReq('bank_account_number'),
                'string',
                'max:60',
            ],

            'bank_name' => [
                'nullable',
                $fieldReq('bank_name'),
                'string',
                'max:190',
            ],

            'ifsc_code' => [
                'nullable',
                $fieldReq('ifsc_code'),
                'string',
                'max:30',
            ],

            'branch_name' => [
                'nullable',
                $fieldReq('branch_name'),
                'string',
                'max:190',
            ],

            'upi_id' => [
                'nullable',
                $fieldReq('upi_id'),
                'string',
                'max:190',
            ],

            'pan_number' => [
                'nullable',
                $fieldReq('pan_number'),
                'string',
                'max:30',
            ],

            'gst_number' => [
                'nullable',
                $fieldReq('gst_number'),
                'string',
                'max:40',
            ],

            'address_line_1' => [
                'nullable',
                $fieldReq('address_line_1'),
                'string',
                'max:255',
            ],

            'address_line_2' => [
                'nullable',
                $fieldReq('address_line_2'),
                'string',
                'max:255',
            ],

            'city' => [
                'nullable',
                $fieldReq('city'),
                'string',
                'max:120',
            ],

            'state' => [
                'nullable',
                $fieldReq('state'),
                'string',
                'max:120',
            ],

            'postal_code' => [
                'nullable',
                $fieldReq('postal_code'),
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
             * FF380-D:
             * A Label login may own multiple independent canonical labels.
             *
             * At least one existing/new label is required, but multiple
             * labels are valid. Each label keeps its own revenue rate.
             */

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
                    'Username may contain lowercase letters and numbers only.',
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

        $ratesBefore = $validated['role'] === 'label' ? $this->labelRatesBefore($validated) : [];

        /*
         * W79: an existing label picked on the form becomes owned by the new
         * account when it has no owner yet. Giving a label an owner is a
         * money-sensitive step, so it needs the e-mailed code (nothing is
         * saved before the code is entered).
         */
        $orphanLabelIds = $validated['role'] === 'label' && ! app()->bound('mixxtune.ownership_tool_create')
            ? Label::query()->whereIn('id', array_map('intval', $validated['label_ids'] ?? []))->whereNull('user_id')->whereNull('deleted_at')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
        if ($orphanLabelIds !== []) {
            app(\App\Services\V2\ActionOtpGuard::class)->require(
                $request->user(),
                'user.create.link_labels:'.implode(',', $orphanLabelIds),
                'give existing labels to the new account'
            );
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

                $user = User::query()->create([
                    'name' =>
                        $validated['name'],

                    'full_name' =>
                        filled($validated['full_name'] ?? null) ? trim($validated['full_name']) : null,

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
                            (string) ($validated['state_code'] ?? null)
                        ) !== ''
                            ? strtoupper(
                                trim(
                                    ($validated['state_code'] ?? null)
                                )
                            )
                            : null,

                    'role' =>
                        $validated['role'],

                    /*
                     * Account activation is system-controlled.
                     * A newly created account remains pending
                     * until the invitation is accepted and the
                     * user sets their password.
                     */
                    'account_status' =>
                        'pending',

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

                    'invitation_sent_at' =>
                        null,

                    'invitation_expires_at' =>
                        null,

                    'invitation_count' =>
                        0,
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
                                ($validated['account_holder_name'] ?? null),

                            'bank_account_number' =>
                                ($validated['bank_account_number'] ?? null),

                            'bank_name' =>
                                ($validated['bank_name'] ?? null),

                            'ifsc_code' =>
                                strtoupper(
                                    trim(
                                        ($validated['ifsc_code'] ?? null)
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
                                        ($validated['pan_number'] ?? null)
                                    )
                                ),

                            'gst_number' =>
                                !empty(
                                    ($validated['gst_number'] ?? null)
                                )
                                    ? strtoupper(
                                        trim(
                                            ($validated['gst_number'] ?? null)
                                        )
                                    )
                                    : null,

                            'address_line_1' =>
                                ($validated['address_line_1'] ?? null),

                            'address_line_2' =>
                                $validated[
                                    'address_line_2'
                                ]
                                ?? null,

                            'city' =>
                                ($validated['city'] ?? null),

                            'state' =>
                                ($validated['state'] ?? null),

                            'postal_code' =>
                                ($validated['postal_code'] ?? null),

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
                            'pending',

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

                    /* W79: each new label row has its own % (else the account %) */

                    $newRates = [];

                    foreach (array_values($validated['new_labels'] ?? []) as $rateIndex => $rateName) {

                        $typed = array_values($validated['new_label_rates'] ?? [])[$rateIndex] ?? null;

                        if (trim((string) $rateName) !== '' && $typed !== null && $typed !== '') {

                            $newRates[mb_strtolower(trim((string) $rateName))] = (float) $typed;

                        }

                    }

                    $accountRate = isset($validated['revenue_share_percentage']) && $validated['revenue_share_percentage'] !== '' ? (float) $validated['revenue_share_percentage'] : null;

                    $newRateFor = fn (string $name) => $newRates[mb_strtolower(trim($name))] ?? $accountRate;


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
                            app(LabelIdentityService::class)
                                ->representative(
                                    $labelName
                                );

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

                                    /* W79: an owner never changes a label's existing % */
                                    'revenue_share_percentage' =>
                                        $label->revenue_share_percentage ?? $newRateFor($labelName),

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
                                        $newRateFor($labelName),

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

                /* W81: new accounts follow the role defaults; old boxes only if a form still sends them */
                if ($request->has('permissions')) {
                    UserPanelPermission::query()
                        ->updateOrCreate(
                            [
                                'user_id' =>
                                    $user->id,
                            ],
                            $permissionValues
                        );
                }

                return $user;
            }
        );

        /* W79: picked labels without an owner now belong to this account */
        foreach ($orphanLabelIds as $orphanId) {
            DB::transaction(function () use ($orphanId, $user, $request, $audit) {
                $label = Label::query()->whereKey($orphanId)->whereNull('user_id')->lockForUpdate()->first();
                if (! $label) {
                    return;
                }
                $label->forceFill(['user_id' => $user->id, 'updated_by' => $request->user()->id])->save();
                $audit->record('label.owner_assigned', 'labels', "Label {$label->name} (#{$label->id}) given to new account {$user->email}.", ['user_id' => null], ['user_id' => $user->id], $label, $request);
            });
        }

        /* W79: each label row on the form has its own revenue % (a label that already has a % keeps it) */
        if ($validated['role'] === 'label') {
            $this->applyLabelRates($user, $validated, $request, $ratesBefore);
        }

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

        abort_unless(
            in_array(
                $user->role,
                ['admin', 'label', 'artist'],
                true
            ),
            422,
            'This user cannot receive an invitation.'
        );

        abort_unless(
            $user->invitation_status !== 'accepted',
            422,
            'This invitation has already been accepted.'
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
                    $user->fresh()->invitation_status,
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

        /* W81: only read the old boxes - opening this page used to save them all unticked */
        $panelPermissions =
            UserPanelPermission::query()
                ->where('user_id', $user->id)
                ->first()
            ?? new UserPanelPermission();

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

                'ownedLabels' =>
                    Label::query()
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->whereNull('deleted_at')
                        ->where('status', 'active')
                        ->orderBy('id')
                        ->get([
                            'id',
                            'name',
                            'revenue_share_percentage',
                        ])
                        ->map(
                            fn (Label $label) => [
                                'id' =>
                                    (int) $label->id,

                                'name' =>
                                    $label->name,

                                'revenue_share_percentage' =>
                                    $label->revenue_share_percentage !== null
                                        ? (float) $label->revenue_share_percentage
                                        : null,
                            ]
                        )
                        ->values(),

                /* W94 MIXXTUNE_ACCOUNT_AGREEMENTS */
                'agreement' => app(\App\Services\V2\AccountAgreements::class)->forUser($user),

                /* W80 MIXXTUNE_KYC_EDIT_LOCK */
                'kycLock' => in_array($user->role, ['label', 'artist'], true) ? $this->kycLockPayload($user) : null,
                'panelTabs' => app(\App\Services\V2\PanelAccess::class)->handles((string) $user->role) ? app(\App\Services\V2\PanelAccess::class)->tabsFor($user) : null,

                'managedUser' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,

                    /*
                     * MIXXTUNE_QUICK_EDIT_FIELD_CONFIG_PAYLOAD_V1
                     */
                    'quick_edit_fields' =>
                        $user->quick_edit_fields,

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

    /**
     * MIXXTUNE_USER_QUICK_EDIT_V1
     *
     * Compact User Management action.
     *
     * This endpoint intentionally updates only fields exposed
     * by the Quick Edit modal. It does not replace the full
     * User Edit workflow.
     */
    /*
     * MIXXTUNE_QUICK_LABEL_OPERATION_V2
     *
     * Quick Edit label operation:
     * 1. Add existing unowned label
     * 2. Create/reuse canonical label
     *
     * Never transfers ownership from another account.
     */
    public function quickLabel(
        Request $request,
        User $user,
        PermissionService $permissions
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        if ($user->role !== 'label') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'label' => [
                    'Labels can only be added to a Label account.',
                ],
            ]);
        }

        $validated = $request->validate([
            'mode' => [
                'required',
                'string',
                'in:existing,create',
            ],

            'label_id' => [
                'nullable',
                'integer',
                'exists:labels,id',
            ],

            'name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'revenue_share_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
        ]);

        if (
            $validated['mode'] === 'existing'
            && empty($validated['label_id'])
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'label_id' => [
                    'Please select an existing label.',
                ],
            ]);
        }

        if (
            $validated['mode'] === 'create'
            && trim(
                (string) ($validated['name'] ?? '')
            ) === ''
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => [
                    'Label name is required.',
                ],
            ]);
        }

        DB::transaction(
            function () use (
                $validated,
                $request,
                $user
            ): void {
                $actorId =
                    (int) $request->user()->id;

                $rate =
                    (float) $validated[
                        'revenue_share_percentage'
                    ];

                if (
                    $validated['mode']
                    === 'existing'
                ) {
                    $label =
                        Label::query()
                            ->whereNull(
                                'deleted_at'
                            )
                            ->lockForUpdate()
                            ->findOrFail(
                                (int) $validated[
                                    'label_id'
                                ]
                            );

                    if (
                        $label->user_id
                        && (int) $label->user_id
                            !== (int) $user->id
                    ) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'label_id' => [
                                "Label '{$label->name}' already belongs to another Label account.",
                            ],
                        ]);
                    }
                } else {
                    $labelName =
                        trim(
                            (string)
                            $validated['name']
                        );

                    /*
                     * Reuse canonical identity.
                     * Prevent creation of another
                     * normalized duplicate.
                     */
                    $label =
                        app(
                            LabelIdentityService::class
                        )->representative(
                            $labelName
                        );

                    if (
                        $label
                        && $label->user_id
                        && (int) $label->user_id
                            !== (int) $user->id
                    ) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'name' => [
                                "Label '{$labelName}' already belongs to another Label account.",
                            ],
                        ]);
                    }

                    if (! $label) {
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

                                'parent_label_id' =>
                                    null,

                                'label_type' =>
                                    'label',

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
                                            (string)
                                            $user->email
                                        )
                                    ),

                                'phone' =>
                                    $user->phone
                                    ?? null,

                                'country' =>
                                    $user->country
                                    ?? 'India',

                                'timezone' =>
                                    'Asia/Kolkata',

                                'currency' =>
                                    'INR',

                                'revenue_share_percentage' =>
                                    $rate,

                                'status' =>
                                    'active',

                                'created_by' =>
                                    $actorId,

                                'updated_by' =>
                                    $actorId,
                            ]);
                    }
                }

                /*
                 * Final ownership guard.
                 */
                if (
                    $label->user_id
                    && (int) $label->user_id
                        !== (int) $user->id
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'label_id' => [
                            "Label '{$label->name}' already belongs to another Label account.",
                        ],
                    ]);
                }

                $oldRate =
                    $label
                        ->revenue_share_percentage;

                $label->forceFill([
                    'user_id' =>
                        $user->id,

                    'revenue_share_percentage' =>
                        $rate,

                    'updated_by' =>
                        $actorId,
                ])->save();

                /*
                 * Add owner pivot WITHOUT detaching
                 * the account's other labels.
                 */
                $user
                    ->assignedLabels()
                    ->syncWithoutDetaching([
                        (int) $label->id => [
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
                                $actorId,
                        ],
                    ]);

                if (
                    $oldRate === null
                    || (float) $oldRate
                        !== $rate
                ) {
                    app(
                        ReportEarningsRecalculationService::class
                    )->recalculateForLabel(
                        $label
                    );
                }
            }
        );

        return back()->with(
            'success',
            $validated['mode'] === 'existing'
                ? 'Label added successfully.'
                : 'Label created and added successfully.'
        );
    }


    
    /**
     * MIXXTUNE_QUICK_EDIT_FIELD_CONFIG_SAVE_V1
     *
     * Controls which Full Edit fields are exposed in Quick Edit.
     * This changes presentation only; it does not change the field value.
     */
    public function updateQuickEditFields(
        Request $request,
        User $user,
        PermissionService $permissions
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $allowed = [
            'name',
            'username',
            'client_id',
            'email',
            'phone',
            'country',
            'role',
            'revenue_share_percentage',
            'label_revenue_rates',
            'account_status',
            'kyc_status',

            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'country_code',

            'pan_number',
            'gst_number',

            'account_holder_name',
            'bank_account_number',
            'bank_name',
            'ifsc_code',
            'branch_name',
            'upi_id',

            'artist_ids',
            'label_ids',
            'new_labels',
            'permissions',
        ];

        $validated = $request->validate([
            'fields' => [
                'present',
                'array',
            ],
            'fields.*' => [
                'string',
                \Illuminate\Validation\Rule::in($allowed),
            ],
        ]);

        $fields = collect(
            $validated['fields'] ?? []
        )
            ->map(
                fn ($field) =>
                    trim((string) $field)
            )
            ->filter(
                fn ($field) =>
                    in_array(
                        $field,
                        $allowed,
                        true
                    )
            )
            ->unique()
            ->values()
            ->all();

        $user->forceFill([
            'quick_edit_fields' => $fields,
        ])->save();

        return back()->with(
            'success',
            'Quick Edit fields updated.'
        );
    }


public function quickUpdate(
        Request $request,
        User $user,
        PermissionService $permissions
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        /* W59: a super admin is changed only on the full Edit page; same safety rules as Edit */
        abort_if($user->role === 'super_admin', 422, 'Use the full Edit page for a super admin.');
        $this->guardSensitiveUserChange($request, $user, (string) $request->input('role', $user->role), (string) $request->input('account_status', $user->account_status), (string) $request->input('email', $user->email));
        $beforeQuick = $user->only(['name', 'email', 'account_status', 'role', 'kyc_status', 'phone', 'country']);

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

            'account_status' => [
                'required',
                'string',
                'in:active,pending,suspended',
            ],

            // MIXXTUNE_QUICK_EDIT_ACCOUNT_FIELDS_V1
            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'country' => [
                'nullable',
                'string',
                'max:100',
            ],

            'role' => [
                'nullable',
                'string',
                'in:admin,label,artist',
            ],

            'kyc_status' => [
                'nullable',
                'string',
                'in:pending,submitted,verified,rejected',
            ],

            // MIXXTUNE_QUICK_EDIT_PAYOUT_VALIDATION_V1
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
                'max:30',
            ],

            'country_code' => [
                'nullable',
                'string',
                'max:10',
            ],

            'pan_number' => [
                'nullable',
                'string',
                'max:50',
            ],

            'gst_number' => [
                'nullable',
                'string',
                'max:50',
            ],

            'account_holder_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'bank_account_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'bank_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'ifsc_code' => [
                'nullable',
                'string',
                'max:50',
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

            // MIXXTUNE_QUICK_EDIT_ASSIGNMENT_VALIDATION_V1
            'artist_ids' => [
                'sometimes',
                'array',
            ],

            'artist_ids.*' => [
                'integer',
                Rule::exists(
                    'artists',
                    'id'
                ),
            ],

            'label_ids' => [
                'sometimes',
                'array',
            ],

            'label_ids.*' => [
                'integer',
                Rule::exists(
                    'labels',
                    'id'
                ),
            ],

            // MIXXTUNE_QUICK_EDIT_PERMISSION_VALIDATION_V1
            'permissions' => [
                'sometimes',
                'array',
            ],

            'permissions.*' => [
                'boolean',
            ],

            'labels' => [
                'nullable',
                'array',
            ],

            'labels.*.id' => [
                'required',
                'integer',
                'exists:labels,id',
            ],

            'labels.*.name' => [
                'required',
                'string',
                'max:255',
            ],

            'labels.*.parent_label_id' => [
                'nullable',
                'integer',
                'exists:labels,id',
            ],

            'labels.*.revenue_share_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'labels.*.manager_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'labels.*.status' => [
                'required',
                'string',
                'in:active,inactive',
            ],

            'labels.*.can_withdraw' => [
                'required',
                'boolean',
            ],
        ]);

        DB::transaction(
            function () use (
                $validated,
                $request,
                $user
            ): void {
                $user->forceFill([
                    'name' =>
                        trim($validated['name']),

                    'email' =>
                        strtolower(
                            trim($validated['email'])
                        ),

                    'account_status' =>
                        $validated['account_status'],

                    'phone' =>
                        array_key_exists(
                            'phone',
                            $validated
                        )
                            ? $validated['phone']
                            : $user->phone,

                    'country' =>
                        array_key_exists(
                            'country',
                            $validated
                        )
                            ? $validated['country']
                            : $user->country,

                    'role' =>
                        array_key_exists(
                            'role',
                            $validated
                        )
                            ? $validated['role']
                            : $user->role,

                    'kyc_status' =>
                        array_key_exists(
                            'kyc_status',
                            $validated
                        )
                            ? $validated['kyc_status']
                            : $user->kyc_status,
                ])->save();

                // MIXXTUNE_QUICK_EDIT_PAYOUT_SAVE_V1
                $payoutKeys = [
                    'address_line_1',
                    'address_line_2',
                    'city',
                    'state',
                    'postal_code',
                    'country_code',
                    'pan_number',
                    'gst_number',
                    'account_holder_name',
                    'bank_account_number',
                    'bank_name',
                    'ifsc_code',
                    'branch_name',
                    'upi_id',
                ];

                $payoutData = [];

                foreach ($payoutKeys as $key) {
                    if (
                        array_key_exists(
                            $key,
                            $validated
                        )
                    ) {
                        $value =
                            $validated[$key];

                        $payoutData[$key] =
                            is_string($value)
                                ? trim($value)
                                : $value;
                    }
                }

                $existingPayout =
                    \App\Models\Finance\PayoutProfile::query()
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->first();

                /*
                 * Blank sensitive values mean:
                 * preserve existing encrypted value.
                 */
                if (
                    empty(
                        $payoutData[
                            'bank_account_number'
                        ] ?? null
                    )
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
                    && $payoutData[
                        'country_code'
                    ] !== ''
                ) {
                    $payoutData[
                        'country_code'
                    ] =
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
                    && $payoutData[
                        'ifsc_code'
                    ] !== ''
                ) {
                    $payoutData[
                        'ifsc_code'
                    ] =
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
                    $payoutData[
                        'pan_number'
                    ] =
                        strtoupper(
                            $payoutData[
                                'pan_number'
                            ]
                        );
                }

                /*
                 * Never fabricate an empty payout profile.
                 */
                /* W59: the default country alone is not payout data (it made empty profiles) */
                $hasPayoutData =
                    collect($payoutData)
                        ->except(['country_code'])
                        ->contains(
                            fn ($value) =>
                                $value !== null
                                && $value !== ''
                        );

                if (
                    $existingPayout
                    || $hasPayoutData
                ) {
                    /*
                     * payout_profiles.country_code is NOT NULL.
                     */
                    if (
                        empty(
                            $payoutData[
                                'country_code'
                            ] ?? null
                        )
                    ) {
                        $payoutData[
                            'country_code'
                        ] =
                            $existingPayout?->country_code
                            ?: 'IN';
                    }

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

                // MIXXTUNE_QUICK_EDIT_PERMISSION_SAVE_V1
                if (
                    array_key_exists(
                        'permissions',
                        $validated
                    )
                    && $request->boolean('permissions_touched')
                ) {
                    $permissionData = [];

                    foreach (
                        $this->permissionFields()
                        as $permissionField
                    ) {
                        $permissionData[
                            $permissionField
                        ] = (bool) (
                            $validated[
                                'permissions'
                            ][
                                $permissionField
                            ] ?? false
                        );
                    }

                    $permissionData[
                        'updated_by'
                    ] = $request->user()->id;

                    UserPanelPermission::query()
                        ->updateOrCreate(
                            [
                                'user_id' =>
                                    $user->id,
                            ],
                            $permissionData
                        );
                }

                // MIXXTUNE_QUICK_EDIT_ASSIGNMENT_SAVE_V1
                if ($user->role === 'admin') {
                    $assignmentPivot = [
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

                    /*
                     * Only sync a relation when that field
                     * was actually submitted by Quick Edit.
                     *
                     * This preserves hidden/disabled fields.
                     */
                    if (
                        array_key_exists(
                            'artist_ids',
                            $validated
                        )
                    ) {
                        $artistSync = [];

                        foreach (
                            $validated['artist_ids']
                            as $artistId
                        ) {
                            $artistSync[
                                (int) $artistId
                            ] = $assignmentPivot;
                        }

                        $user
                            ->assignedArtists()
                            ->sync(
                                $artistSync
                            );
                    }

                    if (
                        array_key_exists(
                            'label_ids',
                            $validated
                        )
                    ) {
                        $labelSync = [];

                        foreach (
                            $validated['label_ids']
                            as $labelId
                        ) {
                            $labelSync[
                                (int) $labelId
                            ] = $assignmentPivot;
                        }

                        $user
                            ->assignedLabels()
                            ->sync(
                                $labelSync
                            );
                    }
                }

                foreach (
                    $validated['labels'] ?? []
                    as $labelData
                ) {
                    /* W59: rows of labels this user does not own (e.g. a manager's assigned labels) are skipped, not a 404 */
                    if (!Label::query()->whereNull('deleted_at')->where('user_id', $user->id)->whereKey((int) ($labelData['id'] ?? 0))->exists()) {
                        continue;
                    }

                    $label = Label::query()
                        ->whereNull('deleted_at')
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->lockForUpdate()
                        ->findOrFail(
                            (int) $labelData['id']
                        );

                    $newName = trim(
                        $labelData['name']
                    );

                    /*
                     * Prevent another active canonical label
                     * with the same normalized name.
                     */
                    $normalizedName =
                        mb_strtolower(
                            preg_replace(
                                '/[^a-z0-9]+/i',
                                '',
                                $newName
                            )
                        );

                    $duplicate = Label::query()
                        ->whereNull('deleted_at')
                        ->where(
                            'id',
                            '<>',
                            $label->id
                        )
                        ->get([
                            'id',
                            'name',
                        ])
                        ->first(
                            function ($candidate) use (
                                $normalizedName
                            ) {
                                return mb_strtolower(
                                    preg_replace(
                                        '/[^a-z0-9]+/i',
                                        '',
                                        trim(
                                            (string)
                                            $candidate->name
                                        )
                                    )
                                ) === $normalizedName;
                            }
                        );

                    if ($duplicate) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'labels' => [
                                "Label '{$newName}' already exists as label #{$duplicate->id}.",
                            ],
                        ]);
                    }

                    $parentId =
                        $labelData[
                            'parent_label_id'
                        ] ?? null;

                    if ($parentId !== null) {
                        $parentId =
                            (int) $parentId;

                        abort_if(
                            $parentId ===
                                (int) $label->id,
                            422,
                            'A label cannot be its own parent.'
                        );

                        $parent = Label::query()
                            ->whereNull(
                                'deleted_at'
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->findOrFail(
                                $parentId
                            );

                        abort_if(
                            $parent->parent_label_id
                                !== null,
                            422,
                            'Only a Master Label can be selected as parent.'
                        );
                    }

                    $oldRate =
                        $label
                            ->revenue_share_percentage;

                    $newRate =
                        $labelData[
                            'revenue_share_percentage'
                        ] ?? null;

                    $label->forceFill([
                        'name' => $newName,

                        'parent_label_id' =>
                            $parentId,

                        'revenue_share_percentage' =>
                            $newRate === null
                                || $newRate === ''
                                ? null
                                : (float) $newRate,

                        'status' =>
                            $labelData['status'],

                        'can_withdraw' =>
                            (bool) $labelData[
                                'can_withdraw'
                            ],

                        'updated_by' =>
                            $request->user()->id,
                    ])->save();

                    if (
                        $newRate !== null
                        && $newRate !== ''
                        && (
                            $oldRate === null
                            || (float) $oldRate
                                !== (float) $newRate
                        )
                    ) {
                        app(
                            ReportEarningsRecalculationService::class
                        )->recalculateForLabel(
                            $label
                        );
                    }

                    /* W59: managers change only when a different manager was chosen (co-managers were dropped before) */
                    $currentManagerIds = DB::table('admin_label_assignments')->where('label_id', $label->id)->where('assignment_role', 'manager')->pluck('user_id')->map(fn ($v) => (int) $v);
                    $wantedManager = (int) ($labelData['manager_id'] ?? 0);
                    $managerUnchanged = !array_key_exists('manager_id', $labelData)
                        || ($wantedManager > 0 && $currentManagerIds->contains($wantedManager))
                        || ($wantedManager === 0 && $currentManagerIds->isEmpty());

                    if (!$managerUnchanged) {
                        /*
                         * Manager assignment:
                         * schema uses user_id, not admin_id.
                         */
                        DB::table(
                            'admin_label_assignments'
                        )
                            ->where(
                                'label_id',
                                $label->id
                            )
                            ->where(
                                'assignment_role',
                                'manager'
                            )
                            ->delete();

                        $managerId =
                            $labelData[
                                'manager_id'
                            ] ?? null;

                        if ($managerId) {
                            $manager = User::query()
                                ->whereKey(
                                    (int) $managerId
                                )
                                ->where(
                                    'role',
                                    'admin'
                                )
                                ->where(
                                    'account_status',
                                    'active'
                                )
                                ->firstOrFail();

                            DB::table(
                                'admin_label_assignments'
                            )->insert([
                                'user_id' =>
                                    $manager->id,

                                'label_id' =>
                                    $label->id,

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

                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        );

        try {
            app(AuditLogService::class)->record('user.quick_updated', 'users', 'Quick Edit saved for '.$user->email.'.', $beforeQuick, $user->fresh()->only(['name', 'email', 'account_status', 'role', 'kyc_status', 'phone', 'country']), $user, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with(
            'success',
            'Quick Edit changes saved.'
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

        $this->guardSensitiveUserChange($request, $user, (string) $request->input('role', $user->role), (string) $request->input('account_status', $user->account_status), (string) $request->input('email', $user->email));

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

            /* W79 */
            'full_name' => [
                'nullable',
                'string',
                'max:150',
            ],
            'new_label_rates' => [
                'nullable',
                'array',
            ],
            'new_label_rates.*' => [
                'nullable',
                'numeric',
                'min:0',
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
                'required_if:role,artist',
                'numeric',
                'min:0',
                'max:100',
            ],

            'label_revenue_rates' => [
                'nullable',
                'array',
            ],

            'label_revenue_rates.*' => [
                'nullable',
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
                        'A Label account must have at least one label.',
                    ],
                ]);
            }

            /*
             * FF380-F:
             * Label accounts may own multiple canonical labels.
             * Each label can maintain its own revenue rate.
             */


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

        $ratesBefore = $validated['role'] === 'label' ? $this->labelRatesBefore(['new_labels' => $validated['new_labels'] ?? []]) : [];

        DB::transaction(
            function () use (
                $validated,
                $request,
                $user
            ) {
                $user->update([
                    'name' =>
                        $validated['name'],

                    'full_name' =>
                        filled($validated['full_name'] ?? null) ? trim($validated['full_name']) : $user->full_name,

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
                    $rateArtists = Artist::query()
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->lockForUpdate()
                        ->get();

                    foreach ($rateArtists as $rateArtist) {
                        $rateArtist->forceFill([
                            'revenue_share_percentage' =>
                                (float) $validated[
                                    'revenue_share_percentage'
                                ],

                            'updated_by' =>
                                $request->user()->id,
                        ])->save();

                        app(
                            ReportEarningsRecalculationService::class
                        )->recalculateForArtist(
                            $rateArtist
                        );
                    }
                } elseif ($validated['role'] === 'label') {
                    /*
                     * Revenue rate is label-level commercial
                     * configuration. A Label login may own
                     * multiple independent canonical labels.
                     *
                     * Update only explicitly submitted owned
                     * label rates. Never propagate one rate
                     * across sibling/independent labels.
                     */
                    $submittedRates = collect(
                        $validated['label_revenue_rates']
                            ?? []
                    );

                    foreach (
                        $submittedRates
                        as $labelId => $submittedRate
                    ) {
                        if ($submittedRate === null
                            || $submittedRate === '') {
                            continue;
                        }

                        $rateLabel = Label::query()
                            ->where(
                                'user_id',
                                $user->id
                            )
                            ->whereNull('deleted_at')
                            ->where('status', 'active')
                            ->lockForUpdate()
                            ->findOrFail(
                                (int) $labelId
                            );

                        $newRate =
                            (float) $submittedRate;

                        $oldRate =
                            $rateLabel
                                ->revenue_share_percentage;

                        if (
                            $oldRate !== null
                            && (float) $oldRate === $newRate
                        ) {
                            continue;
                        }

                        $rateLabel->forceFill([
                            'revenue_share_percentage' =>
                                $newRate,

                            'updated_by' =>
                                $request->user()->id,
                        ])->save();

                        app(
                            ReportEarningsRecalculationService::class
                        )->recalculateForLabel(
                            $rateLabel
                        );
                    }
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

                    $user
                        ->assignedLabels()
                        ->sync(
                            $labelSync
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

                        /*
                         * FF373-D:
                         * Revenue rate was already saved above from
                         * label_revenue_rates[label_id].
                         *
                         * Do not overwrite it with the generic
                         * revenue_share_percentage field here.
                         */

                        $labelSync[
                            (int) $label->id
                        ] = $ownerPivot;
                    }

                    $newRates = [];
                    foreach (array_values($validated['new_labels'] ?? []) as $rateIndex => $rateName) {
                        $typed = array_values($validated['new_label_rates'] ?? [])[$rateIndex] ?? null;
                        if (trim((string) $rateName) !== '' && $typed !== null && $typed !== '') {
                            $newRates[mb_strtolower(trim((string) $rateName))] = (float) $typed;
                        }
                    }
                    $accountRate = isset($validated['revenue_share_percentage']) && $validated['revenue_share_percentage'] !== '' ? (float) $validated['revenue_share_percentage'] : null;
                    $newRateFor = fn (string $name) => $newRates[mb_strtolower(trim($name))] ?? $accountRate;

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
                            app(LabelIdentityService::class)
                                ->representative(
                                    $labelName
                                );

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

                                    /* W79: the % typed next to this new label (else the account %, else Rate Pending) */
                                    'revenue_share_percentage' =>
                                        $newRateFor($labelName),

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

                        /*
                         * W79: before, every label typed here got the account % written over
                         * its own % (and a 500 when the form sent no account %). Now only a
                         * label without a % gets the one typed next to it.
                         */
                        if ($label->revenue_share_percentage === null && $newRateFor($labelName) !== null) {
                            $label->forceFill([
                                'revenue_share_percentage' => $newRateFor($labelName),
                                'updated_by' => $request->user()->id,
                            ])->save();
                        }

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


                /* W81: the old boxes are written only when a form still sends them */
                if ($request->has('permissions')) {
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

                /* W81 MIXXTUNE_PANEL_ACCESS: allow / block single sidebar tabs for this account */
                if (
                    $request->boolean('tab_overrides_touched')
                    && is_array($request->input('tab_overrides'))
                    && app(\App\Services\V2\PanelAccess::class)->handles((string) $user->role)
                ) {
                    $tabChoices = $request->validate([
                        'tab_overrides' => ['array'],
                        'tab_overrides.*' => ['in:default,allow,block'],
                    ])['tab_overrides'];

                    $tabChanges = app(\App\Services\V2\PanelAccess::class)
                        ->saveOverrides($user, $tabChoices, (int) $request->user()->id);

                    if ($tabChanges) {
                        app(\App\Services\V2\AuditLogService::class)->record(
                            'panel.user_access_changed',
                            'permissions',
                            'Panel access of '.($user->name ?: $user->email).' changed: '.implode(', ', array_map(fn ($k, $c) => $k.' '.$c['to'], array_keys($tabChanges), $tabChanges)).'.',
                            array_map(fn ($c) => $c['from'], $tabChanges),
                            array_map(fn ($c) => $c['to'], $tabChanges),
                            $user,
                            $request
                        );
                    }
                }
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
                
                    \App\Models\Finance\PayoutProfile::query();

                    /*
                     * Account/permission edits must not fabricate
                     * an empty payout profile.
                     */
                    $hasPayoutData =
                        collect($payoutData)
                            ->contains(
                                fn ($value) =>
                                    $value !== null
                                    && $value !== ''
                            );

                    if (
                        $existingPayout
                        || $hasPayoutData
                    ) {
                        /*
                         * country_code is NOT NULL.
                         * New payout profiles use the canonical
                         * India default when none was supplied.
                         */
                        if (
                            empty(
                                $payoutData[
                                    'country_code'
                                ] ?? null
                            )
                        ) {
                            $payoutData[
                                'country_code'
                            ] =
                                $existingPayout?->country_code
                                ?: 'IN';
                        }

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

            }
        );

        /* W79: a new label added on the edit form gets its own % */
        if ($validated['role'] === 'label') {
            $this->applyLabelRates($user, ['new_labels' => $validated['new_labels'] ?? [], 'new_label_rates' => $validated['new_label_rates'] ?? []], $request, $ratesBefore);
        }

        $user->refresh();

        $newValues = [
            'user' => $user->only([
                'name',
                'full_name',
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

        /* W59: a new password for someone else needs the e-mailed code */
        app(\App\Services\V2\ActionOtpGuard::class)->require(
            $request->user(),
            'user.password_reset:'.$user->id,
            'make a new password for '.$user->email
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

        /* W59: old "remember me" and open sessions stop working */
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        } catch (\Throwable $e) {
            report($e);
        }

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
            in_array(
                $user->role,
                ['admin', 'label', 'artist'],
                true
            ),
            422,
            'This user cannot receive an invitation.'
        );

        abort_unless(
            $user->invitation_status !== 'accepted',
            422,
            'This invitation has already been accepted.'
        );

        $plainToken = app(
            UserInvitationService::class
        )->issueLink($user);

        $link = url(
            '/invitation/'
            . urlencode($plainToken)
        );

        return back()->with(
            'invitation_link',
            $link
        );
    }


    /*
     * W59 MIXXTUNE_USER_SAFETY_V1
     * - you cannot suspend yourself; the last active super admin stays;
     * - making / removing a super admin, or changing someone's login
     *   e-mail, needs the e-mailed code (the e-mail is where login codes go).
     */
    private function guardSensitiveUserChange(Request $request, User $user, string $newRole, string $newStatus, string $newEmail): void
    {
        $actor = $request->user();

        if ($user->id === $actor->id && $newStatus !== 'active') {
            throw \Illuminate\Validation\ValidationException::withMessages(['account_status' => 'You cannot suspend your own account.']);
        }

        $wasSuper = $user->role === 'super_admin';
        $staysActiveSuper = $newRole === 'super_admin' && $newStatus === 'active';

        if ($wasSuper && !$staysActiveSuper) {
            $others = User::query()->where('role', 'super_admin')->where('account_status', 'active')->where('id', '!=', $user->id)->count();
            if ($others === 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['role' => 'This is the only active super admin - the role / status cannot be removed.']);
            }
        }

        $guard = app(\App\Services\V2\ActionOtpGuard::class);

        if ($wasSuper !== ($newRole === 'super_admin') || ($wasSuper && $newStatus !== $user->account_status)) {
            $guard->require($actor, 'user.role:'.$user->id.':'.$newRole.':'.$newStatus, 'change the super admin role / status of '.$user->email);
        }

        if ($newEmail !== '' && strtolower(trim($newEmail)) !== strtolower((string) $user->email)) {
            $guard->require($actor, 'user.email:'.$user->id.':'.sha1(strtolower(trim($newEmail))), 'change the login e-mail of '.$user->email.' to '.trim($newEmail));
        }
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

    /**
     * W79: per-label revenue % from the account form.
     * label_rates[label_id] for chosen labels, new_label_rates[i] for the
     * i-th new label name. Only labels owned by this account are touched;
     * an empty value leaves the label as it is.
     */
    /** W79: label id => rate before this save, for the labels the form names (null = no % yet) */
    private function labelRatesBefore(array $validated): array
    {
        $before = [];
        foreach (array_keys($validated['label_rates'] ?? []) as $labelId) {
            $label = Label::query()->find((int) $labelId);
            if ($label) {
                $before[(int) $label->id] = $label->revenue_share_percentage;
            }
        }
        foreach ($validated['new_labels'] ?? [] as $name) {
            $name = trim((string) $name);
            $label = $name !== '' ? app(LabelIdentityService::class)->representative($name) : null;
            if ($label) {
                $before[(int) $label->id] = $label->revenue_share_percentage;
            }
        }

        return $before;
    }

    private function applyLabelRates(User $user, array $validated, Request $request, array $before = []): void
    {
        $targets = [];

        foreach (($validated['label_rates'] ?? []) as $labelId => $rate) {
            $hadRate = array_key_exists((int) $labelId, $before) && $before[(int) $labelId] !== null && $before[(int) $labelId] !== '';
            if ($rate !== null && $rate !== '' && ! $hadRate) {
                $targets[] = [Label::query()->whereKey((int) $labelId)->where('user_id', $user->id)->first(), (float) $rate];
            }
        }

        foreach (array_values($validated['new_labels'] ?? []) as $index => $name) {
            $rate = array_values($validated['new_label_rates'] ?? [])[$index] ?? null;
            $name = trim((string) $name);
            if ($name === '' || $rate === null || $rate === '') {
                continue;
            }
            $label = Label::query()
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first()
                ?? app(LabelIdentityService::class)->representative($name);
            $targets[] = [$label && (int) $label->user_id === (int) $user->id ? $label : null, (float) $rate];
        }

        foreach ($targets as [$label, $rate]) {
            if (! $label || ($label->revenue_share_percentage !== null && (float) $label->revenue_share_percentage === $rate)) {
                continue;
            }
            /*
             * A label created in this same save, or one that had no % yet, gets
             * its first % without the e-mailed code (same rule as the observer).
             * A label that already had a % keeps the normal code check.
             */
            $firstRate = ! array_key_exists((int) $label->id, $before) || $before[(int) $label->id] === null || $before[(int) $label->id] === '';
            if ($firstRate) {
                app()->instance('mixxtune.label_rate_confirmed', true);
            }
            try {
                $label->forceFill([
                    'revenue_share_percentage' => $rate,
                    'updated_by' => $request->user()->id,
                ])->save();
            } finally {
                if ($firstRate) {
                    app()->forgetInstance('mixxtune.label_rate_confirmed');
                }
            }

            /* same as the edit form: earnings already imported follow the new rate */
            app(ReportEarningsRecalculationService::class)->recalculateForLabel($label);
        }
    }

    /** W80: lock state of the owner's KYC + the history of openings (newest first) */
    private function kycLockPayload(User $user): array
    {
        $lock = app(\App\Services\V2\KycEditLock::class);
        $state = $lock->state($user);
        $history = \Illuminate\Support\Facades\Schema::hasTable('kyc_edit_unlocks')
            ? \App\Models\Finance\KycEditUnlock::query()->with('unlocker:id,name')->where('user_id', $user->id)->latest('id')->limit(10)->get()
            : collect();
        $fmt = fn ($d) => $d ? $d->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A') : null;

        return [
            'can_owner_edit' => $state['can_edit'],
            'state' => $state['state'],
            'unlock_days' => \App\Services\V2\KycEditLock::UNLOCK_DAYS,
            'history' => $history->map(fn ($u) => [
                'id' => $u->id,
                'reason' => $u->reason,
                'by' => $u->unlocker?->name,
                'at' => $fmt($u->created_at),
                'expires' => $fmt($u->expires_at),
                'used' => $fmt($u->used_at),
                'revoked' => $fmt($u->revoked_at),
                'active' => $u->isActive(),
                'document_name' => $u->document_name,
            ])->values()->all(),
        ];
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
