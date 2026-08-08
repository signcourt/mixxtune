<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->string('track_type', 50)
                ->default('original')
                ->after('subtitle');

            $table->string('author_name')
                ->nullable()
                ->after('featuring_artist_name');

            $table->string('composer_name')
                ->nullable()
                ->after('author_name');

            $table->string('arranger_name')
                ->nullable()
                ->after('composer_name');

            $table->string('producer_name')
                ->nullable()
                ->after('arranger_name');

            $table->string('music_director_name')
                ->nullable()
                ->after('producer_name');

            $table->string('publisher_name')
                ->nullable()
                ->after('music_director_name');

            $table->string('p_line')
                ->nullable()
                ->after('publisher_name');

            $table->unsignedSmallInteger('release_year')
                ->nullable()
                ->after('p_line');

            $table->string('title_language', 100)
                ->nullable()
                ->after('language');

            $table->string('lyrics_language', 100)
                ->nullable()
                ->after('title_language');

            $table->string('parental_advisory', 30)
                ->default('no')
                ->after('is_explicit');

            $table->string('price_tier', 50)
                ->default('free')
                ->after('parental_advisory');
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn([
                'track_type',
                'author_name',
                'composer_name',
                'arranger_name',
                'producer_name',
                'music_director_name',
                'publisher_name',
                'p_line',
                'release_year',
                'title_language',
                'lyrics_language',
                'parental_advisory',
                'price_tier',
            ]);
        });
    }
};
