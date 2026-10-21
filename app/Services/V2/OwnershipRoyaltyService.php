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

        /*
         * MIXXTUNE_ROYALTY_CUTOVER_2026_07
         *
         * Legacy WordPress closing balances already include
         * historical finance through the migration cutover.
         * Report sale months <= 2026-06 are therefore
         * historical/read-only and must never be posted again.
         */
        if ($month < '2026-07') {
            throw new \RuntimeException(
                'Historical royalty month blocked by cutover: '.$month
            );
        }


        $owners = $this->statementOwners(
            $month
        );

        $created = 0;
        $updated = 0;
        $skipped = 0;
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

                /*
                 * W55 MIXXTUNE_REGENERATE_SAFE_V1
                 *
                 * Rebuilding may only touch a statement that is still
                 * pending. An approved / available / paid statement, or one
                 * paid through a report import, already moved money: putting
                 * it back to "pending" with new amounts would let it be
                 * approved (credited) a second time.
                 */
                if (
                    $existing
                    && (
                        !in_array((string) $existing->status, ['pending', 'generated'], true)
                        || DB::table('report_import_postings')
                            ->where('royalty_statement_id', $existing->id)
                            ->exists()
                    )
                ) {
                    $skipped++;

                    continue;
                }

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
            'skipped' => $skipped,

            'failed_count' =>
                count($failed),

            'failed' => $failed,
        ];
    }

    /*
     * MIXXTUNE_IMPORT_SCOPED_ALLOCATION_PREVIEW
     *
     * READ ONLY.
     *
     * Executes the existing allocation formula for one
     * exact statement beneficiary against one exact
     * approved import.
     *
     * No statement/allocation/wallet/status write.
     */
    public function previewImportOwnerAllocations(
        int $reportImportId,
        string $statementOwnerType,
        int $statementOwnerId,
        float $commissionPercent = 0.0
    ): array {
        if (
            !in_array(
                $statementOwnerType,
                [
                    'label',
                    'artist',
                ],
                true
            )
        ) {
            throw new \InvalidArgumentException(
                'Invalid statement owner type.'
            );
        }

        $import =
            DB::table('report_imports')
                ->where(
                    'id',
                    $reportImportId
                )
                ->first([
                    'id',
                    'reporting_month',
                    'financial_status',
                ]);

        if (!$import) {
            throw new \RuntimeException(
                'Revenue import not found.'
            );
        }

        if (
            !in_array(
                $import->financial_status,
                [
                    'approved_scheduled',
                    'published',
                ],
                true
            )
        ) {
            throw new \RuntimeException(
                'Import-scoped allocation requires '
                .'approved_scheduled or published status.'
            );
        }

        $invalidRows =
            DB::table('report_rows')
                ->where(
                    'report_import_id',
                    $reportImportId
                )
                ->where(function ($query) {
                    $query
                        ->where(
                            'mapping_status',
                            '<>',
                            'mapped'
                        )
                        ->orWhereNull(
                            'revenue_owner_type'
                        )
                        ->orWhereNull(
                            'revenue_owner_id'
                        )
                        ->orWhereNull(
                            'currency'
                        )
                        ->orWhere(
                            'currency',
                            ''
                        )
                        ->orWhereNull(
                            'earnings'
                        );
                })
                ->count();

        if ($invalidRows > 0) {
            throw new \RuntimeException(
                'Import contains '
                .$invalidRows
                .' incomplete financial rows.'
            );
        }

        /*
         * buildAllocations() expects Y-m because its
         * existing statement flow is month based.
         */
        $month =
            Carbon::parse(
                $import->reporting_month
            )->format('Y-m');

        $allocations =
            $this->buildAllocations(
                $month,
                $statementOwnerType,
                $statementOwnerId,
                $commissionPercent,
                $reportImportId
            );

        return [
            'report_import_id' =>
                (int) $import->id,

            'reporting_month' =>
                $month,

            'financial_status' =>
                $import->financial_status,

            'statement_owner_type' =>
                $statementOwnerType,

            'statement_owner_id' =>
                $statementOwnerId,

            'allocation_count' =>
                count($allocations),

            'gross_amount' =>
                round(
                    array_sum(
                        array_map(
                            static fn (
                                array $allocation
                            ): float =>
                                (float)
                                    $allocation[
                                        'gross_amount'
                                    ],
                            $allocations
                        )
                    ),
                    8
                ),

            'net_amount' =>
                round(
                    array_sum(
                        array_map(
                            static fn (
                                array $allocation
                            ): float =>
                                (float)
                                    $allocation[
                                        'net_amount'
                                    ],
                            $allocations
                        )
                    ),
                    8
                ),

            'allocations' =>
                $allocations,
        ];
    }

    /*
     * MIXXTUNE_COMBINED_IMPORT_PUBLICATION_PREVIEW
     *
     * READ ONLY.
     *
     * Produces the exact beneficiary/currency posting
     * population for one immutable approved import.
     *
     * No statement, allocation, wallet transaction,
     * journal or publication row is written here.
     */
    public function previewImportPublication(
        int $reportImportId
    ): array {
        $import =
            DB::table('report_imports')
                ->where(
                    'id',
                    $reportImportId
                )
                ->first([
                    'id',
                    'reporting_month',
                    'financial_status',
                    'publish_at',
                ]);

        if (!$import) {
            throw new \RuntimeException(
                'Revenue import not found.'
            );
        }

        if (
            !in_array(
                $import->financial_status,
                [
                    'approved_scheduled',
                    'published',
                ],
                true
            )
        ) {
            throw new \RuntimeException(
                'Import publication preview requires '
                .'approved_scheduled or published status.'
            );
        }

        /*
         * Approval should already enforce these rules,
         * but preview fails closed as a second guard.
         */
        $invalidRows =
            DB::table('report_rows')
                ->where(
                    'report_import_id',
                    $reportImportId
                )
                ->where(function ($query) {
                    $query
                        ->where(
                            'mapping_status',
                            '<>',
                            'mapped'
                        )
                        ->orWhereNull(
                            'revenue_owner_type'
                        )
                        ->orWhereNull(
                            'revenue_owner_id'
                        )
                        ->orWhereNull(
                            'currency'
                        )
                        ->orWhere(
                            'currency',
                            ''
                        )
                        ->orWhereNull(
                            'earnings'
                        );
                })
                ->count();

        if ($invalidRows > 0) {
            throw new \RuntimeException(
                'Import contains '
                .$invalidRows
                .' incomplete financial rows.'
            );
        }

        $month =
            Carbon::parse(
                $import->reporting_month
            )->format('Y-m');

        /*
         * Reuse the exact existing owner discovery.
         */
        $owners =
            $this->statementOwners(
                $month,
                $reportImportId
            );

        /*
         * Currency is part of the frozen row snapshot.
         * Never substitute account/default currency here.
         */
        $currencies =
            DB::table('report_rows')
                ->where(
                    'report_import_id',
                    $reportImportId
                )
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->selectRaw(
                    'UPPER(TRIM(currency)) as currency'
                )
                ->distinct()
                ->orderBy('currency')
                ->pluck('currency')
                ->filter()
                ->values()
                ->all();

        if ($currencies === []) {
            throw new \RuntimeException(
                'Import has no financial currency.'
            );
        }

        $postings = [];

        foreach ($owners as $owner) {
            foreach ($currencies as $currency) {
                /*
                 * commissionPercent = 0.0 intentionally.
                 *
                 * Commercial account rate has already
                 * been frozen into report_rows.earnings.
                 * Hierarchy sharing remains inside the
                 * existing buildAllocations() formula.
                 */
                $allocations =
                    $this->buildAllocations(
                        $month,
                        (string) $owner['type'],
                        (int) $owner['id'],
                        0.0,
                        $reportImportId
                    );

                /*
                 * buildAllocations currently works from
                 * exact import population. Filter by the
                 * frozen currency defensively because a
                 * future import may contain >1 currency.
                 */
                $currencyRowIds =
                    DB::table('report_rows')
                        ->where(
                            'report_import_id',
                            $reportImportId
                        )
                        ->where(
                            'mapping_status',
                            'mapped'
                        )
                        ->whereRaw(
                            'UPPER(TRIM(currency)) = ?',
                            [
                                strtoupper(
                                    (string) $currency
                                ),
                            ]
                        )
                        ->pluck('id')
                        ->map(
                            fn ($id) => (int) $id
                        )
                        ->flip();

                $allocations =
                    array_values(
                        array_filter(
                            $allocations,
                            function ($allocation) use (
                                $currencyRowIds
                            ) {
                                return
                                    $currencyRowIds
                                        ->has(
                                            (int)
                                            $allocation[
                                                'report_row_id'
                                            ]
                                        );
                            }
                        )
                    );

                if ($allocations === []) {
                    continue;
                }

                $gross =
                    round(
                        array_sum(
                            array_map(
                                fn ($allocation) =>
                                    (float)
                                    $allocation[
                                        'gross_amount'
                                    ],
                                $allocations
                            )
                        ),
                        8
                    );

                $net =
                    round(
                        array_sum(
                            array_map(
                                fn ($allocation) =>
                                    (float)
                                    $allocation[
                                        'net_amount'
                                    ],
                                $allocations
                            )
                        ),
                        8
                    );

                $postings[] = [
                    'owner_type' =>
                        (string) $owner['type'],

                    'owner_id' =>
                        (int) $owner['id'],

                    'currency' =>
                        strtoupper(
                            (string) $currency
                        ),

                    'allocation_count' =>
                        count($allocations),

                    'gross_amount' =>
                        $gross,

                    'commission_amount' =>
                        round(
                            $gross - $net,
                            8
                        ),

                    'net_amount' =>
                        $net,
                ];
            }
        }

        usort(
            $postings,
            function ($a, $b) {
                return [
                    $a['owner_type'],
                    $a['owner_id'],
                    $a['currency'],
                ] <=> [
                    $b['owner_type'],
                    $b['owner_id'],
                    $b['currency'],
                ];
            }
        );

        $mappedSnapshot =
            DB::table('report_rows')
                ->where(
                    'report_import_id',
                    $reportImportId
                )
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->selectRaw(
                    'COUNT(*) as row_count, '
                    .'COALESCE(SUM(collected_revenue),0) '
                    .'as collected_revenue, '
                    .'COALESCE(SUM(earnings),0) '
                    .'as earnings'
                )
                ->first();

        $postingNet =
            round(
                array_sum(
                    array_map(
                        fn ($posting) =>
                            (float)
                            $posting['net_amount'],
                        $postings
                    )
                ),
                8
            );

        return [
            'report_import_id' =>
                (int) $import->id,

            'reporting_month' =>
                $month,

            'financial_status' =>
                $import->financial_status,

            'publish_at' =>
                $import->publish_at,

            'owner_count' =>
                count($owners),

            'currency_count' =>
                count($currencies),

            'currencies' =>
                $currencies,

            'posting_count' =>
                count($postings),

            'mapped_row_count' =>
                (int)
                ($mappedSnapshot->row_count ?? 0),

            'mapped_collected_revenue' =>
                round(
                    (float)
                    ($mappedSnapshot
                        ->collected_revenue ?? 0),
                    8
                ),

            'mapped_frozen_earnings' =>
                round(
                    (float)
                    ($mappedSnapshot->earnings ?? 0),
                    8
                ),

            'posting_net_total' =>
                $postingNet,

            /*
             * With no hierarchy agreements this should
             * equal frozen earnings exactly.
             *
             * With valid hierarchy agreements, total
             * beneficiary allocations should still
             * reconcile to the same economic pool.
             */
            'reconciliation_difference' =>
                round(
                    $postingNet
                    - (float)
                    ($mappedSnapshot->earnings ?? 0),
                    8
                ),

            'postings' =>
                $postings,
        ];
    }


    /*
     * MIXXTUNE_IMPORT_SCOPED_OWNER_DISCOVERY_PREVIEW
     *
     * READ ONLY.
     * Uses the exact existing statementOwners()
     * hierarchy rules against one approved import.
     */
    public function previewImportStatementOwners(
        int $reportImportId
    ): array {
        $import =
            DB::table('report_imports')
                ->where(
                    'id',
                    $reportImportId
                )
                ->first([
                    'id',
                    'reporting_month',
                    'financial_status',
                ]);

        if (!$import) {
            throw new \RuntimeException(
                'Revenue import not found.'
            );
        }

        if (
            !in_array(
                $import->financial_status,
                [
                    'approved_scheduled',
                    'published',
                ],
                true
            )
        ) {
            throw new \RuntimeException(
                'Import-scoped owner discovery requires '
                .'approved_scheduled or published status.'
            );
        }

        $invalidRows =
            DB::table('report_rows')
                ->where(
                    'report_import_id',
                    $reportImportId
                )
                ->where(function ($query) {
                    $query
                        ->where(
                            'mapping_status',
                            '<>',
                            'mapped'
                        )
                        ->orWhereNull(
                            'revenue_owner_type'
                        )
                        ->orWhereNull(
                            'revenue_owner_id'
                        )
                        ->orWhereNull(
                            'currency'
                        )
                        ->orWhere(
                            'currency',
                            ''
                        )
                        ->orWhereNull(
                            'earnings'
                        );
                })
                ->count();

        if ($invalidRows > 0) {
            throw new \RuntimeException(
                'Import contains '
                .$invalidRows
                .' incomplete financial rows.'
            );
        }

        $month =
            Carbon::parse(
                $import->reporting_month
            )->format('Y-m');

        $owners =
            $this->statementOwners(
                $month,
                $reportImportId
            );

        return [
            'report_import_id' =>
                (int) $import->id,

            'reporting_month' =>
                $month,

            'financial_status' =>
                $import->financial_status,

            'owner_count' =>
                count($owners),

            'owners' =>
                $owners,
        ];
    }

    private function statementOwners(
        string $month,
        ?int $reportImportId = null
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

        /*
         * MIXXTUNE_FINANCIAL_VISIBILITY_GATE
         *
         * Statement owners are discovered only from
         * financially published report imports.
         */
        $rowsQuery = DB::table(
            'report_rows'
        );

        if ($reportImportId === null) {
            /*
             * Existing user-facing behavior:
             * published financial population only.
             */
            $rowsQuery->whereExists(
                function ($publishedImport) {
                    $publishedImport
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
                            'published'
                        );
                }
            );
        } else {
            /*
             * MIXXTUNE_IMPORT_SCOPED_OWNER_DISCOVERY
             *
             * Internal scheduled-publisher discovery.
             * Exact immutable import only.
             */
            $rowsQuery
                ->where(
                    'report_rows.report_import_id',
                    $reportImportId
                )
                ->whereExists(
                    function ($financialImport) use (
                        $reportImportId
                    ) {
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
                                'financial_import.id',
                                $reportImportId
                            )
                            ->whereIn(
                                'financial_import.financial_status',
                                [
                                    'approved_scheduled',
                                    'published',
                                ]
                            );
                    }
                );
        }

        $rows = $rowsQuery
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
             * STRICT TWO-TIER CATALOGUE OWNER DISCOVERY
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
        float $commissionPercent,
        ?int $reportImportId = null
    ): array {
        $allocations = [];

        /*
         * MIXXTUNE_FINANCIAL_VISIBILITY_GATE
         *
         * Allocations must use exactly the same published
         * financial population as statementOwners().
         */
        $rowQuery =
            DB::table('report_rows');

        if ($reportImportId === null) {
            /*
             * Existing application behavior:
             * user-facing accounting sees published imports only.
             */
            $rowQuery->whereExists(
                function ($publishedImport) {
                    $publishedImport
                        ->selectRaw('1')
                        ->from('report_imports as financial_import')
                        ->whereColumn(
                            'financial_import.id',
                            'report_rows.report_import_id'
                        )
                        ->where(
                            'financial_import.financial_status',
                            'published'
                        );
                }
            );
        } else {
            /*
             * MIXXTUNE_IMPORT_SCOPED_ALLOCATION
             *
             * Internal publisher calculation path.
             * Exact immutable import only.
             *
             * The caller validates that the import is
             * approved_scheduled/published before this
             * query is allowed to execute.
             */
            $rowQuery
                ->where(
                    'report_rows.report_import_id',
                    $reportImportId
                )
                ->whereExists(
                    function ($financialImport) use (
                        $reportImportId
                    ) {
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
                                'financial_import.id',
                                $reportImportId
                            )
                            ->whereIn(
                                'financial_import.financial_status',
                                [
                                    'approved_scheduled',
                                    'published',
                                ]
                            );
                    }
                );
        }

        $rowQuery
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
                'collected_revenue',
                'label_rate',
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
                        $canonicalOwnerHasHierarchyShare =
                            $row->artist_id === null
                            && $row->label_id === null
                            && $this->activeShareForBeneficiary(
                                (string)
                                    $row->revenue_owner_type,
                                (int)
                                    $row->revenue_owner_id,
                                Carbon::createFromFormat(
                                    '!Y-m',
                                    $month
                                )
                                    ->endOfMonth()
                                    ->toDateString()
                            ) !== null;

                        if (
                            (string) $row->revenue_owner_type
                                === $statementOwnerType
                            && (int) $row->revenue_owner_id
                                === $statementOwnerId
                            && $row->artist_id === null
                            && $row->label_id === null
                            && !$canonicalOwnerHasHierarchyShare
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

                        /*
                         * FINANCIAL SOURCE BASE
                         * =====================
                         *
                         * report_rows.earnings can already contain the
                         * account/import commercial rate. Applying a
                         * hierarchy beneficiary percentage to earnings
                         * would therefore apply a second rate.
                         *
                         * Cross-owner / beneficiary allocations must start
                         * from collected_revenue (DSP/source gross).
                         *
                         * Direct canonical ownership preserves the existing
                         * earnings base because its account commercial rate
                         * flow is handled below.
                         *
                         * Legacy rows with NULL collected_revenue safely
                         * fall back to earnings.
                         */
                        $isDirectCanonicalOwner =
                            (string) $row->revenue_owner_type
                                === $statementOwnerType
                            && (int) $row->revenue_owner_id
                                === $statementOwnerId;

                        $hasBeneficiarySplit =
                            !$isDirectCanonicalOwner
                            || abs(
                                (float) $sharePercent
                                - 100.0
                            ) >= 0.0001;

                        if (
                            $hasBeneficiarySplit
                            && $row->collected_revenue !== null
                        ) {
                            $sourceGross =
                                (float)
                                    $row
                                        ->collected_revenue;
                        } else {
                            $sourceGross =
                                (float)
                                    $row
                                        ->earnings;
                        }

                        /*
                         * FINAL COMMERCIAL SNAPSHOT RULE
                         * ==============================
                         *
                         * For the direct canonical owner,
                         * report_rows.earnings already contains
                         * the commercial account rate captured
                         * at import time.
                         *
                         * Never look up the account's CURRENT
                         * revenue_share_percentage here.
                         *
                         * Historical statements must therefore
                         * remain unchanged when an account rate
                         * is edited later.
                         *
                         * Hierarchy beneficiary percentages are
                         * handled separately by shareForStatement().
                         */

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
                        /*
                         * Hierarchy share remains contractual
                         * for both positive and negative rows.
                         *
                         * The negative 100% commercial-rate rule
                         * applies only to a direct canonical owner.
                         *
                         * Example hierarchy:
                         *   source adjustment = -100
                         *   child share       = 80%
                         *   master retained   = 20%
                         *
                         * allocation:
                         *   child  = -80
                         *   master = -20
                         *
                         * total remains exactly -100.
                         */
                        $effectiveSharePercent =
                            $sharePercent;

                        if (
                            $isDirectCanonicalOwner
                            && !$hasBeneficiarySplit
                        ) {
                            if (
                                $row->collected_revenue !== null
                            ) {
                                /*
                                 * NEW SNAPSHOT ROW
                                 * ================
                                 *
                                 * Import already calculated:
                                 *
                                 * collected_revenue
                                 * × applied rate
                                 * = earnings
                                 *
                                 * Therefore stored earnings is
                                 * authoritative and MUST NOT be
                                 * rated again here.
                                 */
                                $allocatedGross =
                                    round(
                                        (float)
                                            $row->earnings,
                                        8
                                    );

                                $effectiveSharePercent =
                                    (float)
                                        $row->earnings < 0
                                            ? 100.0
                                            : round(
                                                (float)
                                                    $row->label_rate
                                                * 100,
                                                8
                                            );
                            } else {
                                /*
                                 * LEGACY ROW
                                 * ==========
                                 *
                                 * Older report_rows may contain
                                 * raw source revenue directly in
                                 * earnings and have NULL
                                 * collected_revenue.
                                 *
                                 * Positive legacy revenue still
                                 * needs the account's configured
                                 * commercial rate.
                                 *
                                 * Negative legacy revenue is
                                 * charged at 100%.
                                 */
                                $legacyGross =
                                    (float)
                                        $row->earnings;

                                if ($legacyGross < 0) {
                                    $effectiveSharePercent =
                                        100.0;
                                } else {
                                    $legacyRate =
                                        $this
                                            ->accountRevenueRate(
                                                $statementOwnerType,
                                                $statementOwnerId
                                            );

                                    $effectiveSharePercent =
                                        $legacyRate ?? 100.0;
                                }

                                $allocatedGross =
                                    round(
                                        $legacyGross
                                        * (
                                            $effectiveSharePercent
                                            / 100
                                        ),
                                        8
                                    );
                            }
                        } else {
                            /*
                             * HIERARCHY ALLOCATION
                             * ====================
                             *
                             * This remains a distinct contractual
                             * beneficiary split.
                             */
                            $allocatedGross =
                                round(
                                    $sourceGross
                                    * (
                                        $effectiveSharePercent
                                        / 100
                                    ),
                                    8
                                );
                        }

                        /*
                         * No second commercial commission.
                         *
                         * allocatedGross is already the final
                         * statement amount for this beneficiary.
                         */
                        $netAmount =
                            $allocatedGross;

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
         * STRICT TWO-TIER CATALOGUE LEVEL SPLIT
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

            /*
             * STRICT TWO-TIER:
             * label beneficiary must be direct child.
             */
            return (int) $beneficiary->parent_label_id
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

            /*
             * STRICT TWO-TIER:
             * artist must be directly attached to master.
             */
            return (int) $artist->label_id
                === (int) $master->id;
        }

        return false;
    }
}
