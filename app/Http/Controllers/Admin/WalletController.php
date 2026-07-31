<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'label_id' => trim(
                (string) $request->input('label_id', '')
            ),
            'currency' => trim(
                (string) $request->input('currency', '')
            ),
            'search' => trim(
                (string) $request->input('search', '')
            ),
        ];

        $walletQuery = DB::table('wallets')
            ->leftJoin(
                'labels',
                'labels.id',
                '=',
                'wallets.label_id'
            )
            ->select([
                'wallets.id',
                'wallets.public_id',
                'wallets.label_id',
                'wallets.currency',
                'wallets.available_balance',
                'wallets.pending_balance',
                'wallets.lifetime_credits',
                'wallets.lifetime_debits',
                'wallets.status',
                'wallets.updated_at',
                'labels.name as label_name',
            ]);

        if ($filters['label_id'] !== '') {
            $walletQuery->where(
                'wallets.label_id',
                $filters['label_id']
            );
        }

        if ($filters['currency'] !== '') {
            $walletQuery->where(
                'wallets.currency',
                $filters['currency']
            );
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $walletQuery->where(function ($query) use ($search) {
                $query
                    ->where(
                        'labels.name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'wallets.public_id',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        $summaryQuery = clone $walletQuery;

        $summary = DB::query()
            ->fromSub($summaryQuery, 'wallet_rows')
            ->selectRaw('
                COUNT(*) as total_wallets,
                COALESCE(SUM(available_balance), 0) as available_balance,
                COALESCE(SUM(pending_balance), 0) as pending_balance,
                COALESCE(SUM(lifetime_credits), 0) as lifetime_credits,
                COALESCE(SUM(lifetime_debits), 0) as lifetime_debits
            ')
            ->first();

        $transactions = DB::table('wallet_transactions')
            ->leftJoin(
                'labels',
                'labels.id',
                '=',
                'wallet_transactions.label_id'
            )
            ->leftJoin(
                'wallets',
                'wallets.id',
                '=',
                'wallet_transactions.wallet_id'
            )
            ->when(
                $filters['label_id'] !== '',
                fn ($query) => $query->where(
                    'wallet_transactions.label_id',
                    $filters['label_id']
                )
            )
            ->when(
                $filters['currency'] !== '',
                fn ($query) => $query->where(
                    'wallet_transactions.currency',
                    $filters['currency']
                )
            )
            ->select([
                'wallet_transactions.id',
                'wallet_transactions.public_id',
                'wallet_transactions.transaction_type',
                'wallet_transactions.direction',
                'wallet_transactions.amount',
                'wallet_transactions.currency',
                'wallet_transactions.balance_before',
                'wallet_transactions.balance_after',
                'wallet_transactions.reference_type',
                'wallet_transactions.reference_id',
                'wallet_transactions.description',
                'wallet_transactions.status',
                'wallet_transactions.effective_at',
                'wallet_transactions.posted_at',
                'wallet_transactions.created_at',
                'labels.name as label_name',
                'wallets.public_id as wallet_public_id',
            ])
            ->orderByDesc('wallet_transactions.id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Wallet/Index', [
            'wallets' => $walletQuery
                ->orderByDesc('wallets.available_balance')
                ->paginate(20, ['*'], 'wallet_page')
                ->withQueryString(),

            'transactions' => $transactions,

            'summary' => [
                'total_wallets' => (int) (
                    $summary->total_wallets ?? 0
                ),
                'available_balance' => (float) (
                    $summary->available_balance ?? 0
                ),
                'pending_balance' => (float) (
                    $summary->pending_balance ?? 0
                ),
                'lifetime_credits' => (float) (
                    $summary->lifetime_credits ?? 0
                ),
                'lifetime_debits' => (float) (
                    $summary->lifetime_debits ?? 0
                ),
                'currency' => $filters['currency'] ?: 'INR',
            ],

            'filterOptions' => [
                'labels' => DB::table('labels')
                    ->select('id', 'name')
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->get(),

                'currencies' => DB::table('wallets')
                    ->distinct()
                    ->orderBy('currency')
                    ->pluck('currency')
                    ->values(),
            ],

            'filters' => $filters,
        ]);
    }
}
