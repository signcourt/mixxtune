<?php

namespace Tests\Feature\Royalties;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\Finance\RoyaltyAllocation;
use App\Models\Finance\RoyaltyStatement;
use App\Models\Finance\WalletAccount;
use App\Models\Finance\WalletTransaction;
use App\Models\Reports\ReportRow;
use App\Models\User;
use App\Services\V2\ReportImportService;
use App\Services\V2\RoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoyaltyStatementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createContext(
        int $rowCount = 2,
        string $month = '2026-07'
    ): array {
        Storage::fake('local');

        $artistUser = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $label = Label::factory()->create([
            'created_by' => $artistUser->id,
        ]);

        $artist = Artist::factory()->create([
            'user_id' => $artistUser->id,
            'label_id' => $label->id,
            'created_by' => $artistUser->id,
        ]);

        static $releaseSequence = 1;

        $releaseSequence++;

        $release = Release::factory()->create([
            'artist_id' => $artist->id,

            /*
             * Legacy workflow tests represent
             * direct artist-owned catalogue.
             *
             * The label object still exists for
             * relationship/context coverage, but
             * financial catalogue ownership here
             * belongs directly to the artist.
             */
            'label_id' => null,
            'catalog_number' =>
                'MXT-ROY-'.uniqid(),
            'title' =>
                'Royalty Workflow Release',
            'primary_artist_name' =>
                $artist->stage_name
                ?: $artist->legal_name,
            'upc' =>
                '89'
                .str_pad(
                    (string) $releaseSequence,
                    9,
                    '0',
                    STR_PAD_LEFT
                )
                .'3',
            'status' => 'live',
            'created_by' => $artistUser->id,
            'updated_by' => $artistUser->id,
        ]);

        $track = Track::factory()
            ->withAudio()
            ->create([
                'release_id' => $release->id,
                'track_number' => 1,
                'title' =>
                    'Royalty Workflow Track',
                'primary_artist_name' =>
                    $release->primary_artist_name,
                'isrc' =>
                    'IN-MXT-26-'
                    .str_pad(
                        (string) $releaseSequence,
                        5,
                        '0',
                        STR_PAD_LEFT
                    ),
                'created_by' => $artistUser->id,
                'updated_by' => $artistUser->id,
            ]);

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $rows = [];

        for ($index = 1; $index <= $rowCount; $index++) {
            $rows[] = [
                'Royalty Artist',
                $release->title,
                'Royalty Artist',
                'Mixx Tune',
                $track->title,
                $track->isrc,
                $release->upc,
                $index % 2 === 0
                    ? 'YouTube'
                    : 'Spotify',
                'INR',
                'IN',
                'Mixx Tune CMS',
                'Stream',
                "2026-07-{$index}",
                $month,
                (string) (1000 * $index),
                (string) (1000 * $index),
                '0.10',
                (string) (100 * $index),
            ];
        }

        $file = $this->csvFile(
            $this->headers(),
            $rows
        );

        app(ReportImportService::class)
            ->import(
                $file,
                $admin
            );

        /*
         * Canonical ownership fixture.
         *
         * ReportImportService is responsible for
         * importing/matching catalogue metadata.
         * Ownership repair normally canonicalizes
         * these fields before royalty generation.
         *
         * These legacy workflow tests call the
         * royalty generator directly, so make that
         * canonical ownership state explicit here.
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

        return [
            $artistUser,
            $label,
            $artist,
            $release,
            $track,
            $admin,
            $month,
        ];
    }

    private function headers(): array
    {
        return [
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
    }

    private function csvFile(
        array $headers,
        array $rows
    ): UploadedFile {
        $path = tempnam(
            sys_get_temp_dir(),
            'mixxtune-royalty-'
        );

        $handle = fopen($path, 'w');

        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return new UploadedFile(
            $path,
            'royalty-report.csv',
            'text/csv',
            null,
            true
        );
    }

    private function royalty(): RoyaltyService
    {
        return app(RoyaltyService::class);
    }

    private function statement(
        Artist $artist,
        string $month
    ): RoyaltyStatement {
        return RoyaltyStatement::query()
            ->where(
                'artist_id',
                $artist->id
            )
            ->whereNull('label_id')
            ->where(
                'statement_month',
                $month
            )
            ->firstOrFail();
    }

    public function test_monthly_statement_is_generated(): void
    {
        [
            ,
            ,
            $artist,
            ,
            ,
            ,
            $month,
        ] = $this->createContext();

        $result = $this
            ->royalty()
            ->generateMonthlyStatements(
                $month,
                10,
                'INR'
            );

        $this->assertSame(
            1,
            $result['created']
        );

        $this->assertSame(
            0,
            $result['failed_count']
        );

        $statement = $this->statement(
            $artist,
            $month
        );

        $this->assertSame(
            'pending',
            $statement->status
        );

        $this->assertEquals(
            300.00,
            (float) $statement
                ->gross_earnings
        );

        $this->assertEquals(
            30.00,
            (float) $statement
                ->commission_amount
        );

        $this->assertEquals(
            270.00,
            (float) $statement
                ->net_payable
        );

        $this->assertSame(
            'INR',
            $statement->currency
        );
    }

    public function test_statement_allocations_are_created(): void
    {
        [
            ,
            ,
            $artist,
            ,
            ,
            ,
            $month,
        ] = $this->createContext();

        $this
            ->royalty()
            ->generateMonthlyStatements(
                $month,
                10,
                'INR'
            );

        $statement = $this->statement(
            $artist,
            $month
        );

        $allocations =
            RoyaltyAllocation::query()
                ->where(
                    'royalty_statement_id',
                    $statement->id
                )
                ->orderBy('id')
                ->get();

        $this->assertCount(
            2,
            $allocations
        );

        $this->assertEquals(
            100.00,
            (float) $allocations
                ->get(0)
                ->gross_amount
        );

        $this->assertEquals(
            90.00,
            (float) $allocations
                ->get(0)
                ->net_amount
        );

        $this->assertEquals(
            200.00,
            (float) $allocations
                ->get(1)
                ->gross_amount
        );

        $this->assertEquals(
            180.00,
            (float) $allocations
                ->get(1)
                ->net_amount
        );

        $this->assertEquals(
            100,
            (float) $allocations
                ->first()
                ->share_percentage
        );
    }

    public function test_generation_uses_only_selected_month(): void
    {
        [
            ,
            ,
            $artist,
        ] = $this->createContext(
            2,
            '2026-07'
        );

        $this->createContext(
            1,
            '2026-08'
        );

        $this
            ->royalty()
            ->generateMonthlyStatements(
                '2026-07',
                0,
                'INR'
            );

        $statement = $this->statement(
            $artist,
            '2026-07'
        );

        $this->assertEquals(
            300.00,
            (float) $statement
                ->gross_earnings
        );
    }

    public function test_generation_is_idempotent(): void
    {
        [
            ,
            ,
            $artist,
            ,
            ,
            ,
            $month,
        ] = $this->createContext();

        $service = $this->royalty();

        $first = $service
            ->generateMonthlyStatements(
                $month,
                10,
                'INR'
            );

        $statement = $this->statement(
            $artist,
            $month
        );

        $publicId =
            $statement->public_id;

        $second = $service
            ->generateMonthlyStatements(
                $month,
                10,
                'INR'
            );

        $this->assertSame(
            1,
            $first['created']
        );

        $this->assertSame(
            1,
            $second['updated']
        );

        $this->assertDatabaseCount(
            'royalty_statements',
            1
        );

        $this->assertSame(
            $publicId,
            $this->statement(
                $artist,
                $month
            )->public_id
        );

        $this->assertDatabaseCount(
            'royalty_allocations',
            2
        );
    }

    public function test_regeneration_rebuilds_allocations(): void
    {
        [
            ,
            ,
            $artist,
            ,
            ,
            ,
            $month,
        ] = $this->createContext();

        $service = $this->royalty();

        $service->generateMonthlyStatements(
            $month,
            10,
            'INR'
        );

        $statement = $this->statement(
            $artist,
            $month
        );

        RoyaltyAllocation::query()
            ->where(
                'royalty_statement_id',
                $statement->id
            )
            ->first()
            ->update([
                'net_amount' => 1,
            ]);

        $service->generateMonthlyStatements(
            $month,
            10,
            'INR'
        );

        $this->assertEquals(
            270.00,
            (float) RoyaltyAllocation::query()
                ->where(
                    'royalty_statement_id',
                    $statement->id
                )
                ->sum('net_amount')
        );
    }

    public function test_zero_commission_statement(): void
    {
        [
            ,
            ,
            $artist,
            ,
            ,
            ,
            $month,
        ] = $this->createContext();

        $this
            ->royalty()
            ->generateMonthlyStatements(
                $month,
                0,
                'INR'
            );

        $statement = $this->statement(
            $artist,
            $month
        );

        $this->assertEquals(
            0,
            (float) $statement
                ->commission_amount
        );

        $this->assertEquals(
            300,
            (float) $statement
                ->net_payable
        );
    }

    public function test_statement_approval_credits_pending_wallet(): void
    {
        [
            $artistUser,
            ,
            $artist,
            ,
            ,
            $admin,
            $month,
        ] = $this->createContext();

        $service = $this->royalty();

        $service->generateMonthlyStatements(
            $month,
            10,
            'INR'
        );

        $statement = $service->approve(
            $this->statement(
                $artist,
                $month
            ),
            $admin
        );

        $this->assertSame(
            'approved',
            $statement->status
        );

        $this->assertNotNull(
            $statement->approved_at
        );

        $this->assertSame(
            $admin->id,
            $statement->approved_by
        );

        $wallet = WalletAccount::query()
            ->where(
                'user_id',
                $artistUser->id
            )
            ->firstOrFail();

        $this->assertEquals(
            270.00,
            (float) $wallet
                ->pending_balance
        );

        $this->assertEquals(
            0,
            (float) $wallet
                ->available_balance
        );

        $this->assertEquals(
            270.00,
            (float) $wallet
                ->lifetime_earnings
        );
    }

    public function test_approval_creates_wallet_transaction(): void
    {
        [
            $artistUser,
            ,
            $artist,
            ,
            ,
            $admin,
            $month,
        ] = $this->createContext();

        $service = $this->royalty();

        $service->generateMonthlyStatements(
            $month,
            10,
            'INR'
        );

        $statement = $service->approve(
            $this->statement(
                $artist,
                $month
            ),
            $admin
        );

        $wallet = WalletAccount::query()
            ->where(
                'user_id',
                $artistUser->id
            )
            ->firstOrFail();

        $this->assertDatabaseHas(
            'wallet_transactions',
            [
                'wallet_id' =>
                    $wallet->id,

                'direction' => 'credit',

                'transaction_type' =>
                    'royalty_statement',

                'reference_type' =>
                    RoyaltyStatement::class,

                'reference_id' =>
                    $statement->id,
            ]
        );
    }

    public function test_approved_statement_can_be_made_available(): void
    {
        [
            $artistUser,
            ,
            $artist,
            ,
            ,
            $admin,
            $month,
        ] = $this->createContext();

        $service = $this->royalty();

        $service->generateMonthlyStatements(
            $month,
            10,
            'INR'
        );

        $statement = $service->approve(
            $this->statement(
                $artist,
                $month
            ),
            $admin
        );

        $statement = $service
            ->makeAvailable(
                $statement,
                $admin
            );

        $this->assertSame(
            'available',
            $statement->status
        );

        $this->assertNotNull(
            $statement->available_at
        );

        $wallet = WalletAccount::query()
            ->where(
                'user_id',
                $artistUser->id
            )
            ->firstOrFail();

        $this->assertEquals(
            0,
            (float) $wallet
                ->pending_balance
        );

        $this->assertEquals(
            270.00,
            (float) $wallet
                ->available_balance
        );

        $this->assertEquals(
            270.00,
            (float) $wallet
                ->lifetime_earnings
        );
    }

    public function test_make_available_creates_two_wallet_transactions(): void
    {
        [
            $artistUser,
            ,
            $artist,
            ,
            ,
            $admin,
            $month,
        ] = $this->createContext();

        $service = $this->royalty();

        $service->generateMonthlyStatements(
            $month,
            10,
            'INR'
        );

        $statement = $service->approve(
            $this->statement(
                $artist,
                $month
            ),
            $admin
        );

        $service->makeAvailable(
            $statement,
            $admin
        );

        $wallet = WalletAccount::query()
            ->where(
                'user_id',
                $artistUser->id
            )
            ->firstOrFail();

        $this->assertDatabaseHas(
            'wallet_transactions',
            [
                'wallet_id' =>
                    $wallet->id,

                'direction' => 'debit',

                'transaction_type' =>
                    'pending_release',

                'reference_id' =>
                    $statement->id,
            ]
        );

        $this->assertDatabaseHas(
            'wallet_transactions',
            [
                'wallet_id' =>
                    $wallet->id,

                'direction' => 'credit',

                'transaction_type' =>
                    'available_credit',

                'reference_id' =>
                    $statement->id,
            ]
        );

        $this->assertSame(
            3,
            WalletTransaction::query()
                ->where(
                    'wallet_id',
                    $wallet->id
                )
                ->count()
        );
    }

    public function test_pending_statement_cannot_be_made_available(): void
    {
        [
            ,
            ,
            $artist,
            ,
            ,
            $admin,
            $month,
        ] = $this->createContext();

        $service = $this->royalty();

        $service->generateMonthlyStatements(
            $month,
            10,
            'INR'
        );

        $this->expectException(
            \Symfony\Component\HttpKernel\Exception\HttpException::class
        );

        $service->makeAvailable(
            $this->statement(
                $artist,
                $month
            ),
            $admin
        );
    }

    public function test_approved_statement_cannot_be_approved_again(): void
    {
        [
            ,
            ,
            $artist,
            ,
            ,
            $admin,
            $month,
        ] = $this->createContext();

        $service = $this->royalty();

        $service->generateMonthlyStatements(
            $month,
            10,
            'INR'
        );

        $statement = $service->approve(
            $this->statement(
                $artist,
                $month
            ),
            $admin
        );

        $this->expectException(
            \Symfony\Component\HttpKernel\Exception\HttpException::class
        );

        $service->approve(
            $statement,
            $admin
        );
    }

    public function test_unmatched_report_rows_do_not_generate_statements(): void
    {
        [
            ,
            ,
            ,
            ,
            ,
            ,
            $month,
        ] = $this->createContext();

        /*
         * Canonical unmatched state.
         *
         * Catalogue relationship fields such as
         * artist_id are metadata only. Royalty
         * generation is controlled by canonical
         * mapping + revenue ownership fields.
         */
        ReportRow::query()->update([
            'artist_id' =>
                null,

            'label_id' =>
                null,

            'mapping_status' =>
                'unmapped',

            'mapped_at' =>
                null,

            'revenue_owner_type' =>
                null,

            'revenue_owner_id' =>
                null,
        ]);

        $result = $this
            ->royalty()
            ->generateMonthlyStatements(
                $month,
                10,
                'INR'
            );

        $this->assertSame(
            0,
            $result['created']
        );

        $this->assertDatabaseCount(
            'royalty_statements',
            0
        );
    }

    public function test_multiple_artists_receive_separate_statements(): void
    {
        $first = $this->createContext(
            1,
            '2026-07'
        );

        $second = $this->createContext(
            1,
            '2026-07'
        );

        $result = $this
            ->royalty()
            ->generateMonthlyStatements(
                '2026-07',
                10,
                'INR'
            );

        $this->assertSame(
            2,
            $result['created']
        );

        $this->assertDatabaseCount(
            'royalty_statements',
            2
        );

        $this->assertDatabaseHas(
            'royalty_statements',
            [
                'artist_id' =>
                    $first[2]->id,

                'statement_month' =>
                    '2026-07',
            ]
        );

        $this->assertDatabaseHas(
            'royalty_statements',
            [
                'artist_id' =>
                    $second[2]->id,

                'statement_month' =>
                    '2026-07',
            ]
        );
    }

    public function test_large_month_creates_all_allocations(): void
    {
        [
            ,
            ,
            $artist,
            ,
            ,
            ,
            $month,
        ] = $this->createContext(
            50,
            '2026-07'
        );

        $this
            ->royalty()
            ->generateMonthlyStatements(
                $month,
                5,
                'INR'
            );

        $statement = $this->statement(
            $artist,
            $month
        );

        $this->assertSame(
            50,
            RoyaltyAllocation::query()
                ->where(
                    'royalty_statement_id',
                    $statement->id
                )
                ->count()
        );

        $expectedGross =
            array_sum(
                array_map(
                    fn ($number) =>
                        100 * $number,
                    range(1, 50)
                )
            );

        $this->assertEquals(
            $expectedGross,
            (float) $statement
                ->gross_earnings
        );
    }

    public function test_canonical_label_owned_catalogue_can_split_revenue_with_direct_artist(): void
    {
        $month = '2026-08';

        $labelOwner =
            User::factory()->create([
                'role' => 'label',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]);

        $artistUser =
            User::factory()->create([
                'role' => 'artist',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]);

        $labelId =
            DB::table('labels')
                ->insertGetId([
                    'public_id' =>
                        (string) Str::ulid(),

                    'name' =>
                        'Canonical Master Label',

                    'slug' =>
                        'canonical-master-label',

                    'user_id' =>
                        $labelOwner->id,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $artistId =
            DB::table('artists')
                ->insertGetId([
                    'public_id' =>
                        (string) Str::ulid(),

                    'stage_name' =>
                        'Canonical Direct Artist',

                    'slug' =>
                        'canonical-direct-artist',

                    'user_id' =>
                        $artistUser->id,

                    'label_id' =>
                        $labelId,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        DB::table(
            'label_revenue_shares'
        )->insert([
            'master_label_id' =>
                $labelId,

            'beneficiary_type' =>
                'artist',

            'beneficiary_id' =>
                $artistId,

            'revenue_share_percent' =>
                70,

            'show_revenue_share' =>
                false,

            'is_active' =>
                true,

            'effective_from' =>
                '2026-08-01',

            'effective_to' =>
                null,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $importId =
            DB::table('report_imports')
                ->insertGetId([
                    'public_id' =>
                        (string)
                            \Illuminate\Support\Str::ulid(),

                    'original_filename' =>
                        'canonical-split.csv',

                    'stored_path' =>
                        'tests/canonical-split.csv',

                    'status' =>
                        'completed',

                    'total_rows' =>
                        1,

                    'imported_rows' =>
                        1,

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

        $reportRowId =
            DB::table('report_rows')
                ->insertGetId([
                    'row_hash' =>
                        hash(
                            'sha256',
                            'canonical-label-artist-split'
                        ),

                    'report_import_id' =>
                        $importId,

                    'artist_id' =>
                        $artistId,

                    'label_id' =>
                        $labelId,

                    'track_artist' =>
                        'Canonical Direct Artist',

                    'album_title' =>
                        'Canonical Album',

                    'album_artist' =>
                        'Canonical Direct Artist',

                    'label_name' =>
                        'Canonical Master Label',

                    'track_title' =>
                        'Canonical Track',

                    'platform' =>
                        'Spotify',

                    'currency' =>
                        'INR',

                    'sale_date' =>
                        '2026-08-15',

                    'sale_month' =>
                        $month,

                    'earnings' =>
                        2500,

                    'mapping_status' =>
                        'mapped',

                    'mapped_at' =>
                        now(),

                    /*
                     * Critical invariant:
                     * catalogue ownership remains
                     * with the master label.
                     */
                    'revenue_owner_type' =>
                        'label',

                    'revenue_owner_id' =>
                        $labelId,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $result =
            $this
                ->royalty()
                ->generateMonthlyStatements(
                    $month,
                    0,
                    'INR'
                );

        $this->assertSame(
            2,
            $result['created']
        );

        $this->assertSame(
            0,
            $result['failed_count']
        );

        $labelStatement =
            DB::table('royalty_statements')
                ->where(
                    'statement_month',
                    $month
                )
                ->where(
                    'label_id',
                    $labelId
                )
                ->whereNull(
                    'artist_id'
                )
                ->first();

        $artistStatement =
            DB::table('royalty_statements')
                ->where(
                    'statement_month',
                    $month
                )
                ->where(
                    'artist_id',
                    $artistId
                )
                ->whereNull(
                    'label_id'
                )
                ->first();

        $this->assertNotNull(
            $labelStatement
        );

        $this->assertNotNull(
            $artistStatement
        );

        /*
         * Master retains 30%.
         */
        $this->assertEquals(
            750.00,
            (float)
                $labelStatement
                    ->gross_earnings
        );

        $this->assertEquals(
            750.00,
            (float)
                $labelStatement
                    ->net_payable
        );

        /*
         * Direct artist receives 70%.
         */
        $this->assertEquals(
            1750.00,
            (float)
                $artistStatement
                    ->gross_earnings
        );

        $this->assertEquals(
            1750.00,
            (float)
                $artistStatement
                    ->net_payable
        );

        /*
         * Financial conservation:
         * split must never duplicate DSP gross.
         */
        $this->assertEquals(
            2500.00,
            (float)
                $labelStatement
                    ->gross_earnings
            +
            (float)
                $artistStatement
                    ->gross_earnings
        );

        $labelAllocation =
            DB::table('royalty_allocations')
                ->where(
                    'royalty_statement_id',
                    $labelStatement->id
                )
                ->where(
                    'report_row_id',
                    $reportRowId
                )
                ->first();

        $artistAllocation =
            DB::table('royalty_allocations')
                ->where(
                    'royalty_statement_id',
                    $artistStatement->id
                )
                ->where(
                    'report_row_id',
                    $reportRowId
                )
                ->first();

        $this->assertNotNull(
            $labelAllocation
        );

        $this->assertNotNull(
            $artistAllocation
        );

        $this->assertEquals(
            30.00,
            (float)
                $labelAllocation
                    ->share_percentage
        );

        $this->assertEquals(
            750.00,
            (float)
                $labelAllocation
                    ->gross_amount
        );

        $this->assertEquals(
            70.00,
            (float)
                $artistAllocation
                    ->share_percentage
        );

        $this->assertEquals(
            1750.00,
            (float)
                $artistAllocation
                    ->gross_amount
        );

        /*
         * Catalogue ownership must NOT be
         * rewritten to artist ownership.
         */
        $sourceRow =
            DB::table('report_rows')
                ->where(
                    'id',
                    $reportRowId
                )
                ->first();

        $this->assertSame(
            'label',
            $sourceRow
                ->revenue_owner_type
        );

        $this->assertSame(
            $labelId,
            (int)
                $sourceRow
                    ->revenue_owner_id
        );

        /*
         * Allocation conservation.
         */
        $this->assertEquals(
            2500.00,
            (float)
                $labelAllocation
                    ->gross_amount
            +
            (float)
                $artistAllocation
                    ->gross_amount
        );
    }


}
