<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_contributors', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            $table->foreignId('track_id')
                ->constrained('tracks')
                ->cascadeOnDelete();

            $table->foreignId('contributor_id')
                ->constrained('contributors')
                ->cascadeOnDelete();

            $table->string('role', 80)->index();
            $table->string('credited_name')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('display_order')->default(1);

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

            $table->unique(
                ['track_id', 'contributor_id', 'role'],
                'track_contributor_role_unique'
            );

            $table->index(['track_id', 'role']);
            $table->index(['contributor_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_contributors');
    }
};
