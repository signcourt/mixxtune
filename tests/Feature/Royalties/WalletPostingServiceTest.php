<?php

namespace Tests\Feature\Royalties;

use App\Models\User;
use App\Services\Royalties\WalletPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class WalletPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }

    private function label(User $user): int
    {
        return DB::table('labels')->insertGetId([
            'public_id' =>
                'LBL-' . strtoupper(substr((string) Str::ulid(), -12)),

            'user_id' => $user->id,
            'name' => 'FF431 K6 Label',
            'slug' =>
                'ff431-k6-label-' .
                strtolower(substr((string) Str::ulid(), -8)),

            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'payout_cycle' => 'monthly',
            'minimum_withdrawal_amount' => 5000,
            'royalty_share_percentage' => 100,
            'parent_commission_percentage' => 0,
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function artist(User $user): int
    {
        return DB::table('artists')->insertGetId([
            'public_id' =>
                'ART-' . strtoupper(substr((string) Str::ulid(), -12)),

            'user_id' => $user->id,
            'label_id' => null,
            'stage_name' => 'FF431 K6 Artist',
            'legal_name' => 'FF431 K6 Artist',
            'slug' =>
                'ff431-k6-artist-' .
                strtolower(substr((string) Str::ulid(), -8)),

            'email' =>
                'ff431-k6-' .
                strtolower(substr((string) Str::ulid(), -8)) .
                '@example.test',

            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',

            'account_status' => 'active',
            'kyc_status' => 'pending',
            'can_receive_splits' => true,
            'can_create_releases' => true,

            'created_by' => $user->id,
            'updated_by' => $user->id,

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ledger(
        string $type,
        int $beneficiaryId,
        User $user,
        float $amount,
        string $status = 'approved'
    ): int {
        $token = strtoupper(
            substr(
                preg_replace(
                    '/[^A-Z0-9]/',
                    '',
                    (string) Str::ulid()
                ),
                -12
            )
        );

        return DB::table('royalty_ledgers')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'ledger_number' => 'FF431-K6-' . $token,
            'source_type' => 'ff431_k6_test',
            'source_key' => 'ff431-k6-' . $token,

            'beneficiary_type' => $type,
            'beneficiary_id' => $beneficiaryId,
            'user_id' => $user->id,

            'label_id' =>
                $type === 'label'
                    ? $beneficiaryId
                    : null,

            'artist_id' =>
                $type === 'artist'
                    ? $beneficiaryId
                    : null,

            'statement_month' => '2026-09',

            /*
             * These columns are DATE in MySQL.
             * Full dates also work under SQLite tests.
             */
            'reporting_month' => '2026-09-01',
            'sale_month' => '2026-09-01',

            'store_name' => 'FF431 K6 TEST',
            'currency' => 'INR',
            'gross_amount' => $amount,

            'label_share' =>
                $type === 'label'
                    ? $amount
                    : 0,

            'artist_share' =>
                $type === 'artist'
                    ? $amount
                    : 0,

            'split_percentage' => 100,
            'payable_amount' => $amount,
            'status' => $status,

            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_label_positive_ledger_credits_label_wallet(): void
    {
        $super = $this->user('super_admin');
        $labelUser = $this->user('label');
        $labelId = $this->label($labelUser);

        $ledgerId = $this->ledger(
            'label',
            $labelId,
            $labelUser,
            100
        );

        $result = app(WalletPostingService::class)
            ->creditLedger($ledgerId, $super->id);

        $this->assertSame('label', $result['beneficiary_type']);
        $this->assertSame($labelId, $result['beneficiary_id']);
        $this->assertSame('credit', $result['direction']);
        $this->assertEqualsWithDelta(
            100,
            (float) $result['signed_amount'],
            0.00000001
        );

        $wallet = DB::table('wallets')
            ->where('label_id', $labelId)
            ->whereNull('artist_id')
            ->where('currency', 'INR')
            ->first();

        $this->assertNotNull($wallet);
        $this->assertEqualsWithDelta(
            100,
            (float) $wallet->available_balance,
            0.00000001
        );
        $this->assertEqualsWithDelta(
            100,
            (float) $wallet->lifetime_credits,
            0.00000001
        );

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'label_id' => $labelId,
            'artist_id' => null,
            'direction' => 'credit',
            'transaction_type' => 'royalty_credit',
            'reference_type' => 'royalty_ledger',
            'reference_id' => $ledgerId,
        ]);

        $this->assertDatabaseHas('royalty_ledgers', [
            'id' => $ledgerId,
            'status' => 'wallet_credited',
        ]);
    }

    public function test_artist_positive_ledger_credits_artist_wallet(): void
    {
        $super = $this->user('super_admin');
        $artistUser = $this->user('artist');
        $artistId = $this->artist($artistUser);

        $ledgerId = $this->ledger(
            'artist',
            $artistId,
            $artistUser,
            75
        );

        $result = app(WalletPostingService::class)
            ->creditLedger($ledgerId, $super->id);

        $this->assertSame('artist', $result['beneficiary_type']);
        $this->assertSame($artistId, $result['beneficiary_id']);
        $this->assertSame('credit', $result['direction']);

        $wallet = DB::table('wallets')
            ->where('artist_id', $artistId)
            ->whereNull('label_id')
            ->where('currency', 'INR')
            ->first();

        $this->assertNotNull($wallet);
        $this->assertEqualsWithDelta(
            75,
            (float) $wallet->available_balance,
            0.00000001
        );

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'artist_id' => $artistId,
            'label_id' => null,
            'direction' => 'credit',
            'reference_id' => $ledgerId,
        ]);

        $this->assertDatabaseHas('royalty_ledgers', [
            'id' => $ledgerId,
            'status' => 'wallet_credited',
        ]);
    }

    public function test_negative_label_ledger_debits_label_wallet(): void
    {
        $super = $this->user('super_admin');
        $labelUser = $this->user('label');
        $labelId = $this->label($labelUser);

        $positive = $this->ledger(
            'label',
            $labelId,
            $labelUser,
            100
        );

        $service = app(WalletPostingService::class);

        $service->creditLedger(
            $positive,
            $super->id
        );

        $negative = $this->ledger(
            'label',
            $labelId,
            $labelUser,
            -30
        );

        $result = $service->creditLedger(
            $negative,
            $super->id
        );

        $this->assertSame('debit', $result['direction']);
        $this->assertEqualsWithDelta(
            -30,
            (float) $result['signed_amount'],
            0.00000001
        );

        $wallet = DB::table('wallets')
            ->where('label_id', $labelId)
            ->where('currency', 'INR')
            ->first();

        $this->assertEqualsWithDelta(
            70,
            (float) $wallet->available_balance,
            0.00000001
        );

        $this->assertEqualsWithDelta(
            30,
            (float) $wallet->lifetime_debits,
            0.00000001
        );

        $this->assertDatabaseHas('wallet_transactions', [
            'reference_type' => 'royalty_ledger',
            'reference_id' => $negative,
            'direction' => 'debit',
            'amount' => 30,
        ]);
    }

    public function test_negative_artist_ledger_debits_artist_wallet(): void
    {
        $super = $this->user('super_admin');
        $artistUser = $this->user('artist');
        $artistId = $this->artist($artistUser);

        $service = app(WalletPostingService::class);

        $positive = $this->ledger(
            'artist',
            $artistId,
            $artistUser,
            75
        );

        $service->creditLedger(
            $positive,
            $super->id
        );

        $negative = $this->ledger(
            'artist',
            $artistId,
            $artistUser,
            -25
        );

        $result = $service->creditLedger(
            $negative,
            $super->id
        );

        $this->assertSame('debit', $result['direction']);

        $wallet = DB::table('wallets')
            ->where('artist_id', $artistId)
            ->where('currency', 'INR')
            ->first();

        $this->assertEqualsWithDelta(
            50,
            (float) $wallet->available_balance,
            0.00000001
        );

        $this->assertEqualsWithDelta(
            25,
            (float) $wallet->lifetime_debits,
            0.00000001
        );

        $this->assertDatabaseHas('wallet_transactions', [
            'reference_type' => 'royalty_ledger',
            'reference_id' => $negative,
            'direction' => 'debit',
            'amount' => 25,
        ]);
    }

    public function test_wallet_posting_is_not_repeatable(): void
    {
        $super = $this->user('super_admin');
        $labelUser = $this->user('label');
        $labelId = $this->label($labelUser);

        $ledgerId = $this->ledger(
            'label',
            $labelId,
            $labelUser,
            100
        );

        $service = app(WalletPostingService::class);

        $service->creditLedger(
            $ledgerId,
            $super->id
        );

        try {
            $service->creditLedger(
                $ledgerId,
                $super->id
            );

            $this->fail(
                'Duplicate wallet posting was not blocked.'
            );
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Royalty ledger is already wallet credited.',
                $e->getMessage()
            );
        }

        $this->assertSame(
            1,
            DB::table('wallet_transactions')
                ->where('reference_type', 'royalty_ledger')
                ->where('reference_id', $ledgerId)
                ->count()
        );
    }

    public function test_draft_ledger_cannot_change_wallet(): void
    {
        $super = $this->user('super_admin');
        $labelUser = $this->user('label');
        $labelId = $this->label($labelUser);

        $ledgerId = $this->ledger(
            'label',
            $labelId,
            $labelUser,
            50,
            'draft'
        );

        try {
            app(WalletPostingService::class)
                ->creditLedger(
                    $ledgerId,
                    $super->id
                );

            $this->fail(
                'Draft ledger was allowed into wallet.'
            );
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Only approved royalty ledger can be posted to wallet.',
                $e->getMessage()
            );
        }

        $this->assertSame(
            0,
            DB::table('wallet_transactions')
                ->where('reference_type', 'royalty_ledger')
                ->where('reference_id', $ledgerId)
                ->count()
        );

        $this->assertSame(
            0,
            DB::table('wallets')
                ->where('label_id', $labelId)
                ->count()
        );

        $this->assertDatabaseHas('royalty_ledgers', [
            'id' => $ledgerId,
            'status' => 'draft',
        ]);
    }
}
