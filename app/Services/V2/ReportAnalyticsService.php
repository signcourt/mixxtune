<?php

namespace App\Services\V2;

use Illuminate\Support\Collection;
use App\Services\V2\LabelAccess\LabelTeamAccessService;

use App\Models\Reports\ReportRow;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportAnalyticsService
{
    public function __construct(
        private readonly AdminAssignmentService $assignments
    ) {
    }

    public function scopedQuery(
        User $user,
        PermissionService $permissions
    ): Builder {
        $role = $permissions->role(
            $user
        );

        $query =
            ReportRow::query();

        if ($role === 'super_admin') {
            return $query;
        }

        if ($role === 'artist') {
            $artistId = DB::table('artists')
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->value('id');

            return $artistId
                ? $query->where(
                    'artist_id',
                    $artistId
                )
                : $query->whereRaw('1 = 0');
        }

        if ($role === 'label') {
            $teamAccess = app(LabelTeamAccessService::class);

            $labelIds = $teamAccess
                ->accessibleLabelIds($user);

            $artistIds = $teamAccess
                ->accessibleArtistIds($user);

            if (
                $labelIds->isEmpty()
                && $artistIds->isEmpty()
            ) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($builder) use (
                    $labelIds,
                    $artistIds
                ) {
                    if ($labelIds->isNotEmpty()) {
                        $builder->whereIn(
                            'label_id',
                            $labelIds
                        );

                        /*
                         * Legacy financial-only rows may
                         * have label_id = NULL while still
                         * carrying authoritative mapped
                         * ownership in revenue_owner_id.
                         *
                         * Include only authorized label
                         * owners so this can never broaden
                         * account visibility.
                         */
                        $builder->orWhere(
                            function ($legacy) use (
                                $labelIds
                            ) {
                                $legacy
                                    ->where(
                                        'mapping_status',
                                        'mapped'
                                    )
                                    ->where(
                                        'revenue_owner_type',
                                        'label'
                                    )
                                    ->whereIn(
                                        'revenue_owner_id',
                                        $labelIds
                                    );
                            }
                        );
                    }

                    if ($artistIds->isNotEmpty()) {
                        if ($labelIds->isNotEmpty()) {
                            $builder->orWhereIn(
                                'artist_id',
                                $artistIds
                            );
                        } else {
                            $builder->whereIn(
                                'artist_id',
                                $artistIds
                            );
                        }
                    }
                });
            }

            return $query;
        }

        if ($role === 'admin') {
            $artistIds =
                $this->assignments->artistIds($user);

            $labelIds =
                $this->assignments->labelIds($user);

            if (
                $artistIds->isEmpty()
                && $labelIds->isEmpty()
            ) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(
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

                        /*
                         * Legacy mapped rows can be
                         * financial-owner-only and have
                         * no catalogue label_id.
                         */
                        $builder->orWhere(
                            function ($legacy) use (
                                $labelIds
                            ) {
                                $legacy
                                    ->where(
                                        'mapping_status',
                                        'mapped'
                                    )
                                    ->where(
                                        'revenue_owner_type',
                                        'label'
                                    )
                                    ->whereIn(
                                        'revenue_owner_id',
                                        $labelIds
                                    );
                            }
                        );
                    }
                }
            );
        }

        return $query->whereRaw('1 = 0');
    }

    public function applyFilters(
        Builder $query,
        array $filters
    ): Builder {
        if (!empty($filters['month'])) {
            $query->where(
                'reporting_month',
                $filters['month']
            );
        } else {
            if (!empty($filters['from_month'])) {
                $query->where(
                    'reporting_month',
                    '>=',
                    $filters['from_month']
                );
            }

            if (!empty($filters['to_month'])) {
                $query->where(
                    'reporting_month',
                    '<=',
                    $filters['to_month']
                );
            }
        }

        if (!empty($filters['sale_month'])) {
            $query->where(
                'sale_month',
                $filters['sale_month']
            );
        }

        if (!empty($filters['platform'])) {
            $query->where(
                'platform',
                $filters['platform']
            );
        }

        if (!empty($filters['sale_type'])) {
            $query->where(
                'sale_type',
                $filters['sale_type']
            );
        }

        if (!empty($filters['country'])) {
            $query->where(
                'country_code',
                $filters['country']
            );
        }

        if (!empty($filters['cms'])) {
            $query->where(
                'cms',
                $filters['cms']
            );
        }

        if (!empty($filters['isrc'])) {
            $query->where(
                'isrc',
                'like',
                '%' . $filters['isrc'] . '%'
            );
        }

        /*
         * MIXX_TUNE_HIERARCHY_REPORT_FILTERS
         *
         * master_label_id:
         *     complete hierarchy owned by that root.
         *
         * level_id:
         *     selected level + complete descendant subtree.
         *
         * artist_id:
         *     exact artist only.
         *
         * These filters operate on canonical report-row
         * ownership fields and never duplicate revenue.
         */
        if (!empty($filters['master_label_id'])) {
            $masterLabelId =
                (int) $filters['master_label_id'];

            $masterTreeIds = app(
                \App\Services\V2\LabelHierarchyService::class
            )->descendantIds(
                $masterLabelId,
                true
            );

            $query->where(
                function ($builder) use (
                    $masterTreeIds
                ) {
                    $builder->whereIn(
                        'label_id',
                        $masterTreeIds
                    );

                    /*
                     * Legacy financial-only mapped rows
                     * may not have catalogue label_id,
                     * but still have authoritative label
                     * ownership.
                     */
                    $builder->orWhere(
                        function ($legacy) use (
                            $masterTreeIds
                        ) {
                            $legacy
                                ->where(
                                    'mapping_status',
                                    'mapped'
                                )
                                ->where(
                                    'revenue_owner_type',
                                    'label'
                                )
                                ->whereIn(
                                    'revenue_owner_id',
                                    $masterTreeIds
                                );
                        }
                    );
                }
            );
        }

        if (!empty($filters['level_id'])) {
            $levelId =
                (int) $filters['level_id'];

            $levelTreeIds = app(
                \App\Services\V2\LabelHierarchyService::class
            )->descendantIds(
                $levelId,
                true
            );

            $query->where(
                function ($builder) use (
                    $levelTreeIds
                ) {
                    $builder->whereIn(
                        'label_id',
                        $levelTreeIds
                    );

                    /*
                     * Legacy financial-only mapped rows
                     * follow authoritative mapped owner
                     * when catalogue placement is absent.
                     */
                    $builder->orWhere(
                        function ($legacy) use (
                            $levelTreeIds
                        ) {
                            $legacy
                                ->where(
                                    'mapping_status',
                                    'mapped'
                                )
                                ->where(
                                    'revenue_owner_type',
                                    'label'
                                )
                                ->whereIn(
                                    'revenue_owner_id',
                                    $levelTreeIds
                                );
                        }
                    );
                }
            );
        }

        if (!empty($filters['artist_id'])) {
            $query->where(
                'artist_id',
                (int) $filters['artist_id']
            );
        }

        if (!empty($filters['search'])) {
            $search =
                $filters['search'];

            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'track_title',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'track_artist',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'album_title',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'isrc',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'upc',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        return $query;
    }

    public function summary(
        Builder $query
    ): array {
        $result = (clone $query)
            ->selectRaw(
                'COALESCE(SUM(streams), 0) as streams'
            )
            ->selectRaw(
                'COALESCE(SUM(sale_units), 0) as sale_units'
            )
            ->selectRaw(
                'COALESCE(SUM(earnings), 0) as earnings'
            )
            ->selectRaw(
                'COUNT(*) as rows_count'
            )
            ->first();

        return [
            'streams' =>
                (float) $result->streams,

            'sale_units' =>
                (float) $result->sale_units,

            'earnings' =>
                (float) $result->earnings,

            'rows' =>
                (int) $result->rows_count,
        ];
    }

    public function monthlyTrend(
        Builder $query,
        int $limit = 12
    ): array {
        return (clone $query)
            ->whereNotNull('reporting_month')
            ->select('reporting_month')
            ->selectRaw(
                'COALESCE(SUM(streams), 0) as streams'
            )
            ->selectRaw(
                'COALESCE(SUM(sale_units), 0) as sale_units'
            )
            ->selectRaw(
                'COALESCE(SUM(earnings), 0) as earnings'
            )
            ->groupBy('reporting_month')
            ->orderByDesc('reporting_month')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($row) => [
                'month' =>
                    $row->reporting_month,

                'streams' =>
                    (float) $row->streams,

                'sale_units' =>
                    (float) $row->sale_units,

                'earnings' =>
                    (float) $row->earnings,
            ])
            ->all();
    }


    public function growthSummary(
        array $monthlyTrend,
        ?string $selectedMonth = null
    ): array {
        if (empty($monthlyTrend)) {
            return [
                'current_month' => null,
                'previous_month' => null,
                'current_earnings' => 0.0,
                'previous_earnings' => 0.0,
                'earnings_change' => 0.0,
                'earnings_percent' => null,
                'current_streams' => 0.0,
                'previous_streams' => 0.0,
                'streams_change' => 0.0,
                'streams_percent' => null,
                'direction' => 'neutral',
                'has_previous' => false,
            ];
        }

        $rows = collect($monthlyTrend)
            ->sortBy('month')
            ->values();

        $currentIndex = null;

        if ($selectedMonth) {
            $currentIndex = $rows->search(
                fn ($row) =>
                    ($row['month'] ?? null)
                    === $selectedMonth
            );
        }

        if (
            $currentIndex === null
            || $currentIndex === false
        ) {
            $currentIndex =
                $rows->count() - 1;
        }

        $current =
            $rows->get($currentIndex);

        $previous =
            $currentIndex > 0
                ? $rows->get(
                    $currentIndex - 1
                )
                : null;

        $currentEarnings =
            (float) (
                $current['earnings']
                ?? 0
            );

        $currentStreams =
            (float) (
                $current['streams']
                ?? 0
            );

        if (!$previous) {
            return [
                'current_month' =>
                    $current['month']
                    ?? null,

                'previous_month' => null,

                'current_earnings' =>
                    $currentEarnings,

                'previous_earnings' => 0.0,

                'earnings_change' => 0.0,

                'earnings_percent' => null,

                'current_streams' =>
                    $currentStreams,

                'previous_streams' => 0.0,

                'streams_change' => 0.0,

                'streams_percent' => null,

                'direction' => 'neutral',

                'has_previous' => false,
            ];
        }

        $previousEarnings =
            (float) (
                $previous['earnings']
                ?? 0
            );

        $previousStreams =
            (float) (
                $previous['streams']
                ?? 0
            );

        $earningsChange =
            $currentEarnings
            - $previousEarnings;

        $streamsChange =
            $currentStreams
            - $previousStreams;

        $earningsPercent =
            $previousEarnings != 0.0
                ? round(
                    (
                        $earningsChange
                        / abs($previousEarnings)
                    ) * 100,
                    2
                )
                : (
                    $currentEarnings == 0.0
                        ? 0.0
                        : null
                );

        $streamsPercent =
            $previousStreams != 0.0
                ? round(
                    (
                        $streamsChange
                        / abs($previousStreams)
                    ) * 100,
                    2
                )
                : (
                    $currentStreams == 0.0
                        ? 0.0
                        : null
                );

        return [
            'current_month' =>
                $current['month']
                ?? null,

            'previous_month' =>
                $previous['month']
                ?? null,

            'current_earnings' =>
                $currentEarnings,

            'previous_earnings' =>
                $previousEarnings,

            'earnings_change' =>
                round(
                    $earningsChange,
                    8
                ),

            'earnings_percent' =>
                $earningsPercent,

            'current_streams' =>
                $currentStreams,

            'previous_streams' =>
                $previousStreams,

            'streams_change' =>
                round(
                    $streamsChange,
                    4
                ),

            'streams_percent' =>
                $streamsPercent,

            'direction' =>
                $earningsChange > 0
                    ? 'up'
                    : (
                        $earningsChange < 0
                            ? 'down'
                            : 'neutral'
                    ),

            'has_previous' => true,
        ];
    }


    public function topPlatforms(
        Builder $query,
        int $limit = 8
    ): array {
        return (clone $query)
            ->whereNotNull('platform')
            ->where('platform', '!=', '')
            ->select('platform')
            ->selectRaw(
                'COALESCE(SUM(streams), 0) as streams'
            )
            ->selectRaw(
                'COALESCE(SUM(sale_units), 0) as sale_units'
            )
            ->selectRaw(
                'COALESCE(SUM(earnings), 0) as earnings'
            )
            ->groupBy('platform')
            ->orderByDesc('earnings')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' =>
                    $row->platform,

                'streams' =>
                    (float) $row->streams,

                'sale_units' =>
                    (float) $row->sale_units,

                'earnings' =>
                    (float) $row->earnings,
            ])
            ->all();
    }

    public function topCountries(
        Builder $query,
        int $limit = 8
    ): array {
        return (clone $query)
            ->whereNotNull('country_code')
            ->where('country_code', '!=', '')
            ->select('country_code')
            ->selectRaw(
                'COALESCE(SUM(streams), 0) as streams'
            )
            ->selectRaw(
                'COALESCE(SUM(sale_units), 0) as sale_units'
            )
            ->selectRaw(
                'COALESCE(SUM(earnings), 0) as earnings'
            )
            ->groupBy('country_code')
            ->orderByDesc('earnings')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' =>
                    $row->country_code,

                'streams' =>
                    (float) $row->streams,

                'sale_units' =>
                    (float) $row->sale_units,

                'earnings' =>
                    (float) $row->earnings,
            ])
            ->all();
    }

    public function topTracks(
        Builder $query,
        int $limit = 10
    ): array {
        return (clone $query)
            ->where(
                function ($builder) {
                    $builder
                        ->whereNotNull('track_title')
                        ->orWhereNotNull('album_title');
                }
            )
            ->select([
                'track_id',
                'track_title',
                'track_artist',
                'album_title',
                'album_artist',
                'isrc',
            ])
            ->selectRaw(
                'COALESCE(SUM(streams), 0) as streams'
            )
            ->selectRaw(
                'COALESCE(SUM(sale_units), 0) as sale_units'
            )
            ->selectRaw(
                'COALESCE(SUM(earnings), 0) as earnings'
            )
            ->groupBy([
                'track_id',
                'track_title',
                'track_artist',
                'album_title',
                'album_artist',
                'isrc',
            ])
            ->orderByDesc('earnings')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'track_id' =>
                    $row->track_id,

                'title' =>
                    $row->track_title
                    ?: $row->album_title
                    ?: 'Unknown Track',

                'artist' =>
                    $row->track_artist
                    ?: $row->album_artist
                    ?: 'Unknown Artist',

                'isrc' =>
                    $row->isrc,

                'streams' =>
                    (float) $row->streams,

                'sale_units' =>
                    (float) $row->sale_units,

                'earnings' =>
                    (float) $row->earnings,
            ])
            ->all();
    }

    public function topArtists(
        Builder $query,
        int $limit = 10
    ): array {
        return (clone $query)
            ->where(function ($builder) {
                $builder
                    ->whereNotNull('track_artist')
                    ->orWhereNotNull('album_artist');
            })
            ->selectRaw(
                "COALESCE(NULLIF(track_artist, ''), NULLIF(album_artist, ''), 'Unknown Artist') as name"
            )
            ->selectRaw(
                'COALESCE(SUM(streams), 0) as streams'
            )
            ->selectRaw(
                'COALESCE(SUM(sale_units), 0) as sale_units'
            )
            ->selectRaw(
                'COALESCE(SUM(earnings), 0) as earnings'
            )
            ->groupByRaw(
                "COALESCE(NULLIF(track_artist, ''), NULLIF(album_artist, ''), 'Unknown Artist')"
            )
            ->orderByDesc('earnings')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'streams' => (float) $row->streams,
                'sale_units' => (float) $row->sale_units,
                'earnings' => (float) $row->earnings,
            ])
            ->all();
    }

    public function topAlbums(
        Builder $query,
        int $limit = 10
    ): array {
        return (clone $query)
            ->whereNotNull('album_title')
            ->where('album_title', '!=', '')
            ->select([
                'album_title',
                'album_artist',
                'upc',
            ])
            ->selectRaw(
                'COALESCE(SUM(streams), 0) as streams'
            )
            ->selectRaw(
                'COALESCE(SUM(sale_units), 0) as sale_units'
            )
            ->selectRaw(
                'COALESCE(SUM(earnings), 0) as earnings'
            )
            ->groupBy([
                'album_title',
                'album_artist',
                'upc',
            ])
            ->orderByDesc('earnings')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' =>
                    $row->album_title
                    ?: 'Unknown Album',

                'artist' =>
                    $row->album_artist
                    ?: 'Unknown Artist',

                'upc' => $row->upc,

                'streams' =>
                    (float) $row->streams,

                'sale_units' =>
                    (float) $row->sale_units,

                'earnings' =>
                    (float) $row->earnings,
            ])
            ->all();
    }

    public function topLabels(
        Builder $query,
        int $limit = 10
    ): array {
        return (clone $query)
            ->whereNotNull('label_name')
            ->where('label_name', '!=', '')
            ->select('label_name')
            ->selectRaw(
                'COALESCE(SUM(streams), 0) as streams'
            )
            ->selectRaw(
                'COALESCE(SUM(sale_units), 0) as sale_units'
            )
            ->selectRaw(
                'COALESCE(SUM(earnings), 0) as earnings'
            )
            ->groupBy('label_name')
            ->orderByDesc('earnings')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->label_name,
                'streams' => (float) $row->streams,
                'sale_units' => (float) $row->sale_units,
                'earnings' => (float) $row->earnings,
            ])
            ->all();
    }

    public function cmsSummary(
        Builder $query,
        int $limit = 20
    ): Collection {
        return $query
            ->select('cms')
            ->selectRaw(
                'SUM(streams) as streams'
            )
            ->selectRaw(
                'SUM(sale_units) as sale_units'
            )
            ->selectRaw(
                'SUM(earnings) as earnings'
            )
            ->whereNotNull('cms')
            ->where('cms', '!=', '')
            ->groupBy('cms')
            ->orderByDesc('earnings')
            ->limit($limit)
            ->get()
            ->map(
                fn ($row) => [
                    'name' =>
                        (string) $row->cms,

                    'streams' =>
                        (int) $row->streams,

                    'sale_units' =>
                        (int) $row->sale_units,

                    'earnings' =>
                        (float) $row->earnings,
                ]
            );
    }

    public function saleTypeSummary(
        Builder $query,
        int $limit = 10
    ): array {
        return (clone $query)
            ->whereNotNull('sale_type')
            ->where('sale_type', '!=', '')
            ->select('sale_type')
            ->selectRaw(
                'COALESCE(SUM(streams), 0) as streams'
            )
            ->selectRaw(
                'COALESCE(SUM(sale_units), 0) as sale_units'
            )
            ->selectRaw(
                'COALESCE(SUM(earnings), 0) as earnings'
            )
            ->groupBy('sale_type')
            ->orderByDesc('earnings')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->sale_type,
                'streams' => (float) $row->streams,
                'sale_units' => (float) $row->sale_units,
                'earnings' => (float) $row->earnings,
            ])
            ->all();
    }


    public function currencySummary(
        Builder $query
    ): array {
        return (clone $query)
            ->whereNotNull('currency')
            ->where('currency', '!=', '')
            ->select('currency')
            ->selectRaw(
                'COALESCE(SUM(earnings), 0) as earnings'
            )
            ->groupBy('currency')
            ->orderByDesc('earnings')
            ->get()
            ->map(fn ($row) => [
                'currency' =>
                    $row->currency,

                'earnings' =>
                    (float) $row->earnings,
            ])
            ->all();
    }

}
