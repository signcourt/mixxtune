<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_rows', function (Blueprint $table) {
            $table->index(
                ['label_id', 'reporting_month'],
                'report_rows_label_id_reporting_month_index'
            );

            $table->index(
                ['artist_id', 'reporting_month'],
                'report_rows_artist_id_reporting_month_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('report_rows', function (Blueprint $table) {
            $table->dropIndex(
                'report_rows_label_id_reporting_month_index'
            );

            $table->dropIndex(
                'report_rows_artist_id_reporting_month_index'
            );
        });
    }
};
