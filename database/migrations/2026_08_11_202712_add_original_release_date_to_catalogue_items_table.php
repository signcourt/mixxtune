<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogue_items', function (Blueprint $table) {
            $table->date('original_release_date')
                ->nullable()
                ->after('digital_release_date');
        });
    }

    public function down(): void
    {
        Schema::table('catalogue_items', function (Blueprint $table) {
            $table->dropColumn('original_release_date');
        });
    }
};
