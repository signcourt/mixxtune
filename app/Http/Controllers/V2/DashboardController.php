<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        PermissionService $permissionService,
        ReportAnalyticsService $analyticsService
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
            $label = DB::table('labels')
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->first();

            if ($label) {
                $query->where('label_id', $label->id);
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

        if ($role === 'label' && isset($label) && $label) {
            $activeArtists = DB::table('artists')
                ->where('label_id', $label->id)
                ->whereNull('deleted_at')
                ->where('account_status', 'active')
                ->count();
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
            'label', 'artist' => [
                ['label' => 'Create Release', 'href' => '/v2/releases/create'],
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
                'walletBalance' => '₹0.00',
                'activeArtists' => $activeArtists,
            ],
            'analytics' => $analytics,
            'recentReleases' => $recentReleases,
            'quickActions' => $quickActions,
        ]);
    }
}
