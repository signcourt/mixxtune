<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'legacy_catalogue_imports',
            function (Blueprint $table) {
                $table->id();

                $table->ulid('public_id')
                    ->unique();

                $table->string('original_filename');

                $table->string('stored_path')
                    ->nullable();

                $table->string('status')
                    ->default('uploaded')
                    ->index();

                $table->unsignedInteger('total_rows')
                    ->default(0);

                $table->unsignedInteger('ready_rows')
                    ->default(0);

                $table->unsignedInteger('blocked_rows')
                    ->default(0);

                $table->unsignedInteger('warning_rows')
                    ->default(0);

                $table->unsignedInteger('imported_rows')
                    ->default(0);

                $table->unsignedInteger('failed_rows')
                    ->default(0);

                $table->json('summary')
                    ->nullable();

                $table->text('failure_message')
                    ->nullable();

                $table->foreignId('uploaded_by')
                    ->constrained('users');

                $table->timestamp('validated_at')
                    ->nullable();

                $table->timestamp('import_started_at')
                    ->nullable();

                $table->timestamp('import_completed_at')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'uploaded_by',
                    'created_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'legacy_catalogue_imports'
        );
    }
};
