<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->string('invoice_number', 40)->unique();

            $table->foreignId('withdrawal_id')
                ->nullable()
                ->constrained('withdrawals')
                ->nullOnDelete();

            $table->foreignId('wallet_id')
                ->nullable()
                ->constrained('wallets')
                ->nullOnDelete();

            $table->foreignId('label_id')
                ->nullable()
                ->constrained('labels')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('invoice_type', 30)
                ->default('withdrawal');

            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            $table->string('currency', 3)->default('INR');

            $table->decimal('subtotal', 18, 8)->default(0);
            $table->decimal('gst_percentage', 8, 4)->default(0);
            $table->decimal('gst_amount', 18, 8)->default(0);
            $table->decimal('tds_percentage', 8, 4)->default(0);
            $table->decimal('tds_amount', 18, 8)->default(0);
            $table->decimal('total_amount', 18, 8)->default(0);
            $table->decimal('net_payable', 18, 8)->default(0);

            $table->string('status', 30)->default('issued');

            $table->string('billing_name', 255)->nullable();
            $table->string('billing_email', 255)->nullable();
            $table->string('billing_phone', 50)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('gstin', 30)->nullable();
            $table->string('pan', 20)->nullable();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['label_id', 'status']);
            $table->index(['withdrawal_id', 'status']);
            $table->index(['invoice_date', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            $table->string('description', 255);
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('rate', 18, 8)->default(0);
            $table->decimal('amount', 18, 8)->default(0);

            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
