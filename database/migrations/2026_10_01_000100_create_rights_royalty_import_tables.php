<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'rights_royalty_imports',
            function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();

                $table->string('source', 50);
                $table->string('statement_month', 7);
                $table->string('currency', 3)
                    ->default('INR');

                $table->string('original_filename');
                $table->string('stored_path')
                    ->nullable();

                $table->unsignedBigInteger('uploaded_by');

                $table->unsignedInteger('total_rows')
                    ->default(0);

                $table->unsignedInteger('mapped_rows')
                    ->default(0);

                $table->unsignedInteger('unmapped_rows')
                    ->default(0);

                $table->decimal(
                    'gross_amount',
                    20,
                    8
                )->default(0);

                $table->decimal(
                    'mapped_amount',
                    20,
                    8
                )->default(0);

                $table->decimal(
                    'unmapped_amount',
                    20,
                    8
                )->default(0);

                /*
                 * uploaded
                 * processing
                 * review_required
                 * approved
                 * published
                 * failed
                 */
                $table->string('status', 40)
                    ->default('uploaded');

                $table->timestamp('processed_at')
                    ->nullable();

                $table->timestamp('approved_at')
                    ->nullable();

                $table->unsignedBigInteger('approved_by')
                    ->nullable();

                $table->timestamp('published_at')
                    ->nullable();

                $table->unsignedBigInteger('published_by')
                    ->nullable();

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'source',
                    'statement_month',
                ]);

                $table->index([
                    'status',
                    'statement_month',
                ]);
            }
        );

        Schema::create(
            'rights_royalty_rows',
            function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();

                $table->unsignedBigInteger(
                    'rights_royalty_import_id'
                );

                $table->unsignedInteger(
                    'source_row_number'
                );

                /*
                 * Canonical normalized fields.
                 */
                $table->string('isrc', 64)
                    ->nullable();

                $table->string('track_title')
                    ->nullable();

                $table->string('rights_type', 80)
                    ->nullable();

                $table->string('territory', 80)
                    ->nullable();

                $table->decimal(
                    'gross_amount',
                    20,
                    8
                )->default(0);

                /*
                 * Mapping snapshot.
                 */
                $table->unsignedBigInteger('track_id')
                    ->nullable();

                $table->unsignedBigInteger('release_id')
                    ->nullable();

                $table->unsignedBigInteger('label_id')
                    ->nullable();

                $table->unsignedBigInteger('artist_id')
                    ->nullable();

                $table->unsignedBigInteger('user_id')
                    ->nullable();

                /*
                 * Contractual percentage snapshot.
                 */
                $table->decimal(
                    'royalty_percentage',
                    8,
                    4
                )->nullable();

                $table->decimal(
                    'beneficiary_amount',
                    20,
                    8
                )->nullable();

                $table->decimal(
                    'company_amount',
                    20,
                    8
                )->nullable();

                /*
                 * mapped / unmapped / error
                 */
                $table->string('mapping_status', 30)
                    ->default('unmapped');

                $table->string('mapping_reason')
                    ->nullable();

                /*
                 * Original provider row preserved for audit.
                 */
                $table->json('raw_data')
                    ->nullable();

                $table->timestamps();

                $table->foreign(
                    'rights_royalty_import_id',
                    'rr_rows_import_fk'
                )
                    ->references('id')
                    ->on('rights_royalty_imports')
                    ->cascadeOnDelete();

                $table->index(
                    'isrc',
                    'rr_rows_isrc_idx'
                );

                $table->index(
                    [
                        'rights_royalty_import_id',
                        'mapping_status',
                    ],
                    'rr_rows_mapping_idx'
                );

                $table->index(
                    'track_id',
                    'rr_rows_track_idx'
                );

                $table->index(
                    'label_id',
                    'rr_rows_label_idx'
                );

                $table->index(
                    'artist_id',
                    'rr_rows_artist_idx'
                );

                $table->unique(
                    [
                        'rights_royalty_import_id',
                        'source_row_number',
                    ],
                    'rr_rows_source_row_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'rights_royalty_rows'
        );

        Schema::dropIfExists(
            'rights_royalty_imports'
        );
    }
};
