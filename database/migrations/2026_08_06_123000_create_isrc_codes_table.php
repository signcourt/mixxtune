<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('isrc_codes')) {
            return;
        }

        Schema::create('isrc_codes', function (Blueprint $table) {

            $table->id();

            $table->string('public_id', 40)->unique();

            $table->string('code', 20)->unique();

            $table->string('country_code', 2);

            $table->string('registrant_code', 3);

            $table->unsignedSmallInteger('reference_year');

            $table->unsignedInteger('designation_code');

            $table->string('status', 30)
                  ->default('available');

            $table->foreignId('track_id')
                  ->nullable()
                  ->constrained('tracks')
                  ->nullOnDelete();

            $table->foreignId('assigned_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('assigned_at')
                  ->nullable();

            $table->text('notes')
                  ->nullable();

            $table->timestamps();

            $table->index(
                ['status', 'reference_year']
            );

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isrc_codes');
    }
};
