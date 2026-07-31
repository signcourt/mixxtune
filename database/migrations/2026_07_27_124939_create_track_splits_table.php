<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_splits', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            $table->foreignId('track_id')
                ->constrained('tracks')
                ->cascadeOnDelete();

            $table->foreignId('contributor_id')
                ->constrained('contributors')
                ->cascadeOnDelete();

            $table->enum('split_type', [
                'master',
                'publishing',
                'mechanical',
                'performance',
                'youtube',
            ])->index();

            $table->decimal('percentage', 5, 2);

            $table->boolean('is_recoupable')->default(false);

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->string('status', 30)
                ->default('active')
                ->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['track_id', 'split_type']);
            $table->index(['contributor_id', 'split_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_splits');
    }
};
