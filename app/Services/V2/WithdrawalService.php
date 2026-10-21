<?php

namespace App\Services\V2;

use App\Services\Invoices\InvoiceGenerationService;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WithdrawalRequest;
use App\Models\Core\Artist;
use App\Models\Core\Label;
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

    public function withdrawalEnabledFor(
        User $user
    ): bool {
        /*
         * Wallets and payout profiles are User-level.
         *
         * Therefore withdrawal permission is also resolved
         * at the financial-owner User account level.
         *
         * Every active Label / Artist entity belonging to
         * the same financial owner must remain enabled.
         * Super Admin update paths synchronize these flags.
         */
        $hasSuspendedLabel = Label::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->where('can_withdraw', false)
            ->exists();

        if ($hasSuspendedLabel) {
            return false;
        }

        $hasSuspendedArtist = Artist::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->where('can_withdraw', false)
            ->exists();

        return ! $hasSuspendedArtist;
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

        if (! $this->withdrawalEnabledFor($user)) {
            throw ValidationException::withMessages([
                'withdrawal' =>
                    'Withdrawals are currently suspended for this account.',
            ]);
        }


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
            (
                !$profile->bank_account_number
                || !$profile->ifsc_code
            )
        ) {
            throw ValidationException::withMessages([
                'payment_method' =>
                    'Verified bank details are required.',
            ]);
        }

        /* MIXXTUNE_BANK_ONLY_PAYOUTS_V1: royalties are paid only to a bank account. */
        if ($paymentMethod !== 'bank') {
            throw ValidationException::withMessages([
                'payment_method' =>
                    'Payouts are made only to a bank account.',
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

            /* MIXXTUNE_WITHDRAWALS_V2: a deleted request lowers the count, so the
             * next number could repeat an existing one (unique index -> error). */
            while (WithdrawalRequest::query()->where('withdrawal_number', $number)->exists()) {
                $sequence++;
                $number = sprintf('WD-%s-%06d', now()->format('Ym'), $sequence);
            }

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

                    'financial_month' =>
                        $withdrawal->created_at
                        ? \Illuminate\Support\Carbon::parse($withdrawal->created_at)
                            ->startOfMonth()
                            ->toDateString()
                        : now()->startOfMonth()->toDateString(),

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

        /* MIXXTUNE_WITHDRAWALS_V2: approve only what can really be paid */
        $profile = PayoutProfile::query()->where('user_id', $withdrawal->user_id)->first();

        if (!$profile || $profile->kyc_status !== 'verified') {
            throw ValidationException::withMessages([
                'withdrawal' => 'KYC of this owner is "'.($profile?->kyc_status ?: 'missing').'". Verify it in KYC Reviews first (or reject this request).',
            ]);
        }

        if (!$profile->bank_account_number || !$profile->ifsc_code) {
            throw ValidationException::withMessages([
                'withdrawal' => 'This owner has no bank account / IFSC. Add them on the user page first.',
            ]);
        }

        if ($withdrawal->user && !$this->withdrawalEnabledFor($withdrawal->user)) {
            throw ValidationException::withMessages([
                'withdrawal' => 'Withdrawals are suspended for this owner (label / artist setting).',
            ]);
        }

        return DB::transaction(function () use ($withdrawal, $admin, $note) {
            $locked = WithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->first();

            abort_unless($locked && $locked->status === 'pending', 422, 'This request was changed by someone else. Reload the page.');

            $withdrawal->setRawAttributes($locked->getAttributes(), true);

            return $this->approveLocked($withdrawal, $admin, $note);
        });
    }

    private function approveLocked(
        WithdrawalRequest $withdrawal,
        User $admin,
        ?string $note
    ): WithdrawalRequest {
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
            /* MIXXTUNE_WITHDRAWALS_V2: lock the request and check again (two people / double click) */
            WithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->first();
            $withdrawal->refresh();

            abort_unless(
                in_array($withdrawal->status, ['pending', 'approved', 'processing'], true),
                422,
                'This request was changed by someone else (now '.$withdrawal->status.'). Reload the page.'
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

                    'financial_month' =>
                        $withdrawal->created_at
                        ? \Illuminate\Support\Carbon::parse($withdrawal->created_at)
                            ->startOfMonth()
                            ->toDateString()
                        : now()->startOfMonth()->toDateString(),

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

        /* MIXXTUNE_BANK_CHANGE_PROTECTION_V1: 48h hold after a bank change + KYC must be verified. */
        app(\App\Services\V2\BankChangeGuard::class)->assertPayoutAllowed((int) $withdrawal->user_id);

        return DB::transaction(function () use (
            $withdrawal,
            $admin,
            $paymentReference,
            $note
        ) {
            /* MIXXTUNE_WITHDRAWALS_V2: lock the request and check again (never pay twice) */
            WithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->first();
            $withdrawal->refresh();

            abort_unless(
                in_array($withdrawal->status, ['approved', 'processing'], true),
                422,
                'This request was changed by someone else (now '.$withdrawal->status.'). Reload the page.'
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
         * Canonical withdrawal minimum is controlled
         * globally by Super Admin System Settings.
         *
         * Legacy labels contain a historical default
         * minimum_withdrawal_amount of 5000. That field
         * is intentionally ignored here so Wallet UI
         * and withdrawal validation use one source.
         */
        $minimum = app(
            SystemSettingService::class
        )->get(
            'finance.minimum_withdrawal',
            self::DEFAULT_MINIMUM_AMOUNT
        );

        return max(
            0,
            (float) $minimum
        );
    }

}
