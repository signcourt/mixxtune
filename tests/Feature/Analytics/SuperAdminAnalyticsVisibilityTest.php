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
}
