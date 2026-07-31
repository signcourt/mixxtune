<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributors', function (Blueprint $table) {
            $table->id();

            $table->string('public_id', 30)->unique();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('artist_id')
                ->nullable()
                ->constrained('artists')
                ->nullOnDelete();

            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable();

            $table->string('country', 100)->default('India');

            $table->string('ipi_number', 30)->nullable()->index();
            $table->string('isni', 30)->nullable()->index();

            $table->string('primary_role', 80)->nullable()->index();

            $table->boolean('can_receive_splits')->default(true);
            $table->boolean('has_dashboard_access')->default(false);

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
            $table->softDeletes();

            $table->index(['artist_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributors');
    }
};
