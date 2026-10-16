<?php

namespace App\Services\Royalties;

use App\Models\Finance\RightsRoyaltyImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RightsRoyaltyPublishingService
{
    /*
     * MIXXTUNE_RIGHTS_PUBLISHING_V1
     *
     * Lifecycle:
     * review_required -> approved -> published
     *
     * Mapping/allocation snapshots are immutable after approval.
     * Publishing creates royalty ledgers only.
     * Balance crediting is intentionally a separate lifecycle step.
     */

    public function approve(
        RightsRoyaltyImport $import,
        ?int $userId = null
    ): RightsRoyaltyImport {
        return DB::transaction(function () use ($import, $userId) {
            $locked = RightsRoyaltyImport::query()
                ->whereKey($import->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'review_required') {
                throw new RuntimeException(
                    'Only review_required Rights royalty import can be approved.'
                );
            }

            $rowCount = DB::table('rights_royalty_rows')
                ->where(
                    'rights_royalty_import_id',
                    $locked->id
                )
                ->count();

            if ($rowCount === 0) {
                throw new RuntimeException(
                    'Rights royalty import has no source rows.'
                );
            }

            /*
             * MIXXTUNE_RIGHTS_ALLOW_SAFE_UNMAPPED_V1
             *
             * Unmapped rows are intentionally allowed to remain
             * unresolved. They must never receive beneficiary
             * allocations or be published to the royalty ledger.
             */
            $unmappedRowIds = DB::table('rights_royalty_rows')
                ->where(
                    'rights_royalty_import_id',
                    $locked->id
                )
                ->where('mapping_status', '!=', 'mapped')
                ->pluck('id');

            if ($unmappedRowIds->isNotEmpty()) {
                $unmappedAllocationCount = DB::table(
                    'rights_royalty_allocations'
                )
                    ->whereIn(
                        'rights_royalty_row_id',
                        $unmappedRowIds
                    )
                    ->count();

                if ($unmappedAllocationCount > 0) {
                    throw new RuntimeException(
                        'Unmapped Rights royalty rows must not have allocations.'
                    );
                }
            }

            $mappedRowIds = DB::table('rights_royalty_rows')
                ->where(
                    'rights_royalty_import_id',
                    $locked->id
                )
                ->where('mapping_status', 'mapped')
                ->pluck('id');

            if ($mappedRowIds->isEmpty()) {
                throw new RuntimeException(
                    'Rights royalty import has no mapped rows.'
                );
            }

            $allocationRowCount = DB::table(
                'rights_royalty_allocations'
            )
                ->whereIn(
                    'rights_royalty_row_id',
                    $mappedRowIds
                )
                ->distinct()
                ->count('rights_royalty_row_id');

            if ($allocationRowCount !== $mappedRowIds->count()) {
                throw new RuntimeException(
                    'Every mapped Rights royalty row must have an allocation.'
                );
            }

            $invalidAllocationCount = DB::table(
                'rights_royalty_allocations'
            )
                ->whereIn(
                    'rights_royalty_row_id',
                    $mappedRowIds
                )
                ->where(function ($query) {
                    $query
                        ->whereNull('beneficiary_type')
                        ->orWhereNull('beneficiary_id')
                        ->orWhereNull('source_key');
                })
                ->count();

            if ($invalidAllocationCount > 0) {
                throw new RuntimeException(
                    'Rights royalty allocation snapshot is incomplete.'
                );
            }

            /*
             * Every mapped source row must reconcile exactly to its
             * immutable beneficiary allocation snapshot.
             *
             * Direct:
             *   gross 100 -> allocation 100
             *
             * Two-tier:
             *   gross 100 -> child 80 + master 20
             *
             * Negative adjustments follow the same rule.
             */
            $reconciliationRows = DB::table(
                'rights_royalty_rows as r'
            )
                ->leftJoin(
                    'rights_royalty_allocations as a',
                    'a.rights_royalty_row_id',
                    '=',
                    'r.id'
                )
                ->where(
                    'r.rights_royalty_import_id',
                    $locked->id
                )
                ->where(
                    'r.mapping_status',
                    'mapped'
                )
                ->groupBy(
                    'r.id',
                    'r.gross_amount'
                )
                ->select([
                    'r.id',
                    'r.gross_amount',
                    DB::raw(
                        'COALESCE(SUM(a.allocated_amount), 0) as allocated_total'
                    ),
                ])
                ->get();

            foreach ($reconciliationRows as $reconciliationRow) {
                $gross = round(
                    (float) $reconciliationRow->gross_amount,
                    8
                );

                $allocated = round(
                    (float) $reconciliationRow->allocated_total,
                    8
                );

                if (abs($gross - $allocated) > 0.00000001) {
                    throw new RuntimeException(
                        'Rights royalty allocation does not reconcile for row ID '.
                        $reconciliationRow->id.'.'
                    );
                }
            }

            $locked->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $userId,
                'published_at' => null,
                'published_by' => null,
            ])->save();

            return $locked->fresh();
        });
    }

    public function publish(
        RightsRoyaltyImport $import,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($import, $userId) {
            $locked = RightsRoyaltyImport::query()
                ->whereKey($import->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'published') {
                return [
                    'import' => $locked,
                    'created' => 0,
                    'skipped' => $this->existingLedgerCount(
                        (int) $locked->id
                    ),
                ];
            }

            if ($locked->status !== 'approved') {
                throw new RuntimeException(
                    'Only approved Rights royalty import can be published.'
                );
            }

            $statementMonth = trim(
                (string) $locked->statement_month
            );

            if (!preg_match('/^\d{4}-\d{2}$/', $statementMonth)) {
                throw new RuntimeException(
                    'Rights royalty statement month is invalid.'
                );
            }

            $reportingMonth = $statementMonth . '-01';
            $saleMonth = $reportingMonth;

            $currency = strtoupper(
                trim((string) ($locked->currency ?: 'INR'))
            );

            $allocations = DB::table(
                'rights_royalty_allocations as a'
            )
                ->join(
                    'rights_royalty_rows as r',
                    'r.id',
                    '=',
                    'a.rights_royalty_row_id'
                )
                ->where(
                    'r.rights_royalty_import_id',
                    $locked->id
                )
                ->where(
                    'r.mapping_status',
                    'mapped'
                )
                ->select([
                    'a.*',
                    'r.source_row_number',
                    'r.isrc',
                    'r.track_title',
                    'r.rights_type',
                    'r.territory',
                ])
                ->orderBy('a.id')
                ->lockForUpdate()
                ->get();

            if ($allocations->isEmpty()) {
                throw new RuntimeException(
                    'Approved Rights royalty import has no allocations.'
                );
            }

            $created = 0;
            $skipped = 0;

            foreach ($allocations as $allocation) {
                $sourceKey = trim(
                    (string) $allocation->source_key
                );

                if ($sourceKey === '') {
                    throw new RuntimeException(
                        'Rights royalty allocation source key is missing.'
                    );
                }

                $exists = DB::table('royalty_ledgers')
                    ->where(
                        'source_type',
                        'rights_royalty_allocation'
                    )
                    ->where(
                        'source_key',
                        $sourceKey
                    )
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $nextSequence = DB::table('royalty_ledgers')
                    ->whereYear(
                        'reporting_month',
                        substr($statementMonth, 0, 4)
                    )
                    ->whereMonth(
                        'reporting_month',
                        substr($statementMonth, 5, 2)
                    )
                    ->lockForUpdate()
                    ->count() + 1;

                $ledgerNumber = sprintf(
                    'RL-%s-%06d',
                    str_replace('-', '', $statementMonth),
                    $nextSequence
                );

                $amount = round(
                    (float) $allocation->allocated_amount,
                    8
                );

                $gross = round(
                    (float) $allocation->gross_amount,
                    8
                );

                $share = round(
                    (float) $allocation->share_percentage,
                    4
                );

                $beneficiaryType = trim(
                    (string) $allocation->beneficiary_type
                );

                if (!in_array(
                    $beneficiaryType,
                    ['label', 'artist'],
                    true
                )) {
                    throw new RuntimeException(
                        'Unsupported Rights royalty beneficiary type.'
                    );
                }

                if (
                    $beneficiaryType === 'label' &&
                    !$allocation->label_id
                ) {
                    throw new RuntimeException(
                        'Label Rights allocation has no label_id.'
                    );
                }

                if (
                    $beneficiaryType === 'artist' &&
                    !$allocation->artist_id
                ) {
                    throw new RuntimeException(
                        'Artist Rights allocation has no artist_id.'
                    );
                }

                DB::table('royalty_ledgers')->insert([
                    'public_id' =>
                        (string) Str::ulid(),

                    'ledger_number' =>
                        $ledgerNumber,

                    'release_id' =>
                        $allocation->release_id,

                    'track_id' =>
                        $allocation->track_id,

                    'artist_id' =>
                        $allocation->artist_id,

                    'user_id' =>
                        $allocation->user_id,

                    'label_id' =>
                        $allocation->label_id,

                    'revenue_label_mapping_id' =>
                        null,

                    'source_type' =>
                        'rights_royalty_allocation',

                    'source_key' =>
                        $sourceKey,

                    'beneficiary_type' =>
                        $beneficiaryType,

                    'beneficiary_id' =>
                        $allocation->beneficiary_id,

                    'master_label_id' =>
                        $allocation->master_label_id,

                    'statement_month' =>
                        $statementMonth,

                    'reporting_month' =>
                        $reportingMonth,

                    'sale_month' =>
                        $saleMonth,

                    'store_name' =>
                        'RIGHTS ROYALTY',

                    'territory' =>
                        $allocation->territory,

                    'currency' =>
                        strtoupper(
                            trim(
                                (string) (
                                    $allocation->currency
                                    ?: $currency
                                )
                            )
                        ),

                    'gross_amount' =>
                        $gross,

                    'label_share' =>
                        $beneficiaryType === 'label'
                            ? $amount
                            : 0,

                    'artist_share' =>
                        $beneficiaryType === 'artist'
                            ? $amount
                            : 0,

                    'split_percentage' =>
                        $share,

                    'payable_amount' =>
                        $amount,

                    'streams' =>
                        0,

                    'status' =>
                        'draft',

                    'metadata' =>
                        json_encode([
                            'source' =>
                                'rights_royalty_allocation',

                            'rights_royalty_import_id' =>
                                (int) $locked->id,

                            'rights_royalty_import_public_id' =>
                                (string) $locked->public_id,

                            'rights_royalty_allocation_id' =>
                                (int) $allocation->id,

                            'rights_royalty_row_id' =>
                                (int) $allocation
                                    ->rights_royalty_row_id,

                            'source_row_number' =>
                                (int) $allocation
                                    ->source_row_number,

                            'isrc' =>
                                $allocation->isrc,

                            'track_title' =>
                                $allocation->track_title,

                            'rights_type' =>
                                $allocation->rights_type,

                            'allocation_type' =>
                                $allocation->allocation_type,

                            'revenue_share_id' =>
                                $allocation->revenue_share_id,

                            'share_effective_from' =>
                                $allocation->share_effective_from,

                            'share_effective_to' =>
                                $allocation->share_effective_to,
                        ]),

                    'created_by' =>
                        $userId,

                    'updated_by' =>
                        $userId,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

                $created++;
            }

            /*
             * Every allocation must now have exactly one canonical
             * ledger identity before the import becomes published.
             */
            $missingLedgerCount = DB::table(
                'rights_royalty_allocations as a'
            )
                ->join(
                    'rights_royalty_rows as r',
                    'r.id',
                    '=',
                    'a.rights_royalty_row_id'
                )
                ->where(
                    'r.rights_royalty_import_id',
                    $locked->id
                )
                ->whereNotExists(function ($query) {
                    $query
                        ->selectRaw('1')
                        ->from('royalty_ledgers as l')
                        ->where(
                            'l.source_type',
                            'rights_royalty_allocation'
                        )
                        ->whereColumn(
                            'l.source_key',
                            'a.source_key'
                        );
                })
                ->count();

            if ($missingLedgerCount > 0) {
                throw new RuntimeException(
                    'Rights royalty publish reconciliation failed.'
                );
            }

            $locked->forceFill([
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $userId,
            ])->save();

            return [
                'import' => $locked->fresh(),
                'created' => $created,
                'skipped' => $skipped,
            ];
        });
    }

    private function existingLedgerCount(
        int $importId
    ): int {
        return DB::table('royalty_ledgers as l')
            ->join(
                'rights_royalty_allocations as a',
                function ($join) {
                    $join
                        ->on(
                            'a.source_key',
                            '=',
                            'l.source_key'
                        )
                        ->where(
                            'l.source_type',
                            '=',
                            'rights_royalty_allocation'
                        );
                }
            )
            ->join(
                'rights_royalty_rows as r',
                'r.id',
                '=',
                'a.rights_royalty_row_id'
            )
            ->where(
                'r.rights_royalty_import_id',
                $importId
            )
            ->count();
    }
}
