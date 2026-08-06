<?php

namespace App\Services\V2;

use App\Models\Reports\ReportRow;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportAnalyticsService
{
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
            $labelId = DB::table('labels')
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->value('id');

            return $labelId
                ? $query->where(
                    'label_id',
                    $labelId
                )
                : $query->whereRaw('1 = 0');
        }

        if ($role === 'admin') {
            $artistIds = DB::table('artists')
                ->where(
                    'assigned_admin_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->pluck('id');

            return $query->whereIn(
                'artist_id',
                $artistIds
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
                'sale_month',
                $filters['month']
            );
        }

        if (!empty($filters['platform'])) {
            $query->where(
                'platform',
                $filters['platform']
            );
        }

        if (!empty($filters['country'])) {
            $query->where(
                'country_code',
                $filters['country']
            );
        }

        if (!empty($filters['isrc'])) {
            $query->where(
                'isrc',
                'like',
                '%' . $filters['isrc'] . '%'
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
            ->whereNotNull('sale_month')
            ->select('sale_month')
            ->selectRaw(
                'COALESCE(SUM(streams), 0) as streams'
            )
            ->selectRaw(
                'COALESCE(SUM(sale_units), 0) as sale_units'
            )
            ->selectRaw(
                'COALESCE(SUM(earnings), 0) as earnings'
            )
            ->groupBy('sale_month')
            ->orderByDesc('sale_month')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($row) => [
                'month' =>
                    $row->sale_month,

                'streams' =>
                    (float) $row->streams,

                'sale_units' =>
                    (float) $row->sale_units,

                'earnings' =>
                    (float) $row->earnings,
            ])
            ->all();
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
