<?php

namespace App\Http\Controllers\V2;

use App\Services\V2\LabelHierarchyService;
use App\Services\V2\UpcService;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\DistributionStore;
use App\Services\V2\AdminAssignmentService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseWorkflowService;
use App\Services\V2\ReleaseValidationService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseAdminEditService;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
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

    /**
     * Resolve catalogue levels that the current
     * authenticated account may assign to releases.
     *
     * Label account:
     *   own root label + every descendant.
     *
     * Artist account:
     *   only the artist's assigned label.
     *
     * Admin / Super Admin:
     *   all active catalogue levels.
     */
    private function selectableCatalogueLabels(
        \Illuminate\Http\Request $request,
        LabelHierarchyService $hierarchy
    ): \Illuminate\Support\Collection {
        $user = $request->user();

        abort_unless($user, 401);

        $role = (string) $user->role;

        if (
            in_array(
                $role,
                ['admin', 'super_admin'],
                true
            )
        ) {
            return \App\Models\Core\Label::query()
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        }

        if ($role === 'label') {
            $root = \App\Models\Core\Label::query()
                ->where('user_id', $user->id)
                ->whereNull('parent_label_id')
                ->whereNull('deleted_at')
                ->first();

            if (!$root) {
                return collect();
            }

            $ids = $hierarchy->visibleLabelIds(
                (int) $root->id
            );

            return \App\Models\Core\Label::query()
                ->whereIn('id', $ids->all())
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->get()
                ->sortBy(function ($label) use ($hierarchy) {
                    return sprintf(
                        '%05d-%s',
                        $hierarchy
                            ->ancestorIds(
                                (int) $label->id,
                                true
                            )
                            ->count(),
                        strtolower((string) $label->name)
                    );
                })
                ->values();
        }

        if ($role === 'artist') {
            $artist = \App\Models\Core\Artist::query()
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->first();

            if (!$artist || !$artist->label_id) {
                return collect();
            }

            return \App\Models\Core\Label::query()
                ->whereKey((int) $artist->label_id)
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->get();
        }

        return collect();
    }


    /**
     * Server-side protection for release catalogue
     * assignment.
     *
     * Never trust the frontend label selector.
     */
    private function assertCatalogueLabelAllowed(
        \Illuminate\Http\Request $request,
        int $labelId,
        LabelHierarchyService $hierarchy
    ): void {
        $allowed = $this
            ->selectableCatalogueLabels(
                $request,
                $hierarchy
            )
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if (!$allowed->contains($labelId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'label_id' => [
                    'You cannot assign this release to the selected catalogue label.',
                ],
            ]);
        }
    }


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

            /* MIXXTUNE_RELEASES_LIST_V2: extra filters for the All Releases list */
            'release_type' => in_array(
                $request->input('release_type'),
                ['single', 'ep', 'album'],
                true
            ) ? (string) $request->input('release_type') : '',

            /* W76: the release-date month filter was removed (an old link with ?month= no longer hides releases) */
            'artist' => mb_substr(trim((string) $request->input('artist', '')), 0, 190),
            'language' => mb_substr(trim((string) $request->input('language', '')), 0, 60),
            'genre' => mb_substr(trim((string) $request->input('genre', '')), 0, 100),

            'label_id' => $request->integer('label_id') > 0
                ? (string) $request->integer('label_id')
                : '',
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
            /*
             * Team-aware Label catalogue scope.
             *
             * Master Owner:
             *   master + direct child labels.
             *
             * Team / entire_label:
             *   permitted strict two-tier catalogue.
             *
             * Team / selected:
             *   only selected Artists / child Labels.
             */
            $teamAccess = app(
                LabelTeamAccessService::class
            );

            $teamMember =
                $teamAccess->membership(
                    $request->user()
                );

            if ($teamMember) {
                $visibleLabelIds =
                    $teamAccess
                        ->accessibleLabelIds(
                            $request->user()
                        );

                $visibleArtistIds =
                    $teamAccess
                        ->accessibleArtistIds(
                            $request->user()
                        );

                if (
                    $visibleLabelIds->isEmpty()
                    && $visibleArtistIds->isEmpty()
                ) {
                    $query->whereRaw('1 = 0');
                } elseif (
                    $teamMember->scope_level
                    === 'entire_label'
                ) {
                    $query->whereIn(
                        'label_id',
                        $visibleLabelIds
                    );
                } else {
                    $query->where(
                        function ($scope) use (
                            $visibleArtistIds,
                            $visibleLabelIds
                        ) {
                            if (
                                $visibleArtistIds
                                    ->isNotEmpty()
                            ) {
                                $scope->whereIn(
                                    'artist_id',
                                    $visibleArtistIds
                                );
                            }

                            /*
                             * Artist-less operator catalogue
                             * releases remain label-scoped.
                             */
                            if (
                                $visibleLabelIds
                                    ->isNotEmpty()
                            ) {
                                $method =
                                    $visibleArtistIds
                                        ->isNotEmpty()
                                        ? 'orWhere'
                                        : 'where';

                                $scope->{$method}(
                                    function ($labelOnly) use (
                                        $visibleLabelIds
                                    ) {
                                        $labelOnly
                                            ->whereNull(
                                                'artist_id'
                                            )
                                            ->whereIn(
                                                'label_id',
                                                $visibleLabelIds
                                            );
                                    }
                                );
                            }
                        }
                    );
                }
            } else {
                /*
                 * Preserve existing Label Owner behaviour.
                 */
                $rootLabelIds = Label::query()
                    ->where(
                        'user_id',
                        $request->user()->id
                    )
                    ->whereNull('deleted_at')
                    ->pluck('id');

                if ($rootLabelIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $rootLabelIds =
                        $rootLabelIds
                            ->map(
                                fn ($id) =>
                                    (int) $id
                            )
                            ->values();

                    $childLabelIds =
                        Label::query()
                            ->whereIn(
                                'parent_label_id',
                                $rootLabelIds
                            )
                            ->whereNull(
                                'deleted_at'
                            )
                            ->pluck('id')
                            ->map(
                                fn ($id) =>
                                    (int) $id
                            );

                    $visibleLabelIds =
                        $rootLabelIds
                            ->merge(
                                $childLabelIds
                            )
                            ->unique()
                            ->values();

                    $query->whereIn(
                        'label_id',
                        $visibleLabelIds
                    );
                }
            }
        }

        if ($role === 'admin') {
            $assignments = app(
                AdminAssignmentService::class
            );

            $assignedArtistIds =
                $assignments->artistIds(
                    $request->user()
                );

            $assignedLabelIds =
                $assignments->labelIds(
                    $request->user()
                );

            if (
                $assignedArtistIds->isEmpty()
                && $assignedLabelIds->isEmpty()
            ) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(
                    function ($builder) use (
                        $assignedArtistIds,
                        $assignedLabelIds
                    ) {
                        if (
                            $assignedArtistIds->isNotEmpty()
                        ) {
                            $builder->whereIn(
                                'artist_id',
                                $assignedArtistIds
                            );
                        }

                        if (
                            $assignedLabelIds->isNotEmpty()
                        ) {
                            if (
                                $assignedArtistIds->isNotEmpty()
                            ) {
                                $builder->orWhereIn(
                                    'label_id',
                                    $assignedLabelIds
                                );
                            } else {
                                $builder->whereIn(
                                    'label_id',
                                    $assignedLabelIds
                                );
                            }
                        }
                    }
                );
            }
        }

        /*
         * Logical Label drill-down.
         *
         * Same normalized label names such as:
         * 31 Beats / 31-Beats / 31beats
         * are treated as one catalogue account.
         *
         * Role scoping above remains authoritative.
         */
        /* MIXXTUNE_RELEASES_LIST_V2: role scope only, before the label filter */
        $labelOptionsQuery = clone $query;

        $requestedLabelId = $request->integer('label_id');

        if ($requestedLabelId > 0) {
            $requestedLabel = Label::query()
                ->whereKey($requestedLabelId)
                ->whereNull('deleted_at')
                ->first(['id', 'name']);

            abort_unless(
                $requestedLabel,
                404,
                'Label not found.'
            );

            /*
             * W63: same unicode rule as LabelIdentityService (the old
             * a-z0-9 rule turned every Hindi name into "" so one Hindi
             * label showed the releases of all Hindi labels).
             */
            $logicalLabelIds = app(\App\Services\V2\LabelIdentityService::class)
                ->ids((string) $requestedLabel->name)
                ->push((int) $requestedLabel->id)
                ->unique()
                ->values();

            /*
             * This whereIn is additive to the role scope
             * already applied above. It cannot widen access.
             */
            $query->whereIn(
                'label_id',
                $logicalLabelIds
            );
        }

        $requestedArtistId = $request->integer('artist_id');

        if ($requestedArtistId > 0) {
            $query->where(
                'artist_id',
                $requestedArtistId
            );
        }


        /*
         * Draft privacy:
         *
         * A draft is a private workspace belonging to its creator.
         * Label/artist ownership and admin assignments must not expose
         * another user's draft. Once submitted, the existing catalogue
         * and assignment visibility rules continue to apply.
         */
        $query->where(
            function ($builder) use ($request) {
                $builder
                    ->where(
                        'status',
                        '!=',
                        'draft'
                    )
                    ->orWhere(
                        function ($draft) use ($request) {
                            $draft
                                ->where(
                                    'status',
                                    'draft'
                                )
                                ->where(
                                    'created_by',
                                    $request->user()->id
                                );
                        }
                    );
            }
        );

        /*
         * My Releases summary metadata.
         *
         * Use real track metadata only. audio_duration_seconds is preferred
         * because it comes from audio validation; duration_seconds remains
         * the safe fallback for older tracks.
         */
        $query
            ->addSelect([
                /*
                 * Resolve the display label directly from the
                 * release label_id. This is presentation-only;
                 * no release/label ownership data is changed.
                 */
                'label_name' => DB::table('labels')
                    ->select('name')
                    ->whereColumn(
                        'labels.id',
                        'releases.label_id'
                    )
                    ->limit(1),

                'track_count' => DB::table('tracks')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn(
                        'tracks.release_id',
                        'releases.id'
                    )
                    ->whereNull('tracks.deleted_at'),

                /* MIXXTUNE_RELEASES_LIST_V2: store delivery progress */
                'stores_total' => DB::table('release_store_deliveries')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn(
                        'release_store_deliveries.release_id',
                        'releases.id'
                    ),

                'stores_live' => DB::table('release_store_deliveries')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn(
                        'release_store_deliveries.release_id',
                        'releases.id'
                    )
                    ->where('release_store_deliveries.status', 'live'),

                'total_audio_duration_seconds' => DB::table('tracks')
                    ->selectRaw(
                        'ROUND(SUM(COALESCE(NULLIF(audio_duration_seconds, 0), NULLIF(duration_seconds, 0), 0)))'
                    )
                    ->whereColumn(
                        'tracks.release_id',
                        'releases.id'
                    )
                    ->whereNull('tracks.deleted_at'),
            ]);

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
                    )
                    /* MIXXTUNE_RELEASES_LIST_V2: also catalogue no, label name, track ISRC */
                    ->orWhere(
                        'catalog_number',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereIn(
                        'label_id',
                        DB::table('labels')
                            ->select('id')
                            ->where('name', 'like', "%{$search}%")
                    )
                    ->orWhereIn(
                        'releases.id',
                        DB::table('tracks')
                            ->select('release_id')
                            ->whereNull('deleted_at')
                            ->where('isrc', 'like', "%{$search}%")
                    );
            });
        }

        if ($filters['release_type'] !== '') {
            $query->where('release_type', $filters['release_type']);
        }

        /* W76/W77: advanced filters (all applied on the server, every page) */
        if ($filters['artist'] !== '') {
            $query->where('primary_artist_name', $filters['artist']);
        }
        if ($filters['language'] !== '') {
            $query->where('language', $filters['language']);
        }
        if ($filters['genre'] !== '') {
            $query->where('primary_genre', $filters['genre']);
        }

        if ($filters['status'] !== '') {
            /* W119 MIXXTUNE_OWNER_RELEASE_STATUS: for a label / artist "Processing" = approved until Live */
            if (in_array($role, ['label', 'artist'], true) && $filters['status'] === 'processing') {
                $query->whereIn('status', ['approved', 'processing', 'delivered', 'failed']);
            } else {
                $query->where(
                    'status',
                    $filters['status']
                );
            }
        }

        match ($filters['sort']) {
            'oldest' =>
                $query->orderBy('id'),

            'title_asc' =>
                $query->orderBy('title'),

            'title_desc' =>
                $query->orderByDesc('title'),

            /* W76 */
            'release_newest' =>
                $query->orderByRaw('digital_release_date IS NULL')->orderByDesc('digital_release_date')->orderByDesc('id'),

            'release_oldest' =>
                $query->orderByRaw('digital_release_date IS NULL')->orderBy('digital_release_date')->orderBy('id'),

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

                /* MIXXTUNE_RELEASES_LIST_V2: labels the viewer can see, for the Label filter */
                'labelOptions' => Label::query()
                    ->whereIn(
                        'id',
                        (clone $labelOptionsQuery)
                            ->whereNotNull('label_id')
                            ->distinct()
                            ->pluck('label_id')
                    )
                    ->whereNull('deleted_at')
                    ->orderBy('name')
                    ->get(['id', 'name']),

                /* W76: choices for the artist / language / genre filters (only what the viewer has) */
                'filterOptions' => [
                    'artists' => $this->distinctValues($labelOptionsQuery, 'primary_artist_name'),
                    'languages' => $this->distinctValues($labelOptionsQuery, 'language'),
                    'genres' => $this->distinctValues($labelOptionsQuery, 'primary_genre'),
                ],

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

    /** W76: sorted distinct non-empty values of one release column inside the viewer's scope */
    private function distinctValues($scoped, string $column): array
    {
        return (clone $scoped)
            ->toBase()
            ->reorder()
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->limit(500)
            ->pluck($column)
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
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

        /* W82: check a typed UPC before anything is saved (format, check digit, not used) */
        if (!(bool) ($validated['generate_upc'] ?? true)) {
            $typedUpc = app(UpcService::class)->validate((string) ($validated['upc'] ?? ''));
            if (\App\Models\Distribution\Release::query()->where('upc', $typedUpc)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['upc' => 'This UPC is already assigned to another release.']);
            }
        }

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

                'artist_id' => $artist?->id,
                'label_id' => $label?->id,

                'release_type' =>
                    $validated['release_type'],

                'title' => $validated['title'],

                'primary_artist_name' =>
                    collect(
                        $validated['primary_artists'] ?? []
                    )
                        ->pluck('name')
                        ->filter()
                        ->first()
                    ?: (
                        $validated['primary_artist_name']
                        ?? $artist?->stage_name
                        ?? $artist?->legal_name
                    ),

                'featuring_artist_name' =>
                    collect(
                        $validated['featuring_artists'] ?? []
                    )
                        ->pluck('name')
                        ->filter()
                        ->implode(', ')
                    ?: (
                        $validated['featuring_artist_name']
                        ?? null
                    ),

                'primary_artists' =>
                    array_values(
                        $validated['primary_artists'] ?? []
                    ),

                'featuring_artists' =>
                    array_values(
                        $validated['featuring_artists'] ?? []
                    ),

                'language' =>
                    $validated['language'] ?? null,

                'primary_genre' =>
                    $validated[
                        'primary_genre'
                    ] ?? null,

                'sub_genre' =>
                    $validated['sub_genre'] ?? null,

                'upc' => null,
                'upc_is_auto_generated' => false,

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

        /** @var UpcService $upcService */
        $upcService = app(UpcService::class);

        if ((bool) $validated['generate_upc']) {
            /* W82: Mixx Tune enters the allotted UPC later (Pending UPC list) */
            $release->update([
                'upc_is_auto_generated' => true,
            ]);
        } else {
            $release = $upcService->assignManual(
                $release,
                $validated['upc'],
                $request->user()
            );

            $release->update([
                'upc_is_auto_generated' => false,
            ]);
        }

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
            /*
             * ReleaseAccessService is now the single
             * Team-aware release authorization source.
             */
            app(
                ReleaseAccessService::class
            )->authorizeView(
                $request->user(),
                $release
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
            ],
            true
        );

        /* MIXXTUNE_SUPER_ADMIN_FULL_EDIT */
        $adminEdit = app(ReleaseAccessService::class)
            ->isAdminOverride($request->user(), $release);

        /*
         * For selected-scope Team Users, expose only Tracks
         * that their catalogue scope permits.
         *
         * Owners, Artists, Admins and Super Admins keep the
         * existing behaviour.
         */
        $visibleTracks = $release->tracks;

        if ($role === 'label') {
            $teamAccess = app(
                LabelTeamAccessService::class
            );

            if (
                $teamAccess->membership(
                    $request->user()
                )
            ) {
                $allowedTrackIds =
                    $teamAccess->accessibleTrackIds(
                        $request->user()
                    );

                $visibleTracks =
                    $visibleTracks
                        ->filter(
                            fn ($track) =>
                                $allowedTrackIds->contains(
                                    (int) $track->id
                                )
                        )
                        ->values();
            }
        }

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
                    'admin_edit' =>
                        $adminEdit,

                    'tracks' =>
                        $visibleTracks
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
                                    'stream_url' =>
                                        $track->audio_path
                                            ? route(
                                                'v2.release-tracks.stream',
                                                $track
                                            )
                                            : null,
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

        /* W64: the Review step shows the label name (it said "Not provided" for admins) */
        $release->setAttribute(
            'label_name',
            $release->label_id
                ? Label::query()->whereKey($release->label_id)->value('name')
                : null
        );

        return Inertia::render(
            'V2/Releases/Create',
            [
                ...$context,
                'release' => $release,
                'title' => 'Edit Release',

                /* MIXXTUNE_SUPER_ADMIN_FULL_EDIT */
                'adminEdit' => app(ReleaseAdminEditService::class)
                    ->active($request, $release),
            ]
        );
    }

    public function destroy(
        Request $request,
        Release $release,
        ReleaseAccessService $access
    ): RedirectResponse {
        /*
         * Normal catalogue deletion is intentionally limited to drafts.
         * Submitted, approved, delivered, live and other workflow states
         * must use their dedicated workflow instead.
         */
        abort_unless(
            $release->status === 'draft',
            403,
            'Only draft releases can be deleted.'
        );

        abort_unless(
            (int) $release->created_by
                === (int) $request->user()->id,
            403,
            'Only the draft creator can delete this release.'
        );

        $access->authorizeUpdate(
            $request->user(),
            $release
        );

        $title = $release->title;

        $release->update([
            'updated_by' => $request->user()->id,
        ]);

        /*
         * Release uses SoftDeletes. Related production records and files
         * are therefore not physically destroyed by this action.
         */
        $release->delete();

        return back()->with(
            'success',
            "Draft '{$title}' deleted successfully."
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

        /*
         * MIXXTUNE_SUPER_ADMIN_FULL_EDIT
         * Super Admin editing a locked release: keep old files,
         * allow a corrected UPC and a past release date (live
         * releases are already out), keep status / wizard progress,
         * audit every change and refresh the catalogue record.
         */
        $adminEditService = app(ReleaseAdminEditService::class);
        $adminEdit = $adminEditService->active($request, $release);

        $validated = $this->validateDraft($request, $adminEdit);
        $auditFields = [
            'title', 'version', 'release_type', 'catalog_number', 'upc',
            'artist_id', 'label_id', 'primary_artist_name',
            'featuring_artist_name', 'language', 'primary_genre',
            'sub_genre', 'original_release_date', 'digital_release_date',
            'copyright_owner', 'copyright_year', 'phonographic_owner',
            'phonographic_year', 'artwork_path',
        ];
        $before = $adminEdit ? $release->only($auditFields) : [];
        $upc = $release->upc;

        if ($adminEdit) {
            $newUpc = trim((string) $request->input('upc', ''));

            if (
                $newUpc !== ''
                && $newUpc !== (string) $release->upc
                && !$request->boolean('generate_upc')
            ) {
                $newUpc = app(UpcService::class)->validate($newUpc); /* W82: 12/13 digits + check digit */
                $request->merge(['upc' => $newUpc]);
                $request->validate([
                    'upc' => [
                        'regex:/^\d{12,14}$/',
                        \Illuminate\Validation\Rule::unique('releases', 'upc')
                            ->ignore($release->id),
                    ],
                ], [
                    'upc.regex' => 'UPC must be 12 to 14 digits.',
                    'upc.unique' => 'This UPC is already used by another release.',
                ]);

                $upc = $newUpc;
            }
        }

        [$artist, $label] = $this->resolveOwnership(
            $request,
            $permissions,
            $validated,
            $release
        );

        if ($request->hasFile('artwork')) {
            if ($release->artwork_path && !$adminEdit) {
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
                ($validated['catalog_number'] ?? null)
                ?: $release->catalog_number,

            'artist_id' => $artist?->id,
            'label_id' => $label?->id,

            'release_type' =>
                ($validated['release_type'] ?? null)
                ?: $release->release_type,

            'title' => $validated['title'],

            'version' =>
                $validated['version'] ?? null,

            'primary_artist_name' =>
                collect(
                    $validated['primary_artists'] ?? []
                )
                    ->pluck('name')
                    ->filter()
                    ->first()
                ?: (
                    $validated['primary_artist_name']
                    ?? $release->primary_artist_name
                    ?? $artist?->stage_name
                    ?? $artist?->legal_name
                ),

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

            /*
             * UPC is immutable once assigned.
             * Prevent Step 1 edits from clearing or replacing it.
             * (Super Admin edit may correct it: see above.)
             */
            'upc' => $upc,

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
                $release->status,

            'wizard_step' => $adminEdit
                ? $release->wizard_step
                : max(
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

            'completion_percentage' => $adminEdit
                ? $release->completion_percentage
                : max(
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

            'updated_by' =>
                $request->user()->id,
        ]);

        if ($adminEdit) {
            $adminEditService->record(
                $release,
                'release details',
                $before,
                $release->fresh()->only($auditFields),
                $request
            );

            return redirect()
                ->route('v2.releases.edit', $release)
                ->with('success', 'Release updated (Super Admin edit).');
        }

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

        /* MIXXTUNE_SUPER_ADMIN_FULL_EDIT */
        $adminEditService = app(ReleaseAdminEditService::class);
        $adminEdit = $adminEditService->active($request, $release);
        $distFields = ['stores', 'worldwide', 'territories', 'release_timezone', 'pre_order'];
        $before = $adminEdit ? $release->only($distFields) : [];

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

        if ($adminEdit) {
            $adminEditService->record(
                $release,
                'stores / territories',
                $before,
                $release->fresh()->only($distFields),
                $request
            );
        }

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

        /* W119 MIXXTUNE_NEW_RELEASE_UI_N6: show the release with "sent for review" and what happens next */
        return redirect()
            ->route('v2.releases.show', $release)
            ->with(
                'success',
                "Release '{$release->title}' submitted for review."
            )
            ->with('submitted', true);
    }

    private function validateDraft(
        Request $request,
        bool $adminEdit = false
    ): array {
        /* W84: an empty featuring-artist box (added with + but left blank) is simply ignored */
        if (is_array($request->input('featuring_artists'))) {
            $request->merge([
                'featuring_artists' => array_values(array_filter(
                    $request->input('featuring_artists'),
                    fn ($row) => trim((string) (is_array($row) ? ($row['name'] ?? '') : $row)) !== ''
                )),
            ]);
        }

        $rules = [
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
                'max:3',
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

            'generate_upc' => [
                'required',
                'boolean',
            ],

            'upc' => [
                'nullable',
                'string',
                'max:20',
                'required_if:generate_upc,false',
            ],

            'language' => [
                'required',
                'string',
                'max:100',
            ],

            'primary_genre' => [
                'required',
                'string',
                'max:100',
            ],

            'sub_genre' => [
                'required',
                'string',
                'max:100',
            ],

            'original_release_date' => [
                'nullable',
                'date',
            ],

            /* W118 MIXXTUNE_RELEASE_DATE_RULE: Super Admin / Manager may also pick a past date;
               a label or artist today or later */
            'digital_release_date' => ($adminEdit || \App\Services\V2\PlatformRules::staffDates($request->user()))
                ? ['required', 'date']
                : [
                'required',
                'date',
                'after_or_equal:'.\App\Services\V2\PlatformRules::earliestReleaseDate()->toDateString(),
            ],

            'copyright_owner' => [
                'required',
                'string',
                'max:255',
            ],

            'copyright_year' => [
                'required',
                'integer',
                'min:1900',
                'max:2100',
            ],

            'phonographic_owner' => [
                'required',
                'string',
                'max:255',
            ],

            'phonographic_year' => [
                'required',
                'integer',
                'min:1900',
                'max:2100',
            ],

            'artwork' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png',
                /* MIXXTUNE_SETTINGS_WIRED_V1: Master Settings > Upload Rules > Artwork Maximum MB */
                'max:'.(\App\Services\V2\PlatformRules::artworkMaxMb() * 1024),
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
        ];

        /*
         * MIXXTUNE_SUPER_ADMIN_FULL_EDIT
         * Old / imported releases often miss fields that the new
         * wizard requires (sub genre, (c)/(p) lines, catalogue no.).
         * A Super Admin fixing a live release must be able to save
         * just the poster or one field, so only the title stays
         * required and the existing release type is accepted.
         */
        if ($adminEdit) {
            foreach ($rules as $field => $list) {
                if ($field === 'title') {
                    continue;
                }

                $rules[$field] = array_values(array_map(
                    fn ($rule) => is_string($rule) && (
                        $rule === 'required' || str_starts_with($rule, 'required_if')
                    ) ? 'nullable' : $rule,
                    $list
                ));
            }

            $rules['release_type'] = ['nullable', 'string', 'max:40'];
            $rules['primary_artists'] = ['nullable', 'array', 'max:3'];
        }

        return $request->validate($rules, [
            'digital_release_date.after_or_equal' => \App\Services\V2\PlatformRules::releaseDateMessage(),
            'artwork.max' => 'Artwork must be '.\App\Services\V2\PlatformRules::artworkMaxMb().' MB or smaller.',
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
            $hierarchy = app(
                LabelHierarchyService::class
            );

            /*
             * Exact catalogue placement.
             *
             * A Master Label login may place a release
             * on its own root or any authorized
             * descendant catalogue level.
             *
             * Never trust label_id from the browser.
             */
            $labelId =
                $validated['label_id']
                ?? $release?->label_id;

            /*
             * Backward compatibility:
             * existing single/root-label forms may
             * omit label_id.
             */
            if (! $labelId) {
                $selectable =
                    $this->selectableCatalogueLabels(
                        $request,
                        $hierarchy
                    );

                if ($selectable->count() === 1) {
                    $labelId =
                        $selectable->first()->id;
                }
            }

            abort_unless(
                $labelId,
                422,
                'Select a catalogue label.'
            );

            $this->assertCatalogueLabelAllowed(
                $request,
                (int) $labelId,
                $hierarchy
            );

            $label = Label::query()
                ->where(
                    'id',
                    (int) $labelId
                )
                ->whereNull('deleted_at')
                ->firstOrFail();

            $artistId =
                $validated['artist_id']
                ?? $release?->artist_id;

            /*
             * Artist and catalogue level must agree.
             *
             * This prevents combinations such as:
             *
             *   Catalogue Level = X1
             *   Artist          = artist from Y
             */
            if (! $artistId) {
                $labelArtistIds = Artist::query()
                    ->where(
                        'label_id',
                        $label->id
                    )
                    ->whereNull('deleted_at')
                    ->where(
                        'account_status',
                        'active'
                    )
                    ->where(
                        'can_create_releases',
                        true
                    )
                    ->limit(2)
                    ->pluck('id');

                if (
                    $labelArtistIds->count()
                    === 1
                ) {
                    $artistId =
                        $labelArtistIds->first();
                }
            }

            /*
             * W83 MIXXTUNE_NO_ACCOUNT_ARTIST_FIELD: the form no longer asks
             * for an "Account Artist". It is linked in the background: the
             * only artist account of the level, or the artist account whose
             * name matches the primary artist; otherwise none (the release
             * belongs to the label, like releases made by the team).
             */
            if (! $artistId) {
                $primaryName = mb_strtolower(trim((string) (
                    $validated['primary_artist_name']
                    ?? data_get($validated, 'primary_artists.0.name')
                    ?? $release?->primary_artist_name
                    ?? ''
                )));

                if ($primaryName !== '') {
                    $artistId = Artist::query()
                        ->where('label_id', $label->id)
                        ->whereNull('deleted_at')
                        ->where('account_status', 'active')
                        ->where('can_create_releases', true)
                        ->get(['id', 'stage_name', 'legal_name'])
                        ->first(fn ($a) => in_array($primaryName, [
                            mb_strtolower(trim((string) $a->stage_name)),
                            mb_strtolower(trim((string) $a->legal_name)),
                        ], true))?->id;
                }
            }

            if (! $artistId) {
                return [null, $label];
            }

            $artist = Artist::query()
                ->where(
                    'id',
                    (int) $artistId
                )
                ->where(
                    'label_id',
                    $label->id
                )
                ->whereNull('deleted_at')
                ->where(
                    'account_status',
                    'active'
                )
                ->where(
                    'can_create_releases',
                    true
                )
                ->first();

            if (! $artist) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'artist_id' => [
                        'The selected artist does not belong to the selected catalogue label.',
                    ],
                ]);
            }

            return [$artist, $label];
        }

        $artistId =
            $validated['artist_id']
            ?? $release?->artist_id;

        $labelId =
            $validated['label_id']
            ?? $release?->label_id;

        /*
         * Admin / Super Admin may create a release directly
         * under a catalog Label without attaching an Artist
         * login account. Artist credits remain mandatory
         * metadata and are handled separately.
         */
        if (! $artistId) {
            abort_unless(
                $labelId,
                422,
                'Select a catalog label.'
            );

            $label = Label::query()
                ->where('id', $labelId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            if ($role === 'admin') {
                $assignments = app(
                    AdminAssignmentService::class
                );

                abort_unless(
                    $assignments->canAccessRelease(
                        $request->user(),
                        null,
                        (int) $label->id
                    ),
                    403,
                    'This label is not assigned to you.'
                );
            }

            return [null, $label];
        }

        $artist = Artist::query()
            ->where('id', $artistId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $labelId =
            $labelId
            ?? $artist->label_id;

        $label = $labelId
            ? Label::query()->findOrFail(
                $labelId
            )
            : null;

        if ($role === 'admin') {
            $assignments = app(
                AdminAssignmentService::class
            );

            abort_unless(
                $assignments->canAccessRelease(
                    $request->user(),
                    (int) $artist->id,
                    $label
                        ? (int) $label->id
                        : null
                ),
                403,
                'This artist or label is not assigned to you.'
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
            $rootLabelIds = Label::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                );

            abort_if(
                $rootLabelIds->isEmpty(),
                403,
                'You cannot manage this release.'
            );

            /*
             * Mixx Tune hierarchy is strictly two-tier:
             *
             * Master Label
             *   -> direct Sub-Labels
             *
             * A master may manage its own catalogue and
             * direct child-label catalogue. No recursive
             * descendant access is granted.
             */
            $childLabelIds = Label::query()
                ->whereIn(
                    'parent_label_id',
                    $rootLabelIds
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                );

            $manageableLabelIds =
                $rootLabelIds
                    ->merge($childLabelIds)
                    ->unique()
                    ->values();

            abort_unless(
                $release->label_id
                && $manageableLabelIds->contains(
                    (int) $release->label_id
                ),
                403,
                'You cannot manage this release.'
            );

            return;
        }

        if ($role === 'admin') {
            $access = app(
                ReleaseAccessService::class
            );

            abort_unless(
                $access->canAccess(
                    $request->user(),
                    $release
                ),
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
            $hierarchy = app(
                LabelHierarchyService::class
            );

            /*
             * RELEASE CATALOGUE LEVELS
             * ========================
             *
             * A Label login can have:
             *
             * 1. explicit assigned labels
             * 2. legacy direct labels via labels.user_id
             *
             * Every accessible root/base label expands
             * to its full descendant catalogue tree.
             */
            $assignedLabelIds =
                $request
                    ->user()
                    ->assignedLabels()
                    ->pluck('labels.id')
                    ->map(
                        fn ($id) =>
                            (int) $id
                    );

            $directLabelIds =
                Label::query()
                    ->where(
                        'user_id',
                        $request->user()->id
                    )
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->map(
                        fn ($id) =>
                            (int) $id
                    );

            $baseLabelIds =
                $assignedLabelIds
                    ->merge(
                        $directLabelIds
                    )
                    ->unique()
                    ->values();

            $expandedLabelIds =
                collect();

            foreach (
                $baseLabelIds
                as $baseLabelId
            ) {
                $expandedLabelIds =
                    $expandedLabelIds
                        ->merge(
                            $hierarchy
                                ->visibleLabelIds(
                                    (int) $baseLabelId
                                )
                        );
            }

            $labelIds =
                $expandedLabelIds
                    ->map(
                        fn ($id) =>
                            (int) $id
                    )
                    ->unique()
                    ->values();

            if (
                $labelIds->isNotEmpty()
            ) {
                $availableLabels =
                    Label::query()
                        ->whereIn(
                            'id',
                            $labelIds->all()
                        )
                        ->whereNull(
                            'deleted_at'
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->get([
                            'id',
                            'name',
                            'parent_label_id',
                            'label_type',
                        ])
                        ->sortBy(
                            function ($item)
                            use ($hierarchy) {
                                $depth =
                                    $hierarchy
                                        ->ancestorIds(
                                            (int) $item->id,
                                            true
                                        )
                                        ->count();

                                return sprintf(
                                    '%05d-%s',
                                    $depth,
                                    strtolower(
                                        (string)
                                            $item->name
                                    )
                                );
                            }
                        )
                        ->values();

                /*
                 * Preserve legacy default when only
                 * one catalogue level is available.
                 */
                if (
                    $availableLabels->count()
                    === 1
                ) {
                    $label =
                        $availableLabels
                            ->first();
                }

                /*
                 * Artists are supplied for every
                 * selectable catalogue level.
                 *
                 * Frontend will filter these by
                 * selected label_id.
                 */
                $availableArtists =
                    Artist::query()
                        ->whereIn(
                            'label_id',
                            $labelIds->all()
                        )
                        ->where(
                            'account_status',
                            'active'
                        )
                        ->where(
                            'can_create_releases',
                            true
                        )
                        ->whereNull(
                            'deleted_at'
                        )
                        ->orderBy(
                            'stage_name'
                        )
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
            /*
             * Account Artist selector must contain only real,
             * active Artist login accounts. Manual Primary Artist
             * metadata is handled separately in the release form.
             */
            $artistsQuery = Artist::query()
                ->whereNull('deleted_at')
                ->whereNotNull('user_id')
                ->where('account_status', 'active')
                ->where('can_create_releases', true)
                ->whereHas(
                    'user',
                    function ($query) {
                        $query->where('role', 'artist');
                    }
                );

            if ($role === 'admin') {
                $assignments = app(
                    AdminAssignmentService::class
                );

                $assignedArtistIds =
                    $assignments->artistIds(
                        $request->user()
                    );

                $assignedLabelIds =
                    $assignments->labelIds(
                        $request->user()
                    );

                if (
                    $assignedArtistIds->isEmpty()
                    && $assignedLabelIds->isEmpty()
                ) {
                    $artistsQuery->whereRaw(
                        '1 = 0'
                    );
                } else {
                    $artistsQuery->where(
                        function ($builder) use (
                            $assignedArtistIds,
                            $assignedLabelIds
                        ) {
                            if (
                                $assignedArtistIds->isNotEmpty()
                            ) {
                                $builder->whereIn(
                                    'id',
                                    $assignedArtistIds
                                );
                            }

                            if (
                                $assignedLabelIds->isNotEmpty()
                            ) {
                                if (
                                    $assignedArtistIds->isNotEmpty()
                                ) {
                                    $builder->orWhereIn(
                                        'label_id',
                                        $assignedLabelIds
                                    );
                                } else {
                                    $builder->whereIn(
                                        'label_id',
                                        $assignedLabelIds
                                    );
                                }
                            }
                        }
                    );
                }
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

            if ($role === 'admin') {
                if (!isset($assignments)) {
                    $assignments = app(
                        AdminAssignmentService::class
                    );
                }

                if (!isset($assignedLabelIds)) {
                    $assignedLabelIds =
                        $assignments->labelIds(
                            $request->user()
                        );
                }

                if ($assignedLabelIds->isEmpty()) {
                    $labelsQuery->whereRaw(
                        '1 = 0'
                    );
                } else {
                    $labelsQuery->whereIn(
                        'id',
                        $assignedLabelIds
                    );
                }
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
                        $artist?->stage_name,
                    'legal_name' =>
                        $artist?->legal_name,
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

            /* W86: names already used on this account's releases, for typing suggestions */
            'artistNameSuggestions' =>
                $this->artistNameSuggestions(
                    $role,
                    $artist,
                    $label,
                    $availableLabels
                ),

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
    /*
     * W86 MIXXTUNE_ARTIST_NAME_SUGGESTIONS
     * Artist names this account has already used (release primary /
     * featuring artists, track artists and its artist profiles), so the
     * form can suggest them while typing ("ar" -> Arijit Singh, Arun ...).
     * Only names from releases the user may see: own artist for an artist
     * login, the selectable labels for a label / manager, everything for a
     * Super Admin.
     */
    private function artistNameSuggestions(string $role, $artist, $label, $availableLabels): array
    {
        $labelIds = collect($availableLabels)->pluck('id')->push($label?->id)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $releases = Release::query()->whereNull('deleted_at');
        if ($role === 'super_admin') {
            // all releases
        } elseif ($role === 'artist') {
            $artist ? $releases->where('artist_id', $artist->id) : $releases->whereRaw('1 = 0');
        } elseif ($labelIds->isNotEmpty()) {
            $releases->whereIn('label_id', $labelIds);
        } else {
            $releases->whereRaw('1 = 0');
        }

        $rows = $releases->latest('id')->limit(3000)->get(['id', 'primary_artist_name', 'featuring_artist_name']);
        $names = collect();
        foreach ($rows as $row) {
            $names->push($row->primary_artist_name, $row->featuring_artist_name);
        }

        if ($rows->isNotEmpty()) {
            /* W91: lyricists and composers too, for the Track Details window */
            $trackColumns = ['primary_artist_name', 'featuring_artist_name'];
            foreach (['author_name', 'composer_name'] as $column) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('tracks', $column)) {
                    $trackColumns[] = $column;
                }
            }
            \App\Models\Distribution\Track::query()
                ->whereIn('release_id', $rows->pluck('id'))
                ->get($trackColumns)
                ->each(function ($t) use ($names, $trackColumns) {
                    foreach ($trackColumns as $column) {
                        $names->push($t->{$column});
                    }
                });
        }

        $artists = Artist::query()->whereNull('deleted_at');
        if ($role === 'artist') {
            $artist ? $artists->where('id', $artist->id) : $artists->whereRaw('1 = 0');
        } elseif ($role !== 'super_admin') {
            $labelIds->isNotEmpty() ? $artists->whereIn('label_id', $labelIds) : $artists->whereRaw('1 = 0');
        }
        $artists->limit(3000)->pluck('stage_name')->each(fn ($n) => $names->push($n));

        return $names
            ->filter()
            ->flatMap(fn ($v) => preg_split('/\s*,\s*/', (string) $v))
            ->map(fn ($n) => trim(preg_replace('/\s+/', ' ', $n)))
            ->filter(fn ($n) => $n !== '' && mb_strlen($n) <= 120)
            ->unique(fn ($n) => mb_strtolower($n))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->take(5000)
            ->all();
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
