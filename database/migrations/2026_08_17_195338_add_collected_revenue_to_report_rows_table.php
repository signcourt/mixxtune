<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'report_rows',
            function (Blueprint $table) {
                $table
                    ->decimal(
                        'collected_revenue',
                        20,
                        8
                    )
                    ->nullable()
                    ->after('label_rate');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'report_rows',
            function (Blueprint $table) {
                $table->dropColumn(
                    'collected_revenue'
                );
            }
        );
    }
};
