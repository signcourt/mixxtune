<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W131 MIXXTUNE_MEDIA_OPTIONAL
 * A flag only an Admin / Super Admin can set on a release: this is old catalogue, save it without the
 * WAV master and without a cover poster. New releases by labels / artists are not affected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('releases') && !Schema::hasColumn('releases', 'media_optional')) {
            Schema::table('releases', function (Blueprint $table) {
                $table->boolean('media_optional')->default(false)->after('artwork_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('releases') && Schema::hasColumn('releases', 'media_optional')) {
            Schema::table('releases', fn (Blueprint $t) => $t->dropColumn('media_optional'));
        }
    }
};
