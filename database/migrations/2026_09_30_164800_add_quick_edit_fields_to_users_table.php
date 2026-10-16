<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'quick_edit_fields')) {
                $table->json('quick_edit_fields')
                    ->nullable()
                    ->after('sidebar_navigation_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'quick_edit_fields')) {
                $table->dropColumn('quick_edit_fields');
            }
        });
    }
};
