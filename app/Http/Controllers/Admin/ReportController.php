<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'reporting_month' => trim(
                (string) $request->input('reporting_month', '')
            ),
            'sale_month' => trim(
                (string) $request->input('sale_month', '')
            ),
            'store' => trim((string) $request->input('store', '')),
            'country' => trim((string) $request->input('country', '')),
            'label' => trim((string) $request->input('label', '')),
            'search' => trim((string) $request->input('search', '')),
        ];

        $baseQuery = DB::table('revenue_rows')
            ->join('tracks', 'tracks.isrc', '=', 'revenue_rows.isrc')
            ->whereNull('tracks.deleted_at');

        $this->applyFilters($baseQuery, $filters);

        $summary = (clone $baseQuery)
            ->selectRaw('
                COUNT(revenue_rows.id) as revenue_rows,
                COUNT(DISTINCT revenue_rows.isrc) as unique_tracks,
                COALESCE(SUM(revenue_rows.streams), 0) as total_streams,
                COALESCE(SUM(revenue_rows.quantity), 0) as total_quantity,
                COALESCE(SUM(revenue_rows.gross_amount), 0) as gross_amount,
                COALESCE(SUM(revenue_rows.net_amount), 0) as net_amount
            ')
            ->first();

        $topTracks = (clone $baseQuery)
            ->select([
                'revenue_rows.isrc',
                DB::raw('MAX(revenue_rows.track_title) as track_title'),
                DB::raw('MAX(revenue_rows.artist_name) as artist_name'),
                DB::raw('MAX(revenue_rows.label_name) as label_name'),
                DB::raw('SUM(revenue_rows.streams) as streams'),
                DB::raw('SUM(revenue_rows.quantity) as quantity'),
                DB::raw('SUM(revenue_rows.net_amount) as net_amount'),
            ])
            ->groupBy('revenue_rows.isrc')
            ->orderByDesc('net_amount')
            ->limit(20)
            ->get();

        $storeBreakdown = (clone $baseQuery)
            ->select([
                'revenue_rows.store_name',
                DB::raw('SUM(revenue_rows.streams) as streams'),
                DB::raw('SUM(revenue_rows.net_amount) as net_amount'),
            ])
            ->groupBy('revenue_rows.store_name')
            ->orderByDesc('net_amount')
            ->limit(20)
            ->get();

        $monthlyBreakdown = (clone $baseQuery)
            ->select([
                'revenue_rows.reporting_month',
                'revenue_rows.sale_month',
                DB::raw('SUM(revenue_rows.streams) as streams'),
                DB::raw('SUM(revenue_rows.net_amount) as net_amount'),
            ])
            ->whereNotNull('revenue_rows.sale_month')
            ->groupBy(
                'revenue_rows.reporting_month',
                'revenue_rows.sale_month'
            )
            ->orderBy('revenue_rows.reporting_month')
            ->orderBy('revenue_rows.sale_month')
            ->get();

        return Inertia::render('Admin/Reports/Index', [
            'summary' => [
                'revenue_rows' => (int) ($summary->revenue_rows ?? 0),
                'unique_tracks' => (int) ($summary->unique_tracks ?? 0),
                'total_streams' => (int) ($summary->total_streams ?? 0),
                'total_quantity' => (float) ($summary->total_quantity ?? 0),
                'gross_amount' => (float) ($summary->gross_amount ?? 0),
                'net_amount' => (float) ($summary->net_amount ?? 0),
                'currency' => 'INR',
            ],

            'topTracks' => $topTracks,
            'storeBreakdown' => $storeBreakdown,
            'monthlyBreakdown' => $monthlyBreakdown,

            'filterOptions' => [
                'reportingMonths' => DB::table('revenue_rows')
                    ->whereNotNull('reporting_month')
                    ->distinct()
                    ->orderByDesc('reporting_month')
                    ->pluck('reporting_month')
                    ->map(fn ($month) => [
                        'value' => $month,
                        'label' => date('M Y', strtotime($month)),
                    ])
                    ->values(),

                'saleMonths' => DB::table('revenue_rows')
                    ->whereNotNull('sale_month')
                    ->distinct()
                    ->orderByDesc('sale_month')
                    ->pluck('sale_month')
                    ->map(fn ($month) => [
                        'value' => $month,
                        'label' => date('M Y', strtotime($month)),
                    ])
                    ->values(),

                'stores' => DB::table('revenue_rows')
                    ->whereNotNull('store_name')
                    ->where('store_name', '!=', '')
                    ->distinct()
                    ->orderBy('store_name')
                    ->pluck('store_name'),

                'countries' => DB::table('revenue_rows')
                    ->whereNotNull('country_code')
                    ->where('country_code', '!=', '')
                    ->distinct()
                    ->orderBy('country_code')
                    ->pluck('country_code'),

                'labels' => DB::table('revenue_rows')
                    ->whereNotNull('label_name')
                    ->where('label_name', '!=', '')
                    ->distinct()
                    ->orderBy('label_name')
                    ->pluck('label_name'),
            ],

            'filters' => $filters,
        ]);
    }

    private function applyFilters($query, array $filters): void
    {
        if ($filters['reporting_month'] !== '') {
            $query->whereDate(
                'revenue_rows.reporting_month',
                $filters['reporting_month']
            );
        }

        if ($filters['sale_month'] !== '') {
            $query->whereDate(
                'revenue_rows.sale_month',
                $filters['sale_month']
            );
        }

        if ($filters['store'] !== '') {
            $query->where('revenue_rows.store_name', $filters['store']);
        }

        if ($filters['country'] !== '') {
            $query->where('revenue_rows.country_code', $filters['country']);
        }

        if ($filters['label'] !== '') {
            $query->where('revenue_rows.label_name', $filters['label']);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('revenue_rows.track_title', 'like', "%{$search}%")
                    ->orWhere('revenue_rows.artist_name', 'like', "%{$search}%")
                    ->orWhere('revenue_rows.isrc', 'like', "%{$search}%")
                    ->orWhere('revenue_rows.upc', 'like', "%{$search}%");
            });
        }
    }

    public function export(Request $request)
    {
        $filters = [
            'reporting_month' => trim(
                (string) $request->input('reporting_month', '')
            ),
            'sale_month' => trim(
                (string) $request->input('sale_month', '')
            ),
            'store' => trim((string) $request->input('store', '')),
            'country' => trim((string) $request->input('country', '')),
            'label' => trim((string) $request->input('label', '')),
            'search' => trim((string) $request->input('search', '')),
        ];

        $query = DB::table('revenue_rows')
            ->join('tracks', 'tracks.isrc', '=', 'revenue_rows.isrc')
            ->whereNull('tracks.deleted_at')
            ->select([
                'revenue_rows.sale_month',
                'revenue_rows.track_title',
                'revenue_rows.artist_name',
                'revenue_rows.label_name',
                'revenue_rows.isrc',
                'revenue_rows.upc',
                'revenue_rows.store_name',
                'revenue_rows.country_code',
                'revenue_rows.sale_type',
                'revenue_rows.streams',
                'revenue_rows.quantity',
                'revenue_rows.gross_amount',
                'revenue_rows.net_amount',
                'revenue_rows.currency',
            ])
            ->orderBy('revenue_rows.sale_month')
            ->orderBy('revenue_rows.track_title');

        $this->applyFilters($query, $filters);

        $filename = 'revenue-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Reporting Month',
                'Sale Month',
                'Track Title',
                'Artist',
                'Label',
                'ISRC',
                'UPC',
                'Store',
                'Country',
                'Sale Type',
                'Streams',
                'Quantity',
                'Gross Amount',
                'Net Amount',
                'Currency',
            ]);

            $query->chunk(1000, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->reporting_month,
                        $row->sale_month,
                        $row->track_title,
                        $row->artist_name,
                        $row->label_name,
                        $row->isrc,
                        $row->upc,
                        $row->store_name,
                        $row->country_code,
                        $row->sale_type,
                        $row->streams,
                        $row->quantity,
                        $row->gross_amount,
                        $row->net_amount,
                        $row->currency,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }


    public function showTrack(Request $request, string $isrc)
    {
        $track = DB::table('revenue_rows')
            ->where('isrc', $isrc)
            ->select([
                'isrc',
                DB::raw('MAX(track_title) as track_title'),
                DB::raw('MAX(artist_name) as artist_name'),
                DB::raw('MAX(label_name) as label_name'),
                DB::raw('MAX(upc) as upc'),
                DB::raw('SUM(streams) as streams'),
                DB::raw('SUM(quantity) as quantity'),
                DB::raw('SUM(gross_amount) as gross_amount'),
                DB::raw('SUM(net_amount) as net_amount'),
            ])
            ->groupBy('isrc')
            ->firstOrFail();

        $stores = DB::table('revenue_rows')
            ->where('isrc', $isrc)
            ->select([
                'store_name',
                DB::raw('SUM(streams) as streams'),
                DB::raw('SUM(net_amount) as net_amount'),
            ])
            ->groupBy('store_name')
            ->orderByDesc('net_amount')
            ->get();

        $countries = DB::table('revenue_rows')
            ->where('isrc', $isrc)
            ->select([
                'country_code',
                DB::raw('SUM(streams) as streams'),
                DB::raw('SUM(net_amount) as net_amount'),
            ])
            ->groupBy('country_code')
            ->orderByDesc('net_amount')
            ->limit(20)
            ->get();

        $months = DB::table('revenue_rows')
            ->where('isrc', $isrc)
            ->select([
                'sale_month',
                DB::raw('SUM(streams) as streams'),
                DB::raw('SUM(net_amount) as net_amount'),
            ])
            ->groupBy('sale_month')
            ->orderBy('sale_month')
            ->get();

        return Inertia::render('Admin/Reports/Track', [
            'track' => $track,
            'stores' => $stores,
            'countries' => $countries,
            'months' => $months,
        ]);
    }

}
