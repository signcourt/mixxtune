<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();

            $table->ulid('public_id')->unique();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('report_type', 30)
                ->default('requested');

            $table->string('report_mode', 30)
                ->default('single');

            $table->string('scope', 50)
                ->default('full_catalogue');

            $table->char('from_month', 7);
            $table->char('to_month', 7);

            $table->json('selected_columns');

            $table->json('filters')
                ->nullable();

            $table->string('currency', 10)
                ->nullable();

            $table->decimal(
                'gross_amount',
                20,
                8
            )->default(0);

            $table->decimal(
                'net_amount',
                20,
                8
            )->default(0);

            $table->unsignedBigInteger(
                'rows_count'
            )->default(0);

            $table->string('status', 30)
                ->default('pending');

            $table->string('file_path')
                ->nullable();

            $table->string('file_name')
                ->nullable();

            $table->text('error_message')
                ->nullable();

            $table->timestamp('generated_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'report_type',
                'created_at',
            ]);

            $table->index([
                'from_month',
                'to_month',
            ]);

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'generated_reports'
        );
    }
};
