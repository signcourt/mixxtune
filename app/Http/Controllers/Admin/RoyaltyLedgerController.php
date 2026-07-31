<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Royalties\WalletPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RoyaltyLedgerController extends Controller
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
            'status' => trim(
                (string) $request->input('status', '')
            ),
            'label_id' => trim(
                (string) $request->input('label_id', '')
            ),
            'search' => trim(
                (string) $request->input('search', '')
            ),
        ];

        $query = DB::table('royalty_ledgers')
            ->leftJoin(
                'labels',
                'labels.id',
                '=',
                'royalty_ledgers.label_id'
            )
            ->select([
                'royalty_ledgers.id',
                'royalty_ledgers.public_id',
                'royalty_ledgers.ledger_number',
                'royalty_ledgers.reporting_month',
                'royalty_ledgers.sale_month',
                'royalty_ledgers.statement_month',
                'royalty_ledgers.currency',
                'royalty_ledgers.gross_amount',
                'royalty_ledgers.label_share as net_amount',
                'royalty_ledgers.split_percentage',
                'royalty_ledgers.payable_amount',
                'royalty_ledgers.streams',
                'royalty_ledgers.status',
                'royalty_ledgers.created_at',
                'labels.name as label_name',
            ]);

        if ($filters['reporting_month'] !== '') {
            $query->whereDate(
                'royalty_ledgers.reporting_month',
                $filters['reporting_month']
            );
        }

        if ($filters['sale_month'] !== '') {
            $query->whereDate(
                'royalty_ledgers.sale_month',
                $filters['sale_month']
            );
        }

        if ($filters['status'] !== '') {
            $query->where(
                'royalty_ledgers.status',
                $filters['status']
            );
        }

        if ($filters['label_id'] !== '') {
            $query->where(
                'royalty_ledgers.label_id',
                $filters['label_id']
            );
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where(
                        'royalty_ledgers.ledger_number',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'labels.name',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        $summaryQuery = clone $query;

        $summary = DB::query()
            ->fromSub($summaryQuery, 'ledger_rows')
            ->selectRaw('
                COUNT(*) as total_ledgers,
                COALESCE(SUM(net_amount), 0) as net_revenue,
                COALESCE(SUM(payable_amount), 0) as payable_amount,
                COALESCE(SUM(streams), 0) as total_streams
            ')
            ->first();

        return Inertia::render('Admin/RoyaltyLedgers/Index', [
            'ledgers' => $query
                ->orderByDesc('royalty_ledgers.reporting_month')
                ->orderByDesc('royalty_ledgers.id')
                ->paginate(25)
                ->withQueryString(),

            'summary' => [
                'total_ledgers' => (int) ($summary->total_ledgers ?? 0),
                'net_revenue' => (float) ($summary->net_revenue ?? 0),
                'payable_amount' => (float) ($summary->payable_amount ?? 0),
                'total_streams' => (int) ($summary->total_streams ?? 0),
                'currency' => 'INR',
            ],

            'filterOptions' => [
                'reportingMonths' => DB::table('royalty_ledgers')
                    ->whereNotNull('reporting_month')
                    ->distinct()
                    ->orderByDesc('reporting_month')
                    ->pluck('reporting_month')
                    ->map(fn ($month) => [
                        'value' => $month,
                        'label' => date('M Y', strtotime($month)),
                    ])
                    ->values(),

                'saleMonths' => DB::table('royalty_ledgers')
                    ->whereNotNull('sale_month')
                    ->distinct()
                    ->orderByDesc('sale_month')
                    ->pluck('sale_month')
                    ->map(fn ($month) => [
                        'value' => $month,
                        'label' => date('M Y', strtotime($month)),
                    ])
                    ->values(),

                'labels' => DB::table('labels')
                    ->select('id', 'name')
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->get(),

                'statuses' => [
                    'draft',
                    'posted',
                    'approved',
                    'paid',
                    'cancelled',
                ],
            ],

            'filters' => $filters,
        ]);
    }

    public function show(int $id)
    {
        $ledger = DB::table('royalty_ledgers')
            ->leftJoin(
                'labels',
                'labels.id',
                '=',
                'royalty_ledgers.label_id'
            )
            ->leftJoin(
                'revenue_label_mappings',
                'revenue_label_mappings.id',
                '=',
                'royalty_ledgers.revenue_label_mapping_id'
            )
            ->where('royalty_ledgers.id', $id)
            ->select([
                'royalty_ledgers.id',
                'royalty_ledgers.public_id',
                'royalty_ledgers.ledger_number',
                'royalty_ledgers.reporting_month',
                'royalty_ledgers.sale_month',
                'royalty_ledgers.statement_month',
                'royalty_ledgers.currency',
                'royalty_ledgers.gross_amount',
                'royalty_ledgers.label_share as net_amount',
                'royalty_ledgers.split_percentage',
                'royalty_ledgers.payable_amount',
                'royalty_ledgers.streams',
                'royalty_ledgers.status',
                'royalty_ledgers.metadata',
                'royalty_ledgers.created_at',
                'royalty_ledgers.updated_at',
                'labels.name as label_name',
                'revenue_label_mappings.revenue_label_name',
            ])
            ->first();

        abort_if(!$ledger, 404);

        $trackRows = DB::table('revenue_rows')
            ->leftJoin(
                'tracks',
                'tracks.isrc',
                '=',
                'revenue_rows.isrc'
            )
            ->whereDate(
                'revenue_rows.reporting_month',
                $ledger->reporting_month
            )
            ->whereDate(
                'revenue_rows.sale_month',
                $ledger->sale_month
            )
            ->whereRaw(
                'LOWER(TRIM(revenue_rows.label_name)) = LOWER(TRIM(?))',
                [
                    $ledger->revenue_label_name
                        ?: $ledger->label_name,
                ]
            )
            ->select([
                'revenue_rows.isrc',
                DB::raw(
                    "COALESCE(
                        NULLIF(revenue_rows.track_title, ''),
                        NULLIF(tracks.title, ''),
                        'Unknown Track'
                    ) as track_title"
                ),
                'revenue_rows.artist_name',
                'revenue_rows.store_name',
                'revenue_rows.country_code',
                DB::raw(
                    'COALESCE(SUM(revenue_rows.streams), 0) as streams'
                ),
                DB::raw(
                    'COALESCE(SUM(revenue_rows.net_amount), 0) as net_amount'
                ),
            ])
            ->groupBy(
                'revenue_rows.isrc',
                DB::raw(
                    "COALESCE(
                        NULLIF(revenue_rows.track_title, ''),
                        NULLIF(tracks.title, ''),
                        'Unknown Track'
                    )"
                ),
                'revenue_rows.artist_name',
                'revenue_rows.store_name',
                'revenue_rows.country_code'
            )
            ->orderByDesc('net_amount')
            ->paginate(50)
            ->withQueryString();

        $trackSummary = DB::table('revenue_rows')
            ->whereDate(
                'reporting_month',
                $ledger->reporting_month
            )
            ->whereDate(
                'sale_month',
                $ledger->sale_month
            )
            ->whereRaw(
                'LOWER(TRIM(label_name)) = LOWER(TRIM(?))',
                [
                    $ledger->revenue_label_name
                        ?: $ledger->label_name,
                ]
            )
            ->selectRaw('
                COUNT(DISTINCT isrc) as total_tracks,
                COALESCE(SUM(streams), 0) as total_streams,
                COALESCE(SUM(net_amount), 0) as net_revenue
            ')
            ->first();

        return Inertia::render('Admin/RoyaltyLedgers/Show', [
            'ledger' => [
                ...((array) $ledger),
                'metadata' => json_decode(
                    (string) $ledger->metadata,
                    true
                ) ?: [],
                'total_tracks' => (int) (
                    $trackSummary->total_tracks ?? 0
                ),
                'source_streams' => (int) (
                    $trackSummary->total_streams ?? 0
                ),
                'source_net_revenue' => (float) (
                    $trackSummary->net_revenue ?? 0
                ),
            ],

            'trackRows' => $trackRows,
        ]);
    }


    public function approve(Request $request, int $id)
    {
        $ledger = DB::table('royalty_ledgers')
            ->where('id', $id)
            ->first();

        abort_if(!$ledger, 404);

        if (!in_array($ledger->status, ['draft', 'posted'], true)) {
            return back()->withErrors([
                'status' => 'Sirf draft ya posted ledger approve ho sakta hai.',
            ]);
        }

        DB::table('royalty_ledgers')
            ->where('id', $id)
            ->update([
                'status' => 'approved',
                'updated_by' => $request->user()?->id,
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Royalty ledger approved.');
    }

    public function cancel(Request $request, int $id)
    {
        $ledger = DB::table('royalty_ledgers')
            ->where('id', $id)
            ->first();

        abort_if(!$ledger, 404);

        if (in_array($ledger->status, ['wallet_credited', 'paid'], true)) {
            return back()->withErrors([
                'status' => 'Wallet credited ya paid ledger cancel nahi ho sakta.',
            ]);
        }

        DB::table('royalty_ledgers')
            ->where('id', $id)
            ->update([
                'status' => 'cancelled',
                'updated_by' => $request->user()?->id,
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Royalty ledger cancelled.');
    }

    public function creditWallet(
        Request $request,
        int $id,
        WalletPostingService $service
    ) {
        try {
            $result = $service->creditLedger(
                $id,
                $request->user()?->id
            );

            return back()->with(
                'success',
                sprintf(
                    '%s %.2f wallet me credit ho gaya. New balance: %.2f',
                    $result['currency'],
                    $result['amount'],
                    $result['balance_after']
                )
            );
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'wallet' => $exception->getMessage(),
            ]);
        }
    }


}
