<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Core\Label;
use App\Services\V2\MasterRevenueVisibilityService;
use App\Services\V2\PermissionService;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use App\Services\V2\ReportAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        PermissionService $permissionService,
        ReportAnalyticsService $analyticsService,
        MasterRevenueVisibilityService $revenueVisibility,
        LabelTeamAccessService $teamAccess
    ) {
        $user = $request->user();
        $role = $user->role ?? 'artist';

        $query = DB::table('releases')
            ->whereNull('deleted_at');

        if ($role === 'artist') {
            $artist = DB::table('artists')
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->first();

            if ($artist) {
                $query->where('artist_id', $artist->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($role === 'label') {
            /*
             * Label dashboard visibility must use the same
             * two-tier/team scope rules as catalogue/releases.
             *
             * Owner:
             *   master + direct child catalogue
             *
             * Team user:
             *   entire-label or explicitly selected scope
             */
            $labelModel = $teamAccess->effectiveLabel($user);

            $label = $labelModel
                ? (object) [
                    'id' => $labelModel->id,
                    'user_id' => $labelModel->user_id,
                ]
                : null;

            if ($labelModel) {
                abort_unless(
                    $teamAccess->allows(
                        $user,
                        'dashboard.view'
                    ),
                    403
                );

                $accessibleLabelIds =
                    $teamAccess
                        ->accessibleLabelIds($user)
                        ->map(fn ($id) => (int) $id)
                        ->values();

                $accessibleArtistIds =
                    $teamAccess
                        ->accessibleArtistIds($user)
                        ->map(fn ($id) => (int) $id)
                        ->values();

                if (
                    $accessibleLabelIds->isEmpty()
                    && $accessibleArtistIds->isEmpty()
                ) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where(function ($scope) use (
                        $accessibleLabelIds,
                        $accessibleArtistIds
                    ) {
                        if ($accessibleLabelIds->isNotEmpty()) {
                            $scope->whereIn(
                                'label_id',
                                $accessibleLabelIds
                            );
                        }

                        if ($accessibleArtistIds->isNotEmpty()) {
                            $method =
                                $accessibleLabelIds->isNotEmpty()
                                    ? 'orWhereIn'
                                    : 'whereIn';

                            $scope->{$method}(
                                'artist_id',
                                $accessibleArtistIds
                            );
                        }
                    });
                }
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $totalReleases = (clone $query)->count();
        $submittedReleases = (clone $query)
            ->where('status', 'submitted')
            ->count();
        $approvedReleases = (clone $query)
            ->where('status', 'approved')
            ->count();

        $recentReleases = (clone $query)
            ->orderByDesc('id')
            ->limit(8)
            ->get([
                'id',
                'title',
                'primary_artist_name',
                'status',
                'created_at',
            ]);

        $analyticsQuery = $analyticsService->scopedQuery(
            $user,
            $permissionService
        );

        $analyticsSummary = $analyticsService->summary(
            clone $analyticsQuery
        );

        $analytics = [
            'summary' => $analyticsSummary,

            'monthly' => $analyticsService->monthlyTrend(
                clone $analyticsQuery,
                12
            ),

            'topTracks' => $analyticsService->topTracks(
                clone $analyticsQuery,
                8
            ),

            'topPlatforms' => $analyticsService->topPlatforms(
                clone $analyticsQuery,
                6
            ),

            'topCountries' => $analyticsService->topCountries(
                clone $analyticsQuery,
                6
            ),

            'currencies' => $analyticsService->currencySummary(
                clone $analyticsQuery
            ),

            'hasData' =>
                (float) ($analyticsSummary['streams'] ?? 0) > 0
                || (float) ($analyticsSummary['earnings'] ?? 0) != 0
                || (int) ($analyticsSummary['rows'] ?? 0) > 0,
        ];

        $activeArtists = 0;

        $revenueSummary = null;

        if (
            $role === 'label'
            && isset($label)
            && $label
        ) {
            $labelModel =
                $role === 'label'
                    ? $teamAccess->effectiveLabel($user)
                    : Label::query()->find($label->id);

            if ($labelModel) {
                $revenueSummary =
                    $revenueVisibility
                        ->summary(
                            $labelModel
                        );
            }
        }

        if (
            $role === 'artist'
            && isset($artist)
            && $artist
        ) {
            $revenueSummary =
                $revenueVisibility
                    ->artistSummary(
                        (int) $artist->id
                    );
        }

        if ($role === 'label' && isset($label) && $label) {
            $dashboardArtistIds =
                $teamAccess
                    ->accessibleArtistIds($user)
                    ->map(fn ($id) => (int) $id)
                    ->values();

            if ($dashboardArtistIds->isNotEmpty()) {
                $activeArtists = DB::table('artists')
                    ->whereIn('id', $dashboardArtistIds)
                    ->whereNull('deleted_at')
                    ->where('account_status', 'active')
                    ->count();
            }
        }

        /*
         * Artist finance summary.
         *
         * The V2 dashboard must use the same wallet ledger that powers
         * royalties and withdrawals. Never calculate artist earnings
         * independently here.
         */
        $walletBalance = 0.0;
        $pendingBalance = 0.0;
        $pendingWithdrawals = 0;
        $walletCurrency = 'INR';

        if (
            $role === 'artist'
            && isset($artist)
            && $artist
        ) {
            $wallet = DB::table('wallets')
                ->where('artist_id', $artist->id)
                ->first();

            if ($wallet) {
                $walletBalance = (float) (
                    $wallet->available_balance ?? 0
                );

                $pendingBalance = (float) (
                    $wallet->pending_balance ?? 0
                );

                $walletCurrency =
                    $wallet->currency
                    ?? 'INR';
            }

            $pendingWithdrawals = DB::table('withdrawals')
                ->where('artist_id', $artist->id)
                ->whereIn(
                    'status',
                    [
                        'pending',
                        'approved',
                        'processing',
                    ]
                )
                ->count();
        }

        $recentTransactions = [];
        $recentWithdrawals = [];

        if (
            $role === 'artist'
            && isset($artist)
            && $artist
        ) {
            $recentTransactions = DB::table(
                'wallet_transactions'
            )
                ->where('artist_id', $artist->id)
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'type' =>
                        $transaction->type ?? null,
                    'amount' =>
                        (float) ($transaction->amount ?? 0),
                    'currency' =>
                        $transaction->currency
                        ?? $walletCurrency,
                    'description' =>
                        $transaction->description
                        ?? null,
                    'created_at' =>
                        $transaction->created_at,
                ])
                ->values()
                ->all();

            $recentWithdrawals = DB::table(
                'withdrawals'
            )
                ->where('artist_id', $artist->id)
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn ($withdrawal) => [
                    'id' => $withdrawal->id,
                    'amount' =>
                        (float) ($withdrawal->amount ?? 0),
                    'currency' =>
                        $withdrawal->currency
                        ?? $walletCurrency,
                    'status' =>
                        $withdrawal->status ?? null,
                    'created_at' =>
                        $withdrawal->created_at,
                ])
                ->values()
                ->all();
        }

        $panelName = match ($role) {
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'label' => 'Label',
            default => 'Artist',
        };

        $quickActions = match ($role) {
            'super_admin', 'admin' => [
                ['label' => 'Review Releases', 'href' => '/release-reviews'],
                ['label' => 'Create Release', 'href' => '/v2/releases/create'],
            ],
            'label' => [
                [
                    'label' => 'Create Release',
                    'href' => '/label/releases',
                ],
            ],

            'artist' => [
                [
                    'label' => 'Create Release',
                    'href' => '/artist/releases/create',
                ],
                [
                    'label' => 'Wallet',
                    'href' => '/artist/wallet',
                ],
                [
                    'label' => 'Royalties',
                    'href' => '/artist/royalties',
                ],
                [
                    'label' => 'Statements',
                    'href' => '/artist/statements',
                ],
                [
                    'label' => 'Withdraw',
                    'href' => '/artist/withdrawals',
                ],
                [
                    'label' => 'Reports',
                    'href' => '/artist/reports',
                ],
            ],
            default => [],
        };

        return Inertia::render('V2/Dashboard', [
            'role' => $role,
            'permissions' =>
                $permissionService->permissions($user),
            'panelName' => $panelName,
            'stats' => [
                'totalReleases' => $totalReleases,
                'submittedReleases' => $submittedReleases,
                'approvedReleases' => $approvedReleases,
                'walletBalance' =>
                    $walletCurrency === 'INR'
                        ? '₹'.number_format(
                            $walletBalance,
                            2
                        )
                        : $walletCurrency.' '.number_format(
                            $walletBalance,
                            2
                        ),

                'walletAvailableBalance' =>
                    $walletBalance,

                'walletPendingBalance' =>
                    $pendingBalance,

                'pendingWithdrawals' =>
                    $pendingWithdrawals,

                'walletCurrency' =>
                    $walletCurrency,

                'activeArtists' => $activeArtists,
            ],
            'analytics' => $analytics,
            'revenueSummary' => $revenueSummary,
            'recentReleases' => $recentReleases,
            'recentTransactions' => $recentTransactions,
            'recentWithdrawals' => $recentWithdrawals,
            'quickActions' => $quickActions,
        ]);
    }
}
