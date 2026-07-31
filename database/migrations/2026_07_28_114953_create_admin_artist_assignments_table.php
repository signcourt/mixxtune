<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_artist_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('artist_id')
                ->constrained('artists')
                ->cascadeOnDelete();

            $table->string('assignment_role', 50)
                ->default('manager');

            $table->boolean('can_view')->default(true);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_manage_releases')->default(false);
            $table->boolean('can_manage_team')->default(false);
            $table->boolean('can_manage_splits')->default(false);

            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['user_id', 'artist_id'],
                'admin_artist_assignment_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_artist_assignments');
    }
};
