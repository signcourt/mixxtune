<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'report_imports',
            function (Blueprint $table) {
                $table
                    ->string(
                        'reporting_month',
                        7
                    )
                    ->nullable()
                    ->after(
                        'original_filename'
                    )
                    ->index();
            }
        );

        Schema::table(
            'report_rows',
            function (Blueprint $table) {
                $table
                    ->string(
                        'reporting_month',
                        7
                    )
                    ->nullable()
                    ->after(
                        'report_import_id'
                    )
                    ->index();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'report_rows',
            function (Blueprint $table) {
                $table->dropIndex([
                    'reporting_month',
                ]);

                $table->dropColumn(
                    'reporting_month'
                );
            }
        );

        Schema::table(
            'report_imports',
            function (Blueprint $table) {
                $table->dropIndex([
                    'reporting_month',
                ]);

                $table->dropColumn(
                    'reporting_month'
                );
            }
        );
    }
};
