<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {

            if (!Schema::hasColumn('tracks', 'isrc_assigned_at')) {
                $table->timestamp('isrc_assigned_at')
                    ->nullable()
                    ->after('isrc');
            }

            if (!Schema::hasColumn('tracks', 'isrc_assigned_by')) {
                $table->foreignId('isrc_assigned_by')
                    ->nullable()
                    ->after('isrc_assigned_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {

            if (Schema::hasColumn('tracks','isrc_assigned_by')) {
                $table->dropForeign(['isrc_assigned_by']);
                $table->dropColumn('isrc_assigned_by');
            }

            if (Schema::hasColumn('tracks','isrc_assigned_at')) {
                $table->dropColumn('isrc_assigned_at');
            }

        });
    }
};
