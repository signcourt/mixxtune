<?php

namespace Tests\Feature\Withdrawals;

use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WalletAccount;
use App\Models\Finance\WalletTransaction;
use App\Models\Finance\WithdrawalRequest;
use App\Models\User;
use App\Services\V2\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WithdrawalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('system_settings')->insert([
            [
                'group' => 'company',
                'key' => 'company.legal_name',
                'value' => 'Test Distribution Company',
                'type' => 'string',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'company',
                'key' => 'company.address',
                'value' => 'Test Company Address',
                'type' => 'string',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'invoice',
                'key' => 'invoice.gst_percent',
                'value' => '18',
                'type' => 'decimal',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'invoice',
                'key' => 'invoice.tds_percent',
                'value' => '10',
                'type' => 'decimal',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function service(): WithdrawalService
    {
        return app(WithdrawalService::class);
    }

    private function createUser(
        string $role = 'artist'
    ): User {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function createWallet(
        User $user,
        float $available = 5000,
        float $pending = 0
    ): WalletAccount {
        return WalletAccount::query()->create([
            'public_id' =>
                (string) \Illuminate\Support\Str::ulid(),

            'user_id' =>
                $user->id,

            'artist_id' =>
                null,

            'label_id' =>
                null,

            'currency' =>
                'INR',

            'available_balance' =>
                $available,

            'pending_balance' =>
                $pending,

            'lifetime_credits' =>
                $available,

            'lifetime_debits' =>
                0,

            'status' =>
                'active',
        ]);
    }

    private function createVerifiedProfile(
        User $user,
        string $method = 'bank'
    ): PayoutProfile {
        $payload = [
            'public_id' =>
                (string) \Illuminate\Support\Str::ulid(),

            'user_id' =>
                $user->id,

            'account_holder_name' =>
                'Test Artist',

            'bank_name' =>
                'Test Bank',

            'bank_account_number' =>
                '123456789012',

            'ifsc_code' =>
                'TEST0001234',

            'upi_id' =>
                'artist@upi',

            'country_code' =>
                'IN',

            'kyc_status' =>
                'verified',

            'verified_at' =>
                now(),
        ];

        if ($method === 'upi') {
            $payload['bank_account_number'] = null;
            $payload['ifsc_code'] = null;
        }

        return PayoutProfile::query()->create(
            $payload
        );
    }

    private function createWithdrawal(
        float $amount = 1500,
        string $method = 'bank'
    ): array {
        $user = $this->createUser();

        $wallet = $this->createWallet(
            $user,
            5000
        );

        $profile = $this
            ->createVerifiedProfile(
                $user,
                $method
            );

        $withdrawal = $this
            ->service()
            ->create(
                $user,
                $amount,
                $method,
                'Withdrawal test request.'
            );

        return [
            $user,
            $wallet,
            $profile,
            $withdrawal,
        ];
    }

    public function test_user_can_create_bank_withdrawal(): void
    {
        [
            $user,
            $wallet,
            ,
            $withdrawal,
        ] = $this->createWithdrawal();

        $this->assertSame(
            'pending',
            $withdrawal->status
        );

        $this->assertSame(
            $user->id,
            $withdrawal->user_id
        );

        $this->assertSame(
            $wallet->id,
            $withdrawal->wallet_id
        );

        $this->assertSame(
            'bank',
            $withdrawal->payment_method
        );

        $this->assertMatchesRegularExpression(
            '/^WD-[0-9]{6}-[0-9]{6}$/',
            $withdrawal->withdrawal_number
        );

        $this->assertNotNull(
            $withdrawal->requested_at
        );
    }

    public function test_create_withdrawal_moves_available_to_pending(): void
    {
        [
            ,
            $wallet,
        ] = $this->createWithdrawal(
            1500
        );

        $wallet->refresh();

        $this->assertEquals(
            3500,
            (float) $wallet
                ->available_balance
        );

        $this->assertEquals(
            1500,
            (float) $wallet
                ->pending_balance
        );

        $this->assertEquals(
            5000,
            (float) $wallet
                ->lifetime_credits
        );

        $this->assertEquals(
            0,
            (float) $wallet
                ->lifetime_debits
        );
    }

    public function test_create_withdrawal_creates_hold_transaction(): void
    {
        [
            $user,
            $wallet,
            ,
            $withdrawal,
        ] = $this->createWithdrawal(
            1500
        );

        $this->assertDatabaseHas(
            'wallet_transactions',
            [
                'wallet_id' =>
                    $wallet->id,

                'user_id' =>
                    $user->id,

                'transaction_type' =>
                    'withdrawal_hold',

                'direction' =>
                    'debit',

                'amount' =>
                    1500,

                'reference_type' =>
                    'withdrawal',

                'reference_id' =>
                    $withdrawal->id,

                'status' =>
                    'pending',
            ]
        );
    }

    public function test_upi_withdrawal_can_be_created(): void
    {
        [
            ,
            ,
            ,
            $withdrawal,
        ] = $this->createWithdrawal(
            1500,
            'upi'
        );

        $this->assertSame(
            'upi',
            $withdrawal->payment_method
        );

        $this->assertSame(
            'pending',
            $withdrawal->status
        );
    }

    public function test_minimum_withdrawal_amount_is_enforced(): void
    {
        $user = $this->createUser();

        $this->createWallet(
            $user,
            5000
        );

        $this->createVerifiedProfile(
            $user
        );

        try {
            $this
                ->service()
                ->create(
                    $user,
                    999,
                    'bank'
                );

            $this->fail(
                'Minimum amount validation did not run.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'amount',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'withdrawals',
            0
        );
    }

    public function test_payout_profile_is_required(): void
    {
        $user = $this->createUser();

        $this->createWallet(
            $user,
            5000
        );

        try {
            $this
                ->service()
                ->create(
                    $user,
                    1500,
                    'bank'
                );

            $this->fail(
                'Payout profile validation did not run.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'payout_profile',
                $exception->errors()
            );
        }
    }

    public function test_verified_kyc_is_required(): void
    {
        $user = $this->createUser();

        $this->createWallet(
            $user,
            5000
        );

        PayoutProfile::query()->create([
            'public_id' =>
                (string) \Illuminate\Support\Str::ulid(),

            'user_id' =>
                $user->id,

            'account_holder_name' =>
                'Test Artist',

            'bank_account_number' =>
                '123456789012',

            'ifsc_code' =>
                'TEST0001234',

            'kyc_status' =>
                'pending',
        ]);

        try {
            $this
                ->service()
                ->create(
                    $user,
                    1500,
                    'bank'
                );

            $this->fail(
                'KYC validation did not run.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'kyc',
                $exception->errors()
            );
        }
    }

    public function test_bank_details_are_required_for_bank_withdrawal(): void
    {
        $user = $this->createUser();

        $this->createWallet(
            $user,
            5000
        );

        PayoutProfile::query()->create([
            'public_id' =>
                (string) \Illuminate\Support\Str::ulid(),

            'user_id' =>
                $user->id,

            'account_holder_name' =>
                'Test Artist',

            'kyc_status' =>
                'verified',

            'verified_at' =>
                now(),
        ]);

        try {
            $this
                ->service()
                ->create(
                    $user,
                    1500,
                    'bank'
                );

            $this->fail(
                'Bank details validation did not run.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'payment_method',
                $exception->errors()
            );
        }
    }

    public function test_upi_id_is_required_for_upi_withdrawal(): void
    {
        $user = $this->createUser();

        $this->createWallet(
            $user,
            5000
        );

        PayoutProfile::query()->create([
            'public_id' =>
                (string) \Illuminate\Support\Str::ulid(),

            'user_id' =>
                $user->id,

            'account_holder_name' =>
                'Test Artist',

            'kyc_status' =>
                'verified',

            'verified_at' =>
                now(),
        ]);

        try {
            $this
                ->service()
                ->create(
                    $user,
                    1500,
                    'upi'
                );

            $this->fail(
                'UPI validation did not run.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'payment_method',
                $exception->errors()
            );
        }
    }

    public function test_insufficient_wallet_balance_is_rejected(): void
    {
        $user = $this->createUser();

        $this->createWallet(
            $user,
            500
        );

        $this->createVerifiedProfile(
            $user
        );

        try {
            $this
                ->service()
                ->create(
                    $user,
                    1500,
                    'bank'
                );

            $this->fail(
                'Insufficient balance validation did not run.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'amount',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'withdrawals',
            0
        );
    }

    public function test_duplicate_active_withdrawal_is_rejected(): void
    {
        [
            $user,
        ] = $this->createWithdrawal(
            1500
        );

        WalletAccount::query()
            ->where('user_id', $user->id)
            ->update([
                'available_balance' =>
                    5000,
            ]);

        try {
            $this
                ->service()
                ->create(
                    $user,
                    1500,
                    'bank'
                );

            $this->fail(
                'Duplicate active withdrawal was allowed.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'withdrawal',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'withdrawals',
            1
        );
    }

    public function test_admin_can_approve_pending_withdrawal(): void
    {
        [
            ,
            ,
            ,
            $withdrawal,
        ] = $this->createWithdrawal();

        $admin = $this->createUser(
            'super_admin'
        );

        $withdrawal = $this
            ->service()
            ->approve(
                $withdrawal,
                $admin,
                'Approved for payment.'
            );

        $this->assertSame(
            'approved',
            $withdrawal->status
        );

        $this->assertSame(
            $admin->id,
            $withdrawal->approved_by
        );

        $this->assertNotNull(
            $withdrawal->approved_at
        );

        $this->assertSame(
            'Approved for payment.',
            $withdrawal->admin_note
        );
    }

    public function test_non_pending_withdrawal_cannot_be_approved(): void
    {
        [
            ,
            ,
            ,
            $withdrawal,
        ] = $this->createWithdrawal();

        $admin = $this->createUser(
            'super_admin'
        );

        $withdrawal = $this
            ->service()
            ->approve(
                $withdrawal,
                $admin
            );

        $this->expectException(
            HttpException::class
        );

        $this
            ->service()
            ->approve(
                $withdrawal,
                $admin
            );
    }

    public function test_pending_withdrawal_can_be_rejected(): void
    {
        [
            ,
            $wallet,
            ,
            $withdrawal,
        ] = $this->createWithdrawal(
            1500
        );

        $admin = $this->createUser(
            'super_admin'
        );

        $withdrawal = $this
            ->service()
            ->reject(
                $withdrawal,
                $admin,
                'Incorrect payout details.'
            );

        $this->assertSame(
            'rejected',
            $withdrawal->status
        );

        $this->assertSame(
            'Incorrect payout details.',
            $withdrawal
                ->rejection_reason
        );

        $this->assertNotNull(
            $withdrawal->rejected_at
        );

        $wallet->refresh();

        $this->assertEquals(
            5000,
            (float) $wallet
                ->available_balance
        );

        $this->assertEquals(
            0,
            (float) $wallet
                ->pending_balance
        );
    }

    public function test_rejection_reverses_hold_and_creates_release_transaction(): void
    {
        [
            ,
            $wallet,
            ,
            $withdrawal,
        ] = $this->createWithdrawal(
            1500
        );

        $admin = $this->createUser(
            'super_admin'
        );

        $this
            ->service()
            ->reject(
                $withdrawal,
                $admin,
                'Rejected.'
            );

        $this->assertDatabaseHas(
            'wallet_transactions',
            [
                'wallet_id' =>
                    $wallet->id,

                'transaction_type' =>
                    'withdrawal_hold',

                'reference_id' =>
                    $withdrawal->id,

                'status' =>
                    'reversed',
            ]
        );

        $this->assertDatabaseHas(
            'wallet_transactions',
            [
                'wallet_id' =>
                    $wallet->id,

                'transaction_type' =>
                    'withdrawal_release',

                'direction' =>
                    'credit',

                'reference_id' =>
                    $withdrawal->id,

                'status' =>
                    'posted',
            ]
        );
    }

    public function test_approved_withdrawal_can_be_marked_paid(): void
    {
        [
            ,
            $wallet,
            ,
            $withdrawal,
        ] = $this->createWithdrawal(
            1500
        );

        $admin = $this->createUser(
            'super_admin'
        );

        $withdrawal = $this
            ->service()
            ->approve(
                $withdrawal,
                $admin
            );

        $withdrawal = $this
            ->service()
            ->markPaid(
                $withdrawal,
                $admin,
                'UTR-TEST-123',
                'Payment completed.'
            );

        $this->assertSame(
            'paid',
            $withdrawal->status
        );

        $this->assertSame(
            'UTR-TEST-123',
            $withdrawal
                ->payment_reference
        );

        $this->assertNotNull(
            $withdrawal->paid_at
        );

        $wallet->refresh();

        $this->assertEquals(
            3500,
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
                ->lifetime_debits
        );
    }

    public function test_mark_paid_posts_existing_hold_without_second_debit(): void
    {
        [
            ,
            $wallet,
            ,
            $withdrawal,
        ] = $this->createWithdrawal(
            1500
        );

        $admin = $this->createUser(
            'super_admin'
        );

        $withdrawal = $this
            ->service()
            ->approve(
                $withdrawal,
                $admin
            );

        $this
            ->service()
            ->markPaid(
                $withdrawal,
                $admin,
                'UTR-TEST-456'
            );

        $this->assertDatabaseHas(
            'wallet_transactions',
            [
                'wallet_id' =>
                    $wallet->id,

                'transaction_type' =>
                    'withdrawal_hold',

                'direction' =>
                    'debit',

                'reference_type' =>
                    'withdrawal',

                'reference_id' =>
                    $withdrawal->id,

                'status' =>
                    'posted',
            ]
        );

        $this->assertDatabaseMissing(
            'wallet_transactions',
            [
                'wallet_id' =>
                    $wallet->id,

                'transaction_type' =>
                    'withdrawal_paid',

                'reference_type' =>
                    'withdrawal',

                'reference_id' =>
                    $withdrawal->id,
            ]
        );

        $this->assertSame(
            1,
            WalletTransaction::query()
                ->where(
                    'wallet_id',
                    $wallet->id
                )
                ->where(
                    'reference_type',
                    'withdrawal'
                )
                ->where(
                    'reference_id',
                    $withdrawal->id
                )
                ->count()
        );
    }

    public function test_pending_withdrawal_cannot_be_marked_paid(): void
    {
        [
            ,
            ,
            ,
            $withdrawal,
        ] = $this->createWithdrawal();

        $admin = $this->createUser(
            'super_admin'
        );

        $this->expectException(
            HttpException::class
        );

        $this
            ->service()
            ->markPaid(
                $withdrawal,
                $admin,
                'UTR-INVALID'
            );
    }

    public function test_paid_withdrawal_cannot_be_rejected(): void
    {
        [
            ,
            ,
            ,
            $withdrawal,
        ] = $this->createWithdrawal();

        $admin = $this->createUser(
            'super_admin'
        );

        $withdrawal = $this
            ->service()
            ->approve(
                $withdrawal,
                $admin
            );

        $withdrawal = $this
            ->service()
            ->markPaid(
                $withdrawal,
                $admin,
                'UTR-PAID'
            );

        $this->expectException(
            HttpException::class
        );

        $this
            ->service()
            ->reject(
                $withdrawal,
                $admin,
                'Cannot reject paid withdrawal.'
            );
    }

    public function test_withdrawal_metadata_preserves_payout_profile(): void
    {
        [
            ,
            ,
            $profile,
            $withdrawal,
        ] = $this->createWithdrawal();

        $this->assertSame(
            $profile->id,
            $withdrawal
                ->payout_profile_id
        );

        $this->assertSame(
            'v2',
            $withdrawal
                ->metadata['request_source']
                ?? null
        );
    }

    public function test_withdrawal_model_uses_unified_table(): void
    {
        $model = new WithdrawalRequest();

        $this->assertSame(
            'withdrawals',
            $model->getTable()
        );

        $wallet = new WalletAccount();

        $this->assertSame(
            'wallets',
            $wallet->getTable()
        );
    }
}
