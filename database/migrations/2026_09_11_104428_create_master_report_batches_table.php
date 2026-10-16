<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_report_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code')->unique();

            $table->string('original_filename');
            $table->string('stored_path')->nullable();

            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('total_tracks')->default(0);
            $table->unsignedBigInteger('total_stores')->default(0);
            $table->decimal('total_streams', 24, 4)->default(0);

            $table->decimal('gross_revenue', 24, 8)->default(0);
            $table->decimal('vendor_revenue', 24, 8)->default(0);
            $table->decimal('deduction_amount', 24, 8)->default(0);
            $table->decimal('deduction_percent', 12, 6)->default(0);

            $table->string('currency', 20)->nullable();
            $table->string('status', 30)->default('processing');
            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_report_batches');
    }
};
