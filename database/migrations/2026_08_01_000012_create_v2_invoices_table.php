<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            Schema::create(
                'invoices',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->string(
                        'invoice_number',
                        50
                    )->unique();

                    $table->unsignedBigInteger(
                        'user_id'
                    );

                    $table->unsignedBigInteger(
                        'royalty_statement_id'
                    )->nullable();

                    $table->string(
                        'invoice_type',
                        30
                    )->default('royalty');

                    $table->date(
                        'invoice_date'
                    );

                    $table->date(
                        'due_date'
                    )->nullable();

                    $table->string(
                        'currency',
                        10
                    )->default('INR');

                    $table->decimal(
                        'subtotal',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'tax_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'tds_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'total_amount',
                        20,
                        8
                    )->default(0);

                    $table->string(
                        'status',
                        30
                    )->default('generated');

                    $table->json(
                        'billing_details'
                    )->nullable();

                    $table->json(
                        'company_details'
                    )->nullable();

                    $table->text(
                        'notes'
                    )->nullable();

                    $table->timestamp(
                        'paid_at'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'created_by'
                    )->nullable();

                    $table->timestamps();

                    $table->index([
                        'user_id',
                        'invoice_date',
                    ]);

                    $table->index([
                        'status',
                        'invoice_date',
                    ]);

                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                }
            );
        }

        if (!Schema::hasTable('invoice_items')) {
            Schema::create(
                'invoice_items',
                function (Blueprint $table) {
                    $table->id();

                    $table->unsignedBigInteger(
                        'invoice_id'
                    );

                    $table->string(
                        'description'
                    );

                    $table->decimal(
                        'quantity',
                        16,
                        4
                    )->default(1);

                    $table->decimal(
                        'rate',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'amount',
                        20,
                        8
                    )->default(0);

                    $table->json(
                        'meta'
                    )->nullable();

                    $table->timestamps();

                    $table->foreign(
                        'invoice_id'
                    )
                        ->references('id')
                        ->on('invoices')
                        ->cascadeOnDelete();
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Financial records are not deleted
         * automatically.
         */
    }
};
