<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W195 MIXXTUNE_POSTER_CHANGE_REQUEST: label/artist request a poster change that a
 * super-admin/admin must approve before it goes live. One pending request per release.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poster_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')
                ->constrained('releases')
                ->cascadeOnDelete();
            $table->string('proposed_path');
            $table->foreignId('requested_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['release_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poster_change_requests');
    }
};
