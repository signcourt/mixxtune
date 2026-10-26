<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W117 MIXXTUNE_MAIL_SCHEDULE_SEND: when a "Schedule send" mail goes out (one empty column) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $t) {
            $t->timestamp('scheduled_at')->nullable()->after('snooze_until')->index();
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', fn (Blueprint $t) => $t->dropColumn('scheduled_at'));
    }
};
