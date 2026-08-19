<?php

namespace Tests\Feature\Analytics;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\User;
use App\Services\V2\FinancialAnalyticsService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
