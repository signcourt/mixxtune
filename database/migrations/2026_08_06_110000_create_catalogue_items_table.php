<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('catalogue_items')) {
            return;
        }

        Schema::create('catalogue_items', function (Blueprint $table) {

            $table->id();

            $table->string('public_id',40)->unique();

            $table->foreignId('release_id')
                ->constrained('releases')
                ->cascadeOnDelete();

            $table->foreignId('artist_id')
                ->nullable()
                ->constrained('artists')
                ->nullOnDelete();

            $table->foreignId('label_id')
                ->nullable()
                ->constrained('labels')
                ->nullOnDelete();

            $table->string('title');

            $table->string('release_type',40)->nullable();

            $table->string('primary_artist_name')->nullable();

            $table->json('primary_artists')->nullable();

            $table->json('featuring_artists')->nullable();

            $table->string('label_name')->nullable();

            $table->string('upc')->nullable();

            $table->string('catalog_number')->nullable();

            $table->string('language')->nullable();

            $table->string('primary_genre')->nullable();

            $table->string('sub_genre')->nullable();

            $table->string('artwork_path')->nullable();

            $table->date('digital_release_date')->nullable();

            $table->string('release_status')->nullable();

            $table->unsignedInteger('track_count')->default(0);

            $table->unsignedInteger('isrc_assigned_count')->default(0);

            $table->json('store_ids')->nullable();

            $table->json('delivery_summary')->nullable();

            $table->boolean('is_visible')->default(false);

            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            $table->index(['release_status','is_visible']);

            $table->index(['artist_id']);

            $table->index(['label_id']);

            $table->index(['digital_release_date']);
        });
    }

    public function down(): void
    {
        //
    }
};
