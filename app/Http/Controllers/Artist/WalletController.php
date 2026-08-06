<?php

namespace App\Http\Controllers\Artist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $artist = DB::table('artists')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();

        abort_if(!$artist, 404, 'Artist profile not linked.');

        $wallet = DB::table('wallets')
            ->where('artist_id', $artist->id)
            ->where('status', 'active')
            ->first();

        $transactions = DB::table('wallet_transactions')
            ->where('artist_id', $artist->id)
            ->select([
                'id',
                'public_id',
                'transaction_type',
                'direction',
                'amount',
                'currency',
                'balance_before',
                'balance_after',
                'description',
                'status',
                'effective_at',
                'posted_at',
                'created_at',
            ])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Artist/Wallet/Index', [
            'artist' => [
                'id' => $artist->id,
                'stage_name' => $artist->stage_name,
                'currency' => $artist->currency ?: 'INR',
            ],

            'wallet' => [
                'id' => $wallet->id ?? null,
                'public_id' => $wallet->public_id ?? null,
                'currency' => $wallet->currency
                    ?? $artist->currency
                    ?? 'INR',
                'available_balance' => (float) (
                    $wallet->available_balance ?? 0
                ),
                'pending_balance' => (float) (
                    $wallet->pending_balance ?? 0
                ),
                'lifetime_credits' => (float) (
                    $wallet->lifetime_credits ?? 0
                ),
                'lifetime_debits' => (float) (
                    $wallet->lifetime_debits ?? 0
                ),
                'status' => $wallet->status ?? 'active',
            ],

            'transactions' => $transactions,
        ]);
    }
}
