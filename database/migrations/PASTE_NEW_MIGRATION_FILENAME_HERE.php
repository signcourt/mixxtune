<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenue_rows', function (Blueprint $table) {
            $table->string('country_code', 255)->nullable()->change();
        });

        Schema::table('revenue_imports', function (Blueprint $table) {
            $table->longText('failure_reason')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('revenue_rows', function (Blueprint $table) {
            $table->string('country_code', 10)->nullable()->change();
        });

        Schema::table('revenue_imports', function (Blueprint $table) {
            $table->text('failure_reason')->nullable()->change();
        });
    }
};
