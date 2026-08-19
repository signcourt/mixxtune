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

class ArtistAnalyticsIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function artistUser(): User
    {
        return User::factory()->create([
            'role' => 'artist',
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
                    'artist-analytics-isolation.csv',
                'stored_path' =>
                    'tests/artist-analytics-isolation.csv',
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
                    'artist',

                'revenue_owner_id' =>
                    $artist->id,

                'mapping_status' =>
                    'mapped',

                'mapped_at' =>
                    now(),

                'label_name' =>
                    $label->name,

                'track_title' =>
                    'Artist Analytics Test',

                'track_artist' =>
                    $artist->stage_name,

                'album_title' =>
                    'Artist Test Album',

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

    public function test_artist_raw_analytics_excludes_foreign_artist(): void
    {
        $creator = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $owner = $this->artistUser();
        $foreignOwner = $this->artistUser();

        $label = $this->label(
            $creator,
            'Artist Test Label'
        );

        $ownArtist = $this->artist(
            $owner,
            $label,
            'Own Artist'
        );

        $foreignArtist = $this->artist(
            $foreignOwner,
            $label,
            'Foreign Artist'
        );

        $importId =
            $this->reportImport();

        $ownRow =
            $this->reportRow(
                $importId,
                $label,
                $ownArtist,
                100
            );

        $foreignRow =
            $this->reportRow(
                $importId,
                $label,
                $foreignArtist,
                999
            );

        $ids = app(
            ReportAnalyticsService::class
        )
            ->scopedQuery(
                $owner,
                app(PermissionService::class)
            )
            ->pluck('id');

        $this->assertTrue(
            $ids->contains($ownRow)
        );

        $this->assertFalse(
            $ids->contains($foreignRow)
        );
    }

    public function test_artist_financial_analytics_excludes_foreign_artist(): void
    {
        $creator = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $owner = $this->artistUser();
        $foreignOwner = $this->artistUser();

        $label = $this->label(
            $creator,
            'Artist Finance Label'
        );

        $ownArtist = $this->artist(
            $owner,
            $label,
            'Own Artist'
        );

        $foreignArtist = $this->artist(
            $foreignOwner,
            $label,
            'Foreign Artist'
        );

        $ownStatement =
            $this->statement(
                $label,
                $ownArtist,
                100
            );

        $foreignStatement =
            $this->statement(
                $label,
                $foreignArtist,
                999
            );

        $ids = app(
            FinancialAnalyticsService::class
        )
            ->scopedStatements(
                $owner,
                app(PermissionService::class)
            )
            ->pluck('rs.id');

        $this->assertTrue(
            $ids->contains(
                $ownStatement
            )
        );

        $this->assertFalse(
            $ids->contains(
                $foreignStatement
            )
        );
    }

    public function test_artist_without_owned_artist_has_zero_visibility(): void
    {
        $creator = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $owner =
            $this->artistUser();

        $foreignOwner =
            $this->artistUser();

        $label =
            $this->label(
                $creator,
                'Foreign Artist Label'
            );

        $foreignArtist =
            $this->artist(
                $foreignOwner,
                $label,
                'Foreign Artist'
            );

        $importId =
            $this->reportImport();

        $this->reportRow(
            $importId,
            $label,
            $foreignArtist,
            999
        );

        $this->statement(
            $label,
            $foreignArtist,
            999
        );

        $rawCount = app(
            ReportAnalyticsService::class
        )
            ->scopedQuery(
                $owner,
                app(PermissionService::class)
            )
            ->count();

        $financialCount = app(
            FinancialAnalyticsService::class
        )
            ->scopedStatements(
                $owner,
                app(PermissionService::class)
            )
            ->count();

        $this->assertSame(
            0,
            $rawCount
        );

        $this->assertSame(
            0,
            $financialCount
        );
    }

    public function test_artist_http_dashboard_excludes_foreign_artist(): void
    {
        $creator = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $owner = $this->artistUser();
        $foreignOwner = $this->artistUser();

        $label = $this->label(
            $creator,
            'HTTP Artist Label'
        );

        $ownArtist = $this->artist(
            $owner,
            $label,
            'HTTP Own Artist'
        );

        $foreignArtist = $this->artist(
            $foreignOwner,
            $label,
            'HTTP Foreign Artist'
        );

        $importId = $this->reportImport();

        $this->reportRow(
            $importId,
            $label,
            $ownArtist,
            135
        );

        $this->reportRow(
            $importId,
            $label,
            $foreignArtist,
            865
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
                            'artist'
                        )
                        ->where(
                            'summary.earnings',
                            fn ($value) =>
                                abs(
                                    (float) $value - 135.0
                                ) < 0.000001
                        )
            );

        $content = $response->getContent();

        $this->assertStringNotContainsString(
            $foreignArtist->stage_name,
            $content
        );
    }

    public function test_artist_http_export_excludes_foreign_artist(): void
    {
        $creator = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $owner = $this->artistUser();
        $foreignOwner = $this->artistUser();

        $label = $this->label(
            $creator,
            'Export Artist Label'
        );

        $ownArtist = $this->artist(
            $owner,
            $label,
            'Export Own Artist'
        );

        $foreignArtist = $this->artist(
            $foreignOwner,
            $label,
            'Export Foreign Artist'
        );

        $importId = $this->reportImport();

        $this->reportRow(
            $importId,
            $label,
            $ownArtist,
            230
        );

        $this->reportRow(
            $importId,
            $label,
            $foreignArtist,
            970
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
            $ownArtist->stage_name,
            $csv
        );

        $this->assertStringNotContainsString(
            $foreignArtist->stage_name,
            $csv
        );
    }



    public function test_artist_foreign_artist_id_filter_cannot_escape_scope(): void
    {
        $creator = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $owner = $this->artistUser();
        $foreignOwner =
            $this->artistUser();

        $label = $this->label(
            $creator,
            'K29 Artist Label'
        );

        $ownArtist = $this->artist(
            $owner,
            $label,
            'K29 Own Artist'
        );

        $foreignArtist = $this->artist(
            $foreignOwner,
            $label,
            'K29 Foreign Artist'
        );

        $importId = $this->reportImport();

        $this->reportRow(
            $importId,
            $label,
            $ownArtist,
            333
        );

        $this->reportRow(
            $importId,
            $label,
            $foreignArtist,
            987652
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
            $foreignArtist->stage_name,
            $content
        );

        $this->assertStringNotContainsString(
            '987652',
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
            $foreignArtist->stage_name,
            $csv
        );

        $this->assertStringNotContainsString(
            '987652',
            $csv
        );
    }



    public function test_artist_foreign_hierarchy_filters_cannot_escape_scope(): void
    {
        $creator = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $owner = $this->artistUser();

        $foreignOwner =
            $this->artistUser();

        $ownLabel = $this->label(
            $creator,
            'K33 Artist Own Label'
        );

        $foreignLabel = $this->label(
            $creator,
            'K33 Artist Foreign Label'
        );

        $ownArtist = $this->artist(
            $owner,
            $ownLabel,
            'K33 Artist Own Artist'
        );

        $foreignArtist = $this->artist(
            $foreignOwner,
            $foreignLabel,
            'K33 Artist Foreign Artist'
        );

        $importId = $this->reportImport();

        $this->reportRow(
            $importId,
            $ownLabel,
            $ownArtist,
            666
        );

        $this->reportRow(
            $importId,
            $foreignLabel,
            $foreignArtist,
            987649
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
                '987649',
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
                '987649',
                $csv
            );
        }
    }



    public function test_artist_search_filter_cannot_escape_scope(): void
    {
        $creator = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $owner = $this->artistUser();
        $foreignOwner = $this->artistUser();

        $label = $this->label(
            $creator,
            'K39 Artist Label'
        );

        $ownArtist = $this->artist(
            $owner,
            $label,
            'K39 Artist Own'
        );

        $foreignArtist = $this->artist(
            $foreignOwner,
            $label,
            'K39 Artist Foreign'
        );

        $importId = $this->reportImport();

        $this->reportRow(
            $importId,
            $label,
            $ownArtist,
            999
        );

        $this->reportRow(
            $importId,
            $label,
            $foreignArtist,
            987645
        );

        /*
         * Both report rows use the same searchable
         * test title. Artist authorization must
         * remain the outer visibility boundary.
         */
        $params = [
            'month' => '2026-06',
            'search' => 'Artist Analytics Test',
        ];

        $dashboard = $this
            ->actingAs($owner)
            ->get(
                route(
                    'v2.analytics.index',
                    $params
                )
            );

        $dashboard
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'V2/Analytics/Index'
                        )
                        ->where(
                            'role',
                            'artist'
                        )
                        ->where(
                            'summary.earnings',
                            fn ($value) =>
                                abs(
                                    (float) $value
                                    - 999.0
                                ) < 0.000001
                        )
                        ->has(
                            'topTracks',
                            1
                        )
            );

        $content = $dashboard->getContent();

        $this->assertStringContainsString(
            $ownArtist->stage_name,
            $content
        );

        $this->assertStringNotContainsString(
            $foreignArtist->stage_name,
            $content
        );

        $this->assertStringNotContainsString(
            '987645',
            $content
        );

        $export = $this
            ->actingAs($owner)
            ->get(
                route(
                    'v2.analytics.export',
                    $params
                )
            );

        $export->assertOk();

        ob_start();

        $export
            ->baseResponse
            ->sendContent();

        $csv = (string) ob_get_clean();

        $this->assertStringContainsString(
            $ownArtist->stage_name,
            $csv
        );

        $this->assertStringNotContainsString(
            $foreignArtist->stage_name,
            $csv
        );

        $this->assertStringNotContainsString(
            '987645',
            $csv
        );
    }



    public function test_artist_scalar_filters_cannot_escape_scope(): void
    {
        $creator = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $owner = $this->artistUser();
        $foreignOwner = $this->artistUser();

        $label = $this->label(
            $creator,
            'K42 Artist Label'
        );

        $ownArtist = $this->artist(
            $owner,
            $label,
            'K42 Artist Own'
        );

        $foreignArtist = $this->artist(
            $foreignOwner,
            $label,
            'K42 Artist Foreign'
        );

        $importId = $this->reportImport();

        foreach ([
            [
                $ownArtist,
                1203,
            ],
            [
                $foreignArtist,
                91203,
            ],
        ] as [
            $artist,
            $earnings,
        ]) {
            DB::table('report_rows')->insert([
                'report_import_id' => $importId,
                'row_hash' => hash(
                    'sha256',
                    Str::uuid()->toString()
                ),
                'reporting_month' => '2026-06',
                'label_id' => $label->id,
                'artist_id' => $artist->id,
                'revenue_owner_type' => 'label',
                'revenue_owner_id' => $label->id,
                'mapping_status' => 'mapped',
                'mapped_at' => now(),
                'label_name' => $label->name,
                'track_title' =>
                    $earnings === 1203
                        ? 'K42 Artist Own Track'
                        : 'K42 Artist Foreign Track',
                'track_artist' =>
                    $artist->stage_name,
                'album_title' =>
                    'K42 Artist Scalar Album',
                'album_artist' =>
                    $artist->stage_name,
                'isrc' => 'K42ARTISTISRC',
                'upc' => 'K42ARTISTUPC',
                'platform' =>
                    'K42 Artist Platform',
                'currency' => 'INR',
                'country_code' => 'A4',
                'sale_type' => 'Stream',
                'sale_date' => '2026-06-15',
                'sale_month' => '2026-06',
                'streams' => $earnings,
                'sale_units' => $earnings,
                'label_rate' => 100,
                'collected_revenue' =>
                    $earnings,
                'earnings' => $earnings,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $cases = [
            [
                'platform' =>
                    'K42 Artist Platform',
            ],
            [
                'country' => 'A4',
            ],
            [
                'isrc' => 'K42ARTISTISRC',
            ],
        ];

        foreach ($cases as $filter) {
            $params = array_merge(
                ['month' => '2026-06'],
                $filter
            );

            $dashboard = $this
                ->actingAs($owner)
                ->get(route(
                    'v2.analytics.index',
                    $params
                ));

            $dashboard
                ->assertOk()
                ->assertInertia(
                    fn (Assert $page) =>
                        $page
                            ->where(
                                'summary.earnings',
                                fn ($value) =>
                                    abs(
                                        (float) $value
                                        - 1203.0
                                    ) < 0.000001
                            )
                );

            $content =
                $dashboard->getContent();

            $this->assertStringContainsString(
                'K42 Artist Own Track',
                $content
            );

            $this->assertStringNotContainsString(
                'K42 Artist Foreign Track',
                $content
            );

            $this->assertStringNotContainsString(
                '91203',
                $content
            );

            $export = $this
                ->actingAs($owner)
                ->get(route(
                    'v2.analytics.export',
                    $params
                ));

            $export->assertOk();

            ob_start();
            $export->baseResponse->sendContent();
            $csv = (string) ob_get_clean();

            $this->assertStringContainsString(
                'K42 Artist Own Track',
                $csv
            );

            $this->assertStringNotContainsString(
                'K42 Artist Foreign Track',
                $csv
            );

            $this->assertStringNotContainsString(
                '91203',
                $csv
            );
        }
    }



    public function test_artist_temporal_filters_cannot_escape_scope(): void
    {
        $creator = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $owner = $this->artistUser();
        $foreignOwner = $this->artistUser();

        $label = $this->label(
            $creator,
            'K47 Artist Label'
        );

        $ownArtist = $this->artist(
            $owner,
            $label,
            'K47 Artist Own'
        );

        $foreignArtist = $this->artist(
            $foreignOwner,
            $label,
            'K47 Artist Foreign'
        );

        $importId = $this->reportImport();

        foreach ([
            [
                $ownArtist,
                'K47 Artist Own June',
                '2026-06',
                '2026-05',
                1501,
            ],
            [
                $foreignArtist,
                'K47 Artist Foreign June',
                '2026-06',
                '2026-05',
                91501,
            ],
            [
                $ownArtist,
                'K47 Artist Own July',
                '2026-07',
                '2026-06',
                1502,
            ],
            [
                $foreignArtist,
                'K47 Artist Foreign July',
                '2026-07',
                '2026-06',
                91502,
            ],
        ] as [
            $artist,
            $title,
            $reportingMonth,
            $saleMonth,
            $earnings,
        ]) {
            DB::table('report_rows')->insert([
                'report_import_id' =>
                    $importId,
                'row_hash' => hash(
                    'sha256',
                    Str::uuid()->toString()
                ),
                'reporting_month' =>
                    $reportingMonth,
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
                    $title,
                'track_artist' =>
                    $artist->stage_name,
                'album_title' =>
                    $title . ' Album',
                'album_artist' =>
                    $artist->stage_name,
                'isrc' =>
                    'K47ARTIST' . $earnings,
                'upc' =>
                    'K47ARTISTUPC' . $earnings,
                'platform' =>
                    'Spotify',
                'currency' =>
                    'INR',
                'country_code' =>
                    'IN',
                'sale_type' =>
                    'Stream',
                'sale_date' =>
                    $saleMonth . '-15',
                'sale_month' =>
                    $saleMonth,
                'streams' =>
                    $earnings,
                'sale_units' =>
                    $earnings,
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

        $cases = [
            [
                'params' => [
                    'month' => '2026-06',
                ],
                'expected' => 1501.0,
                'own' =>
                    'K47 Artist Own June',
                'foreign' =>
                    'K47 Artist Foreign June',
                'foreignMoney' =>
                    '91501',
            ],
            [
                'params' => [
                    'from_month' => '2026-06',
                    'to_month' => '2026-07',
                ],
                'expected' => 3003.0,
                'own' =>
                    'K47 Artist Own July',
                'foreign' =>
                    'K47 Artist Foreign July',
                'foreignMoney' =>
                    '91502',
            ],
            [
                'params' => [
                    'sale_month' => '2026-06',
                ],
                'expected' => 1502.0,
                'own' =>
                    'K47 Artist Own July',
                'foreign' =>
                    'K47 Artist Foreign July',
                'foreignMoney' =>
                    '91502',
            ],
        ];

        foreach ($cases as $case) {
            $dashboard = $this
                ->actingAs($owner)
                ->get(route(
                    'v2.analytics.index',
                    $case['params']
                ));

            $dashboard
                ->assertOk()
                ->assertInertia(
                    fn (Assert $page) =>
                        $page->where(
                            'summary.earnings',
                            fn ($value) =>
                                abs(
                                    (float) $value
                                    - $case['expected']
                                ) < 0.000001
                        )
                );

            $content =
                $dashboard->getContent();

            $this->assertStringContainsString(
                $case['own'],
                $content
            );

            $this->assertStringNotContainsString(
                $case['foreign'],
                $content
            );

            $this->assertStringNotContainsString(
                $case['foreignMoney'],
                $content
            );

            $export = $this
                ->actingAs($owner)
                ->get(route(
                    'v2.analytics.export',
                    $case['params']
                ));

            $export->assertOk();

            ob_start();
            $export->baseResponse->sendContent();
            $csv = (string) ob_get_clean();

            $this->assertStringContainsString(
                $case['own'],
                $csv
            );

            $this->assertStringNotContainsString(
                $case['foreign'],
                $csv
            );

            $this->assertStringNotContainsString(
                $case['foreignMoney'],
                $csv
            );
        }
    }


}
