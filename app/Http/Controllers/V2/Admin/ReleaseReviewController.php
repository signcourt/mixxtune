<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Services\V2\AdminAssignmentService;
use App\Services\V2\AdminReleaseReviewService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseValidationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReleaseReviewController extends Controller
{
    public function index(
        Request $request,
        AdminReleaseReviewService $reviews,
        PermissionService $permissions,
        AdminAssignmentService $assignments
    ): Response {
        $reviews->authorizeReviewer(
            $request->user()
        );

        $role = $permissions->role(
            $request->user()
        );

        $reviewStatuses = [
            'submitted',
            'approved',
            'changes_requested',
            'rejected',
            'processing',
        ];

        $requestedStatus = trim(
            (string) $request->input(
                'status',
                'submitted'
            )
        );

        $filters = [
            'search' => trim(
                (string) $request->input(
                    'search',
                    ''
                )
            ),

            /*
             * Drafts are private workspaces and can never enter
             * the Admin/Super Admin review queue.
             */
            'status' => in_array(
                $requestedStatus,
                $reviewStatuses,
                true
            )
                ? $requestedStatus
                : 'submitted',

            'sort' => trim(
                (string) $request->input(
                    'sort',
                    'oldest'
                )
            ),
        ];

        /* W63 MIXXTUNE_RELEASE_QUEUE_V2: label name + owner shown in the queue */
        $query = Release::query()
            ->whereNull('deleted_at')
            ->select('releases.*')
            ->addSelect([
                'label_name' => DB::table('labels')->select('name')->whereColumn('labels.id', 'releases.label_id')->limit(1),
                /* W118 MIXXTUNE_REVIEW_LIST_DETAILS: who sent it, and how many tracks still have no ISRC */
                'sent_by_name' => DB::table('users')->select('name')->whereColumn('users.id', 'releases.created_by')->limit(1),
                'sent_by_role' => DB::table('users')->select('role')->whereColumn('users.id', 'releases.created_by')->limit(1),
                'tracks_without_isrc' => DB::table('tracks')->selectRaw('count(*)')->whereColumn('tracks.release_id', 'releases.id')->whereNull('tracks.deleted_at')->where(fn ($q) => $q->whereNull('tracks.isrc')->orWhere('tracks.isrc', '')),
            ])
            ->withCount('tracks');

        $artistIds = collect();
        $labelIds = collect();

        if ($role === 'admin') {
            $artistIds =
                $assignments->artistIds(
                    $request->user()
                );

            $labelIds =
                $assignments->labelIds(
                    $request->user()
                );

            if (
                $artistIds->isEmpty()
                && $labelIds->isEmpty()
            ) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(
                    function ($builder) use (
                        $artistIds,
                        $labelIds
                    ) {
                        if ($artistIds->isNotEmpty()) {
                            $builder->whereIn(
                                'artist_id',
                                $artistIds
                            );
                        }

                        if ($labelIds->isNotEmpty()) {
                            if ($artistIds->isNotEmpty()) {
                                $builder->orWhereIn(
                                    'label_id',
                                    $labelIds
                                );
                            } else {
                                $builder->whereIn(
                                    'label_id',
                                    $labelIds
                                );
                            }
                        }
                    }
                );
            }
        }

        if ($filters['status'] !== '') {
            $query->where(
                'status',
                $filters['status']
            );
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(
                function ($builder) use ($search) {
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
                        ->orWhere(
                            'catalog_number',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereIn(
                            'label_id',
                            DB::table('labels')->select('id')->where('name', 'like', "%{$search}%")
                        )
                        ->orWhereIn(
                            'releases.id',
                            DB::table('tracks')->select('release_id')->whereNull('deleted_at')->where('isrc', 'like', "%{$search}%")
                        );
                }
            );
        }

        if (!in_array($filters['sort'], ['oldest', 'latest', 'title_asc', 'title_desc', 'release_date'], true)) {
            $filters['sort'] = 'oldest';
        }

        match ($filters['sort']) {
            'release_date' =>
                $query->orderBy('digital_release_date')->orderBy('id'),

            'latest' =>
                $query->orderByDesc('submitted_at'),

            'title_asc' =>
                $query->orderBy('title'),

            'title_desc' =>
                $query->orderByDesc('title'),

            default =>
                $query
                    ->orderBy('submitted_at')
                    ->orderBy('id'),
        };

        $countsQuery = Release::query()
            ->whereNull('deleted_at');

        if ($role === 'admin') {
            if (
                $artistIds->isEmpty()
                && $labelIds->isEmpty()
            ) {
                $countsQuery->whereRaw('1 = 0');
            } else {
                $countsQuery->where(
                    function ($builder) use (
                        $artistIds,
                        $labelIds
                    ) {
                        if ($artistIds->isNotEmpty()) {
                            $builder->whereIn(
                                'artist_id',
                                $artistIds
                            );
                        }

                        if ($labelIds->isNotEmpty()) {
                            if ($artistIds->isNotEmpty()) {
                                $builder->orWhereIn(
                                    'label_id',
                                    $labelIds
                                );
                            } else {
                                $builder->whereIn(
                                    'label_id',
                                    $labelIds
                                );
                            }
                        }
                    }
                );
            }
        }

        /* W63: one grouped query for the five cards (was five) */
        $grouped = (clone $countsQuery)
            ->whereIn('status', $reviewStatuses)
            ->groupBy('status')
            ->pluck(DB::raw('count(*) as c'), 'status');

        $counts = [];
        foreach ($reviewStatuses as $st) {
            $counts[$st] = (int) ($grouped[$st] ?? 0);
        }

        return Inertia::render(
            'V2/Admin/ReleaseReviews/Index',
            [
                'role' => $role,
                'filters' => $filters,
                'counts' => $counts,
                'releases' =>
                    $query
                        ->paginate(25)
                        ->withQueryString(),
            ]
        );
    }

    public function show(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews,
        ReleaseAccessService $access,
        ReleaseValidationService $validator
    ): Response {
        $reviews->authorizeReviewer(
            $request->user()
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $release->load([
            'artist',
            'label',
            'tracks' => function ($query) {
                $query
                    ->with([
                        'contributors',
                        'contributorCredits',
                    ])
                    ->orderBy('disc_number')
                    ->orderBy('track_number')
                    ->orderBy('id');
            },
        ]);

        $release->setAttribute(
            'label_name',
            $release->label?->name
                ?? $release->label?->label_name
                ?? null
        );

        $release->tracks->each(function ($track) {
            $track->setAttribute(
                'stream_url',
                $track->audio_path
                    ? route(
                        'v2.release-tracks.stream',
                        $track
                    )
                    : null
            );
        });

        $statusLogs = [];

        if (
            Schema::hasTable(
                'release_status_logs'
            )
        ) {
            /* W63: who did it */
            $statusLogs = DB::table(
                'release_status_logs'
            )
                ->leftJoin('users', 'users.id', '=', 'release_status_logs.changed_by')
                ->where(
                    'release_status_logs.release_id',
                    $release->id
                )
                ->orderByDesc('release_status_logs.id')
                ->limit(100)
                ->get(['release_status_logs.*', 'users.name as changed_by_name']);
        }

        return Inertia::render(
            'V2/Admin/ReleaseReviews/Show',
            [
                'role' =>
                    app(
                        \App\Services\V2\PermissionService::class
                    )->role(
                        $request->user()
                    ),

                'release' => $release,

                'submissionChecklist' =>
                    $validator->checklist(
                        $release
                    ),

                'availableActions' => [
                'approve' =>
                    $release->status
                    === 'submitted',

                'reject' =>
                    $release->status
                    === 'submitted',

                'request_changes' =>
                    $release->status
                    === 'submitted',

                'start_processing' =>
                    $release->status
                    === 'approved',
            ],

                'statusLogs' =>
                    $statusLogs,

                /* W63: next release waiting in the queue (oldest first), for "Approve & next" */
                'nextInQueue' => $this->nextInQueue($request, $release),

                'deliveryUrl' => in_array($release->status, ['approved', 'processing', 'delivered', 'live', 'failed', 'takedown_requested', 'taken_down'], true)
                    ? '/admin/distribution/'.$release->id
                    : null,

                'canEditMetadata' => in_array($release->status, ['submitted', 'changes_requested', 'approved'], true),
            ]
        );
    }

    public function updateMetadata(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews,
        ReleaseAccessService $access
    ): RedirectResponse {
        $reviews->authorizeReviewer($request->user());
        $access->authorizeView($request->user(), $release);

        abort_unless(
            in_array($release->status, [
                'submitted',
                'changes_requested',
                'approved',
            ], true),
            422,
            'Release metadata cannot be edited in its current status.'
        );

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'release_type' => ['nullable', 'string', 'max:100'],
            'primary_artist_name' => ['nullable', 'string', 'max:255'],
            'featuring_artist_name' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:100'],
            'primary_genre' => ['nullable', 'string', 'max:100'],
            'sub_genre' => ['nullable', 'string', 'max:100'],
            'original_release_date' => ['nullable', 'date'],
            'digital_release_date' => ['nullable', 'date'],
            'copyright_owner' => ['nullable', 'string', 'max:255'],
            'copyright_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'phonographic_owner' => ['nullable', 'string', 'max:255'],
            'phonographic_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'release_timezone' => ['nullable', 'string', 'max:100'],
            'pre_order' => ['nullable', 'boolean'],
        ]);

        $changes = $this->diff($release, $validated);
        $validated['updated_by'] = $request->user()->id;

        $release->fill($validated);
        $release->save();

        if ($changes) {
            $this->audit('release.metadata_edited_in_review', 'Release "'.$release->title.'" (#'.$release->id.') edited in review: '.implode(', ', array_keys($changes)).'.', array_map(fn ($c) => $c[0], $changes), array_map(fn ($c) => $c[1], $changes), $release, $request);
        }

        return back()->with(
            'success',
            $changes ? 'Saved: '.implode(', ', array_map(fn ($k) => str_replace('_', ' ', $k), array_keys($changes))).'.' : 'Nothing changed.'
        );
    }


    public function updateTrackMetadata(
        Request $request,
        Release $release,
        Track $track,
        AdminReleaseReviewService $reviews,
        ReleaseAccessService $access
    ): RedirectResponse {
        $reviews->authorizeReviewer($request->user());
        $access->authorizeView($request->user(), $release);

        abort_unless(
            (int) $track->release_id === (int) $release->id,
            404
        );

        abort_unless(
            in_array($release->status, [
                'submitted',
                'changes_requested',
                'approved',
            ], true),
            422,
            'Track metadata cannot be edited in its current status.'
        );

        $validated = $request->validate([
            'disc_number' => ['nullable', 'integer', 'min:1'],
            'track_number' => ['nullable', 'integer', 'min:1'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'track_type' => ['nullable', 'string', 'max:100'],
            'primary_artist_name' => ['nullable', 'string', 'max:255'],
            'featuring_artist_name' => ['nullable', 'string', 'max:255'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'composer_name' => ['nullable', 'string', 'max:255'],
            'arranger_name' => ['nullable', 'string', 'max:255'],
            'producer_name' => ['nullable', 'string', 'max:255'],
            'music_director_name' => ['nullable', 'string', 'max:255'],
            'publisher_name' => ['nullable', 'string', 'max:255'],
            'p_line' => ['nullable', 'string', 'max:255'],
            'release_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'language' => ['nullable', 'string', 'max:100'],
            'title_language' => ['nullable', 'string', 'max:100'],
            'lyrics_language' => ['nullable', 'string', 'max:100'],
            'genre' => ['nullable', 'string', 'max:100'],
            'sub_genre' => ['nullable', 'string', 'max:100'],
            'parental_advisory' => ['nullable', 'string', 'max:50'],
            'price_tier' => ['nullable', 'string', 'max:50'],
            'is_explicit' => ['nullable', 'boolean'],
            'is_instrumental' => ['nullable', 'boolean'],
            'contains_ai_generated_content' => ['nullable', 'boolean'],
            'preview_start_seconds' => ['nullable', 'integer', 'min:0'],
            'lyrics' => ['nullable', 'string'],
        ]);

        $changes = $this->diff($track, $validated);
        $validated['updated_by'] = $request->user()->id;

        $track->fill($validated);
        $track->save();

        if ($changes) {
            $this->audit('release.track_edited_in_review', 'Track "'.$track->title.'" (#'.$track->id.') of release #'.$release->id.' edited in review: '.implode(', ', array_keys($changes)).'.', array_map(fn ($c) => $c[0], $changes), array_map(fn ($c) => $c[1], $changes), $release, $request);
        }

        return back()->with(
            'success',
            $changes ? 'Track '.($track->track_number ?: '').' saved: '.implode(', ', array_map(fn ($k) => str_replace('_', ' ', $k), array_keys($changes))).'.' : 'Nothing changed.'
        );
    }


    public function approve(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews
    ): RedirectResponse {
        $validated = $request->validate([
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $next = $request->boolean('next') ? $this->nextInQueue($request, $release) : null;

        $release = $reviews->approve(
            $release,
            $request->user(),
            $validated['remarks'] ?? null
        );

        return ($next ? redirect('/admin/release-reviews/'.$next['id']) : redirect()->route('v2.admin.release-reviews.index'))
            ->with(
                'success',
                $next ? '"'.$release->title.'" approved. Next in the queue: "'.$next['title'].'".' : 'Release approved successfully.'
            );
    }

    public function reject(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:5000',
            ],
        ]);

        $release = $reviews->reject(
            $release,
            $request->user(),
            $validated['reason']
        );

        return redirect()
            ->route('v2.admin.release-reviews.index')
            ->with(
                'success',
                'Release rejected successfully.'
            );
    }

    public function requestChanges(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews
    ): RedirectResponse {
        $validated = $request->validate([
            'notes' => [
                'required',
                'string',
                'min:3',
                'max:5000',
            ],
        ]);

        $release = $reviews->requestChanges(
            $release,
            $request->user(),
            $validated['notes']
        );

        return redirect()
            ->route('v2.admin.release-reviews.index')
            ->with(
                'success',
                'Changes requested successfully.'
            );
    }

    public function startProcessing(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews
    ): RedirectResponse {
        $validated = $request->validate([
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $release = $reviews->startProcessing(
            $release,
            $request->user(),
            $validated['remarks'] ?? null
        );

        /* W63: go straight to the delivery page, where stores are started */
        return redirect('/admin/distribution/'.$release->id)
            ->with(
                'success',
                'Release processing started.'
            );
    }

    /** Next submitted release (oldest first) this reviewer can see, other than $current. */
    private function nextInQueue(Request $request, Release $current): ?array
    {
        $q = Release::query()
            ->whereNull('deleted_at')
            ->where('status', 'submitted')
            ->whereKeyNot($current->id);

        if (app(PermissionService::class)->role($request->user()) === 'admin') {
            $assign = app(AdminAssignmentService::class);
            $artistIds = $assign->artistIds($request->user());
            $labelIds = $assign->labelIds($request->user());

            if ($artistIds->isEmpty() && $labelIds->isEmpty()) {
                return null;
            }

            $q->where(function ($b) use ($artistIds, $labelIds) {
                $b->whereIn('artist_id', $artistIds->all() ?: [0])->orWhereIn('label_id', $labelIds->all() ?: [0]);
            });
        }

        $next = $q->orderBy('submitted_at')->orderBy('id')->first(['id', 'title']);

        return $next ? ['id' => (int) $next->id, 'title' => (string) $next->title] : null;
    }

    /** Fields that really change: [field => [old, new]]. */
    private function diff($model, array $values): array
    {
        $out = [];
        foreach ($values as $key => $new) {
            $old = $model->getAttribute($key);
            $oldCmp = $old instanceof \DateTimeInterface ? $old->format('Y-m-d') : (is_bool($old) ? (int) $old : (string) ($old ?? ''));
            $newCmp = is_bool($new) ? (int) $new : (string) ($new ?? '');
            if ($old instanceof \DateTimeInterface && $newCmp !== '') {
                $newCmp = substr($newCmp, 0, 10);
            }
            if ($oldCmp !== $newCmp) {
                $out[$key] = [$old instanceof \DateTimeInterface ? $old->format('Y-m-d') : $old, $new];
            }
        }

        return $out;
    }

    private function audit(string $action, string $description, array $old, array $new, Release $release, Request $request): void
    {
        try {
            app(\App\Services\V2\AuditLogService::class)->record($action, 'releases', $description, $old, $new, $release, $request);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
