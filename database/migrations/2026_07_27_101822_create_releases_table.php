<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();

            $table->string('public_id', 30)->unique();
            $table->string('catalog_number', 50)->unique();

            $table->foreignId('artist_id')
                ->constrained('artists')
                ->cascadeOnDelete();

            $table->foreignId('label_id')
                ->nullable()
                ->constrained('labels')
                ->nullOnDelete();

            $table->string('release_type', 30)->index();
            $table->string('title');
            $table->string('version')->nullable();

            $table->string('primary_artist_name');
            $table->string('featuring_artist_name')->nullable();

            $table->json('primary_artists')->nullable();
            $table->json('featuring_artists')->nullable();

            $table->json('stores')->nullable();
            $table->json('excluded_store_ids')->nullable();
            $table->json('territories')->nullable();

            $table->boolean('worldwide')->default(true);

            $table->string(
                'release_timezone',
                100
            )->default('Asia/Kolkata');

            $table->boolean('pre_order')->default(false);

            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->string('language', 100)->nullable();
            $table->string('primary_genre', 100)->nullable();
            $table->string('sub_genre', 100)->nullable();

            $table->string('upc', 20)->nullable()->unique();
            $table->boolean('upc_is_auto_generated')->default(false);

            $table->date('original_release_date')->nullable();
            $table->date('digital_release_date')->nullable();

            $table->string('copyright_owner')->nullable();
            $table->string('copyright_year', 4)->nullable();
            $table->string('phonographic_owner')->nullable();
            $table->string('phonographic_year', 4)->nullable();

            $table->string('artwork_path')->nullable();

            $table->string('status', 40)
                ->default('draft')
                ->index();

            $table->unsignedTinyInteger('wizard_step')->default(1);
            $table->unsignedTinyInteger('completion_percentage')->default(0);

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('live_at')->nullable();
            $table->timestamp('archived_at')->nullable();

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

            $table->index(['artist_id', 'status']);
            $table->index(['label_id', 'status']);
            $table->index(['digital_release_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
