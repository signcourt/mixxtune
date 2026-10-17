<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Services\V2\ReportImportScheduledPublisher;
use App\Services\V2\ReportUnclaimedRevenueReleaser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * MIXXTUNE_E2E_REVENUE_FLOW_TEST_V1
 *
 * Generated test data only (SQLite in-memory test DB).
 * Real pipeline, nothing mocked:
 *   approved_scheduled import
 *   -> ReportImportScheduledPublisher (real Materializer)
 *   -> owned label credited to canonical user wallet (8dp exact)
 *   -> ownerless label held as unclaimed revenue (no wallet)
 *   -> retry is idempotent
 *   -> Super Admin releases hold to the new owner.
 */
class ReportImportEndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        putenv('DB_FOREIGN_KEYS=false');
        $_ENV['DB_FOREIGN_KEYS'] = 'false';
        $_SERVER['DB_FOREIGN_KEYS'] = 'false';

        parent::setUp();
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
            $this->seq++;
            $data[$n] = match (true) {
                str_contains($t, 'int') => 1,
                str_contains($t, 'dec') || str_contains($t, 'num') || str_contains($t, 'float')
                    || str_contains($t, 'double') || str_contains($t, 'real') => 0,
                str_contains($t, 'bool') => 0,
                str_contains($t, 'date') || str_contains($t, 'time') => now(),
                str_contains($t, 'json') => '{}',
                default => $n.'-e2e-'.$this->seq,
            };
        }

        return (int) DB::table($table)->insertGetId($data);
    }

    private function dp($value): string
    {
        return number_format((float) $value, 8, '.', '');
    }

    private function row(int $importId, int $labelId, string $earnings): void
    {
        $this->seq++;
        $this->ins('report_rows', [
            'report_import_id' => $importId,
            'label_id' => $labelId,
            'artist_id' => null,
            'mapping_status' => 'mapped',
            'revenue_owner_type' => 'label',
            'revenue_owner_id' => $labelId,
            'currency' => 'INR',
            'collected_revenue' => $earnings,
            'label_rate' => 1,
            'earnings' => $earnings,
            'platform' => 'Spotify',
            'country' => 'IN',
            'sale_type' => 'Stream',
            'sale_month' => '2026-07',
            'reporting_month' => '2026-07',
            'streams' => 100,
            'sale_units' => 100,
            'isrc' => 'INE2E26'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT),
            'track_title' => 'E2E Track '.$this->seq,
            'row_hash' => 'e2e-'.$importId.'-'.$this->seq,
        ]);
    }

    private function wallet(int $userId): ?object
    {
        return DB::table('wallets')
            ->where('user_id', $userId)
            ->whereNull('label_id')
            ->whereNull('artist_id')
            ->first();
    }

    public function test_full_revenue_flow_with_generated_data(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);

        $owned = (object) ['id' => $this->ins('labels', ['user_id' => $owner->id, 'created_by' => $actor->id, 'name' => 'E2E Owned Label', 'slug' => 'e2e-owned-label', 'status' => 'active'])];
        $ownerless = (object) ['id' => $this->ins('labels', ['user_id' => null, 'created_by' => $actor->id, 'name' => 'E2E Ownerless Label', 'slug' => 'e2e-ownerless-label', 'status' => 'active'])];

        $importId = $this->ins('report_imports', [
            'public_id' => (string) Str::ulid(),
            'original_filename' => 'e2e-generated.csv',
            'reporting_month' => '2026-07',
            'stored_path' => 'report-imports/e2e-generated.csv',
            'status' => 'completed',
            'financial_status' => 'approved_scheduled',
            'publish_at' => now()->subMinute(),
            'reviewed_at' => now()->subMinutes(5),
            'reviewed_by' => $actor->id,
            'approved_at' => now()->subMinutes(5),
            'approved_by' => $actor->id,
            'review_row_count' => 3,
            'review_mapped_rows' => 3,
            'review_unmapped_rows' => 0,
            'review_collected_revenue' => '180.37345678',
            'review_earnings' => '180.37345678',
            'total_rows' => 3,
            'imported_rows' => 3,
            'duplicate_rows' => 0,
            'failed_rows' => 0,
            'uploaded_by' => $actor->id,
        ]);

        $this->row($importId, $owned->id, '100.12345678');
        $this->row($importId, $owned->id, '50.00000000');
        $this->row($importId, $ownerless->id, '30.25000000');

        /* 1) publish (real materializer) */
        $publisher = app(ReportImportScheduledPublisher::class);
        $publisher->publish($importId);

        $this->assertSame('published', (string) DB::table('report_imports')->where('id', $importId)->value('financial_status'));

        /* 2) owned label -> posted -> owner's canonical wallet, 8dp exact */
        $posted = DB::table('report_import_postings')
            ->where('report_import_id', $importId)->where('owner_type', 'label')->where('owner_id', $owned->id)->first();
        $this->assertNotNull($posted, 'owned label posting missing');
        $this->assertSame('posted', (string) $posted->status);
        $this->assertSame('150.12345678', $this->dp($posted->earnings));

        $ownerWallet = $this->wallet($owner->id);
        $this->assertNotNull($ownerWallet, 'owner wallet missing');
        $this->assertSame('150.12345678', $this->dp($ownerWallet->available_balance));
        $this->assertSame(1, DB::table('wallet_transactions')->where('wallet_id', $ownerWallet->id)->count());

        /* 3) ownerless label -> held_unclaimed, no wallet money */
        $held = DB::table('report_import_postings')
            ->where('report_import_id', $importId)->where('owner_type', 'label')->where('owner_id', $ownerless->id)->first();
        $this->assertNotNull($held, 'ownerless posting missing');
        $this->assertSame('held_unclaimed', (string) $held->status);
        $this->assertSame('30.25000000', $this->dp($held->earnings));

        $holds = DB::table('report_unclaimed_revenues')->where('status', 'held')->get();
        $this->assertCount(1, $holds);
        $this->assertSame(1, DB::table('wallet_transactions')->count());

        /* 4) retry is idempotent */
        $again = $publisher->publish($importId);
        $this->assertSame('already_published', $again['status'] ?? null);
        $this->assertSame(1, DB::table('wallet_transactions')->count());
        $this->assertSame('150.12345678', $this->dp($this->wallet($owner->id)->available_balance));

        /* 5) label gets an owner later -> Super Admin releases the hold */
        $newOwner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        DB::table('labels')->where('id', $ownerless->id)->update(['user_id' => $newOwner->id]);

        app(ReportUnclaimedRevenueReleaser::class)->release((int) $holds->first()->id, (int) $actor->id, 'E2E release');

        $this->assertSame('released', (string) DB::table('report_unclaimed_revenues')->where('id', $holds->first()->id)->value('status'));
        $this->assertSame('posted', (string) DB::table('report_import_postings')->where('id', $held->id)->value('status'));

        $newWallet = $this->wallet($newOwner->id);
        $this->assertNotNull($newWallet, 'new owner wallet missing');
        $this->assertSame('30.25000000', $this->dp($newWallet->available_balance));

        /* 6) first owner untouched; exactly two credits overall */
        $this->assertSame('150.12345678', $this->dp($this->wallet($owner->id)->available_balance));
        $this->assertSame(2, DB::table('wallet_transactions')->count());
    }
}
