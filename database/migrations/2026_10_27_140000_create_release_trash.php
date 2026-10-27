<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W120 MIXXTUNE_RELEASE_TRASH: releases a Super Admin moved to Trash (kept 90 days, then removed for good) */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('release_trash')) {
            return;
        }
        Schema::create('release_trash', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('release_id')->unique();
            $table->string('title', 255)->nullable();
            $table->string('catalog_number', 120)->nullable();
            $table->string('upc', 40)->nullable();
            $table->string('label_name', 255)->nullable();
            $table->string('artist_name', 255)->nullable();
            $table->string('artwork_path', 500)->nullable();
            $table->string('status_before', 40)->nullable();
            $table->unsignedInteger('tracks')->default(0);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamp('trashed_at')->nullable();
            $table->timestamp('purge_after')->nullable()->index();
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_trash');
    }
};
