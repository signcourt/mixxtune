<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = Schema::getColumnListing('revenue_imports');

        Schema::table('revenue_imports', function (Blueprint $table) use ($columns) {
            if (!in_array('dsp_name', $columns, true)) {
                $table->string('dsp_name', 120)->nullable()->index();
            }

            if (!in_array('statement_month', $columns, true)) {
                $table->date('statement_month')->nullable()->index();
            }

            if (!in_array('currency', $columns, true)) {
                $table->char('currency', 3)->default('INR');
            }

            if (!in_array('original_filename', $columns, true)) {
                $table->string('original_filename')->nullable();
            }

            if (!in_array('stored_file_path', $columns, true)) {
                $table->string('stored_file_path')->nullable();
            }

            if (!in_array('file_hash', $columns, true)) {
                $table->string('file_hash', 64)->nullable()->index();
            }

            if (!in_array('total_rows', $columns, true)) {
                $table->unsignedBigInteger('total_rows')->default(0);
            }

            if (!in_array('matched_rows', $columns, true)) {
                $table->unsignedBigInteger('matched_rows')->default(0);
            }

            if (!in_array('unmatched_rows', $columns, true)) {
                $table->unsignedBigInteger('unmatched_rows')->default(0);
            }

            if (!in_array('duplicate_rows', $columns, true)) {
                $table->unsignedBigInteger('duplicate_rows')->default(0);
            }

            if (!in_array('error_rows', $columns, true)) {
                $table->unsignedBigInteger('error_rows')->default(0);
            }

            if (!in_array('gross_revenue', $columns, true)) {
                $table->decimal('gross_revenue', 20, 8)->default(0);
            }

            if (!in_array('net_revenue', $columns, true)) {
                $table->decimal('net_revenue', 20, 8)->default(0);
            }

            if (!in_array('status', $columns, true)) {
                $table->string('status', 30)->default('uploaded')->index();
            }

            if (!in_array('failure_reason', $columns, true)) {
                $table->text('failure_reason')->nullable();
            }

            if (!in_array('column_mapping', $columns, true)) {
                $table->json('column_mapping')->nullable();
            }

            if (!in_array('metadata', $columns, true)) {
                $table->json('metadata')->nullable();
            }

            if (!in_array('imported_by', $columns, true)) {
                $table->foreignId('imported_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!in_array('processing_started_at', $columns, true)) {
                $table->timestamp('processing_started_at')->nullable();
            }

            if (!in_array('completed_at', $columns, true)) {
                $table->timestamp('completed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Production data safe रखने के लिए intentionally empty.
    }
};
