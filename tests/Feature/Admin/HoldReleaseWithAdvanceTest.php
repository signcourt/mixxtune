<?php

namespace Tests\Feature\Admin;

use App\Services\V2\ReportUnclaimedRevenueReleaser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Releasing a held import payment when the owner has an active advance:
 * the advance is recovered first, only the remainder credits the wallet,
 * and the advance's outstanding falls by the recovered amount.
 */
class HoldReleaseWithAdvanceTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

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
            $full = strtolower((string) ($c['type'] ?? ''));
            $t = strtolower((string) ($c['type_name'] ?? $c['type'] ?? ''));
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
                default => 'x'.$this->seq,
            };
        }

        return (int) DB::table($table)->insertGetId($data);
    }

    public function test_advance_is_recovered_first_then_remainder_credits_wallet(): void
    {
        // Owner + active user
        $userId = $this->ins('users', [
            'name' => 'Owner', 'email' => Str::lower(Str::random(10)).'@x.test', 'role' => 'artist',
            'account_status' => 'active', 'kyc_status' => 'verified', 'password' => Hash::make('x'), 'email_verified_at' => now(),
        ]);
        $labelId = $this->ins('labels', ['name' => 'Lbl '.Str::random(4), 'user_id' => $userId, 'slug' => Str::slug('l-'.Str::random(5)), 'public_id' => (string) Str::uuid(), 'status' => 'active', 'revenue_share_percentage' => 80]);

        // Active advance: 400 outstanding
        $this->ins('recoupment_plans', [
            'user_id' => $userId, 'label_id' => $labelId, 'plan_number' => 'RP-'.Str::random(5), 'title' => 'Advance',
            'base_percentage' => 100, 'recovery_uplift_percentage' => 100, 'maximum_recovery_percentage' => 100,
            'total_recoverable_amount' => 400, 'total_recovered_amount' => 0, 'outstanding_amount' => 400, 'status' => 'active',
        ]);

        // A published import + held posting + hold of net 1000
        $importId = $this->ins('report_imports', ['reporting_month' => '2026-09', 'financial_status' => 'published', 'original_filename' => 'x.csv']);
        $pubId = $this->ins('report_import_publications', ['report_import_id' => $importId, 'status' => 'completed']);
        $stmtId = $this->ins('royalty_statements', ['public_id' => (string) Str::ulid(), 'label_id' => $labelId, 'statement_month' => '2026-09', 'currency' => 'INR', 'gross_earnings' => 1000, 'net_payable' => 1000, 'status' => 'pending']);
        $postingId = $this->ins('report_import_postings', [
            'publication_id' => $pubId, 'report_import_id' => $importId, 'owner_type' => 'label', 'owner_id' => $labelId,
            'currency' => 'INR', 'posting_key' => (string) Str::ulid(), 'status' => 'held_unclaimed', 'row_count' => 1,
            'collected_revenue' => 1000, 'earnings' => 1000, 'royalty_statement_id' => $stmtId,
        ]);
        $holdId = $this->ins('report_unclaimed_revenues', [
            'hold_key' => (string) Str::ulid(), 'report_import_id' => $importId, 'report_import_posting_id' => $postingId,
            'owner_type' => 'label', 'owner_id' => $labelId, 'currency' => 'INR', 'gross_amount' => 1000, 'net_amount' => 1000, 'status' => 'held',
        ]);

        $result = app(ReportUnclaimedRevenueReleaser::class)->release($holdId, $userId, 'test');

        // 400 recovered against the advance, 600 credited to the wallet.
        $this->assertEqualsWithDelta(400.0, (float) $result['advance_recovered'], 0.001);
        $this->assertEqualsWithDelta(600.0, (float) $result['credited'], 0.001);

        // Advance outstanding now 0.
        $this->assertEqualsWithDelta(0.0, (float) DB::table('recoupment_plans')->where('user_id', $userId)->value('outstanding_amount'), 0.001);

        // Wallet available balance = 600.
        $bal = DB::table('wallets')->where('user_id', $userId)->whereNull('label_id')->whereNull('artist_id')->value('available_balance');
        $this->assertEqualsWithDelta(600.0, (float) $bal, 0.001);

        // Hold is cleared.
        $this->assertSame('released', DB::table('report_unclaimed_revenues')->where('id', $holdId)->value('status'));

        // A recovery row exists for this hold (idempotency key).
        $this->assertSame(1, DB::table('recoupment_recoveries')->where('idempotency_key', 'report_hold_release:'.$holdId)->count());
    }
}
