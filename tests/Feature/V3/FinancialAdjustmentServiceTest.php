<?php

namespace Tests\Feature\V3;

use App\Models\Finance\FinancialAdjustment;
use App\Models\Finance\WalletAccount;
use App\Models\Finance\WalletTransaction;
use App\Models\User;
use App\Services\V3\FinancialAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialAdjustmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FinancialAdjustmentService
    {
        return app(
            FinancialAdjustmentService::class
        );
    }

    private function user(
        string $role = 'artist'
    ): User {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_credit_adjustment_posts_directly_to_available_wallet(): void
    {
        $user = $this->user();
        $admin = $this->user(
            'super_admin'
        );

        $adjustment = $this->service()->post(
            $user,
            'credit',
            1500,
            'Manual correction',
            [
                'reference_number' =>
                    'REF-CREDIT-001',
            ],
            $admin
        );

        $wallet = WalletAccount::query()
            ->where(
                'user_id',
                $user->id
            )
            ->firstOrFail();

        $this->assertEquals(
            1500,
            (float) $wallet
                ->available_balance
        );

        $this->assertEquals(
            0,
            (float) $wallet
                ->pending_balance
        );

        $this->assertEquals(
            1500,
            (float) $wallet
                ->lifetime_credits
        );

        $this->assertSame(
            'credit',
            $adjustment->direction
        );

        $this->assertSame(
            'posted',
            $adjustment->status
        );

        $this->assertNotNull(
            $adjustment
                ->wallet_transaction_id
        );

        $this->assertDatabaseHas(
            'wallet_transactions',
            [
                'id' =>
                    $adjustment
                        ->wallet_transaction_id,

                'transaction_type' =>
                    'manual_adjustment',

                'direction' =>
                    'credit',

                'reference_type' =>
                    FinancialAdjustment::class,

                'reference_id' =>
                    $adjustment->id,
            ]
        );
    }

    public function test_debit_adjustment_reduces_available_wallet(): void
    {
        $user = $this->user();
        $admin = $this->user(
            'super_admin'
        );

        $service = $this->service();

        $service->post(
            $user,
            'credit',
            2000,
            'Opening manual credit',
            [],
            $admin
        );

        $debit = $service->post(
            $user,
            'debit',
            750,
            'Manual deduction',
            [],
            $admin
        );

        $wallet = WalletAccount::query()
            ->where(
                'user_id',
                $user->id
            )
            ->firstOrFail();

        $this->assertEquals(
            1250,
            (float) $wallet
                ->available_balance
        );

        $this->assertEquals(
            750,
            (float) $wallet
                ->lifetime_debits
        );

        $this->assertSame(
            'debit',
            $debit->direction
        );

        $this->assertSame(
            'manual_adjustment',
            $debit
                ->walletTransaction
                ->transaction_type
        );
    }

    public function test_insufficient_debit_rolls_back_adjustment(): void
    {
        $user = $this->user();
        $admin = $this->user(
            'super_admin'
        );

        try {
            $this->service()->post(
                $user,
                'debit',
                500,
                'Should fail',
                [],
                $admin
            );

            $this->fail(
                'Expected ValidationException.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'amount',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'financial_adjustments',
            0
        );

        $this->assertDatabaseCount(
            'wallet_transactions',
            0
        );

        /*
         * Wallet creation happens inside the same
         * transaction as the failed debit.
         *
         * The ValidationException must roll back
         * both the adjustment and the newly-created
         * empty wallet account.
         */
        $this->assertFalse(
            WalletAccount::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->exists()
        );
    }

    public function test_credit_adjustment_can_be_reversed(): void
    {
        $user = $this->user();
        $admin = $this->user(
            'super_admin'
        );

        $service = $this->service();

        $original = $service->post(
            $user,
            'credit',
            1000,
            'Incorrect credit',
            [],
            $admin
        );

        $reversal = $service->reverse(
            $original,
            'Reverse incorrect credit',
            $admin
        );

        $original->refresh();

        $wallet = WalletAccount::query()
            ->where(
                'user_id',
                $user->id
            )
            ->firstOrFail();

        $this->assertEquals(
            0,
            (float) $wallet
                ->available_balance
        );

        $this->assertSame(
            'reversed',
            $original->status
        );

        $this->assertNotNull(
            $original->reversed_at
        );

        $this->assertEquals(
            $admin->id,
            $original->reversed_by
        );

        $this->assertSame(
            'reversal',
            $reversal->type
        );

        $this->assertSame(
            'debit',
            $reversal->direction
        );

        $this->assertEquals(
            $original->id,
            $reversal->reversal_of_id
        );

        $this->assertSame(
            'posted',
            $reversal->status
        );

        $this->assertSame(
            'adjustment_reversal',
            $reversal
                ->walletTransaction
                ->transaction_type
        );

        $this->assertDatabaseCount(
            'financial_adjustments',
            2
        );

        $this->assertDatabaseCount(
            'wallet_transactions',
            2
        );
    }

    public function test_debit_adjustment_can_be_reversed(): void
    {
        $user = $this->user();
        $admin = $this->user(
            'super_admin'
        );

        $service = $this->service();

        $service->post(
            $user,
            'credit',
            2000,
            'Opening balance',
            [],
            $admin
        );

        $debit = $service->post(
            $user,
            'debit',
            600,
            'Incorrect debit',
            [],
            $admin
        );

        $reversal = $service->reverse(
            $debit,
            'Reverse incorrect debit',
            $admin
        );

        $wallet = WalletAccount::query()
            ->where(
                'user_id',
                $user->id
            )
            ->firstOrFail();

        $this->assertEquals(
            2000,
            (float) $wallet
                ->available_balance
        );

        $this->assertSame(
            'credit',
            $reversal->direction
        );

        $this->assertSame(
            'adjustment_reversal',
            $reversal
                ->walletTransaction
                ->transaction_type
        );
    }

    public function test_same_adjustment_cannot_be_reversed_twice(): void
    {
        $user = $this->user();
        $admin = $this->user(
            'super_admin'
        );

        $service = $this->service();

        $original = $service->post(
            $user,
            'credit',
            500,
            'Credit',
            [],
            $admin
        );

        $service->reverse(
            $original,
            'First reversal',
            $admin
        );

        try {
            $service->reverse(
                $original,
                'Second reversal',
                $admin
            );

            $this->fail(
                'Expected ValidationException.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'adjustment',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'financial_adjustments',
            2
        );

        $this->assertDatabaseCount(
            'wallet_transactions',
            2
        );
    }

    public function test_reversal_entry_cannot_itself_be_reversed(): void
    {
        $user = $this->user();
        $admin = $this->user(
            'super_admin'
        );

        $service = $this->service();

        $original = $service->post(
            $user,
            'credit',
            500,
            'Credit',
            [],
            $admin
        );

        $reversal = $service->reverse(
            $original,
            'Reverse credit',
            $admin
        );

        try {
            $service->reverse(
                $reversal,
                'Reverse reversal',
                $admin
            );

            $this->fail(
                'Expected ValidationException.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'adjustment',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'financial_adjustments',
            2
        );
    }

    public function test_failed_credit_reversal_is_atomic_when_balance_was_spent(): void
    {
        $user = $this->user();
        $admin = $this->user(
            'super_admin'
        );

        $service = $this->service();

        $original = $service->post(
            $user,
            'credit',
            1000,
            'Original credit',
            [],
            $admin
        );

        $service->post(
            $user,
            'debit',
            800,
            'Later valid debit',
            [],
            $admin
        );

        try {
            $service->reverse(
                $original,
                'Cannot reverse because funds were spent',
                $admin
            );

            $this->fail(
                'Expected ValidationException.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'amount',
                $exception->errors()
            );
        }

        $original->refresh();

        $this->assertSame(
            'posted',
            $original->status
        );

        $this->assertDatabaseCount(
            'financial_adjustments',
            2
        );

        $this->assertDatabaseCount(
            'wallet_transactions',
            2
        );

        $wallet = WalletAccount::query()
            ->where(
                'user_id',
                $user->id
            )
            ->firstOrFail();

        $this->assertEquals(
            200,
            (float) $wallet
                ->available_balance
        );
    }

    public function test_invalid_direction_does_not_create_financial_records(): void
    {
        $user = $this->user();

        try {
            $this->service()->post(
                $user,
                'invalid',
                100,
                'Invalid direction'
            );

            $this->fail(
                'Expected ValidationException.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'direction',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'financial_adjustments',
            0
        );

        $this->assertDatabaseCount(
            'wallet_transactions',
            0
        );
    }

    public function test_zero_amount_is_rejected(): void
    {
        $user = $this->user();

        try {
            $this->service()->post(
                $user,
                'credit',
                0,
                'Zero'
            );

            $this->fail(
                'Expected ValidationException.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'amount',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'financial_adjustments',
            0
        );
    }

    public function test_same_idempotency_key_does_not_post_adjustment_twice(): void
    {
        $user = $this->user();

        $admin = $this->user(
            'super_admin'
        );

        $service = $this->service();

        $context = [
            'idempotency_key' =>
                'adjustment-test-key-001',
        ];

        $first = $service->post(
            $user,
            'credit',
            1250,
            'Idempotent credit',
            $context,
            $admin
        );

        $second = $service->post(
            $user,
            'credit',
            1250,
            'Idempotent credit',
            $context,
            $admin
        );

        $this->assertEquals(
            $first->id,
            $second->id
        );

        $this->assertSame(
            'adjustment-test-key-001',
            $first->idempotency_key
        );

        $this->assertDatabaseCount(
            'financial_adjustments',
            1
        );

        $this->assertDatabaseCount(
            'wallet_transactions',
            1
        );

        $wallet =
            WalletAccount::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->firstOrFail();

        $this->assertEquals(
            1250,
            (float)
                $wallet->available_balance
        );
    }


    public function test_duplicate_idempotency_key_throws_unique_exception_and_rolls_back_loser_transaction(): void
    {
        $user = $this->user();

        $admin = $this->user(
            'super_admin'
        );

        $winner = $this->service()->post(
            $user,
            'credit',
            1250,
            'Winning adjustment',
            [
                'idempotency_key' =>
                    'race-proof-key-001',
            ],
            $admin
        );

        $walletBefore =
            WalletAccount::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->firstOrFail();

        $this->assertEquals(
            1250,
            (float)
                $walletBefore->available_balance
        );

        try {
            DB::transaction(
                function () use (
                    $user,
                    $admin
                ): void {
                    /*
                     * Simulate the losing transaction after
                     * both requests have passed an earlier
                     * application-level existence check.
                     *
                     * The database unique constraint must
                     * reject this duplicate before any
                     * additional wallet mutation can survive.
                     */
                    FinancialAdjustment::query()
                        ->create([
                            'user_id' =>
                                $user->id,

                            'adjustment_number' =>
                                'ADJ-RACE-LOSER-001',

                            'idempotency_key' =>
                                'race-proof-key-001',

                            'type' =>
                                'manual',

                            'direction' =>
                                'credit',

                            'amount' =>
                                1250,

                            'reason' =>
                                'Losing race transaction',

                            'effective_date' =>
                                now()->toDateString(),

                            'status' =>
                                'posted',

                            'created_by' =>
                                $admin->id,
                        ]);

                    $this->fail(
                        'Duplicate insert should not succeed.'
                    );
                }
            );

            $this->fail(
                'Expected unique constraint violation.'
            );
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            $this->assertNotEmpty(
                $exception->getMessage()
            );
        }

        $existing =
            FinancialAdjustment::query()
                ->where(
                    'idempotency_key',
                    'race-proof-key-001'
                )
                ->firstOrFail();

        $this->assertEquals(
            $winner->id,
            $existing->id
        );

        $this->assertDatabaseCount(
            'financial_adjustments',
            1
        );

        $this->assertDatabaseCount(
            'wallet_transactions',
            1
        );

        $walletAfter =
            WalletAccount::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->firstOrFail();

        $this->assertEquals(
            1250,
            (float)
                $walletAfter->available_balance
        );
    }


}
