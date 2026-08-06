<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('panel_notifications')) {
            return;
        }

        Schema::create('panel_notifications', function (Blueprint $table) {

            $table->id();

            $table->string('public_id',40)->unique();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('type');

            $table->string('title');

            $table->text('message')->nullable();

            $table->string('action_url')->nullable();

            $table->string('severity',20)
                ->default('info');

            $table->string('related_type')
                ->nullable();

            $table->unsignedBigInteger('related_id')
                ->nullable();

            $table->json('data')
                ->nullable();

            $table->timestamp('read_at')
                ->nullable();

            $table->timestamp('dismissed_at')
                ->nullable();

            $table->timestamps();

            $table->index(['user_id','read_at']);
            $table->index(['type']);
            $table->index(['related_type','related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_notifications');
    }
};
