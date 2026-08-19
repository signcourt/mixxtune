<?php

namespace Tests\Feature\Reports;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\Reports\ReportImport;
use App\Models\Reports\ReportRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportImportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const STORE_ROUTE = 'v2.admin.reports.imports.store';

    private function createContext(): array
    {
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

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'catalog_number' => 'MXT-RPT-'.uniqid(),
            'title' => 'Report Import Release',
            'primary_artist_name' =>
                $artist->stage_name
                ?: $artist->legal_name,
            'upc' => '890000000013',
            'status' => 'live',
            'created_by' => $artistUser->id,
            'updated_by' => $artistUser->id,
        ]);

        $track = Track::factory()
            ->withAudio()
            ->create([
                'release_id' => $release->id,
                'track_number' => 1,
                'title' => 'Report Import Track',
                'primary_artist_name' =>
                    $release->primary_artist_name,
                'isrc' => 'IN-MXT-26-00001',
                'created_by' => $artistUser->id,
                'updated_by' => $artistUser->id,
            ]);

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        return [
            $artistUser,
            $label,
            $artist,
            $release,
            $track,
            $admin,
        ];
    }

    private function importUrl(): string
    {
        return route(self::STORE_ROUTE);
    }

    private function csvFile(
        array $headers,
        array $rows,
        string $name = 'report.csv'
    ): UploadedFile {
        $path = tempnam(
            sys_get_temp_dir(),
            'mixxtune-report-'
        );

        $handle = fopen($path, 'w');

        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return new UploadedFile(
            $path,
            $name,
            'text/csv',
            null,
            true
        );
    }

    private function standardHeaders(): array
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

    private function standardRow(
        string $isrc = 'IN-MXT-26-00001',
        string $upc = '890000000013',
        string $saleDate = '2026-07-15',
        string $saleMonth = '2026-07',
        string $earnings = '125.50'
    ): array {
        return [
            'Test Artist',
            'Report Import Release',
            'Test Artist',
            'Mixx Tune',
            'Report Import Track',
            $isrc,
            $upc,
            'Spotify',
            'INR',
            'IN',
            'Mixx Tune CMS',
            'Stream',
            $saleDate,
            $saleMonth,
            '1000',
            '1000',
            '0.1255',
            $earnings,
        ];
    }

    public function test_super_admin_can_import_valid_csv(): void
    {
        Storage::fake('local');

        [
            ,
            ,
            ,
            $release,
            $track,
            $admin,
        ] = $this->createContext();

        $file = $this->csvFile(
            $this->standardHeaders(),
            [
                $this->standardRow(),
            ]
        );

        $response = $this
            ->actingAs($admin)
            ->post(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            );

        $response
            ->assertRedirect()
            ->assertSessionHas(
                'success'
            );

        $import = ReportImport::query()
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            'completed',
            $import->status
        );

        $this->assertSame(
            1,
            (int) $import->total_rows
        );

        $this->assertSame(
            1,
            (int) $import->imported_rows
        );

        $this->assertSame(
            0,
            (int) $import->duplicate_rows
        );

        $this->assertSame(
            0,
            (int) $import->failed_rows
        );

        $row = ReportRow::query()
            ->firstOrFail();

        $this->assertSame(
            $release->id,
            $row->release_id
        );

        $this->assertSame(
            $track->id,
            $row->track_id
        );

        $this->assertSame(
            'Spotify',
            $row->platform
        );

        $this->assertEquals(
            125.50,
            (float) $row->earnings
        );
    }

    public function test_isrc_matches_track_and_release(): void
    {
        Storage::fake('local');

        [
            ,
            ,
            ,
            $release,
            $track,
            $admin,
        ] = $this->createContext();

        $file = $this->csvFile(
            $this->standardHeaders(),
            [
                $this->standardRow(
                    $track->isrc,
                    ''
                ),
            ]
        );

        $this
            ->actingAs($admin)
            ->post(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            )
            ->assertRedirect();

        $row = ReportRow::query()
            ->firstOrFail();

        $this->assertSame(
            $track->id,
            $row->track_id
        );

        $this->assertSame(
            $release->id,
            $row->release_id
        );

        $this->assertSame(
            $release->artist_id,
            $row->artist_id
        );

        $this->assertSame(
            $release->label_id,
            $row->label_id
        );
    }

    public function test_upc_matches_release_when_isrc_is_missing(): void
    {
        Storage::fake('local');

        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext();

        $file = $this->csvFile(
            $this->standardHeaders(),
            [
                $this->standardRow(
                    '',
                    $release->upc
                ),
            ]
        );

        $this
            ->actingAs($admin)
            ->post(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            )
            ->assertRedirect();

        $row = ReportRow::query()
            ->firstOrFail();

        $this->assertNull(
            $row->track_id
        );

        $this->assertNull(
            $row->release_id
        );

        $this->assertNull(
            $row->artist_id
        );

        $this->assertNull(
            $row->label_id
        );

        $this->assertSame(
            'unmapped',
            $row->mapping_status
        );

        $this->assertSame(
            $release->upc,
            $row->upc
        );
    }

    public function test_unknown_identifiers_create_unmatched_row(): void
    {
        Storage::fake('local');

        [
            ,
            ,
            ,
            ,
            ,
            $admin,
        ] = $this->createContext();

        $file = $this->csvFile(
            $this->standardHeaders(),
            [
                $this->standardRow(
                    'IN-ABC-26-99999',
                    '123456789012'
                ),
            ]
        );

        $this
            ->actingAs($admin)
            ->post(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            )
            ->assertRedirect();

        $row = ReportRow::query()
            ->firstOrFail();

        $this->assertNull(
            $row->track_id
        );

        $this->assertNull(
            $row->release_id
        );

        $this->assertNull(
            $row->artist_id
        );

        $this->assertNull(
            $row->label_id
        );

        $this->assertSame(
            'IN-ABC-26-99999',
            $row->isrc
        );

        $this->assertSame(
            '123456789012',
            $row->upc
        );
    }

    public function test_duplicate_rows_are_counted_and_skipped(): void
    {
        Storage::fake('local');

        [
            ,
            ,
            ,
            ,
            ,
            $admin,
        ] = $this->createContext();

        $row = $this->standardRow();

        $file = $this->csvFile(
            $this->standardHeaders(),
            [
                $row,
                $row,
            ]
        );

        $this
            ->actingAs($admin)
            ->post(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            )
            ->assertRedirect();

        $import = ReportImport::query()
            ->firstOrFail();

        $this->assertSame(
            2,
            (int) $import->total_rows
        );

        $this->assertSame(
            1,
            (int) $import->imported_rows
        );

        $this->assertSame(
            1,
            (int) $import->duplicate_rows
        );

        $this->assertDatabaseCount(
            'report_rows',
            1
        );
    }

    public function test_column_aliases_are_detected(): void
    {
        Storage::fake('local');

        [
            ,
            ,
            ,
            ,
            $track,
            $admin,
        ] = $this->createContext();

        $headers = [
            'Artist',
            'Release Title',
            'Album Artist',
            'Label Name',
            'Title',
            'ISRC',
            'Barcode',
            'Store',
            'Client Payment Currency',
            'Territory',
            'CMS',
            'Usage Type',
            'Date',
            'Month',
            'Stream',
            'Quantity',
            'Rate',
            'Net Revenue',
        ];

        $file = $this->csvFile(
            $headers,
            [
                [
                    'Alias Artist',
                    'Alias Album',
                    'Alias Artist',
                    'Alias Label',
                    'Alias Track',
                    $track->isrc,
                    '',
                    'YouTube',
                    'USD',
                    'US',
                    'Alias CMS',
                    'Premium Stream',
                    '2026-06-20',
                    'Jun-2026',
                    '500',
                    '500',
                    '0.02',
                    '10.00',
                ],
            ]
        );

        $this
            ->actingAs($admin)
            ->post(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            )
            ->assertRedirect();

        $row = ReportRow::query()
            ->firstOrFail();

        $this->assertSame(
            'Alias Artist',
            $row->track_artist
        );

        $this->assertSame(
            'Alias Album',
            $row->album_title
        );

        $this->assertSame(
            'YouTube',
            $row->platform
        );

        $this->assertSame(
            'US',
            $row->country_code
        );

        $this->assertEquals(
            10.00,
            (float) $row->earnings
        );
    }

    public function test_sale_month_is_parsed_from_month_column(): void
    {
        Storage::fake('local');

        [
            ,
            ,
            ,
            ,
            ,
            $admin,
        ] = $this->createContext();

        $file = $this->csvFile(
            $this->standardHeaders(),
            [
                $this->standardRow(
                    saleDate: '',
                    saleMonth: 'Jul-2026'
                ),
            ]
        );

        $this
            ->actingAs($admin)
            ->post(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            )
            ->assertRedirect();

        $row = ReportRow::query()
            ->firstOrFail();

        $this->assertSame(
            '2026-07',
            (string) $row->sale_month
        );
    }

    public function test_sale_month_falls_back_to_sale_date(): void
    {
        Storage::fake('local');

        [
            ,
            ,
            ,
            ,
            ,
            $admin,
        ] = $this->createContext();

        $file = $this->csvFile(
            $this->standardHeaders(),
            [
                $this->standardRow(
                    saleDate: '2026-05-19',
                    saleMonth: ''
                ),
            ]
        );

        $this
            ->actingAs($admin)
            ->post(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            )
            ->assertRedirect();

        $row = ReportRow::query()
            ->firstOrFail();

        $this->assertSame(
            '2026-05',
            (string) $row->sale_month
        );
    }

    public function test_scientific_notation_upc_is_normalised(): void
    {
        Storage::fake('local');

        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext();

        $release->update([
            'upc' => '890000000013',
        ]);

        $file = $this->csvFile(
            $this->standardHeaders(),
            [
                $this->standardRow(
                    '',
                    '8.90000000013E+11'
                ),
            ]
        );

        $this
            ->actingAs($admin)
            ->post(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            )
            ->assertRedirect();

        $row = ReportRow::query()
            ->firstOrFail();

        $this->assertSame(
            '890000000013',
            $row->upc
        );

        $this->assertNull(
            $row->release_id
        );

        $this->assertNull(
            $row->track_id
        );

        $this->assertNull(
            $row->artist_id
        );

        $this->assertNull(
            $row->label_id
        );

        $this->assertSame(
            'unmapped',
            $row->mapping_status
        );
    }

    public function test_multiple_valid_rows_are_imported(): void
    {
        Storage::fake('local');

        [
            ,
            ,
            ,
            ,
            ,
            $admin,
        ] = $this->createContext();

        $first = $this->standardRow();

        $second = $this->standardRow(
            saleDate: '2026-07-16',
            earnings: '250.75'
        );

        $second[7] = 'YouTube';

        $file = $this->csvFile(
            $this->standardHeaders(),
            [
                $first,
                $second,
            ]
        );

        $this
            ->actingAs($admin)
            ->post(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            )
            ->assertRedirect();

        $import = ReportImport::query()
            ->firstOrFail();

        $this->assertSame(
            2,
            (int) $import->imported_rows
        );

        $this->assertDatabaseCount(
            'report_rows',
            2
        );

        $this->assertEquals(
            376.25,
            (float) ReportRow::query()
                ->sum('earnings')
        );
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        Storage::fake('local');

        [
            ,
            ,
            ,
            ,
            ,
            $admin,
        ] = $this->createContext();

        $file = UploadedFile::fake()
            ->create(
                'report.pdf',
                10,
                'application/pdf'
            );

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'report_file'
            );

        $this->assertDatabaseCount(
            'report_imports',
            0
        );
    }

    public function test_report_file_is_required(): void
    {
        [
            ,
            ,
            ,
            ,
            ,
            $admin,
        ] = $this->createContext();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->importUrl(),
                []
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'report_file'
            );

        $this->assertDatabaseCount(
            'report_imports',
            0
        );
    }

    public function test_artist_cannot_import_report(): void
    {
        Storage::fake('local');

        [
            $artistUser,
        ] = $this->createContext();

        $file = $this->csvFile(
            $this->standardHeaders(),
            [
                $this->standardRow(),
            ]
        );

        $response = $this
            ->actingAs($artistUser)
            ->post(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseCount(
            'report_imports',
            0
        );
    }

    public function test_guest_cannot_import_report(): void
    {
        Storage::fake('local');

        $file = $this->csvFile(
            $this->standardHeaders(),
            [
                $this->standardRow(),
            ]
        );

        $response = $this->post(
            $this->importUrl(),
            [
                'reporting_month' => '2026-07',

                'report_file' => $file,
            ]
        );

        $this->assertTrue(
            in_array(
                $response->getStatusCode(),
                [
                    302,
                    401,
                    403,
                ],
                true
            )
        );

        $this->assertDatabaseCount(
            'report_imports',
            0
        );
    }

    public function test_import_metadata_is_saved(): void
    {
        Storage::fake('local');

        [
            ,
            ,
            ,
            ,
            ,
            $admin,
        ] = $this->createContext();

        $file = $this->csvFile(
            $this->standardHeaders(),
            [
                $this->standardRow(),
            ],
            'july-2026-report.csv'
        );

        $this
            ->actingAs($admin)
            ->post(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            )
            ->assertRedirect();

        $import = ReportImport::query()
            ->firstOrFail();

        $this->assertSame(
            'july-2026-report.csv',
            $import->original_filename
        );

        $this->assertSame(
            $admin->id,
            $import->uploaded_by
        );

        $this->assertNotNull(
            $import->started_at
        );

        $this->assertNotNull(
            $import->completed_at
        );

        $this->assertIsArray(
            $import->column_map
        );

        $this->assertNotEmpty(
            $import->stored_path
        );
    }

    public function test_raw_csv_data_is_preserved(): void
    {
        Storage::fake('local');

        [
            ,
            ,
            ,
            ,
            ,
            $admin,
        ] = $this->createContext();

        $file = $this->csvFile(
            $this->standardHeaders(),
            [
                $this->standardRow(),
            ]
        );

        $this
            ->actingAs($admin)
            ->post(
                $this->importUrl(),
                [
                'reporting_month' => '2026-07',

                    'report_file' => $file,
                ]
            )
            ->assertRedirect();

        $row = ReportRow::query()
            ->firstOrFail();

        $this->assertIsArray(
            $row->raw_data
        );

        $this->assertSame(
            'Spotify',
            $row->raw_data['platform']
                ?? null
        );

        $this->assertSame(
            '125.50',
            $row->raw_data['earnings']
                ?? null
        );
    }
}
