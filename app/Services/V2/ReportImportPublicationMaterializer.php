<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/*
 * MIXXTUNE_ADDITIVE_IMPORT_MATERIALIZER
 * MIXXTUNE_ADDITIVE_MATERIALIZATION_V2
 *
 * Exact-import additive statement materialization.
 *
 * GUARANTEES
 * ----------
 * 1. Only approved_scheduled / published imports.
 * 2. No wallet mutation.
 * 3. No import publication/status mutation.
 * 4. Existing royalty allocations are NEVER deleted.
 * 5. Import posting journal is the idempotency boundary.
 * 6. Statement totals are recomputed from ALL allocations
 *    after new import allocations are appended.
 * 7. Frozen report-row financial values remain source.
 */
class ReportImportPublicationMaterializer
{
    public function __construct(
        private readonly OwnershipRoyaltyService $royalties
    ) {
    }

    public function preview(
        int $reportImportId
    ): array {
        return $this->royalties
            ->previewImportPublication(
                $reportImportId
            );
    }

    public function assertEligible(
        int $reportImportId
    ): array {
        $import = DB::table('report_imports')
            ->where('id', $reportImportId)
            ->first([
                'id',
                'reporting_month',
                'financial_status',
                'publish_at',
                'approved_at',
                'published_at',
            ]);

        if (!$import) {
            throw new RuntimeException(
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
            throw new RuntimeException(
                'Import materialization requires '
                .'approved_scheduled or published status.'
            );
        }

        /*
         * NULL-safe completeness guard.
         */
        $invalidRows = DB::table('report_rows')
            ->where(
                'report_import_id',
                $reportImportId
            )
            ->where(function ($query) {
                $query
                    ->whereNull('mapping_status')
                    ->orWhere(
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
                    ->orWhereNull('currency')
                    ->orWhereRaw(
                        "TRIM(currency) = ''"
                    )
                    ->orWhereNull('earnings');
            })
            ->count();

        if ($invalidRows > 0) {
            throw new RuntimeException(
                'Import contains '
                .$invalidRows
                .' incomplete financial rows.'
            );
        }

        $preview = $this->preview(
            $reportImportId
        );

        if (
            (int) (
                $preview['mapped_row_count']
                ?? 0
            ) <= 0
        ) {
            throw new RuntimeException(
                'Import has no mapped financial rows.'
            );
        }

        if (
            (int) (
                $preview['posting_count']
                ?? 0
            ) <= 0
        ) {
            throw new RuntimeException(
                'Import has no publication postings.'
            );
        }

        return [
            'import' => $import,
            'preview' => $preview,
        ];
    }

    /*
     * Materializes accounting only.
     *
     * DOES NOT:
     * - credit wallet
     * - change financial_status to published
     * - set published_at
     */
    public function materialize(
        int $reportImportId
    ): array {
        $eligible = $this->assertEligible(
            $reportImportId
        );

        $preview = $eligible['preview'];

        return DB::transaction(
            function () use (
                $reportImportId,
                $preview
            ) {
                $import = DB::table(
                    'report_imports'
                )
                    ->where(
                        'id',
                        $reportImportId
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$import) {
                    throw new RuntimeException(
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
                    throw new RuntimeException(
                        'Import is no longer eligible '
                        .'for materialization.'
                    );
                }

                /*
                 * One publication journal per import.
                 */
                $publication =
                    DB::table(
                        'report_import_publications'
                    )
                        ->where(
                            'report_import_id',
                            $reportImportId
                        )
                        ->lockForUpdate()
                        ->first();

                if (!$publication) {
                    DB::table(
                        'report_import_publications'
                    )->insert([
                        'report_import_id' =>
                            $reportImportId,

                        'status' =>
                            'materializing',

                        'scheduled_for' =>
                            $import->publish_at,

                        'started_at' =>
                            now(),

                        'attempt_count' =>
                            1,

                        'row_count' =>
                            (int) (
                                $preview[
                                    'mapped_row_count'
                                ] ?? 0
                            ),

                        'posting_count' =>
                            (int) (
                                $preview[
                                    'posting_count'
                                ] ?? 0
                            ),

                        'collected_revenue' =>
                            $preview[
                                'mapped_collected_revenue'
                            ] ?? 0,

                        'earnings' =>
                            $preview[
                                'mapped_frozen_earnings'
                            ] ?? 0,

                        'metadata' =>
                            json_encode([
                                'phase' =>
                                    'statement_materialization',

                                'reporting_month' =>
                                    $preview[
                                        'reporting_month'
                                    ] ?? null,
                            ]),

                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $publication =
                        DB::table(
                            'report_import_publications'
                        )
                            ->where(
                                'report_import_id',
                                $reportImportId
                            )
                            ->lockForUpdate()
                            ->first();
                } else {
                    DB::table(
                        'report_import_publications'
                    )
                        ->where(
                            'id',
                            $publication->id
                        )
                        ->update([
                            'attempt_count' =>
                                DB::raw(
                                    'attempt_count + 1'
                                ),

                            'started_at' =>
                                $publication->started_at
                                ?: now(),

                            'updated_at' =>
                                now(),
                        ]);
                }

                if (!$publication) {
                    throw new RuntimeException(
                        'Unable to create publication journal.'
                    );
                }

                $materialized = [];
                $month =
                    (string)
                    $preview['reporting_month'];

                foreach (
                    $preview['postings']
                    as $posting
                ) {
                    $ownerType =
                        (string)
                        $posting['owner_type'];

                    $ownerId =
                        (int)
                        $posting['owner_id'];

                    $currency =
                        strtoupper(
                            trim(
                                (string)
                                $posting['currency']
                            )
                        );

                    $postingKey =
                        'import:'
                        .$reportImportId
                        .':'
                        .$ownerType
                        .':'
                        .$ownerId
                        .':'
                        .$currency;

                    /*
                     * Import+beneficiary+currency journal
                     * is the additive idempotency boundary.
                     */
                    $journal =
                        DB::table(
                            'report_import_postings'
                        )
                            ->where(
                                'report_import_id',
                                $reportImportId
                            )
                            ->where(
                                'owner_type',
                                $ownerType
                            )
                            ->where(
                                'owner_id',
                                $ownerId
                            )
                            ->where(
                                'currency',
                                $currency
                            )
                            ->lockForUpdate()
                            ->first();

                    if (
                        $journal
                        && in_array(
                            $journal->status,
                            [
                                'materialized',
                                'posted',
                                'completed',
                                'held_unclaimed', // MIXXTUNE_MATERIALIZER_HELD_UNCLAIMED_FINAL_V1
                            ],
                            true
                        )
                    ) {
                        $materialized[] = [
                            'posting_key' =>
                                $postingKey,

                            'status' =>
                                'already_materialized',

                            'statement_id' =>
                                $journal
                                    ->royalty_statement_id,
                        ];

                        continue;
                    }

                    if (!$journal) {
                        DB::table(
                            'report_import_postings'
                        )->insert([
                            'publication_id' =>
                                $publication->id,

                            'report_import_id' =>
                                $reportImportId,

                            'owner_type' =>
                                $ownerType,

                            'owner_id' =>
                                $ownerId,

                            'currency' =>
                                $currency,

                            'posting_key' =>
                                $postingKey,

                            'status' =>
                                'materializing',

                            'row_count' =>
                                (int)
                                $posting[
                                    'allocation_count'
                                ],

                            /*
                             * Journal naming is legacy:
                             * collected_revenue = posting gross
                             * earnings = posting final net.
                             */
                            'collected_revenue' =>
                                $posting[
                                    'gross_amount'
                                ],

                            'earnings' =>
                                $posting[
                                    'net_amount'
                                ],

                            'metadata' =>
                                json_encode([
                                    'reporting_month' =>
                                        $month,

                                    'commission_amount' =>
                                        $posting[
                                            'commission_amount'
                                        ],
                                ]),

                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $journal =
                            DB::table(
                                'report_import_postings'
                            )
                                ->where(
                                    'posting_key',
                                    $postingKey
                                )
                                ->lockForUpdate()
                                ->first();
                    }

                    if (!$journal) {
                        throw new RuntimeException(
                            'Unable to create posting journal: '
                            .$postingKey
                        );
                    }

                    /*
                     * Find/create consolidated
                     * owner+month+currency statement.
                     *
                     * Explicit owner predicates avoid
                     * relying on nullable MySQL unique
                     * index semantics.
                     */
                    $statementQuery =
                        DB::table(
                            'royalty_statements'
                        )
                            ->where(
                                'statement_month',
                                $month
                            )
                            ->where(
                                'currency',
                                $currency
                            );

                    if ($ownerType === 'label') {
                        $statementQuery
                            ->where(
                                'label_id',
                                $ownerId
                            )
                            ->whereNull(
                                'artist_id'
                            );
                    } elseif (
                        $ownerType === 'artist'
                    ) {
                        $statementQuery
                            ->where(
                                'artist_id',
                                $ownerId
                            )
                            ->whereNull(
                                'label_id'
                            );
                    } else {
                        throw new RuntimeException(
                            'Unsupported owner type: '
                            .$ownerType
                        );
                    }

                    $statement =
                        $statementQuery
                            ->lockForUpdate()
                            ->first();

                    if (
                        $statement
                        && in_array(
                            $statement->status,
                            [
                                'paid',
                            ],
                            true
                        )
                    ) {
                        throw new RuntimeException(
                            'Cannot append import to paid '
                            .'royalty statement #'
                            .$statement->id
                        );
                    }

                    if (!$statement) {
                        $statementId =
                            DB::table(
                                'royalty_statements'
                            )
                                ->insertGetId([
                                    'public_id' =>
                                        (string)
                                        Str::ulid(),

                                    'artist_id' =>
                                        $ownerType
                                        === 'artist'
                                            ? $ownerId
                                            : null,

                                    'label_id' =>
                                        $ownerType
                                        === 'label'
                                            ? $ownerId
                                            : null,

                                    'statement_month' =>
                                        $month,

                                    'currency' =>
                                        $currency,

                                    'gross_earnings' =>
                                        0,

                                    'commission_amount' =>
                                        0,

                                    'tax_amount' =>
                                        0,

                                    'other_deductions' =>
                                        0,

                                    'net_payable' =>
                                        0,

                                    'status' =>
                                        'pending',

                                    'created_at' =>
                                        now(),

                                    'updated_at' =>
                                        now(),
                                ]);

                        $statement =
                            DB::table(
                                'royalty_statements'
                            )
                                ->where(
                                    'id',
                                    $statementId
                                )
                                ->lockForUpdate()
                                ->first();
                    }

                    if (!$statement) {
                        throw new RuntimeException(
                            'Unable to create royalty statement.'
                        );
                    }

                    /*
                     * We deliberately reuse the already
                     * validated preview population and then
                     * fetch exact allocation detail through
                     * the existing public preview method.
                     */
                    $allocationPreview =
                        $this->royalties
                            ->previewImportOwnerAllocations(
                                $reportImportId,
                                $ownerType,
                                $ownerId,
                                0.0
                            );

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
                                [$currency]
                            )
                            ->pluck('id')
                            ->map(
                                fn ($id) =>
                                    (int) $id
                            )
                            ->flip();

                    $allocations =
                        array_values(
                            array_filter(
                                $allocationPreview[
                                    'allocations'
                                ] ?? [],
                                function (
                                    $allocation
                                ) use (
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
                        throw new RuntimeException(
                            'Posting has no allocations: '
                            .$postingKey
                        );
                    }

                    /*
                     * ADDITIVE INSERT.
                     *
                     * Never delete existing allocations.
                     * Unique(statement_id, report_row_id)
                     * is the DB-level duplicate guard.
                     */
                    $inserted = 0;

                    foreach (
                        array_chunk(
                            $allocations,
                            500
                        )
                        as $chunk
                    ) {
                        foreach (
                            $chunk
                            as $allocation
                        ) {
                            $exists =
                                DB::table(
                                    'royalty_allocations'
                                )
                                    ->where(
                                        'royalty_statement_id',
                                        $statement->id
                                    )
                                    ->where(
                                        'report_row_id',
                                        $allocation[
                                            'report_row_id'
                                        ]
                                    )
                                    ->exists();

                            if ($exists) {
                                continue;
                            }

                            DB::table(
                                'royalty_allocations'
                            )->insert([
                                'public_id' =>
                                    (string)
                                    Str::ulid(),

                                'royalty_statement_id' =>
                                    $statement->id,

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
                            ]);

                            $inserted++;
                        }
                    }

                    /*
                     * Recompute statement totals from ALL
                     * allocations, including older imports.
                     */
                    $totals =
                        DB::table(
                            'royalty_allocations'
                        )
                            ->where(
                                'royalty_statement_id',
                                $statement->id
                            )
                            ->selectRaw(
                                'COALESCE(SUM(gross_amount),0) '
                                .'as gross_amount, '
                                .'COALESCE(SUM(net_amount),0) '
                                .'as net_amount'
                            )
                            ->first();

                    $gross =
                        round(
                            (float)
                            ($totals->gross_amount ?? 0),
                            8
                        );

                    $net =
                        round(
                            (float)
                            ($totals->net_amount ?? 0),
                            8
                        );

                    $commission =
                        round(
                            $gross - $net,
                            8
                        );

                    DB::table(
                        'royalty_statements'
                    )
                        ->where(
                            'id',
                            $statement->id
                        )
                        ->update([
                            'gross_earnings' =>
                                $gross,

                            'commission_amount' =>
                                $commission,

                            'net_payable' =>
                                $net,

                            /*
                             * Materialization is pre-wallet.
                             * Do not auto-approve here.
                             */
                            'status' =>
                                $statement->status,

                            'updated_at' =>
                                now(),
                        ]);

                    DB::table(
                        'report_import_postings'
                    )
                        ->where(
                            'id',
                            $journal->id
                        )
                        ->update([
                            'status' =>
                                'materialized',

                            'row_count' =>
                                count($allocations),

                            'collected_revenue' =>
                                $posting[
                                    'gross_amount'
                                ],

                            'earnings' =>
                                $posting[
                                    'net_amount'
                                ],

                            'royalty_statement_id' =>
                                $statement->id,

                            'last_error' =>
                                null,

                            'updated_at' =>
                                now(),
                        ]);

                    $materialized[] = [
                        'posting_key' =>
                            $postingKey,

                        'status' =>
                            'materialized',

                        'statement_id' =>
                            $statement->id,

                        'allocation_count' =>
                            count($allocations),

                        'inserted_allocations' =>
                            $inserted,

                        'posting_net' =>
                            $posting[
                                'net_amount'
                            ],

                        'statement_net' =>
                            $net,
                    ];
                }

                $pendingPostingCount =
                    DB::table(
                        'report_import_postings'
                    )
                        ->where(
                            'report_import_id',
                            $reportImportId
                        )
                        ->whereNotIn(
                            'status',
                            [
                                'materialized',
                                'posted',
                                'completed',
                                'held_unclaimed', // MIXXTUNE_MATERIALIZER_HELD_UNCLAIMED_FINAL_V1
                            ]
                        )
                        ->count();

                if ($pendingPostingCount > 0) {
                    throw new RuntimeException(
                        'Import has '
                        .$pendingPostingCount
                        .' incomplete posting journals.'
                    );
                }

                DB::table(
                    'report_import_publications'
                )
                    ->where(
                        'id',
                        $publication->id
                    )
                    ->update([
                        /*
                         * Accounting prepared.
                         * Wallet/publish still pending.
                         */
                        'status' =>
                            'materialized',

                        'posting_count' =>
                            count(
                                $preview['postings']
                            ),

                        'last_error' =>
                            null,

                        'updated_at' =>
                            now(),
                    ]);

                return [
                    'report_import_id' =>
                        $reportImportId,

                    'status' =>
                        'materialized',

                    'posting_count' =>
                        count(
                            $materialized
                        ),

                    'postings' =>
                        $materialized,
                ];
            },
            3
        );
    }
}
