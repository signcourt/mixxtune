<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices', 'royalty_statement_id')) {
                    $table->unsignedBigInteger(
                        'royalty_statement_id'
                    )->nullable();
                }

                if (!Schema::hasColumn('invoices', 'tax_amount')) {
                    $table->decimal(
                        'tax_amount',
                        20,
                        8
                    )->default(0);
                }

                if (!Schema::hasColumn('invoices', 'billing_details')) {
                    $table->json(
                        'billing_details'
                    )->nullable();
                }

                if (!Schema::hasColumn('invoices', 'company_details')) {
                    $table->json(
                        'company_details'
                    )->nullable();
                }

                if (!Schema::hasColumn('invoices', 'paid_at')) {
                    $table->timestamp(
                        'paid_at'
                    )->nullable();
                }
            });
        }

        if (
            Schema::hasTable('invoice_items')
            && !Schema::hasColumn('invoice_items', 'meta')
        ) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->json('meta')->nullable();
            });
        }
    }

    public function down(): void
    {
        /*
         * Finance compatibility fields are retained.
         */
    }
};
