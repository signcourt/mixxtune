<?php

namespace App\Http\Controllers\V2\Label;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\V2\UserInvitationService;
use App\Services\V2\UsernameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ArtistManagementController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $label = $this->currentLabel(
            $request
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

        $query = DB::table('artists')
            ->leftJoin(
                'users',
                'users.id',
                '=',
                'artists.user_id'
            )
            ->where(
                'artists.label_id',
                $label->id
            )
            ->whereNull(
                'artists.deleted_at'
            )
            ->select([
                'artists.id',
                'artists.public_id',
                'artists.user_id',
                'artists.stage_name',
                'artists.legal_name',
                'artists.email',
                'artists.phone',
                'artists.country',
                'artists.account_status',
                'artists.kyc_status',
                'artists.can_create_releases',
                'artists.created_at',
                'users.username',
                'users.invitation_status',
                'users.last_login_at',
            ]);

        if ($search !== '') {
            $term = '%'.$search.'%';

            $query->where(
                function ($builder) use (
                    $term
                ) {
                    $builder
                        ->where(
                            'artists.stage_name',
                            'like',
                            $term
                        )
                        ->orWhere(
                            'artists.legal_name',
                            'like',
                            $term
                        )
                        ->orWhere(
                            'artists.email',
                            'like',
                            $term
                        )
                        ->orWhere(
                            'users.username',
                            'like',
                            $term
                        );
                }
            );
        }

        if (
            in_array(
                $status,
                [
                    'active',
                    'pending',
                    'suspended',
                ],
                true
            )
        ) {
            $query->where(
                'artists.account_status',
                $status
            );
        }

        $summaryQuery = DB::table(
            'artists'
        )
            ->where(
                'label_id',
                $label->id
            )
            ->whereNull('deleted_at');

        return Inertia::render(
            'V2/Label/Artists/Index',
            [
                'role' => 'label',

                'label' => [
                    'id' => $label->id,
                    'name' => $label->name,
                    'public_id' =>
                        $label->public_id,
                ],

                'filters' => [
                    'search' => $search,
                    'status' => $status,
                ],

                'summary' => [
                    'total' =>
                        (clone $summaryQuery)
                            ->count(),

                    'active' =>
                        (clone $summaryQuery)
                            ->where(
                                'account_status',
                                'active'
                            )
                            ->count(),

                    'pending' =>
                        (clone $summaryQuery)
                            ->where(
                                'account_status',
                                'pending'
                            )
                            ->count(),

                    'suspended' =>
                        (clone $summaryQuery)
                            ->where(
                                'account_status',
                                'suspended'
                            )
                            ->count(),
                ],

                'artists' =>
                    $query
                        ->orderByDesc(
                            'artists.id'
                        )
                        ->paginate(25)
                        ->withQueryString(),
            ]
        );
    }

    public function create(
        Request $request
    ): Response {
        $label = $this->currentLabel(
            $request
        );

        return Inertia::render(
            'V2/Label/Artists/Create',
            [
                'role' => 'label',

                'label' => [
                    'id' => $label->id,
                    'name' => $label->name,
                    'public_id' =>
                        $label->public_id,
                ],
            ]
        );
    }

    public function store(
        Request $request,
        UsernameService $usernameService,
        UserInvitationService $invitationService
    ): RedirectResponse {
        $label = $this->currentLabel(
            $request
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
                $request,
                $label,
                $username,
                $email,
                $sendInvitation
            ) {
                $user = User::query()->create([
                    'name' =>
                        $validated[
                            'stage_name'
                        ],

                    'username' =>
                        $username,

                    'email' =>
                        $email,

                    'phone' =>
                        $validated['phone']
                        ?? null,

                    'country' =>
                        $validated['country'],

                    'role' =>
                        'artist',

                    'account_status' =>
                        $validated[
                            'account_status'
                        ],

                    'kyc_status' =>
                        'pending',

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

                $slugBase = Str::slug(
                    $validated[
                        'stage_name'
                    ]
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
                    'public_id' =>
                        $publicId,

                    'user_id' =>
                        $user->id,

                    'label_id' =>
                        $label->id,

                    'stage_name' =>
                        $validated[
                            'stage_name'
                        ],

                    'legal_name' =>
                        $validated[
                            'legal_name'
                        ] ?? null,

                    'slug' =>
                        $slug,

                    'email' =>
                        $email,

                    'phone' =>
                        $validated['phone']
                        ?? null,

                    'country' =>
                        $validated['country'],

                    'timezone' =>
                        $validated[
                            'timezone'
                        ],

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

                    'kyc_status' =>
                        'pending',

                    'can_receive_splits' =>
                        (bool) $validated[
                            'can_receive_splits'
                        ],

                    'can_create_releases' =>
                        (bool) $validated[
                            'can_create_releases'
                        ],

                    'created_by' =>
                        $request->user()->id,

                    'updated_by' =>
                        $request->user()->id,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

                return [
                    'user' => $user,
                    'artist_id' =>
                        $artistId,
                    'public_id' =>
                        $publicId,
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
                        'v2.label.artists.index'
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
                'v2.label.artists.index'
            )
            ->with(
                'success',
                'Artist account created successfully.'
            );
    }

    public function show(
        Request $request,
        int $artist
    ): Response {
        $label = $this->currentLabel(
            $request
        );

        $managedArtist = $this->ownedArtist(
            $label->id,
            $artist
        );

        $user = User::query()->findOrFail(
            $managedArtist->user_id
        );

        $releaseBase = DB::table('releases')
            ->where(
                'artist_id',
                $managedArtist->id
            )
            ->whereNull('deleted_at');

        $catalogueCount = DB::table(
            'catalogue_items'
        )
            ->where(
                'artist_id',
                $managedArtist->id
            )
            ->count();

        $trackCount = DB::table('tracks')
            ->join(
                'releases',
                'releases.id',
                '=',
                'tracks.release_id'
            )
            ->where(
                'releases.artist_id',
                $managedArtist->id
            )
            ->whereNull(
                'releases.deleted_at'
            )
            ->whereNull(
                'tracks.deleted_at'
            )
            ->count();

        $wallet = DB::table(
            'wallet_accounts'
        )
            ->where(
                'user_id',
                $user->id
            )
            ->first();

        $walletTransactionCount =
            DB::table('wallet_transactions')
                ->where(
                    'artist_id',
                    $managedArtist->id
                )
                ->count();

        $withdrawalCount =
            DB::table('withdrawal_requests')
                ->where(
                    'user_id',
                    $user->id
                )
                ->count();

        $recentReleases = DB::table(
            'releases'
        )
            ->where(
                'artist_id',
                $managedArtist->id
            )
            ->whereNull('deleted_at')
            ->select([
                'id',
                'public_id',
                'title',
                'release_type',
                'status',
                'upc',
                'digital_release_date',
                'created_at',
            ])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return Inertia::render(
            'V2/Label/Artists/Show',
            [
                'role' => 'label',

                'label' => [
                    'id' => $label->id,
                    'name' => $label->name,
                    'public_id' =>
                        $label->public_id,
                ],

                'artist' => [
                    'id' =>
                        $managedArtist->id,

                    'public_id' =>
                        $managedArtist->public_id,

                    'stage_name' =>
                        $managedArtist->stage_name,

                    'legal_name' =>
                        $managedArtist->legal_name,

                    'username' =>
                        $user->username,

                    'email' =>
                        $managedArtist->email,

                    'phone' =>
                        $managedArtist->phone,

                    'country' =>
                        $managedArtist->country,

                    'timezone' =>
                        $managedArtist->timezone,

                    'currency' =>
                        $managedArtist->currency,

                    'bio' =>
                        $managedArtist->bio,

                    'profile_image_path' =>
                        $managedArtist
                            ->profile_image_path,

                    'account_status' =>
                        $managedArtist
                            ->account_status,

                    'kyc_status' =>
                        $managedArtist
                            ->kyc_status,

                    'can_create_releases' =>
                        (bool) $managedArtist
                            ->can_create_releases,

                    'can_receive_splits' =>
                        (bool) $managedArtist
                            ->can_receive_splits,

                    'invitation_status' =>
                        $user->invitation_status,

                    'invitation_sent_at' =>
                        $user->invitation_sent_at,

                    'invitation_expires_at' =>
                        $user->invitation_expires_at,

                    'last_login_at' =>
                        $user->last_login_at,

                    'created_at' =>
                        $managedArtist->created_at,
                ],

                'releaseSummary' => [
                    'total' =>
                        (clone $releaseBase)
                            ->count(),

                    'draft' =>
                        (clone $releaseBase)
                            ->where(
                                'status',
                                'draft'
                            )
                            ->count(),

                    'submitted' =>
                        (clone $releaseBase)
                            ->where(
                                'status',
                                'submitted'
                            )
                            ->count(),

                    'approved' =>
                        (clone $releaseBase)
                            ->where(
                                'status',
                                'approved'
                            )
                            ->count(),

                    'live' =>
                        (clone $releaseBase)
                            ->where(
                                'status',
                                'live'
                            )
                            ->count(),

                    'rejected' =>
                        (clone $releaseBase)
                            ->where(
                                'status',
                                'rejected'
                            )
                            ->count(),
                ],

                'catalogueSummary' => [
                    'items' =>
                        $catalogueCount,

                    'tracks' =>
                        $trackCount,
                ],

                'walletSummary' => [
                    'currency' =>
                        $wallet->currency
                            ?? $managedArtist->currency
                            ?? 'INR',

                    'pending_balance' =>
                        (float) (
                            $wallet->pending_balance
                            ?? 0
                        ),

                    'available_balance' =>
                        (float) (
                            $wallet->available_balance
                            ?? 0
                        ),

                    'withdrawn_balance' =>
                        (float) (
                            $wallet->withdrawn_balance
                            ?? 0
                        ),

                    'lifetime_earnings' =>
                        (float) (
                            $wallet->lifetime_earnings
                            ?? 0
                        ),

                    'hold_balance' =>
                        (float) (
                            $wallet->hold_balance
                            ?? 0
                        ),

                    'transactions' =>
                        $walletTransactionCount,

                    'withdrawals' =>
                        $withdrawalCount,
                ],

                'recentReleases' =>
                    $recentReleases,
            ]
        );
    }

    public function edit(
        Request $request,
        int $artist
    ): Response {
        $label = $this->currentLabel(
            $request
        );

        $managedArtist = $this->ownedArtist(
            $label->id,
            $artist
        );

        $user = User::query()->findOrFail(
            $managedArtist->user_id
        );

        return Inertia::render(
            'V2/Label/Artists/Edit',
            [
                'role' => 'label',

                'label' => [
                    'id' => $label->id,
                    'name' => $label->name,
                    'public_id' =>
                        $label->public_id,
                ],

                'artist' => [
                    'id' =>
                        $managedArtist->id,

                    'public_id' =>
                        $managedArtist->public_id,

                    'stage_name' =>
                        $managedArtist->stage_name,

                    'legal_name' =>
                        $managedArtist->legal_name,

                    'username' =>
                        $user->username,

                    'email' =>
                        $managedArtist->email,

                    'phone' =>
                        $managedArtist->phone,

                    'country' =>
                        $managedArtist->country,

                    'timezone' =>
                        $managedArtist->timezone,

                    'currency' =>
                        $managedArtist->currency,

                    'account_status' =>
                        $managedArtist->account_status,

                    'kyc_status' =>
                        $managedArtist->kyc_status,

                    'can_create_releases' =>
                        (bool) $managedArtist
                            ->can_create_releases,

                    'can_receive_splits' =>
                        (bool) $managedArtist
                            ->can_receive_splits,

                    'invitation_status' =>
                        $user->invitation_status,

                    'last_login_at' =>
                        $user->last_login_at,
                ],
            ]
        );
    }

    public function update(
        Request $request,
        int $artist,
        UsernameService $usernameService
    ): RedirectResponse {
        $label = $this->currentLabel(
            $request
        );

        $managedArtist = $this->ownedArtist(
            $label->id,
            $artist
        );

        $user = User::query()->findOrFail(
            $managedArtist->user_id
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
                'required',
                'string',
                'max:40',
            ],

            'email' => [
                'required',
                'email',
                'max:190',
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
                'in:active,pending,suspended',
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

        $email = Str::lower(
            trim($validated['email'])
        );

        $emailExists = User::query()
            ->where('email', $email)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($emailExists) {
            throw ValidationException::withMessages([
                'email' =>
                    'This email is already in use.',
            ]);
        }

        $artistEmailExists = DB::table('artists')
            ->where('email', $email)
            ->where('id', '!=', $managedArtist->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($artistEmailExists) {
            throw ValidationException::withMessages([
                'email' =>
                    'This email is already linked to another artist.',
            ]);
        }

        $requestedUsername = trim(
            $validated['username']
        );

        $username =
            $usernameService->normalize(
                $requestedUsername
            );

        if (
            Str::lower($requestedUsername)
            !== $username
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
                $username,
                $user->id
            )
        ) {
            throw ValidationException::withMessages([
                'username' =>
                    'This username is already in use.',
            ]);
        }

        DB::transaction(
            function () use (
                $validated,
                $request,
                $managedArtist,
                $user,
                $username,
                $email
            ) {
                $user->forceFill([
                    'name' =>
                        $validated['stage_name'],

                    'username' =>
                        $username,

                    'email' =>
                        $email,

                    'phone' =>
                        $validated['phone']
                        ?? null,

                    'country' =>
                        $validated['country'],

                    'account_status' =>
                        $validated[
                            'account_status'
                        ],
                ])->save();

                DB::table('artists')
                    ->where(
                        'id',
                        $managedArtist->id
                    )
                    ->update([
                        'stage_name' =>
                            $validated[
                                'stage_name'
                            ],

                        'legal_name' =>
                            $validated[
                                'legal_name'
                            ] ?? null,

                        'email' =>
                            $email,

                        'phone' =>
                            $validated['phone']
                            ?? null,

                        'country' =>
                            $validated[
                                'country'
                            ],

                        'timezone' =>
                            $validated[
                                'timezone'
                            ],

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

                        'can_create_releases' =>
                            (bool) $validated[
                                'can_create_releases'
                            ],

                        'can_receive_splits' =>
                            (bool) $validated[
                                'can_receive_splits'
                            ],

                        'updated_by' =>
                            $request->user()->id,

                        'updated_at' =>
                            now(),
                    ]);
            }
        );

        return redirect()
            ->route(
                'v2.label.artists.index'
            )
            ->with(
                'success',
                'Artist updated successfully.'
            );
    }

    public function toggleStatus(
        Request $request,
        int $artist
    ): RedirectResponse {
        $label = $this->currentLabel(
            $request
        );

        $managedArtist = $this->ownedArtist(
            $label->id,
            $artist
        );

        $user = User::query()->findOrFail(
            $managedArtist->user_id
        );

        $newStatus =
            $managedArtist->account_status
                === 'active'
                    ? 'suspended'
                    : 'active';

        DB::transaction(
            function () use (
                $request,
                $managedArtist,
                $user,
                $newStatus
            ) {
                $user->forceFill([
                    'account_status' =>
                        $newStatus,
                ])->save();

                DB::table('artists')
                    ->where(
                        'id',
                        $managedArtist->id
                    )
                    ->update([
                        'account_status' =>
                            $newStatus,

                        'updated_by' =>
                            $request->user()->id,

                        'updated_at' =>
                            now(),
                    ]);
            }
        );

        return back()->with(
            'success',
            "Artist status changed to {$newStatus}."
        );
    }

    public function resendInvitation(
        Request $request,
        int $artist,
        UserInvitationService $invitationService
    ): RedirectResponse {
        $label = $this->currentLabel(
            $request
        );

        $managedArtist = $this->ownedArtist(
            $label->id,
            $artist
        );

        $user = User::query()->findOrFail(
            $managedArtist->user_id
        );

        try {
            $invitationService->send(
                $user
            );
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'Invitation email failed: '
                .$exception->getMessage()
            );
        }

        return back()->with(
            'success',
            'Invitation email sent successfully.'
        );
    }

    public function resetPassword(
        Request $request,
        int $artist,
        UserInvitationService $invitationService
    ): RedirectResponse {
        $label = $this->currentLabel(
            $request
        );

        $managedArtist = $this->ownedArtist(
            $label->id,
            $artist
        );

        $user = User::query()->findOrFail(
            $managedArtist->user_id
        );

        $user->forceFill([
            'password' =>
                Hash::make(
                    Str::random(40)
                ),

            'password_set_at' =>
                null,

            'invitation_status' =>
                'pending',

            'invitation_error' =>
                null,
        ])->save();

        try {
            $invitationService->send(
                $user
            );
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'Password reset email failed: '
                .$exception->getMessage()
            );
        }

        return back()->with(
            'success',
            'Secure password setup email sent.'
        );
    }

    private function ownedArtist(
        int $labelId,
        int $artistId
    ): object {
        $artist = DB::table('artists')
            ->where(
                'id',
                $artistId
            )
            ->where(
                'label_id',
                $labelId
            )
            ->whereNull('deleted_at')
            ->first();

        abort_unless(
            $artist,
            404,
            'Artist was not found under this label.'
        );

        abort_unless(
            $artist->user_id,
            422,
            'Artist user account is not linked.'
        );

        return $artist;
    }

    private function currentLabel(
        Request $request
    ): object {
        $user = $request->user();

        abort_unless(
            $user,
            401,
            'Authentication required.'
        );

        abort_unless(
            $user->role === 'label',
            403,
            'Label access required.'
        );

        $label = DB::table('labels')
            ->where(
                'user_id',
                $user->id
            )
            ->whereNull('deleted_at')
            ->first();

        abort_unless(
            $label,
            404,
            'Label profile is not linked.'
        );

        abort_if(
            $label->status !== 'active',
            403,
            'Label account is not active.'
        );

        return $label;
    }
}
