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

class LabelAnalyticsIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function labelUser(): User
    {
        return User::factory()->create([
            'role' => 'label',
        ]);
    }

    private function label(
        User $user,
        string $name
    ): Label {
        return Label::factory()->create([
            'user_id' => $user->id,
            'name' => $name,
        ]);
    }

    private function artist(
        Label $label,
        string $name
    ): Artist {
        return Artist::factory()->create([
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
                    'label-analytics-isolation.csv',
                'stored_path' =>
                    'tests/label-analytics-isolation.csv',
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
        ?Artist $artist,
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
            'artist_id' => $artist?->id,

            'revenue_owner_type' => 'label',
            'revenue_owner_id' => $label->id,

            'mapping_status' => 'mapped',
            'mapped_at' => now(),

            'label_name' => $label->name,

            'track_title' =>
                'Analytics Test Track',

            'track_artist' =>
                $artist?->stage_name
                ?? 'Test Artist',

            'album_title' =>
                'Analytics Test Album',

            'album_artist' =>
                $artist?->stage_name
                ?? 'Test Artist',

            'platform' => 'Spotify',
            'currency' => 'INR',
            'country_code' => 'IN',

            'sale_type' => 'Stream',
            'sale_date' => '2026-06-15',
            'sale_month' => '2026-06',

            'streams' => 100,
            'sale_units' => 100,
            'label_rate' => 100,

            'collected_revenue' =>
                $earnings,

            'earnings' =>
                $earnings,

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    private function statement(
        Label $label,
        ?Artist $artist,
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
                $artist?->id,

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


    public function test_label_raw_analytics_excludes_foreign_label(): void
    {
        $owner = $this->labelUser();
        $foreignOwner = $this->labelUser();

        $ownLabel = $this->label($owner, 'Own Label');
        $foreignLabel = $this->label(
            $foreignOwner,
            'Foreign Label'
        );

        $ownArtist = $this->artist(
            $ownLabel,
            'Own Artist'
        );

        $foreignArtist = $this->artist(
            $foreignLabel,
            'Foreign Artist'
        );

        $importId = $this->reportImport();

        $ownRow = $this->reportRow(
            $importId,
            $ownLabel,
            $ownArtist,
            100
        );

        $foreignRow = $this->reportRow(
            $importId,
            $foreignLabel,
            $foreignArtist,
            999
        );

        $ids = app(ReportAnalyticsService::class)
            ->scopedQuery(
                $owner,
                app(PermissionService::class)
            )
            ->pluck('id');

        $this->assertTrue($ids->contains($ownRow));
        $this->assertFalse($ids->contains($foreignRow));
    }

    public function test_label_financial_analytics_excludes_foreign_label(): void
    {
        $owner = $this->labelUser();
        $foreignOwner = $this->labelUser();

        $ownLabel = $this->label($owner, 'Own Label');
        $foreignLabel = $this->label(
            $foreignOwner,
            'Foreign Label'
        );

        $ownArtist = $this->artist(
            $ownLabel,
            'Own Artist'
        );

        $foreignArtist = $this->artist(
            $foreignLabel,
            'Foreign Artist'
        );

        $ownStatement = $this->statement(
            $ownLabel,
            $ownArtist,
            100
        );

        $foreignStatement = $this->statement(
            $foreignLabel,
            $foreignArtist,
            999
        );

        $ids = app(FinancialAnalyticsService::class)
            ->scopedStatements(
                $owner,
                app(PermissionService::class)
            )
            ->pluck('rs.id');

        $this->assertTrue(
            $ids->contains($ownStatement)
        );

        $this->assertFalse(
            $ids->contains($foreignStatement)
        );
    }

    public function test_label_without_owned_label_has_zero_visibility(): void
    {
        $user = $this->labelUser();

        $foreignOwner = $this->labelUser();

        $foreignLabel = $this->label(
            $foreignOwner,
            'Foreign Label'
        );

        $foreignArtist = $this->artist(
            $foreignLabel,
            'Foreign Artist'
        );

        $importId = $this->reportImport();

        $this->reportRow(
            $importId,
            $foreignLabel,
            $foreignArtist,
            999
        );

        $rawCount = app(ReportAnalyticsService::class)
            ->scopedQuery(
                $user,
                app(PermissionService::class)
            )
            ->count();

        $financialCount =
            app(FinancialAnalyticsService::class)
                ->scopedStatements(
                    $user,
                    app(PermissionService::class)
                )
                ->count();

        $this->assertSame(0, $rawCount);
        $this->assertSame(0, $financialCount);
    }

    public function test_label_http_dashboard_excludes_foreign_label(): void
    {
        $owner = $this->labelUser();
        $foreignOwner = $this->labelUser();

        $ownLabel = $this->label(
            $owner,
            'HTTP Own Label'
        );

        $foreignLabel = $this->label(
            $foreignOwner,
            'HTTP Foreign Label'
        );

        $ownArtist = $this->artist(
            $ownLabel,
            'HTTP Own Artist'
        );

        $foreignArtist = $this->artist(
            $foreignLabel,
            'HTTP Foreign Artist'
        );

        $importId = $this->reportImport();

        $this->reportRow(
            $importId,
            $ownLabel,
            $ownArtist,
            125
        );

        $this->reportRow(
            $importId,
            $foreignLabel,
            $foreignArtist,
            875
        );

        $response = $this
            ->actingAs($owner)
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
                            'label'
                        )
                        ->where(
                            'summary.earnings',
                            fn ($value) =>
                                abs(
                                    (float) $value - 125.0
                                ) < 0.000001
                        )
            );

        $content = $response->getContent();

        $this->assertStringNotContainsString(
            $foreignLabel->name,
            $content
        );

        $this->assertStringNotContainsString(
            $foreignArtist->stage_name,
            $content
        );
    }

    public function test_label_http_export_excludes_foreign_label(): void
    {
        $owner = $this->labelUser();
        $foreignOwner = $this->labelUser();

        $ownLabel = $this->label(
            $owner,
            'Export Own Label'
        );

        $foreignLabel = $this->label(
            $foreignOwner,
            'Export Foreign Label'
        );

        $ownArtist = $this->artist(
            $ownLabel,
            'Export Own Artist'
        );

        $foreignArtist = $this->artist(
            $foreignLabel,
            'Export Foreign Artist'
        );

        $importId = $this->reportImport();

        $this->reportRow(
            $importId,
            $ownLabel,
            $ownArtist,
            210
        );

        $this->reportRow(
            $importId,
            $foreignLabel,
            $foreignArtist,
            990
        );

        $response = $this
            ->actingAs($owner)
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
            $ownLabel->name,
            $csv
        );

        $this->assertStringContainsString(
            $ownArtist->stage_name,
            $csv
        );

        $this->assertStringNotContainsString(
            $foreignLabel->name,
            $csv
        );

        $this->assertStringNotContainsString(
            $foreignArtist->stage_name,
            $csv
        );
    }



    public function test_label_foreign_artist_id_filter_cannot_escape_scope(): void
    {
        $owner = $this->labelUser();
        $foreignOwner = $this->labelUser();

        $ownLabel = $this->label(
            $owner,
            'K29 Label Own Label'
        );

        $foreignLabel = $this->label(
            $foreignOwner,
            'K29 Label Foreign Label'
        );

        $ownArtist = $this->artist(
            $ownLabel,
            'K29 Label Own Artist'
        );

        $foreignArtist = $this->artist(
            $foreignLabel,
            'K29 Label Foreign Artist'
        );

        $importId = $this->reportImport();

        $this->reportRow(
            $importId,
            $ownLabel,
            $ownArtist,
            222
        );

        $this->reportRow(
            $importId,
            $foreignLabel,
            $foreignArtist,
            987653
        );

        $dashboard = $this
            ->actingAs($owner)
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

        $content =
            $dashboard->getContent();

        $this->assertStringNotContainsString(
            $foreignLabel->name,
            $content
        );

        $this->assertStringNotContainsString(
            $foreignArtist->stage_name,
            $content
        );

        $this->assertStringNotContainsString(
            '987653',
            $content
        );

        $export = $this
            ->actingAs($owner)
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
            '987653',
            $csv
        );
    }



    public function test_label_foreign_hierarchy_filters_cannot_escape_scope(): void
    {
        $owner = $this->labelUser();
        $foreignOwner = $this->labelUser();

        $ownLabel = $this->label(
            $owner,
            'K33 Label Own Label'
        );

        $foreignLabel = $this->label(
            $foreignOwner,
            'K33 Label Foreign Label'
        );

        $ownArtist = $this->artist(
            $ownLabel,
            'K33 Label Own Artist'
        );

        $foreignArtist = $this->artist(
            $foreignLabel,
            'K33 Label Foreign Artist'
        );

        $importId = $this->reportImport();

        $this->reportRow(
            $importId,
            $ownLabel,
            $ownArtist,
            555
        );

        $this->reportRow(
            $importId,
            $foreignLabel,
            $foreignArtist,
            987650
        );

        foreach (
            ['master_label_id', 'level_id']
            as $filter
        ) {
            $dashboard = $this
                ->actingAs($owner)
                ->get(
                    route(
                        'v2.analytics.index',
                        [
                            'month' =>
                                '2026-06',
                            $filter =>
                                $foreignLabel->id,
                        ]
                    )
                );

            $dashboard->assertOk();

            $content =
                $dashboard->getContent();

            $this->assertStringNotContainsString(
                $foreignLabel->name,
                $content
            );

            $this->assertStringNotContainsString(
                $foreignArtist->stage_name,
                $content
            );

            $this->assertStringNotContainsString(
                '987650',
                $content
            );

            $export = $this
                ->actingAs($owner)
                ->get(
                    route(
                        'v2.analytics.export',
                        [
                            'month' =>
                                '2026-06',
                            $filter =>
                                $foreignLabel->id,
                        ]
                    )
                );

            $export->assertOk();

            ob_start();

            $export
                ->baseResponse
                ->sendContent();

            $csv =
                (string) ob_get_clean();

            $this->assertStringNotContainsString(
                $foreignLabel->name,
                $csv
            );

            $this->assertStringNotContainsString(
                $foreignArtist->stage_name,
                $csv
            );

            $this->assertStringNotContainsString(
                '987650',
                $csv
            );
        }
    }


}
