<?php

namespace Tests\Feature\Admin;

use App\Models\Reports\ReportImport;
use App\Models\User;
use App\Services\V2\ReportMonthChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\ConfirmsActionOtp;
use Tests\TestCase;

/**
 * W162: move a published digital report to a different reporting month
 * EVERYWHERE it shows, without changing any amount or wallet balance.
 */
class ReportMonthChangeTest extends TestCase
{
    use ConfirmsActionOtp;
    use RefreshDatabase;

    private int $seq = 0;

    private function makeLabel(): int
    {
        $row = ['name' => 'Label '.Str::random(5), 'created_at' => now(), 'updated_at' => now()];
        foreach (['slug' => Str::slug('label-'.Str::random(6)), 'public_id' => (string) Str::uuid(), 'status' => 'active', 'revenue_share_percentage' => 80] as $col => $val) {
            if (Schema::hasColumn('labels', $col)) {
                $row[$col] = $val;
            }
        }

        return (int) DB::table('labels')->insertGetId($row);
    }

    private function makeAdmin(): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Admin Person',
            'email' => Str::lower(Str::random(10)).'@example.test',
            'role' => 'admin',
            'account_status' => 'active',
            'kyc_status' => 'verified',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    protected function setUp(): void
    {
        putenv('DB_FOREIGN_KEYS=false');
        $_ENV['DB_FOREIGN_KEYS'] = 'false';
        $_SERVER['DB_FOREIGN_KEYS'] = 'false';
        parent::setUp();
        // Fixtures reference owner labels/users/plans that live only as ids here;
        // disable FK enforcement so the isolated financial move can be asserted.
        Schema::disableForeignKeyConstraints();
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }
        parent::tearDown();
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

    public function test_published_report_moves_month_everywhere_without_changing_amounts(): void
    {
        $old = '2026-08';
        $new = '2026-09';
        $labelId = $this->makeLabel();

        // A published August report with two mapped rows totalling 1000 gross / 800 net.
        $importId = $this->ins('report_imports', [
            'reporting_month' => $old,
            'financial_status' => 'published',
            'original_filename' => 'aug.csv',
        ]);

        $augStmt = $this->ins('royalty_statements', [
            'public_id' => (string) Str::ulid(),
            'label_id' => $labelId,
            'artist_id' => null,
            'statement_month' => $old,
            'currency' => 'INR',
            'gross_earnings' => 1000,
            'commission_amount' => 200,
            'tax_amount' => 0,
            'other_deductions' => 0,
            'net_payable' => 800,
            'status' => 'pending',
        ]);

        foreach ([[600, 480], [400, 320]] as [$g, $n]) {
            $rowId = $this->ins('report_rows', [
                'report_import_id' => $importId,
                'label_id' => $labelId,
                'reporting_month' => $old,
                'currency' => 'INR',
                'earnings' => $g,
            ]);
            $this->ins('royalty_allocations', [
                'public_id' => (string) Str::ulid(),
                'royalty_statement_id' => $augStmt,
                'report_row_id' => $rowId,
                'gross_amount' => $g,
                'net_amount' => $n,
                'share_percentage' => 80,
            ]);
        }

        // Wallet transaction that credited the owner for this import (month in metadata).
        $ownerUserId = $this->makeAdmin()->id;
        $txId = $this->ins('wallet_transactions', [
            'public_id' => (string) Str::ulid(),
            'user_id' => $ownerUserId,
            'label_id' => $labelId,
            'transaction_type' => 'royalty_import',
            'direction' => 'credit',
            'amount' => 800,
            'currency' => 'INR',
            'balance_before' => 0,
            'balance_after' => 800,
            'status' => 'posted',
            'description' => 'Royalty import '.$old,
            'metadata' => json_encode(['report_import_id' => $importId, 'statement_id' => $augStmt, 'reporting_month' => $old]),
        ]);

        $import = ReportImport::findOrFail($importId);
        $result = app(ReportMonthChangeService::class)->move($import, $new, 1);

        $this->assertTrue($result['moved']);
        $this->assertTrue($result['published']);

        // August statement emptied; September statement carries the full amount.
        $aug = DB::table('royalty_statements')->where('id', $augStmt)->first();
        $this->assertEquals(0, (float) $aug->gross_earnings);
        $this->assertEquals(0, (float) $aug->net_payable);

        $sep = DB::table('royalty_statements')->where('statement_month', $new)->where('label_id', $labelId)->first();
        $this->assertNotNull($sep);
        $this->assertEquals(1000, (float) $sep->gross_earnings);
        $this->assertEquals(800, (float) $sep->net_payable);
        $this->assertEquals(200, (float) $sep->commission_amount);

        // Allocations re-pointed to the September statement.
        $this->assertSame(0, DB::table('royalty_allocations')->where('royalty_statement_id', $augStmt)->count());
        $this->assertSame(2, DB::table('royalty_allocations')->where('royalty_statement_id', $sep->id)->count());

        // Wallet balance unchanged; only the month label moved.
        $tx = DB::table('wallet_transactions')->where('id', $txId)->first();
        $this->assertEquals(800, (float) $tx->amount);
        $this->assertEquals(800, (float) $tx->balance_after);
        $this->assertStringContainsString($new, (string) $tx->description);
        $meta = json_decode((string) $tx->metadata, true);
        $this->assertEquals($new, $meta['reporting_month']);
        $this->assertEquals($sep->id, (int) $meta['statement_id']);

        // Report + rows carry the new month.
        $this->assertEquals($new, DB::table('report_imports')->where('id', $importId)->value('reporting_month'));
        $this->assertSame(0, DB::table('report_rows')->where('report_import_id', $importId)->where('reporting_month', $old)->count());
        $this->assertSame(2, DB::table('report_rows')->where('report_import_id', $importId)->where('reporting_month', $new)->count());
    }

    public function test_recoupment_blocks_automatic_move(): void
    {
        $old = '2026-08';
        $labelId = $this->makeLabel();

        $importId = $this->ins('report_imports', [
            'reporting_month' => $old,
            'financial_status' => 'published',
            'original_filename' => 'aug.csv',
        ]);
        $stmt = $this->ins('royalty_statements', [
            'public_id' => (string) Str::ulid(),
            'label_id' => $labelId,
            'artist_id' => null,
            'statement_month' => $old,
            'currency' => 'INR',
            'gross_earnings' => 500,
            'net_payable' => 400,
            'status' => 'pending',
        ]);
        $rowId = $this->ins('report_rows', [
            'report_import_id' => $importId,
            'label_id' => $labelId,
            'reporting_month' => $old,
            'currency' => 'INR',
            'earnings' => 500,
        ]);
        $this->ins('royalty_allocations', [
            'public_id' => (string) Str::ulid(),
            'royalty_statement_id' => $stmt,
            'report_row_id' => $rowId,
            'gross_amount' => 500,
            'net_amount' => 400,
            'share_percentage' => 80,
        ]);
        $planUserId = $this->makeAdmin()->id;
        $planId = $this->ins('recoupment_plans', [
            'user_id' => $planUserId,
            'label_id' => $labelId,
            'plan_number' => 'RP-'.Str::random(6),
            'title' => 'Advance',
            'total_recoverable_amount' => 1000,
            'outstanding_amount' => 900,
            'status' => 'active',
        ]);
        $this->ins('recoupment_recoveries', [
            'recoupment_plan_id' => $planId,
            'royalty_statement_id' => $stmt,
            'source_amount' => 100,
            'reporting_month' => $old,
        ]);

        $this->expectException(\RuntimeException::class);
        app(ReportMonthChangeService::class)->move(ReportImport::findOrFail($importId), '2026-09', 1);
    }

    public function test_change_month_endpoint_is_otp_gated_then_moves(): void
    {
        $admin = $this->makeAdmin();
        $old = '2026-08';
        $new = '2026-09';
        $labelId = $this->makeLabel();

        $importId = $this->ins('report_imports', [
            'reporting_month' => $old,
            'financial_status' => 'published',
            'original_filename' => 'aug.csv',
        ]);
        $stmt = $this->ins('royalty_statements', [
            'public_id' => (string) Str::ulid(),
            'label_id' => $labelId,
            'artist_id' => null,
            'statement_month' => $old,
            'currency' => 'INR',
            'gross_earnings' => 300,
            'net_payable' => 240,
            'status' => 'pending',
        ]);
        $rowId = $this->ins('report_rows', [
            'report_import_id' => $importId,
            'label_id' => $labelId,
            'reporting_month' => $old,
            'currency' => 'INR',
            'earnings' => 300,
        ]);
        $this->ins('royalty_allocations', [
            'public_id' => (string) Str::ulid(),
            'royalty_statement_id' => $stmt,
            'report_row_id' => $rowId,
            'gross_amount' => 300,
            'net_amount' => 240,
            'share_percentage' => 80,
        ]);

        $send = fn () => $this->actingAs($admin)->patch(
            "/admin/reports/imports/{$importId}/change-month",
            ['reporting_month' => $new]
        );

        // First attempt is stopped with action_otp; after the code, it moves.
        $this->viaActionOtp($admin, $send)->assertSessionHas('success');

        $this->assertEquals($new, DB::table('report_imports')->where('id', $importId)->value('reporting_month'));
        $sep = DB::table('royalty_statements')->where('statement_month', $new)->where('label_id', $labelId)->first();
        $this->assertNotNull($sep);
        $this->assertEquals(240, (float) $sep->net_payable);
    }
}
