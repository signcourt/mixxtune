<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        ReportAnalyticsService $analytics
    ): Response {
        $permissions->authorize(
            $request->user(),
            'reports.view'
        );

        $filters = [
            'search' => trim(
                (string) $request->input(
                    'search',
                    ''
                )
            ),

            'month' => trim(
                (string) $request->input(
                    'month',
                    ''
                )
            ),

            'platform' => trim(
                (string) $request->input(
                    'platform',
                    ''
                )
            ),

            'country' => trim(
                (string) $request->input(
                    'country',
                    ''
                )
            ),

            'isrc' => trim(
                (string) $request->input(
                    'isrc',
                    ''
                )
            ),
        ];

        $base = $analytics->scopedQuery(
            $request->user(),
            $permissions
        );

        $query = $analytics->applyFilters(
            clone $base,
            $filters
        );

        $platforms = (clone $base)
            ->whereNotNull('platform')
            ->distinct()
            ->orderBy('platform')
            ->pluck('platform');

        $months = (clone $base)
            ->whereNotNull('sale_month')
            ->distinct()
            ->orderByDesc('sale_month')
            ->pluck('sale_month');

        $countries = (clone $base)
            ->whereNotNull('country_code')
            ->distinct()
            ->orderBy('country_code')
            ->pluck('country_code');

        return Inertia::render(
            'V2/Reports/Index',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'filters' =>
                    $filters,

                'summary' =>
                    $analytics->summary(
                        clone $query
                    ),

                'monthlyTrend' =>
                    $analytics->monthlyTrend(
                        clone $query
                    ),

                'topPlatforms' =>
                    $analytics->topPlatforms(
                        clone $query
                    ),

                'topCountries' =>
                    $analytics->topCountries(
                        clone $query
                    ),

                'topTracks' =>
                    $analytics->topTracks(
                        clone $query
                    ),

                'currencySummary' =>
                    $analytics->currencySummary(
                        clone $query
                    ),

                'platforms' =>
                    $platforms,

                'months' =>
                    $months,

                'countries' =>
                    $countries,

                'rows' =>
                    $query
                        ->orderByDesc('sale_date')
                        ->orderByDesc('id')
                        ->paginate(50)
                        ->withQueryString(),
            ]
        );
    }
}
