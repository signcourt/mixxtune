<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Fresh SQLite test databases already create the payout
         * columns with compatible storage. SQLite table rebuilds
         * for these legacy ->change() operations are unnecessary
         * and can produce an empty temporary-table definition.
         *
         * Production MySQL has already executed this migration.
         */
        if (
            Schema::getConnection()
                ->getDriverName() === 'sqlite'
        ) {
            return;
        }

        Schema::table(
            'payout_profiles',
            function (Blueprint $table) {
                /*
                 * Laravel encrypted casts produce ciphertext
                 * substantially longer than plaintext.
                 */
                $table->text('bank_account_number')
                    ->change();

                $table->text('pan_number')
                    ->change();
            }
        );
    }

    public function down(): void
    {
        /*
         * Intentionally do not automatically shrink encrypted
         * columns because existing ciphertext may be truncated.
         */
    }
};
