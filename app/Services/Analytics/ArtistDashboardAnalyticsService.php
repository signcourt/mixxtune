<?php

namespace App\Services\Analytics;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ArtistDashboardAnalyticsService
{
    public function build(int $artistId, string $currency = 'INR'): array
    {
        $base = DB::table('report_rows')
            ->where('artist_id', $artistId);

        $summary = (clone $base)
            ->selectRaw('COALESCE(SUM(streams), 0) as total_streams')
            ->selectRaw('COALESCE(SUM(earnings), 0) as total_earnings')
            ->selectRaw('COUNT(DISTINCT track_id) as unique_tracks')
            ->selectRaw('COUNT(DISTINCT platform) as active_platforms')
            ->first();

        $monthly = (clone $base)
            ->whereNotNull('sale_month')
            ->select('sale_month')
            ->selectRaw('COALESCE(SUM(streams), 0) as streams')
            ->selectRaw('COALESCE(SUM(earnings), 0) as earnings')
            ->groupBy('sale_month')
            ->orderBy('sale_month')
            ->get()
            ->map(fn ($row) => [
                'month' => (string) $row->sale_month,
                'streams' => (int) $row->streams,
                'earnings' => (float) $row->earnings,
            ])
            ->values();

        $monthly = $this->lastTwelveMonths($monthly);

        $topTracks = (clone $base)
            ->select([
                'track_id',
                'track_title',
                'isrc',
            ])
            ->selectRaw('COALESCE(SUM(streams), 0) as streams')
            ->selectRaw('COALESCE(SUM(earnings), 0) as earnings')
            ->groupBy('track_id', 'track_title', 'isrc')
            ->orderByDesc('streams')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'track_id' => $row->track_id,
                'title' => $row->track_title ?: 'Untitled Track',
                'isrc' => $row->isrc,
                'streams' => (int) $row->streams,
                'earnings' => (float) $row->earnings,
            ])
            ->values();

        $topPlatforms = (clone $base)
            ->whereNotNull('platform')
            ->where('platform', '<>', '')
            ->select('platform')
            ->selectRaw('COALESCE(SUM(streams), 0) as streams')
            ->selectRaw('COALESCE(SUM(earnings), 0) as earnings')
            ->groupBy('platform')
            ->orderByDesc('streams')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->platform,
                'streams' => (int) $row->streams,
                'earnings' => (float) $row->earnings,
            ])
            ->values();

        $topCountries = (clone $base)
            ->whereNotNull('country_code')
            ->where('country_code', '<>', '')
            ->select('country_code')
            ->selectRaw('COALESCE(SUM(streams), 0) as streams')
            ->selectRaw('COALESCE(SUM(earnings), 0) as earnings')
            ->groupBy('country_code')
            ->orderByDesc('streams')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'country_code' => (string) $row->country_code,
                'streams' => (int) $row->streams,
                'earnings' => (float) $row->earnings,
            ])
            ->values();

        [$streamGrowth, $earningGrowth] = $this->growth($monthly);

        return [
            'summary' => [
                'total_streams' => (int) ($summary->total_streams ?? 0),
                'total_earnings' => (float) ($summary->total_earnings ?? 0),
                'unique_tracks' => (int) ($summary->unique_tracks ?? 0),
                'active_platforms' => (int) ($summary->active_platforms ?? 0),
                'stream_growth_percent' => $streamGrowth,
                'earning_growth_percent' => $earningGrowth,
                'currency' => $currency,
            ],
            'monthly' => $monthly,
            'top_tracks' => $topTracks,
            'top_platforms' => $topPlatforms,
            'top_countries' => $topCountries,
            'has_data' => (int) ($summary->total_streams ?? 0) > 0
                || (float) ($summary->total_earnings ?? 0) != 0.0,
        ];
    }

    private function lastTwelveMonths(Collection $rows): Collection
    {
        return $rows
            ->sortBy('month')
            ->values()
            ->slice(-12)
            ->values();
    }

    private function growth(Collection $monthly): array
    {
        if ($monthly->count() < 2) {
            return [0.0, 0.0];
        }

        $current = $monthly->last();
        $previous = $monthly->get($monthly->count() - 2);

        return [
            $this->percentageChange(
                (float) ($previous['streams'] ?? 0),
                (float) ($current['streams'] ?? 0)
            ),
            $this->percentageChange(
                (float) ($previous['earnings'] ?? 0),
                (float) ($current['earnings'] ?? 0)
            ),
        ];
    }

    private function percentageChange(float $previous, float $current): float
    {
        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : 100.0;
        }

        return round((($current - $previous) / abs($previous)) * 100, 2);
    }
}
