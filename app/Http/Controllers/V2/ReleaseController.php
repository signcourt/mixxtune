<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\DistributionStore;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseWorkflowService;
use App\Services\V2\ReleaseValidationService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReleaseController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $permissions->authorize(
            $request->user(),
            'releases.view'
        );

        $role = $permissions->role(
            $request->user()
        );

        $filters = [
            'search' => trim(
                (string) $request->input(
                    'search',
                    ''
                )
            ),

            'status' => trim(
                (string) $request->input(
                    'status',
                    ''
                )
            ),

            'sort' => trim(
                (string) $request->input(
                    'sort',
                    'latest'
                )
            ),
        ];

        $query = Release::query()
            ->whereNull('deleted_at');

        if ($role === 'artist') {
            $artist = Artist::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->first();

            $artist
                ? $query->where(
                    'artist_id',
                    $artist->id
                )
                : $query->whereRaw('1 = 0');
        }

        if ($role === 'label') {
            $label = Label::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->first();

            $label
                ? $query->where(
                    'label_id',
                    $label->id
                )
                : $query->whereRaw('1 = 0');
        }

        if (
            $role === 'admin'
            && Schema::hasColumn(
                'artists',
                'assigned_admin_id'
            )
        ) {
            $assignedArtistIds =
                Artist::query()
                    ->where(
                        'assigned_admin_id',
                        $request->user()->id
                    )
                    ->pluck('id');

            $query->whereIn(
                'artist_id',
                $assignedArtistIds
            );
        }

        $scopedQuery = clone $query;

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where(
                        'title',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'primary_artist_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'upc',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        if ($filters['status'] !== '') {
            $query->where(
                'status',
                $filters['status']
            );
        }

        match ($filters['sort']) {
            'oldest' =>
                $query->orderBy('id'),

            'title_asc' =>
                $query->orderBy('title'),

            'title_desc' =>
                $query->orderByDesc('title'),

            default =>
                $query->orderByDesc('id'),
        };

        return Inertia::render(
            'V2/Releases/Index',
            [
                'role' => $role,

                'permissions' =>
                    $permissions->permissions(
                        $request->user()
                    ),

                'filters' => $filters,

                'releases' =>
                    $query
                        ->paginate(20)
                        ->withQueryString(),

                'statusCounts' => [
                    'total' =>
                        (clone $scopedQuery)
                            ->count(),

                    'draft' =>
                        (clone $scopedQuery)
                            ->where(
                                'status',
                                'draft'
                            )
                            ->count(),

                    'submitted' =>
                        (clone $scopedQuery)
                            ->where(
                                'status',
                                'submitted'
                            )
                            ->count(),

                    'approved' =>
                        (clone $scopedQuery)
                            ->where(
                                'status',
                                'approved'
                            )
                            ->count(),

                    'rejected' =>
                        (clone $scopedQuery)
                            ->where(
                                'status',
                                'rejected'
                            )
                            ->count(),
                ],
            ]
        );
    }

    public function create(
        Request $request,
        PermissionService $permissions
    ): Response {
        $permissions->authorize(
            $request->user(),
            'releases.create'
        );

        $context = $this->formContext(
            $request,
            $permissions
        );

        return Inertia::render(
            'V2/Releases/Create',
            [
                ...$context,
                'release' => null,
                'title' => 'Create Release',
            ]
        );
    }

    public function store(
        Request $request,
        PermissionService $permissions
    ): RedirectResponse {
        $permissions->authorize(
            $request->user(),
            'releases.create'
        );

        $validated = $this->validateDraft($request);

        [$artist, $label] = $this->resolveOwnership(
            $request,
            $permissions,
            $validated
        );

        $artworkPath = null;

        if ($request->hasFile('artwork')) {
            $artworkPath = $request
                ->file('artwork')
                ->store(
                    'releases/artwork',
                    'public'
                );
        }

        $catalogNumber =
            $this->generateCatalogNumber();

        $release = DB::transaction(function () use (
            $request,
            $validated,
            $artist,
            $label,
            $artworkPath,
            $catalogNumber
        ) {
            return Release::query()->create([
                'public_id' => (string) Str::ulid(),
                'catalog_number' =>
                    $catalogNumber,

                'artist_id' => $artist->id,
                'label_id' => $label?->id,

                'release_type' =>
                    $validated['release_type'],

                'title' => $validated['title'],

                'primary_artist_name' =>
                    $artist->stage_name
                    ?: $artist->legal_name,

                'featuring_artist_name' =>
                    $validated[
                        'featuring_artist_name'
                    ] ?? null,

                'language' =>
                    $validated['language'] ?? null,

                'primary_genre' =>
                    $validated[
                        'primary_genre'
                    ] ?? null,

                'sub_genre' =>
                    $validated['sub_genre'] ?? null,

                'upc' =>
                    $validated['upc'] ?? null,

                'original_release_date' =>
                    $validated[
                        'original_release_date'
                    ] ?? null,

                'digital_release_date' =>
                    $validated[
                        'digital_release_date'
                    ] ?? null,

                'copyright_owner' =>
                    $validated[
                        'copyright_owner'
                    ] ?? null,

                'copyright_year' =>
                    $validated[
                        'copyright_year'
                    ] ?? null,

                'phonographic_owner' =>
                    $validated[
                        'phonographic_owner'
                    ] ?? null,

                'phonographic_year' =>
                    $validated[
                        'phonographic_year'
                    ] ?? null,

                'artwork_path' => $artworkPath,

                'status' => 'draft',
                'wizard_step' => 1,
                'completion_percentage' => 20,

                'created_by' =>
                    $request->user()->id,

                'updated_by' =>
                    $request->user()->id,
            ]);
        });

        return redirect()
            ->route(
                'v2.releases.edit',
                $release
            )
            ->with(
                'success',
                "Draft '{$release->title}' created."
            );
    }

    public function show(
        Request $request,
        Release $release,
        PermissionService $permissions
    ) {
        $role = $permissions->role(
            $request->user()
        );

        $release->loadMissing([
            'artist',
            'label',
            'tracks',
        ]);

        if ($role === 'artist') {
            abort_unless(
                $release->artist
                && (int) $release->artist->user_id
                    === (int) $request->user()->id,
                403,
                'You cannot view this release.'
            );
        } elseif ($role === 'label') {
            abort_unless(
                $release->label
                && (int) $release->label->user_id
                    === (int) $request->user()->id,
                403,
                'You cannot view this release.'
            );
        } else {
            abort_unless(
                in_array(
                    $role,
                    ['admin', 'super_admin'],
                    true
                ),
                403,
                'You cannot view this release.'
            );
        }

        $editable = in_array(
            (string) $release->status,
            [
                'draft',
                'changes_requested',
                'rejected',
            ],
            true
        );

        return Inertia::render(
            'V2/Releases/Show',
            [
                'role' => $role,

                'release' => [
                    'id' => $release->id,
                    'public_id' =>
                        $release->public_id,
                    'title' =>
                        $release->title,
                    'release_type' =>
                        $release->release_type,
                    'status' =>
                        $release->status,
                    'primary_artist_name' =>
                        $release->primary_artist_name,
                    'featuring_artist_name' =>
                        $release->featuring_artist_name,
                    'artist_name' =>
                        $release->artist?->stage_name,
                    'label_name' =>
                        $release->label?->name,
                    'catalog_number' =>
                        $release->catalog_number,
                    'upc' =>
                        $release->upc,
                    'language' =>
                        $release->language,
                    'primary_genre' =>
                        $release->primary_genre,
                    'sub_genre' =>
                        $release->sub_genre,
                    'digital_release_date' =>
                        optional(
                            $release->digital_release_date
                        )->format('Y-m-d')
                        ?? $release->digital_release_date,
                    'original_release_date' =>
                        optional(
                            $release->original_release_date
                        )->format('Y-m-d')
                        ?? $release->original_release_date,
                    'copyright_owner' =>
                        $release->copyright_owner,
                    'copyright_year' =>
                        $release->copyright_year,
                    'phonographic_owner' =>
                        $release->phonographic_owner,
                    'phonographic_year' =>
                        $release->phonographic_year,
                    'artwork_path' =>
                        $release->artwork_path,
                    'worldwide' =>
                        (bool) $release->worldwide,
                    'stores' =>
                        $release->stores,
                    'territories' =>
                        $release->territories,
                    'completion_percentage' =>
                        $release->completion_percentage,
                    'submitted_at' =>
                        optional(
                            $release->submitted_at
                        )->toDateTimeString(),
                    'approved_at' =>
                        optional(
                            $release->approved_at
                        )->toDateTimeString(),
                    'delivered_at' =>
                        optional(
                            $release->delivered_at
                        )->toDateTimeString(),
                    'live_at' =>
                        optional(
                            $release->live_at
                        )->toDateTimeString(),
                    'review_notes' =>
                        $release->review_notes,
                    'rejection_reason' =>
                        $release->rejection_reason,
                    'editable' =>
                        $editable,

                    'tracks' =>
                        $release->tracks
                            ->sortBy([
                                ['disc_number', 'asc'],
                                ['track_number', 'asc'],
                            ])
                            ->values()
                            ->map(
                                fn ($track) => [
                                    'id' =>
                                        $track->id,
                                    'title' =>
                                        $track->title,
                                    'version' =>
                                        $track->version,
                                    'disc_number' =>
                                        $track->disc_number,
                                    'track_number' =>
                                        $track->track_number,
                                    'primary_artist_name' =>
                                        $track->primary_artist_name,
                                    'featuring_artist_name' =>
                                        $track->featuring_artist_name,
                                    'isrc' =>
                                        $track->isrc,
                                    'language' =>
                                        $track->language,
                                    'genre' =>
                                        $track->genre,
                                    'is_explicit' =>
                                        (bool) $track->is_explicit,
                                    'is_instrumental' =>
                                        (bool) $track->is_instrumental,
                                    'audio_path' =>
                                        $track->audio_path,
                                    'audio_original_name' =>
                                        $track->audio_original_name,
                                    'audio_size_bytes' =>
                                        $track->audio_size_bytes,
                                    'audio_validation_status' =>
                                        $track->audio_validation_status,
                                    'audio_validation_errors' =>
                                        $track->audio_validation_errors,
                                    'status' =>
                                        $track->status,
                                ]
                            )
                            ->all(),
                ],
            ]
        );
    }

    public function edit(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): Response {
        $access->authorizeUpdate(
            $request->user(),
            $release
        );

        $release->load([
            'tracks' => function ($query) {
                $query
                    ->orderBy('disc_number')
                    ->orderBy('track_number')
                    ->orderBy('id');
            },
        ]);

        $context = $this->formContext(
            $request,
            $permissions
        );

        return Inertia::render(
            'V2/Releases/Create',
            [
                ...$context,
                'release' => $release,
                'title' => 'Edit Release',
            ]
        );
    }

    public function update(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): RedirectResponse {
        $access->authorizeUpdate(
            $request->user(),
            $release
        );

        $validated = $this->validateDraft($request);

        [$artist, $label] = $this->resolveOwnership(
            $request,
            $permissions,
            $validated,
            $release
        );

        if ($request->hasFile('artwork')) {
            if ($release->artwork_path) {
                Storage::disk('public')->delete(
                    $release->artwork_path
                );
            }

            $validated['artwork_path'] = $request
                ->file('artwork')
                ->store(
                    'releases/artwork',
                    'public'
                );
        }

        unset($validated['artwork']);

        $release->update([
            'catalog_number' =>
                $validated['catalog_number'],

            'artist_id' => $artist->id,
            'label_id' => $label?->id,

            'release_type' =>
                $validated['release_type'],

            'title' => $validated['title'],

            'version' =>
                $validated['version'] ?? null,

            'primary_artist_name' =>
                $artist->stage_name
                ?: $artist->legal_name,

            'featuring_artist_name' =>
                collect(
                    $validated[
                        'featuring_artists'
                    ] ?? []
                )
                    ->pluck('name')
                    ->filter()
                    ->implode(', ')
                ?: (
                    $validated[
                        'featuring_artist_name'
                    ] ?? null
                ),

            'primary_artists' =>
                array_values(
                    $validated[
                        'primary_artists'
                    ] ?? []
                ),

            'featuring_artists' =>
                array_values(
                    $validated[
                        'featuring_artists'
                    ] ?? []
                ),

            'language' =>
                $validated['language'] ?? null,

            'primary_genre' =>
                $validated['primary_genre'] ?? null,

            'sub_genre' =>
                $validated['sub_genre'] ?? null,

            'upc' =>
                $validated['upc'] ?? null,

            'original_release_date' =>
                $validated[
                    'original_release_date'
                ] ?? null,

            'digital_release_date' =>
                $validated[
                    'digital_release_date'
                ] ?? null,

            'copyright_owner' =>
                $validated[
                    'copyright_owner'
                ] ?? null,

            'copyright_year' =>
                $validated[
                    'copyright_year'
                ] ?? null,

            'phonographic_owner' =>
                $validated[
                    'phonographic_owner'
                ] ?? null,

            'phonographic_year' =>
                $validated[
                    'phonographic_year'
                ] ?? null,

            'artwork_path' =>
                $validated['artwork_path']
                ?? $release->artwork_path,

            'status' =>
                $release->status === 'rejected'
                    ? 'draft'
                    : $release->status,

            'wizard_step' => max(
                1,
                min(
                    4,
                    (int) (
                        $validated['wizard_step']
                        ?? $release->wizard_step
                        ?? 1
                    )
                )
            ),

            'completion_percentage' => max(
                20,
                min(
                    100,
                    (int) (
                        $validated[
                            'completion_percentage'
                        ]
                        ?? $release
                            ->completion_percentage
                        ?? 20
                    )
                )
            ),

            'rejection_reason' => null,
            'rejected_at' => null,
            'rejected_by' => null,

            'updated_by' =>
                $request->user()->id,
        ]);

        return redirect()
            ->route(
                'v2.releases.edit',
                $release
            )
            ->with(
                'success',
                'Draft updated successfully.'
            );
    }


    public function saveDistribution(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): RedirectResponse {
        $access->authorizeUpdate(
            $request->user(),
            $release
        );

        $validated = $request->validate([
            'stores' => [
                'required',
                'array',
                'min:1',
            ],

            'stores.*' => [
                'required',
                'integer',
                'distinct',
                'exists:distribution_stores,id',
            ],

            'worldwide' => [
                'required',
                'boolean',
            ],

            'territories' => [
                'nullable',
                'array',
            ],

            'territories.*' => [
                'string',
                'size:2',
            ],

            'release_timezone' => [
                'required',
                'string',
                'max:100',
            ],

            'pre_order' => [
                'required',
                'boolean',
            ],
        ]);

        if (
            !$validated['worldwide']
            && empty(
                $validated['territories']
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'territories' => [
                    'Select at least one territory or enable Worldwide.',
                ],
            ]);
        }

        $activeStoreIds =
            DistributionStore::query()
                ->where('is_active', true)
                ->whereIn(
                    'id',
                    $validated['stores']
                )
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                )
                ->values()
                ->all();

        if (
            count($activeStoreIds)
            !== count(
                $validated['stores']
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'stores' => [
                    'One or more selected stores are inactive or invalid.',
                ],
            ]);
        }

        $release->update([
            'stores' =>
                $activeStoreIds,

            'worldwide' =>
                (bool) $validated[
                    'worldwide'
                ],

            'territories' =>
                $validated['worldwide']
                    ? []
                    : array_values(
                        array_unique(
                            array_map(
                                'strtoupper',
                                $validated[
                                    'territories'
                                ] ?? []
                            )
                        )
                    ),

            'release_timezone' =>
                $validated[
                    'release_timezone'
                ],

            'pre_order' =>
                (bool) $validated[
                    'pre_order'
                ],

            'wizard_step' => max(
                4,
                (int) $release
                    ->wizard_step
            ),

            'completion_percentage' =>
                max(
                    90,
                    (int) $release
                        ->completion_percentage
                ),

            'updated_by' =>
                $request->user()->id,
        ]);

        return back()->with(
            'success',
            'Stores and distribution settings saved successfully.'
        );
    }


    public function submissionChecklist(
        Request $request,
        Release $release,
        ReleaseAccessService $access,
        ReleaseValidationService $validator
    ) {
        $access->authorizeView(
            $request->user(),
            $release
        );

        return response()->json(
            $validator->checklist($release)
        );
    }

    public function submitForReview(
        Request $request,
        Release $release,
        ReleaseAccessService $access,
        ReleaseWorkflowService $workflow
    ): RedirectResponse {
        $access->authorizeSubmit(
            $request->user(),
            $release
        );

        /*
         * Artist releases में stores खाली रहने पर
         * सभी active distribution stores automatically
         * select और persist करें.
         */
        if (
            empty($release->stores) ||
            !is_array($release->stores)
        ) {
            $defaultStoreIds =
                DistributionStore::query()
                    ->where(
                        'is_active',
                        true
                    )
                    ->orderBy(
                        'sort_order'
                    )
                    ->pluck('id')
                    ->map(
                        fn ($id) =>
                            (int) $id
                    )
                    ->values()
                    ->all();

            $release->update([
                'stores' =>
                    $defaultStoreIds,

                'worldwide' =>
                    $release->worldwide
                        ?? true,

                'release_timezone' =>
                    $release
                        ->release_timezone
                        ?: 'Asia/Kolkata',

                'updated_by' =>
                    $request->user()->id,
            ]);

            $release->refresh();
        }

        $release = $workflow->submit(
            $release,
            $request->user(),
            [
                'action' =>
                    'submitted_for_review',

                'remarks' =>
                    'Release submitted for admin review through V2.',

                'ip_address' =>
                    $request->ip(),

                'user_agent' =>
                    $request->userAgent(),
            ]
        );

        return redirect()
            ->route('v2.releases.index')
            ->with(
                'success',
                "Release '{$release->title}' submitted for review."
            );
    }

    private function validateDraft(
        Request $request
    ): array {
        return $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'release_type' => [
                'required',
                'in:single,ep,album',
            ],

            'version' => [
                'nullable',
                'string',
                'max:255',
            ],

            'artist_id' => [
                'nullable',
                'integer',
                'exists:artists,id',
            ],

            'label_id' => [
                'nullable',
                'integer',
                'exists:labels,id',
            ],

            'featuring_artist_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'primary_artists' => [
                'required',
                'array',
                'min:1',
            ],

            'primary_artists.*.name' => [
                'required',
                'string',
                'max:255',
            ],

            'primary_artists.*.spotify_url' => [
                'nullable',
                'url',
                'max:500',
            ],

            'primary_artists.*.apple_music_url' => [
                'nullable',
                'url',
                'max:500',
            ],

            'primary_artists.*.youtube_topic_url' => [
                'nullable',
                'url',
                'max:500',
            ],

            'featuring_artists' => [
                'nullable',
                'array',
            ],

            'featuring_artists.*.name' => [
                'required',
                'string',
                'max:255',
            ],

            'catalog_number' => [
                'required',
                'string',
                'max:100',
            ],

            'upc' => [
                'nullable',
                'string',
                'max:20',
            ],

            'language' => [
                'nullable',
                'string',
                'max:100',
            ],

            'primary_genre' => [
                'nullable',
                'string',
                'max:100',
            ],

            'sub_genre' => [
                'nullable',
                'string',
                'max:100',
            ],

            'original_release_date' => [
                'nullable',
                'date',
            ],

            'digital_release_date' => [
                'required',
                'date',
                'after:today',
            ],

            'copyright_owner' => [
                'nullable',
                'string',
                'max:255',
            ],

            'copyright_year' => [
                'nullable',
                'integer',
                'min:1900',
                'max:2100',
            ],

            'phonographic_owner' => [
                'nullable',
                'string',
                'max:255',
            ],

            'phonographic_year' => [
                'nullable',
                'integer',
                'min:1900',
                'max:2100',
            ],

            'artwork' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png',
                'max:20480',
            ],

            'wizard_step' => [
                'nullable',
                'integer',
                'min:1',
                'max:5',
            ],

            'completion_percentage' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
        ]);
    }

    private function resolveOwnership(
        Request $request,
        PermissionService $permissions,
        array $validated,
        ?Release $release = null
    ): array {
        $role = $permissions->role(
            $request->user()
        );

        if ($role === 'artist') {
            $artist = Artist::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->firstOrFail();

            $label = $artist->label_id
                ? Label::query()->find(
                    $artist->label_id
                )
                : null;

            return [$artist, $label];
        }

        if ($role === 'label') {
            $label = Label::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->firstOrFail();

            $artistId =
                $validated['artist_id']
                ?? $release?->artist_id;

            /*
             * A label with exactly one available artist should not fail
             * merely because the frontend omitted artist_id.
             */
            if (! $artistId) {
                $labelArtistIds = Artist::query()
                    ->where('label_id', $label->id)
                    ->whereNull('deleted_at')
                    ->where('account_status', 'active')
                    ->limit(2)
                    ->pluck('id');

                if ($labelArtistIds->count() === 1) {
                    $artistId = $labelArtistIds->first();
                }
            }

            abort_unless(
                $artistId,
                422,
                'Select an artist.'
            );

            $artist = Artist::query()
                ->where('id', $artistId)
                ->where(
                    'label_id',
                    $label->id
                )
                ->whereNull('deleted_at')
                ->firstOrFail();

            return [$artist, $label];
        }

        $artistId =
            $validated['artist_id']
            ?? $release?->artist_id;

        abort_unless(
            $artistId,
            422,
            'Select an artist.'
        );

        $artist = Artist::query()
            ->where('id', $artistId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $labelId =
            $validated['label_id']
            ?? $artist->label_id
            ?? $release?->label_id;

        $label = $labelId
            ? Label::query()->findOrFail(
                $labelId
            )
            : null;

        if (
            $role === 'admin'
            && Schema::hasColumn(
                'artists',
                'assigned_admin_id'
            )
        ) {
            abort_unless(
                (int) $artist->assigned_admin_id
                    === (int) $request->user()->id,
                403,
                'This artist is not assigned to you.'
            );
        }

        return [$artist, $label];
    }

    private function authorizeRelease(
        Request $request,
        Release $release,
        PermissionService $permissions
    ): void {
        $role = $permissions->role(
            $request->user()
        );

        if ($role === 'super_admin') {
            return;
        }

        if ($role === 'artist') {
            $artist = Artist::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->firstOrFail();

            abort_unless(
                (int) $release->artist_id
                    === (int) $artist->id,
                403,
                'You cannot manage this release.'
            );

            return;
        }

        if ($role === 'label') {
            $label = Label::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->firstOrFail();

            abort_unless(
                (int) $release->label_id
                    === (int) $label->id,
                403,
                'You cannot manage this release.'
            );

            return;
        }

        if (
            $role === 'admin'
            && Schema::hasColumn(
                'artists',
                'assigned_admin_id'
            )
        ) {
            $artist = Artist::query()->find(
                $release->artist_id
            );

            abort_unless(
                $artist
                && (int) $artist->assigned_admin_id
                    === (int) $request->user()->id,
                403,
                'This release is not assigned to you.'
            );
        }
    }

    private function formContext(
        Request $request,
        PermissionService $permissions
    ): array {
        $role = $permissions->role(
            $request->user()
        );

        $artist = null;
        $label = null;
        $availableArtists = collect();
        $availableLabels = collect();

        if ($role === 'artist') {
            $artist = Artist::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->first();

            if ($artist?->label_id) {
                $label = Label::query()->find(
                    $artist->label_id
                );
            }
        }

        if ($role === 'label') {
            $label = Label::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->first();

            if ($label) {
                $availableArtists = Artist::query()
                    ->where(
                        'label_id',
                        $label->id
                    )
                    ->where(
                        'account_status',
                        'active'
                    )
                    ->where(
                        'can_create_releases',
                        true
                    )
                    ->whereNull('deleted_at')
                    ->orderBy('stage_name')
                    ->get([
                        'id',
                        'stage_name',
                        'legal_name',
                        'label_id',
                        'account_status',
                        'can_create_releases',
                    ]);
            }
        }

        if (
            in_array(
                $role,
                ['admin', 'super_admin'],
                true
            )
        ) {
            $artistsQuery = Artist::query()
                ->whereNull('deleted_at');

            if (
                $role === 'admin'
                && Schema::hasColumn(
                    'artists',
                    'assigned_admin_id'
                )
            ) {
                $artistsQuery->where(
                    'assigned_admin_id',
                    $request->user()->id
                );
            }

            $availableArtists = $artistsQuery
                ->orderBy('stage_name')
                ->get([
                    'id',
                    'stage_name',
                    'legal_name',
                    'label_id',
                ]);

            $labelsQuery = Label::query()
                ->whereNull('deleted_at');

            if (
                $role === 'admin'
                && Schema::hasColumn(
                    'labels',
                    'assigned_admin_id'
                )
            ) {
                $labelsQuery->where(
                    'assigned_admin_id',
                    $request->user()->id
                );
            }

            $availableLabels = $labelsQuery
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]);
        }

        return [
            'role' => $role,

            'permissions' =>
                $permissions->permissions(
                    $request->user()
                ),

            'artist' => $artist
                ? [
                    'id' => $artist->id,
                    'stage_name' =>
                        $artist->stage_name,
                    'legal_name' =>
                        $artist->legal_name,
                    'label_id' =>
                        $artist->label_id,
                ]
                : null,

            'label' => $label
                ? [
                    'id' => $label->id,
                    'name' => $label->name,
                ]
                : null,

            'availableArtists' =>
                $availableArtists,

            'availableLabels' =>
                $availableLabels,

            'distributionStores' =>
                DistributionStore::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'slug',
                        'logo_path',
                        'default_selected',
                    ]),
        ];
    }
    private function generateCatalogNumber(): string
    {
        $latestId = (int) (
            Release::query()->max('id') ?? 0
        );

        $number = $latestId + 1;

        do {
            $catalogNumber =
                'MT'.
                str_pad(
                    (string) $number,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            $exists = Release::query()
                ->where(
                    'catalog_number',
                    $catalogNumber
                )
                ->exists();

            $number++;
        } while ($exists);

        return $catalogNumber;
    }

}
