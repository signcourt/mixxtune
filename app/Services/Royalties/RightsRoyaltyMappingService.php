<?php

namespace App\Services\Royalties;

use App\Models\Finance\RightsRoyaltyImport;
use App\Services\V2\CatalogueOwnershipService;
use Illuminate\Support\Facades\DB;

class RightsRoyaltyMappingService
{
    /*
     * MIXXTUNE_RIGHTS_ROYALTY_MAPPING_V1
     *
     * Important:
     * - ISRC is the only automatic catalogue key.
     * - No title/artist fuzzy matching.
     * - No wallet posting here.
     * - Percentage is snapshotted per row.
     */
    public function mapImport(
        RightsRoyaltyImport $import
    ): array {
        /*
         * MIXXTUNE_RIGHTS_IMMUTABLE_AFTER_APPROVAL_V1
         *
         * Approved/published Rights imports are immutable accounting
         * snapshots. Their row mappings and beneficiary allocations
         * must never be rebuilt.
         */
        if (in_array(
            (string) $import->status,
            ['approved', 'published'],
            true
        )) {
            throw new \RuntimeException(
                'Approved or published Rights royalty import cannot be remapped.'
            );
        }

        $mappedRows = 0;
        $unmappedRows = 0;

        $mappedAmount = 0.0;
        $unmappedAmount = 0.0;

        DB::transaction(
            function () use (
                $import,
                &$mappedRows,
                &$unmappedRows,
                &$mappedAmount,
                &$unmappedAmount
            ) {
                DB::table('rights_royalty_rows')
                    ->where(
                        'rights_royalty_import_id',
                        $import->id
                    )
                    ->orderBy('id')
                    ->chunkById(
                        500,
                        function ($rows) use (
                            $import,
                            &$mappedRows,
                            &$unmappedRows,
                            &$mappedAmount,
                            &$unmappedAmount
                        ) {
                            foreach ($rows as $row) {
                                $this->mapRow(
                                    $import,
                                    $row,
                                    $mappedRows,
                                    $unmappedRows,
                                    $mappedAmount,
                                    $unmappedAmount
                                );
                            }
                        }
                    );

                DB::table('rights_royalty_imports')
                    ->where('id', $import->id)
                    ->update([
                        'mapped_rows' =>
                            $mappedRows,

                        'unmapped_rows' =>
                            $unmappedRows,

                        'mapped_amount' =>
                            round(
                                $mappedAmount,
                                8
                            ),

                        'unmapped_amount' =>
                            round(
                                $unmappedAmount,
                                8
                            ),

                        'status' =>
                            'review_required',

                        'processed_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);
            }
        );

        return [
            'mapped_rows' => $mappedRows,
            'unmapped_rows' => $unmappedRows,
            'mapped_amount' =>
                round($mappedAmount, 8),
            'unmapped_amount' =>
                round($unmappedAmount, 8),
        ];
    }

    private function mapRow(
        RightsRoyaltyImport $import,
        object $row,
        int &$mappedRows,
        int &$unmappedRows,
        float &$mappedAmount,
        float &$unmappedAmount
    ): void {
        $gross = round(
            (float) $row->gross_amount,
            8
        );

        $isrc = strtoupper(
            preg_replace(
                '/[^A-Z0-9]/',
                '',
                (string) $row->isrc
            )
        );

        if ($isrc === '') {
            $this->markUnmapped(
                $row->id,
                'ISRC missing'
            );

            $unmappedRows++;
            $unmappedAmount += $gross;

            return;
        }

        /*
         * Exact normalized ISRC only.
         *
         * Financial mapping must fail closed:
         * 0 matches  = catalogue miss
         * 1 match    = deterministic mapping
         * 2+ matches = ambiguous ISRC
         */
        $tracks = DB::table('tracks')
            ->whereRaw(
                "UPPER(REPLACE(REPLACE(TRIM(isrc), '-', ''), ' ', '')) = ?",
                [$isrc]
            )
            ->limit(2)
            ->get();

        if ($tracks->isEmpty()) {
            $this->markUnmapped(
                $row->id,
                'ISRC not found in catalogue'
            );

            $unmappedRows++;
            $unmappedAmount += $gross;

            return;
        }

        if ($tracks->count() > 1) {
            $this->markUnmapped(
                $row->id,
                'Ambiguous ISRC: multiple catalogue tracks found'
            );

            $unmappedRows++;
            $unmappedAmount += $gross;

            return;
        }

        $track = $tracks->first();

        $release = DB::table('releases')
            ->where('id', $track->release_id)
            ->first();

        if (! $release) {
            $this->markUnmapped(
                $row->id,
                'Release not found'
            );

            $unmappedRows++;
            $unmappedAmount += $gross;

            return;
        }

        /*
         * Canonical financial ownership.
         *
         * release.label_id remains the exact catalogue level,
         * while CatalogueOwnershipService resolves the root/master
         * financial owner for hierarchy-aware accounting.
         */
        $owner = app(
            CatalogueOwnershipService::class
        )->resolveReleaseOwner($release);

        $ownerType = $owner['type'] ?? null;
        $ownerId = isset($owner['id'])
            ? (int) $owner['id']
            : null;

        if (
            ! in_array(
                $ownerType,
                ['label', 'artist'],
                true
            )
            || ! $ownerId
        ) {
            $this->markUnmapped(
                $row->id,
                'Financial owner not resolved'
            );

            $unmappedRows++;
            $unmappedAmount += $gross;

            return;
        }

        $label = null;
        $artist = null;

        if ($ownerType === 'label') {
            $label = DB::table('labels')
                ->where('id', $ownerId)
                ->first();

            if (! $label) {
                $this->markUnmapped(
                    $row->id,
                    'Canonical financial label not found'
                );

                $unmappedRows++;
                $unmappedAmount += $gross;

                return;
            }

            /*
             * Rights royalty commercial rate snapshot.
             *
             * Dedicated royalty rate takes precedence.
             * Revenue share remains the existing fallback.
             */
            $percentage = (float) (
                $label->royalty_share_percentage
                ?? $label->revenue_share_percentage
                ?? 100
            );

            $labelId = (int) $label->id;
            $artistId = null;
            $userId = $label->user_id ?? null;
        } else {
            $artist = DB::table('artists')
                ->where('id', $ownerId)
                ->first();

            if (! $artist) {
                $this->markUnmapped(
                    $row->id,
                    'Canonical financial artist not found'
                );

                $unmappedRows++;
                $unmappedAmount += $gross;

                return;
            }

            $percentage = (float) (
                $artist->revenue_share_percentage
                ?? 100
            );

            $labelId = null;
            $artistId = (int) $artist->id;
            $userId = $artist->user_id ?? null;
        }

        if (
            $percentage < 0
            || $percentage > 100
        ) {
            $this->markUnmapped(
                $row->id,
                'Invalid royalty percentage'
            );

            $unmappedRows++;
            $unmappedAmount += $gross;

            return;
        }

        $beneficiary = round(
            $gross * ($percentage / 100),
            8
        );

        $company = round(
            $gross - $beneficiary,
            8
        );

        DB::table('rights_royalty_rows')
            ->where('id', $row->id)
            ->update([
                'isrc' => $isrc,

                'track_id' =>
                    $track->id,

                'release_id' =>
                    $release->id,

                'label_id' =>
                    $labelId,

                'artist_id' =>
                    $artistId,

                'user_id' =>
                    $userId,

                'royalty_percentage' =>
                    $percentage,

                'beneficiary_amount' =>
                    $beneficiary,

                'company_amount' =>
                    $company,

                'mapping_status' =>
                    'mapped',

                'mapping_reason' =>
                    null,

                'updated_at' =>
                    now(),
            ]);

        $this->syncAllocations(
            $import,
            $row,
            $track,
            $release,
            $gross,
            $ownerType,
            $ownerId,
            $labelId,
            $artistId,
            $userId
        );

        $mappedRows++;
        $mappedAmount += $gross;
    }

    /**
     * Build immutable beneficiary allocations for one mapped
     * Rights source row.
     *
     * Important:
     * - No wallet / ledger posting happens here.
     * - No recursive hierarchy.
     * - Hierarchy percentage is the final allocation split.
     * - It is NOT multiplied by account commercial rates.
     */
    private function syncAllocations(
        RightsRoyaltyImport $import,
        object $row,
        object $track,
        object $release,
        float $gross,
        string $ownerType,
        int $ownerId,
        ?int $labelId,
        ?int $artistId,
        ?int $userId
    ): void {
        DB::table('rights_royalty_allocations')
            ->where('rights_royalty_row_id', $row->id)
            ->delete();

        $periodDate = $this->statementPeriodDate(
            (string) $import->statement_month
        );

        $currency = strtoupper(
            (string) ($import->currency ?: 'INR')
        );

        /*
         * Exact catalogue beneficiary is distinct from the
         * root financial owner.
         */
        $catalogueType = null;
        $catalogueId = null;

        if (! empty($release->label_id)) {
            $catalogueType = 'label';
            $catalogueId = (int) $release->label_id;
        } elseif (! empty($release->artist_id)) {
            $catalogueType = 'artist';
            $catalogueId = (int) $release->artist_id;
        }

        $share = null;

        /*
         * A hierarchy agreement is relevant only when the
         * catalogue beneficiary differs from the resolved
         * financial root owner.
         */
        if (
            $catalogueType !== null
            && $catalogueId !== null
            && ! (
                $catalogueType === $ownerType
                && $catalogueId === $ownerId
            )
        ) {
            $share = $this->activeHierarchyShare(
                $catalogueType,
                $catalogueId,
                $ownerType,
                $ownerId,
                $periodDate
            );
        }

        if ($share) {
            $childPercent = (float)
                $share->revenue_share_percent;

            if (
                $childPercent < 0
                || $childPercent > 100
            ) {
                throw new \RuntimeException(
                    'Invalid hierarchy revenue share percentage'
                );
            }

            $masterPercent = round(
                100 - $childPercent,
                4
            );

            $childAmount = round(
                $gross * ($childPercent / 100),
                8
            );

            /*
             * Difference calculation preserves exact
             * reconciliation after decimal rounding and
             * works for negative royalty rows too.
             */
            $masterAmount = round(
                $gross - $childAmount,
                8
            );

            $childAccount = $this->beneficiaryAccount(
                $catalogueType,
                $catalogueId
            );

            if (! $childAccount) {
                throw new \RuntimeException(
                    'Hierarchy beneficiary account not found'
                );
            }

            $masterAccount = $this->beneficiaryAccount(
                'label',
                (int) $share->master_label_id
            );

            if (! $masterAccount) {
                throw new \RuntimeException(
                    'Hierarchy master label not found'
                );
            }

            $this->insertAllocation([
                'rights_royalty_row_id' =>
                    $row->id,
                'release_id' =>
                    $release->id,
                'track_id' =>
                    $track->id,
                'beneficiary_type' =>
                    $catalogueType,
                'beneficiary_id' =>
                    $catalogueId,
                'user_id' =>
                    $childAccount->user_id ?? null,
                'label_id' =>
                    $catalogueType === 'label'
                        ? $catalogueId
                        : null,
                'artist_id' =>
                    $catalogueType === 'artist'
                        ? $catalogueId
                        : null,
                'master_label_id' =>
                    (int) $share->master_label_id,
                'allocation_type' =>
                    'child',
                'share_percentage' =>
                    $childPercent,
                'gross_amount' =>
                    $gross,
                'allocated_amount' =>
                    $childAmount,
                'currency' =>
                    $currency,
                'revenue_share_id' =>
                    $share->id,
                'share_effective_from' =>
                    $share->effective_from,
                'share_effective_to' =>
                    $share->effective_to,
                'source_key' =>
                    $this->allocationSourceKey(
                        (int) $row->id,
                        $catalogueType,
                        $catalogueId,
                        'child'
                    ),
                'status' =>
                    'mapped',
                'metadata' =>
                    json_encode([
                        'statement_month' =>
                            $import->statement_month,
                        'catalogue_beneficiary_type' =>
                            $catalogueType,
                        'catalogue_beneficiary_id' =>
                            $catalogueId,
                        'financial_owner_type' =>
                            $ownerType,
                        'financial_owner_id' =>
                            $ownerId,
                    ]),
            ]);

            $this->insertAllocation([
                'rights_royalty_row_id' =>
                    $row->id,
                'release_id' =>
                    $release->id,
                'track_id' =>
                    $track->id,
                'beneficiary_type' =>
                    'label',
                'beneficiary_id' =>
                    (int) $share->master_label_id,
                'user_id' =>
                    $masterAccount->user_id ?? null,
                'label_id' =>
                    (int) $share->master_label_id,
                'artist_id' =>
                    null,
                'master_label_id' =>
                    (int) $share->master_label_id,
                'allocation_type' =>
                    'master_retained',
                'share_percentage' =>
                    $masterPercent,
                'gross_amount' =>
                    $gross,
                'allocated_amount' =>
                    $masterAmount,
                'currency' =>
                    $currency,
                'revenue_share_id' =>
                    $share->id,
                'share_effective_from' =>
                    $share->effective_from,
                'share_effective_to' =>
                    $share->effective_to,
                'source_key' =>
                    $this->allocationSourceKey(
                        (int) $row->id,
                        'label',
                        (int) $share->master_label_id,
                        'master_retained'
                    ),
                'status' =>
                    'mapped',
                'metadata' =>
                    json_encode([
                        'statement_month' =>
                            $import->statement_month,
                        'catalogue_beneficiary_type' =>
                            $catalogueType,
                        'catalogue_beneficiary_id' =>
                            $catalogueId,
                        'financial_owner_type' =>
                            $ownerType,
                        'financial_owner_id' =>
                            $ownerId,
                    ]),
            ]);

            return;
        }

        /*
         * No valid direct-child hierarchy agreement:
         * preserve the canonical resolved financial owner
         * as one 100% accounting allocation.
         *
         * The legacy Rights row commercial fields remain
         * untouched for compatibility/review UI.
         */
        $this->insertAllocation([
            'rights_royalty_row_id' =>
                $row->id,
            'release_id' =>
                $release->id,
            'track_id' =>
                $track->id,
            'beneficiary_type' =>
                $ownerType,
            'beneficiary_id' =>
                $ownerId,
            'user_id' =>
                $userId,
            'label_id' =>
                $labelId,
            'artist_id' =>
                $artistId,
            'master_label_id' =>
                null,
            'allocation_type' =>
                'direct',
            'share_percentage' =>
                100,
            'gross_amount' =>
                $gross,
            'allocated_amount' =>
                $gross,
            'currency' =>
                $currency,
            'revenue_share_id' =>
                null,
            'share_effective_from' =>
                null,
            'share_effective_to' =>
                null,
            'source_key' =>
                $this->allocationSourceKey(
                    (int) $row->id,
                    $ownerType,
                    $ownerId,
                    'direct'
                ),
            'status' =>
                'mapped',
            'metadata' =>
                json_encode([
                    'statement_month' =>
                        $import->statement_month,
                    'catalogue_beneficiary_type' =>
                        $catalogueType,
                    'catalogue_beneficiary_id' =>
                        $catalogueId,
                    'financial_owner_type' =>
                        $ownerType,
                    'financial_owner_id' =>
                        $ownerId,
                ]),
        ]);
    }

    private function activeHierarchyShare(
        string $beneficiaryType,
        int $beneficiaryId,
        string $ownerType,
        int $ownerId,
        string $periodDate
    ): ?object {
        /*
         * A two-tier share always originates from a
         * root/master label.
         */
        if ($ownerType !== 'label') {
            return null;
        }

        $share = DB::table('label_revenue_shares')
            ->where(
                'master_label_id',
                $ownerId
            )
            ->where(
                'beneficiary_type',
                $beneficiaryType
            )
            ->where(
                'beneficiary_id',
                $beneficiaryId
            )
            ->where('is_active', true)
            ->where(function ($query) use ($periodDate) {
                $query
                    ->whereNull('effective_from')
                    ->orWhere(
                        'effective_from',
                        '<=',
                        $periodDate
                    );
            })
            ->where(function ($query) use ($periodDate) {
                $query
                    ->whereNull('effective_to')
                    ->orWhere(
                        'effective_to',
                        '>=',
                        $periodDate
                    );
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if (! $share) {
            return null;
        }

        return $this->isValidHierarchyShare(
            $share
        )
            ? $share
            : null;
    }

    private function isValidHierarchyShare(
        object $share
    ): bool {
        $master = DB::table('labels')
            ->where(
                'id',
                $share->master_label_id
            )
            ->whereNull('deleted_at')
            ->first([
                'id',
                'parent_label_id',
            ]);

        if (
            ! $master
            || $master->parent_label_id !== null
        ) {
            return false;
        }

        if ($share->beneficiary_type === 'label') {
            $beneficiary = DB::table('labels')
                ->where(
                    'id',
                    $share->beneficiary_id
                )
                ->whereNull('deleted_at')
                ->first([
                    'id',
                    'parent_label_id',
                ]);

            return $beneficiary
                && (int) $beneficiary->id
                    !== (int) $master->id
                && (int) $beneficiary->parent_label_id
                    === (int) $master->id;
        }

        if ($share->beneficiary_type === 'artist') {
            $artist = DB::table('artists')
                ->where(
                    'id',
                    $share->beneficiary_id
                )
                ->whereNull('deleted_at')
                ->first([
                    'id',
                    'label_id',
                ]);

            return $artist
                && $artist->label_id
                && (int) $artist->label_id
                    === (int) $master->id;
        }

        return false;
    }

    private function beneficiaryAccount(
        string $type,
        int $id
    ): ?object {
        if ($type === 'label') {
            return DB::table('labels')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->first();
        }

        if ($type === 'artist') {
            return DB::table('artists')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->first();
        }

        return null;
    }

    private function statementPeriodDate(
        string $statementMonth
    ): string {
        if (
            ! preg_match(
                '/^\d{4}-\d{2}$/',
                $statementMonth
            )
        ) {
            throw new \RuntimeException(
                'Invalid Rights statement month'
            );
        }

        return $statementMonth.'-01';
    }

    private function allocationSourceKey(
        int $rowId,
        string $beneficiaryType,
        int $beneficiaryId,
        string $allocationType
    ): string {
        return implode(':', [
            'rights',
            $rowId,
            $beneficiaryType,
            $beneficiaryId,
            $allocationType,
        ]);
    }

    private function insertAllocation(
        array $data
    ): void {
        DB::table('rights_royalty_allocations')
            ->insert(
                array_merge(
                    [
                        'public_id' =>
                            (string) \Illuminate\Support\Str::ulid(),
                        'created_at' =>
                            now(),
                        'updated_at' =>
                            now(),
                    ],
                    $data
                )
            );
    }

    private function markUnmapped(
        int $rowId,
        string $reason
    ): void {
        DB::table('rights_royalty_allocations')
            ->where('rights_royalty_row_id', $rowId)
            ->delete();

        DB::table('rights_royalty_rows')
            ->where('id', $rowId)
            ->update([
                'mapping_status' =>
                    'unmapped',

                'mapping_reason' =>
                    $reason,

                'track_id' => null,
                'release_id' => null,
                'label_id' => null,
                'artist_id' => null,
                'user_id' => null,

                'royalty_percentage' =>
                    null,

                'beneficiary_amount' =>
                    null,

                'company_amount' =>
                    null,

                'updated_at' =>
                    now(),
            ]);
    }
}
