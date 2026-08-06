<?php

namespace App\Http\Controllers\Artist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
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

        $releaseQuery = DB::table('releases')
            ->where('artist_id', $artist->id)
            ->whereNull('deleted_at');

        $totalReleases = (clone $releaseQuery)->count();

        $liveReleases = (clone $releaseQuery)
            ->where(function ($query) {
                $query
                    ->whereNotNull('live_at')
                    ->orWhereIn('status', [
                        'approved',
                        'delivered',
                        'live',
                    ]);
            })
            ->count();

        $pendingWithdrawals = DB::table('withdrawals')
            ->where('artist_id', $artist->id)
            ->whereIn('status', [
                'pending',
                'approved',
                'processing',
            ])
            ->count();

        $recentReleases = (clone $releaseQuery)
            ->select([
                'id',
                'public_id',
                'title',
                'release_type',
                'status',
                'artwork_path',
                'digital_release_date',
                'created_at',
            ])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentTransactions = DB::table('wallet_transactions')
            ->where('artist_id', $artist->id)
            ->select([
                'id',
                'transaction_type',
                'direction',
                'amount',
                'currency',
                'balance_after',
                'description',
                'status',
                'posted_at',
                'created_at',
            ])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentWithdrawals = DB::table('withdrawals')
            ->where('artist_id', $artist->id)
            ->select([
                'id',
                'withdrawal_number',
                'amount',
                'currency',
                'status',
                'requested_at',
                'paid_at',
            ])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return Inertia::render('Artist/Dashboard', [
            'artist' => [
                'id' => $artist->id,
                'stage_name' => $artist->stage_name,
                'legal_name' => $artist->legal_name,
                'email' => $artist->email,
                'currency' => $artist->currency ?: 'INR',
                'kyc_status' => $artist->kyc_status,
                'account_status' => $artist->account_status,
            ],

            'stats' => [
                'total_releases' => $totalReleases,
                'live_releases' => $liveReleases,
                'wallet_balance' => (float) (
                    $wallet->available_balance ?? 0
                ),
                'pending_balance' => (float) (
                    $wallet->pending_balance ?? 0
                ),
                'pending_withdrawals' => $pendingWithdrawals,
                'currency' => $wallet->currency
                    ?? $artist->currency
                    ?? 'INR',
            ],

            'recentReleases' => $recentReleases,
            'recentTransactions' => $recentTransactions,
            'recentWithdrawals' => $recentWithdrawals,
        ]);
    }
}
