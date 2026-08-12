<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * These tables belong to the deprecated parallel finance schema.
         *
         * Canonical tables:
         *   wallet_accounts      => wallets
         *   withdrawal_requests => withdrawals
         *
         * Never silently discard legacy financial data.
         */
        foreach ([
            'withdrawal_requests',
            'wallet_accounts',
        ] as $table) {
            if (
                Schema::hasTable($table)
                && DB::table($table)->exists()
            ) {
                throw new \RuntimeException(
                    "Cannot retire {$table}: table contains data."
                );
            }
        }

        /*
         * withdrawal_requests has a foreign key to wallet_accounts,
         * therefore it must be dropped first.
         */
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('wallet_accounts');
    }

    public function down(): void
    {
        /*
         * Intentionally irreversible.
         *
         * Recreating the deprecated parallel finance schema would allow
         * application data to diverge from canonical wallets/withdrawals.
         */
    }
};
