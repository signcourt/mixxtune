<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('report_imports')) {
            return;
        }

        Schema::create(
            'report_imports',
            function (Blueprint $table) {
                $table->id();

                $table->string(
                    'public_id',
                    40
                )->unique();

                $table->string(
                    'original_filename'
                );

                $table->text(
                    'stored_path'
                );

                $table->string(
                    'status',
                    40
                )->default('pending');

                $table->unsignedBigInteger(
                    'total_rows'
                )->default(0);

                $table->unsignedBigInteger(
                    'imported_rows'
                )->default(0);

                $table->unsignedBigInteger(
                    'duplicate_rows'
                )->default(0);

                $table->unsignedBigInteger(
                    'failed_rows'
                )->default(0);

                $table->text(
                    'error_file_path'
                )->nullable();

                $table->json(
                    'column_map'
                )->nullable();

                $table->longText(
                    'error_message'
                )->nullable();

                $table->foreignId(
                    'uploaded_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp(
                    'started_at'
                )->nullable();

                $table->timestamp(
                    'completed_at'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'status',
                    'created_at',
                ]);

                $table->index([
                    'uploaded_by',
                    'created_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        /*
         * Imported report audit records are retained.
         */
    }
};
