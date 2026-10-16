<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_report_rows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('batch_id')
                ->constrained('master_report_batches')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('source_row_number');

            $table->string('store')->nullable()->index();
            $table->string('report_month')->nullable()->index();
            $table->string('track_title')->nullable()->index();
            $table->string('version')->nullable();
            $table->string('artist')->nullable()->index();
            $table->string('label')->nullable()->index();
            $table->string('isrc', 50)->nullable()->index();
            $table->string('upc', 50)->nullable()->index();
            $table->string('release_title')->nullable();
            $table->string('country', 100)->nullable()->index();
            $table->string('currency', 20)->nullable();

            $table->decimal('streams', 24, 4)->default(0);
            $table->decimal('gross_revenue', 24, 8)->default(0);
            $table->decimal('vendor_revenue', 24, 8)->default(0);
            $table->decimal('deduction_amount', 24, 8)->default(0);
            $table->decimal('deduction_percent', 12, 6)->default(0);

            $table->string('content_type')->nullable();
            $table->string('usage_type')->nullable();
            $table->string('subscription_type')->nullable();

            $table->string('validation_status', 20)
                ->default('valid')
                ->index();

            $table->json('validation_messages')->nullable();

            $table->text('raw_store')->nullable();
            $table->text('raw_track')->nullable();
            $table->text('raw_artist')->nullable();
            $table->text('raw_label')->nullable();
            $table->text('raw_isrc')->nullable();
            $table->text('raw_upc')->nullable();
            $table->text('raw_streams')->nullable();
            $table->text('raw_gross_revenue')->nullable();
            $table->text('raw_vendor_revenue')->nullable();

            $table->json('raw_data')->nullable();

            $table->timestamps();

            $table->index(['batch_id', 'store']);
            $table->index(['batch_id', 'isrc']);
            $table->index(['batch_id', 'validation_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_report_rows');
    }
};
