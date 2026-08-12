<?php

namespace App\Services\V2;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OwnershipRoyaltyService
{
    private array $shareCache = [];

    public function generateMonthlyStatements(
        string $month,
        float $commissionPercent = 0,
        string $currency = 'INR'
    ): array {
        $owners = $this->statementOwners(
            $month
        );

        $created = 0;
        $updated = 0;
        $failed = [];

        foreach ($owners as $owner) {
            try {
                $existing = DB::table(
                    'royalty_statements'
                )
                    ->where(
                        'statement_month',
                        $month
                    )
                    ->where(
                        'currency',
                        strtoupper($currency)
                    )
                    ->when(
                        $owner['type'] === 'label',
                        fn ($query) =>
                            $query
                                ->where(
                                    'label_id',
                                    $owner['id']
                                )
                                ->whereNull(
                                    'artist_id'
                                ),
                        fn ($query) =>
                            $query
                                ->where(
                                    'artist_id',
                                    $owner['id']
                                )
                                ->whereNull(
                                    'label_id'
                                )
                    )
                    ->first();

                $this->generateOwnerStatement(
                    $month,
                    $owner['type'],
                    $owner['id'],
                    $commissionPercent,
                    $currency,
                    $existing
                );

                $existing
                    ? $updated++
                    : $created++;
            } catch (\Throwable $exception) {
                $failed[] = [
                    'owner_type' =>
                        $owner['type'],

                    'owner_id' =>
                        $owner['id'],

                    'message' =>
                        $exception
                            ->getMessage(),
                ];
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,

            'failed_count' =>
                count($failed),

            'failed' => $failed,
        ];
    }

    private function statementOwners(
        string $month
    ): array {
        $owners = [];
        $seen = [];

        $sourceOwners = DB::table(
            'report_rows'
        )
            ->where(
                'sale_month',
                $month
            )
            ->where(
                'mapping_status',
                'mapped'
            )
            ->whereNotNull(
                'revenue_owner_type'
            )
            ->whereNotNull(
                'revenue_owner_id'
            )
            ->select([
                'revenue_owner_type',
                'revenue_owner_id',
            ])
            ->distinct()
            ->get();

        foreach ($sourceOwners as $owner) {
            $this->addOwner(
                $owners,
                $seen,
                (string)
                    $owner
                        ->revenue_owner_type,
                (int)
                    $owner
                        ->revenue_owner_id
            );

            /*
             * Revenue-share agreements can begin or end
             * during a month. Discover the master owner
             * from the actual sale dates represented in
             * this month's report rows.
             */
            $saleDates = DB::table('report_rows')
                ->where('sale_month', $month)
                ->where('mapping_status', 'mapped')
                ->where(
                    'revenue_owner_type',
                    (string)
                        $owner
                            ->revenue_owner_type
                )
                ->where(
                    'revenue_owner_id',
                    (int)
                        $owner
                            ->revenue_owner_id
                )
                ->whereNotNull('sale_date')
                ->select('sale_date')
                ->distinct()
                ->pluck('sale_date');

            foreach ($saleDates as $saleDate) {
                $share =
                    $this->activeShareForBeneficiary(
                        (string)
                            $owner
                                ->revenue_owner_type,
                        (int)
                            $owner
                                ->revenue_owner_id,
                        (string) $saleDate
                    );

                if (!$share) {
                    continue;
                }

                $this->addOwner(
                    $owners,
                    $seen,
                    'label',
                    (int)
                        $share
                            ->master_label_id
                );
            }
        }

        return $owners;
    }

    private function addOwner(
        array &$owners,
        array &$seen,
        string $type,
        int $id
    ): void {
        $key = $type.':'.$id;

        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;

        $owners[] = [
            'type' => $type,
            'id' => $id,
        ];
    }

    private function generateOwnerStatement(
        string $month,
        string $ownerType,
        int $ownerId,
        float $commissionPercent,
        string $currency,
        ?object $existing
    ): void {
        DB::transaction(function () use (
            $month,
            $ownerType,
            $ownerId,
            $commissionPercent,
            $currency,
            $existing
        ) {
            $allocations =
                $this->buildAllocations(
                    $month,
                    $ownerType,
                    $ownerId,
                    $commissionPercent
                );

            $gross = round(
                array_sum(
                    array_column(
                        $allocations,
                        'gross_amount'
                    )
                ),
                8
            );

            $net = round(
                array_sum(
                    array_column(
                        $allocations,
                        'net_amount'
                    )
                ),
                8
            );

            /*
             * Calculate commission as the exact
             * difference so statement arithmetic
             * always reconciles after row rounding.
             */
            $commission = round(
                $gross - $net,
                8
            );

            $statementData = [
                'public_id' =>
                    $existing?->public_id
                    ?: (string) Str::ulid(),

                'artist_id' =>
                    $ownerType === 'artist'
                        ? $ownerId
                        : null,

                'label_id' =>
                    $ownerType === 'label'
                        ? $ownerId
                        : null,

                'statement_month' =>
                    $month,

                'currency' =>
                    strtoupper(
                        $currency
                    ),

                'gross_earnings' =>
                    $gross,

                'commission_amount' =>
                    $commission,

                'tax_amount' => 0,

                'other_deductions' => 0,

                'net_payable' =>
                    $net,

                'status' =>
                    'pending',

                'updated_at' =>
                    now(),
            ];

            if ($existing) {
                DB::table(
                    'royalty_statements'
                )
                    ->where(
                        'id',
                        $existing->id
                    )
                    ->update(
                        $statementData
                    );

                $statementId =
                    $existing->id;
            } else {
                $statementData[
                    'created_at'
                ] = now();

                $statementId =
                    DB::table(
                        'royalty_statements'
                    )
                        ->insertGetId(
                            $statementData
                        );
            }

            /*
             * Regeneration is idempotent.
             */
            DB::table(
                'royalty_allocations'
            )
                ->where(
                    'royalty_statement_id',
                    $statementId
                )
                ->delete();

            if ($allocations === []) {
                return;
            }

            $payload = [];

            foreach ($allocations as $allocation) {
                $payload[] = [
                    'public_id' =>
                        (string)
                            Str::ulid(),

                    'royalty_statement_id' =>
                        $statementId,

                    'report_row_id' =>
                        $allocation[
                            'report_row_id'
                        ],

                    'release_id' =>
                        $allocation[
                            'release_id'
                        ],

                    'track_id' =>
                        $allocation[
                            'track_id'
                        ],

                    'gross_amount' =>
                        $allocation[
                            'gross_amount'
                        ],

                    'net_amount' =>
                        $allocation[
                            'net_amount'
                        ],

                    'share_percentage' =>
                        $allocation[
                            'share_percentage'
                        ],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ];
            }

            foreach (
                array_chunk(
                    $payload,
                    1000
                )
                as $chunk
            ) {
                DB::table(
                    'royalty_allocations'
                )->insert($chunk);
            }
        });
    }

    private function buildAllocations(
        string $month,
        string $statementOwnerType,
        int $statementOwnerId,
        float $commissionPercent
    ): array {
        $allocations = [];

        DB::table('report_rows')
            ->where(
                'sale_month',
                $month
            )
            ->where(
                'mapping_status',
                'mapped'
            )
            ->whereNotNull(
                'revenue_owner_type'
            )
            ->whereNotNull(
                'revenue_owner_id'
            )
            ->select([
                'id',
                'release_id',
                'track_id',
                'earnings',
                'sale_date',
                'revenue_owner_type',
                'revenue_owner_id',
            ])
            ->orderBy('id')
            ->chunkById(
                1000,
                function ($rows) use (
                    $month,
                    $statementOwnerType,
                    $statementOwnerId,
                    $commissionPercent,
                    &$allocations
                ) {
                    foreach ($rows as $row) {
                        $sharePercent =
                            $this->shareForStatement(
                                (string)
                                    $row
                                        ->revenue_owner_type,
                                (int)
                                    $row
                                        ->revenue_owner_id,
                                $statementOwnerType,
                                $statementOwnerId,
                                (string)
                                    $row
                                        ->sale_date
                            );

                        if (
                            $sharePercent
                            <= 0
                        ) {
                            continue;
                        }

                        $sourceGross =
                            (float)
                                $row
                                    ->earnings;

                        $allocatedGross =
                            round(
                                $sourceGross
                                * (
                                    $sharePercent
                                    / 100
                                ),
                                8
                            );

                        $netAmount =
                            round(
                                $allocatedGross
                                * (
                                    1
                                    - (
                                        $commissionPercent
                                        / 100
                                    )
                                ),
                                8
                            );

                        $allocations[] = [
                            'report_row_id' =>
                                $row->id,

                            'release_id' =>
                                $row
                                    ->release_id,

                            'track_id' =>
                                $row
                                    ->track_id,

                            /*
                             * gross_amount here is the
                             * beneficiary's allocated
                             * gross, not a second copy
                             * of DSP gross revenue.
                             */
                            'gross_amount' =>
                                $allocatedGross,

                            'net_amount' =>
                                $netAmount,

                            'share_percentage' =>
                                round(
                                    $sharePercent,
                                    4
                                ),
                        ];
                    }
                },
                'id'
            );

        return $allocations;
    }

    private function shareForStatement(
        string $sourceOwnerType,
        int $sourceOwnerId,
        string $statementOwnerType,
        int $statementOwnerId,
        string $saleDate
    ): float {
        $share =
            $this->activeShareForBeneficiary(
                $sourceOwnerType,
                $sourceOwnerId,
                $saleDate
            );

        /*
         * No explicit master/child share:
         * preserve existing behaviour.
         *
         * Source owner receives 100%.
         */
        if (!$share) {
            return (
                $sourceOwnerType
                    === $statementOwnerType
                && $sourceOwnerId
                    === $statementOwnerId
            )
                ? 100.0
                : 0.0;
        }

        $childPercent =
            (float)
                $share
                    ->revenue_share_percent;

        /*
         * Source child gets configured share.
         */
        if (
            $sourceOwnerType
                === $statementOwnerType
            && $sourceOwnerId
                === $statementOwnerId
        ) {
            return $childPercent;
        }

        /*
         * Master label receives only
         * the retained difference.
         */
        if (
            $statementOwnerType === 'label'
            && $statementOwnerId
                === (int)
                    $share
                        ->master_label_id
        ) {
            return round(
                100.0
                    - $childPercent,
                4
            );
        }

        return 0.0;
    }

    private function activeShareForBeneficiary(
        string $beneficiaryType,
        int $beneficiaryId,
        string $saleDate
    ): ?object {
        $periodDate =
            Carbon::parse(
                $saleDate
            )->toDateString();

        $key =
            $periodDate
            .'|'
            .$beneficiaryType
            .'|'
            .$beneficiaryId;

        if (
            array_key_exists(
                $key,
                $this->shareCache
            )
        ) {
            return $this
                ->shareCache[
                    $key
                ];
        }

        if (
            !in_array(
                $beneficiaryType,
                [
                    'label',
                    'artist',
                ],
                true
            )
        ) {
            return $this
                ->shareCache[
                    $key
                ] = null;
        }

        $share = DB::table(
            'label_revenue_shares'
        )
            ->where(
                'beneficiary_type',
                $beneficiaryType
            )
            ->where(
                'beneficiary_id',
                $beneficiaryId
            )
            ->where(
                'is_active',
                true
            )
            ->where(function ($query) use (
                $periodDate
            ) {
                $query
                    ->whereNull(
                        'effective_from'
                    )
                    ->orWhere(
                        'effective_from',
                        '<=',
                        $periodDate
                    );
            })
            ->where(function ($query) use (
                $periodDate
            ) {
                $query
                    ->whereNull(
                        'effective_to'
                    )
                    ->orWhere(
                        'effective_to',
                        '>=',
                        $periodDate
                    );
            })
            ->first();

        if (!$share) {
            return $this
                ->shareCache[
                    $key
                ] = null;
        }

        /*
         * HARD SECURITY / HIERARCHY CHECK
         *
         * Only:
         * Master -> direct Sub-Label
         * Master -> direct Artist
         *
         * No child-of-child split.
         */
        if (
            !$this->isValidDirectChildShare(
                $share
            )
        ) {
            return $this
                ->shareCache[
                    $key
                ] = null;
        }

        return $this
            ->shareCache[
                $key
            ] = $share;
    }

    private function isValidDirectChildShare(
        object $share
    ): bool {
        $master = DB::table(
            'labels'
        )
            ->where(
                'id',
                $share
                    ->master_label_id
            )
            ->whereNull(
                'deleted_at'
            )
            ->first([
                'id',
                'parent_label_id',
            ]);

        if (
            !$master
            || $master
                ->parent_label_id
                !== null
        ) {
            return false;
        }

        if (
            $share
                ->beneficiary_type
            === 'label'
        ) {
            return DB::table(
                'labels'
            )
                ->where(
                    'id',
                    $share
                        ->beneficiary_id
                )
                ->where(
                    'parent_label_id',
                    $master->id
                )
                ->whereNull(
                    'deleted_at'
                )
                ->exists();
        }

        if (
            $share
                ->beneficiary_type
            === 'artist'
        ) {
            return DB::table(
                'artists'
            )
                ->where(
                    'id',
                    $share
                        ->beneficiary_id
                )
                ->where(
                    'label_id',
                    $master->id
                )
                ->whereNull(
                    'deleted_at'
                )
                ->exists();
        }

        return false;
    }
}
