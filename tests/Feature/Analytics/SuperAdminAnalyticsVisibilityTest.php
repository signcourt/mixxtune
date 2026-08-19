<?php

namespace Tests\Feature\Analytics;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\User;
use App\Services\V2\FinancialAnalyticsService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminAnalyticsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
        ]);
    }

    private function label(
        User $creator,
        string $name
    ): Label {
        return Label::factory()->create([
            'created_by' => $creator->id,
            'name' => $name,
        ]);
    }

    private function artist(
        User $user,
        Label $label,
        string $name
    ): Artist {
        return Artist::factory()->create([
            'user_id' => $user->id,
            'label_id' => $label->id,
            'stage_name' => $name,
            'legal_name' => $name,
        ]);
    }

    private function reportImport(): int
    {
        return DB::table('report_imports')
            ->insertGetId([
                'public_id' => (string) Str::ulid(),
                'original_filename' =>
                    'super-admin-analytics.csv',
                'stored_path' =>
                    'tests/super-admin-analytics.csv',
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

    private function reportRow(
        int $importId,
        Label $label,
        Artist $artist,
        float $earnings
    ): int {
        return DB::table('report_rows')
            ->insertGetId([
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
                    $label->id,

                'artist_id' =>
                    $artist->id,

                'revenue_owner_type' =>
                    'label',

                'revenue_owner_id' =>
                    $label->id,

                'mapping_status' =>
                    'mapped',

                'mapped_at' =>
                    now(),

                'label_name' =>
                    $label->name,

                'track_title' =>
                    'Super Admin Test Track',

                'track_artist' =>
                    $artist->stage_name,

                'album_title' =>
                    'Super Admin Album',

                'album_artist' =>
                    $artist->stage_name,

                'platform' =>
                    'Spotify',

                'currency' =>
                    'INR',

                'country_code' =>
                    'IN',

                'sale_type' =>
                    'Stream',

                'cms' =>
                    'K55 Super CMS',

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
                    $earnings,

                'earnings' =>
                    $earnings,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
    }

    private function statement(
        Label $label,
        Artist $artist,
        float $amount
    ): int {
        return DB::table(
            'royalty_statements'
        )->insertGetId([
            'public_id' =>
                (string) Str::ulid(),

            'label_id' =>
                $label->id,

            'artist_id' =>
                $artist->id,

            'statement_month' =>
                '2026-06',

            'currency' =>
                'INR',

            'gross_earnings' =>
                $amount,

            'commission_amount' =>
                0,

            'tax_amount' =>
                0,

            'other_deductions' =>
                0,

            'net_payable' =>
                $amount,

            'status' =>
                'approved',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    public function test_super_admin_sees_all_raw_analytics(): void
    {
        $super = $this->user('super_admin');

        $labelOwnerA = $this->user('label');
        $labelOwnerB = $this->user('label');

        $artistOwnerA = $this->user('artist');
        $artistOwnerB = $this->user('artist');

        $labelA = $this->label(
            $labelOwnerA,
            'Label A'
        );

        $labelB = $this->label(
            $labelOwnerB,
            'Label B'
        );

        $artistA = $this->artist(
            $artistOwnerA,
            $labelA,
            'Artist A'
        );

        $artistB = $this->artist(
            $artistOwnerB,
            $labelB,
            'Artist B'
        );

        $importId = $this->reportImport();

        $rowA = $this->reportRow(
            $importId,
            $labelA,
            $artistA,
            100
        );

        $rowB = $this->reportRow(
            $importId,
            $labelB,
            $artistB,
            999
        );

        $ids = app(
            ReportAnalyticsService::class
        )
            ->scopedQuery(
                $super,
                app(PermissionService::class)
            )
            ->pluck('id');

        $this->assertTrue(
            $ids->contains($rowA)
        );

        $this->assertTrue(
            $ids->contains($rowB)
        );
    }

    public function test_super_admin_sees_all_financial_analytics(): void
    {
        $super = $this->user('super_admin');

        $labelOwnerA = $this->user('label');
        $labelOwnerB = $this->user('label');

        $artistOwnerA = $this->user('artist');
        $artistOwnerB = $this->user('artist');

        $labelA = $this->label(
            $labelOwnerA,
            'Finance Label A'
        );

        $labelB = $this->label(
            $labelOwnerB,
            'Finance Label B'
        );

        $artistA = $this->artist(
            $artistOwnerA,
            $labelA,
            'Finance Artist A'
        );

        $artistB = $this->artist(
            $artistOwnerB,
            $labelB,
            'Finance Artist B'
        );

        $statementA = $this->statement(
            $labelA,
            $artistA,
            100
        );

        $statementB = $this->statement(
            $labelB,
            $artistB,
            999
        );

        $ids = app(
            FinancialAnalyticsService::class
        )
            ->scopedStatements(
                $super,
                app(PermissionService::class)
            )
            ->pluck('rs.id');

        $this->assertTrue(
            $ids->contains($statementA)
        );

        $this->assertTrue(
            $ids->contains($statementB)
        );
    }

    public function test_super_admin_http_dashboard_sees_all_analytics(): void
    {
        $super = $this->user('super_admin');

        $labelOwnerA = $this->user('label');
        $labelOwnerB = $this->user('label');

        $artistOwnerA = $this->user('artist');
        $artistOwnerB = $this->user('artist');

        $labelA = $this->label(
            $labelOwnerA,
            'HTTP Super Label A'
        );

        $labelB = $this->label(
            $labelOwnerB,
            'HTTP Super Label B'
        );

        $artistA = $this->artist(
            $artistOwnerA,
            $labelA,
            'HTTP Super Artist A'
        );

        $artistB = $this->artist(
            $artistOwnerB,
            $labelB,
            'HTTP Super Artist B'
        );

        $importId = $this->reportImport();

        $this->reportRow(
            $importId,
            $labelA,
            $artistA,
            125
        );

        $this->reportRow(
            $importId,
            $labelB,
            $artistB,
            875
        );

        $response = $this
            ->actingAs($super)
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
                            'super_admin'
                        )
                        ->where(
                            'summary.earnings',
                            fn ($value) =>
                                abs(
                                    (float) $value - 1000.0
                                ) < 0.000001
                        )
                        ->where(
                            'filterOptions.cms',
                            fn ($value) =>
                                collect($value)
                                    ->contains(
                                        'K55 Super CMS'
                                    )
                        )
                        ->where(
                            'cmsSummary.0.name',
                            'K55 Super CMS'
                        )
                        ->where(
                            'cmsSummary.0.earnings',
                            fn ($value) =>
                                abs(
                                    (float) $value
                                    - 1000.0
                                ) < 0.000001
                        )
            );

        $content = $response->getContent();

        $this->assertStringContainsString(
            $labelA->name,
            $content
        );

        $this->assertStringContainsString(
            $labelB->name,
            $content
        );

        $this->assertStringContainsString(
            $artistA->stage_name,
            $content
        );

        $this->assertStringContainsString(
            $artistB->stage_name,
            $content
        );
    }

    public function test_super_admin_http_export_sees_all_analytics(): void
    {
        $super = $this->user('super_admin');

        $labelOwnerA = $this->user('label');
        $labelOwnerB = $this->user('label');

        $artistOwnerA = $this->user('artist');
        $artistOwnerB = $this->user('artist');

        $labelA = $this->label(
            $labelOwnerA,
            'Export Super Label A'
        );

        $labelB = $this->label(
            $labelOwnerB,
            'Export Super Label B'
        );

        $artistA = $this->artist(
            $artistOwnerA,
            $labelA,
            'Export Super Artist A'
        );

        $artistB = $this->artist(
            $artistOwnerB,
            $labelB,
            'Export Super Artist B'
        );

        $importId = $this->reportImport();

        $this->reportRow(
            $importId,
            $labelA,
            $artistA,
            150
        );

        $this->reportRow(
            $importId,
            $labelB,
            $artistB,
            850
        );

        $response = $this
            ->actingAs($super)
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
            $labelA->name,
            $csv
        );

        $this->assertStringContainsString(
            $labelB->name,
            $csv
        );

        $this->assertStringContainsString(
            $artistA->stage_name,
            $csv
        );

        $this->assertStringContainsString(
            $artistB->stage_name,
            $csv
        );

        /*
         * Super Admin export must retain source
         * economics at 100%, not apply label/artist
         * account revenue-share reductions.
         */
        $this->assertStringContainsString(
            '150',
            $csv
        );

        $this->assertStringContainsString(
            '850',
            $csv
        );
    }


}
