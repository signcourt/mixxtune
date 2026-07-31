<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_label_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('revenue_label_name');
            $table->string('normalized_name')->index();
            $table->foreignId('label_id')
                ->nullable()
                ->constrained('labels')
                ->nullOnDelete();
            $table->decimal('royalty_percentage', 8, 4)->default(100);
            $table->enum('status', [
                'unmapped',
                'mapped',
                'ignored',
            ])->default('unmapped');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('normalized_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_label_mappings');
    }
};
