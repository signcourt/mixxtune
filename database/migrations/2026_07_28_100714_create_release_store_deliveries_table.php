<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_store_deliveries', function (Blueprint $table) {
            $table->id();

            $table->string('public_id',40)
                ->unique();


            $table->foreignId('release_id')
                ->constrained('releases')
                ->cascadeOnDelete();

            $table->foreignId('distribution_store_id')
                ->constrained('distribution_stores')
                ->cascadeOnDelete();

            $table->string('status', 30)
                ->default('pending')
                ->index();

            $table->string('store_release_id', 255)->nullable();
            $table->string('store_url', 1000)->nullable();

            $table->text('delivery_notes')->nullable();
            $table->text('delivery_note')->nullable();
            $table->string('external_reference', 255)->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('live_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('takedown_at')->nullable();
            $table->timestamp('taken_down_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['release_id', 'distribution_store_id'],
                'release_store_delivery_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_store_deliveries');
    }
};
