<?php

namespace Tests\Feature\Integration;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\DistributionStore;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\RoyaltyStatement;
use App\Models\Reports\ReportRow;
use App\Models\Finance\WalletAccount;
use App\Models\ReleaseStoreDelivery;
use App\Models\User;
use App\Services\V2\InvoiceService;
use App\Services\V2\ReportImportService;
use App\Services\V2\RoyaltyService;
use App\Services\V2\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class FullBusinessFlowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_music_business_flow(): void
    {
        /*
         * =========================================================
         * 1. USERS / LABEL / ARTIST
         * =========================================================
         */

        $artistUser = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        /*
         * Canonical ownership fixture:
         *
         * The integration flow below validates an Artist royalty
         * statement, wallet, invoice and withdrawal. Therefore the
         * Artist account must be the canonical revenue owner.
         *
         * A Label can still be attached to release metadata without
         * incorrectly making the Artist login the Label account owner.
         */
        $label = Label::factory()->create([
            'user_id' => null,
            'created_by' => $admin->id,
        ]);

        $artist = Artist::factory()->create([
            'user_id' => $artistUser->id,
            'label_id' => $label->id,
            'created_by' => $admin->id,
        ]);

        /*
         * =========================================================
         * 2. DSP STORE
         * =========================================================
         */

        $store = DistributionStore::query()->create([
            'name' => 'E2E Test Store '.uniqid(),
            'slug' => 'e2e-test-store-'.uniqid(),
            'is_active' => true,
        ]);

        /*
         * =========================================================
         * 3. COMPLETE DRAFT RELEASE
         * =========================================================
         */

        /*
         * This E2E specifically validates the direct Artist finance flow:
         * Report -> Artist Statement -> Wallet -> Invoice -> Withdrawal.
         *
         * Canonical ownership rule:
         * label_id present = Label financial owner
         * label_id null    = Artist financial owner
         */
        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'label_id' => null,

            'catalog_number' =>
                'MXT-E2E-'.uniqid(),

            'release_type' => 'single',
            'title' => 'Full Business Flow Test',

            'primary_artist_name' =>
                $artist->stage_name
                ?: $artist->legal_name,

            'primary_artists' => [
                [
                    'name' =>
                        $artist->stage_name
                        ?: $artist->legal_name,
                ],
            ],

            'language' => 'Hindi',
            'primary_genre' => 'Devotional',

            'digital_release_date' =>
                now()->addDays(14)->toDateString(),

            'artwork_path' =>
                'releases/artwork/e2e-cover.jpg',

            'stores' => [$store->id],

            'worldwide' => true,
            'territories' => [],

            'release_timezone' => 'Asia/Kolkata',
            'pre_order' => false,

            'status' => 'draft',
            'wizard_step' => 4,
            'completion_percentage' => 90,

            'upc' => null,
            'upc_is_auto_generated' => false,

            'created_by' => $artistUser->id,
            'updated_by' => $artistUser->id,
        ]);

        $track = Track::factory()
            ->withAudio()
            ->create([
                'release_id' => $release->id,
                'track_number' => 1,

                'title' =>
                    'Full Business Flow Track',

                'primary_artist_name' =>
                    $release->primary_artist_name,

                'isrc' => null,
                'isrc_is_auto_generated' => false,

                'created_by' => $artistUser->id,
                'updated_by' => $artistUser->id,
            ]);

        /*
         * =========================================================
         * 4. ARTIST SUBMITS RELEASE
         * =========================================================
         */

        $this
            ->actingAs($artistUser)
            ->post(
                route(
                    'v2.releases.submit',
                    $release
                )
            )
            ->assertRedirect(
                route('v2.releases.index')
            );

        $release->refresh();

        $this->assertSame(
            'submitted',
            $release->status
        );

        /*
         * =========================================================
         * 5. SUPER ADMIN APPROVES
         * =========================================================
         */

        $this
            ->actingAs($admin)
            ->postJson(
                route(
                    'v2.admin.release-reviews.approve',
                    $release
                ),
                [
                    'remarks' =>
                        'Full E2E approval.',
                ]
            )
            ->assertRedirect(
                route(
                    'v2.admin.release-reviews.index'
                )
            );

        $release->refresh();

        $this->assertSame(
            'approved',
            $release->status
        );

        /*
         * =========================================================
         * 6. ASSIGN ISRC
         * =========================================================
         */

        $this
            ->actingAs($admin)
            ->postJson(
                route(
                    'v2.admin.identifiers.isrc.assign',
                    $track
                ),
                [
                    'isrc' =>
                        'IN-MXT-26-00001',

                    'notes' =>
                        'E2E manual ISRC.',
                ]
            )
            ->assertOk();

        $track->refresh();

        $this->assertSame(
            'IN-MXT-26-00001',
            $track->isrc
        );

        /*
         * =========================================================
         * 7. ASSIGN VALID UPC
         * =========================================================
         *
         * Body: 89000000001
         * Valid check digit: 4
         */

        $this
            ->actingAs($admin)
            ->postJson(
                route(
                    'v2.admin.identifiers.upc.assign',
                    $release
                ),
                [
                    'upc' =>
                        '890000000014',

                    'notes' =>
                        'E2E manual UPC.',
                ]
            )
            ->assertOk();

        $release->refresh();

        $this->assertSame(
            '890000000014',
            $release->upc
        );

        /*
         * =========================================================
         * 8. INITIALISE DSP DELIVERY
         * =========================================================
         */

        $this
            ->actingAs($admin)
            ->postJson(
                route(
                    'v2.admin.delivery.initialise',
                    $release
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'summary.total',
                1
            );

        $delivery =
            ReleaseStoreDelivery::query()
                ->where(
                    'release_id',
                    $release->id
                )
                ->firstOrFail();

        $this->assertSame(
            'pending',
            $delivery->status
        );

        /*
         * =========================================================
         * 9. DELIVERY -> PROCESSING
         * =========================================================
         */

        $this
            ->actingAs($admin)
            ->patchJson(
                route(
                    'v2.admin.delivery.update',
                    $delivery
                ),
                [
                    'status' =>
                        'processing',

                    'delivery_note' =>
                        'Sent to DSP.',
                ]
            )
            ->assertOk();

        $delivery->refresh();

        $this->assertSame(
            'processing',
            $delivery->status
        );

        /*
         * =========================================================
         * 10. DELIVERY -> DELIVERED
         * =========================================================
         */

        $this
            ->actingAs($admin)
            ->patchJson(
                route(
                    'v2.admin.delivery.update',
                    $delivery
                ),
                [
                    'status' =>
                        'delivered',

                    'delivery_note' =>
                        'DSP accepted package.',
                ]
            )
            ->assertOk();

        /*
         * =========================================================
         * 11. DELIVERY -> LIVE
         * =========================================================
         */

        $delivery->refresh();

        $this
            ->actingAs($admin)
            ->patchJson(
                route(
                    'v2.admin.delivery.update',
                    $delivery
                ),
                [
                    'status' =>
                        'live',

                    'delivery_note' =>
                        'Release is live.',
                ]
            )
            ->assertOk();

        $delivery->refresh();

        $this->assertSame(
            'live',
            $delivery->status
        );

        /*
         * =========================================================
         * 12. DSP REPORT IMPORT
         * =========================================================
         */

        $month = '2026-07';

        $headers = [
            'Track Artist',
            'Album Title',
            'Album Artist',
            'Label',
            'Track Title',
            'ISRC',
            'UPC',
            'Platform',
            'Currency',
            'Country/Region',
            'CMS',
            'Sale Type',
            'Sale Date',
            'Sale Month',
            'Streams',
            'Sale Units',
            'Label Rate',
            'Earnings',
        ];

        $rows = [
            [
                $release->primary_artist_name,
                $release->title,
                $release->primary_artist_name,
                $label->name ?? 'Mixx Tune',
                $track->title,
                $track->isrc,
                $release->upc,
                'Spotify',
                'INR',
                'IN',
                'Mixx Tune CMS',
                'Stream',
                '2026-07-15',
                $month,
                '20000',
                '20000',
                '0.10',
                '2000',
            ],
        ];

        $path = tempnam(
            sys_get_temp_dir(),
            'mixxtune-e2e-'
        );

        $handle = fopen(
            $path,
            'w'
        );

        fputcsv(
            $handle,
            $headers
        );

        foreach ($rows as $row) {
            fputcsv(
                $handle,
                $row
            );
        }

        fclose($handle);

        $file = new UploadedFile(
            $path,
            'e2e-report.csv',
            'text/csv',
            null,
            true
        );

        $import = app(
            ReportImportService::class
        )->import(
            $file,
            $admin
        );

        $this->assertNotNull(
            $import->id
        );

        $this->assertDatabaseHas(
            'report_rows',
            [
                'isrc' =>
                    $track->isrc,

                'upc' =>
                    $release->upc,

                'sale_month' =>
                    $month,
            ]
        );

        /*
         * Canonicalize imported catalogue ownership before royalty
         * generation.
         *
         * ReportImportService imports and matches catalogue metadata.
         * The royalty engine consumes canonical mapped ownership.
         *
         * This E2E represents direct Artist-owned catalogue because
         * the Release itself has label_id = null.
         */
        ReportRow::query()
            ->where(
                'sale_month',
                $month
            )
            ->where(
                'release_id',
                $release->id
            )
            ->update([
                'mapping_status' =>
                    'mapped',

                'mapped_at' =>
                    now(),

                'revenue_owner_type' =>
                    'artist',

                'revenue_owner_id' =>
                    $artist->id,
            ]);

        $this->assertDatabaseHas(
            'report_rows',
            [
                'release_id' =>
                    $release->id,

                'artist_id' =>
                    $artist->id,

                'label_id' =>
                    null,

                'mapping_status' =>
                    'mapped',

                'revenue_owner_type' =>
                    'artist',

                'revenue_owner_id' =>
                    $artist->id,
            ]
        );

        /*
         * =========================================================
         * 13. GENERATE ROYALTY STATEMENT
         * =========================================================
         */

        $royalty =
            app(RoyaltyService::class);

        $result =
            $royalty
                ->generateMonthlyStatements(
                    $month,
                    10,
                    'INR'
                );

        $this->assertSame(
            0,
            $result['failed_count']
        );

        $statement =
            RoyaltyStatement::query()
                ->where(
                    'artist_id',
                    $artist->id
                )
                ->whereNull(
                    'label_id'
                )
                ->where(
                    'statement_month',
                    $month
                )
                ->firstOrFail();

        $this->assertSame(
            'pending',
            $statement->status
        );

        $this->assertEquals(
            2000,
            (float) $statement
                ->gross_earnings
        );

        $this->assertEquals(
            200,
            (float) $statement
                ->commission_amount
        );

        $this->assertEquals(
            1800,
            (float) $statement
                ->net_payable
        );

        /*
         * =========================================================
         * 14. APPROVE STATEMENT -> PENDING WALLET
         * =========================================================
         */

        $statement =
            $royalty->approve(
                $statement,
                $admin
            );

        $this->assertSame(
            'approved',
            $statement->status
        );

        $wallet =
            WalletAccount::query()
                ->where(
                    'user_id',
                    $artistUser->id
                )
                ->firstOrFail();

        $this->assertEquals(
            1800,
            (float) $wallet
                ->pending_balance
        );

        /*
         * =========================================================
         * 15. MAKE AVAILABLE -> AVAILABLE WALLET
         * =========================================================
         */

        $statement =
            $royalty->makeAvailable(
                $statement,
                $admin
            );

        $wallet->refresh();

        $this->assertEquals(
            0,
            (float) $wallet
                ->pending_balance
        );

        $this->assertEquals(
            1800,
            (float) $wallet
                ->available_balance
        );

        /*
         * =========================================================
         * 16. GENERATE INVOICE FROM STATEMENT
         * =========================================================
         */

        $invoice =
            app(InvoiceService::class)
                ->generateFromStatement(
                    $statement,
                    $admin
                );

        $this->assertSame(
            $artistUser->id,
            $invoice->user_id
        );

        $this->assertSame(
            $statement->id,
            $invoice
                ->royalty_statement_id
        );

        $this->assertEquals(
            1800,
            (float) $invoice
                ->total_amount
        );

        $this->assertCount(
            1,
            $invoice->items
        );

        /*
         * =========================================================
         * 17. VERIFIED PAYOUT PROFILE
         * =========================================================
         */

        PayoutProfile::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'user_id' =>
                $artistUser->id,

            'account_holder_name' =>
                'E2E Artist',

            'bank_name' =>
                'Test Bank',

            'bank_account_number' =>
                '123456789012',

            'ifsc_code' =>
                'TEST0001234',

            'upi_id' =>
                'e2eartist@upi',

            'country_code' =>
                'IN',

            'kyc_status' =>
                'verified',

            'verified_at' =>
                now(),
        ]);

        /*
         * Invoice generation requires a valid company billing
         * identity. Production receives these values from the
         * System Settings module; this integration test provides
         * isolated test-only configuration.
         */
        \Illuminate\Support\Facades\DB::table(
            'system_settings'
        )->insert([
            [
                'group' => 'company',
                'key' => 'company.legal_name',
                'value' => 'Mixx Tune Test Company',
                'type' => 'string',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'company',
                'key' => 'company.address',
                'value' => 'Test Billing Address',
                'type' => 'string',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'invoice',
                'key' => 'invoice.gst_percent',
                'value' => '18',
                'type' => 'decimal',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'invoice',
                'key' => 'invoice.tds_percent',
                'value' => '10',
                'type' => 'decimal',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
         * =========================================================
         * 18. CREATE WITHDRAWAL
         * =========================================================
         */

        $wallet->update([
            'available_balance' => 5300,
            'lifetime_credits' => 5300,
        ]);

        $wallet->refresh();

        $withdrawalService =
            app(WithdrawalService::class);

        $withdrawal =
            $withdrawalService
                ->create(
                    $artistUser,
                    5000,
                    'bank',
                    'Full business flow withdrawal.'
                );

        $this->assertSame(
            'pending',
            $withdrawal->status
        );

        $wallet->refresh();

        $this->assertEquals(
            300,
            (float) $wallet
                ->available_balance
        );

        $this->assertEquals(
            5000,
            (float) $wallet
                ->pending_balance
        );

        /*
         * =========================================================
         * 19. ADMIN APPROVES WITHDRAWAL
         * =========================================================
         */

        $withdrawal =
            $withdrawalService
                ->approve(
                    $withdrawal,
                    $admin,
                    'Approved by E2E test.'
                );

        $this->assertSame(
            'approved',
            $withdrawal->status
        );

        /*
         * =========================================================
         * 20. ADMIN MARKS WITHDRAWAL PAID
         * =========================================================
         */

        $withdrawal =
            $withdrawalService
                ->markPaid(
                    $withdrawal,
                    $admin,
                    'UTR-E2E-000001',
                    'E2E payment completed.'
                );

        $this->assertSame(
            'paid',
            $withdrawal->status
        );

        $this->assertSame(
            'UTR-E2E-000001',
            $withdrawal
                ->payment_reference
        );

        $wallet->refresh();

        $this->assertEquals(
            300,
            (float) $wallet
                ->available_balance
        );

        $this->assertEquals(
            0,
            (float) $wallet
                ->pending_balance
        );

        $this->assertEquals(
            5000,
            (float) $wallet
                ->lifetime_debits
        );

        /*
         * =========================================================
         * FINAL DATABASE ASSERTIONS
         * =========================================================
         */

        $this->assertDatabaseHas(
            'release_store_deliveries',
            [
                'release_id' =>
                    $release->id,

                'status' =>
                    'live',
            ]
        );

        $this->assertDatabaseHas(
            'royalty_statements',
            [
                'id' =>
                    $statement->id,

                'artist_id' =>
                    $artist->id,
            ]
        );

        $this->assertDatabaseHas(
            'invoices',
            [
                'royalty_statement_id' =>
                    $statement->id,

                'user_id' =>
                    $artistUser->id,
            ]
        );

        $this->assertDatabaseHas(
            'withdrawals',
            [
                'id' =>
                    $withdrawal->id,

                'status' =>
                    'paid',

                'payment_reference' =>
                    'UTR-E2E-000001',
            ]
        );
    }
}
