<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenue_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('revenue_rows', 'reporting_month')) {
                $table->date('reporting_month')
                    ->nullable()
                    ->after('sale_month')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('revenue_rows', function (Blueprint $table) {
            if (Schema::hasColumn('revenue_rows', 'reporting_month')) {
                $table->dropColumn('reporting_month');
            }
        });
    }
};
