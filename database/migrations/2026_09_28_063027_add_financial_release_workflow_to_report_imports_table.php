<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_imports', function (Blueprint $table) {
            /*
             * Import processing status remains in `status`.
             *
             * Financial workflow is intentionally separate:
             *
             * review_pending
             * approved
             * scheduled
             * published
             */
            $table->string('financial_status', 30)
                ->default('review_pending')
                ->after('status')
                ->index();

            /*
             * User-selected date/time from which this batch
             * becomes financially available.
             */
            $table->timestamp('publish_at')
                ->nullable()
                ->after('financial_status')
                ->index();

            $table->timestamp('reviewed_at')
                ->nullable()
                ->after('publish_at');

            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('reviewed_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')
                ->nullable()
                ->after('reviewed_by');

            $table->foreignId('approved_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('published_at')
                ->nullable()
                ->after('approved_by')
                ->index();

            $table->foreignId('published_by')
                ->nullable()
                ->after('published_at')
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Frozen financial snapshot totals.
             * These are audit/reconciliation values only.
             * report_rows remains the row-level source.
             */
            $table->unsignedBigInteger('review_row_count')
                ->nullable()
                ->after('published_by');

            $table->unsignedBigInteger('review_mapped_rows')
                ->nullable()
                ->after('review_row_count');

            $table->unsignedBigInteger('review_unmapped_rows')
                ->nullable()
                ->after('review_mapped_rows');

            $table->decimal(
                'review_collected_revenue',
                24,
                8
            )
                ->nullable()
                ->after('review_unmapped_rows');

            $table->decimal(
                'review_earnings',
                24,
                8
            )
                ->nullable()
                ->after('review_collected_revenue');

            $table->text('review_note')
                ->nullable()
                ->after('review_earnings');
        });
    }

    public function down(): void
    {
        Schema::table('report_imports', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['published_by']);

            $table->dropIndex(
                'report_imports_financial_status_index'
            );

            $table->dropIndex(
                'report_imports_publish_at_index'
            );

            $table->dropIndex(
                'report_imports_published_at_index'
            );

            $table->dropColumn([
                'financial_status',
                'publish_at',
                'reviewed_at',
                'reviewed_by',
                'approved_at',
                'approved_by',
                'published_at',
                'published_by',
                'review_row_count',
                'review_mapped_rows',
                'review_unmapped_rows',
                'review_collected_revenue',
                'review_earnings',
                'review_note',
            ]);
        });
    }
};
