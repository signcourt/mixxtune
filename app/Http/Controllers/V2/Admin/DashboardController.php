<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\AdminAssignmentService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        AdminAssignmentService $assignments,
        ReportAnalyticsService $analytics
    ): Response {
        $user = $request->user();

        abort_unless(
            $user,
            401,
            'Authentication required.'
        );

        $role = $permissions->role($user);

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );

        $artistIds =
            $assignments->artistIds($user);

        $labelIds =
            $assignments->labelIds($user);

        $releaseQuery = Release::query()
            ->whereNull('deleted_at');

        $this->scopeReleaseQuery(
            $releaseQuery,
            $role,
            $artistIds,
            $labelIds
        );

        $analyticsQuery =
            $analytics->scopedQuery(
                $user,
                $permissions
            );

        $analyticsSummary =
            $analytics->summary(
                clone $analyticsQuery
            );

        $recentReleases =
            (clone $releaseQuery)
                ->select([
                    'id',
                    'title',
                    'primary_artist_name',
                    'status',
                    'upc',
                    'digital_release_date',
                    'created_at',
                    'submitted_at',
                ])
                ->orderByDesc('id')
                ->limit(10)
                ->get();

        $artistsQuery =
            DB::table('artists')
                ->whereNull('deleted_at');

        $labelsQuery =
            DB::table('labels')
                ->whereNull('deleted_at');

        $tracksQuery =
            DB::table('tracks')
                ->join(
                    'releases',
                    'releases.id',
                    '=',
                    'tracks.release_id'
                )
                ->whereNull(
                    'releases.deleted_at'
                );

        if ($role === 'admin') {
            if (
                $artistIds->isEmpty()
                && $labelIds->isEmpty()
            ) {
                $artistsQuery->whereRaw('1 = 0');
                $labelsQuery->whereRaw('1 = 0');
                $tracksQuery->whereRaw('1 = 0');
            } else {
                if ($artistIds->isNotEmpty()) {
                    $artistsQuery->whereIn(
                        'artists.id',
                        $artistIds
                    );
                } else {
                    $artistsQuery->whereRaw('1 = 0');
                }

                if ($labelIds->isNotEmpty()) {
                    $labelsQuery->whereIn(
                        'labels.id',
                        $labelIds
                    );
                } else {
                    $labelsQuery->whereRaw('1 = 0');
                }

                $tracksQuery->where(
                    function ($builder) use (
                        $artistIds,
                        $labelIds
                    ) {
                        if (
                            $artistIds->isNotEmpty()
                        ) {
                            $builder->whereIn(
                                'releases.artist_id',
                                $artistIds
                            );
                        }

                        if (
                            $labelIds->isNotEmpty()
                        ) {
                            if (
                                $artistIds->isNotEmpty()
                            ) {
                                $builder->orWhereIn(
                                    'releases.label_id',
                                    $labelIds
                                );
                            } else {
                                $builder->whereIn(
                                    'releases.label_id',
                                    $labelIds
                                );
                            }
                        }
                    }
                );
            }
        }

        $deliveryQuery =
            DB::table(
                'release_store_deliveries as deliveries'
            )
                ->join(
                    'releases',
                    'releases.id',
                    '=',
                    'deliveries.release_id'
                )
                ->whereNull(
                    'releases.deleted_at'
                );

        if ($role === 'admin') {
            $this->scopeJoinedReleaseQuery(
                $deliveryQuery,
                $artistIds,
                $labelIds
            );
        }

        $withdrawalQuery =
            DB::table('withdrawals');

        if ($role === 'admin') {
            $this->scopeOwnerQuery(
                $withdrawalQuery,
                $artistIds,
                $labelIds
            );
        }

        $recentWithdrawals =
            (clone $withdrawalQuery)
                ->select([
                    'id',
                    'withdrawal_number',
                    'amount',
                    'currency',
                    'status',
                    'requested_at',
                ])
                ->orderByDesc('id')
                ->limit(5)
                ->get();

        $recentImports =
            DB::table('report_imports')
                ->select([
                    'id',
                    'original_filename',
                    'status',
                    'total_rows',
                    'imported_rows',
                    'failed_rows',
                    'created_at',
                ])
                ->orderByDesc('id')
                ->limit(5)
                ->get();

        $supportQuery =
            DB::table('support_tickets');

        if ($role === 'admin') {
            $supportQuery->where(
                function ($builder) use ($user) {
                    $builder
                        ->where(
                            'assigned_admin_id',
                            $user->id
                        )
                        ->orWhereNull(
                            'assigned_admin_id'
                        );
                }
            );
        }

        return Inertia::render(
            'V2/Admin/Dashboard',
            [
                'role' => $role,

                'stats' => [
                    'total_releases' =>
                        (clone $releaseQuery)
                            ->count(),

                    'artists' =>
                        (clone $artistsQuery)
                            ->count(),

                    'labels' =>
                        (clone $labelsQuery)
                            ->count(),

                    'tracks' =>
                        (clone $tracksQuery)
                            ->count(),

                    'draft' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'draft'
                            )
                            ->count(),

                    'submitted' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'submitted'
                            )
                            ->count(),

                    'approved' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'approved'
                            )
                            ->count(),

                    'processing' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'processing'
                            )
                            ->count(),

                    'delivered' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'delivered'
                            )
                            ->count(),

                    'live' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'live'
                            )
                            ->count(),

                    'rejected' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'rejected'
                            )
                            ->count(),

                    'failed_deliveries' =>
                        (clone $deliveryQuery)
                            ->where(
                                'deliveries.status',
                                'failed'
                            )
                            ->count(),

                    'processing_deliveries' =>
                        (clone $deliveryQuery)
                            ->where(
                                'deliveries.status',
                                'processing'
                            )
                            ->count(),

                    'live_deliveries' =>
                        (clone $deliveryQuery)
                            ->where(
                                'deliveries.status',
                                'live'
                            )
                            ->count(),

                    'pending_withdrawals' =>
                        (clone $withdrawalQuery)
                            ->where(
                                'status',
                                'pending'
                            )
                            ->count(),

                    'open_tickets' =>
                        (clone $supportQuery)
                            ->whereIn(
                                'status',
                                [
                                    'open',
                                    'customer_reply',
                                    'waiting',
                                ]
                            )
                            ->count(),

                    'report_imports' =>
                        DB::table(
                            'report_imports'
                        )->count(),

                    'streams' =>
                        (float) (
                            $analyticsSummary[
                                'streams'
                            ] ?? 0
                        ),

                    'earnings' =>
                        (float) (
                            $analyticsSummary[
                                'earnings'
                            ] ?? 0
                        ),

                    'sale_units' =>
                        (float) (
                            $analyticsSummary[
                                'sale_units'
                            ] ?? 0
                        ),
                ],

                'analytics' => [
                    'monthly' =>
                        $analytics->monthlyTrend(
                            clone $analyticsQuery,
                            12
                        ),

                    'topPlatforms' =>
                        $analytics->topPlatforms(
                            clone $analyticsQuery,
                            6
                        ),

                    'topCountries' =>
                        $analytics->topCountries(
                            clone $analyticsQuery,
                            6
                        ),

                    'topTracks' =>
                        $analytics->topTracks(
                            clone $analyticsQuery,
                            6
                        ),
                ],

                'recentReleases' =>
                    $recentReleases,

                'recentImports' =>
                    $recentImports,

                'recentWithdrawals' =>
                    $recentWithdrawals,
            ]
        );
    }

    private function scopeReleaseQuery(
        $query,
        string $role,
        $artistIds,
        $labelIds
    ): void {
        if ($role !== 'admin') {
            return;
        }

        if (
            $artistIds->isEmpty()
            && $labelIds->isEmpty()
        ) {
            $query->whereRaw('1 = 0');

            return;
        }

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

    private function scopeJoinedReleaseQuery(
        $query,
        $artistIds,
        $labelIds
    ): void {
        if (
            $artistIds->isEmpty()
            && $labelIds->isEmpty()
        ) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(
            function ($builder) use (
                $artistIds,
                $labelIds
            ) {
                if ($artistIds->isNotEmpty()) {
                    $builder->whereIn(
                        'releases.artist_id',
                        $artistIds
                    );
                }

                if ($labelIds->isNotEmpty()) {
                    if ($artistIds->isNotEmpty()) {
                        $builder->orWhereIn(
                            'releases.label_id',
                            $labelIds
                        );
                    } else {
                        $builder->whereIn(
                            'releases.label_id',
                            $labelIds
                        );
                    }
                }
            }
        );
    }

    private function scopeOwnerQuery(
        $query,
        $artistIds,
        $labelIds
    ): void {
        if (
            $artistIds->isEmpty()
            && $labelIds->isEmpty()
        ) {
            $query->whereRaw('1 = 0');

            return;
        }

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
