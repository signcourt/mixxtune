<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Royalties\RoyaltyPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RoyaltyPostingController extends Controller
{
    public function index(
        Request $request,
        RoyaltyPostingService $service
    ) {
        $reportingMonth = trim(
            (string) $request->input('reporting_month', '')
        );

        $saleMonth = trim(
            (string) $request->input('sale_month', '')
        );

        $previewRows = $service->preview(
            $reportingMonth !== '' ? $reportingMonth : null,
            $saleMonth !== '' ? $saleMonth : null
        );

        $collection = collect($previewRows);

        $postedLedgers = DB::table('royalty_ledgers')
            ->leftJoin(
                'labels',
                'labels.id',
                '=',
                'royalty_ledgers.label_id'
            )
            ->whereNotNull('royalty_ledgers.label_id')
            ->select([
                'royalty_ledgers.id',
                'royalty_ledgers.statement_month',
                'royalty_ledgers.currency',
                'royalty_ledgers.label_share as net_amount',
                'royalty_ledgers.split_percentage as share_percentage',
                'royalty_ledgers.payable_amount as beneficiary_amount',
                'royalty_ledgers.status',
                'labels.name as label_name',
            ])
            ->orderByDesc('royalty_ledgers.statement_month')
            ->orderByDesc('royalty_ledgers.id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/RoyaltyPosting/Index', [
            'previewRows' => $previewRows,

            'summary' => [
                'rows' => $collection->count(),
                'labels' => $collection
                    ->pluck('label_id')
                    ->unique()
                    ->count(),
                'net_amount' => $collection->sum('net_amount'),
                'beneficiary_amount' => $collection
                    ->sum('beneficiary_amount'),
            ],

            'postedLedgers' => $postedLedgers,

            'reportingMonths' => DB::table('revenue_rows')
                ->whereNotNull('reporting_month')
                ->distinct()
                ->orderByDesc('reporting_month')
                ->pluck('reporting_month')
                ->map(fn ($value) => [
                    'value' => $value,
                    'label' => date('M Y', strtotime($value)),
                ])
                ->values(),

            'saleMonths' => DB::table('revenue_rows')
                ->whereNotNull('sale_month')
                ->distinct()
                ->orderByDesc('sale_month')
                ->pluck('sale_month')
                ->map(fn ($value) => [
                    'value' => $value,
                    'label' => date('M Y', strtotime($value)),
                ])
                ->values(),

            'filters' => [
                'reporting_month' => $reportingMonth,
                'sale_month' => $saleMonth,
            ],
        ]);
    }

    public function store(
        Request $request,
        RoyaltyPostingService $service
    ) {
        $validated = $request->validate([
            'reporting_month' => ['nullable', 'date_format:Y-m-d'],
            'sale_month' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $result = $service->post(
            $validated['reporting_month'] ?? null,
            $validated['sale_month'] ?? null,
            $request->user()?->id
        );

        return back()->with(
            'success',
            "{$result['created']} royalty ledgers created; " .
            "{$result['skipped']} duplicates skipped."
        );
    }
}
