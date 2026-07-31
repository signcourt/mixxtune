<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('royalty_ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('royalty_ledgers', 'label_id')) {
                $table->foreignId('label_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('labels')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn(
                'royalty_ledgers',
                'revenue_label_mapping_id'
            )) {
                $table->foreignId('revenue_label_mapping_id')
                    ->nullable()
                    ->after('label_id')
                    ->constrained('revenue_label_mappings')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('royalty_ledgers', function (Blueprint $table) {
            if (Schema::hasColumn(
                'royalty_ledgers',
                'revenue_label_mapping_id'
            )) {
                $table->dropConstrainedForeignId(
                    'revenue_label_mapping_id'
                );
            }

            if (Schema::hasColumn('royalty_ledgers', 'label_id')) {
                $table->dropConstrainedForeignId('label_id');
            }
        });
    }
};
