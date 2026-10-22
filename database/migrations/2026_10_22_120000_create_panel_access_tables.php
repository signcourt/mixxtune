<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W81 MIXXTUNE_PANEL_ACCESS
 * panel_role_defaults    - which sidebar tab each role (label / artist /
 *                          manager) gets by default. No row = standard.
 * panel_access_overrides - one account allowed / blocked for one tab,
 *                          above its role default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('panel_role_defaults')) {
            Schema::create('panel_role_defaults', function (Blueprint $table) {
                $table->id();
                $table->string('role', 30);
                $table->string('tab', 60);
                $table->boolean('allowed');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['role', 'tab']);
            });
        }

        if (!Schema::hasTable('panel_access_overrides')) {
            Schema::create('panel_access_overrides', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('tab', 60);
                $table->boolean('allowed');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'tab']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_access_overrides');
        Schema::dropIfExists('panel_role_defaults');
    }
};
