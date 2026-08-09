<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('labels')
            && ! Schema::hasColumn('labels', 'user_id')
        ) {
            Schema::table('labels', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('public_id')
                    ->constrained('users')
                    ->nullOnDelete();

                $table->index(
                    ['user_id', 'status'],
                    'labels_user_status_index'
                );
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('labels')
            && Schema::hasColumn('labels', 'user_id')
        ) {
            Schema::table('labels', function (Blueprint $table) {
                $table->dropIndex(
                    'labels_user_status_index'
                );

                $table->dropForeign(['user_id']);

                $table->dropColumn('user_id');
            });
        }
    }
};
