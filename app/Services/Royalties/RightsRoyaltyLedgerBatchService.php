<?php

namespace App\Services\Royalties;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/*
 * FF431_K17B_RIGHTS_LEDGER_BATCH_SERVICE
 *
 * Exact Rights-import operational batch actions.
 *
 * Lifecycle remains:
 *
 * Rights Publish
 *   -> draft ledger
 *   -> batch approve
 *   -> approved ledger
 *   -> batch wallet credit
 *   -> wallet_credited
 *
 * This service NEVER publishes Rights imports and NEVER combines
 * approval + wallet credit into one action.
 */
class RightsRoyaltyLedgerBatchService
{
    public function __construct(
        private readonly WalletPostingService $walletPostingService
    ) {
    }

    private function normalizedImportId(
        string $rightsImportPublicId
    ): string {
        $value = trim($rightsImportPublicId);

        if ($value === '') {
            throw new RuntimeException(
                'Rights royalty import public ID is required.'
            );
        }

        return $value;
    }

    private function baseQuery(
        string $rightsImportPublicId
    ) {
        return DB::table('royalty_ledgers')
            ->where(
                'source_type',
                'rights_royalty_allocation'
            )
            ->where(
                'metadata->rights_royalty_import_public_id',
                $rightsImportPublicId
            );
    }

    public function approveImport(
        string $rightsImportPublicId,
        int $userId
    ): array {
        $rightsImportPublicId =
            $this->normalizedImportId(
                $rightsImportPublicId
            );

        return DB::transaction(
            function () use (
                $rightsImportPublicId,
                $userId
            ): array {
                /*
                 * Lock the exact Rights-import batch.
                 * No other source_type or import can enter this set.
                 */
                $rows = $this
                    ->baseQuery($rightsImportPublicId)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get([
                        'id',
                        'status',
                    ]);

                if ($rows->isEmpty()) {
                    throw new RuntimeException(
                        'No royalty ledgers found for this Rights import.'
                    );
                }

                $invalid = $rows->filter(
                    fn ($row) =>
                        ! in_array(
                            (string) $row->status,
                            [
                                'draft',
                                'posted',
                                'approved',
                            ],
                            true
                        )
                );

                if ($invalid->isNotEmpty()) {
                    throw new RuntimeException(
                        'Rights import contains ledger(s) that cannot be approved.'
                    );
                }

                $eligibleIds = $rows
                    ->filter(
                        fn ($row) =>
                            in_array(
                                (string) $row->status,
                                ['draft', 'posted'],
                                true
                            )
                    )
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values();

                $alreadyApproved =
                    $rows->where(
                        'status',
                        'approved'
                    )->count();

                if ($eligibleIds->isNotEmpty()) {
                    DB::table('royalty_ledgers')
                        ->whereIn(
                            'id',
                            $eligibleIds->all()
                        )
                        ->update([
                            'status' => 'approved',
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                }

                return [
                    'rights_import' =>
                        $rightsImportPublicId,

                    'total' =>
                        $rows->count(),

                    'approved' =>
                        $eligibleIds->count(),

                    'already_approved' =>
                        $alreadyApproved,
                ];
            }
        );
    }

    public function creditImport(
        string $rightsImportPublicId,
        int $userId
    ): array {
        $rightsImportPublicId =
            $this->normalizedImportId(
                $rightsImportPublicId
            );

        /*
         * Snapshot IDs only.
         *
         * WalletPostingService owns the transaction + row-lock contract
         * for each ledger. Do NOT wrap the complete batch in one giant
         * wallet transaction.
         */
        $rows = $this
            ->baseQuery($rightsImportPublicId)
            ->orderBy('id')
            ->get([
                'id',
                'status',
            ]);

        if ($rows->isEmpty()) {
            throw new RuntimeException(
                'No royalty ledgers found for this Rights import.'
            );
        }

        $invalid = $rows->filter(
            fn ($row) =>
                ! in_array(
                    (string) $row->status,
                    [
                        'approved',
                        'wallet_credited',
                    ],
                    true
                )
        );

        if ($invalid->isNotEmpty()) {
            throw new RuntimeException(
                'Rights import contains ledger(s) that are not approved for wallet credit.'
            );
        }

        $eligibleIds = $rows
            ->where('status', 'approved')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $alreadyCredited =
            $rows->where(
                'status',
                'wallet_credited'
            )->count();

        $credited = 0;

        foreach ($eligibleIds as $ledgerId) {
            /*
             * Re-verify exact batch membership immediately before
             * each wallet mutation. This prevents a stale snapshot
             * from widening the batch.
             */
            $belongs = $this
                ->baseQuery($rightsImportPublicId)
                ->where('id', $ledgerId)
                ->exists();

            if (! $belongs) {
                throw new RuntimeException(
                    'Royalty ledger no longer belongs to the requested Rights import.'
                );
            }

            $this->walletPostingService
                ->creditLedger(
                    $ledgerId,
                    $userId
                );

            $credited++;
        }

        return [
            'rights_import' =>
                $rightsImportPublicId,

            'total' =>
                $rows->count(),

            'credited' =>
                $credited,

            'already_credited' =>
                $alreadyCredited,
        ];
    }
}
