<?php

namespace App\Services\V2;

use App\Services\Invoices\InvoiceGenerationService;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WithdrawalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public const DEFAULT_MINIMUM_AMOUNT = 1000;

    public function __construct(
        private readonly WalletService $walletService,
        private readonly InvoiceGenerationService $invoiceGenerationService
    ) {
    }

    public function create(
        User $user,
        float $amount,
        string $paymentMethod,
        ?string $note = null,
        ?User $actor = null
    ): WithdrawalRequest {
        /*
         * WITHDRAWAL_REQUEST_ACTOR
         *
         * $user  = financial owner of wallet/payout profile.
         * $actor = authenticated person who initiated request.
         *
         * Existing callers remain backward compatible:
         * when no actor is supplied, financial owner is actor.
         */
        $actor ??= $user;


        $amount = round($amount, 8);

        $minimumAmount =
            $this->minimumAmountFor(
                $user
            );

        if ($amount < $minimumAmount) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Minimum withdrawal amount is ₹'
                    . number_format(
                        $minimumAmount,
                        2,
                        '.',
                        ','
                    )
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
            $note,
                $actor) {
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
                        $actor->id,

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
                        $actor->id,

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

    public function deleteOpenWithdrawal(
        WithdrawalRequest $withdrawal,
        User $admin
    ): void {
        abort_unless(
            in_array(
                $withdrawal->status,
                [
                    'pending',
                    'approved',
                ],
                true
            ),
            422,
            'Only pending or approved withdrawals can be deleted.'
        );

        DB::transaction(function () use (
            $withdrawal,
            $admin
        ) {
            $withdrawal->refresh();

            abort_unless(
                in_array(
                    $withdrawal->status,
                    [
                        'pending',
                        'approved',
                    ],
                    true
                ),
                422,
                'Withdrawal status changed and can no longer be deleted.'
            );

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
                (float) $wallet->available_balance,
                8
            );

            $pendingBefore = round(
                (float) $wallet->pending_balance,
                8
            );

            abort_unless(
                $pendingBefore >= $amount,
                422,
                'Reserved withdrawal balance is insufficient.'
            );

            $availableAfter = round(
                $availableBefore + $amount,
                8
            );

            $pendingAfter = round(
                $pendingBefore - $amount,
                8
            );

            DB::table('wallets')
                ->where(
                    'id',
                    $wallet->id
                )
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
                ->where(
                    'status',
                    'pending'
                )
                ->update([
                    'status' =>
                        'reversed',

                    'updated_at' =>
                        now(),
                ]);

            /*
             * Older records can contain a posted hold
             * before payout completion. Deletion is only
             * allowed before payment, so reverse that hold
             * as well when encountered.
             */
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
                ->where(
                    'status',
                    'posted'
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
                        'Reserved amount released for deleted withdrawal '
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
                                $withdrawal->withdrawal_number,

                            'action' =>
                                'delete_release',

                            'deleted_by' =>
                                $admin->id,
                        ]),

                    'created_by' =>
                        $admin->id,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

            $metadata =
                $withdrawal->metadata ?? [];

            $metadata['deleted_by'] =
                $admin->id;

            $metadata['deleted_from_status'] =
                $withdrawal->status;

            $metadata['deleted_at'] =
                now()->toIso8601String();

            $withdrawal->update([
                'updated_by' =>
                    $admin->id,

                'metadata' =>
                    $metadata,
            ]);

            /*
             * Soft delete preserves the financial audit
             * trail while removing the request from all
             * operational withdrawal screens.
             */
            $withdrawal->delete();
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

            /*
             * Settlement does not create a second monetary debit.
             * The withdrawal_hold transaction is the canonical debit.
             * On payment, that hold is posted and pending balance is
             * released from reservation into completed payout state.
             */

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

            /*
             * AUTO FINANCIAL DOCUMENT
             *
             * This executes inside the same logical
             * payment transaction.
             *
             * If document generation fails, the
             * exception bubbles up and the withdrawal
             * payment transaction is rolled back.
             *
             * InvoiceGenerationService is idempotent
             * by withdrawal_id and the database also
             * has a UNIQUE constraint on that field.
             */
            $this->invoiceGenerationService
                ->generateFromWithdrawal(
                    $withdrawal->id,
                    $admin->id
                );

            return $withdrawal->fresh();
        });
    }

    public function minimumAmountFor(
        User $user
    ): float {
        /*
         * Label accounts use their own configured
         * minimum withdrawal amount.
         */
        $labelMinimum = DB::table('labels')
            ->where(
                'user_id',
                $user->id
            )
            ->whereNull('deleted_at')
            ->value(
                'minimum_withdrawal_amount'
            );

        if ($labelMinimum !== null) {
            return max(
                0,
                (float) $labelMinimum
            );
        }

        /*
         * Artist accounts inherit the minimum from
         * their parent/master label when available.
         */
        $artist = DB::table('artists')
            ->where(
                'user_id',
                $user->id
            )
            ->whereNull('deleted_at')
            ->first([
                'id',
                'label_id',
            ]);

        if (
            $artist &&
            $artist->label_id
        ) {
            $artistMinimum = DB::table('labels')
                ->where(
                    'id',
                    $artist->label_id
                )
                ->whereNull('deleted_at')
                ->value(
                    'minimum_withdrawal_amount'
                );

            if ($artistMinimum !== null) {
                return max(
                    0,
                    (float) $artistMinimum
                );
            }
        }

        return (float)
            self::DEFAULT_MINIMUM_AMOUNT;
    }

}
