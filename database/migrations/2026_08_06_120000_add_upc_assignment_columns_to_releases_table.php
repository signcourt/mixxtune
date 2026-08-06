<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('releases')) {
            return;
        }

        Schema::table('releases', function (Blueprint $table) {

            if (!Schema::hasColumn('releases', 'upc_assigned_at')) {
                $table->timestamp('upc_assigned_at')
                    ->nullable()
                    ->after('upc');
            }

            if (!Schema::hasColumn('releases', 'upc_assigned_by')) {
                $table->foreignId('upc_assigned_by')
                    ->nullable()
                    ->after('upc_assigned_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('releases')) {
            return;
        }

        Schema::table('releases', function (Blueprint $table) {

            if (Schema::hasColumn('releases', 'upc_assigned_by')) {
                $table->dropConstrainedForeignId('upc_assigned_by');
            }

            if (Schema::hasColumn('releases', 'upc_assigned_at')) {
                $table->dropColumn('upc_assigned_at');
            }

        });
    }
};
