<?php

namespace Tests\Feature\Reports;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Services\V2\CatalogueOwnershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogueOwnershipFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function createCatalogue(
        string $trackTitle = 'Jija Ji Demo',
        string $artistName = 'Meeta Baroda'
    ): array {
        $label = Label::factory()->create();

        $artist = Artist::factory()->create([
            'label_id' => $label->id,
            'stage_name' => $artistName,
        ]);

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'title' => 'Jija Ji Demo',
            'primary_artist_name' => $artistName,
            'upc' => null,
        ]);

        $track = Track::factory()->create([
            'release_id' => $release->id,
            'title' => $trackTitle,
            'primary_artist_name' => $artistName,
            'isrc' => null,
        ]);

        return [
            $label,
            $artist,
            $release,
            $track,
        ];
    }

    private function createReportRow(
        string $title,
        string $artist
    ): int {
        $importId = DB::table('report_imports')
            ->insertGetId([
                'public_id' => (string) \Illuminate\Support\Str::ulid(),
                'original_filename' => 'fallback-test.csv',
                'stored_path' => 'testing/fallback-test.csv',
                'status' => 'completed',
                'total_rows' => 1,
                'imported_rows' => 1,
                'duplicate_rows' => 0,
                'failed_rows' => 0,
                'uploaded_by' => null,
                'started_at' => now(),
                'completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return DB::table('report_rows')->insertGetId([
            'report_import_id' => $importId,
            'row_hash' => hash(
                'sha256',
                uniqid('fallback-', true)
            ),
            'track_title' => $title,
            'track_artist' => $artist,
            'album_title' => $title,
            'isrc' => null,
            'upc' => null,
            'platform' => 'Spotify',
            'currency' => 'INR',
            'country_code' => 'IN',
            'sale_type' => 'Stream',
            'sale_month' => '2026-07',
            'streams' => 100,
            'sale_units' => 100,
            'label_rate' => 0.10,
            'earnings' => 10.00,
            'mapping_status' => 'unmapped',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_unique_title_and_artist_without_isrc_remains_unmapped(): void
    {
        [
            $label,
            $artist,
            $release,
            $track,
        ] = $this->createCatalogue();

        $rowId = $this->createReportRow(
            '  jIJa   ji DEMO ',
            ' meeta   BARODA '
        );

        $result = app(
            CatalogueOwnershipService::class
        )->remapReports(true);

        $row = DB::table('report_rows')
            ->where('id', $rowId)
            ->first();

        $this->assertSame(0, $result['mapped']);
        $this->assertSame(1, $result['unmapped']);

        $this->assertSame(
            'unmapped',
            $row->mapping_status
        );

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

        $this->assertNull(
            $row->revenue_owner_type
        );

        $this->assertNull(
            $row->revenue_owner_id
        );
    }

    public function test_title_only_match_is_not_allowed(): void
    {
        $this->createCatalogue(
            'Jija Ji Demo',
            'Meeta Baroda'
        );

        $rowId = $this->createReportRow(
            'Jija Ji Demo',
            'Different Artist'
        );

        app(
            CatalogueOwnershipService::class
        )->remapReports(true);

        $row = DB::table('report_rows')
            ->where('id', $rowId)
            ->first();

        $this->assertSame(
            'unmapped',
            $row->mapping_status
        );

        $this->assertNull($row->track_id);
        $this->assertNull($row->release_id);
        $this->assertNull(
            $row->revenue_owner_type
        );
        $this->assertNull(
            $row->revenue_owner_id
        );
    }

    public function test_ambiguous_title_and_artist_stays_unmapped(): void
    {
        $this->createCatalogue(
            'Same Song',
            'Same Artist'
        );

        $this->createCatalogue(
            'Same Song',
            'Same Artist'
        );

        $rowId = $this->createReportRow(
            'same song',
            'same artist'
        );

        app(
            CatalogueOwnershipService::class
        )->remapReports(true);

        $row = DB::table('report_rows')
            ->where('id', $rowId)
            ->first();

        $this->assertSame(
            'unmapped',
            $row->mapping_status
        );

        $this->assertNull($row->track_id);
        $this->assertNull($row->release_id);
        $this->assertNull(
            $row->revenue_owner_type
        );
        $this->assertNull(
            $row->revenue_owner_id
        );
    }
}
