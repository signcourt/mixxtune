<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tracks')) {
            return;
        }

        Schema::table('tracks', function (Blueprint $table) {
            if (!Schema::hasColumn('tracks', 'audio_codec')) {
                $table->string(
                    'audio_codec',
                    100
                )->nullable();
            }

            if (!Schema::hasColumn('tracks', 'audio_sample_rate')) {
                $table->unsignedInteger(
                    'audio_sample_rate'
                )->nullable();
            }

            if (!Schema::hasColumn('tracks', 'audio_bit_depth')) {
                $table->unsignedSmallInteger(
                    'audio_bit_depth'
                )->nullable();
            }

            if (!Schema::hasColumn('tracks', 'audio_channels')) {
                $table->unsignedSmallInteger(
                    'audio_channels'
                )->nullable();
            }

            if (!Schema::hasColumn('tracks', 'audio_channel_layout')) {
                $table->string(
                    'audio_channel_layout',
                    100
                )->nullable();
            }

            if (!Schema::hasColumn('tracks', 'audio_duration_seconds')) {
                $table->decimal(
                    'audio_duration_seconds',
                    12,
                    3
                )->nullable();
            }

            if (!Schema::hasColumn('tracks', 'audio_validated_at')) {
                $table->timestamp(
                    'audio_validated_at'
                )->nullable();
            }
        });
    }

    public function down(): void
    {
        /*
         * Audio validation metadata is retained.
         * No automatic destructive rollback.
         */
    }
};
