<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WordPressLegacyCatalogueImportCommand extends Command
{
    protected $signature = 'mixxtune:wordpress-legacy-catalogue
        {directory}
        {--commit : Commit catalogue migration}';

    protected $description = 'Import authoritative WordPress catalogue into Laravel';

    private const SOURCE_KEY = 'backstagemixxtune.com';

    public function handle(): int
    {
        $directory = rtrim((string) $this->argument('directory'), '/');
        $postsFile = $directory.'/business_posts.jsonl';
        $metaFile  = $directory.'/business_postmeta.jsonl';

        if (!is_file($postsFile) || !is_file($metaFile)) {
            $this->error('Required WordPress snapshot files are missing.');
            return self::FAILURE;
        }

        $releases = [];

        $fh = fopen($postsFile, 'r');

        while (($line = fgets($fh)) !== false) {
            $row = json_decode($line, true);

            if (!is_array($row) || (string) ($row['post_type'] ?? '') !== 'releases') {
                continue;
            }

            $releases[(string) $row['ID']] = $row;
        }

        fclose($fh);

        $meta = [];

        $fh = fopen($metaFile, 'r');

        while (($line = fgets($fh)) !== false) {
            $row = json_decode($line, true);

            if (!is_array($row)) {
                continue;
            }

            $postId = (string) ($row['post_id'] ?? '');

            if (!isset($releases[$postId])) {
                continue;
            }

            $meta[$postId][(string) ($row['meta_key'] ?? '')] = $row['meta_value'] ?? null;
        }

        fclose($fh);

        /*
         * WordPress user -> Laravel user maps.
         */
        $userMaps = DB::table('wordpress_legacy_id_maps')
            ->where('source_key', self::SOURCE_KEY)
            ->where('source_entity_type', 'user')
            ->where('migration_status', 'mapped')
            ->whereNotNull('target_entity_id')
            ->pluck('target_entity_id', 'source_entity_id');

        /*
         * WordPress label post -> Laravel label maps.
         */
        $labelMaps = DB::table('wordpress_legacy_id_maps')
            ->where('source_key', self::SOURCE_KEY)
            ->where('source_entity_type', 'label_post')
            ->where('migration_status', 'mapped')
            ->whereNotNull('target_entity_id')
            ->pluck('target_entity_id', 'source_entity_id');

        $existingReleaseMaps = DB::table('wordpress_legacy_id_maps')
            ->where('source_key', self::SOURCE_KEY)
            ->where('source_entity_type', 'release_post')
            ->get()
            ->keyBy(fn ($row) => (string) $row->source_entity_id);

        $stats = [
            'source_releases'          => 0,
            'single_releases'          => 0,
            'ep_releases'              => 0,
            'auto_drafts'              => 0,
            'publish'                  => 0,
            'pending'                  => 0,
            'takedown'                 => 0,
            'other_status'             => 0,
            'owner_mapped'             => 0,
            'owner_unresolved'         => 0,
            'label_mapped'             => 0,
            'label_unresolved'         => 0,
            'label_blank'              => 0,
            'missing_upc'              => 0,
            'missing_single_isrc'      => 0,
            'ep_tracks'                => 0,
            'existing_release_maps'    => $existingReleaseMaps->count(),
            'safe_candidate_releases'  => 0,
            'pending_candidate_releases' => 0,
        ];

        $unresolvedOwners = [];
        $unresolvedLabels = [];
        $otherStatuses = [];

        foreach ($releases as $wpId => $post) {
            $stats['source_releases']++;

            $m = $meta[$wpId] ?? [];

            $format = trim((string) ($m['format'] ?? ''));
            $status = trim((string) ($post['post_status'] ?? ''));
            $owner  = (string) ($post['post_author'] ?? '');
            $title  = trim((string) ($m['release_title'] ?? $post['post_title'] ?? ''));
            $upc    = trim((string) ($m['release_upc'] ?? ''));
            $label  = trim((string) ($m['label_name_'] ?? ''));

            if (strcasecmp($format, 'Single') === 0) {
                $stats['single_releases']++;
            } elseif (strcasecmp($format, 'EP') === 0) {
                $stats['ep_releases']++;
            } elseif ($status === 'auto-draft') {
                $stats['auto_drafts']++;
            }

            if ($status === 'auto-draft') {
                // already counted separately above
            } elseif (isset($stats[$status])) {
                $stats[$status]++;
            } else {
                $stats['other_status']++;
                $otherStatuses[$status] = ($otherStatuses[$status] ?? 0) + 1;
            }

            $ownerMapped = isset($userMaps[$owner]);

            if ($ownerMapped) {
                $stats['owner_mapped']++;
            } else {
                $stats['owner_unresolved']++;

                $unresolvedOwners[] = [
                    'wp_id'  => $wpId,
                    'owner'  => $owner,
                    'title'  => $title,
                    'status' => $status,
                ];
            }

            $labelMapped = false;

            if ($label === '') {
                $stats['label_blank']++;
            } elseif (isset($labelMaps[$label])) {
                $labelMapped = true;
                $stats['label_mapped']++;
            } else {
                $stats['label_unresolved']++;

                $unresolvedLabels[] = [
                    'wp_id' => $wpId,
                    'label_wp_id' => $label,
                    'title' => $title,
                    'owner' => $owner,
                ];
            }

            if ($upc === '') {
                $stats['missing_upc']++;
            }

            if (strcasecmp($format, 'Single') === 0) {
                $isrc = trim((string) ($m['single_group_isrc'] ?? ''));

                if ($isrc === '') {
                    $stats['missing_single_isrc']++;
                }
            }

            if (strcasecmp($format, 'EP') === 0) {
                for ($n = 0; $n < 100; $n++) {
                    $trackTitle = trim((string) ($m["ep_tracks_{$n}_album_title"] ?? ''));

                    if ($trackTitle !== '') {
                        $stats['ep_tracks']++;
                    }
                }
            }

            /*
             * Migration candidate policy:
             *
             * - post_author is authoritative ownership.
             * - Auto-drafts are not imported.
             * - A bad/missing legacy label reference must NOT block an
             *   otherwise authoritative release.
             * - label_id will remain NULL later unless labelMaps contains
             *   an authoritative WordPress label_post mapping.
             * - Original legacy label reference is preserved in ID-map
             *   metadata during commit.
             */
            if (
                $status !== 'auto-draft'
                && $ownerMapped
                && $title !== ''
            ) {
                $stats['safe_candidate_releases']++;
            } else {
                $stats['pending_candidate_releases']++;
            }
        }

        $this->newLine();
        $this->info('===== WORDPRESS CATALOGUE DRY RUN =====');

        foreach ($stats as $key => $value) {
            $this->line(strtoupper($key).'='.$value);
        }

        $this->newLine();
        $this->info('===== OTHER SOURCE STATUSES =====');

        if (!$otherStatuses) {
            $this->line('NONE');
        } else {
            foreach ($otherStatuses as $status => $count) {
                $this->line(($status === '' ? '(blank)' : $status).'='.$count);
            }
        }

        $this->newLine();
        $this->info('===== UNRESOLVED OWNERS =====');

        foreach (array_slice($unresolvedOwners, 0, 100) as $row) {
            $this->line(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        if (!$unresolvedOwners) {
            $this->line('NONE');
        }

        $this->newLine();
        $this->info('===== UNRESOLVED LABELS =====');

        foreach (array_slice($unresolvedLabels, 0, 100) as $row) {
            $this->line(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        if (!$unresolvedLabels) {
            $this->line('NONE');
        }

        if (!$this->option('commit')) {
            $this->newLine();
            $this->info('NO DATABASE WRITES PERFORMED');
            $this->info('WORDPRESS_CATALOGUE_DRY_RUN_COMPLETE');

            return self::SUCCESS;
        }

        /*
         * Production write safety.
         *
         * This importer is intentionally one-shot. Existing release maps
         * mean that a prior catalogue migration has already started.
         */
        if ($existingReleaseMaps->isNotEmpty()) {
            $this->error(
                'COMMIT BLOCKED: release_post ID maps already exist.'
            );

            return self::FAILURE;
        }

        if (
            DB::table('releases')->exists()
            || DB::table('tracks')->exists()
        ) {
            $this->error(
                'COMMIT BLOCKED: target catalogue is not empty.'
            );

            return self::FAILURE;
        }

        $now = now();

        $createdReleases = 0;
        $createdTracks = 0;
        $mappedReleaseMaps = 0;
        $pendingReleaseMaps = 0;
        $mappedTrackMaps = 0;

        $duplicateUpcSuppressed = 0;
        $duplicateIsrcSuppressed = 0;

        $seenUpc = [];
        $seenIsrc = [];

        $clean = static function ($value): string {
            return trim((string) ($value ?? ''));
        };

        $normCode = static function ($value) use ($clean): string {
            $value = $clean($value);

            if ($value === '') {
                return '';
            }

            return strtoupper(
                preg_replace('/[^A-Z0-9]/i', '', $value) ?? ''
            );
        };

        $dateValue = static function ($value) use ($clean): ?string {
            $value = $clean($value);

            if ($value === '') {
                return null;
            }

            try {
                return (new \DateTime($value))->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        };

        $yearValue = static function ($value) use ($clean): ?int {
            $value = $clean($value);

            if (
                preg_match('/\b(19|20)\d{2}\b/', $value, $match)
            ) {
                return (int) $match[0];
            }

            return null;
        };

        $statusMap = static function (string $source): string {
            return match ($source) {
                'publish' => 'live',
                'takedown' => 'takedown',
                'pending' => 'submitted',
                default => 'draft',
            };
        };

        DB::transaction(function () use (
            $releases,
            $meta,
            $userMaps,
            $labelMaps,
            $now,
            $clean,
            $normCode,
            $dateValue,
            $yearValue,
            $statusMap,
            &$createdReleases,
            &$createdTracks,
            &$mappedReleaseMaps,
            &$pendingReleaseMaps,
            &$mappedTrackMaps,
            &$duplicateUpcSuppressed,
            &$duplicateIsrcSuppressed,
            &$seenUpc,
            &$seenIsrc
        ) {
            foreach ($releases as $wpId => $post) {
                $m = $meta[$wpId] ?? [];

                $sourceStatus =
                    $clean($post['post_status'] ?? '');

                $ownerWpId =
                    (string) ($post['post_author'] ?? '');

                $ownerTargetId =
                    $userMaps[$ownerWpId] ?? null;

                $title =
                    $clean($m['release_title'] ?? '');

                if ($title === '') {
                    $title =
                        $clean($post['post_title'] ?? '');
                }

                /*
                 * Preserve excluded source records as pending maps.
                 */
                if (
                    $sourceStatus === 'auto-draft'
                    || !$ownerTargetId
                    || $title === ''
                ) {
                    DB::table(
                        'wordpress_legacy_id_maps'
                    )->insert([
                        'source_key' => self::SOURCE_KEY,
                        'source_entity_type' => 'release_post',
                        'source_entity_id' => (string) $wpId,
                        'target_entity_type' => 'release',
                        'target_entity_id' => null,
                        'migration_status' => 'pending',

                        'source_snapshot' => json_encode([
                            'post_author' => $ownerWpId,
                            'post_status' => $sourceStatus,
                            'post_title' =>
                                $post['post_title'] ?? null,
                        ], JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES),

                        'metadata' => json_encode([
                            'reason' =>
                                $sourceStatus === 'auto-draft'
                                    ? 'auto_draft'
                                    : (
                                        !$ownerTargetId
                                            ? 'unresolved_owner'
                                            : 'missing_title'
                                    ),
                            'legacy_label_reference' =>
                                $clean($m['label_name_'] ?? ''),
                        ], JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES),

                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $pendingReleaseMaps++;

                    continue;
                }

                $format =
                    strtolower($clean($m['format'] ?? ''));

                $releaseType = match ($format) {
                    'ep' => 'ep',
                    'album' => 'album',
                    default => 'single',
                };

                $primaryArtist =
                    $clean($m['primery'] ?? '');

                if (
                    $primaryArtist === ''
                    && $releaseType === 'single'
                ) {
                    $primaryArtist =
                        $clean(
                            $m['single_group_single_artist']
                            ?? ''
                        );
                }

                /*
                 * Readiness audit proved no eligible release is missing
                 * primary artist. Abort atomically if source changes.
                 */
                if ($primaryArtist === '') {
                    throw new \RuntimeException(
                        "Missing primary artist for WP release {$wpId}"
                    );
                }

                $legacyLabel =
                    $clean($m['label_name_'] ?? '');

                $targetLabelId =
                    $labelMaps[$legacyLabel] ?? null;

                $sourceUpc =
                    $normCode($m['release_upc'] ?? '');

                $targetUpc = null;
                $upcConflict = false;

                if ($sourceUpc !== '') {
                    if (isset($seenUpc[$sourceUpc])) {
                        $upcConflict = true;
                        $duplicateUpcSuppressed++;
                    } else {
                        $targetUpc = $sourceUpc;
                        $seenUpc[$sourceUpc] = (string) $wpId;
                    }
                }

                $releaseDate =
                    $dateValue($m['release_date'] ?? '');

                $releaseYear =
                    $yearValue(
                        $m['releaseing_year']
                        ?? $m['release_date']
                        ?? ''
                    );

                $copyrightOwner =
                    $clean(
                        $m['copyright_holder']
                        ?? $m['copyright']
                        ?? ''
                    );

                $phonographicOwner =
                    $clean(
                        $m['production_holder']
                        ?? $m['phonographic']
                        ?? ''
                    );

                $language =
                    $clean($m['language'] ?? '');

                $genre =
                    $clean($m['genre'] ?? '');

                $subGenre =
                    $clean($m['sub_genre'] ?? '');

                $targetStatus =
                    $statusMap($sourceStatus);

                $catalogNumber =
                    'WP-'.$wpId;

                $releaseId = DB::table('releases')
                    ->insertGetId([
                        'public_id' => (string) Str::ulid(),
                        'catalog_number' => $catalogNumber,

                        'artist_id' => null,
                        'label_id' => $targetLabelId,

                        'release_type' => $releaseType,
                        'title' => $title,

                        'primary_artist_name' =>
                            $primaryArtist,

                        'primary_artists' => json_encode([
                            ['name' => $primaryArtist],
                        ], JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES),

                        'featuring_artist_name' =>
                            $clean(
                                $m['featuring_artist']
                                ?? ''
                            ) ?: null,

                        'featuring_artists' =>
                            json_encode([]),

                        'language' =>
                            $language !== ''
                                ? $language
                                : null,

                        'primary_genre' =>
                            $genre !== ''
                                ? $genre
                                : null,

                        'sub_genre' =>
                            $subGenre !== ''
                                ? $subGenre
                                : null,

                        'upc' => $targetUpc,
                        'upc_is_auto_generated' => false,

                        'original_release_date' =>
                            $releaseDate,

                        'digital_release_date' =>
                            $releaseDate,

                        'copyright_owner' =>
                            $copyrightOwner !== ''
                                ? $copyrightOwner
                                : null,

                        'copyright_year' =>
                            $releaseYear,

                        'phonographic_owner' =>
                            $phonographicOwner !== ''
                                ? $phonographicOwner
                                : null,

                        'phonographic_year' =>
                            $releaseYear,

                        'status' => $targetStatus,

                        'worldwide' => true,
                        'release_timezone' =>
                            'Asia/Kolkata',

                        'pre_order' => false,
                        'wizard_step' => 5,
                        'completion_percentage' => 100,

                        'submitted_at' =>
                            in_array(
                                $targetStatus,
                                [
                                    'submitted',
                                    'live',
                                    'takedown',
                                ],
                                true
                            )
                                ? $now
                                : null,

                        'approved_at' =>
                            in_array(
                                $targetStatus,
                                ['live', 'takedown'],
                                true
                            )
                                ? $now
                                : null,

                        'live_at' =>
                            $targetStatus === 'live'
                                ? $now
                                : null,

                        'created_by' =>
                            (int) $ownerTargetId,

                        'updated_by' =>
                            (int) $ownerTargetId,

                        'created_at' =>
                            $dateValue(
                                $post['post_date'] ?? ''
                            ) ?? $now,

                        'updated_at' =>
                            $dateValue(
                                $post['post_modified'] ?? ''
                            ) ?? $now,
                    ]);

                $createdReleases++;

                DB::table(
                    'wordpress_legacy_id_maps'
                )->insert([
                    'source_key' => self::SOURCE_KEY,
                    'source_entity_type' => 'release_post',
                    'source_entity_id' => (string) $wpId,
                    'target_entity_type' => 'release',
                    'target_entity_id' => $releaseId,
                    'migration_status' => 'mapped',

                    'source_snapshot' => json_encode([
                        'post_author' => $ownerWpId,
                        'post_status' => $sourceStatus,
                        'post_title' =>
                            $post['post_title'] ?? null,
                    ], JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES),

                    'metadata' => json_encode([
                        'legacy_label_reference' =>
                            $legacyLabel ?: null,

                        'target_label_id' =>
                            $targetLabelId,

                        'source_upc' =>
                            $sourceUpc ?: null,

                        'target_upc' =>
                            $targetUpc,

                        'upc_conflict' =>
                            $upcConflict,

                        'source_format' =>
                            $format,

                        'source_status' =>
                            $sourceStatus,
                    ], JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES),

                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $mappedReleaseMaps++;

                /*
                 * Single track.
                 */
                if ($releaseType === 'single') {
                    $trackTitle =
                        $clean(
                            $m['single_group_song_title']
                            ?? $m['single_group_track_title']
                            ?? ''
                        );

                    if ($trackTitle === '') {
                        $trackTitle = $title;
                    }

                    $trackArtist =
                        $clean(
                            $m['single_group_single_artist']
                            ?? ''
                        );

                    if ($trackArtist === '') {
                        $trackArtist = $primaryArtist;
                    }

                    $sourceIsrc =
                        $normCode(
                            $m['single_group_isrc']
                            ?? ''
                        );

                    $targetIsrc = null;
                    $isrcConflict = false;

                    if ($sourceIsrc !== '') {
                        if (isset($seenIsrc[$sourceIsrc])) {
                            $isrcConflict = true;
                            $duplicateIsrcSuppressed++;
                        } else {
                            $targetIsrc = $sourceIsrc;
                            $seenIsrc[$sourceIsrc] =
                                $wpId.':0';
                        }
                    }

                    $trackId = DB::table('tracks')
                        ->insertGetId([
                            'public_id' =>
                                (string) Str::ulid(),

                            'release_id' => $releaseId,
                            'disc_number' => 1,
                            'track_number' => 1,

                            'title' => $trackTitle,
                            'track_type' => 'original',

                            'primary_artist_name' =>
                                $trackArtist,

                            'author_name' =>
                                $clean(
                                    $m[
                                        'single_group_author'
                                    ] ?? ''
                                ) ?: null,

                            'composer_name' =>
                                $clean(
                                    $m[
                                        'single_group_composer'
                                    ] ?? ''
                                ) ?: null,

                            'isrc' => $targetIsrc,
                            'isrc_is_auto_generated' =>
                                false,

                            'language' =>
                                $language !== ''
                                    ? $language
                                    : null,

                            'genre' =>
                                $genre !== ''
                                    ? $genre
                                    : null,

                            'sub_genre' =>
                                $subGenre !== ''
                                    ? $subGenre
                                    : null,

                            'audio_validation_status' =>
                                'pending',

                            'status' => $targetStatus,

                            'created_by' =>
                                (int) $ownerTargetId,

                            'updated_by' =>
                                (int) $ownerTargetId,

                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                    $createdTracks++;

                    DB::table(
                        'wordpress_legacy_id_maps'
                    )->insert([
                        'source_key' =>
                            self::SOURCE_KEY,

                        'source_entity_type' =>
                            'track',

                        'source_entity_id' =>
                            $wpId.':0',

                        'target_entity_type' =>
                            'track',

                        'target_entity_id' =>
                            $trackId,

                        'migration_status' =>
                            'mapped',

                        'source_snapshot' =>
                            json_encode([
                                'release_wp_id' =>
                                    (string) $wpId,
                                'track_index' => 0,
                                'title' => $trackTitle,
                            ], JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES),

                        'metadata' =>
                            json_encode([
                                'source_isrc' =>
                                    $sourceIsrc ?: null,
                                'target_isrc' =>
                                    $targetIsrc,
                                'isrc_conflict' =>
                                    $isrcConflict,
                            ], JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES),

                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $mappedTrackMaps++;
                }

                /*
                 * EP tracks.
                 */
                if ($releaseType === 'ep') {
                    $targetTrackNumber = 0;

                    for ($i = 0; $i < 100; $i++) {
                        $trackTitle =
                            $clean(
                                $m[
                                    "ep_tracks_{$i}_album_title"
                                ] ?? ''
                            );

                        if ($trackTitle === '') {
                            continue;
                        }

                        $targetTrackNumber++;

                        $trackArtist =
                            $clean(
                                $m[
                                    "ep_tracks_{$i}_album_artist"
                                ] ?? ''
                            );

                        if ($trackArtist === '') {
                            $trackArtist =
                                $primaryArtist;
                        }

                        $sourceIsrc =
                            $normCode(
                                $m[
                                    "ep_tracks_{$i}_isrc_"
                                ] ?? ''
                            );

                        $targetIsrc = null;
                        $isrcConflict = false;

                        if ($sourceIsrc !== '') {
                            if (
                                isset(
                                    $seenIsrc[$sourceIsrc]
                                )
                            ) {
                                $isrcConflict = true;
                                $duplicateIsrcSuppressed++;
                            } else {
                                $targetIsrc =
                                    $sourceIsrc;

                                $seenIsrc[$sourceIsrc] =
                                    $wpId.':'.$i;
                            }
                        }

                        $trackId =
                            DB::table('tracks')
                            ->insertGetId([
                                'public_id' =>
                                    (string) Str::ulid(),

                                'release_id' =>
                                    $releaseId,

                                'disc_number' => 1,

                                'track_number' =>
                                    $targetTrackNumber,

                                'title' => $trackTitle,

                                'track_type' =>
                                    'original',

                                'primary_artist_name' =>
                                    $trackArtist,

                                'author_name' =>
                                    $clean(
                                        $m[
                                            "ep_tracks_{$i}_author"
                                        ] ?? ''
                                    ) ?: null,

                                'composer_name' =>
                                    $clean(
                                        $m[
                                            "ep_tracks_{$i}_com"
                                        ] ?? ''
                                    ) ?: null,

                                'isrc' => $targetIsrc,

                                'isrc_is_auto_generated' =>
                                    false,

                                'language' =>
                                    $language !== ''
                                        ? $language
                                        : null,

                                'genre' =>
                                    $genre !== ''
                                        ? $genre
                                        : null,

                                'sub_genre' =>
                                    $subGenre !== ''
                                        ? $subGenre
                                        : null,

                                'audio_validation_status' =>
                                    'pending',

                                'status' =>
                                    $targetStatus,

                                'created_by' =>
                                    (int) $ownerTargetId,

                                'updated_by' =>
                                    (int) $ownerTargetId,

                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);

                        $createdTracks++;

                        DB::table(
                            'wordpress_legacy_id_maps'
                        )->insert([
                            'source_key' =>
                                self::SOURCE_KEY,

                            'source_entity_type' =>
                                'track',

                            'source_entity_id' =>
                                $wpId.':'.$i,

                            'target_entity_type' =>
                                'track',

                            'target_entity_id' =>
                                $trackId,

                            'migration_status' =>
                                'mapped',

                            'source_snapshot' =>
                                json_encode([
                                    'release_wp_id' =>
                                        (string) $wpId,
                                    'track_index' => $i,
                                    'title' =>
                                        $trackTitle,
                                ], JSON_UNESCAPED_UNICODE
                                    | JSON_UNESCAPED_SLASHES),

                            'metadata' =>
                                json_encode([
                                    'source_isrc' =>
                                        $sourceIsrc
                                        ?: null,

                                    'target_isrc' =>
                                        $targetIsrc,

                                    'isrc_conflict' =>
                                        $isrcConflict,
                                ], JSON_UNESCAPED_UNICODE
                                    | JSON_UNESCAPED_SLASHES),

                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        $mappedTrackMaps++;
                    }
                }
            }
        }, 3);

        $this->newLine();
        $this->info('===== CATALOGUE COMMIT RESULT =====');

        $this->line(
            'CREATED_RELEASES='.$createdReleases
        );

        $this->line(
            'CREATED_TRACKS='.$createdTracks
        );

        $this->line(
            'MAPPED_RELEASE_MAPS='.$mappedReleaseMaps
        );

        $this->line(
            'PENDING_RELEASE_MAPS='.$pendingReleaseMaps
        );

        $this->line(
            'MAPPED_TRACK_MAPS='.$mappedTrackMaps
        );

        $this->line(
            'DUPLICATE_UPC_SUPPRESSED='.
            $duplicateUpcSuppressed
        );

        $this->line(
            'DUPLICATE_ISRC_SUPPRESSED='.
            $duplicateIsrcSuppressed
        );

        $this->info(
            'WORDPRESS_CATALOGUE_COMMIT_COMPLETE'
        );

        return self::SUCCESS;
    }
}
