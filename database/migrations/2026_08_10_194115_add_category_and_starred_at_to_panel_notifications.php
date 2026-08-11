<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panel_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('panel_notifications', 'category')) {
                $table->string('category', 30)
                    ->default('primary')
                    ->after('severity')
                    ->index();
            }

            if (!Schema::hasColumn('panel_notifications', 'starred_at')) {
                $table->timestamp('starred_at')
                    ->nullable()
                    ->after('read_at');
            }
        });

        DB::table('panel_notifications')
            ->whereNull('category')
            ->orWhere('category', '')
            ->update([
                'category' => 'primary',
            ]);
    }

    public function down(): void
    {
        Schema::table('panel_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('panel_notifications', 'starred_at')) {
                $table->dropColumn('starred_at');
            }

            if (Schema::hasColumn('panel_notifications', 'category')) {
                $table->dropIndex(['category']);
                $table->dropColumn('category');
            }
        });
    }
};
