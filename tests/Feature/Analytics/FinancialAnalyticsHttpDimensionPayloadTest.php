<?php

namespace Tests\Feature\Analytics;

use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialAnalyticsHttpDimensionPayloadTest extends TestCase
{
    use RefreshDatabase;

    private function label(
        User $owner,
        string $name
    ): Label {
        return Label::factory()->create([
            'user_id' => $owner->id,
            'name' => $name,
        ]);
    }

    private function reportImport(): int
    {
        return DB::table(
            'report_imports'
        )->insertGetId([
            'public_id' =>
                (string) Str::ulid(),

            'original_filename' =>
                'k57c-http-financial-dimensions.csv',

            'stored_path' =>
                'tests/k57c-http-financial-dimensions.csv',

            'reporting_month' =>
                '2026-06',

            'status' =>
                'completed',

            'total_rows' =>
                3,

            'imported_rows' =>
                3,

            'duplicate_rows' =>
                0,

            'failed_rows' =>
                0,

            'uploaded_by' =>
                null,

            'started_at' =>
                now(),

            'completed_at' =>
                now(),

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    private function reportRow(
        int $importId,
        Label $label,
        string $key,
        string $saleType,
        string $currency,
        string $cms
    ): int {
        return DB::table(
            'report_rows'
        )->insertGetId([
            'report_import_id' =>
                $importId,

            'row_hash' =>
                hash(
                    'sha256',
                    'K57CB-' . $key
                ),

            'reporting_month' =>
                '2026-06',

            'label_id' =>
                $label->id,

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
                'K57CB ' . $key,

            'track_artist' =>
                'K57CB Artist',

            'album_title' =>
                'K57CB Album',

            'album_artist' =>
                'K57CB Artist',

            'isrc' =>
                'K57CB' . str_pad(
                    $key,
                    7,
                    '0'
                ),

            'upc' =>
                '57000000000' . $key,

            'platform' =>
                $key === 'A'
                    ? 'Spotify'
                    : 'YouTube',

            'country_code' =>
                $key === 'A'
                    ? 'IN'
                    : 'US',

            'sale_type' =>
                $saleType,

            'currency' =>
                $currency,

            'cms' =>
                $cms,

            'sale_date' =>
                '2026-06-15',

            'sale_month' =>
                '2026-06',

            'streams' =>
                10,

            'sale_units' =>
                10,

            /*
             * Deliberately unrelated to payable
             * financial amounts.
             */
            'collected_revenue' =>
                9999,

            'earnings' =>
                9999,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    private function statement(
        Label $label,
        float $gross,
        float $net
    ): int {
        return DB::table(
            'royalty_statements'
        )->insertGetId([
            'public_id' =>
                (string) Str::ulid(),

            'label_id' =>
                $label->id,

            'statement_month' =>
                '2026-06',

            'currency' =>
                'INR',

            'gross_earnings' =>
                $gross,

            'commission_amount' =>
                $gross - $net,

            'tax_amount' =>
                0,

            'other_deductions' =>
                0,

            'net_payable' =>
                $net,

            'status' =>
                'approved',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    private function allocation(
        int $statementId,
        int $reportRowId,
        float $gross,
        float $net
    ): void {
        DB::table(
            'royalty_allocations'
        )->insert([
            'public_id' =>
                (string) Str::ulid(),

            'royalty_statement_id' =>
                $statementId,

            'report_row_id' =>
                $reportRowId,

            'gross_amount' =>
                $gross,

            'net_amount' =>
                $net,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    public function test_label_http_payload_exposes_allocation_safe_dimensions_without_foreign_leakage(): void
    {
        $owner = User::factory()->create([
            'role' => 'label',
        ]);

        $foreignOwner =
            User::factory()->create([
                'role' => 'label',
            ]);

        $ownLabel = $this->label(
            $owner,
            'K57CB Own Label'
        );

        $foreignLabel = $this->label(
            $foreignOwner,
            'K57CB Foreign Label'
        );

        $importId =
            $this->reportImport();

        $ownA = $this->reportRow(
            $importId,
            $ownLabel,
            'A',
            'Subscription',
            'INR',
            'WMG'
        );

        $ownB = $this->reportRow(
            $importId,
            $ownLabel,
            'B',
            'Ad Supported',
            'USD',
            'BLV'
        );

        $foreign = $this->reportRow(
            $importId,
            $foreignLabel,
            'F',
            'Foreign Type',
            'EUR',
            'FOREIGN-CMS'
        );

        $ownStatement =
            $this->statement(
                $ownLabel,
                300,
                240
            );

        $foreignStatement =
            $this->statement(
                $foreignLabel,
                9000,
                8000
            );

        $this->allocation(
            $ownStatement,
            $ownA,
            100,
            80
        );

        $this->allocation(
            $ownStatement,
            $ownB,
            200,
            160
        );

        $this->allocation(
            $foreignStatement,
            $foreign,
            9000,
            8000
        );

        $response = $this
            ->actingAs($owner)
            ->get(route(
                'v2.analytics.index',
                [
                    'month' =>
                        '2026-06',
                ]
            ));

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->has(
                            'financialAnalytics.saleTypes',
                            2
                        )
                        ->has(
                            'financialAnalytics.currencies',
                            0
                        )
                        ->has(
                            'financialAnalytics.cms',
                            0
                        )
                        ->where(
                            'financialAnalytics.saleTypes',
                            function ($rows) {
                                $rows = collect(
                                    $rows
                                );

                                $subscription =
                                    $rows->firstWhere(
                                        'sale_type',
                                        'Subscription'
                                    );

                                $adSupported =
                                    $rows->firstWhere(
                                        'sale_type',
                                        'Ad Supported'
                                    );

                                return
                                    $subscription !== null
                                    && $adSupported !== null
                                    && abs(
                                        (float) $subscription[
                                            'gross_earnings'
                                        ] - 100.0
                                    ) < 0.000001
                                    && abs(
                                        (float) $subscription[
                                            'net_payable'
                                        ] - 80.0
                                    ) < 0.000001
                                    && abs(
                                        (float) $adSupported[
                                            'gross_earnings'
                                        ] - 200.0
                                    ) < 0.000001
                                    && abs(
                                        (float) $adSupported[
                                            'net_payable'
                                        ] - 160.0
                                    ) < 0.000001
                                    && !$rows->contains(
                                        'sale_type',
                                        'Foreign Type'
                                    );
                            }
                        )

            );

        $content =
            $response->getContent();

        $this->assertStringNotContainsString(
            'Foreign Type',
            $content
        );

        $this->assertStringNotContainsString(
            'FOREIGN-CMS',
            $content
        );

        $this->assertStringNotContainsString(
            '9000',
            $content
        );
    }
}
