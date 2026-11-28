<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W242 MIXXTUNE_ACCENT_THEMES: the colour theme a distributor picked for his panel. Adds one column only. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('distributors') && ! Schema::hasColumn('distributors', 'theme')) {
            Schema::table('distributors', function (Blueprint $table) {
                $table->string('theme', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        /* the column stays: a rollback never removes data */
    }
};
