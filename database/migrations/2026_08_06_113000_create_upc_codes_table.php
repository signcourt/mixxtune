<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('upc_codes')) {
            return;
        }

        Schema::create('upc_codes', function (Blueprint $table) {
            $table->id();

            $table->string('public_id', 40)->unique();

            $table->string('code', 20)->unique();

            $table->string('prefix', 12)
                ->nullable()
                ->index();

            $table->string('status', 30)
                ->default('available')
                ->index();

            $table->unsignedBigInteger('release_id')
                ->nullable()
                ->index();

            $table->unsignedBigInteger('assigned_by')
                ->nullable();

            $table->timestamp('assigned_at')
                ->nullable();

            $table->text('notes')
                ->nullable();

            $table->timestamps();

            $table->foreign('release_id')
                ->references('id')
                ->on('releases')
                ->nullOnDelete();

            $table->foreign('assigned_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upc_codes');
    }
};
