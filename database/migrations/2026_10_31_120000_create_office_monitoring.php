<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W128 MIXXTUNE_OFFICE_MONITOR
 * The desktop activity companion: each office computer runs a small agent (Windows / Mac) that,
 * with the staff member's consent, sends how long the keyboard/mouse was active or idle, which
 * program is in front, and a screenshot every few minutes. The Super Admin sets the screenshot
 * gap and the idle-alert minutes from the panel, and can open a live screen through RustDesk.
 * All new office_ tables + a few new columns on office_attendance. Nothing existing is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('office_devices')) {
            Schema::create('office_devices', function (Blueprint $table) {
                $table->id();
                $table->string('public_id', 40)->unique();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('platform', 20)->nullable(); /* windows | mac */
                $table->string('device_name', 190)->nullable();
                $table->string('token_hash', 64)->nullable()->index();
                $table->string('pair_code', 12)->nullable()->index();
                $table->timestamp('pair_expires_at')->nullable();
                $table->boolean('approved')->default(false);
                $table->string('rustdesk_id', 40)->nullable();
                $table->string('app_version', 30)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->unsignedInteger('idle_seconds')->default(0);
                $table->string('foreground_app', 190)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('office_screenshots')) {
            Schema::create('office_screenshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('device_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->date('work_date')->index();
                $table->string('path', 500);
                $table->string('foreground_app', 190)->nullable();
                $table->timestamp('taken_at')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        /* one row per heartbeat (about one a minute); used for the timeline and active/idle maths */
        if (!Schema::hasTable('office_activity_pings')) {
            Schema::create('office_activity_pings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('device_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->date('work_date')->index();
                $table->timestamp('pinged_at')->nullable();
                $table->unsignedInteger('idle_seconds')->default(0);
                $table->boolean('active')->default(true);
                $table->string('foreground_app', 190)->nullable();
            });
        }

        if (Schema::hasTable('office_attendance')) {
            Schema::table('office_attendance', function (Blueprint $table) {
                if (!Schema::hasColumn('office_attendance', 'comp_active_seconds')) {
                    $table->unsignedInteger('comp_active_seconds')->default(0)->after('active_seconds');
                }
                if (!Schema::hasColumn('office_attendance', 'comp_idle_seconds')) {
                    $table->unsignedInteger('comp_idle_seconds')->default(0)->after('comp_active_seconds');
                }
                if (!Schema::hasColumn('office_attendance', 'idle_alerted_at')) {
                    $table->timestamp('idle_alerted_at')->nullable()->after('comp_idle_seconds');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['comp_active_seconds', 'comp_idle_seconds', 'idle_alerted_at'] as $c) {
            if (Schema::hasTable('office_attendance') && Schema::hasColumn('office_attendance', $c)) {
                Schema::table('office_attendance', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
        foreach (['office_activity_pings', 'office_screenshots', 'office_devices'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
