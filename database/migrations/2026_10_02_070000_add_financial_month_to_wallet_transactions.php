<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wallet_transactions', 'financial_month')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->date('financial_month')
                    ->nullable()
                    ->after('posted_at');

                $table->index(
                    ['wallet_id', 'financial_month'],
                    'wallet_transactions_wallet_financial_month_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('wallet_transactions', 'financial_month')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->dropIndex(
                    'wallet_transactions_wallet_financial_month_idx'
                );

                $table->dropColumn('financial_month');
            });
        }
    }
};
