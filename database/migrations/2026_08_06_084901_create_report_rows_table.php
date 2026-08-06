<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('report_rows')) {
            return;
        }

        Schema::create(
            'report_rows',
            function (Blueprint $table) {
                $table->id();

                $table->string(
                    'row_hash',
                    64
                )->unique();

                $table->foreignId(
                    'report_import_id'
                )
                    ->constrained('report_imports')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger(
                    'release_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'track_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'artist_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'label_id'
                )->nullable();

                $table->string(
                    'track_artist'
                )->nullable();

                $table->string(
                    'album_title'
                )->nullable();

                $table->string(
                    'album_artist'
                )->nullable();

                $table->string(
                    'label_name'
                )->nullable();

                $table->string(
                    'track_title'
                )->nullable();

                $table->string(
                    'isrc',
                    30
                )->nullable();

                $table->string(
                    'upc',
                    30
                )->nullable();

                $table->string(
                    'platform',
                    150
                )->nullable();

                $table->string(
                    'currency',
                    10
                )->nullable();

                $table->string(
                    'country_code',
                    10
                )->nullable();

                $table->string(
                    'cms',
                    150
                )->nullable();

                $table->string(
                    'sale_type',
                    150
                )->nullable();

                $table->date(
                    'sale_date'
                )->nullable();

                $table->string(
                    'sale_month',
                    10
                )->nullable();

                $table->decimal(
                    'streams',
                    20,
                    4
                )->default(0);

                $table->decimal(
                    'sale_units',
                    20,
                    4
                )->default(0);

                $table->decimal(
                    'label_rate',
                    20,
                    8
                )->default(0);

                $table->decimal(
                    'earnings',
                    20,
                    8
                )->default(0);

                $table->json(
                    'raw_data'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'report_import_id',
                    'sale_month',
                ]);

                $table->index([
                    'track_id',
                    'sale_month',
                ]);

                $table->index([
                    'artist_id',
                    'sale_month',
                ]);

                $table->index([
                    'label_id',
                    'sale_month',
                ]);

                $table->index([
                    'isrc',
                    'upc',
                ]);
            }
        );
    }

    public function down(): void
    {
        /*
         * Imported royalty rows are retained.
         */
    }
};
