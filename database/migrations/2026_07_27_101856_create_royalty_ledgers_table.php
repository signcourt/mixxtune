<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('royalty_ledgers', function (Blueprint $table) {

            $table->id();

            $table->string('public_id',40)->unique();

            $table->foreignId('release_id')
                ->nullable()
                ->constrained('releases')
                ->nullOnDelete();

            $table->foreignId('track_id')
                ->nullable()
                ->constrained('tracks')
                ->nullOnDelete();

            $table->foreignId('artist_id')
                ->nullable()
                ->constrained('artists')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('statement_month',7)->index();

            $table->string('store_name')->index();

            $table->string('territory',100)->nullable();

            $table->string('currency',3)->default('USD');

            $table->decimal('gross_amount',18,8)->default(0);

            $table->decimal('label_share',18,8)->default(0);

            $table->decimal('artist_share',18,8)->default(0);

            $table->decimal('split_percentage',8,4)->default(100);

            $table->decimal('payable_amount',18,8)->default(0);

            $table->unsignedBigInteger('streams')->default(0);

            $table->string('status',30)
                ->default('pending')
                ->index();

            $table->json('metadata')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['artist_id','statement_month']);
            $table->index(['track_id','statement_month']);
            $table->index(['release_id','statement_month']);
            $table->index(['store_name','statement_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('royalty_ledgers');
    }
};
