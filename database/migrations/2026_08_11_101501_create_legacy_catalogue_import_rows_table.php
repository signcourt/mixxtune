<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'legacy_catalogue_import_rows',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'legacy_catalogue_import_id'
                )
                    ->constrained(
                        'legacy_catalogue_imports'
                    )
                    ->cascadeOnDelete();

                $table->unsignedInteger('row_number');

                /*
                 * Original catalogue metadata.
                 *
                 * IMPORTANT:
                 * Legacy imports intentionally contain
                 * NO AUDIO fields.
                 */
                $table->string('label_name')
                    ->nullable();

                $table->string('release_title')
                    ->nullable();

                $table->string('release_type')
                    ->nullable();

                $table->string('primary_artist')
                    ->nullable();

                $table->text('featuring_artists')
                    ->nullable();

                $table->string('track_title')
                    ->nullable();

                $table->unsignedSmallInteger(
                    'disc_number'
                )->default(1);

                $table->unsignedSmallInteger(
                    'track_number'
                )->default(1);

                $table->string('isrc', 32)
                    ->nullable()
                    ->index();

                $table->string('upc', 32)
                    ->nullable()
                    ->index();

                $table->string('language')
                    ->nullable();

                $table->string('primary_genre')
                    ->nullable();

                $table->string('sub_genre')
                    ->nullable();

                $table->date('original_release_date')
                    ->nullable();

                $table->date('digital_release_date')
                    ->nullable();

                $table->string('copyright_owner')
                    ->nullable();

                $table->unsignedSmallInteger(
                    'copyright_year'
                )->nullable();

                $table->string('phonographic_owner')
                    ->nullable();

                $table->unsignedSmallInteger(
                    'phonographic_year'
                )->nullable();

                $table->string('artwork_filename')
                    ->nullable();

                $table->string('territory')
                    ->nullable();

                /*
                 * Validation state.
                 *
                 * Missing ISRC/UPC blocks import.
                 * Identifiers are NEVER auto-generated
                 * by the legacy importer.
                 */
                $table->string('validation_status')
                    ->default('pending')
                    ->index();

                $table->json('validation_errors')
                    ->nullable();

                $table->json('validation_warnings')
                    ->nullable();

                /*
                 * Resolved destination records.
                 */
                $table->foreignId('resolved_label_id')
                    ->nullable()
                    ->constrained('labels')
                    ->nullOnDelete();

                $table->foreignId('resolved_artist_id')
                    ->nullable()
                    ->constrained('artists')
                    ->nullOnDelete();

                $table->foreignId('release_id')
                    ->nullable()
                    ->constrained('releases')
                    ->nullOnDelete();

                $table->foreignId('track_id')
                    ->nullable()
                    ->constrained('tracks')
                    ->nullOnDelete();

                $table->string('import_status')
                    ->default('pending')
                    ->index();

                $table->text('import_error')
                    ->nullable();

                $table->json('raw_data')
                    ->nullable();

                $table->timestamp('imported_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'legacy_catalogue_import_id',
                        'row_number',
                    ],
                    'legacy_catalogue_import_row_unique'
                );

                $table->index(
                    [
                        'legacy_catalogue_import_id',
                        'validation_status',
                    ],
                    'legacy_catalogue_validation_idx'
                );

                $table->index(
                    [
                        'legacy_catalogue_import_id',
                        'import_status',
                    ],
                    'legacy_catalogue_import_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'legacy_catalogue_import_rows'
        );
    }
};
