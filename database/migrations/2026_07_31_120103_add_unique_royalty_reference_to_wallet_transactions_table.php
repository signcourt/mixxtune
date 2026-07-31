<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->index(
                [
                    'reference_type',
                    'reference_id',
                    'transaction_type',
                    'direction',
                ],
                'wallet_transactions_reference_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(
                'wallet_transactions_reference_lookup'
            );
        });
    }
};
