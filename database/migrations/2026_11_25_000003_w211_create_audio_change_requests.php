<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W211 - WAV replacements and replacement requests (who, why, old file, new file, decision) */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audio_change_requests')) {
            return;
        }
        Schema::create('audio_change_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('release_id')->index();
            $table->unsignedBigInteger('track_id')->index();
            $table->string('proposed_path', 500);
            $table->string('previous_path', 500)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending')->index(); /* pending | done | rejected */
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('approval_id')->nullable()->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        /* production data is never dropped by a rollback - the table stays */
    }
};
