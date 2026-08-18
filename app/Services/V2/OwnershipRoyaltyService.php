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

        $periodDate =
            Carbon::createFromFormat(
                '!Y-m',
                $month
            )
                ->endOfMonth()
                ->toDateString();

        $rows = DB::table(
            'report_rows'
        )
            ->where(
                'reporting_month',
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
                'artist_id',
                'label_id',
            ])
            ->distinct()
            ->get();

        foreach ($rows as $row) {
            $sourceOwnerType =
                (string)
                    $row
                        ->revenue_owner_type;

            $sourceOwnerId =
                (int)
                    $row
                        ->revenue_owner_id;

            /*
             * Canonical catalogue owner remains
             * a statement owner.
             */
            $this->addOwner(
                $owners,
                $seen,
                $sourceOwnerType,
                $sourceOwnerId
            );

            /*
             * Catalogue can remain owned by a
             * master label while a direct artist
             * has a beneficiary revenue split.
             */
            if (
                $sourceOwnerType === 'label'
                && !empty(
                    $row->artist_id
                )
                && !empty(
                    $row->label_id
                )
                && (int)
                    $row->label_id
                    === $sourceOwnerId
            ) {
                $artistShare =
                    $this->activeShareForBeneficiary(
                        'artist',
                        (int)
                            $row->artist_id,
                        $periodDate
                    );

                if (
                    $artistShare
                    && (int)
                        $artistShare
                            ->master_label_id
                        === $sourceOwnerId
                ) {
                    $this->addOwner(
                        $owners,
                        $seen,
                        'artist',
                        (int)
                            $row->artist_id
                    );
                }
            }

            /*
             * RECURSIVE CATALOGUE LEVEL OWNER DISCOVERY
             * ==========================================
             *
             * Exact catalogue level lives in label_id.
             * Financial owner lives in revenue_owner_id.
             *
             * Example:
             *
             * Sanatan -> X -> X1
             *
             * report row:
             *   label_id         = X1
             *   revenue_owner_id = Sanatan
             *
             * If X1 has an active revenue agreement,
             * X1 must become a statement owner.
             */
            if (
                $sourceOwnerType === 'label'
                && !empty($row->label_id)
                && (int) $row->label_id
                    !== $sourceOwnerId
            ) {
                $levelShare =
                    $this->activeShareForBeneficiary(
                        'label',
                        (int) $row->label_id,
                        $periodDate
                    );

                if (
                    $levelShare
                    && (int)
                        $levelShare->master_label_id
                        === $sourceOwnerId
                ) {
                    $this->addOwner(
                        $owners,
                        $seen,
                        'label',
                        (int) $row->label_id
                    );

                    /*
                     * Root/master should also remain
                     * available for its retained share.
                     */
                    $this->addOwner(
                        $owners,
                        $seen,
                        'label',
                        $sourceOwnerId
                    );
                }
            }

            /*
             * Existing child-label / beneficiary
             * source ownership support.
             */
            $sourceShare =
                $this->activeShareForBeneficiary(
                    $sourceOwnerType,
                    $sourceOwnerId,
                    $periodDate
                );

            if ($sourceShare) {
                $this->addOwner(
                    $owners,
                    $seen,
                    'label',
                    (int)
                        $sourceShare
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
                'reporting_month',
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
                'artist_id',
                'label_id',
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
                        /*
                         * Canonical owner fast-path.
                         *
                         * Manually mapped revenue can legitimately have
                         * no catalogue release/track/artist/label IDs and
                         * no sale_date. In that case revenue_owner_type +
                         * revenue_owner_id remain the authoritative
                         * financial ownership fields.
                         *
                         * Direct canonical ownership therefore must not
                         * depend on catalogue metadata being present.
                         *
                         * Child/master cross-owner allocations continue
                         * through shareForStatement().
                         */
                        if (
                            (string) $row->revenue_owner_type
                                === $statementOwnerType
                            && (int) $row->revenue_owner_id
                                === $statementOwnerId
                            && $row->artist_id === null
                            && $row->label_id === null
                        ) {
                            $sharePercent = 100.0;
                        } else {
                            $sharePercent =
                                $this->shareForStatement(
                                (string)
                                    $row
                                        ->revenue_owner_type,
                                (int)
                                    $row
                                        ->revenue_owner_id,
                                $row->artist_id
                                    ? (int)
                                        $row->artist_id
                                    : null,
                                $row->label_id
                                    ? (int)
                                        $row->label_id
                                    : null,
                                $statementOwnerType,
                                $statementOwnerId,
                                Carbon::createFromFormat(
                                    '!Y-m',
                                    $month
                                )
                                    ->endOfMonth()
                                    ->toDateString()
                            );
                        }

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

                        /*
                         * Direct account commercial rate.
                         *
                         * When the statement belongs to the same
                         * canonical Label/Artist that owns the source
                         * revenue, use the rate assigned by Super Admin
                         * on that account.
                         *
                         * Cross-owner master/child beneficiary splits
                         * continue to use shareForStatement().
                         */
                        /*
                         * DIRECT ACCOUNT RATE
                         * ===================
                         *
                         * Apply the account commercial rate only when
                         * no beneficiary split has already changed the
                         * canonical 100% ownership result.
                         *
                         * Example nested hierarchy:
                         *
                         * X1 = 70%
                         * Root = 30%
                         *
                         * The 30% root result MUST NOT be overwritten
                         * by the root account rate (normally 100%).
                         *
                         * The same protection also applies to Artist
                         * beneficiary splits.
                         */
                        if (
                            (string) $row->revenue_owner_type
                                === $statementOwnerType
                            && (int) $row->revenue_owner_id
                                === $statementOwnerId
                            && abs(
                                (float) $sharePercent
                                - 100.0
                            ) < 0.0001
                        ) {
                            $accountRate =
                                $this->accountRevenueRate(
                                    $statementOwnerType,
                                    $statementOwnerId
                                );

                            if ($accountRate !== null) {
                                $sharePercent =
                                    $accountRate;
                            }
                        }

                        /*
                         * MIXX TUNE FINANCIAL RULE
                         * -------------------------
                         * Positive revenue:
                         *   apply beneficiary's normal assigned rate.
                         *
                         * Negative revenue / adjustment:
                         *   charge 100% of the negative amount to the
                         *   beneficiary instead of reducing it by the
                         *   assigned revenue-share percentage.
                         *
                         * Example:
                         *   +100 @ 80% = +80
                         *    -10 @ 80% = -10 (effective rate 100%)
                         *   payable     = +70
                         *
                         * This is an allocation-time override only.
                         * The beneficiary's persisted assigned rate
                         * remains unchanged.
                         */
                        $effectiveSharePercent =
                            $sourceGross < 0
                                ? 100.0
                                : $sharePercent;

                        $allocatedGross =
                            round(
                                $sourceGross
                                * (
                                    $effectiveSharePercent
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
                                    $effectiveSharePercent,
                                    4
                                ),
                        ];
                    }
                },
                'id'
            );

        return $allocations;
    }

    private function accountRevenueRate(
        string $ownerType,
        int $ownerId
    ): ?float {
        if ($ownerType === 'label') {
            $rate =
                DB::table('labels')
                    ->where('id', $ownerId)
                    ->value(
                        'revenue_share_percentage'
                    );
        } elseif ($ownerType === 'artist') {
            $rate =
                DB::table('artists')
                    ->where('id', $ownerId)
                    ->value(
                        'revenue_share_percentage'
                    );
        } else {
            return null;
        }

        if ($rate === null) {
            return null;
        }

        return round(
            max(
                0.0,
                min(
                    100.0,
                    (float) $rate
                )
            ),
            4
        );
    }

    private function shareForStatement(
        string $sourceOwnerType,
        int $sourceOwnerId,
        ?int $rowArtistId,
        ?int $rowLabelId,
        string $statementOwnerType,
        int $statementOwnerId,
        string $periodDate
    ): float {
        /*
         * RECURSIVE CATALOGUE LEVEL SPLIT
         * ===============================
         *
         * report_rows.label_id keeps the exact
         * catalogue level while revenue_owner_id
         * keeps the root/master financial owner.
         *
         * Example:
         *
         * Sanatan(root) -> X -> X1
         *
         * report row:
         *   label_id         = X1
         *   revenue_owner_id = Sanatan
         *
         * If X1 has a 70% agreement against
         * Sanatan:
         *
         *   X1      = 70%
         *   Sanatan = 30%
         *
         * This is NOT compounded through X.
         */
        if (
            $sourceOwnerType === 'label'
            && $rowLabelId !== null
            && $rowLabelId !== $sourceOwnerId
        ) {
            $levelShare =
                $this->activeShareForBeneficiary(
                    'label',
                    $rowLabelId,
                    $periodDate
                );

            if (
                $levelShare
                && (int) $levelShare->master_label_id
                    === $sourceOwnerId
            ) {
                $levelPercent =
                    max(
                        0.0,
                        min(
                            100.0,
                            (float)
                                $levelShare
                                    ->revenue_share_percent
                        )
                    );

                /*
                 * Exact level statement.
                 */
                if (
                    $statementOwnerType === 'label'
                    && $statementOwnerId === $rowLabelId
                ) {
                    return round(
                        $levelPercent,
                        4
                    );
                }

                /*
                 * Root/master retains difference.
                 */
                if (
                    $statementOwnerType === 'label'
                    && $statementOwnerId === $sourceOwnerId
                ) {
                    return round(
                        100.0 - $levelPercent,
                        4
                    );
                }

                return 0.0;
            }
        }

        /*
         * Master-label catalogue ownership with
         * a direct artist beneficiary.
         *
         * Example:
         *
         * DSP gross = 2500
         * Artist    = 70%
         * Master    = 30%
         *
         * Catalogue ownership remains label.
         */
        if (
            $sourceOwnerType === 'label'
            && $rowArtistId !== null
            && $rowLabelId !== null
            && $rowLabelId
                === $sourceOwnerId
        ) {
            $artistShare =
                $this->activeShareForBeneficiary(
                    'artist',
                    $rowArtistId,
                    $periodDate
                );

            if (
                $artistShare
                && (int)
                    $artistShare
                        ->master_label_id
                    === $sourceOwnerId
            ) {
                $artistPercent =
                    max(
                        0.0,
                        min(
                            100.0,
                            (float)
                                $artistShare
                                    ->revenue_share_percent
                        )
                    );

                if (
                    $statementOwnerType
                        === 'artist'
                    && $statementOwnerId
                        === $rowArtistId
                ) {
                    return round(
                        $artistPercent,
                        4
                    );
                }

                if (
                    $statementOwnerType
                        === 'label'
                    && $statementOwnerId
                        === $sourceOwnerId
                ) {
                    return round(
                        100.0
                            - $artistPercent,
                        4
                    );
                }

                return 0.0;
            }
        }

        /*
         * Existing source-beneficiary split.
         */
        $share =
            $this->activeShareForBeneficiary(
                $sourceOwnerType,
                $sourceOwnerId,
                $periodDate
            );

        /*
         * No agreement means canonical owner
         * receives full revenue.
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
            max(
                0.0,
                min(
                    100.0,
                    (float)
                        $share
                            ->revenue_share_percent
                )
            );

        if (
            $sourceOwnerType
                === $statementOwnerType
            && $sourceOwnerId
                === $statementOwnerId
        ) {
            return round(
                $childPercent,
                4
            );
        }

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
            !$this->isValidHierarchyShare(
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

        /*
         * Financial contracts may only originate
         * from a root/master label.
         */
        if (
            !$master
            || $master->parent_label_id !== null
        ) {
            return false;
        }

        $hierarchy = app(
            LabelHierarchyService::class
        );

        if (
            $share->beneficiary_type
            === 'label'
        ) {
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

            if (!$beneficiary) {
                return false;
            }

            /*
             * Master cannot be its own beneficiary.
             */
            if (
                (int) $beneficiary->id
                === (int) $master->id
            ) {
                return false;
            }

            return (int)
                $hierarchy->rootLabelId(
                    (int) $beneficiary->id
                )
                === (int) $master->id;
        }

        if (
            $share->beneficiary_type
            === 'artist'
        ) {
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

            if (
                !$artist
                || !$artist->label_id
            ) {
                return false;
            }

            return (int)
                $hierarchy->rootLabelId(
                    (int) $artist->label_id
                )
                === (int) $master->id;
        }

        return false;
    }
}
