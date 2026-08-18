<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_mapping_rules', function (Blueprint $table) {
            $table->id();

            $table->string('identifier_type', 20);
            $table->string('identifier_value', 191);

            $table->string('owner_type', 20);
            $table->unsignedBigInteger('owner_id');

            $table->unsignedBigInteger('track_id')->nullable();
            $table->unsignedBigInteger('release_id')->nullable();

            $table->string('source', 30)
                ->default('manual');

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->unique(
                ['identifier_type', 'identifier_value'],
                'revenue_mapping_rules_identifier_unique'
            );

            $table->index(
                ['owner_type', 'owner_id'],
                'revenue_mapping_rules_owner_index'
            );

            $table->index('track_id');
            $table->index('release_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_mapping_rules');
    }
};
