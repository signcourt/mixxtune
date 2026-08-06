<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Distribution\Release;
use App\Services\V2\AdminReleaseReviewService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseValidationService;
use Illuminate\Http\JsonResponse;
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
        PermissionService $permissions
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

        if (
            $role === 'admin'
            && Schema::hasColumn(
                'artists',
                'assigned_admin_id'
            )
        ) {
            $artistIds = Artist::query()
                ->where(
                    'assigned_admin_id',
                    $request->user()->id
                )
                ->pluck('id');

            $query->whereIn(
                'artist_id',
                $artistIds
            );
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

        if (
            $role === 'admin'
            && isset($artistIds)
        ) {
            $countsQuery->whereIn(
                'artist_id',
                $artistIds
            );
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
    ): JsonResponse {
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

        return response()->json([
            'message' =>
                'Release approved successfully.',

            'release' => $release,
        ]);
    }

    public function reject(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews
    ): JsonResponse {
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

        return response()->json([
            'message' =>
                'Release rejected successfully.',

            'release' => $release,
        ]);
    }

    public function requestChanges(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews
    ): JsonResponse {
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

        return response()->json([
            'message' =>
                'Changes requested successfully.',

            'release' => $release,
        ]);
    }

    public function startProcessing(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews
    ): JsonResponse {
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

        return response()->json([
            'message' =>
                'Release processing started.',

            'release' => $release,
        ]);
    }
}
