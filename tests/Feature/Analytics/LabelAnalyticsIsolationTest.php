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
}
