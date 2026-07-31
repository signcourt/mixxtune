<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenue_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('revenue_rows', 'revenue_import_id')) {
                $table->foreignId('revenue_import_id')
                    ->after('id')
                    ->constrained('revenue_imports')
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('revenue_rows', 'source_row_number')) {
                $table->unsignedBigInteger('source_row_number')->nullable();
            }

            if (!Schema::hasColumn('revenue_rows', 'isrc')) {
                $table->string('isrc', 20)->nullable()->index();
            }

            if (!Schema::hasColumn('revenue_rows', 'upc')) {
                $table->string('upc', 30)->nullable()->index();
            }

            if (!Schema::hasColumn('revenue_rows', 'track_title')) {
                $table->string('track_title')->nullable();
            }

            if (!Schema::hasColumn('revenue_rows', 'release_title')) {
                $table->string('release_title')->nullable();
            }

            if (!Schema::hasColumn('revenue_rows', 'artist_name')) {
                $table->string('artist_name')->nullable();
            }

            if (!Schema::hasColumn('revenue_rows', 'label_name')) {
                $table->string('label_name')->nullable();
            }

            if (!Schema::hasColumn('revenue_rows', 'store_name')) {
                $table->string('store_name', 120)->nullable()->index();
            }

            if (!Schema::hasColumn('revenue_rows', 'country_code')) {
                $table->string('country_code', 10)->nullable()->index();
            }

            if (!Schema::hasColumn('revenue_rows', 'sale_type')) {
                $table->string('sale_type', 120)->nullable();
            }

            if (!Schema::hasColumn('revenue_rows', 'sale_month')) {
                $table->date('sale_month')->nullable()->index();
            }

            if (!Schema::hasColumn('revenue_rows', 'currency')) {
                $table->char('currency', 3)->default('INR');
            }

            if (!Schema::hasColumn('revenue_rows', 'streams')) {
                $table->unsignedBigInteger('streams')->default(0);
            }

            if (!Schema::hasColumn('revenue_rows', 'quantity')) {
                $table->decimal('quantity', 20, 6)->default(0);
            }

            if (!Schema::hasColumn('revenue_rows', 'gross_amount')) {
                $table->decimal('gross_amount', 20, 8)->default(0);
            }

            if (!Schema::hasColumn('revenue_rows', 'net_amount')) {
                $table->decimal('net_amount', 20, 8)->default(0);
            }

            if (!Schema::hasColumn('revenue_rows', 'source_row_hash')) {
                $table->string('source_row_hash', 64)->nullable()->index();
            }

            if (!Schema::hasColumn('revenue_rows', 'match_status')) {
                $table->string('match_status', 30)
                    ->default('pending')
                    ->index();
            }

            if (!Schema::hasColumn('revenue_rows', 'raw_data')) {
                $table->json('raw_data')->nullable();
            }

            if (!Schema::hasColumn('revenue_rows', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    public function down(): void
    {
    }
};
