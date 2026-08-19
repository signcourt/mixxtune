<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminDashboardProfitCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_dashboard_exposes_retained_profit_earnings(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $labelUser = User::factory()->create([
            'role' => 'label',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $labelId = DB::table('labels')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'name' => 'Dashboard Profit Label',
            'slug' => 'dashboard-profit-label',
            'user_id' => $labelUser->id,
            'revenue_share_percentage' => 80,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $importId = DB::table('report_imports')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'original_filename' => 'dashboard-profit.csv',
            'stored_path' => 'tests/dashboard-profit.csv',
            'status' => 'completed',
            'total_rows' => 1,
            'imported_rows' => 1,
            'duplicate_rows' => 0,
            'failed_rows' => 0,
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('report_rows')->insert([
            'report_import_id' => $importId,
            'reporting_month' => '2026-08',
            'row_hash' => hash(
                'sha256',
                'dashboard-profit-row'
            ),
            'label_name' => 'Dashboard Profit Label',
            'track_title' => 'Dashboard Profit Track',
            'platform' => 'Spotify',
            'currency' => 'INR',
            'sale_date' => '2026-08-15',
            'sale_month' => '2026-08',
            'earnings' => 100,
            'mapping_status' => 'mapped',
            'mapped_at' => now(),
            'label_id' => $labelId,
            'revenue_owner_type' => 'label',
            'revenue_owner_id' => $labelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($superAdmin)
            ->get('/super-admin/dashboard')
            ->assertOk()
            ->assertInertia(
                fn ($page) =>
                    $page
                        ->component('V2/Admin/Dashboard')
                        ->where(
                            'stats.profit_earnings',
                            fn ($value) =>
                                abs(
                                    (float) $value
                                    - 20.0
                                ) < 0.000001
                        )
            );
    }

    public function test_admin_dashboard_does_not_receive_platform_profit(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(
                fn ($page) =>
                    $page
                        ->component('V2/Admin/Dashboard')
                        ->where(
                            'stats.profit_earnings',
                            null
                        )
            );
    }
}
