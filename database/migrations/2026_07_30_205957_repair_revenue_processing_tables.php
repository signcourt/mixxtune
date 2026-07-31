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
                $table->boolean('is_resolved')->default(false)->index();
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

        Schema::table('revenue_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('revenue_matches', 'revenue_row_id')) {
                $table->foreignId('revenue_row_id')
                    ->after('id')
                    ->constrained('revenue_rows')
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('revenue_matches', 'track_id')) {
                $table->foreignId('track_id')
                    ->nullable()
                    ->constrained('tracks')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('revenue_matches', 'release_id')) {
                $table->foreignId('release_id')
                    ->nullable()
                    ->constrained('releases')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('revenue_matches', 'artist_id')) {
                $table->foreignId('artist_id')
                    ->nullable()
                    ->constrained('artists')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('revenue_matches', 'label_id')) {
                $table->foreignId('label_id')
                    ->nullable()
                    ->constrained('labels')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('revenue_matches', 'match_type')) {
                $table->string('match_type', 40)
                    ->default('unmatched')
                    ->index();
            }

            if (!Schema::hasColumn('revenue_matches', 'confidence')) {
                $table->decimal('confidence', 5, 2)->default(0);
            }

            if (!Schema::hasColumn('revenue_matches', 'is_confirmed')) {
                $table->boolean('is_confirmed')->default(false)->index();
            }

            if (!Schema::hasColumn('revenue_matches', 'confirmed_by')) {
                $table->foreignId('confirmed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('revenue_matches', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable();
            }

            if (!Schema::hasColumn('revenue_matches', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Production data safety.
    }
};
