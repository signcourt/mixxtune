<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Distribution fields have been moved into
        // 2026_07_27_101822_create_releases_table.php.
        // Keep this migration for production compatibility.
    }

    public function down(): void
    {
        //
    }
};
