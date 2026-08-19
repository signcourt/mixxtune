<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Label;
use App\Models\Core\Artist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAnalyticsHttpIsolationTest extends TestCase
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
        return DB::table('report_imports')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'original_filename' =>
                'admin-analytics-http-isolation.csv',
            'stored_path' =>
                'tests/admin-analytics-http-isolation.csv',
            'status' => 'completed',
            'total_rows' => 2,
            'imported_rows' => 2,
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
        Label $label,
        string $track,
        float $earnings
    ): int {
        return DB::table('report_rows')->insertGetId([
            'report_import_id' => $importId,

            'row_hash' => hash(
                'sha256',
                Str::uuid()->toString()
            ),

            'reporting_month' => '2026-06',
            'label_id' => $label->id,

            'revenue_owner_type' => 'label',
            'revenue_owner_id' => $label->id,

            'mapping_status' => 'mapped',
            'mapped_at' => now(),

            'label_name' => $label->name,
            'track_title' => $track,
            'track_artist' => 'HTTP Test Artist',
            'album_title' => 'HTTP Test Album',
            'album_artist' => 'HTTP Test Artist',
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

    public function test_admin_dashboard_excludes_foreign_analytics(): void
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

        $this->row(
            $importId,
            $assigned,
            'VISIBLE-ADMIN-TRACK',
            100
        );

        $this->row(
            $importId,
            $foreign,
            'FOREIGN-SECRET-TRACK',
            999
        );

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'v2.analytics.index',
                    ['month' => '2026-06']
                )
            );

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'V2/Analytics/Index'
                        )
                        ->where(
                            'role',
                            'admin'
                        )
                        ->where(
                            'summary.earnings',
                            fn ($value) =>
                                abs(
                                    (float) $value
                                    - 100.0
                                ) < 0.000001
                        )
            );

        $content = $response->getContent();

        $this->assertStringNotContainsString(
            'FOREIGN-SECRET-TRACK',
            $content
        );

        $this->assertStringNotContainsString(
            $foreign->name,
            $content
        );
    }

    public function test_admin_export_excludes_foreign_analytics(): void
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

        $this->row(
            $importId,
            $assigned,
            'VISIBLE-EXPORT-TRACK',
            100
        );

        $this->row(
            $importId,
            $foreign,
            'FOREIGN-EXPORT-SECRET',
            999
        );

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'v2.analytics.export',
                    ['month' => '2026-06']
                )
            );

        $response->assertOk();

        $this->assertStringContainsString(
            'text/csv',
            (string) $response
                ->headers
                ->get('Content-Type')
        );

        ob_start();

        $response
            ->baseResponse
            ->sendContent();

        $csv = (string) ob_get_clean();

        $this->assertStringContainsString(
            'VISIBLE-EXPORT-TRACK',
            $csv
        );

        $this->assertStringContainsString(
            $assigned->name,
            $csv
        );

        $this->assertStringNotContainsString(
            'FOREIGN-EXPORT-SECRET',
            $csv
        );

        $this->assertStringNotContainsString(
            $foreign->name,
            $csv
        );

        $this->assertStringNotContainsString(
            '999',
            $csv
        );
    }

    public function test_unassigned_admin_http_has_zero_analytics(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $foreign = $this->label($super);

        $importId = $this->reportImport();

        $this->row(
            $importId,
            $foreign,
            'UNASSIGNED-FOREIGN-TRACK',
            999
        );

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'v2.analytics.index',
                    ['month' => '2026-06']
                )
            );

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'V2/Analytics/Index'
                        )
                        ->where(
                            'role',
                            'admin'
                        )
                        ->where(
                            'summary.earnings',
                            fn ($value) =>
                                abs(
                                    (float) $value
                                ) < 0.000001
                        )
            );

        $this->assertStringNotContainsString(
            'UNASSIGNED-FOREIGN-TRACK',
            $response->getContent()
        );
    }

    public function test_admin_foreign_artist_id_filter_cannot_escape_scope(): void
    {
        $admin = $this->user('admin');

        $ownLabel = $this->label(
            $admin
        );

        $foreignAdmin = $this->user(
            'admin'
        );

        $foreignLabel = $this->label(
            $foreignAdmin
        );

        $ownArtist = Artist::factory()->create([
            'label_id' => $ownLabel->id,
            'stage_name' =>
                'K29 Admin Own Artist',
            'legal_name' =>
                'K29 Admin Own Artist',
        ]);

        $foreignArtist =
            Artist::factory()->create([
                'label_id' =>
                    $foreignLabel->id,
                'stage_name' =>
                    'K29 Admin Foreign Artist',
                'legal_name' =>
                    'K29 Admin Foreign Artist',
            ]);

        $importId = $this->reportImport();

        DB::table('report_rows')->insert([
            [
                'report_import_id' =>
                    $importId,
                'row_hash' =>
                    hash(
                        'sha256',
                        Str::uuid()->toString()
                    ),
                'reporting_month' =>
                    '2026-06',
                'label_id' =>
                    $ownLabel->id,
                'artist_id' =>
                    $ownArtist->id,
                'revenue_owner_type' =>
                    'label',
                'revenue_owner_id' =>
                    $ownLabel->id,
                'mapping_status' =>
                    'mapped',
                'mapped_at' =>
                    now(),
                'label_name' =>
                    $ownLabel->name,
                'track_title' =>
                    'K29 Admin Own Track',
                'track_artist' =>
                    $ownArtist->stage_name,
                'album_title' =>
                    'K29 Admin Own Album',
                'album_artist' =>
                    $ownArtist->stage_name,
                'platform' =>
                    'Spotify',
                'currency' =>
                    'INR',
                'country_code' =>
                    'IN',
                'sale_type' =>
                    'Stream',
                'sale_date' =>
                    '2026-06-15',
                'sale_month' =>
                    '2026-06',
                'streams' =>
                    100,
                'sale_units' =>
                    100,
                'label_rate' =>
                    100,
                'collected_revenue' =>
                    111,
                'earnings' =>
                    111,
                'created_at' =>
                    now(),
                'updated_at' =>
                    now(),
            ],
            [
                'report_import_id' =>
                    $importId,
                'row_hash' =>
                    hash(
                        'sha256',
                        Str::uuid()->toString()
                    ),
                'reporting_month' =>
                    '2026-06',
                'label_id' =>
                    $foreignLabel->id,
                'artist_id' =>
                    $foreignArtist->id,
                'revenue_owner_type' =>
                    'label',
                'revenue_owner_id' =>
                    $foreignLabel->id,
                'mapping_status' =>
                    'mapped',
                'mapped_at' =>
                    now(),
                'label_name' =>
                    $foreignLabel->name,
                'track_title' =>
                    'K29 Foreign Secret Track',
                'track_artist' =>
                    $foreignArtist->stage_name,
                'album_title' =>
                    'K29 Foreign Secret Album',
                'album_artist' =>
                    $foreignArtist->stage_name,
                'platform' =>
                    'Spotify',
                'currency' =>
                    'INR',
                'country_code' =>
                    'IN',
                'sale_type' =>
                    'Stream',
                'sale_date' =>
                    '2026-06-15',
                'sale_month' =>
                    '2026-06',
                'streams' =>
                    987654,
                'sale_units' =>
                    987654,
                'label_rate' =>
                    100,
                'collected_revenue' =>
                    987654,
                'earnings' =>
                    987654,
                'created_at' =>
                    now(),
                'updated_at' =>
                    now(),
            ],
        ]);

        $dashboard = $this
            ->actingAs($admin)
            ->get(
                route(
                    'v2.analytics.index',
                    [
                        'month' =>
                            '2026-06',
                        'artist_id' =>
                            $foreignArtist->id,
                    ]
                )
            );

        $dashboard->assertOk();

        $dashboardContent =
            $dashboard->getContent();

        $this->assertStringNotContainsString(
            $foreignLabel->name,
            $dashboardContent
        );

        $this->assertStringNotContainsString(
            $foreignArtist->stage_name,
            $dashboardContent
        );

        $this->assertStringNotContainsString(
            'K29 Foreign Secret Track',
            $dashboardContent
        );

        $this->assertStringNotContainsString(
            '987654',
            $dashboardContent
        );

        $export = $this
            ->actingAs($admin)
            ->get(
                route(
                    'v2.analytics.export',
                    [
                        'month' =>
                            '2026-06',
                        'artist_id' =>
                            $foreignArtist->id,
                    ]
                )
            );

        $export->assertOk();

        ob_start();

        $export
            ->baseResponse
            ->sendContent();

        $csv = (string) ob_get_clean();

        $this->assertStringNotContainsString(
            $foreignLabel->name,
            $csv
        );

        $this->assertStringNotContainsString(
            $foreignArtist->stage_name,
            $csv
        );

        $this->assertStringNotContainsString(
            'K29 Foreign Secret Track',
            $csv
        );

        $this->assertStringNotContainsString(
            '987654',
            $csv
        );
    }


}
