<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'payout_profiles',
            function (Blueprint $table) {
                $table
                    ->text('bank_account_number')
                    ->nullable()
                    ->change();

                $table
                    ->text('pan_number')
                    ->nullable()
                    ->change();
            }
        );
    }

    public function down(): void
    {
        /*
         * Intentionally keep these fields nullable.
         *
         * Existing payout profiles may legitimately use
         * UPI without bank/PAN data. Making them NOT NULL
         * again could make rollback unsafe.
         */
    }
};
