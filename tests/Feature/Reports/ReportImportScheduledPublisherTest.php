<?php

namespace Tests\Feature\Reports;

use App\Services\V2\ReportImportPublicationMaterializer;
use App\Services\V2\ReportImportScheduledPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * MIXXTUNE_PUBLISHER_UNCLAIMED_HOLD_TESTS_V1
 * Isolated SQLite tests. Fixture users/labels exist only in the test DB.
 */
class ReportImportScheduledPublisherTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        putenv('DB_FOREIGN_KEYS=false');
        $_ENV['DB_FOREIGN_KEYS'] = 'false';
        $_SERVER['DB_FOREIGN_KEYS'] = 'false';

        parent::setUp();

        if (DB::getDriverName() === 'mysql') {
            Schema::disableForeignKeyConstraints();
        }

        $rt = (new \ReflectionMethod(ReportImportPublicationMaterializer::class, 'materialize'))->getReturnType();
        $ret = null;
        if ($rt instanceof \ReflectionNamedType && ! $rt->allowsNull()) {
            $ret = match ($rt->getName()) { 'array' => [], 'int' => 0, 'bool' => true, default => null };
        }

        $this->mock(ReportImportPublicationMaterializer::class, function ($m) use ($ret) {
            $m->shouldReceive('materialize')->andReturn($ret);
        });
    }

    private function ins(string $table, array $data): int
    {
        $cols = Schema::getColumns($table);
        $data = array_intersect_key($data, array_flip(array_column($cols, 'name')));

        foreach ($cols as $c) {
            $n = $c['name'];
            if ($n === 'id' || array_key_exists($n, $data)) {
                continue;
            }
            if (in_array($n, ['created_at', 'updated_at'], true)) {
                $data[$n] = now();
                continue;
            }
            if ($c['nullable'] || $c['default'] !== null || ! empty($c['auto_increment'])) {
                continue;
            }
            $t = strtolower((string) ($c['type_name'] ?? $c['type'] ?? ''));
            $full = strtolower((string) ($c['type'] ?? ''));
            if (preg_match('/^enum\((.+)\)$/', $full, $em)) {
                $data[$n] = trim(explode(',', $em[1])[0], "'\" ");
                continue;
            }
            $this->seq++;
            $data[$n] = match (true) {
                str_contains($t, 'int') => 1,
                str_contains($t, 'dec') || str_contains($t, 'num') || str_contains($t, 'float')
                    || str_contains($t, 'double') || str_contains($t, 'real') => 0,
                str_contains($t, 'bool') => 0,
                str_contains($t, 'date') || str_contains($t, 'time') => now(),
                str_contains($t, 'json') => '{}',
                default => $this->fxString($n, $full),
            };
        }

        return (int) DB::table($table)->insertGetId($data);
    }

    /**
     * $specs: list of ['user' => 'active'|'suspended'|null, 'missing' => bool, 'allocs' => [[gross, net], ...]]
     */
    private function fxString(string $n, string $full): string
    {
        $v = 'fx_'.$n.'_'.$this->seq;
        if (preg_match('/\((\d+)\)/', $full, $lm) && strlen($v) > (int) $lm[1]) {
            $v = substr(base_convert((string) $this->seq, 10, 36).'x'.md5($n.$this->seq), 0, (int) $lm[1]);
        }

        return $v;
    }

    private function scenario(array $specs): array
    {
        $importId = $this->ins('report_imports', [
            'reporting_month' => '2026-10',
            'status' => 'completed',
            'financial_status' => 'approved_scheduled',
            'approved_at' => now()->subDay(),
            'publish_at' => now()->subHour(),
            'published_at' => null,
        ]);

        $pubId = $this->ins('report_import_publications', [
            'report_import_id' => $importId,
            'status' => 'materialized',
        ]);

        $out = ['import' => $importId, 'postings' => [], 'labels' => [], 'users' => [], 'net' => []];

        foreach ($specs as $spec) {
            $this->seq++;
            $userId = null;
            if (! empty($spec['user'])) {
                $userId = $this->ins('users', [
                    'name' => 'Fixture User '.$this->seq,
                    'email' => 'fixture'.$this->seq.'@example.test',
                    'password' => bcrypt('fixture-only'),
                    'account_status' => $spec['user'],
                ]);
            }

            $labelId = ! empty($spec['missing'])
                ? 990000 + $this->seq
                : $this->ins('labels', ['name' => 'Fixture Label '.$this->seq, 'user_id' => $userId]);

            $stmtId = $this->ins('royalty_statements', [
                'label_id' => $labelId,
                'artist_id' => null,
                'statement_month' => '2026-10',
                'currency' => 'INR',
                'status' => 'pending',
            ]);

            $g = 0.0; $n = 0.0;
            foreach ($spec['allocs'] as [$gross, $net]) {
                $rowId = $this->ins('report_rows', ['report_import_id' => $importId, 'currency' => 'INR']);
                $this->ins('royalty_allocations', [
                    'royalty_statement_id' => $stmtId,
                    'report_row_id' => $rowId,
                    'gross_amount' => $gross,
                    'net_amount' => $net,
                ]);
                $g += (float) $gross; $n += (float) $net;
            }

            $postingId = $this->ins('report_import_postings', [
                'publication_id' => $pubId,
                'report_import_id' => $importId,
                'owner_type' => 'label',
                'owner_id' => $labelId,
                'currency' => 'INR',
                'posting_key' => 'fixture:'.$this->seq,
                'status' => 'materialized',
                'row_count' => count($spec['allocs']),
                'collected_revenue' => round($g, 8),
                'earnings' => round($n, 8),
                'royalty_statement_id' => $stmtId,
                'metadata' => json_encode(['reporting_month' => '2026-10', 'commission_amount' => 0]),
            ]);

            $out['postings'][] = $postingId;
            $out['labels'][] = $labelId;
            $out['users'][] = $userId;
            $out['net'][] = round($n, 8);
            $out['stmt'][] = $stmtId;
        }

        return $out;
    }

    private function publisher(): ReportImportScheduledPublisher
    {
        return app(ReportImportScheduledPublisher::class);
    }

    private function fmt($v): string
    {
        return number_format((float) $v, 8, '.', '');
    }

    private function expectFailure(callable $fn, string $needle): void
    {
        $thrown = null;
        try { $fn(); } catch (RuntimeException $e) { $thrown = $e; }
        $this->assertNotNull($thrown, 'Expected RuntimeException: '.$needle);
        $this->assertStringContainsString($needle, $thrown->getMessage());
    }

    private function preHold(array $s, int $i, array $override = []): void
    {
        $this->ins('report_unclaimed_revenues', array_merge([
            'hold_key' => 'report_import_posting:'.$s['postings'][$i],
            'report_import_id' => $s['import'],
            'report_import_posting_id' => $s['postings'][$i],
            'royalty_statement_id' => $s['stmt'][$i],
            'owner_type' => 'label',
            'owner_id' => $s['labels'][$i],
            'currency' => 'INR',
            'gross_amount' => $this->fmt($s['net'][$i] + 0), // replaced below by test where needed
            'net_amount' => $this->fmt($s['net'][$i]),
            'status' => 'held',
            'metadata' => json_encode(['reason' => 'owner_user_missing']),
        ], $override));
    }

    // Test A
    public function test_a_ownerless_label_creates_one_hold_and_no_wallet_mutation(): void
    {
        $s = $this->scenario([['user' => null, 'allocs' => [['3.00000000', '2.11181634']]]]);

        $this->publisher()->publish($s['import']);

        $this->assertSame(1, DB::table('report_unclaimed_revenues')->count());
        $hold = DB::table('report_unclaimed_revenues')->first();
        $this->assertSame('held', $hold->status);
        $this->assertSame('label', $hold->owner_type);
        $this->assertSame($s['labels'][0], (int) $hold->owner_id);
        $this->assertSame($s['postings'][0], (int) $hold->report_import_posting_id);
        $this->assertNull($hold->claimed_by_user_id);
        $this->assertNull($hold->wallet_id);
        $this->assertNull($hold->wallet_transaction_id);

        $p = DB::table('report_import_postings')->find($s['postings'][0]);
        $this->assertSame('held_unclaimed', $p->status);
        $this->assertNull($p->wallet_id);
        $this->assertNull($p->wallet_transaction_id);

        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $this->assertSame(0, DB::table('wallets')->count());
        $this->assertSame('published', DB::table('report_imports')->find($s['import'])->financial_status);
    }

    // Test B
    public function test_b_hold_amounts_reconcile_to_allocations_with_8dp_precision(): void
    {
        $s = $this->scenario([['user' => null, 'allocs' => [
            ['10.00000001', '8.12345678'],
            ['7.10344034', '8.97998357'],
        ]]]);

        $this->publisher()->publish($s['import']);

        $hold = DB::table('report_unclaimed_revenues')->first();
        $this->assertSame('17.10344035', $this->fmt($hold->gross_amount));
        $this->assertSame('17.10344035', $this->fmt($hold->net_amount));
        $this->assertSame('INR', $hold->currency);
        $this->assertSame($s['stmt'][0], (int) $hold->royalty_statement_id);
        $this->assertSame($s['import'], (int) $hold->report_import_id);
    }

    // Test C
    public function test_c_posting_metadata_is_merged_not_replaced(): void
    {
        $s = $this->scenario([['user' => null, 'allocs' => [['1.00000000', '0.52178512']]]]);

        $this->publisher()->publish($s['import']);

        $meta = json_decode(DB::table('report_import_postings')->find($s['postings'][0])->metadata, true);
        $this->assertArrayHasKey('commission_amount', $meta);
        $this->assertEquals(0, $meta['commission_amount']);
        $this->assertSame('2026-10', $meta['reporting_month']);
        $this->assertSame('report_import_posting:'.$s['postings'][0], $meta['unclaimed_hold_key']);
        $this->assertSame('owner_user_missing', $meta['unclaimed_reason']);
    }

    // Test D
    public function test_d_retry_with_existing_matching_hold_is_idempotent(): void
    {
        $s = $this->scenario([['user' => null, 'allocs' => [['5.00000000', '4.00000000']]]]);
        $this->preHold($s, 0, ['gross_amount' => '5.00000000', 'net_amount' => '4.00000000']);

        $this->publisher()->publish($s['import']);
        $second = $this->publisher()->publish($s['import']);

        $this->assertSame('already_published', $second['status']);
        $this->assertSame(1, DB::table('report_unclaimed_revenues')->count());
        $hold = DB::table('report_unclaimed_revenues')->first();
        $this->assertSame('held', $hold->status);
        $this->assertNull($hold->claimed_at);
        $this->assertSame(0, DB::table('wallet_transactions')->count());
    }

    // Recoupment end-to-end: an active advance is recovered from this import's
    // earning, and only the remainder credits the wallet.
    public function test_report_publish_recovers_an_active_advance_from_earning(): void
    {
        // Active-user label with 1000 net earning on this import.
        $s = $this->scenario([['user' => 'active', 'allocs' => [['1250.00000000', '1000.00000000']]]]);

        // Advance: 400 outstanding, recover 100% of earning.
        $planId = $this->ins('recoupment_plans', [
            'user_id' => $s['users'][0],
            'label_id' => $s['labels'][0],
            'artist_id' => null,
            'plan_number' => 'RP-E2E-1',
            'title' => 'Advance',
            'base_percentage' => 100,
            'recovery_uplift_percentage' => 100,
            'maximum_recovery_percentage' => null,
            'total_recoverable_amount' => 400,
            'total_recovered_amount' => 0,
            'outstanding_amount' => 400,
            'status' => 'active',
        ]);

        $this->publisher()->publish($s['import']);

        // A recovery of 400 was recorded against the advance.
        $recovery = DB::table('recoupment_recoveries')->where('recoupment_plan_id', $planId)->first();
        $this->assertNotNull($recovery, 'no recovery was created on publish');
        $this->assertEqualsWithDelta(400.0, (float) $recovery->applied_recovery_amount, 0.00001);

        // Advance outstanding fell to 0.
        $this->assertEqualsWithDelta(0.0, (float) DB::table('recoupment_plans')->where('id', $planId)->value('outstanding_amount'), 0.00001);

        // Wallet was credited the remainder only: 1000 - 400 = 600.
        $credit = DB::table('wallet_transactions')->where('direction', 'credit')->sum('amount');
        $this->assertEqualsWithDelta(600.0, (float) $credit, 0.00001);
    }

    // MIXXTUNE_ADVANCE_CHARGE_ON_WALLET_V1: the charge is a flat % of the NET
    // amount landing in the wallet (not a label-rate reduction); recovery runs on
    // the remaining net.
    public function test_advance_charge_is_percent_of_net_then_recovery_runs_on_the_rest(): void
    {
        // Active-user label: collected 1000, net 800 (applicable rate 80%).
        $s = $this->scenario([['user' => 'active', 'allocs' => [['1000.00000000', '800.00000000']]]]);

        // Advance 10000, advance charge 10% of net, recovery 100% of earning.
        $planId = $this->ins('recoupment_plans', [
            'user_id' => $s['users'][0],
            'label_id' => $s['labels'][0],
            'artist_id' => null,
            'plan_number' => 'RP-ADVCHG-1',
            'title' => 'Advance',
            'base_percentage' => 80,
            'recovery_uplift_percentage' => 100,
            'maximum_recovery_percentage' => null,
            'advance_charge_percentage' => 10,
            'total_recoverable_amount' => 10000,
            'total_recovered_amount' => 0,
            'outstanding_amount' => 10000,
            'status' => 'active',
        ]);

        $this->publisher()->publish($s['import']);

        // Advance charge = 10% of net 800 = 80 -> reduced net 720.
        // Recovery 100% of 720 = 720 to the advance; wallet gets 0.
        $recovery = DB::table('recoupment_recoveries')->where('recoupment_plan_id', $planId)->first();
        $this->assertNotNull($recovery);
        $this->assertEqualsWithDelta(720.0, (float) $recovery->applied_recovery_amount, 0.001);

        // Outstanding fell by 720 (10000 -> 9280).
        $this->assertEqualsWithDelta(9280.0, (float) DB::table('recoupment_plans')->where('id', $planId)->value('outstanding_amount'), 0.001);

        // Wallet credited 0 (whole reduced net went to the advance).
        $credit = (float) DB::table('wallet_transactions')->where('direction', 'credit')->sum('amount');
        $this->assertEqualsWithDelta(0.0, $credit, 0.001);

        // Statement: the 80 advance charge is recorded as commission (your profit).
        $stmt = DB::table('royalty_statements')->where('id', $s['stmt'][0])->first();
        $this->assertEqualsWithDelta(80.0, (float) $stmt->commission_amount, 0.001);
    }

    // Test E1
    public function test_e1_existing_hold_with_tampered_amount_fails_safely(): void
    {
        $s = $this->scenario([['user' => null, 'allocs' => [['5.00000000', '4.00000000']]]]);
        $this->preHold($s, 0, ['gross_amount' => '5.00000000', 'net_amount' => '1.00000000']);

        $this->expectFailure(fn () => $this->publisher()->publish($s['import']), 'does not match posting');

        $this->assertSame('materialized', DB::table('report_import_postings')->find($s['postings'][0])->status);
        $this->assertSame('1.00000000', $this->fmt(DB::table('report_unclaimed_revenues')->first()->net_amount));
        $this->assertSame('approved_scheduled', DB::table('report_imports')->find($s['import'])->financial_status);
    }

    // Test E2
    public function test_e2_released_hold_is_never_reset_by_publisher(): void
    {
        $s = $this->scenario([['user' => null, 'allocs' => [['5.00000000', '4.00000000']]]]);
        $this->preHold($s, 0, [
            'gross_amount' => '5.00000000', 'net_amount' => '4.00000000',
            'status' => 'released', 'claimed_at' => now(),
        ]);

        $this->expectFailure(fn () => $this->publisher()->publish($s['import']), 'no longer claimable');

        $this->assertSame('released', DB::table('report_unclaimed_revenues')->first()->status);
        $this->assertSame('materialized', DB::table('report_import_postings')->find($s['postings'][0])->status);
    }

    // Test F
    public function test_f_missing_owner_entity_fails_safely(): void
    {
        $s = $this->scenario([['missing' => true, 'allocs' => [['1.00000000', '1.00000000']]]]);

        $this->expectFailure(fn () => $this->publisher()->publish($s['import']), 'Posting owner is missing');

        $this->assertSame(0, DB::table('report_unclaimed_revenues')->count());
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $this->assertSame('approved_scheduled', DB::table('report_imports')->find($s['import'])->financial_status);
    }

    // Test G
    public function test_g_inactive_user_is_rejected_before_wallet_mutation(): void
    {
        $s = $this->scenario([['user' => 'suspended', 'allocs' => [['2.00000000', '1.50000000']]]]);

        $this->expectFailure(fn () => $this->publisher()->publish($s['import']), 'not active');

        $this->assertSame(0, DB::table('wallets')->count());
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $this->assertSame(0, DB::table('report_unclaimed_revenues')->count());
    }

    // Test H
    public function test_h_active_user_gets_exactly_one_credit_in_canonical_user_wallet(): void
    {
        $s = $this->scenario([['user' => 'active', 'allocs' => [['20.00000000', '16.25000000']]]]);

        $this->publisher()->publish($s['import']);

        $uid = $s['users'][0];
        $this->assertSame(1, DB::table('wallets')->where('user_id', $uid)->whereNull('label_id')->whereNull('artist_id')->count());
        $this->assertSame(0, DB::table('wallets')->whereNotNull('label_id')->count());
        $this->assertSame(0, DB::table('wallets')->whereNotNull('artist_id')->count());

        $this->assertSame(1, DB::table('wallet_transactions')->count());
        $tx = DB::table('wallet_transactions')->first();
        $this->assertSame('credit', $tx->direction);
        $this->assertSame('16.25000000', $this->fmt($tx->amount));
        $this->assertSame($uid, (int) $tx->user_id);
        $this->assertSame($s['postings'][0], (int) $tx->reference_id);

        $wallet = DB::table('wallets')->where('user_id', $uid)->first();
        $this->assertSame('16.25000000', $this->fmt($wallet->available_balance));

        $p = DB::table('report_import_postings')->find($s['postings'][0]);
        $this->assertContains($p->status, ['posted', 'completed']);
        $this->assertSame((int) $tx->id, (int) $p->wallet_transaction_id);
        $this->assertSame(0, DB::table('report_unclaimed_revenues')->count());
    }

    // Test I
    public function test_i_mixed_publication_credits_valid_and_holds_ownerless(): void
    {
        $s = $this->scenario([
            ['user' => 'active', 'allocs' => [['10.00000000', '8.00000000']]],
            ['user' => null, 'allocs' => [['20.00000000', '17.10344035']]],
            ['user' => 'active', 'allocs' => [['3.00000000', '2.50000000']]],
        ]);

        $this->publisher()->publish($s['import']);

        $statuses = DB::table('report_import_postings')->whereIn('id', $s['postings'])->orderBy('id')->pluck('status')->all();
        $this->assertContains($statuses[0], ['posted', 'completed']);
        $this->assertSame('held_unclaimed', $statuses[1]);
        $this->assertContains($statuses[2], ['posted', 'completed']);

        $this->assertSame(2, DB::table('wallet_transactions')->count());
        $this->assertSame('10.50000000', $this->fmt(DB::table('wallet_transactions')->sum('amount')));
        $this->assertSame(1, DB::table('report_unclaimed_revenues')->count());
        $this->assertSame('17.10344035', $this->fmt(DB::table('report_unclaimed_revenues')->sum('net_amount')));
        $this->assertSame(0, DB::table('wallets')->whereNull('user_id')->count());

        $import = DB::table('report_imports')->find($s['import']);
        $this->assertSame('published', $import->financial_status);
        $this->assertNotNull($import->published_at);
    }

    // Test J
    public function test_j_fatal_failure_rolls_back_all_credits_and_holds(): void
    {
        $s = $this->scenario([
            ['user' => 'active', 'allocs' => [['10.00000000', '8.00000000']]],
            ['user' => null, 'allocs' => [['2.00000000', '1.00000000']]],
            ['missing' => true, 'allocs' => [['1.00000000', '1.00000000']]],
        ]);

        $this->expectFailure(fn () => $this->publisher()->publish($s['import']), 'Posting owner is missing');

        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $this->assertSame(0, DB::table('wallets')->count());
        $this->assertSame(0, DB::table('report_unclaimed_revenues')->count());
        $this->assertSame(
            ['materialized'],
            DB::table('report_import_postings')->whereIn('id', $s['postings'])->distinct()->pluck('status')->all()
        );
        $import = DB::table('report_imports')->find($s['import']);
        $this->assertSame('approved_scheduled', $import->financial_status);
        $this->assertNull($import->published_at);
    }

    // Test K
    public function test_k_published_import_never_reenters_materializer(): void
    {
        $s = $this->scenario([['user' => null, 'allocs' => [['1.00000000', '0.52178512']]]]);
        $this->publisher()->publish($s['import']);

        $this->mock(ReportImportPublicationMaterializer::class, function ($m) {
            $m->shouldReceive('materialize')->never();
        });

        $again = $this->publisher()->publish($s['import']);
        $this->assertSame('already_published', $again['status']);
        $this->assertSame('held_unclaimed', DB::table('report_import_postings')->find($s['postings'][0])->status);
        $this->assertSame(1, DB::table('report_unclaimed_revenues')->count());
    }

    private function legacyStatement(array $s, string $status): object
    {
        $id = $s['stmt'][0];
        $cols = Schema::getColumnListing('royalty_statements');
        $upd = array_intersect_key([
            'label_id' => $s['labels'][0],
            'artist_id' => null,
            'statement_month' => '2026-10',
            'currency' => 'INR',
            'gross_earnings' => $s['net'][0],
            'net_payable' => $s['net'][0],
            'status' => $status,
        ], array_flip($cols));
        DB::table('royalty_statements')->where('id', $id)->update($upd);
        $cls = (new \ReflectionMethod(\App\Services\V2\RoyaltyService::class, 'approve'))
            ->getParameters()[0]->getType()->getName();

        return $cls::query()->findOrFail($id);
    }

    private function expectHttp422(callable $fn): void
    {
        $code = null;
        try { $fn(); } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $code = $e->getStatusCode(); }
        $this->assertSame(422, $code);
    }

    // Test L
    public function test_l_legacy_approve_rejects_import_journal_statement(): void
    {
        $s = $this->scenario([['user' => 'active', 'allocs' => [['10.00000000', '8.00000000']]]]);
        $statement = $this->legacyStatement($s, 'pending');
        $admin = \App\Models\User::query()->findOrFail($s['users'][0]);

        $this->expectHttp422(fn () => app(\App\Services\V2\RoyaltyService::class)->approve($statement, $admin));

        $this->assertSame('pending', DB::table('royalty_statements')->find($s['stmt'][0])->status);
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $this->assertSame(0, DB::table('wallets')->count());
    }

    // Test M
    public function test_m_legacy_make_available_rejects_import_journal_statement(): void
    {
        $s = $this->scenario([['user' => 'active', 'allocs' => [['10.00000000', '8.00000000']]]]);
        $statement = $this->legacyStatement($s, 'approved');
        $admin = \App\Models\User::query()->findOrFail($s['users'][0]);

        $this->expectHttp422(fn () => app(\App\Services\V2\RoyaltyService::class)->makeAvailable($statement, $admin));

        $this->assertSame('approved', DB::table('royalty_statements')->find($s['stmt'][0])->status);
        $this->assertSame(0, DB::table('wallet_transactions')->count());
    }

    // Test N (MySQL only: SQLite stores DECIMAL as REAL)
    public function test_n_large_balance_credit_is_decimal_exact(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Decimal precision is only meaningful on MySQL.');
        }

        $s = $this->scenario([['user' => 'active', 'allocs' => [['432970.89290222', '432970.89290222']]]]);
        $uid = $s['users'][0];
        $this->ins('wallets', [
            'public_id' => 'fixture-wallet-'.$uid, 'user_id' => $uid, 'label_id' => null, 'artist_id' => null,
            'currency' => 'INR', 'available_balance' => '6372787.00000000', 'pending_balance' => '0',
            'lifetime_credits' => '12066740.00000000', 'lifetime_debits' => '5693953.00000000', 'status' => 'active',
        ]);

        $this->publisher()->publish($s['import']);

        $w = DB::table('wallets')->where('user_id', $uid)->first();
        $tx = DB::table('wallet_transactions')->first();
        $this->assertSame('6805757.89290222', (string) $w->available_balance);
        $this->assertSame('12499710.89290222', (string) $w->lifetime_credits);
        $this->assertSame('6372787.00000000', (string) $tx->balance_before);
        $this->assertSame('6805757.89290222', (string) $tx->balance_after);
        $this->assertSame('432970.89290222', (string) $tx->amount);
    }

    // Test O
    public function test_o_posted_path_merges_metadata(): void
    {
        $s = $this->scenario([['user' => 'active', 'allocs' => [['5.00000000', '4.00000000']]]]);

        $this->publisher()->publish($s['import']);

        $m = json_decode(DB::table('report_import_postings')->find($s['postings'][0])->metadata, true);
        $this->assertArrayHasKey('commission_amount', $m);
        $this->assertEquals(0, $m['commission_amount']);
        $this->assertSame('2026-10', $m['reporting_month']);
        $this->assertEquals(4.0, $m['wallet_payable']);
        $this->assertArrayHasKey('delta_net', $m);
    }
}
