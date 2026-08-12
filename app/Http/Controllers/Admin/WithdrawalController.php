<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RuntimeException;

class WithdrawalController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
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

        $query = DB::table('withdrawals')
            ->leftJoin(
                'wallets',
                'wallets.id',
                '=',
                'withdrawals.wallet_id'
            )
            ->leftJoin(
                'labels',
                'labels.id',
                '=',
                'withdrawals.label_id'
            )
            ->select([
                'withdrawals.id',
                'withdrawals.public_id',
                'withdrawals.withdrawal_number',
                'withdrawals.wallet_id',
                'withdrawals.label_id',
                'withdrawals.amount',
                'withdrawals.currency',
                'withdrawals.status',
                'withdrawals.payment_method',
                'withdrawals.payment_reference',
                'withdrawals.note',
                'withdrawals.requested_at',
                'withdrawals.approved_at',
                'withdrawals.paid_at',
                'withdrawals.rejected_at',
                'withdrawals.created_at',
                'labels.name as label_name',
                'wallets.available_balance',
            ]);

        if ($filters['status'] !== '') {
            $query->where(
                'withdrawals.status',
                $filters['status']
            );
        }

        if ($filters['label_id'] !== '') {
            $query->where(
                'withdrawals.label_id',
                $filters['label_id']
            );
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where(
                        'withdrawals.withdrawal_number',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'labels.name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'withdrawals.payment_reference',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        $summaryQuery = clone $query;

        $summary = DB::query()
            ->fromSub($summaryQuery, 'withdrawal_rows')
            ->selectRaw('
                COUNT(*) as total_requests,
                COALESCE(SUM(
                    CASE WHEN status = "pending"
                    THEN amount ELSE 0 END
                ), 0) as pending_amount,
                COALESCE(SUM(
                    CASE WHEN status = "approved"
                    THEN amount ELSE 0 END
                ), 0) as approved_amount,
                COALESCE(SUM(
                    CASE WHEN status = "paid"
                    THEN amount ELSE 0 END
                ), 0) as paid_amount
            ')
            ->first();

        return Inertia::render('Admin/Withdrawals/Index', [
            'withdrawals' => $query
                ->orderByDesc('withdrawals.id')
                ->paginate(25)
                ->withQueryString(),

            'wallets' => DB::table('wallets')
                ->leftJoin(
                    'labels',
                    'labels.id',
                    '=',
                    'wallets.label_id'
                )
                ->where('wallets.status', 'active')
                ->where('wallets.available_balance', '>', 0)
                ->select([
                    'wallets.id',
                    'wallets.label_id',
                    'wallets.currency',
                    'wallets.available_balance',
                    'labels.name as label_name',
                ])
                ->orderBy('labels.name')
                ->get(),

            'summary' => [
                'total_requests' => (int) (
                    $summary->total_requests ?? 0
                ),
                'pending_amount' => (float) (
                    $summary->pending_amount ?? 0
                ),
                'approved_amount' => (float) (
                    $summary->approved_amount ?? 0
                ),
                'paid_amount' => (float) (
                    $summary->paid_amount ?? 0
                ),
                'currency' => 'INR',
            ],

            'filterOptions' => [
                'labels' => DB::table('labels')
                    ->select('id', 'name')
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->get(),

                'statuses' => [
                    'pending',
                    'approved',
                    'processing',
                    'paid',
                    'rejected',
                    'cancelled',
                ],
            ],

            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'wallet_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => [
                'nullable',
                'string',
                'max:50',
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $wallet = DB::table('wallets')
                ->where('id', $validated['wallet_id'])
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new RuntimeException('Wallet nahi mila.');
            }

            $amount = round(
                (float) $validated['amount'],
                8
            );

            if ($amount > (float) $wallet->available_balance) {
                throw new RuntimeException(
                    'Withdrawal amount available balance se zyada hai.'
                );
            }

            $pendingExists = DB::table('withdrawals')
                ->where('wallet_id', $wallet->id)
                ->whereIn('status', [
                    'pending',
                    'approved',
                    'processing',
                ])
                ->exists();

            if ($pendingExists) {
                throw new RuntimeException(
                    'Is wallet ki ek active withdrawal request pehle se hai.'
                );
            }

            $nextSequence = DB::table('withdrawals')
                ->whereYear('created_at', now()->year)
                ->lockForUpdate()
                ->count() + 1;

            $withdrawalNumber = sprintf(
                'WD-%s-%06d',
                now()->format('Ym'),
                $nextSequence
            );

            $balanceBefore = round(
                (float) $wallet->available_balance,
                8
            );

            $balanceAfter = round(
                $balanceBefore - $amount,
                8
            );

            $pendingAfter = round(
                (float) $wallet->pending_balance + $amount,
                8
            );

            $withdrawalId = DB::table('withdrawals')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'withdrawal_number' => $withdrawalNumber,
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'artist_id' => $wallet->artist_id,
                'label_id' => $wallet->label_id,
                'amount' => $amount,
                'currency' => $wallet->currency,
                'status' => 'pending',
                'payment_method' =>
                    $validated['payment_method'] ?? null,
                'note' => $validated['note'] ?? null,
                'requested_at' => now(),
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('wallets')
                ->where('id', $wallet->id)
                ->update([
                    'available_balance' => $balanceAfter,
                    'pending_balance' => $pendingAfter,
                    'updated_at' => now(),
                ]);

            DB::table('wallet_transactions')->insert([
                'public_id' => (string) Str::ulid(),
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id
                    ?? $request->user()?->id
                    ?? 1,
                'artist_id' => $wallet->artist_id,
                'label_id' => $wallet->label_id,
                'transaction_type' => 'withdrawal_hold',
                'direction' => 'debit',
                'amount' => $amount,
                'currency' => $wallet->currency,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => 'withdrawal',
                'reference_id' => $withdrawalId,
                'description' => sprintf(
                    'Amount reserved for withdrawal %s',
                    $withdrawalNumber
                ),
                'status' => 'pending',
                'effective_at' => now(),
                'posted_at' => null,
                'metadata' => json_encode([
                    'withdrawal_number' => $withdrawalNumber,
                    'action' => 'hold',
                ]),
                'created_by' => $request->user()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with(
            'success',
            'Withdrawal request create ho gayi.'
        );
    }

    public function approve(Request $request, int $id)
    {
        $updated = DB::table('withdrawals')
            ->where('id', $id)
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
                'updated_at' => now(),
            ]);

        if (!$updated) {
            return back()->withErrors([
                'status' => 'Sirf pending withdrawal approve ho sakti hai.',
            ]);
        }

        return back()->with('success', 'Withdrawal approved.');
    }

    public function processing(Request $request, int $id)
    {
        $updated = DB::table('withdrawals')
            ->where('id', $id)
            ->where('status', 'approved')
            ->update([
                'status' => 'processing',
                'updated_by' => $request->user()?->id,
                'updated_at' => now(),
            ]);

        if (!$updated) {
            return back()->withErrors([
                'status' => 'Sirf approved withdrawal processing me ja sakti hai.',
            ]);
        }

        return back()->with('success', 'Withdrawal marked as processing.');
    }

    public function reject(Request $request, int $id)
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($id, $validated, $request) {
            $withdrawal = DB::table('withdrawals')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$withdrawal) {
                throw new RuntimeException('Withdrawal nahi mili.');
            }

            if (!in_array(
                $withdrawal->status,
                ['pending', 'approved', 'processing'],
                true
            )) {
                throw new RuntimeException(
                    'Is withdrawal ko reject nahi kiya ja sakta.'
                );
            }

            $wallet = DB::table('wallets')
                ->where('id', $withdrawal->wallet_id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new RuntimeException('Wallet nahi mila.');
            }

            $amount = round((float) $withdrawal->amount, 8);
            $before = round((float) $wallet->available_balance, 8);
            $after = round($before + $amount, 8);
            $pending = max(
                0,
                round((float) $wallet->pending_balance - $amount, 8)
            );

            DB::table('wallets')
                ->where('id', $wallet->id)
                ->update([
                    'available_balance' => $after,
                    'pending_balance' => $pending,
                    'updated_at' => now(),
                ]);

            DB::table('wallet_transactions')->insert([
                'public_id' => (string) Str::ulid(),
                'wallet_id' => $wallet->id,
                'user_id' => $withdrawal->user_id
                    ?? $request->user()?->id
                    ?? 1,
                'artist_id' => $withdrawal->artist_id,
                'label_id' => $withdrawal->label_id,
                'transaction_type' => 'withdrawal_release',
                'direction' => 'credit',
                'amount' => $amount,
                'currency' => $withdrawal->currency,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => 'withdrawal',
                'reference_id' => $withdrawal->id,
                'description' => sprintf(
                    'Reserved amount released for rejected withdrawal %s',
                    $withdrawal->withdrawal_number
                ),
                'status' => 'posted',
                'effective_at' => now(),
                'posted_at' => now(),
                'metadata' => json_encode([
                    'withdrawal_number' =>
                        $withdrawal->withdrawal_number,
                    'action' => 'release',
                ]),
                'created_by' => $request->user()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('wallet_transactions')
                ->where('reference_type', 'withdrawal')
                ->where('reference_id', $withdrawal->id)
                ->where('transaction_type', 'withdrawal_hold')
                ->update([
                    'status' => 'reversed',
                    'updated_at' => now(),
                ]);

            DB::table('withdrawals')
                ->where('id', $withdrawal->id)
                ->update([
                    'status' => 'rejected',
                    'note' => $validated['note']
                        ?? $withdrawal->note,
                    'rejected_at' => now(),
                    'updated_by' => $request->user()?->id,
                    'updated_at' => now(),
                ]);
        });

        return back()->with('success', 'Withdrawal rejected and amount released.');
    }

    public function paid(Request $request, int $id)
    {
        $validated = $request->validate([
            'payment_reference' => [
                'required',
                'string',
                'max:100',
            ],
            'payment_method' => [
                'nullable',
                'string',
                'max:50',
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($id, $validated, $request) {
            $withdrawal = DB::table('withdrawals')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$withdrawal) {
                throw new RuntimeException('Withdrawal nahi mili.');
            }

            if (!in_array(
                $withdrawal->status,
                ['approved', 'processing'],
                true
            )) {
                throw new RuntimeException(
                    'Sirf approved ya processing withdrawal paid ho sakti hai.'
                );
            }

            $wallet = DB::table('wallets')
                ->where('id', $withdrawal->wallet_id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new RuntimeException('Wallet nahi mila.');
            }

            $amount = round((float) $withdrawal->amount, 8);
            $available = round(
                (float) $wallet->available_balance,
                8
            );

            $pendingAfter = max(
                0,
                round((float) $wallet->pending_balance - $amount, 8)
            );

            DB::table('wallets')
                ->where('id', $wallet->id)
                ->update([
                    'pending_balance' => $pendingAfter,
                    'lifetime_debits' => DB::raw(
                        'lifetime_debits + ' . $amount
                    ),
                    'updated_at' => now(),
                ]);

            DB::table('wallet_transactions')
                ->where('reference_type', 'withdrawal')
                ->where('reference_id', $withdrawal->id)
                ->where('transaction_type', 'withdrawal_hold')
                ->update([
                    'status' => 'posted',
                    'posted_at' => now(),
                    'updated_at' => now(),
                ]);

            /*
             * Settlement does not create a second monetary debit.
             * withdrawal_hold remains the canonical wallet debit.
             */

            DB::table('withdrawals')
                ->where('id', $withdrawal->id)
                ->update([
                    'status' => 'paid',
                    'payment_method' =>
                        $validated['payment_method']
                        ?? $withdrawal->payment_method,
                    'payment_reference' =>
                        $validated['payment_reference'],
                    'note' => $validated['note']
                        ?? $withdrawal->note,
                    'paid_at' => now(),
                    'updated_by' => $request->user()?->id,
                    'updated_at' => now(),
                ]);
        });

        return back()->with('success', 'Withdrawal marked as paid.');
    }


}
