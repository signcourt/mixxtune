<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W238 MIXXTUNE_PROMO_KIT: one public "listen" page per live release. Creates a table only; nothing existing is touched. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('release_share_pages')) {
            return;
        }

        Schema::create('release_share_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('release_id')->unique();
            $table->string('code', 16)->unique();
            $table->boolean('enabled')->default(true);
            $table->json('links')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        /* never dropped by a rollback: the pages and their links are data */
    }
};
