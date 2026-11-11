<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * IPRS Publishing Royalty module - Phase 1.
 *
 * MIXX TUNE is the publisher (role "E") on IPRS statements. These three tables
 * hold the imported statements, the works seen across statements, and the
 * individual publisher royalty lines. Mapping to the catalogue, splitting to
 * clients and statements come in later phases; the nullable mapping columns on
 * iprs_royalty_lines are placeholders those phases fill in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iprs_uploads', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->string('file_name');
            // Hash of the parsed royalty lines (work no + source + amount) - used
            // to reject an identical statement that was already imported.
            $table->string('file_hash', 64);
            $table->string('stored_path')->nullable();

            $table->unsignedBigInteger('uploaded_by');

            // Member block read from the top of the IPRS sheet.
            $table->string('member_internal_no', 60)->nullable();
            $table->string('member_name')->nullable();
            $table->string('ipi_name_no', 60)->nullable();
            $table->string('ipi_base_no', 60)->nullable();

            $table->decimal('file_total', 14, 4)->default(0);
            $table->decimal('parsed_total', 14, 4)->default(0);
            $table->unsignedInteger('total_lines')->default(0);
            $table->unsignedInteger('total_works')->default(0);

            // imported | needs_review | mapped | distributed | reversed
            $table->string('status', 30)->default('imported');

            $table->string('period_label', 80)->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('file_hash', 'iprs_uploads_hash_idx');
            $table->index('status', 'iprs_uploads_status_idx');
        });

        Schema::create('iprs_works', function (Blueprint $table) {
            $table->id();
            $table->string('work_int_no', 40)->unique();
            $table->string('title')->nullable();
            $table->string('language', 120)->nullable();
            // [{name, role, society, own_pct, coll_pct}, ...]
            $table->json('credits')->nullable();
            $table->timestamps();
        });

        Schema::create('iprs_royalty_lines', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->unsignedBigInteger('iprs_upload_id');

            $table->string('work_int_no', 40);
            $table->string('title')->nullable();

            $table->string('pool', 120)->nullable();
            $table->string('source', 160)->nullable();
            // Parsed from the SOURCE text, e.g. "MUSERK JULY" -> 7. Nullable.
            $table->unsignedTinyInteger('source_month')->nullable();

            $table->decimal('own_pct', 8, 4)->nullable();
            $table->decimal('coll_pct', 8, 4)->nullable();

            $table->decimal('amount', 14, 4)->default(0);

            // matched | unmatched | ignored  (Phase 2 confirms these)
            $table->string('mapping_status', 20)->default('unmatched');

            // Catalogue mapping snapshot - filled from Phase 2 onwards.
            $table->unsignedBigInteger('track_id')->nullable();
            $table->unsignedBigInteger('release_id')->nullable();
            $table->string('isrc', 64)->nullable();
            $table->string('upc', 64)->nullable();
            $table->unsignedBigInteger('client_id')->nullable();

            // Original provider row(s) preserved for audit.
            $table->json('raw')->nullable();

            $table->timestamps();

            $table->foreign('iprs_upload_id', 'iprs_lines_upload_fk')
                ->references('id')
                ->on('iprs_uploads')
                ->cascadeOnDelete();

            $table->index('iprs_upload_id', 'iprs_lines_upload_idx');
            $table->index('work_int_no', 'iprs_lines_work_idx');
            $table->index('mapping_status', 'iprs_lines_mapping_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iprs_royalty_lines');
        Schema::dropIfExists('iprs_works');
        Schema::dropIfExists('iprs_uploads');
    }
};
