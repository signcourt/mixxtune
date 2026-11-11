<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W143: expected total data-row count, set once at the start of processing, so the
   UI can show real import progress (processed of expected). Nullable; nothing else changes. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_imports')) {
            return;
        }

        if (! Schema::hasColumn('report_imports', 'expected_rows')) {
            Schema::table('report_imports', function (Blueprint $table) {
                $table->unsignedInteger('expected_rows')->nullable()->after('imported_rows');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('report_imports') && Schema::hasColumn('report_imports', 'expected_rows')) {
            Schema::table('report_imports', function (Blueprint $table) {
                $table->dropColumn('expected_rows');
            });
        }
    }
};
