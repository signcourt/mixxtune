<?php

namespace Tests\Feature\RevenueSharing;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\LabelRevenueShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RevenueSharingExportRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function labelUser(): User
    {
        return User::factory()->create([
            'name' => 'Master User',
            'role' => 'label',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function masterLabel(
        User $user
    ): Label {
        return Label::factory()->create([
            'user_id' => $user->id,
            'parent_label_id' => null,
            'name' => 'Master Label',
            'status' => 'active',
        ]);
    }

    private function artist(
        Label $master
    ): Artist {
        return Artist::factory()->create([
            'label_id' => $master->id,
            'stage_name' => 'Revenue Test Artist',
            'account_status' => 'active',
        ]);
    }

    private function share(
        User $user,
        Label $master,
        Artist $artist
    ): LabelRevenueShare {
        return LabelRevenueShare::query()->create([
            'master_label_id' => $master->id,
            'beneficiary_type' => 'artist',
            'beneficiary_id' => $artist->id,
            'revenue_share_percent' => 70,
            'show_revenue_share' => true,
            'is_active' => true,
            'effective_from' => '2026-08-11',
            'effective_to' => null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function insertReportRow(
        Label $master,
        Artist $artist,
        string $saleDate,
        string $saleMonth,
        float $earnings = 2500
    ): void {
        $importId = DB::table(
            'report_imports'
        )->insertGetId([
            'public_id' =>
                (string) \Illuminate\Support\Str::ulid(),

            'stored_path' =>
                'tests/revenue-regression.csv',


            'original_filename' =>
                'revenue-regression.csv',

            'status' =>
                'completed',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        DB::table('report_rows')->insert([
            'row_hash' =>
                hash(
                    'sha256',
                    $saleDate
                    .'|'
                    .$saleMonth
                    .'|'
                    .$artist->id
                ),

            'report_import_id' =>
                $importId,

            'artist_id' =>
                $artist->id,

            'label_id' =>
                $master->id,

            'track_artist' =>
                $artist->stage_name,

            'album_title' =>
                'Revenue Regression Album',

            'album_artist' =>
                $artist->stage_name,

            'label_name' =>
                $master->name,

            'track_title' =>
                'Revenue Regression Track',

            'platform' =>
                'Spotify',

            'currency' =>
                'INR',

            'country_code' =>
                'IN',

            'sale_type' =>
                'Stream',

            'sale_date' =>
                $saleDate,

            'sale_month' =>
                $saleMonth,

            'streams' =>
                1000,

            'sale_units' =>
                1000,

            'label_rate' =>
                1,

            'earnings' =>
                $earnings,

            'revenue_owner_type' =>
                'label',

            'revenue_owner_id' =>
                $master->id,

            'mapping_status' =>
                'mapped',

            'mapped_at' =>
                now(),

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    private function csvBody(
        $response
    ): string {
        ob_start();

        $response
            ->baseResponse
            ->sendContent();

        return (string) ob_get_clean();
    }

    public function test_same_sale_month_is_included_even_when_sale_date_is_before_effective_day(): void
    {
        $user = $this->labelUser();
        $master = $this->masterLabel($user);
        $artist = $this->artist($master);

        $this->share(
            $user,
            $master,
            $artist
        );

        $this->insertReportRow(
            $master,
            $artist,
            '2026-08-01',
            '2026-08',
            2500
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    "/v2/label/revenue-sharing/artist/{$artist->id}/export"
                );

        $response->assertOk();

        $csv = $this->csvBody(
            $response
        );

        $this->assertStringContainsString(
            'Revenue Regression Track',
            $csv
        );

        $this->assertStringContainsString(
            '2500.00000000',
            $csv
        );

        $this->assertStringContainsString(
            '70.0000',
            $csv
        );

        $this->assertStringContainsString(
            '1750.00000000',
            $csv
        );

        $this->assertStringContainsString(
            '750.00000000',
            $csv
        );
    }

    public function test_previous_sale_month_is_excluded(): void
    {
        $user = $this->labelUser();
        $master = $this->masterLabel($user);
        $artist = $this->artist($master);

        $this->share(
            $user,
            $master,
            $artist
        );

        $this->insertReportRow(
            $master,
            $artist,
            '2026-07-31',
            '2026-07',
            2500
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    "/v2/label/revenue-sharing/artist/{$artist->id}/export"
                );

        $response->assertOk();

        $csv = $this->csvBody(
            $response
        );

        $this->assertStringNotContainsString(
            'Revenue Regression Track',
            $csv
        );

        $this->assertStringContainsString(
            'Beneficiary Payable',
            $csv
        );
    }
}
