<?php

namespace App\Services\V2;

use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WithdrawalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public const MINIMUM_AMOUNT = 1000;

    public function __construct(
        private readonly WalletService $walletService
    ) {
    }

    public function create(
        User $user,
        float $amount,
        string $paymentMethod,
        ?string $note = null
    ): WithdrawalRequest {
        $amount = round($amount, 8);

        if ($amount < self::MINIMUM_AMOUNT) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Minimum withdrawal amount is ₹'
                    . self::MINIMUM_AMOUNT
                    . '.',
            ]);
        }

        $profile = PayoutProfile::query()
            ->where('user_id', $user->id)
            ->first();

        if (!$profile) {
            throw ValidationException::withMessages([
                'payout_profile' =>
                    'Complete your payout and KYC profile first.',
            ]);
        }

        if ($profile->kyc_status !== 'verified') {
            throw ValidationException::withMessages([
                'kyc' =>
                    'KYC verification must be completed before withdrawal.',
            ]);
        }

        if (
            $paymentMethod === 'bank'
            && (
                !$profile->bank_account_number
                || !$profile->ifsc_code
            )
        ) {
            throw ValidationException::withMessages([
                'payment_method' =>
                    'Verified bank details are required.',
            ]);
        }

        if (
            $paymentMethod === 'upi'
            && !$profile->upi_id
        ) {
            throw ValidationException::withMessages([
                'payment_method' =>
                    'UPI ID is required.',
            ]);
        }

        $wallet = $this->walletService
            ->account($user);

        return DB::transaction(function () use (
            $user,
            $wallet,
            $profile,
            $amount,
            $paymentMethod,
            $note
        ) {
            $lockedWallet = DB::table('wallets')
                ->where('id', $wallet->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedWallet) {
                throw ValidationException::withMessages([
                    'wallet' =>
                        'Wallet account was not found.',
                ]);
            }

            if (
                (float) $lockedWallet
                    ->available_balance
                < $amount
            ) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'Available wallet balance is insufficient.',
                ]);
            }

            $hasPending = WithdrawalRequest::query()
                ->where('wallet_id', $lockedWallet->id)
                ->whereIn(
                    'status',
                    [
                        'pending',
                        'approved',
                        'processing',
                    ]
                )
                ->exists();

            if ($hasPending) {
                throw ValidationException::withMessages([
                    'withdrawal' =>
                        'A withdrawal request is already being processed.',
                ]);
            }

            $sequence = WithdrawalRequest::query()
                ->whereYear(
                    'created_at',
                    now()->year
                )
                ->lockForUpdate()
                ->count() + 1;

            $number = sprintf(
                'WD-%s-%06d',
                now()->format('Ym'),
                $sequence
            );

            $availableBefore = round(
                (float) $lockedWallet
                    ->available_balance,
                8
            );

            $availableAfter = round(
                $availableBefore - $amount,
                8
            );

            $pendingAfter = round(
                (float) $lockedWallet
                    ->pending_balance
                + $amount,
                8
            );

            $withdrawal = WithdrawalRequest::query()
                ->create([
                    'public_id' =>
                        (string) Str::ulid(),

                    'withdrawal_number' =>
                        $number,

                    'wallet_id' =>
                        $lockedWallet->id,

                    'user_id' =>
                        $user->id,

                    'artist_id' =>
                        $lockedWallet->artist_id,

                    'label_id' =>
                        $lockedWallet->label_id,

                    'amount' =>
                        $amount,

                    'currency' =>
                        $lockedWallet->currency,

                    'status' =>
                        'pending',

                    'payment_method' =>
                        $paymentMethod,

                    'note' =>
                        $note,

                    'requested_at' =>
                        now(),

                    'created_by' =>
                        $user->id,

                    'updated_by' =>
                        $user->id,

                    'metadata' => [
                        'payout_profile_id' =>
                            $profile->id,

                        'request_source' =>
                            'v2',
                    ],
                ]);

            DB::table('wallets')
                ->where('id', $lockedWallet->id)
                ->update([
                    'available_balance' =>
                        $availableAfter,

                    'pending_balance' =>
                        $pendingAfter,

                    'updated_at' =>
                        now(),
                ]);

            DB::table('wallet_transactions')
                ->insert([
                    'public_id' =>
                        (string) Str::ulid(),

                    'wallet_id' =>
                        $lockedWallet->id,

                    'user_id' =>
                        $user->id,

                    'artist_id' =>
                        $lockedWallet->artist_id,

                    'label_id' =>
                        $lockedWallet->label_id,

                    'transaction_type' =>
                        'withdrawal_hold',

                    'direction' =>
                        'debit',

                    'amount' =>
                        $amount,

                    'currency' =>
                        $lockedWallet->currency,

                    'balance_before' =>
                        $availableBefore,

                    'balance_after' =>
                        $availableAfter,

                    'reference_type' =>
                        'withdrawal',

                    'reference_id' =>
                        $withdrawal->id,

                    'description' =>
                        "Amount reserved for withdrawal {$number}",

                    'status' =>
                        'pending',

                    'effective_at' =>
                        now(),

                    'posted_at' =>
                        null,

                    'metadata' =>
                        json_encode([
                            'withdrawal_number' =>
                                $number,

                            'action' =>
                                'hold',
                        ]),

                    'created_by' =>
                        $user->id,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

            return $withdrawal->fresh();
        });
    }

    public function approve(
        WithdrawalRequest $withdrawal,
        User $admin,
        ?string $note = null
    ): WithdrawalRequest {
        abort_unless(
            $withdrawal->status === 'pending',
            422,
            'Only pending requests can be approved.'
        );

        $metadata = $withdrawal->metadata
            ?? [];

        if ($note !== null) {
            $metadata['admin_note'] = $note;
        }

        $withdrawal->update([
            'status' =>
                'approved',

            'approved_at' =>
                now(),

            'approved_by' =>
                $admin->id,

            'updated_by' =>
                $admin->id,

            'metadata' =>
                $metadata,
        ]);

        return $withdrawal->fresh();
    }

    public function reject(
        WithdrawalRequest $withdrawal,
        User $admin,
        string $reason
    ): WithdrawalRequest {
        abort_unless(
            in_array(
                $withdrawal->status,
                [
                    'pending',
                    'approved',
                    'processing',
                ],
                true
            ),
            422,
            'This request cannot be rejected.'
        );

        return DB::transaction(function () use (
            $withdrawal,
            $admin,
            $reason
        ) {
            $withdrawal->refresh();

            $wallet = DB::table('wallets')
                ->where(
                    'id',
                    $withdrawal->wallet_id
                )
                ->lockForUpdate()
                ->first();

            abort_unless(
                $wallet,
                422,
                'Withdrawal wallet is missing.'
            );

            $amount = round(
                (float) $withdrawal->amount,
                8
            );

            $availableBefore = round(
                (float) $wallet
                    ->available_balance,
                8
            );

            $availableAfter = round(
                $availableBefore + $amount,
                8
            );

            $pendingAfter = max(
                0,
                round(
                    (float) $wallet
                        ->pending_balance
                    - $amount,
                    8
                )
            );

            DB::table('wallets')
                ->where('id', $wallet->id)
                ->update([
                    'available_balance' =>
                        $availableAfter,

                    'pending_balance' =>
                        $pendingAfter,

                    'updated_at' =>
                        now(),
                ]);

            DB::table('wallet_transactions')
                ->where(
                    'reference_type',
                    'withdrawal'
                )
                ->where(
                    'reference_id',
                    $withdrawal->id
                )
                ->where(
                    'transaction_type',
                    'withdrawal_hold'
                )
                ->update([
                    'status' =>
                        'reversed',

                    'updated_at' =>
                        now(),
                ]);

            DB::table('wallet_transactions')
                ->insert([
                    'public_id' =>
                        (string) Str::ulid(),

                    'wallet_id' =>
                        $wallet->id,

                    'user_id' =>
                        $withdrawal->user_id,

                    'artist_id' =>
                        $withdrawal->artist_id,

                    'label_id' =>
                        $withdrawal->label_id,

                    'transaction_type' =>
                        'withdrawal_release',

                    'direction' =>
                        'credit',

                    'amount' =>
                        $amount,

                    'currency' =>
                        $withdrawal->currency,

                    'balance_before' =>
                        $availableBefore,

                    'balance_after' =>
                        $availableAfter,

                    'reference_type' =>
                        'withdrawal',

                    'reference_id' =>
                        $withdrawal->id,

                    'description' =>
                        'Reserved amount released for rejected withdrawal '
                        . $withdrawal->withdrawal_number,

                    'status' =>
                        'posted',

                    'effective_at' =>
                        now(),

                    'posted_at' =>
                        now(),

                    'metadata' =>
                        json_encode([
                            'withdrawal_number' =>
                                $withdrawal
                                    ->withdrawal_number,

                            'action' =>
                                'release',

                            'reason' =>
                                $reason,
                        ]),

                    'created_by' =>
                        $admin->id,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

            $metadata = $withdrawal->metadata
                ?? [];

            $metadata['rejection_reason'] =
                $reason;

            $withdrawal->update([
                'status' =>
                    'rejected',

                'note' =>
                    $reason,

                'rejected_at' =>
                    now(),

                'updated_by' =>
                    $admin->id,

                'metadata' =>
                    $metadata,
            ]);

            return $withdrawal->fresh();
        });
    }

    public function markPaid(
        WithdrawalRequest $withdrawal,
        User $admin,
        string $paymentReference,
        ?string $note = null
    ): WithdrawalRequest {
        abort_unless(
            in_array(
                $withdrawal->status,
                [
                    'approved',
                    'processing',
                ],
                true
            ),
            422,
            'Withdrawal must be approved first.'
        );

        return DB::transaction(function () use (
            $withdrawal,
            $admin,
            $paymentReference,
            $note
        ) {
            $withdrawal->refresh();

            $wallet = DB::table('wallets')
                ->where(
                    'id',
                    $withdrawal->wallet_id
                )
                ->lockForUpdate()
                ->first();

            abort_unless(
                $wallet,
                422,
                'Withdrawal wallet is missing.'
            );

            $amount = round(
                (float) $withdrawal->amount,
                8
            );

            if (
                (float) $wallet
                    ->pending_balance
                < $amount
            ) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'Reserved withdrawal balance is insufficient.',
                ]);
            }

            $pendingAfter = round(
                (float) $wallet
                    ->pending_balance
                - $amount,
                8
            );

            DB::table('wallets')
                ->where('id', $wallet->id)
                ->update([
                    'pending_balance' =>
                        $pendingAfter,

                    'lifetime_debits' =>
                        round(
                            (float) $wallet
                                ->lifetime_debits
                            + $amount,
                            8
                        ),

                    'updated_at' =>
                        now(),
                ]);

            DB::table('wallet_transactions')
                ->where(
                    'reference_type',
                    'withdrawal'
                )
                ->where(
                    'reference_id',
                    $withdrawal->id
                )
                ->where(
                    'transaction_type',
                    'withdrawal_hold'
                )
                ->update([
                    'status' =>
                        'posted',

                    'posted_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

            DB::table('wallet_transactions')
                ->insert([
                    'public_id' =>
                        (string) Str::ulid(),

                    'wallet_id' =>
                        $wallet->id,

                    'user_id' =>
                        $withdrawal->user_id,

                    'artist_id' =>
                        $withdrawal->artist_id,

                    'label_id' =>
                        $withdrawal->label_id,

                    'transaction_type' =>
                        'withdrawal_paid',

                    'direction' =>
                        'debit',

                    'amount' =>
                        $amount,

                    'currency' =>
                        $withdrawal->currency,

                    'balance_before' =>
                        (float) $wallet
                            ->available_balance,

                    'balance_after' =>
                        (float) $wallet
                            ->available_balance,

                    'reference_type' =>
                        'withdrawal',

                    'reference_id' =>
                        $withdrawal->id,

                    'description' =>
                        'Withdrawal '
                        . $withdrawal
                            ->withdrawal_number
                        . ' paid',

                    'status' =>
                        'posted',

                    'effective_at' =>
                        now(),

                    'posted_at' =>
                        now(),

                    'metadata' =>
                        json_encode([
                            'withdrawal_number' =>
                                $withdrawal
                                    ->withdrawal_number,

                            'payment_reference' =>
                                $paymentReference,
                        ]),

                    'created_by' =>
                        $admin->id,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

            $metadata = $withdrawal->metadata
                ?? [];

            if ($note !== null) {
                $metadata['admin_note'] =
                    $note;
            }

            $withdrawal->update([
                'status' =>
                    'paid',

                'payment_reference' =>
                    $paymentReference,

                'paid_at' =>
                    now(),

                'updated_by' =>
                    $admin->id,

                'metadata' =>
                    $metadata,
            ]);

            return $withdrawal->fresh();
        });
    }
}
