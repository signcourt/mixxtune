<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('royalty_ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('royalty_ledgers', 'ledger_number')) {
                $table->string('ledger_number', 40)
                    ->nullable()
                    ->unique()
                    ->after('public_id');
            }

            if (!Schema::hasColumn('royalty_ledgers', 'reporting_month')) {
                $table->date('reporting_month')
                    ->nullable()
                    ->after('statement_month')
                    ->index();
            }

            if (!Schema::hasColumn('royalty_ledgers', 'sale_month')) {
                $table->date('sale_month')
                    ->nullable()
                    ->after('reporting_month')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('royalty_ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('royalty_ledgers', 'sale_month')) {
                $table->dropColumn('sale_month');
            }

            if (Schema::hasColumn('royalty_ledgers', 'reporting_month')) {
                $table->dropColumn('reporting_month');
            }

            if (Schema::hasColumn('royalty_ledgers', 'ledger_number')) {
                $table->dropUnique(
                    'royalty_ledgers_ledger_number_unique'
                );

                $table->dropColumn('ledger_number');
            }
        });
    }
};
