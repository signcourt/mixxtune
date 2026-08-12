<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            /*
             * Historical versions recorded payment twice:
             *
             * 1. withdrawal_hold — canonical monetary debit
             * 2. withdrawal_paid — duplicate debit with no
             *    additional available-balance movement
             *
             * Remove only duplicate settlement rows whose
             * canonical posted hold is demonstrably present.
             */

            $duplicateIds = DB::table(
                'wallet_transactions as paid'
            )
                ->join(
                    'withdrawals as wd',
                    'wd.id',
                    '=',
                    'paid.reference_id'
                )
                ->where(
                    'paid.reference_type',
                    'withdrawal'
                )
                ->where(
                    'paid.transaction_type',
                    'withdrawal_paid'
                )
                ->where(
                    'paid.direction',
                    'debit'
                )
                ->where(
                    'paid.status',
                    'posted'
                )
                ->where(
                    'wd.status',
                    'paid'
                )
                ->whereExists(function ($query) {
                    $query
                        ->selectRaw('1')
                        ->from(
                            'wallet_transactions as hold'
                        )
                        ->whereColumn(
                            'hold.wallet_id',
                            'paid.wallet_id'
                        )
                        ->whereColumn(
                            'hold.reference_id',
                            'paid.reference_id'
                        )
                        ->where(
                            'hold.reference_type',
                            'withdrawal'
                        )
                        ->where(
                            'hold.transaction_type',
                            'withdrawal_hold'
                        )
                        ->where(
                            'hold.direction',
                            'debit'
                        )
                        ->where(
                            'hold.status',
                            'posted'
                        );
                })
                ->pluck('paid.id');

            if ($duplicateIds->isEmpty()) {
                return;
            }

            DB::table('wallet_transactions')
                ->whereIn(
                    'id',
                    $duplicateIds->all()
                )
                ->delete();
        });
    }

    public function down(): void
    {
        /*
         * Intentionally irreversible.
         *
         * Recreating duplicate monetary ledger entries
         * would corrupt withdrawal debit reporting.
         */
    }
};
