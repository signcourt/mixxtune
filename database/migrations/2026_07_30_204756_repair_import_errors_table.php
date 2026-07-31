<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_errors', function (Blueprint $table) {
            if (!Schema::hasColumn('import_errors', 'revenue_import_id')) {
                $table->foreignId('revenue_import_id')
                    ->after('id')
                    ->constrained('revenue_imports')
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('import_errors', 'revenue_row_id')) {
                $table->foreignId('revenue_row_id')
                    ->nullable()
                    ->constrained('revenue_rows')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('import_errors', 'source_row_number')) {
                $table->unsignedBigInteger('source_row_number')->nullable();
            }

            if (!Schema::hasColumn('import_errors', 'error_type')) {
                $table->string('error_type', 100)->index();
            }

            if (!Schema::hasColumn('import_errors', 'message')) {
                $table->text('message');
            }

            if (!Schema::hasColumn('import_errors', 'row_data')) {
                $table->json('row_data')->nullable();
            }

            if (!Schema::hasColumn('import_errors', 'is_resolved')) {
                $table->boolean('is_resolved')
                    ->default(false)
                    ->index();
            }

            if (!Schema::hasColumn('import_errors', 'resolved_by')) {
                $table->foreignId('resolved_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('import_errors', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Production safety.
    }
};
