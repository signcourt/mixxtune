<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_errors', function (Blueprint $table) {
            $table->foreignId('revenue_import_id')
                ->after('id')
                ->constrained('revenue_imports')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('revenue_row_id')
                ->nullable()
                ->after('revenue_import_id');

            $table->unsignedInteger('source_row_number')
                ->nullable()
                ->after('revenue_row_id');

            $table->string('error_type', 100)
                ->after('source_row_number');

            $table->text('message')
                ->after('error_type');

            $table->json('row_data')
                ->nullable()
                ->after('message');

            $table->boolean('is_resolved')
                ->default(false)
                ->after('row_data');

            $table->foreignId('resolved_by')
                ->nullable()
                ->after('is_resolved')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('resolved_at')
                ->nullable()
                ->after('resolved_by');

            $table->index(
                ['revenue_import_id', 'is_resolved'],
                'import_errors_import_resolved_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('import_errors', function (Blueprint $table) {
            $table->dropForeign(['revenue_import_id']);
            $table->dropForeign(['resolved_by']);
            $table->dropIndex('import_errors_import_resolved_index');

            $table->dropColumn([
                'revenue_import_id',
                'revenue_row_id',
                'source_row_number',
                'error_type',
                'message',
                'row_data',
                'is_resolved',
                'resolved_by',
                'resolved_at',
            ]);
        });
    }
};
