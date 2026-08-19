<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Label;
use App\Models\User;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAnalyticsIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function label(User $creator): Label
    {
        return Label::factory()->create([
            'created_by' => $creator->id,
        ]);
    }

    private function assignLabel(
        User $admin,
        Label $label,
        User $super
    ): void {
        DB::table('admin_label_assignments')->insert([
            'user_id' => $admin->id,
            'label_id' => $label->id,
            'assignment_role' => 'manager',
            'can_view' => true,
            'can_edit' => true,
            'can_manage_releases' => true,
            'can_manage_team' => false,
            'can_manage_splits' => false,
            'assigned_by' => $super->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reportImport(): int
    {
        return DB::table('report_imports')
            ->insertGetId([
                'public_id' => (string) Str::ulid(),
                'original_filename' =>
                    'admin-analytics-isolation.csv',
                'stored_path' =>
                    'tests/admin-analytics-isolation.csv',
                'status' => 'completed',
                'total_rows' => 10,
                'imported_rows' => 10,
                'duplicate_rows' => 0,
                'failed_rows' => 0,
                'uploaded_by' => null,
                'started_at' => now(),
                'completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function row(
        int $importId,
        ?int $labelId,
        float $earnings,
        ?int $revenueOwnerId = null
    ): int {
        return DB::table('report_rows')->insertGetId([
            'report_import_id' => $importId,

            'row_hash' => hash(
                'sha256',
                Str::uuid()->toString()
            ),

            'reporting_month' => '2026-06',

            'label_id' => $labelId,

            'revenue_owner_type' =>
                $revenueOwnerId ? 'label' : null,

            'revenue_owner_id' =>
                $revenueOwnerId,

            'mapping_status' =>
                ($labelId || $revenueOwnerId)
                    ? 'mapped'
                    : 'unmapped',

            'mapped_at' =>
                ($labelId || $revenueOwnerId)
                    ? now()
                    : null,

            'label_name' => 'Analytics Test Label',
            'track_title' => 'Analytics Test Track',
            'track_artist' => 'Analytics Test Artist',
            'album_title' => 'Analytics Test Album',
            'album_artist' => 'Analytics Test Artist',
            'platform' => 'Spotify',
            'currency' => 'INR',
            'country_code' => 'IN',
            'sale_type' => 'Stream',
            'sale_date' => '2026-06-15',
            'sale_month' => '2026-06',
            'streams' => 100,
            'sale_units' => 100,
            'label_rate' => 100,
            'collected_revenue' => $earnings,
            'earnings' => $earnings,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function visibleIds(User $user)
    {
        return app(
            ReportAnalyticsService::class
        )
            ->scopedQuery(
                $user,
                app(PermissionService::class)
            )
            ->pluck('id');
    }

    public function test_admin_sees_assigned_label_only(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $assigned = $this->label($super);
        $foreign = $this->label($super);

        $this->assignLabel(
            $admin,
            $assigned,
            $super
        );

        $importId = $this->reportImport();

        $visible = $this->row(
            $importId,
            $assigned->id,
            100
        );

        $hidden = $this->row(
            $importId,
            $foreign->id,
            999
        );

        $ids = $this->visibleIds($admin);

        $this->assertTrue(
            $ids->contains($visible)
        );

        $this->assertFalse(
            $ids->contains($hidden)
        );
    }

    public function test_admin_sees_authorized_legacy_owner_row(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $assigned = $this->label($super);

        $this->assignLabel(
            $admin,
            $assigned,
            $super
        );

        $importId = $this->reportImport();

        $legacy = $this->row(
            $importId,
            null,
            250,
            $assigned->id
        );

        $ids = $this->visibleIds($admin);

        $this->assertTrue(
            $ids->contains($legacy)
        );
    }

    public function test_admin_cannot_see_foreign_legacy_owner_row(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $assigned = $this->label($super);
        $foreign = $this->label($super);

        $this->assignLabel(
            $admin,
            $assigned,
            $super
        );

        $importId = $this->reportImport();

        $foreignRow = $this->row(
            $importId,
            null,
            999,
            $foreign->id
        );

        $ids = $this->visibleIds($admin);

        $this->assertFalse(
            $ids->contains($foreignRow)
        );
    }

    public function test_unassigned_admin_has_zero_analytics_visibility(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $foreign = $this->label($super);

        $importId = $this->reportImport();

        $this->row(
            $importId,
            $foreign->id,
            999
        );

        $this->assertSame(
            0,
            $this->visibleIds($admin)->count()
        );
    }
}
