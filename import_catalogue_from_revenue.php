<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$csvPath = storage_path(
    'app/private/revenue-imports/2026/07/K0hLo29TfTjzaXY8j4pkzG5a0qRiF4FUnVfv71rE.csv'
);

if (!is_file($csvPath)) {
    exit("CSV file not found: {$csvPath}\n");
}

$handle = fopen($csvPath, 'r');

if (!$handle) {
    exit("CSV file could not be opened.\n");
}

$headers = fgetcsv($handle);

if (!$headers) {
    exit("CSV headers could not be read.\n");
}

$headers = array_map(function ($header) {
    return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header));
}, $headers);

$requiredHeaders = [
    'Track title',
    'Track Artist',
    'Label',
    'ISRC',
    'UPC',
];

foreach ($requiredHeaders as $requiredHeader) {
    if (!in_array($requiredHeader, $headers, true)) {
        exit("Missing CSV column: {$requiredHeader}\n");
    }
}

$catalogue = [];
$sourceRows = 0;
$skippedRows = 0;

while (($values = fgetcsv($handle)) !== false) {
    $sourceRows++;

    if (count($values) < count($headers)) {
        $values = array_pad($values, count($headers), null);
    }

    if (count($values) > count($headers)) {
        $values = array_slice($values, 0, count($headers));
    }

    $row = array_combine($headers, $values);

    if ($row === false) {
        $skippedRows++;
        continue;
    }

    $isrc = strtoupper(
        preg_replace('/[^A-Z0-9]/', '', trim((string) ($row['ISRC'] ?? '')))
    );

    $upc = preg_replace('/[^0-9A-Za-z]/', '', trim((string) ($row['UPC'] ?? '')));
    $title = trim((string) ($row['Track title'] ?? ''));
    $artist = trim((string) ($row['Track Artist'] ?? ''));
    $label = trim((string) ($row['Label'] ?? ''));

    if ($isrc === '' || $title === '') {
        $skippedRows++;
        continue;
    }

    if ($upc === '') {
        $upc = 'ISRC-'.$isrc;
    }

    /*
     * Revenue CSV में एक ISRC की कई earning rows होती हैं।
     * इसलिए हर ISRC को केवल एक बार catalogue में रखा जाएगा।
     */
    $catalogue[$isrc] = [
        'isrc' => $isrc,
        'upc' => $upc,
        'title' => $title,
        'artist' => $artist !== '' ? $artist : 'Unknown Artist',
        'label' => $label,
    ];
}

fclose($handle);

$groupedByUpc = [];

foreach ($catalogue as $track) {
    $groupedByUpc[$track['upc']][] = $track;
}

$createdReleases = 0;
$updatedReleases = 0;
$createdTracks = 0;
$updatedTracks = 0;

DB::transaction(function () use (
    $groupedByUpc,
    &$createdReleases,
    &$updatedReleases,
    &$createdTracks,
    &$updatedTracks
) {
    foreach ($groupedByUpc as $upc => $tracks) {
        $firstTrack = $tracks[0];

        $release = DB::table('releases')
            ->where('upc', $upc)
            ->whereNull('deleted_at')
            ->first();

        $releaseData = [
            'title' => $firstTrack['title'],
            'primary_artist_name' => $firstTrack['artist'],
            'artist_id' => 1,
            'label_id' => 1,
            'release_type' => count($tracks) > 1 ? 'album' : 'single',
            'upc' => substr($upc, 0, 20),
            'upc_is_auto_generated' => 0,
            'status' => 'approved',
            'wizard_step' => 5,
            'completion_percentage' => 100,
            'worldwide' => 1,
            'release_timezone' => 'Asia/Kolkata',
            'updated_at' => now(),
        ];

        if ($release) {
            DB::table('releases')
                ->where('id', $release->id)
                ->update($releaseData);

            $releaseId = $release->id;
            $updatedReleases++;
        } else {
            $releaseId = DB::table('releases')->insertGetId(array_merge(
                $releaseData,
                [
                    'public_id' => (string) Str::ulid(),
                    'catalog_number' => substr('CSV-'.$upc, 0, 50),
                    'created_at' => now(),
                ]
            ));

            $createdReleases++;
        }

        foreach (array_values($tracks) as $index => $track) {
            $existingTrack = DB::table('tracks')
                ->where('isrc', $track['isrc'])
                ->whereNull('deleted_at')
                ->first();

            $trackData = [
                'release_id' => $releaseId,
                'disc_number' => 1,
                'track_number' => $index + 1,
                'title' => $track['title'],
                'primary_artist_name' => $track['artist'],
                'isrc' => substr($track['isrc'], 0, 20),
                'isrc_is_auto_generated' => 0,
                'status' => 'approved',
                'audio_validation_status' => 'pending',
                'updated_at' => now(),
            ];

            if ($existingTrack) {
                DB::table('tracks')
                    ->where('id', $existingTrack->id)
                    ->update($trackData);

                $updatedTracks++;
            } else {
                DB::table('tracks')->insert(array_merge(
                    $trackData,
                    [
                        'public_id' => (string) Str::ulid(),
                        'created_at' => now(),
                    ]
                ));

                $createdTracks++;
            }
        }
    }
});

$matchingRevenueRows = DB::table('revenue_rows')
    ->join('tracks', 'tracks.isrc', '=', 'revenue_rows.isrc')
    ->count('revenue_rows.id');

$matchingUniqueIsrcs = DB::table('revenue_rows')
    ->join('tracks', 'tracks.isrc', '=', 'revenue_rows.isrc')
    ->distinct()
    ->count('revenue_rows.isrc');

echo "\nCatalogue import completed successfully.\n";
echo "----------------------------------------\n";
echo "CSV earning rows read: {$sourceRows}\n";
echo "Invalid rows skipped: {$skippedRows}\n";
echo "Unique ISRCs found: ".count($catalogue)."\n";
echo "Unique UPCs found: ".count($groupedByUpc)."\n";
echo "Releases created: {$createdReleases}\n";
echo "Releases updated: {$updatedReleases}\n";
echo "Tracks created: {$createdTracks}\n";
echo "Tracks updated: {$updatedTracks}\n";
echo "Matched unique revenue ISRCs: {$matchingUniqueIsrcs}\n";
echo "Matched revenue rows: {$matchingRevenueRows}\n";
