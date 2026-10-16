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
                'report_import_id',
                'report_rows_report_import_id_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('report_rows', function (Blueprint $table) {
            $table->dropIndex(
                'report_rows_report_import_id_index'
            );
        });
    }
};
