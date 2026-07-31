<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('distribution_stores')) {
            Schema::create('distribution_stores', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('slug', 150)->unique();
                $table->string('logo_path')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('default_selected')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_stores');
    }
};
