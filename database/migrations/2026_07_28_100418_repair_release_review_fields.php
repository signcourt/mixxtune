<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            if (!Schema::hasColumn('releases', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('status');
            }

            if (!Schema::hasColumn('releases', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('review_notes');
            }

            if (!Schema::hasColumn('releases', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }

            if (!Schema::hasColumn('releases', 'approved_by')) {
                $table->foreignId('approved_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('releases', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }

            if (!Schema::hasColumn('releases', 'rejected_by')) {
                $table->foreignId('rejected_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            if (Schema::hasColumn('releases', 'approved_by')) {
                $table->dropForeign(['approved_by']);
            }

            if (Schema::hasColumn('releases', 'rejected_by')) {
                $table->dropForeign(['rejected_by']);
            }

            $columns = array_filter([
                Schema::hasColumn('releases', 'review_notes')
                    ? 'review_notes'
                    : null,
                Schema::hasColumn('releases', 'rejection_reason')
                    ? 'rejection_reason'
                    : null,
                Schema::hasColumn('releases', 'approved_at')
                    ? 'approved_at'
                    : null,
                Schema::hasColumn('releases', 'approved_by')
                    ? 'approved_by'
                    : null,
                Schema::hasColumn('releases', 'rejected_at')
                    ? 'rejected_at'
                    : null,
                Schema::hasColumn('releases', 'rejected_by')
                    ? 'rejected_by'
                    : null,
            ]);

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
