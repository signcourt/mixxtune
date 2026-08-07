<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('identifier_assignment_logs')) {
            return;
        }

        Schema::create('identifier_assignment_logs', function (Blueprint $table) {

            $table->id();

            $table->string('public_id', 40)->unique();

            $table->string('identifier_type', 20);

            $table->string('identifier_code', 30);

            $table->string('action', 50);

            $table->foreignId('release_id')
                ->nullable()
                ->constrained('releases')
                ->nullOnDelete();

            $table->foreignId('track_id')
                ->nullable()
                ->constrained('tracks')
                ->nullOnDelete();

            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->string('ip_address', 64)->nullable();

            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(
                ['identifier_type', 'identifier_code']
            );

            $table->index(['release_id']);

            $table->index(['track_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'identifier_assignment_logs'
        );
    }
};
