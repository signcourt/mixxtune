<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W69 MIXXTUNE_DASHBOARD_V2 */
class SuperAdminDashboardV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function withdrawal(User $owner, string $status, float $amount): void
    {
        $walletId = DB::table('wallets')->where('user_id', $owner->id)->value('id')
            ?? DB::table('wallets')->insertGetId(['public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'currency' => 'INR', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('withdrawals')->insert([
            'public_id' => (string) Str::ulid(),
            'withdrawal_number' => 'WD-'.Str::upper(Str::random(8)),
            'wallet_id' => $walletId,
            'user_id' => $owner->id,
            'amount' => $amount,
            'currency' => 'INR',
            'status' => $status,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_dashboard_shows_action_counts_pipeline_and_setup_gaps(): void
    {
        $super = $this->user('super_admin');
        $label = Label::factory()->create(['name' => 'Dash Master']);

        Release::factory()->count(2)->create(['status' => 'submitted', 'label_id' => $label->id, 'submitted_at' => now()->subDays(4)]);
        Release::factory()->create(['status' => 'approved', 'label_id' => $label->id]);
        Release::factory()->create(['status' => 'changes_requested', 'label_id' => $label->id]);
        Release::factory()->count(3)->create(['status' => 'live', 'label_id' => $label->id]);

        $owner = $this->user('label');
        $this->withdrawal($owner, 'pending', 150.50);
        $this->withdrawal($owner, 'approved', 99.50);
        $this->withdrawal($owner, 'paid', 500);

        DB::table('labels')->insert([
            'public_id' => (string) Str::ulid(),
            'name' => 'Sub Without Share',
            'slug' => 'sub-without-share',
            'parent_label_id' => $label->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expectedMastersWithoutLogin = DB::table('labels')->whereNull('deleted_at')->whereNull('parent_label_id')->whereNull('user_id')->count();
        $expectedTotal = DB::table('releases')->whereNull('deleted_at')->count();

        $this->actingAs($super)->get('/super-admin/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Admin/Dashboard')
            ->where('stats.submitted', 2)
            ->where('stats.approved', 1)
            ->where('stats.changes_requested', 1)
            ->where('stats.live', 3)
            ->where('stats.total_releases', $expectedTotal)
            ->where('stats.oldest_waiting_days', 4)
            ->where('stats.pending_withdrawals', 1)
            ->where('stats.pending_withdrawals_amount', fn ($v) => abs((float) $v - 150.5) < 0.001)
            ->where('stats.withdrawals_to_pay', 1)
            ->where('setup.sub_labels_without_share', 1)
            ->where('setup.masters_without_login', $expectedMastersWithoutLogin)
            ->where('recentReleases.0.label_name', 'Dash Master')
            ->has('recentWithdrawals', 3)
            ->has('revenueTrend'));
    }

    public function test_profit_trend_matches_profit_total_by_reporting_month(): void
    {
        $super = $this->user('super_admin');
        $labelId = DB::table('labels')->insertGetId([
            'public_id' => (string) Str::ulid(), 'name' => 'Trend Label', 'slug' => 'trend-label',
            'revenue_share_percentage' => 80, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $importId = DB::table('report_imports')->insertGetId([
            'public_id' => (string) Str::ulid(), 'original_filename' => 'trend.csv', 'stored_path' => 'tests/trend.csv',
            'status' => 'completed', 'financial_status' => 'published', 'total_rows' => 2, 'imported_rows' => 2, 'duplicate_rows' => 0, 'failed_rows' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([['2026-07', '2026-06', 100], ['2026-08', '2026-08', 50]] as $i => [$reporting, $sale, $revenue]) {
            DB::table('report_rows')->insert([
                'report_import_id' => $importId, 'reporting_month' => $reporting, 'sale_month' => $sale,
                'row_hash' => hash('sha256', 'trend-'.$i), 'label_name' => 'Trend Label', 'track_title' => 'T'.$i,
                'isrc' => 'INXXX260000'.$i, 'platform' => 'Spotify', 'currency' => 'INR', 'streams' => 10 * ($i + 1),
                'collected_revenue' => $revenue, 'label_rate' => 0.80, 'earnings' => $revenue * 0.8,
                'mapping_status' => 'mapped', 'label_id' => $labelId, 'revenue_owner_type' => 'label', 'revenue_owner_id' => $labelId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($super)->get('/super-admin/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('stats.profit_earnings', fn ($v) => abs((float) $v - 30.0) < 0.0001)
            ->where('revenueTrend', fn ($rows) => collect($rows)->pluck('month')->all() === ['2026-07', '2026-08']
                && abs(collect($rows)->sum('profit') - 30.0) < 0.01)
            ->where('analytics.monthly', fn ($rows) => collect($rows)->pluck('month')->all() === ['2026-07', '2026-08'])
            ->where('growth.month', '2026-08')
            ->where('stats.reported_tracks', 2)
            ->where('stats.active_platforms', 1));
    }

    public function test_manager_dashboard_has_no_platform_setup_or_profit(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Admin/Dashboard')
            ->where('setup', null)
            ->where('stats.profit_earnings', null)
            ->where('stats.total_releases', 0));
    }
}
