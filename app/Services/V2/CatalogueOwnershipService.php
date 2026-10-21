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
            /*
             * CATALOGUE LEVEL VS FINANCIAL OWNER
             * -----------------------------------
             *
             * release.label_id always identifies the
             * exact catalogue level.
             *
             * Revenue ownership rolls upward to the
             * root/master label of that hierarchy.
             *
             * Example:
             *
             * Sanatan (1)
             *   -> X
             *      -> X1
             *
             * A release stored on X1 keeps:
             * releases.label_id = X1
             *
             * Report rows keep:
             * report_rows.label_id = X1
             *
             * Financial ownership becomes:
             * revenue_owner_type = label
             * revenue_owner_id   = Sanatan
             *
             * Standalone/root labels remain their
             * own financial owner.
             */
            $rootLabelId =
                app(
                    LabelHierarchyService::class
                )->rootLabelId(
                    (int) $release->label_id
                );

            return [
                'type' => 'label',
                'id' => (int) $rootLabelId,
            ];
        }

        if (!empty($release->artist_id)) {
            return [
                'type' => 'artist',
                'id' => (int) $release->artist_id,
            ];
        }

        /*
         * MIXXTUNE_INVALID_CATALOGUE_OWNER_GUARD_V1
         *
         * A catalogue release without a valid label_id or
         * artist_id has no deterministic financial owner.
         *
         * created_by is intentionally NOT used as a financial
         * ownership fallback because one user may create
         * releases for multiple catalogue labels.
         */
        return [
            'type' => null,
            'id' => null,
        ];
    }

    public function remapReports(
        bool $onlyUnmapped = false,
        ?string $month = null,
        ?int $reportImportId = null
    ): array {
        $mapped = 0;
        $unmapped = 0;

        $query = $this->applyFinancialSnapshotLock(
                    DB::table('report_rows')
                )
            ->select([
                'id',
                'isrc',
                'upc',
                'track_title',
                'track_artist',
                'label_id',
                'artist_id',
            ])
            ->when(
                $month !== null && trim($month) !== '',
                fn ($query) => $query->where(
                    'reporting_month',
                    trim($month)
                )
            )
            ->when(
                $reportImportId !== null,
                fn ($query) => $query->where(
                    'report_import_id',
                    $reportImportId
                )
            )
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
                    /*
                     * Persistent manual ownership rules have highest
                     * priority for report rows that do not belong to
                     * the current catalogue.
                     */
                    $rule = $this->persistentRule(
                        $row->isrc
                    );

                    if ($rule) {
                        /*
                         * MIXXTUNE_PERSISTENT_RULE_OWNER_GUARD_V1
                         *
                         * A persistent rule is authoritative only while
                         * its financial owner still exists and is active.
                         *
                         * Broken/deleted owners must never convert a
                         * report row into a financially mapped row.
                         */
                        $ruleOwnerType =
                            $rule->owner_type ?? null;

                        $ruleOwnerId =
                            (int) ($rule->owner_id ?? 0);

                        $ruleOwnerValid = false;

                        if (
                            $ruleOwnerType === 'label'
                            && $ruleOwnerId > 0
                        ) {
                            $ruleOwnerValid =
                                DB::table('labels')
                                    ->where(
                                        'id',
                                        $ruleOwnerId
                                    )
                                    ->whereNull(
                                        'deleted_at'
                                    )
                                    ->exists();
                        } elseif (
                            $ruleOwnerType === 'artist'
                            && $ruleOwnerId > 0
                        ) {
                            $ruleOwnerValid =
                                DB::table('artists')
                                    ->where(
                                        'id',
                                        $ruleOwnerId
                                    )
                                    ->whereNull(
                                        'deleted_at'
                                    )
                                    ->exists();
                        }

                        if (!$ruleOwnerValid) {
                            /*
                             * Ignore the stale rule and continue with
                             * canonical catalogue ISRC resolution.
                             *
                             * We intentionally do NOT delete or modify
                             * the persistent rule here.
                             */
                            $rule = null;
                        }
                    }

                    if ($rule) {
                        $this->applyFinancialSnapshotLock(
                    DB::table('report_rows')
                )
                            ->where('id', $row->id)
                            ->update([
                                'revenue_owner_type' =>
                                    $rule->owner_type,

                                'revenue_owner_id' =>
                                    (int) $rule->owner_id,

                                'label_id' =>
                                    !empty(
                                        $rule
                                            ->catalogue_label_id
                                    )
                                        ? (int)
                                            $rule
                                                ->catalogue_label_id
                                        : $row->label_id,

                                'artist_id' =>
                                    !empty(
                                        $rule
                                            ->catalogue_label_id
                                    )
                                        ? (
                                            !empty(
                                                $rule
                                                    ->artist_id
                                            )
                                                ? (int)
                                                    $rule
                                                        ->artist_id
                                                : null
                                        )
                                        : $row->artist_id,

                                'mapping_status' =>
                                    'mapped',

                                'mapped_at' =>
                                    now(),

                                'updated_at' =>
                                    now(),
                            ]);

                        $mapped++;

                        continue;
                    }

                    /*
                     * Catalogue revenue ownership is ISRC-only.
                     * No title/artist/UPC fallback is permitted.
                     */
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

                    if (!$release) {
                        $this->applyFinancialSnapshotLock(
                    DB::table('report_rows')
                )
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

                    /*
                     * MIXXTUNE_REMAP_VALID_OWNER_REQUIRED_V1
                     *
                     * Finding a catalogue ISRC is not enough.
                     * The matched release must also have a valid,
                     * deterministic financial owner.
                     */
                    if (
                        !in_array(
                            $owner['type'] ?? null,
                            ['label', 'artist'],
                            true
                        )
                        || (int) ($owner['id'] ?? 0) <= 0
                    ) {
                        $this->applyFinancialSnapshotLock(
                            DB::table('report_rows')
                        )
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

                    $this->applyFinancialSnapshotLock(
                    DB::table('report_rows')
                )
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


    /*
     * MIXXTUNE_FINANCIAL_SNAPSHOT_LOCK
     *
     * Financial report rows may only be mutated while
     * their parent import is still review_pending.
     *
     * approved_scheduled and published imports are
     * immutable accounting snapshots.
     */
    private function applyFinancialSnapshotLock(
        $query
    )
    {
        return $query->whereExists(
            function ($financialImport) {
                $financialImport
                    ->selectRaw('1')
                    ->from(
                        'report_imports as financial_import'
                    )
                    ->whereColumn(
                        'financial_import.id',
                        'report_rows.report_import_id'
                    )
                    ->where(
                        'financial_import.financial_status',
                        'review_pending'
                    );
            }
        );
    }

    private function doTransferRelease(
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
            /* W56: only this release's rows; rows in approved / paid statements never move */
            $this->applyFinancialSnapshotLock(
                    DB::table('report_rows')
                )
                ->where(
                    'release_id',
                    $releaseId
                )
                ->whereNotExists($this->paidAllocation())
                ->update([
                    'artist_id' =>
                        $newArtistId,

                    'label_id' =>
                        $newLabelId,

                    /*
                     * Catalogue location and financial
                     * ownership are separate concerns.
                     *
                     * label_id keeps the exact level,
                     * while financial ownership resolves
                     * to the hierarchy root/master.
                     */
                    'revenue_owner_type' =>
                        $newLabelId !== null
                            ? 'label'
                            : 'artist',

                    'revenue_owner_id' =>
                        $newLabelId !== null
                            ? app(
                                LabelHierarchyService::class
                            )->rootLabelId(
                                (int) $newLabelId
                            )
                            : (int) $newArtistId,

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

    private function doTransferTrack(
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

        /* W56: the target release may already use this track number - put it at the end instead of failing */
        $disc = (int) ($track->disc_number ?? 1) ?: 1;
        $newNumber = (int) $track->track_number;
        $clash = DB::table('tracks')
            ->where('release_id', $newReleaseId)
            ->where('id', '!=', $trackId)
            ->where('disc_number', $track->disc_number)
            ->where('track_number', $track->track_number)
            ->exists();

        if ($clash) {
            $newNumber = (int) DB::table('tracks')
                ->where('release_id', $newReleaseId)
                ->where('disc_number', $track->disc_number)
                ->max('track_number') + 1;
        }

        DB::table('tracks')
            ->where('id', $trackId)
            ->update([
                'release_id' =>
                    $newReleaseId,

                'track_number' =>
                    $newNumber,

                'updated_at' =>
                    now(),
            ]);

        $owner =
            $this->resolveReleaseOwner(
                $newRelease
            );

        if ($scope === 'pending_and_future') {
            $this->applyFinancialSnapshotLock(
                    DB::table('report_rows')
                )
                ->where('track_id', $trackId)
                ->whereNotExists($this->paidAllocation())
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

                'track_number' =>
                    (int) $track->track_number,
            ],

            'new_values' => [
                'release_id' =>
                    $newReleaseId,

                'track_number' =>
                    $newNumber,

                'disc_number' =>
                    $disc,
            ],

            'transferred_by' =>
                $adminId,
        ]);
    }

    private function doTransferLabelUser(
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

    private function doTransferArtistUser(
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

    /*
     * W56 MIXXTUNE_OWNERSHIP_SAFE_V1
     *
     * Every transfer runs in one transaction with the entity row locked,
     * refuses a transfer to the current owner / to a login of the wrong
     * kind, and afterwards recalculates pending-review earnings with the
     * new owner's % (rows of approved / published reports never change).
     */
    private function paidAllocation(): \Closure
    {
        return function ($sub) {
            $sub->selectRaw('1')
                ->from('royalty_allocations as ra_lock')
                ->join('royalty_statements as rs_lock', 'rs_lock.id', '=', 'ra_lock.royalty_statement_id')
                ->whereColumn('ra_lock.report_row_id', 'report_rows.id')
                ->whereIn('rs_lock.status', ['approved', 'available', 'paid']);
        };
    }

    private function lockRow(string $table, int $id, string $field, string $what): object
    {
        $row = DB::table($table)->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();

        if (!$row) {
            throw ValidationException::withMessages([$field => $what.' not found.']);
        }

        return $row;
    }

    private function activeLogin(int $userId, string $role, string $field): object
    {
        $user = DB::table('users')->where('id', $userId)->first();

        if (!$user) {
            throw ValidationException::withMessages([$field => 'User not found.']);
        }

        if ((string) $user->role !== $role) {
            throw ValidationException::withMessages([
                $field => 'Choose a '.$role.' login. '.$user->email.' is a '.str_replace('_', ' ', (string) $user->role).' account.',
            ]);
        }

        if (($user->account_status ?? 'active') !== 'active') {
            throw ValidationException::withMessages([
                $field => $user->email.' is '.($user->account_status ?: 'not active').'. Only an active login can take over.',
            ]);
        }

        return $user;
    }

    private function recalculate(?int $labelId, ?int $artistId): ?string
    {
        try {
            $service = app(ReportEarningsRecalculationService::class);

            if ($labelId) {
                $label = \App\Models\Core\Label::query()->find($labelId);
                if ($label) {
                    $service->recalculateForLabel($label);
                }
            } elseif ($artistId) {
                $artist = \App\Models\Core\Artist::query()->find($artistId);
                if ($artist) {
                    $service->recalculateForArtist($artist);
                }
            }
        } catch (\Throwable $exception) {
            report($exception);

            return $exception->getMessage();
        }

        return null;
    }

    public function transferRelease(int $releaseId, string $ownerType, int $ownerId, string $scope, ?string $reason, int $adminId): void
    {
        DB::transaction(function () use ($releaseId, $ownerType, $ownerId, $scope, $reason, $adminId) {
            $release = $this->lockRow('releases', $releaseId, 'release_id', 'Release');

            if (
                ($ownerType === 'label' && (int) $release->label_id === $ownerId)
                || ($ownerType === 'artist' && empty($release->label_id) && (int) $release->artist_id === $ownerId)
            ) {
                throw ValidationException::withMessages(['owner_id' => 'This release already belongs to that '.$ownerType.'. Nothing to change.']);
            }

            $this->doTransferRelease($releaseId, $ownerType, $ownerId, $scope, $reason, $adminId);
        });

        if ($scope === 'pending_and_future') {
            $this->recalculate($ownerType === 'label' ? $ownerId : null, $ownerType === 'artist' ? $ownerId : null);
        }
    }

    public function transferTrack(int $trackId, int $newReleaseId, string $scope, ?string $reason, int $adminId): void
    {
        $target = null;

        DB::transaction(function () use ($trackId, $newReleaseId, $scope, $reason, $adminId, &$target) {
            $track = $this->lockRow('tracks', $trackId, 'track_id', 'Track');
            $target = $this->lockRow('releases', $newReleaseId, 'release_id', 'Target release');

            if ((int) $track->release_id === $newReleaseId) {
                throw ValidationException::withMessages(['release_id' => 'The track is already on that release.']);
            }

            $this->doTransferTrack($trackId, $newReleaseId, $scope, $reason, $adminId);
        });

        if ($scope === 'pending_and_future' && $target) {
            $this->recalculate($target->label_id ? (int) $target->label_id : null, $target->label_id ? null : (int) $target->artist_id);
        }
    }

    public function transferLabelUser(int $labelId, int $newUserId, ?string $reason, int $adminId): void
    {
        DB::transaction(function () use ($labelId, $newUserId, $reason, $adminId) {
            $label = $this->lockRow('labels', $labelId, 'label_id', 'Label');
            $this->activeLogin($newUserId, 'label', 'user_id');

            if ((int) $label->user_id === $newUserId) {
                throw ValidationException::withMessages(['user_id' => 'That login already owns this label.']);
            }

            $this->doTransferLabelUser($labelId, $newUserId, $reason, $adminId);
        });
    }

    public function transferArtistUser(int $artistId, int $newUserId, ?string $reason, int $adminId): void
    {
        DB::transaction(function () use ($artistId, $newUserId, $reason, $adminId) {
            $artist = $this->lockRow('artists', $artistId, 'artist_id', 'Artist');
            $this->activeLogin($newUserId, 'artist', 'user_id');

            if ((int) $artist->user_id === $newUserId) {
                throw ValidationException::withMessages(['user_id' => 'That login already belongs to this artist.']);
            }

            $this->doTransferArtistUser($artistId, $newUserId, $reason, $adminId);
        });
    }

    private function persistentRule(
        ?string $isrc
    ): ?object {
        $normalized = $this->normalizeIsrc($isrc);

        if (
            $normalized === ''
            || !\Illuminate\Support\Facades\Schema::hasTable(
                'revenue_mapping_rules'
            )
        ) {
            return null;
        }

        return DB::table('revenue_mapping_rules')
            ->where('identifier_type', 'isrc')
            ->where('identifier_value', $normalized)
            ->first();
    }

    private function findTrack(
        ?string $isrc
    ): ?object {
        $normalized =
            $this->normalizeIsrc($isrc);

        if ($normalized === '') {
            return null;
        }

        /*
         * FAIL-SAFE ISRC RESOLUTION
         * -------------------------
         * Revenue ownership requires exactly one active catalogue
         * track for the normalized ISRC.
         *
         * 0 matches  => unmapped
         * 1 match    => valid catalogue track
         * 2+ matches => ambiguous, therefore unmapped
         *
         * Never select an arbitrary first track for revenue ownership.
         */
        $matches = DB::table('tracks')
            ->whereNull('deleted_at')
            ->whereRaw(
                "
                REGEXP_REPLACE(
                    UPPER(TRIM(isrc)),
                    '[^A-Z0-9]',
                    ''
                ) = ?
                ",
                [$normalized]
            )
            ->limit(2)
            ->get();

        return $matches->count() === 1
            ? $matches->first()
            : null;
    }

    private function findTrackByTitleAndArtist(
        ?string $title,
        ?string $artist
    ): ?object {
        $title = $this->normalizeText(
            $title
        );

        $artist = $this->normalizeText(
            $artist
        );

        if (
            $title === ''
            || $artist === ''
        ) {
            return null;
        }

        $matches = [];

        DB::table('tracks')
            ->select([
                'id',
                'release_id',
                'title',
                'primary_artist_name',
            ])
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(
                500,
                function ($tracks) use (
                    $title,
                    $artist,
                    &$matches
                ) {
                    foreach ($tracks as $track) {
                        if (
                            $this->normalizeText(
                                $track->title
                            ) !== $title
                        ) {
                            continue;
                        }

                        if (
                            $this->normalizeText(
                                $track->primary_artist_name
                            ) !== $artist
                        ) {
                            continue;
                        }

                        $matches[] = $track;

                        /*
                         * Never auto-map an ambiguous
                         * title + artist combination.
                         */
                        if (
                            count($matches) > 1
                        ) {
                            return false;
                        }
                    }

                    return true;
                },
                'id'
            );

        if (count($matches) !== 1) {
            return null;
        }

        return $matches[0];
    }

    private function normalizeText(
        ?string $value
    ): string {
        return mb_strtolower(
            trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    (string) $value
                )
            ),
            'UTF-8'
        );
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
