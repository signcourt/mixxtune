<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class InitializeArtistTrackSplits extends Command
{
    protected $signature = 'royalty:initialize-track-splits
        {--artist= : Artist database ID}
        {--execute : Actually create missing records}';

    protected $description =
        'Create missing contributor, track contributor links and default master splits for an artist';

    public function handle(): int
    {
        $artistId = (int) $this->option('artist');
        $execute = (bool) $this->option('execute');

        if ($artistId < 1) {
            $this->error(
                'Valid artist ID required. Example: --artist=1'
            );

            return self::FAILURE;
        }

        $artist = DB::table('artists')
            ->where('id', $artistId)
            ->whereNull('deleted_at')
            ->first();

        if (!$artist) {
            $this->error("Artist ID {$artistId} not found.");

            return self::FAILURE;
        }

        $tracks = DB::table('tracks')
            ->join(
                'releases',
                'releases.id',
                '=',
                'tracks.release_id'
            )
            ->where('releases.artist_id', $artistId)
            ->whereNull('tracks.deleted_at')
            ->whereNull('releases.deleted_at')
            ->select([
                'tracks.id',
                'tracks.title',
                'tracks.isrc',
            ])
            ->orderBy('tracks.id')
            ->get();

        $trackIds = $tracks
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $contributor = DB::table('contributors')
            ->where('artist_id', $artistId)
            ->whereNull('deleted_at')
            ->first();

        $existingLinks = $trackIds === []
            ? 0
            : DB::table('track_contributors')
                ->whereIn('track_id', $trackIds)
                ->count();

        $existingSplits = $trackIds === []
            ? 0
            : DB::table('track_splits')
                ->whereIn('track_id', $trackIds)
                ->count();

        $missingLinks = count($trackIds) - $existingLinks;

        $tracksWithAnySplit = $trackIds === []
            ? 0
            : DB::table('track_splits')
                ->whereIn('track_id', $trackIds)
                ->distinct()
                ->count('track_id');

        $missingSplits = count($trackIds) - $tracksWithAnySplit;

        $this->newLine();

        $this->table(
            ['Item', 'Value'],
            [
                ['Mode', $execute ? 'EXECUTE' : 'DRY RUN'],
                ['Artist ID', $artist->id],
                ['Artist', $artist->stage_name],
                ['User ID', $artist->user_id ?? 'NULL'],
                ['Tracks found', count($trackIds)],
                [
                    'Existing contributor',
                    $contributor->id ?? 'NONE',
                ],
                ['Existing track links', $existingLinks],
                ['Missing track links', max(0, $missingLinks)],
                ['Tracks with splits', $tracksWithAnySplit],
                ['Missing track splits', max(0, $missingSplits)],
            ]
        );

        if (!$execute) {
            $this->newLine();
            $this->warn(
                'Dry run only. Database में कोई बदलाव नहीं हुआ।'
            );

            $this->line(
                'Execute करने के लिए:'
            );

            $this->line(
                "php artisan royalty:initialize-track-splits --artist={$artistId} --execute"
            );

            return self::SUCCESS;
        }

        if ($trackIds === []) {
            $this->warn('इस artist के कोई tracks नहीं मिले।');

            return self::SUCCESS;
        }

        try {
            $result = DB::transaction(function () use (
                $artist,
                $artistId,
                $tracks,
                $contributor
            ) {
                if (!$contributor) {
                    $contributorId = DB::table(
                        'contributors'
                    )->insertGetId([
                        'public_id' => (string) Str::ulid(),
                        'user_id' => $artist->user_id,
                        'artist_id' => $artistId,
                        'name' => $artist->stage_name,
                        'legal_name' => $artist->legal_name,
                        'email' => $artist->email,
                        'phone' => $artist->phone,
                        'country' => $artist->country,
                        'ipi_number' => null,
                        'isni' => null,
                        'primary_role' => 'artist',
                        'can_receive_splits' => 1,
                        'has_dashboard_access' => 1,
                        'status' => 'active',
                        'created_by' => 1,
                        'updated_by' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $contributor = DB::table('contributors')
                        ->where('id', $contributorId)
                        ->first();
                }

                if (!$contributor) {
                    throw new RuntimeException(
                        'Contributor create नहीं हो पाया।'
                    );
                }

                $linksCreated = 0;
                $linksSkipped = 0;
                $splitsCreated = 0;
                $splitsSkipped = 0;

                foreach ($tracks as $track) {
                    $linkExists = DB::table(
                        'track_contributors'
                    )
                        ->where('track_id', $track->id)
                        ->where(
                            'contributor_id',
                            $contributor->id
                        )
                        ->exists();

                    if (!$linkExists) {
                        DB::table(
                            'track_contributors'
                        )->insert([
                            'public_id' => (string) Str::ulid(),
                            'track_id' => $track->id,
                            'contributor_id' =>
                                $contributor->id,
                            'role' => 'primary_artist',
                            'credited_name' =>
                                $artist->stage_name,
                            'is_primary' => 1,
                            'is_featured' => 0,
                            'display_order' => 1,
                            'metadata' => json_encode([
                                'source' =>
                                    'default_artist_split',
                            ]),
                            'created_by' => 1,
                            'updated_by' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $linksCreated++;
                    } else {
                        $linksSkipped++;
                    }

                    $anySplitExists = DB::table(
                        'track_splits'
                    )
                        ->where('track_id', $track->id)
                        ->exists();

                    if (!$anySplitExists) {
                        DB::table('track_splits')->insert([
                            'public_id' => (string) Str::ulid(),
                            'track_id' => $track->id,
                            'contributor_id' =>
                                $contributor->id,
                            'split_type' => 'master',
                            'percentage' => 100,
                            'is_recoupable' => 0,
                            'effective_from' => null,
                            'effective_to' => null,
                            'status' => 'active',
                            'created_by' => 1,
                            'updated_by' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $splitsCreated++;
                    } else {
                        $splitsSkipped++;
                    }
                }

                return [
                    'contributor_id' => $contributor->id,
                    'links_created' => $linksCreated,
                    'links_skipped' => $linksSkipped,
                    'splits_created' => $splitsCreated,
                    'splits_skipped' => $splitsSkipped,
                ];
            });
        } catch (Throwable $exception) {
            report($exception);

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Initialization complete.');

        $this->table(
            ['Result', 'Count'],
            [
                ['Contributor ID', $result['contributor_id']],
                ['Links created', $result['links_created']],
                ['Links skipped', $result['links_skipped']],
                ['Splits created', $result['splits_created']],
                ['Splits skipped', $result['splits_skipped']],
            ]
        );

        return self::SUCCESS;
    }
}
