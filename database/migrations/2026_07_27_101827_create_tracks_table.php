<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();

            $table->string('public_id', 30)->unique();

            $table->foreignId('release_id')
                ->constrained('releases')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('disc_number')->default(1);
            $table->unsignedSmallInteger('track_number')->default(1);

            $table->string('title');
            $table->string('version')->nullable();
            $table->string('subtitle')->nullable();

            $table->string('primary_artist_name');
            $table->string('featuring_artist_name')->nullable();

            $table->string('isrc', 20)->nullable()->unique();
            $table->boolean('isrc_is_auto_generated')->default(false);

            $table->string('language', 100)->nullable();
            $table->string('genre', 100)->nullable();
            $table->string('sub_genre', 100)->nullable();

            $table->boolean('is_explicit')->default(false);
            $table->boolean('is_instrumental')->default(false);
            $table->boolean('contains_ai_generated_content')->default(false);

            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('preview_start_seconds')->nullable();

            $table->longText('lyrics')->nullable();

            $table->string('audio_path')->nullable();
            $table->string('audio_original_name')->nullable();
            $table->string('audio_mime_type', 100)->nullable();
            $table->unsignedBigInteger('audio_size_bytes')->nullable();

            $table->unsignedInteger('sample_rate')->nullable();
            $table->unsignedSmallInteger('bit_depth')->nullable();
            $table->unsignedSmallInteger('channels')->nullable();

            $table->string('audio_validation_status', 30)
                ->default('pending')
                ->index();

            $table->json('audio_validation_errors')->nullable();

            $table->string('status', 30)
                ->default('draft')
                ->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['release_id', 'disc_number', 'track_number'],
                'tracks_release_disc_track_unique'
            );

            $table->index(['release_id', 'status']);
            $table->index(['isrc', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
