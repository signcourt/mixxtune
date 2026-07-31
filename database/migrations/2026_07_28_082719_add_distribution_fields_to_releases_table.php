<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->json('stores')->nullable()->after('status');
            $table->json('territories')->nullable()->after('stores');
            $table->boolean('worldwide')->default(true)->after('territories');
            $table->string('release_timezone', 100)
                ->default('Asia/Kolkata')
                ->after('worldwide');
            $table->boolean('pre_order')->default(false)
                ->after('release_timezone');
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropColumn([
                'stores',
                'territories',
                'worldwide',
                'release_timezone',
                'pre_order',
            ]);
        });
    }
};
