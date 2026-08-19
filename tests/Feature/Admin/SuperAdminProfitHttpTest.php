<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminProfitHttpTest extends TestCase
{
    use RefreshDatabase;

    private function user(
        string $role
    ): User {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function label(
        User $user,
        string $name,
        float $rate
    ): int {
        return DB::table('labels')
            ->insertGetId([
                'public_id' =>
                    (string) Str::ulid(),

                'name' =>
                    $name,

                'slug' =>
                    Str::slug($name)
                    .'-'
                    .Str::lower(
                        Str::random(6)
                    ),

                'user_id' =>
                    $user->id,

                'revenue_share_percentage' =>
                    $rate,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
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
                'super-admin-profit-http.csv',

            'stored_path' =>
                'tests/super-admin-profit-http.csv',

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

    private function row(
        int $importId,
        int $labelId,
        string $labelName,
        string $platform,
        string $reportingMonth,
        string $saleMonth,
        float $earnings,
        string $key
    ): void {
        DB::table('report_rows')
            ->insert([
                'report_import_id' =>
                    $importId,

                'row_hash' =>
                    hash(
                        'sha256',
                        $key
                    ),

                'reporting_month' =>
                    $reportingMonth,

                'label_id' =>
                    $labelId,

                'revenue_owner_type' =>
                    'label',

                'revenue_owner_id' =>
                    $labelId,

                'mapping_status' =>
                    'mapped',

                'mapped_at' =>
                    now(),

                'label_name' =>
                    $labelName,

                'track_title' =>
                    'Profit HTTP '.$key,

                'track_artist' =>
                    'Profit Test Artist',

                'album_title' =>
                    'Profit Test Album',

                'album_artist' =>
                    'Profit Test Artist',

                'platform' =>
                    $platform,

                'currency' =>
                    'INR',

                'country_code' =>
                    'IN',

                'sale_type' =>
                    'Stream',

                'sale_date' =>
                    $saleMonth.'-15',

                'sale_month' =>
                    $saleMonth,

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

    private function csvBody(
        $response
    ): string {
        ob_start();

        $response
            ->baseResponse
            ->sendContent();

        return (string)
            ob_get_clean();
    }

    public function test_super_admin_can_open_profit_page_with_filters(): void
    {
        $super =
            $this->user(
                'super_admin'
            );

        $labelUser =
            $this->user(
                'label'
            );

        $labelId =
            $this->label(
                $labelUser,
                'Profit Filter Label',
                80
            );

        $importId =
            $this->reportImport();

        $this->row(
            $importId,
            $labelId,
            'Profit Filter Label',
            'Spotify',
            '2026-08',
            '2026-08',
            100,
            'aug-spotify'
        );

        $this->row(
            $importId,
            $labelId,
            'Profit Filter Label',
            'Apple Music',
            '2026-08',
            '2026-08',
            200,
            'aug-apple'
        );

        $this
            ->actingAs($super)
            ->get(
                route(
                    'v2.admin.profit.index',
                    [
                        'month' =>
                            '2026-08',

                        'platform' =>
                            'Spotify',

                        'owner_type' =>
                            'label',

                        'owner_id' =>
                            $labelId,
                    ]
                )
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'V2/Admin/Profit/Index'
                        )
                        ->where(
                            'filters.month',
                            '2026-08'
                        )
                        ->where(
                            'filters.platform',
                            'Spotify'
                        )
                        ->where(
                            'filters.owner_type',
                            'label'
                        )
                        ->where(
                            'filters.owner_id',
                            $labelId
                        )
                        ->where(
                            'summary.collected_revenue',
                            fn ($value) =>
                                abs(
                                    (float) $value
                                    - 100.0
                                ) < 0.000001
                        )
                        ->where(
                            'summary.user_earning',
                            fn ($value) =>
                                abs(
                                    (float) $value
                                    - 80.0
                                ) < 0.000001
                        )
                        ->where(
                            'summary.super_admin_profit',
                            fn ($value) =>
                                abs(
                                    (float) $value
                                    - 20.0
                                ) < 0.000001
                        )
            );
    }

    public function test_profit_owner_selector_contains_mapped_label(): void
    {
        $super =
            $this->user(
                'super_admin'
            );

        $labelUser =
            $this->user(
                'label'
            );

        $labelId =
            $this->label(
                $labelUser,
                'Owner Selector Label',
                85
            );

        $importId =
            $this->reportImport();

        $this->row(
            $importId,
            $labelId,
            'Owner Selector Label',
            'Spotify',
            '2026-08',
            '2026-08',
            100,
            'owner-selector'
        );

        $this
            ->actingAs($super)
            ->get(
                route(
                    'v2.admin.profit.index'
                )
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->has('owners')
                        ->where(
                            'owners',
                            fn ($owners) =>
                                collect($owners)
                                    ->contains(
                                        fn ($owner) =>
                                            (string)
                                                data_get(
                                                    $owner,
                                                    'type'
                                                )
                                                === 'label'
                                            &&
                                            (int)
                                                data_get(
                                                    $owner,
                                                    'id'
                                                )
                                                === $labelId
                                            &&
                                            (string)
                                                data_get(
                                                    $owner,
                                                    'name'
                                                )
                                                ===
                                                'Owner Selector Label'
                                    )
                        )
            );
    }

    public function test_profit_export_uses_filters_and_canonical_month(): void
    {
        $super =
            $this->user(
                'super_admin'
            );

        $labelUser =
            $this->user(
                'label'
            );

        $labelId =
            $this->label(
                $labelUser,
                'Export Profit Label',
                80
            );

        $otherUser =
            $this->user(
                'label'
            );

        $otherLabelId =
            $this->label(
                $otherUser,
                'Foreign Profit Label',
                50
            );

        $importId =
            $this->reportImport();

        /*
         * Legacy row:
         * reporting_month missing,
         * sale_month is authoritative fallback.
         */
        DB::table('report_rows')
            ->insert([
                'report_import_id' =>
                    $importId,

                'row_hash' =>
                    hash(
                        'sha256',
                        'canonical-export-row'
                    ),

                'reporting_month' =>
                    null,

                'label_id' =>
                    $labelId,

                'revenue_owner_type' =>
                    'label',

                'revenue_owner_id' =>
                    $labelId,

                'mapping_status' =>
                    'mapped',

                'mapped_at' =>
                    now(),

                'label_name' =>
                    'Export Profit Label',

                'track_title' =>
                    'Canonical Export Track',

                'track_artist' =>
                    'Export Artist',

                'album_title' =>
                    'Export Album',

                'album_artist' =>
                    'Export Artist',

                'platform' =>
                    'Spotify',

                'currency' =>
                    'INR',

                'country_code' =>
                    'IN',

                'sale_type' =>
                    'Stream',

                'sale_date' =>
                    '2026-08-15',

                'sale_month' =>
                    '2026-08',

                'streams' =>
                    100,

                'sale_units' =>
                    100,

                'label_rate' =>
                    100,

                'collected_revenue' =>
                    100,

                'earnings' =>
                    100,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        $this->row(
            $importId,
            $otherLabelId,
            'Foreign Profit Label',
            'Spotify',
            '2026-08',
            '2026-08',
            999,
            'foreign-export'
        );

        $response =
            $this
                ->actingAs($super)
                ->get(
                    route(
                        'v2.admin.profit.export',
                        [
                            'month' =>
                                '2026-08',

                            'platform' =>
                                'Spotify',

                            'owner_type' =>
                                'label',

                            'owner_id' =>
                                $labelId,
                        ]
                    )
                );

        $response
            ->assertOk();

        $this->assertStringContainsString(
            'text/csv',
            (string)
                $response
                    ->headers
                    ->get(
                        'Content-Type'
                    )
        );

        $csv =
            $this->csvBody(
                $response
            );

        $this->assertStringContainsString(
            'Sale Month',
            $csv
        );

        $this->assertStringContainsString(
            '2026-08',
            $csv
        );

        $this->assertStringContainsString(
            ',100.00000000,80.0000,80.00000000,20.00000000',
            $csv
        );

        $this->assertStringNotContainsString(
            '999.00000000',
            $csv
        );
    }

    public function test_admin_cannot_open_or_export_super_admin_profit(): void
    {
        $admin =
            $this->user(
                'admin'
            );

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'v2.admin.profit.index'
                )
            )
            ->assertForbidden();

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'v2.admin.profit.export'
                )
            )
            ->assertForbidden();
    }
}
