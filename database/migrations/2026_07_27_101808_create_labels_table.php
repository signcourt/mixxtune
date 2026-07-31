<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->id();

            $table->string('public_id', 30)->unique();
            $table->string('name');
            $table->string('slug')->unique();

            $table->string('legal_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();

            $table->string('country', 100)->default('India');
            $table->string('timezone', 100)->default('Asia/Kolkata');
            $table->string('currency', 3)->default('INR');

            $table->string('status', 30)->default('active')->index();

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

            $table->index(['status', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labels');
    }
};
