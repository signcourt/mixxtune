<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\Admin\SuperAdminProfitService;
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
        ReportAnalyticsService $analytics,
        SuperAdminProfitService $profit
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

        /*
         * Super Admin dashboard business profit.
         *
         * This uses the same canonical calculation as
         * the Business Profit screen:
         *
         * collected revenue - user earning
         * = retained Mixx Tune profit.
         *
         * Admin users continue to use scoped reported
         * earnings and never receive platform-wide profit.
         */
        $profitSummary = null;
        $revenueTrend = [];

        if ($role === 'super_admin') {
            [$profitSummary, $revenueTrend] =
                $this->profitWithTrend($profit);
        }

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
                    'label_id',
                ])
                ->with('label:id,name')
                ->orderByDesc('id')
                ->limit(8)
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
                ->leftJoin(
                    'users as requester',
                    'requester.id',
                    '=',
                    'withdrawals.user_id'
                )
                ->select([
                    'withdrawals.id',
                    'withdrawals.withdrawal_number',
                    'withdrawals.amount',
                    'withdrawals.currency',
                    'withdrawals.status',
                    'withdrawals.requested_at',
                    'withdrawals.created_at',
                    'requester.name as requester_name',
                ])
                ->orderByDesc('withdrawals.id')
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

        /*
         * W69 MIXXTUNE_DASHBOARD_V2: one grouped query for release statuses,
         * one for deliveries, one for withdrawals (count + amount) instead of
         * a separate COUNT per status.
         */
        $statusCounts = (clone $releaseQuery)
            ->toBase()
            ->reorder()
            ->select('status')
            ->selectRaw('COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn ($c) => (int) $c)
            ->all();

        $releaseCount = fn (string $status): int => (int) ($statusCounts[$status] ?? 0);

        $deliveryCounts = (clone $deliveryQuery)
            ->select('deliveries.status')
            ->selectRaw('COUNT(*) as c')
            ->groupBy('deliveries.status')
            ->pluck('c', 'deliveries.status')
            ->map(fn ($c) => (int) $c)
            ->all();

        $withdrawalCounts = ['pending' => ['count' => 0, 'amount' => 0.0], 'to_pay' => ['count' => 0, 'amount' => 0.0]];

        foreach (
            (clone $withdrawalQuery)
                ->whereIn('withdrawals.status', ['pending', 'approved', 'processing'])
                ->select('withdrawals.status')
                ->selectRaw('COUNT(*) as c')
                ->selectRaw('COALESCE(SUM(withdrawals.amount), 0) as a')
                ->groupBy('withdrawals.status')
                ->get()
            as $row
        ) {
            $key = $row->status === 'pending' ? 'pending' : 'to_pay';
            $withdrawalCounts[$key]['count'] += (int) $row->c;
            $withdrawalCounts[$key]['amount'] += (float) $row->a;
        }

        $supportCounts = (clone $supportQuery)
            ->whereIn('status', ['open', 'customer_reply', 'waiting'])
            ->select('status')
            ->selectRaw('COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn ($c) => (int) $c)
            ->all();

        $oldestSubmittedAt = (clone $releaseQuery)
            ->where('status', 'submitted')
            ->min('submitted_at');

        $oldestWaitingDays = $oldestSubmittedAt
            ? max(0, (int) \Illuminate\Support\Carbon::parse($oldestSubmittedAt)->startOfDay()->diffInDays(now()->startOfDay()))
            : null;

        $monthly = $analytics->monthlyTrend(
            clone $analyticsQuery,
            12,
            true
        );

        $growth = $analytics->growthSummary($monthly);

        $reportedTracks = (int) (clone $analyticsQuery)
            ->toBase()
            ->reorder()
            ->whereNotNull('isrc')
            ->where('isrc', '!=', '')
            ->distinct()
            ->count('isrc');

        if ($role !== 'super_admin') {
            $revenueTrend = collect($monthly)
                ->map(fn ($row) => [
                    'month' => $row['month'],
                    'collected_revenue' => (float) $row['collected_revenue'],
                    'user_earning' => (float) $row['earnings'],
                    'profit' => null,
                ])
                ->values()
                ->all();
        }

        $setup = $role === 'super_admin'
            ? $this->setupChecks()
            : null;

        return Inertia::render(
            'V2/Admin/Dashboard',
            [
                'role' => $role,

                'stats' => [
                    'total_releases' => array_sum($statusCounts),
                    'artists' => (clone $artistsQuery)->count(),
                    'labels' => (clone $labelsQuery)->count(),
                    'tracks' => (clone $tracksQuery)->count(),
                    'draft' => $releaseCount('draft'),
                    'submitted' => $releaseCount('submitted'),
                    'changes_requested' => $releaseCount('changes_requested'),
                    'approved' => $releaseCount('approved'),
                    'processing' => $releaseCount('processing'),
                    'delivered' => $releaseCount('delivered'),
                    'live' => $releaseCount('live'),
                    'rejected' => $releaseCount('rejected'),
                    'oldest_waiting_days' => $oldestWaitingDays,
                    'failed_deliveries' => (int) ($deliveryCounts['failed'] ?? 0),
                    'processing_deliveries' => (int) ($deliveryCounts['processing'] ?? 0),
                    'pending_deliveries' => (int) ($deliveryCounts['pending'] ?? 0),
                    'live_deliveries' => (int) ($deliveryCounts['live'] ?? 0),
                    'pending_withdrawals' => $withdrawalCounts['pending']['count'],
                    'pending_withdrawals_amount' => round($withdrawalCounts['pending']['amount'], 2),
                    'withdrawals_to_pay' => $withdrawalCounts['to_pay']['count'],
                    'withdrawals_to_pay_amount' => round($withdrawalCounts['to_pay']['amount'], 2),
                    'open_tickets' => array_sum($supportCounts),
                    'tickets_need_reply' => (int) ($supportCounts['open'] ?? 0) + (int) ($supportCounts['customer_reply'] ?? 0),
                    'report_imports' => DB::table('report_imports')->count(),
                    'streams' => (float) ($analyticsSummary['streams'] ?? 0),
                    'earnings' => (float) ($analyticsSummary['earnings'] ?? 0),
                    'collected_revenue' => (float) ($analyticsSummary['collected_revenue'] ?? 0),
                    'active_platforms' => (int) ($analyticsSummary['active_platforms'] ?? 0),
                    'reported_tracks' => $reportedTracks,
                    'profit_earnings' => $role === 'super_admin'
                        ? (float) ($profitSummary['super_admin_profit'] ?? 0)
                        : null,
                    'profit_collected' => $role === 'super_admin'
                        ? (float) ($profitSummary['collected_revenue'] ?? 0)
                        : null,
                    'profit_user_earning' => $role === 'super_admin'
                        ? (float) ($profitSummary['user_earning'] ?? 0)
                        : null,
                    /* W92: profit of reports still in review - shown apart, not counted */
                    'profit_in_review' => $role === 'super_admin'
                        ? (float) ($profit->summary($profit->withStage($profit->baseQuery(), 'review'))['super_admin_profit'] ?? 0)
                        : null,
                    'sale_units' => (float) ($analyticsSummary['sale_units'] ?? 0),
                ],

                'setup' => $setup,

                /* W94 MIXXTUNE_ACCOUNT_AGREEMENTS: agreements ending within 90 days (or ended, not renewed) */
                'agreements' => $role === 'super_admin' ? (function () use ($request) {
                    $svc = app(\App\Services\V2\AccountAgreements::class);
                    $due = $svc->due();

                    return ['due' => $due, 'popup' => $due !== [] && $svc->popupDue($request->user())];
                })() : null,

                'growth' => [
                    'month' => $growth['current_month'] ?? null,
                    'previous_month' => $growth['previous_month'] ?? null,
                    'streams_percent' => $growth['streams_percent'] ?? null,
                    'earnings_percent' => $growth['earnings_percent'] ?? null,
                ],

                'revenueTrend' => array_slice($revenueTrend, -12),

                'analytics' => [
                    'monthly' => $monthly,
                    'topPlatforms' => $analytics->topPlatforms(clone $analyticsQuery, 6),
                    'topCountries' => $analytics->topCountries(clone $analyticsQuery, 6),
                    'topTracks' => $analytics->topTracks(clone $analyticsQuery, 6),
                ],

                'recentReleases' => $recentReleases->map(fn ($release) => [
                    'id' => $release->id,
                    'title' => $release->title,
                    'primary_artist_name' => $release->primary_artist_name,
                    'label_name' => $release->label?->name,
                    'status' => $release->status,
                    'upc' => $release->upc,
                    'digital_release_date' => optional($release->digital_release_date)->toDateString(),
                    'created_at' => optional($release->created_at)->toIso8601String(),
                    'submitted_at' => optional($release->submitted_at)->toIso8601String(),
                ])->values(),

                'recentImports' => $recentImports,

                'recentWithdrawals' => $recentWithdrawals,
            ]
        );
    }

    /**
     * Super Admin: retained profit (same maths as summary()) plus a
     * month-wise trend, in ONE pass over the report rows.
     */
    private function profitWithTrend(SuperAdminProfitService $profit): array
    {
        $summary = [
            'collected_revenue' => 0.0,
            'user_earning' => 0.0,
            'super_admin_profit' => 0.0,
        ];
        $months = [];

        /* W92: profit counts published reports only (what owners already see in their wallets) */
        foreach ($profit->withStage($profit->baseQuery(), 'published')->orderBy('id')->cursor() as $row) {
            $calc = $profit->calculateRow($row);

            foreach (array_keys($summary) as $field) {
                $summary[$field] += $calc[$field];
            }

            $month = $profit->effectiveMonth($row);

            if ($month === '') {
                continue;
            }

            $months[$month] ??= ['month' => $month, 'collected_revenue' => 0.0, 'user_earning' => 0.0, 'profit' => 0.0];
            $months[$month]['collected_revenue'] += $calc['collected_revenue'];
            $months[$month]['user_earning'] += $calc['user_earning'];
            $months[$month]['profit'] += $calc['super_admin_profit'];
        }

        foreach ($summary as $field => $value) {
            $summary[$field] = round($value, 8);
        }

        ksort($months);

        $trend = array_map(fn ($m) => [
            'month' => $m['month'],
            'collected_revenue' => round($m['collected_revenue'], 2),
            'user_earning' => round($m['user_earning'], 2),
            'profit' => round($m['profit'], 2),
        ], array_values($months));

        return [$summary, $trend];
    }

    /**
     * Super Admin: things that are not set up yet. Counts only - nothing
     * is guessed or filled in from here.
     */
    private function setupChecks(): array
    {
        $today = now()->toDateString();

        $subWithoutShare = DB::table('labels')
            ->whereNull('labels.deleted_at')
            ->whereNotNull('labels.parent_label_id')
            ->whereNotExists(function ($q) use ($today) {
                $q->selectRaw('1')
                    ->from('label_revenue_shares as s')
                    ->whereColumn('s.master_label_id', 'labels.parent_label_id')
                    ->whereColumn('s.beneficiary_id', 'labels.id')
                    ->where('s.beneficiary_type', 'label')
                    ->where('s.is_active', true)
                    ->where(fn ($w) => $w->whereNull('s.effective_from')->orWhereDate('s.effective_from', '<=', $today))
                    ->where(fn ($w) => $w->whereNull('s.effective_to')->orWhereDate('s.effective_to', '>=', $today));
            })
            ->count();

        $mastersWithoutLogin = DB::table('labels')
            ->whereNull('deleted_at')
            ->whereNull('parent_label_id')
            ->whereNull('user_id')
            ->count();

        $unmapped = DB::table('report_rows')
            ->whereNull('label_id')
            ->whereNotNull('isrc')
            ->where('isrc', '!=', '')
            ->selectRaw('COUNT(*) as c')
            ->selectRaw('COALESCE(SUM(collected_revenue), 0) as r')
            ->first();

        $kycToReview = \Illuminate\Support\Facades\Schema::hasTable('payout_profiles')
            ? DB::table('payout_profiles')->where('kyc_status', 'submitted')->count()
            : 0;

        return [
            'sub_labels_without_share' => (int) $subWithoutShare,
            'masters_without_login' => (int) $mastersWithoutLogin,
            'unmapped_rows' => (int) ($unmapped->c ?? 0),
            'unmapped_revenue' => round((float) ($unmapped->r ?? 0), 2),
            'kyc_to_review' => (int) $kycToReview,
        ];
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
                        'withdrawals.artist_id',
                        $artistIds
                    );
                }

                if ($labelIds->isNotEmpty()) {
                    if ($artistIds->isNotEmpty()) {
                        $builder->orWhereIn(
                            'withdrawals.label_id',
                            $labelIds
                        );
                    } else {
                        $builder->whereIn(
                            'withdrawals.label_id',
                            $labelIds
                        );
                    }
                }
            }
        );
    }
}
