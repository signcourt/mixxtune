<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'legacy_catalogue_import_rows',
            function (Blueprint $table) {
                $table->boolean('is_excluded')
                    ->default(false)
                    ->after('import_status');

                $table->text('exclusion_reason')
                    ->nullable()
                    ->after('is_excluded');

                $table->unsignedBigInteger('excluded_by')
                    ->nullable()
                    ->after('exclusion_reason');

                $table->timestamp('excluded_at')
                    ->nullable()
                    ->after('excluded_by');

                $table->index(
                    [
                        'legacy_catalogue_import_id',
                        'is_excluded',
                    ],
                    'legacy_catalogue_import_excluded_idx'
                );

                $table->foreign('excluded_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'legacy_catalogue_import_rows',
            function (Blueprint $table) {
                $table->dropForeign(['excluded_by']);

                $table->dropIndex(
                    'legacy_catalogue_import_excluded_idx'
                );

                $table->dropColumn([
                    'is_excluded',
                    'exclusion_reason',
                    'excluded_by',
                    'excluded_at',
                ]);
            }
        );
    }
};
