<?php

namespace App\Services\V2;

use App\Models\Finance\RecoupmentPlan;
use App\Services\V3\RecoupmentManagementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * MIXXTUNE_SCHEDULED_IMPORT_PUBLISHER_V1
 *
 * Exact-import financial publisher.
 *
 * Contract:
 * - approved_scheduled only
 * - publish_at must be due
 * - materialize exact import first
 * - each posting locked/idempotent
 * - recoupment applies only to THIS import delta
 * - wallet becomes available only at publish time
 * - import becomes published only after every posting completes
 */
class ReportImportScheduledPublisher
{
    public function __construct(
        private readonly ReportImportPublicationMaterializer $materializer,
        private readonly RecoupmentManagementService $recoupment
    ) {
    }

    public function publish(int $reportImportId): array
    {
        /*
         * Materializer itself performs its own eligibility checks.
         * It does NOT touch wallet or published status.
         */
        $this->assertDue($reportImportId);

        /*
         * MIXXTUNE_PUBLISHED_IMPORT_SKIPS_MATERIALIZER_V1
         *
         * A published import is final. Never re-enter the
         * materializer: posted / held_unclaimed journals must
         * not be reprocessed by a manual retry.
         */
        $currentFinancialStatus = DB::table('report_imports')
            ->where('id', $reportImportId)
            ->value('financial_status');

        if ((string) $currentFinancialStatus === 'published') {
            return [
                'report_import_id' =>
                    $reportImportId,
                'status' =>
                    'already_published',
            ];
        }

        $this->materializer->materialize(
            $reportImportId
        );

        return DB::transaction(function () use (
            $reportImportId
        ) {
            $import = DB::table('report_imports')
                ->where('id', $reportImportId)
                ->lockForUpdate()
                ->first();

            if (! $import) {
                throw new RuntimeException(
                    'Report import not found.'
                );
            }

            /*
             * Retry after successful publication is a no-op.
             */
            if (
                (string) $import->financial_status ===
                'published'
            ) {
                return [
                    'report_import_id' =>
                        $reportImportId,
                    'status' =>
                        'already_published',
                ];
            }

            if (
                (string) $import->financial_status !==
                'approved_scheduled'
            ) {
                throw new RuntimeException(
                    'Only approved_scheduled import can publish.'
                );
            }

            if (
                empty($import->publish_at)
                || now()->lt($import->publish_at)
            ) {
                throw new RuntimeException(
                    'Import publish date is not due.'
                );
            }

            $publication = DB::table(
                'report_import_publications'
            )
                ->where(
                    'report_import_id',
                    $reportImportId
                )
                ->lockForUpdate()
                ->first();

            if (! $publication) {
                throw new RuntimeException(
                    'Publication journal is missing.'
                );
            }

            $postings = DB::table(
                'report_import_postings'
            )
                ->where(
                    'report_import_id',
                    $reportImportId
                )
                ->orderBy('id')
                ->get();

            if ($postings->isEmpty()) {
                throw new RuntimeException(
                    'No publication postings found.'
                );
            }

            $postedCount = 0;

            foreach ($postings as $postingRow) {
                $this->postOne(
                    $reportImportId,
                    (int) $postingRow->id,
                    $import
                );

                $postedCount++;
            }

            /*
             * Re-check journals after posting.
             */
            $incomplete = DB::table(
                'report_import_postings'
            )
                ->where(
                    'report_import_id',
                    $reportImportId
                )
                ->whereNotIn(
                    'status',
                    [
                        'posted',
                        'completed',
                        'held_unclaimed',
                    ]
                )
                ->count();

            if ($incomplete > 0) {
                throw new RuntimeException(
                    'Publication has incomplete postings.'
                );
            }

            DB::table(
                'report_import_publications'
            )
                ->where('id', $publication->id)
                ->update([
                    'status' =>
                        'completed',
                    'posting_count' =>
                        $postings->count(),
                    'completed_at' =>
                        now(),
                    'failed_at' =>
                        null,
                    'last_error' =>
                        null,
                    'updated_at' =>
                        now(),
                ]);

            /*
             * Visibility flips ONLY after every wallet posting
             * succeeded in this same transaction.
             */
            DB::table('report_imports')
                ->where('id', $reportImportId)
                ->update([
                    'financial_status' =>
                        'published',
                    'published_at' =>
                        now(),
                    'published_by' =>
                        $import->approved_by
                        ?: $import->reviewed_by,
                    'updated_at' =>
                        now(),
                ]);

            return [
                'report_import_id' =>
                    $reportImportId,
                'status' =>
                    'published',
                'posting_count' =>
                    $postedCount,
            ];
        });
    }

    private function assertDue(
        int $reportImportId
    ): void {
        $import = DB::table('report_imports')
            ->where('id', $reportImportId)
            ->first();

        if (! $import) {
            throw new RuntimeException(
                'Report import not found.'
            );
        }

        if (
            (string) $import->financial_status ===
            'published'
        ) {
            return;
        }

        if (
            (string) $import->financial_status !==
            'approved_scheduled'
        ) {
            throw new RuntimeException(
                'Import is not approved_scheduled.'
            );
        }

        if (empty($import->publish_at)) {
            throw new RuntimeException(
                'Import publish_at is missing.'
            );
        }

        if (now()->lt($import->publish_at)) {
            throw new RuntimeException(
                'Import publish date is not due.'
            );
        }
    }

    private function postOne(
        int $reportImportId,
        int $postingId,
        object $import
    ): void {
        $posting = DB::table(
            'report_import_postings'
        )
            ->where('id', $postingId)
            ->lockForUpdate()
            ->first();

        if (! $posting) {
            throw new RuntimeException(
                'Publication posting not found.'
            );
        }

        /*
         * Primary application idempotency guard.
         */
        if (
            in_array(
                    (string) $posting->status,
                    [
                        'posted',
                        'completed',
                        'held_unclaimed',
                    ],
                    true
                )
        ) {
            return;
        }

        if (
            (int) $posting->report_import_id !==
            $reportImportId
        ) {
            throw new RuntimeException(
                'Posting/import mismatch.'
            );
        }

        if (! $posting->royalty_statement_id) {
            throw new RuntimeException(
                'Posting has no royalty statement.'
            );
        }

        $currency = strtoupper(
            trim((string) $posting->currency)
        );

        if ($currency === '') {
            throw new RuntimeException(
                'Posting currency missing.'
            );
        }

        $ownerType = strtolower(
            trim((string) $posting->owner_type)
        );

        $ownerId = (int) $posting->owner_id;

        if (
            ! in_array(
                $ownerType,
                ['label', 'artist'],
                true
            )
            || $ownerId <= 0
        ) {
            throw new RuntimeException(
                'Invalid posting owner.'
            );
        }

        /*
         * Calculate THIS IMPORT'S delta from allocations,
         * never from the consolidated statement total.
         */
        $delta = DB::table(
            'royalty_allocations as ra'
        )
            ->join(
                'report_rows as rr',
                'rr.id',
                '=',
                'ra.report_row_id'
            )
            ->where(
                'ra.royalty_statement_id',
                $posting->royalty_statement_id
            )
            ->where(
                'rr.report_import_id',
                $reportImportId
            )
            ->where(
                'rr.currency',
                $currency
            )
            ->selectRaw(
                'COALESCE(SUM(ra.gross_amount),0) as gross_amount'
            )
            ->selectRaw(
                'COALESCE(SUM(ra.net_amount),0) as net_amount'
            )
            ->first();

        $deltaGross = round(
            (float) ($delta->gross_amount ?? 0),
            8
        );

        $deltaNet = round(
            (float) ($delta->net_amount ?? 0),
            8
        );

        /*
         * Resolve exact beneficiary account.
         */
        $ownerTable =
            $ownerType === 'label'
                ? 'labels'
                : 'artists';

        $owner = DB::table($ownerTable)
            ->where('id', $ownerId)
            ->first();

        if (! $owner) {
            throw new RuntimeException(
                'Posting owner is missing.'
            );
        }

        /*
         * MIXXTUNE_UNCLAIMED_REVENUE_HOLD_V2_IMMUTABLE
         *
         * A valid Label / Artist can exist before a beneficiary User
         * has been attached. Never fabricate identity data and never
         * create a wallet without a real User.
         *
         * The hold journal is immutable financial evidence:
         * - create it once;
         * - on retry, validate the existing row exactly;
         * - never reset claim/wallet/status fields;
         * - preserve existing posting metadata.
         *
         * No recoupment and no wallet mutation occurs on this path.
         */
        if (! $owner->user_id) {
            $holdKey =
                'report_import_posting:'
                .$postingId;

            $holdExpected = [
                'report_import_id' =>
                    $reportImportId,
                'report_import_posting_id' =>
                    $postingId,
                'royalty_statement_id' =>
                    (int) $posting->royalty_statement_id,
                'owner_type' =>
                    $ownerType,
                'owner_id' =>
                    $ownerId,
                'currency' =>
                    $currency,
                'gross_amount' =>
                    number_format(
                        $deltaGross,
                        8,
                        '.',
                        ''
                    ),
                'net_amount' =>
                    number_format(
                        $deltaNet,
                        8,
                        '.',
                        ''
                    ),
            ];

            $existingHold = DB::table(
                'report_unclaimed_revenues'
            )
                ->where('hold_key', $holdKey)
                ->lockForUpdate()
                ->first();

            if (! $existingHold) {
                DB::table(
                    'report_unclaimed_revenues'
                )->insert([
                    'hold_key' =>
                        $holdKey,
                    'report_import_id' =>
                        $holdExpected[
                            'report_import_id'
                        ],
                    'report_import_posting_id' =>
                        $holdExpected[
                            'report_import_posting_id'
                        ],
                    'royalty_statement_id' =>
                        $holdExpected[
                            'royalty_statement_id'
                        ],
                    'owner_type' =>
                        $holdExpected['owner_type'],
                    'owner_id' =>
                        $holdExpected['owner_id'],
                    'currency' =>
                        $holdExpected['currency'],
                    'gross_amount' =>
                        $holdExpected['gross_amount'],
                    'net_amount' =>
                        $holdExpected['net_amount'],
                    'status' =>
                        'held',
                    'claimed_by_user_id' =>
                        null,
                    'wallet_id' =>
                        null,
                    'wallet_transaction_id' =>
                        null,
                    'claimed_at' =>
                        null,
                    'metadata' =>
                        json_encode([
                            'reason' =>
                                'owner_user_missing',
                            'reporting_month' =>
                                $import->reporting_month,
                        ]),
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

                $existingHold = DB::table(
                    'report_unclaimed_revenues'
                )
                    ->where(
                        'hold_key',
                        $holdKey
                    )
                    ->lockForUpdate()
                    ->first();
            }

            if (! $existingHold) {
                throw new RuntimeException(
                    'Unclaimed revenue hold was not created.'
                );
            }

            $holdActual = [
                'report_import_id' =>
                    (int) $existingHold
                        ->report_import_id,
                'report_import_posting_id' =>
                    (int) $existingHold
                        ->report_import_posting_id,
                'royalty_statement_id' =>
                    (int) $existingHold
                        ->royalty_statement_id,
                'owner_type' =>
                    (string) $existingHold
                        ->owner_type,
                'owner_id' =>
                    (int) $existingHold
                        ->owner_id,
                'currency' =>
                    (string) $existingHold
                        ->currency,
                'gross_amount' =>
                    number_format(
                        (float) $existingHold
                            ->gross_amount,
                        8,
                        '.',
                        ''
                    ),
                'net_amount' =>
                    number_format(
                        (float) $existingHold
                            ->net_amount,
                        8,
                        '.',
                        ''
                    ),
            ];

            if ($holdActual !== $holdExpected) {
                throw new RuntimeException(
                    'Existing unclaimed revenue hold '
                    .'does not match posting.'
                );
            }

            /*
             * A previously claimed/released hold must never be
             * silently reset by a publication retry.
             */
            if (
                (string) $existingHold->status !==
                    'held'
                || $existingHold->claimed_by_user_id
                || $existingHold->wallet_id
                || $existingHold
                    ->wallet_transaction_id
                || $existingHold->claimed_at
            ) {
                throw new RuntimeException(
                    'Existing unclaimed revenue hold '
                    .'is no longer claimable by publisher.'
                );
            }

            $postingMetadata = json_decode(
                (string) ($posting->metadata ?? ''),
                true
            );

            if (! is_array($postingMetadata)) {
                $postingMetadata = [];
            }

            $postingMetadata['reporting_month'] =
                $import->reporting_month;

            $postingMetadata['unclaimed_hold_key'] =
                $holdKey;

            $postingMetadata['unclaimed_reason'] =
                'owner_user_missing';

            DB::table(
                'report_import_postings'
            )
                ->where('id', $postingId)
                ->update([
                    'status' =>
                        'held_unclaimed',
                    'wallet_id' =>
                        null,
                    'wallet_transaction_id' =>
                        null,
                    'posted_at' =>
                        null,
                    'last_error' =>
                        null,
                    'metadata' =>
                        json_encode(
                            $postingMetadata
                        ),
                    'updated_at' =>
                        now(),
                ]);

            return;
        }

        $userId = (int) $owner->user_id;

        /*
         * MIXXTUNE_ACTIVE_FINANCIAL_OWNER_PUBLISH_GATE
         *
         * Approval validates the financial owner before scheduling,
         * but account state can change before publication. Re-resolve
         * the User here before any recoupment or wallet mutation.
         */
        $ownerUser = DB::table('users')
            ->where('id', $userId)
            ->first();

        if (! $ownerUser) {
            throw new RuntimeException(
                'Posting owner user is missing.'
            );
        }

        if (
            ! isset($ownerUser->account_status)
            || $ownerUser->account_status !== 'active'
        ) {
            throw new RuntimeException(
                'Posting owner user is not active.'
            );
        }

        /*
         * Recoupment only applies to positive payable.
         * Negative report adjustments remain authoritative.
         */
        $recovery = null;
        $recoveryAmount = 0.0;

        if ($deltaNet > 0) {
            $planQuery = RecoupmentPlan::query()
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->where(
                    'outstanding_amount',
                    '>',
                    0
                );

            if ($ownerType === 'label') {
                $plan = (clone $planQuery)
                    ->where('label_id', $ownerId)
                    ->whereNull('artist_id')
                    ->first();

                if (! $plan) {
                    $plan = (clone $planQuery)
                        ->whereNull('label_id')
                        ->whereNull('artist_id')
                        ->first();
                }
            } else {
                $plan = (clone $planQuery)
                    ->where('artist_id', $ownerId)
                    ->whereNull('label_id')
                    ->first();

                if (! $plan) {
                    $plan = (clone $planQuery)
                        ->whereNull('label_id')
                        ->whereNull('artist_id')
                        ->first();
                }
            }

            if (isset($plan) && $plan) {
                $recovery =
                    $this->recoupment
                        ->applyStatementRecovery(
                            $plan,
                            $deltaGross,
                            $deltaNet,
                            [
                                'royalty_statement_id' =>
                                    (int)
                                    $posting
                                        ->royalty_statement_id,

                                'reporting_month' =>
                                    $import
                                        ->reporting_month,

                                'reference' =>
                                    'Report import '
                                    .$reportImportId
                                    .' posting '
                                    .$postingId,

                                'idempotency_key' =>
                                    'report_import_posting:'
                                    .$postingId,
                            ]
                        );

                if ($recovery) {
                    $recoveryAmount = round(
                        (float)
                        $recovery
                            ->applied_recovery_amount,
                        8
                    );
                }
            }
        }

        $payable = round(
            $deltaNet - $recoveryAmount,
            8
        );

        /*
         * MIXXTUNE_CANONICAL_USER_WALLET
         *
         * Financial wallets are User-level. Label / Artist remain
         * attribution fields on the posting and transaction, but must
         * not create parallel owner-specific wallet balances.
         */
        $wallet = DB::table('wallets')
            ->where('user_id', $userId)
            ->where('currency', $currency)
            ->whereNull('label_id')
            ->whereNull('artist_id')
            ->lockForUpdate()
            ->first();

        if (! $wallet) {
            $walletId =
                DB::table('wallets')
                    ->insertGetId([
                        'public_id' =>
                            (string) Str::ulid(),
                        'user_id' =>
                            $userId,
                        'artist_id' =>
                            null,
                        'label_id' =>
                            null,
                        'currency' =>
                            $currency,
                        'available_balance' =>
                            0,
                        'pending_balance' =>
                            0,
                        'lifetime_credits' =>
                            0,
                        'lifetime_debits' =>
                            0,
                        'status' =>
                            'active',
                        'created_at' =>
                            now(),
                        'updated_at' =>
                            now(),
                    ]);

            $wallet = DB::table('wallets')
                ->where('id', $walletId)
                ->lockForUpdate()
                ->first();
        }

        /*
         * Posting row is locked. A successful wallet transaction
         * and posting status update occur atomically.
         */
        $walletTransactionId = null;

        if ($payable != 0.0) {
            /*
             * MIXXTUNE_WALLET_DECIMAL_SAFE_V1
             *
             * Never add money in PHP float. The database adds the
             * exact 8dp decimal and balance_after is read back.
             */
            $before = (string) $wallet->available_balance;

            $payableSql = number_format($payable, 8, '.', '');

            $after = null;

            $walletUpdate = [
                'available_balance' =>
                    DB::raw(
                        'available_balance + ('
                        .$payableSql
                        .')'
                    ),
                'updated_at' =>
                    now(),
            ];

            if ($payable > 0) {
                $walletUpdate[
                    'lifetime_credits'
                ] = DB::raw(
                    'lifetime_credits + '
                    .number_format(abs($payable), 8, '.', '')
                );
            } else {
                $walletUpdate[
                    'lifetime_debits'
                ] = DB::raw(
                    'lifetime_debits + '
                    .number_format(abs($payable), 8, '.', '')
                );
            }

            DB::table('wallets')
                ->where('id', $wallet->id)
                ->update($walletUpdate);

            $after = (string) DB::table('wallets')
                ->where('id', $wallet->id)
                ->value('available_balance');

            $walletTransactionId =
                DB::table(
                    'wallet_transactions'
                )->insertGetId([
                    'public_id' =>
                        (string) Str::ulid(),
                    'wallet_id' =>
                        $wallet->id,
                    'user_id' =>
                        $userId,
                    'artist_id' =>
                        $ownerType === 'artist'
                            ? $ownerId
                            : null,
                    'label_id' =>
                        $ownerType === 'label'
                            ? $ownerId
                            : null,
                    'transaction_type' =>
                        'royalty_import',
                    'direction' =>
                        $payable > 0
                            ? 'credit'
                            : 'debit',
                    'amount' =>
                        number_format(abs($payable), 8, '.', ''),
                    'currency' =>
                        $currency,
                    'balance_before' =>
                        $before,
                    'balance_after' =>
                        $after,
                    'reference_type' =>
                        'report_import_posting',
                    'reference_id' =>
                        $postingId,
                    'description' =>
                        'Royalty import '
                        .$import->reporting_month,
                    'status' =>
                        'posted',
                    'effective_at' =>
                        now(),
                    'posted_at' =>
                        now(),
                    'metadata' =>
                        json_encode([
                            'report_import_id' =>
                                $reportImportId,
                            'posting_id' =>
                                $postingId,
                            'statement_id' =>
                                (int)
                                $posting
                                    ->royalty_statement_id,
                            'reporting_month' =>
                                $import
                                    ->reporting_month,
                            'publish_at' =>
                                $import->publish_at,
                            'delta_gross' =>
                                $deltaGross,
                            'delta_net' =>
                                $deltaNet,
                            'recovery_amount' =>
                                $recoveryAmount,
                            'payable' =>
                                $payable,
                        ]),
                    'created_by' =>
                        $import->approved_by
                        ?: $import->reviewed_by,
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);
        }

        if (
            $recovery
            && $walletTransactionId
        ) {
            $recovery->update([
                'wallet_transaction_id' =>
                    $walletTransactionId,
            ]);
        }

        /*
         * Consolidated statement remains an audit/report object.
         * Its totals cover all allocations, while deductions
         * accumulate import-by-import.
         */
        if ($recoveryAmount > 0) {
            DB::table('royalty_statements')
                ->where(
                    'id',
                    $posting->royalty_statement_id
                )
                ->update([
                    'other_deductions' =>
                        DB::raw(
                            'other_deductions + '
                            .$recoveryAmount
                        ),
                    'net_payable' =>
                        DB::raw(
                            'GREATEST(0, net_payable - '
                            .$recoveryAmount
                            .')'
                        ),
                    'updated_at' =>
                        now(),
                ]);
        }

        DB::table(
            'report_import_postings'
        )
            ->where('id', $postingId)
            ->update([
                'status' =>
                    'posted',
                'wallet_id' =>
                    $wallet->id,
                'wallet_transaction_id' =>
                    $walletTransactionId,
                'posted_at' =>
                    now(),
                'last_error' =>
                    null,
                'metadata' =>
                    json_encode(array_merge(
                        /* MIXXTUNE_POSTED_METADATA_MERGE_V1 */
                        (function () use ($posting) {
                            $existing = json_decode(
                                (string) ($posting->metadata ?? ''),
                                true
                            );

                            return is_array($existing) ? $existing : [];
                        })(),
                        [
                            'reporting_month' =>
                                $import->reporting_month,
                            'delta_gross' =>
                                $deltaGross,
                            'delta_net' =>
                                $deltaNet,
                            'recovery_amount' =>
                                $recoveryAmount,
                            'wallet_payable' =>
                                $payable,
                        ]
                    )),
                'updated_at' =>
                    now(),
            ]);
    }
}
