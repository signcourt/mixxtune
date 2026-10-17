<?php

namespace Tests\Feature\Reports;

use App\Services\V2\ReportImportPublicationMaterializer;
use App\Services\V2\ReportImportScheduledPublisher;
use App\Services\V2\ReportUnclaimedRevenueReleaser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * MIXXTUNE_UNCLAIMED_REVENUE_RELEASE_TESTS_V1
 * Isolated SQLite tests. Fixture users/labels exist only in the test DB.
 */
class ReportUnclaimedRevenueReleaserTest extends TestCase
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

    private function fxString(string $n, string $full): string
    {
        $v = 'fx_'.$n.'_'.$this->seq;
        if (preg_match('/\((\d+)\)/', $full, $lm) && strlen($v) > (int) $lm[1]) {
            $v = substr(base_convert((string) $this->seq, 10, 36).'x'.md5($n.$this->seq), 0, (int) $lm[1]);
        }

        return $v;
    }

    private function fmt($v): string
    {
        return number_format((float) $v, 8, '.', '');
    }

    /** Ownerless label -> publisher creates hold. Returns ids. */
    private function heldScenario(string $gross = '20.00000000', string $net = '17.10344035'): array
    {
        $importId = $this->ins('report_imports', [
            'reporting_month' => '2026-10',
            'status' => 'completed',
            'financial_status' => 'approved_scheduled',
            'approved_at' => now()->subDay(),
            'publish_at' => now()->subHour(),
            'published_at' => null,
        ]);
        $pubId = $this->ins('report_import_publications', ['report_import_id' => $importId, 'status' => 'materialized']);
        $labelId = $this->ins('labels', ['name' => 'Fixture Ownerless Label', 'user_id' => null]);
        $stmtId = $this->ins('royalty_statements', [
            'label_id' => $labelId, 'artist_id' => null, 'statement_month' => '2026-10',
            'currency' => 'INR', 'status' => 'pending',
        ]);
        $rowId = $this->ins('report_rows', ['report_import_id' => $importId, 'currency' => 'INR']);
        $this->ins('royalty_allocations', [
            'royalty_statement_id' => $stmtId, 'report_row_id' => $rowId,
            'gross_amount' => $gross, 'net_amount' => $net,
        ]);
        $postingId = $this->ins('report_import_postings', [
            'publication_id' => $pubId, 'report_import_id' => $importId,
            'owner_type' => 'label', 'owner_id' => $labelId, 'currency' => 'INR',
            'posting_key' => 'fixture:release', 'status' => 'materialized', 'row_count' => 1,
            'collected_revenue' => $gross, 'earnings' => $net, 'royalty_statement_id' => $stmtId,
            'metadata' => json_encode(['reporting_month' => '2026-10', 'commission_amount' => 0]),
        ]);

        app(ReportImportScheduledPublisher::class)->publish($importId);

        $hold = DB::table('report_unclaimed_revenues')->first();
        $this->assertNotNull($hold, 'publisher must create a hold for an ownerless label');
        $this->assertSame('held_unclaimed', DB::table('report_import_postings')->find($postingId)->status);

        return ['import' => $importId, 'label' => $labelId, 'posting' => $postingId, 'hold' => (int) $hold->id, 'net' => $net];
    }

    private function linkOwner(int $labelId, string $status = 'active'): int
    {
        $uid = $this->ins('users', [
            'name' => 'Fixture Owner '.(++$this->seq),
            'email' => 'owner'.$this->seq.'@example.test',
            'password' => bcrypt('fixture-only'),
            'account_status' => $status,
        ]);
        DB::table('labels')->where('id', $labelId)->update(['user_id' => $uid]);

        return $uid;
    }

    private function actor(): int
    {
        return $this->ins('users', [
            'name' => 'Fixture Admin '.(++$this->seq),
            'email' => 'admin'.$this->seq.'@example.test',
            'password' => bcrypt('fixture-only'),
            'role' => 'super_admin',
            'account_status' => 'active',
        ]);
    }

    private function releaser(): ReportUnclaimedRevenueReleaser
    {
        return app(ReportUnclaimedRevenueReleaser::class);
    }

    private function expectFailure(callable $fn, string $needle): void
    {
        $thrown = null;
        try { $fn(); } catch (RuntimeException $e) { $thrown = $e; }
        $this->assertNotNull($thrown, 'Expected RuntimeException: '.$needle);
        $this->assertStringContainsString($needle, $thrown->getMessage());
    }

    private function assertNoMoneyMoved(): void
    {
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $this->assertSame('held', DB::table('report_unclaimed_revenues')->first()->status);
    }

    public function test_r1_release_after_linking_credits_owner_exactly_once(): void
    {
        $s = $this->heldScenario();
        $uid = $this->linkOwner($s['label']);

        $r = $this->releaser()->release($s['hold'], $a = $this->actor(), 'owner confirmed');

        $this->assertSame($uid, $r['user_id']);
        $this->assertSame(1, DB::table('wallet_transactions')->count());
        $tx = DB::table('wallet_transactions')->first();
        $this->assertSame('credit', $tx->direction);
        $this->assertSame('17.10344035', $this->fmt($tx->amount));
        $this->assertSame($uid, (int) $tx->user_id);
        $this->assertSame($s['posting'], (int) $tx->reference_id);

        $w = DB::table('wallets')->where('user_id', $uid)->whereNull('label_id')->whereNull('artist_id')->first();
        $this->assertSame('17.10344035', $this->fmt($w->available_balance));
        $this->assertSame('17.10344035', $this->fmt($w->lifetime_credits));

        $h = DB::table('report_unclaimed_revenues')->find($s['hold']);
        $this->assertSame('released', $h->status);
        $this->assertSame($uid, (int) $h->claimed_by_user_id);
        $this->assertSame((int) $tx->id, (int) $h->wallet_transaction_id);
        $this->assertNotNull($h->claimed_at);

        $p = DB::table('report_import_postings')->find($s['posting']);
        $this->assertSame('posted', $p->status);
        $this->assertSame((int) $tx->id, (int) $p->wallet_transaction_id);
        $meta = json_decode($p->metadata, true);
        $this->assertSame(0, $meta['commission_amount']);
        $this->assertSame($a, $meta['hold_release']['released_by_user_id']);
    }

    public function test_r2_second_release_is_rejected_without_double_credit(): void
    {
        $s = $this->heldScenario();
        $uid = $this->linkOwner($s['label']);
        $this->releaser()->release($s['hold'], $this->actor());

        $this->expectFailure(fn () => $this->releaser()->release($s['hold'], $this->actor()), 'not releasable');

        $this->assertSame(1, DB::table('wallet_transactions')->count());
        $this->assertSame('17.10344035', $this->fmt(DB::table('wallets')->where('user_id', $uid)->value('available_balance')));
    }

    public function test_r3_unlinked_owner_is_rejected_without_mutation(): void
    {
        $s = $this->heldScenario();

        $this->expectFailure(fn () => $this->releaser()->release($s['hold'], $this->actor()), 'not linked to a user');

        $this->assertNoMoneyMoved();
        $this->assertSame(0, DB::table('wallets')->count());
    }

    public function test_r4_inactive_owner_is_rejected_without_mutation(): void
    {
        $s = $this->heldScenario();
        $this->linkOwner($s['label'], 'suspended');

        $this->expectFailure(fn () => $this->releaser()->release($s['hold'], $this->actor()), 'not active');

        $this->assertNoMoneyMoved();
        $this->assertSame(0, DB::table('wallets')->count());
    }

    public function test_r5_tampered_hold_amount_is_rejected(): void
    {
        $s = $this->heldScenario();
        $this->linkOwner($s['label']);
        DB::table('report_unclaimed_revenues')->where('id', $s['hold'])->update(['net_amount' => '99.00000000']);

        $this->expectFailure(fn () => $this->releaser()->release($s['hold'], $this->actor()), 'does not match posting');

        $this->assertNoMoneyMoved();
    }

    public function test_r6_active_recoupment_plan_blocks_release(): void
    {
        $s = $this->heldScenario();
        $uid = $this->linkOwner($s['label']);
        $this->ins('recoupment_plans', ['user_id' => $uid, 'status' => 'active', 'outstanding_amount' => 10]);

        $this->expectFailure(fn () => $this->releaser()->release($s['hold'], $this->actor()), 'recoupment');

        $this->assertNoMoneyMoved();
    }

    public function test_r7_existing_wallet_balance_is_added_exactly(): void
    {
        $s = $this->heldScenario();
        $uid = $this->linkOwner($s['label']);
        $this->ins('wallets', [
            'public_id' => 'fxwallet'.$uid, 'user_id' => $uid, 'artist_id' => null, 'label_id' => null,
            'currency' => 'INR', 'available_balance' => '1000.50000000', 'pending_balance' => 0,
            'lifetime_credits' => '1000.50000000', 'lifetime_debits' => 0, 'status' => 'active',
        ]);

        $r = $this->releaser()->release($s['hold'], $this->actor());

        $this->assertSame(1, DB::table('wallets')->where('user_id', $uid)->count());
        $this->assertSame('1000.50000000', $this->fmt($r['balance_before']));
        $this->assertSame('1017.60344035', $this->fmt($r['balance_after']));
        $tx = DB::table('wallet_transactions')->first();
        $this->assertSame('1017.60344035', $this->fmt($tx->balance_after));
    }
}
