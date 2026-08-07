<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Distribution\Release;
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
                    'submitted'
                )
            ),

            'sort' => trim(
                (string) $request->input(
                    'sort',
                    'oldest'
                )
            ),
        ];

        $query = Release::query()
            ->whereNull('deleted_at')
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
                        );
                }
            );
        }

        match ($filters['sort']) {
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

        $counts = [
                'submitted' =>
                    (clone $countsQuery)
                        ->where(
                            'status',
                            'submitted'
                        )
                        ->count(),

                'approved' =>
                    (clone $countsQuery)
                        ->where(
                            'status',
                            'approved'
                        )
                        ->count(),

                'changes_requested' =>
                    (clone $countsQuery)
                        ->where(
                            'status',
                            'changes_requested'
                        )
                        ->count(),

                'rejected' =>
                    (clone $countsQuery)
                        ->where(
                            'status',
                            'rejected'
                        )
                        ->count(),

                
'processing' =>
                    (clone $countsQuery)
                        ->where(
                            'status',
                            'processing'
                        )
                        ->count(),
        ];


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
            'tracks' => function ($query) {
                $query
                    ->orderBy('disc_number')
                    ->orderBy('track_number')
                    ->orderBy('id');
            },
        ]);

        $statusLogs = [];

        if (
            Schema::hasTable(
                'release_status_logs'
            )
        ) {
            $statusLogs = DB::table(
                'release_status_logs'
            )
                ->where(
                    'release_id',
                    $release->id
                )
                ->orderByDesc('id')
                ->limit(100)
                ->get();
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
            ]
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

        $release = $reviews->approve(
            $release,
            $request->user(),
            $validated['remarks'] ?? null
        );

        return redirect()
            ->route('v2.admin.release-reviews.index')
            ->with(
                'success',
                'Release approved successfully.'
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

        return redirect()
            ->route('v2.admin.release-reviews.index')
            ->with(
                'success',
                'Release processing started.'
            );
    }
}
