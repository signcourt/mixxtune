<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('release_store_deliveries')
            && !Schema::hasColumn('release_store_deliveries', 'delivery_note')
        ) {
            Schema::table('release_store_deliveries', function (Blueprint $table) {
                $table->text('delivery_note')->nullable();
            });
        }

        if (
            Schema::hasTable('release_store_deliveries')
            && !Schema::hasColumn('release_store_deliveries', 'external_reference')
        ) {
            Schema::table('release_store_deliveries', function (Blueprint $table) {
                $table->string('external_reference')->nullable();
            });
        }

        if (
            Schema::hasTable('release_store_deliveries')
            && !Schema::hasColumn('release_store_deliveries', 'taken_down_at')
        ) {
            Schema::table('release_store_deliveries', function (Blueprint $table) {
                $table->timestamp('taken_down_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('release_store_deliveries', function (Blueprint $table) {
            foreach ([
                'delivery_note',
                'external_reference',
                'taken_down_at',
            ] as $column) {
                if (Schema::hasColumn('release_store_deliveries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
