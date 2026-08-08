<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropForeign(['artist_id']);
        });

        Schema::table('releases', function (Blueprint $table) {
            $table->unsignedBigInteger('artist_id')
                ->nullable()
                ->change();
        });

        Schema::table('releases', function (Blueprint $table) {
            $table->foreign('artist_id')
                ->references('id')
                ->on('artists')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        /*
         * Existing operator releases may legitimately have no
         * artist account, so rollback must not silently invent one.
         */
        if (
            \Illuminate\Support\Facades\DB::table('releases')
                ->whereNull('artist_id')
                ->exists()
        ) {
            throw new RuntimeException(
                'Cannot make releases.artist_id required while releases without artist accounts exist.'
            );
        }

        Schema::table('releases', function (Blueprint $table) {
            $table->dropForeign(['artist_id']);
        });

        Schema::table('releases', function (Blueprint $table) {
            $table->unsignedBigInteger('artist_id')
                ->nullable(false)
                ->change();
        });

        Schema::table('releases', function (Blueprint $table) {
            $table->foreign('artist_id')
                ->references('id')
                ->on('artists')
                ->cascadeOnDelete();
        });
    }
};
