<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogueOwnershipService
{
    public function resolveReleaseOwner(
        object $release
    ): array {
        if (!empty($release->label_id)) {
            return [
                'type' => 'label',
                'id' => (int) $release->label_id,
            ];
        }

        return [
            'type' => 'artist',
            'id' => (int) $release->artist_id,
        ];
    }

    public function remapReports(
        bool $onlyUnmapped = false
    ): array {
        $mapped = 0;
        $unmapped = 0;

        $query = DB::table('report_rows')
            ->select([
                'id',
                'isrc',
                'upc',
            ])
            ->orderBy('id');

        if ($onlyUnmapped) {
            $query->where(function ($builder) {
                $builder
                    ->whereNull('mapping_status')
                    ->orWhere(
                        'mapping_status',
                        'unmapped'
                    );
            });
        }

        $query->chunkById(
            500,
            function ($rows) use (
                &$mapped,
                &$unmapped
            ) {
                foreach ($rows as $row) {
                    $track = $this->findTrack(
                        $row->isrc
                    );

                    $release = null;

                    if ($track) {
                        $release = DB::table('releases')
                            ->where(
                                'id',
                                $track->release_id
                            )
                            ->whereNull('deleted_at')
                            ->first();
                    }

                    if (
                        !$release
                        && !empty($row->upc)
                    ) {
                        $release = DB::table('releases')
                            ->where(
                                'upc',
                                $this->cleanCode(
                                    $row->upc
                                )
                            )
                            ->whereNull('deleted_at')
                            ->first();
                    }

                    if (!$release) {
                        DB::table('report_rows')
                            ->where('id', $row->id)
                            ->update([
                                'mapping_status' =>
                                    'unmapped',

                                'release_id' =>
                                    null,

                                'track_id' =>
                                    null,

                                'artist_id' =>
                                    null,

                                'label_id' =>
                                    null,

                                'revenue_owner_type' =>
                                    null,

                                'revenue_owner_id' =>
                                    null,

                                'mapped_at' =>
                                    null,

                                'updated_at' =>
                                    now(),
                            ]);

                        $unmapped++;

                        continue;
                    }

                    $owner =
                        $this->resolveReleaseOwner(
                            $release
                        );

                    DB::table('report_rows')
                        ->where('id', $row->id)
                        ->update([
                            'release_id' =>
                                $release->id,

                            'track_id' =>
                                $track?->id,

                            // Performer metadata
                            'artist_id' =>
                                $release->artist_id,

                            // Catalogue label
                            'label_id' =>
                                $release->label_id,

                            // Financial ownership
                            'revenue_owner_type' =>
                                $owner['type'],

                            'revenue_owner_id' =>
                                $owner['id'],

                            'mapping_status' =>
                                'mapped',

                            'mapped_at' =>
                                now(),

                            'updated_at' =>
                                now(),
                        ]);

                    $mapped++;
                }
            }
        );

        return [
            'mapped' => $mapped,
            'unmapped' => $unmapped,
        ];
    }

    public function transferRelease(
        int $releaseId,
        string $ownerType,
        int $ownerId,
        string $scope,
        ?string $reason,
        int $adminId
    ): void {
        $release = DB::table('releases')
            ->where('id', $releaseId)
            ->whereNull('deleted_at')
            ->first();

        if (!$release) {
            throw ValidationException::withMessages([
                'release' =>
                    'Release not found.',
            ]);
        }

        $oldOwner =
            $this->resolveReleaseOwner(
                $release
            );

        $oldValues = [
            'artist_id' =>
                $release->artist_id,

            'label_id' =>
                $release->label_id,

            'owner_type' =>
                $oldOwner['type'],

            'owner_id' =>
                $oldOwner['id'],
        ];

        if ($ownerType === 'label') {
            $label = DB::table('labels')
                ->where('id', $ownerId)
                ->whereNull('deleted_at')
                ->first();

            if (!$label) {
                throw ValidationException::withMessages([
                    'owner_id' =>
                        'Target label not found.',
                ]);
            }

            DB::table('releases')
                ->where('id', $releaseId)
                ->update([
                    // Performer remains unchanged
                    'label_id' =>
                        $label->id,

                    'updated_by' =>
                        $adminId,

                    'updated_at' =>
                        now(),
                ]);

            $newArtistId =
                (int) $release->artist_id;

            $newLabelId =
                (int) $label->id;
        } elseif ($ownerType === 'artist') {
            $artist = DB::table('artists')
                ->where('id', $ownerId)
                ->whereNull('deleted_at')
                ->first();

            if (!$artist) {
                throw ValidationException::withMessages([
                    'owner_id' =>
                        'Target artist not found.',
                ]);
            }

            DB::table('releases')
                ->where('id', $releaseId)
                ->update([
                    'artist_id' =>
                        $artist->id,

                    // Direct artist ownership
                    'label_id' =>
                        null,

                    'primary_artist_name' =>
                        $artist->stage_name
                        ?: $artist->legal_name,

                    'updated_by' =>
                        $adminId,

                    'updated_at' =>
                        now(),
                ]);

            $newArtistId =
                (int) $artist->id;

            $newLabelId = null;
        } else {
            throw ValidationException::withMessages([
                'owner_type' =>
                    'Owner must be artist or label.',
            ]);
        }

        if ($scope === 'pending_and_future') {
            $protectedRowIds = DB::table(
                'royalty_allocations as ra'
            )
                ->join(
                    'royalty_statements as rs',
                    'rs.id',
                    '=',
                    'ra.royalty_statement_id'
                )
                ->whereIn(
                    'rs.status',
                    [
                        'approved',
                        'available',
                        'paid',
                    ]
                )
                ->pluck('ra.report_row_id');

            DB::table('report_rows')
                ->where(
                    'release_id',
                    $releaseId
                )
                ->when(
                    $protectedRowIds->isNotEmpty(),
                    fn ($query) =>
                        $query->whereNotIn(
                            'id',
                            $protectedRowIds
                        )
                )
                ->update([
                    'artist_id' =>
                        $newArtistId,

                    'label_id' =>
                        $newLabelId,

                    'revenue_owner_type' =>
                        $ownerType,

                    'revenue_owner_id' =>
                        $ownerId,

                    'mapping_status' =>
                        'mapped',

                    'mapped_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
        }

        $this->audit([
            'transfer_type' =>
                'release_owner_transfer',

            'entity_type' =>
                'release',

            'entity_id' =>
                $releaseId,

            'from_owner_type' =>
                $oldOwner['type'],

            'from_owner_id' =>
                $oldOwner['id'],

            'to_owner_type' =>
                $ownerType,

            'to_owner_id' =>
                $ownerId,

            'revenue_scope' =>
                $scope,

            'reason' =>
                $reason,

            'old_values' =>
                $oldValues,

            'new_values' => [
                'artist_id' =>
                    $newArtistId,

                'label_id' =>
                    $newLabelId,

                'owner_type' =>
                    $ownerType,

                'owner_id' =>
                    $ownerId,
            ],

            'transferred_by' =>
                $adminId,
        ]);
    }

    public function transferTrack(
        int $trackId,
        int $newReleaseId,
        string $scope,
        ?string $reason,
        int $adminId
    ): void {
        $track = DB::table('tracks')
            ->where('id', $trackId)
            ->whereNull('deleted_at')
            ->first();

        $newRelease = DB::table('releases')
            ->where('id', $newReleaseId)
            ->whereNull('deleted_at')
            ->first();

        if (!$track || !$newRelease) {
            throw ValidationException::withMessages([
                'track' =>
                    'Track or target release not found.',
            ]);
        }

        $oldReleaseId =
            (int) $track->release_id;

        DB::table('tracks')
            ->where('id', $trackId)
            ->update([
                'release_id' =>
                    $newReleaseId,

                'updated_at' =>
                    now(),
            ]);

        $owner =
            $this->resolveReleaseOwner(
                $newRelease
            );

        if ($scope === 'pending_and_future') {
            DB::table('report_rows')
                ->where('track_id', $trackId)
                ->whereNotIn(
                    'id',
                    DB::table(
                        'royalty_allocations as ra'
                    )
                        ->join(
                            'royalty_statements as rs',
                            'rs.id',
                            '=',
                            'ra.royalty_statement_id'
                        )
                        ->whereIn(
                            'rs.status',
                            [
                                'approved',
                                'available',
                                'paid',
                            ]
                        )
                        ->pluck(
                            'ra.report_row_id'
                        )
                )
                ->update([
                    'release_id' =>
                        $newReleaseId,

                    'artist_id' =>
                        $newRelease->artist_id,

                    'label_id' =>
                        $newRelease->label_id,

                    'revenue_owner_type' =>
                        $owner['type'],

                    'revenue_owner_id' =>
                        $owner['id'],

                    'mapped_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
        }

        $this->audit([
            'transfer_type' =>
                'track_release_transfer',

            'entity_type' =>
                'track',

            'entity_id' =>
                $trackId,

            'from_owner_type' =>
                'release',

            'from_owner_id' =>
                $oldReleaseId,

            'to_owner_type' =>
                'release',

            'to_owner_id' =>
                $newReleaseId,

            'revenue_scope' =>
                $scope,

            'reason' =>
                $reason,

            'old_values' => [
                'release_id' =>
                    $oldReleaseId,
            ],

            'new_values' => [
                'release_id' =>
                    $newReleaseId,
            ],

            'transferred_by' =>
                $adminId,
        ]);
    }

    public function transferLabelUser(
        int $labelId,
        int $newUserId,
        ?string $reason,
        int $adminId
    ): void {
        $label = DB::table('labels')
            ->where('id', $labelId)
            ->whereNull('deleted_at')
            ->first();

        $user = DB::table('users')
            ->where('id', $newUserId)
            ->first();

        if (!$label || !$user) {
            throw ValidationException::withMessages([
                'user_id' =>
                    'Label or user not found.',
            ]);
        }

        $oldUserId =
            $label->user_id;

        DB::table('labels')
            ->where('id', $labelId)
            ->update([
                'user_id' =>
                    $newUserId,

                'updated_by' =>
                    $adminId,

                'updated_at' =>
                    now(),
            ]);

        $this->audit([
            'transfer_type' =>
                'label_user_transfer',

            'entity_type' =>
                'label',

            'entity_id' =>
                $labelId,

            'from_user_id' =>
                $oldUserId,

            'to_user_id' =>
                $newUserId,

            'revenue_scope' =>
                'access_only',

            'reason' =>
                $reason,

            'old_values' => [
                'user_id' =>
                    $oldUserId,
            ],

            'new_values' => [
                'user_id' =>
                    $newUserId,
            ],

            'transferred_by' =>
                $adminId,
        ]);
    }

    public function transferArtistUser(
        int $artistId,
        int $newUserId,
        ?string $reason,
        int $adminId
    ): void {
        $artist = DB::table('artists')
            ->where('id', $artistId)
            ->whereNull('deleted_at')
            ->first();

        $user = DB::table('users')
            ->where('id', $newUserId)
            ->first();

        if (!$artist || !$user) {
            throw ValidationException::withMessages([
                'user_id' =>
                    'Artist or user not found.',
            ]);
        }

        $oldUserId =
            $artist->user_id;

        DB::table('artists')
            ->where('id', $artistId)
            ->update([
                'user_id' =>
                    $newUserId,

                'updated_by' =>
                    $adminId,

                'updated_at' =>
                    now(),
            ]);

        $this->audit([
            'transfer_type' =>
                'artist_user_transfer',

            'entity_type' =>
                'artist',

            'entity_id' =>
                $artistId,

            'from_user_id' =>
                $oldUserId,

            'to_user_id' =>
                $newUserId,

            'revenue_scope' =>
                'access_only',

            'reason' =>
                $reason,

            'old_values' => [
                'user_id' =>
                    $oldUserId,
            ],

            'new_values' => [
                'user_id' =>
                    $newUserId,
            ],

            'transferred_by' =>
                $adminId,
        ]);
    }

    private function findTrack(
        ?string $isrc
    ): ?object {
        $normalized =
            $this->normalizeIsrc($isrc);

        if ($normalized === '') {
            return null;
        }

        return DB::table('tracks')
            ->whereNull('deleted_at')
            ->whereRaw(
                "
                REPLACE(
                    REPLACE(
                        UPPER(TRIM(isrc)),
                        '-',
                        ''
                    ),
                    ' ',
                    ''
                ) = ?
                ",
                [$normalized]
            )
            ->first();
    }

    private function normalizeIsrc(
        ?string $isrc
    ): string {
        return strtoupper(
            preg_replace(
                '/[^A-Z0-9]/i',
                '',
                trim((string) $isrc)
            )
        );
    }

    private function cleanCode(
        ?string $value
    ): string {
        return trim(
            preg_replace(
                '/\.0+$/',
                '',
                (string) $value
            )
        );
    }

    private function audit(
        array $data
    ): void {
        DB::table('catalogue_transfers')
            ->insert([
                'public_id' =>
                    (string) Str::ulid(),

                'transfer_type' =>
                    $data['transfer_type'],

                'entity_type' =>
                    $data['entity_type'],

                'entity_id' =>
                    $data['entity_id'],

                'from_owner_type' =>
                    $data[
                        'from_owner_type'
                    ] ?? null,

                'from_owner_id' =>
                    $data[
                        'from_owner_id'
                    ] ?? null,

                'to_owner_type' =>
                    $data[
                        'to_owner_type'
                    ] ?? null,

                'to_owner_id' =>
                    $data[
                        'to_owner_id'
                    ] ?? null,

                'from_user_id' =>
                    $data[
                        'from_user_id'
                    ] ?? null,

                'to_user_id' =>
                    $data[
                        'to_user_id'
                    ] ?? null,

                'revenue_scope' =>
                    $data[
                        'revenue_scope'
                    ] ?? 'future_only',

                'reason' =>
                    $data['reason'] ?? null,

                'old_values' =>
                    json_encode(
                        $data[
                            'old_values'
                        ] ?? []
                    ),

                'new_values' =>
                    json_encode(
                        $data[
                            'new_values'
                        ] ?? []
                    ),

                'transferred_by' =>
                    $data[
                        'transferred_by'
                    ] ?? null,

                'transferred_at' =>
                    now(),

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
    }
}
