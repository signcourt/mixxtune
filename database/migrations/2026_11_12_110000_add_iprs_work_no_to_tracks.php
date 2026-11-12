<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Store the IPRS "WORK INT NO" directly on the track it belongs to. Once an
 * admin maps an IPRS work to a song, the work number lives here, so a later
 * IPRS statement carrying the same work number maps to this track automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->string('iprs_work_no', 40)->nullable()->after('isrc')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropIndex(['iprs_work_no']);
            $table->dropColumn('iprs_work_no');
        });
    }
};
