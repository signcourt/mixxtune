<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RoyaltyController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'month' => trim((string) $request->input('month', '')),
            'label' => trim((string) $request->input('label', '')),
            'artist' => trim((string) $request->input('artist', '')),
            'search' => trim((string) $request->input('search', '')),
        ];

        $baseQuery = DB::table('revenue_rows')
            ->join('tracks', 'tracks.isrc', '=', 'revenue_rows.isrc')
            ->join('releases', 'releases.id', '=', 'tracks.release_id')
            ->leftJoin('labels', 'labels.id', '=', 'releases.label_id')
            ->whereNull('tracks.deleted_at')
            ->whereNull('releases.deleted_at');

        $this->applyFilters($baseQuery, $filters);

        $summary = (clone $baseQuery)
            ->selectRaw('
                COUNT(DISTINCT revenue_rows.isrc) as unique_tracks,
                COALESCE(SUM(revenue_rows.streams), 0) as total_streams,
                COALESCE(SUM(revenue_rows.net_amount), 0) as net_revenue
            ')
            ->first();

        $labelRoyalties = (clone $baseQuery)
            ->select([
                DB::raw("COALESCE(NULLIF(revenue_rows.label_name, ''), labels.name, 'Unknown Label') as label_name"),
                DB::raw('COUNT(DISTINCT revenue_rows.isrc) as tracks'),
                DB::raw('SUM(revenue_rows.streams) as streams'),
                DB::raw('SUM(revenue_rows.net_amount) as net_revenue'),
                DB::raw('MAX(COALESCE(labels.royalty_share_percentage, 100)) as royalty_percentage'),
                DB::raw('SUM(revenue_rows.net_amount) * MAX(COALESCE(labels.royalty_share_percentage, 100)) / 100 as payable_amount'),
            ])
            ->groupBy(DB::raw("COALESCE(NULLIF(revenue_rows.label_name, ''), labels.name, 'Unknown Label')"))
            ->orderByDesc('payable_amount')
            ->paginate(20)
            ->withQueryString();

        $artistRoyalties = (clone $baseQuery)
            ->select([
                'revenue_rows.artist_name',
                DB::raw('COUNT(DISTINCT revenue_rows.isrc) as tracks'),
                DB::raw('SUM(revenue_rows.streams) as streams'),
                DB::raw('SUM(revenue_rows.net_amount) as net_revenue'),
            ])
            ->groupBy('revenue_rows.artist_name')
            ->orderByDesc('net_revenue')
            ->limit(20)
            ->get();


        $topTracks = (clone $baseQuery)
            ->select([
                'revenue_rows.track_title',
                'revenue_rows.isrc',
                'revenue_rows.artist_name',
                DB::raw("COALESCE(NULLIF(revenue_rows.label_name, ''), labels.name, 'Unknown Label') as label_name"),
                DB::raw('SUM(revenue_rows.streams) as streams'),
                DB::raw('SUM(revenue_rows.net_amount) as revenue'),
            ])
            ->groupBy(
                'revenue_rows.track_title',
                'revenue_rows.isrc',
                'revenue_rows.artist_name',
                DB::raw("COALESCE(NULLIF(revenue_rows.label_name, ''), labels.name, 'Unknown Label')")
            )
            ->orderByDesc('revenue')
            ->limit(20)
            ->get();

        return Inertia::render('Admin/Royalties/Index', [
            'summary' => [
                'unique_tracks' => (int) ($summary->unique_tracks ?? 0),
                'total_streams' => (int) ($summary->total_streams ?? 0),
                'net_revenue' => (float) ($summary->net_revenue ?? 0),
                'currency' => 'INR',
            ],

            'labelRoyalties' => $labelRoyalties,
            'artistRoyalties' => $artistRoyalties,
            'topTracks' => $topTracks,

            'filterOptions' => [
                'months' => DB::table('revenue_rows')
                    ->whereNotNull('sale_month')
                    ->distinct()
                    ->orderByDesc('sale_month')
                    ->pluck('sale_month')
                    ->map(fn ($month) => [
                        'value' => $month,
                        'label' => date('M Y', strtotime($month)),
                    ])
                    ->values(),

                'labels' => DB::table('revenue_rows')
                    ->whereNotNull('label_name')
                    ->where('label_name', '!=', '')
                    ->distinct()
                    ->orderBy('label_name')
                    ->pluck('label_name'),

                'artists' => DB::table('revenue_rows')
                    ->whereNotNull('artist_name')
                    ->where('artist_name', '!=', '')
                    ->distinct()
                    ->orderBy('artist_name')
                    ->pluck('artist_name'),
            ],

            'filters' => $filters,
        ]);
    }

    private function applyFilters($query, array $filters): void
    {
        if ($filters['month'] !== '') {
            $query->whereDate('revenue_rows.sale_month', $filters['month']);
        }

        if ($filters['label'] !== '') {
            $query->where('revenue_rows.label_name', $filters['label']);
        }

        if ($filters['artist'] !== '') {
            $query->where('revenue_rows.artist_name', $filters['artist']);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('revenue_rows.track_title', 'like', "%{$search}%")
                    ->orWhere('revenue_rows.artist_name', 'like', "%{$search}%")
                    ->orWhere('revenue_rows.label_name', 'like', "%{$search}%")
                    ->orWhere('revenue_rows.isrc', 'like', "%{$search}%");
            });
        }
    }

    public function showLabel(Request $request, string $label)
    {
        $labelName = urldecode($label);

        $baseQuery = DB::table('revenue_rows')
            ->join('tracks', 'tracks.isrc', '=', 'revenue_rows.isrc')
            ->whereNull('tracks.deleted_at')
            ->where('revenue_rows.label_name', $labelName);

        $summary = (clone $baseQuery)
            ->selectRaw('
                COUNT(DISTINCT revenue_rows.isrc) as unique_tracks,
                COALESCE(SUM(revenue_rows.streams), 0) as total_streams,
                COALESCE(SUM(revenue_rows.net_amount), 0) as net_revenue
            ')
            ->first();

        $tracks = (clone $baseQuery)
            ->select([
                'revenue_rows.isrc',
                DB::raw('MAX(revenue_rows.track_title) as track_title'),
                DB::raw('MAX(revenue_rows.artist_name) as artist_name'),
                DB::raw('MAX(revenue_rows.upc) as upc'),
                DB::raw('SUM(revenue_rows.streams) as streams'),
                DB::raw('SUM(revenue_rows.quantity) as quantity'),
                DB::raw('SUM(revenue_rows.net_amount) as net_revenue'),
            ])
            ->groupBy('revenue_rows.isrc')
            ->orderByDesc('net_revenue')
            ->paginate(25)
            ->withQueryString();

        $topStores = (clone $baseQuery)
            ->select([
                'revenue_rows.store_name',
                DB::raw('SUM(revenue_rows.streams) as streams'),
                DB::raw('SUM(revenue_rows.net_amount) as net_revenue'),
            ])
            ->groupBy('revenue_rows.store_name')
            ->orderByDesc('net_revenue')
            ->limit(10)
            ->get();

        $monthly = (clone $baseQuery)
            ->select([
                'revenue_rows.sale_month',
                DB::raw('SUM(revenue_rows.streams) as streams'),
                DB::raw('SUM(revenue_rows.net_amount) as net_revenue'),
            ])
            ->whereNotNull('revenue_rows.sale_month')
            ->groupBy('revenue_rows.sale_month')
            ->orderBy('revenue_rows.sale_month')
            ->get();

        return Inertia::render('Admin/Royalties/Label', [
            'labelName' => $labelName,
            'summary' => [
                'unique_tracks' => (int) ($summary->unique_tracks ?? 0),
                'total_streams' => (int) ($summary->total_streams ?? 0),
                'net_revenue' => (float) ($summary->net_revenue ?? 0),
                'currency' => 'INR',
            ],
            'tracks' => $tracks,
            'topStores' => $topStores,
            'monthly' => $monthly,
        ]);
    }

}
