<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->string('public_id', 40)->unique();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('artist_id')
                ->nullable()
                ->constrained('artists')
                ->nullOnDelete();

            $table->string('transaction_type', 30)->index();
            $table->string('direction', 10)->index();

            $table->decimal('amount', 18, 8);
            $table->string('currency', 3)->default('INR');

            $table->decimal('balance_before', 18, 8)->default(0);
            $table->decimal('balance_after', 18, 8)->default(0);

            $table->string('reference_type', 100)->nullable()->index();
            $table->unsignedBigInteger('reference_id')->nullable()->index();

            $table->string('description')->nullable();

            $table->string('status', 30)
                ->default('posted')
                ->index();

            $table->timestamp('effective_at')->nullable()->index();
            $table->timestamp('posted_at')->nullable()->index();

            $table->json('metadata')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['artist_id', 'status']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['currency', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
