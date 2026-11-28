<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W239 MIXXTUNE_LIVE_LINKS_AGENT: what the agent found for each release. Creates a table only. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('release_link_agent')) {
            return;
        }

        Schema::create('release_link_agent', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('release_id')->unique();
            $table->string('status', 20)->default('searching')->index(); /* searching | partial | found | gave_up */
            $table->json('links')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_note', 500)->nullable();
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->timestamp('found_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->unsignedBigInteger('whatsapp_sent_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        /* never dropped by a rollback: found links are data */
    }
};
