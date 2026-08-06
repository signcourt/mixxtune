<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('release_activity_logs')) {
            return;
        }

        Schema::create(
            'release_activity_logs',
            function (Blueprint $table) {
                $table->id();

                $table->string(
                    'public_id',
                    40
                )->unique();

                $table->unsignedBigInteger(
                    'release_id'
                );

                $table->unsignedBigInteger(
                    'track_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'user_id'
                )->nullable();

                $table->string(
                    'action',
                    120
                );

                $table->string(
                    'category',
                    80
                )->default('general');

                $table->string(
                    'title'
                );

                $table->text(
                    'description'
                )->nullable();

                $table->json(
                    'old_values'
                )->nullable();

                $table->json(
                    'new_values'
                )->nullable();

                $table->json(
                    'meta'
                )->nullable();

                $table->string(
                    'ip_address',
                    45
                )->nullable();

                $table->text(
                    'user_agent'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'release_id',
                    'created_at',
                ]);

                $table->index([
                    'track_id',
                    'created_at',
                ]);

                $table->index([
                    'user_id',
                    'created_at',
                ]);

                $table->index([
                    'action',
                    'created_at',
                ]);

                $table->foreign('release_id')
                    ->references('id')
                    ->on('releases')
                    ->cascadeOnDelete();

                $table->foreign('track_id')
                    ->references('id')
                    ->on('tracks')
                    ->nullOnDelete();

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );
    }

    public function down(): void
    {
        /*
         * Audit history is retained in production.
         */
    }
};
