<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WordPressLegacyEntitiesImportCommand extends Command
{
    protected $signature =
        'mixxtune:wordpress-legacy-entities
        {directory}
        {--commit : Actually create labels and artists}';

    protected $description =
        'Dry-run or import WordPress legacy labels and artists';

    public function handle(): int
    {
        $directory = rtrim((string) $this->argument('directory'), '/');
        $postsFile = $directory.'/business_posts.jsonl';
        $metaFile = $directory.'/business_postmeta.jsonl';
        $sourceKey = 'backstagemixxtune.com';
        $commit = (bool) $this->option('commit');

        if (! is_file($postsFile) || ! is_file($metaFile)) {
            $this->error('Required snapshot files missing.');
            return self::FAILURE;
        }

        $labels = [];
        $artists = [];

        $h = fopen($postsFile, 'rb');

        while (($line = fgets($h)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $type = (string) ($row['post_type'] ?? '');

            if ($type === 'label') {
                $labels[(string) $row['ID']] = $row;
            }

            if ($type === 'artist') {
                $artists[(string) $row['ID']] = $row;
            }
        }

        fclose($h);

        $wanted = array_fill_keys(
            array_merge(array_keys($labels), array_keys($artists)),
            true
        );

        $meta = [];
        $h = fopen($metaFile, 'rb');

        while (($line = fgets($h)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $postId = (string) ($row['post_id'] ?? '');

            if (! isset($wanted[$postId])) {
                continue;
            }

            $meta[$postId][(string) $row['meta_key']] =
                $row['meta_value'] ?? null;
        }

        fclose($h);

        $userMaps = DB::table('wordpress_legacy_id_maps')
            ->where('source_key', $sourceKey)
            ->where('source_entity_type', 'user')
            ->where('migration_status', 'mapped')
            ->whereNotNull('target_entity_id')
            ->pluck('target_entity_id', 'source_entity_id')
            ->mapWithKeys(
                fn ($target, $source) => [
                    (string) $source => (int) $target,
                ]
            )
            ->all();

        $existingLabelMaps = DB::table('wordpress_legacy_id_maps')
            ->where('source_key', $sourceKey)
            ->where('source_entity_type', 'label_post')
            ->count();

        $existingArtistMaps = DB::table('wordpress_legacy_id_maps')
            ->where('source_key', $sourceKey)
            ->where('source_entity_type', 'artist_post')
            ->count();

        $summary = [
            'source_labels' => count($labels),
            'source_artists' => count($artists),

            'label_publish' => 0,
            'label_draft' => 0,
            'label_auto_draft' => 0,

            'artist_publish' => 0,

            'labels_owner_mapped' => 0,
            'labels_owner_unresolved' => 0,

            'artists_owner_mapped' => 0,
            'artists_owner_unresolved' => 0,

            'existing_label_maps' => $existingLabelMaps,
            'existing_artist_maps' => $existingArtistMaps,

            'empty_label_names' => 0,
            'empty_artist_names' => 0,
        ];

        $unresolvedLabels = [];
        $unresolvedArtists = [];

        foreach ($labels as $wpId => $row) {
            $status = (string) ($row['post_status'] ?? '');

            if ($status === 'publish') {
                $summary['label_publish']++;
            } elseif ($status === 'draft') {
                $summary['label_draft']++;
            } elseif ($status === 'auto-draft') {
                $summary['label_auto_draft']++;
            }

            $name = trim(
                (string) (
                    $meta[$wpId]['label_name']
                    ?? $row['post_title']
                    ?? ''
                )
            );

            if ($name === '') {
                $summary['empty_label_names']++;
            }

            $owner = (string) ($row['post_author'] ?? '');

            if (isset($userMaps[$owner])) {
                $summary['labels_owner_mapped']++;
            } else {
                $summary['labels_owner_unresolved']++;

                $unresolvedLabels[] = [
                    'wp_post_id' => $wpId,
                    'post_author' => $owner,
                    'name' => $name,
                    'status' => $status,
                ];
            }
        }

        foreach ($artists as $wpId => $row) {
            if (($row['post_status'] ?? '') === 'publish') {
                $summary['artist_publish']++;
            }

            $name = trim(
                (string) (
                    $meta[$wpId]['artist_name']
                    ?? $row['post_title']
                    ?? ''
                )
            );

            if ($name === '') {
                $summary['empty_artist_names']++;
            }

            $owner = (string) ($row['post_author'] ?? '');

            if (isset($userMaps[$owner])) {
                $summary['artists_owner_mapped']++;
            } else {
                $summary['artists_owner_unresolved']++;

                $unresolvedArtists[] = [
                    'wp_post_id' => $wpId,
                    'post_author' => $owner,
                    'name' => $name,
                ];
            }
        }

        $this->line(json_encode(
            $summary,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ));

        echo PHP_EOL."===== UNRESOLVED LABEL OWNERS =====".PHP_EOL;

        if ($unresolvedLabels === []) {
            echo "NONE".PHP_EOL;
        } else {
            foreach ($unresolvedLabels as $row) {
                echo json_encode(
                    $row,
                    JSON_UNESCAPED_SLASHES
                ).PHP_EOL;
            }
        }

        echo PHP_EOL."===== UNRESOLVED ARTIST OWNERS =====".PHP_EOL;

        if ($unresolvedArtists === []) {
            echo "NONE".PHP_EOL;
        } else {
            foreach ($unresolvedArtists as $row) {
                echo json_encode(
                    $row,
                    JSON_UNESCAPED_SLASHES
                ).PHP_EOL;
            }
        }

        if ($commit) {
            $result = DB::transaction(function () use (
                $labels,
                $artists,
                $meta,
                $userMaps,
                $sourceKey
            ) {
                $createdLabels = 0;
                $createdArtists = 0;
                $pendingLabels = 0;
                $pendingArtists = 0;
                $alreadyMappedLabels = 0;
                $alreadyMappedArtists = 0;

                $uniqueSlug = function (
                    string $table,
                    string $name,
                    string $fallback
                ): string {
                    $base = Str::slug($name);

                    if ($base === '') {
                        $base = $fallback;
                    }

                    $slug = $base;
                    $counter = 1;

                    while (
                        DB::table($table)
                            ->where('slug', $slug)
                            ->exists()
                    ) {
                        $slug = $base.'-'.$counter;
                        $counter++;
                    }

                    return $slug;
                };

                foreach ($labels as $wpId => $row) {
                    $existing = DB::table('wordpress_legacy_id_maps')
                        ->where('source_key', $sourceKey)
                        ->where('source_entity_type', 'label_post')
                        ->where('source_entity_id', (string) $wpId)
                        ->first();

                    if ($existing) {
                        if ($existing->migration_status === 'mapped') {
                            $alreadyMappedLabels++;
                        }

                        continue;
                    }

                    $ownerWpId = (string) ($row['post_author'] ?? '');
                    $name = trim((string) (
                        $meta[$wpId]['label_name']
                        ?? $row['post_title']
                        ?? ''
                    ));

                    if (! isset($userMaps[$ownerWpId])) {
                        DB::table('wordpress_legacy_id_maps')->insert([
                            'source_key' => $sourceKey,
                            'source_entity_type' => 'label_post',
                            'source_entity_id' => (string) $wpId,
                            'target_entity_type' => 'label',
                            'target_entity_id' => null,
                            'migration_status' => 'pending',
                            'source_snapshot' => json_encode([
                                'post_author' => $ownerWpId,
                                'post_title' => $row['post_title'] ?? null,
                                'post_status' => $row['post_status'] ?? null,
                            ], JSON_UNESCAPED_SLASHES),
                            'metadata' => json_encode([
                                'reason' => 'unresolved_wordpress_owner',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $pendingLabels++;
                        continue;
                    }

                    $targetUserId = (int) $userMaps[$ownerWpId];

                    /*
                     * Preserve WP publish/draft state.
                     * Target label has no draft enum from WP, so non-published
                     * records remain inactive rather than being activated.
                     */
                    $status =
                        (($row['post_status'] ?? '') === 'publish')
                            ? 'active'
                            : 'inactive';

                    $labelId = DB::table('labels')->insertGetId([
                        'parent_label_id' => null,
                        'label_type' => 'label',
                        'public_id' => (string) Str::ulid(),
                        'user_id' => $targetUserId,
                        'name' => $name,
                        'slug' => $uniqueSlug(
                            'labels',
                            $name,
                            'label-'.$wpId
                        ),
                        'country' => 'India',
                        'timezone' => 'Asia/Kolkata',
                        'currency' => 'INR',
                        'payout_cycle' => 'monthly',
                        'minimum_withdrawal_amount' => 5000,
                        'royalty_share_percentage' => 100,
                        'parent_commission_percentage' => 0,
                        'status' => $status,
                        'can_access_catalogue' => 1,
                        'can_access_royalties' => 1,
                        'can_access_reports' => 1,
                        'can_access_wallet' => 1,
                        'can_withdraw' => 1,
                        'created_by' => $targetUserId,
                        'updated_by' => $targetUserId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('wordpress_legacy_id_maps')->insert([
                        'source_key' => $sourceKey,
                        'source_entity_type' => 'label_post',
                        'source_entity_id' => (string) $wpId,
                        'target_entity_type' => 'label',
                        'target_entity_id' => $labelId,
                        'migration_status' => 'mapped',
                        'source_snapshot' => json_encode([
                            'post_author' => $ownerWpId,
                            'post_title' => $row['post_title'] ?? null,
                            'post_status' => $row['post_status'] ?? null,
                        ], JSON_UNESCAPED_SLASHES),
                        'metadata' => json_encode([
                            'migration' => 'wordpress_core_snapshot',
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $createdLabels++;
                }

                foreach ($artists as $wpId => $row) {
                    $existing = DB::table('wordpress_legacy_id_maps')
                        ->where('source_key', $sourceKey)
                        ->where('source_entity_type', 'artist_post')
                        ->where('source_entity_id', (string) $wpId)
                        ->first();

                    if ($existing) {
                        if ($existing->migration_status === 'mapped') {
                            $alreadyMappedArtists++;
                        }

                        continue;
                    }

                    $ownerWpId = (string) ($row['post_author'] ?? '');
                    $name = trim((string) (
                        $meta[$wpId]['artist_name']
                        ?? $row['post_title']
                        ?? ''
                    ));

                    if (! isset($userMaps[$ownerWpId])) {
                        DB::table('wordpress_legacy_id_maps')->insert([
                            'source_key' => $sourceKey,
                            'source_entity_type' => 'artist_post',
                            'source_entity_id' => (string) $wpId,
                            'target_entity_type' => 'artist',
                            'target_entity_id' => null,
                            'migration_status' => 'pending',
                            'source_snapshot' => json_encode([
                                'post_author' => $ownerWpId,
                                'post_title' => $row['post_title'] ?? null,
                                'post_status' => $row['post_status'] ?? null,
                            ], JSON_UNESCAPED_SLASHES),
                            'metadata' => json_encode([
                                'reason' => 'unresolved_wordpress_owner',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $pendingArtists++;
                        continue;
                    }

                    $targetUserId = (int) $userMaps[$ownerWpId];
                    $email = trim((string) (
                        $meta[$wpId]['artist_email'] ?? ''
                    ));

                    $artistId = DB::table('artists')->insertGetId([
                        'public_id' => (string) Str::ulid(),
                        'user_id' => $targetUserId,
                        'label_id' => null,
                        'stage_name' => $name,
                        'slug' => $uniqueSlug(
                            'artists',
                            $name,
                            'artist-'.$wpId
                        ),
                        'email' => $email !== '' ? $email : null,
                        'country' => 'India',
                        'timezone' => 'Asia/Kolkata',
                        'currency' => 'INR',
                        'account_status' => 'active',
                        'kyc_status' => 'pending',
                        'can_receive_splits' => 1,
                        'can_create_releases' => 1,
                        'created_by' => $targetUserId,
                        'updated_by' => $targetUserId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('wordpress_legacy_id_maps')->insert([
                        'source_key' => $sourceKey,
                        'source_entity_type' => 'artist_post',
                        'source_entity_id' => (string) $wpId,
                        'target_entity_type' => 'artist',
                        'target_entity_id' => $artistId,
                        'migration_status' => 'mapped',
                        'source_snapshot' => json_encode([
                            'post_author' => $ownerWpId,
                            'post_title' => $row['post_title'] ?? null,
                            'post_status' => $row['post_status'] ?? null,
                        ], JSON_UNESCAPED_SLASHES),
                        'metadata' => json_encode([
                            'migration' => 'wordpress_core_snapshot',
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $createdArtists++;
                }

                return [
                    'created_labels' => $createdLabels,
                    'created_artists' => $createdArtists,
                    'pending_labels' => $pendingLabels,
                    'pending_artists' => $pendingArtists,
                    'already_mapped_labels' => $alreadyMappedLabels,
                    'already_mapped_artists' => $alreadyMappedArtists,
                ];
            });

            $this->line(
                json_encode(
                    $result,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                )
            );

            $this->info('WORDPRESS_ENTITIES_COMMIT_COMPLETE');

            return self::SUCCESS;
        }

        $this->info('WORDPRESS_ENTITIES_DRY_RUN_COMPLETE');

        return self::SUCCESS;
    }
}
