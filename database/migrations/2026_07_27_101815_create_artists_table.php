<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();

            $table->string('public_id', 30)->unique();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('label_id')
                ->nullable()
                ->constrained('labels')
                ->nullOnDelete();

            $table->string('stage_name');
            $table->string('legal_name')->nullable();
            $table->string('slug')->unique();

            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable();

            $table->string('country', 100)->default('India');
            $table->string('timezone', 100)->default('Asia/Kolkata');
            $table->string('currency', 3)->default('INR');

            $table->string('profile_image_path')->nullable();
            $table->text('bio')->nullable();

            $table->string('account_status', 30)
                ->default('active')
                ->index();

            $table->string('kyc_status', 30)
                ->default('pending')
                ->index();

            $table->boolean('can_receive_splits')->default(true);
            $table->boolean('can_create_releases')->default(true);

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

            $table->index(['label_id', 'account_status']);
            $table->index(['user_id', 'account_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
