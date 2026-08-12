<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A withdrawal must never have multiple financial
         * documents/invoices.
         */
        $duplicates = DB::table('invoices')
            ->select(
                'withdrawal_id',
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('withdrawal_id')
            ->groupBy('withdrawal_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'Duplicate withdrawal invoices exist. Migration stopped.'
            );
        }

        Schema::table(
            'invoices',
            function (Blueprint $table) {
                /*
                 * Tax/document classification.
                 *
                 * tax_treatment:
                 *   gst_registered
                 *   non_gst
                 *   exempt
                 *   manual
                 */
                if (
                    !Schema::hasColumn(
                        'invoices',
                        'tax_treatment'
                    )
                ) {
                    $table
                        ->string(
                            'tax_treatment',
                            30
                        )
                        ->nullable()
                        ->after('invoice_type');
                }

                /*
                 * Immutable financial snapshot captured
                 * when payment request is submitted.
                 */
                if (
                    !Schema::hasColumn(
                        'invoices',
                        'tax_snapshot'
                    )
                ) {
                    $table
                        ->json('tax_snapshot')
                        ->nullable()
                        ->after('company_details');
                }

                if (
                    !Schema::hasColumn(
                        'invoices',
                        'document_generated_at'
                    )
                ) {
                    $table
                        ->timestamp(
                            'document_generated_at'
                        )
                        ->nullable()
                        ->after('paid_at');
                }
            }
        );

        /*
         * Enforce one invoice/document per withdrawal.
         */
        $indexName = 'invoices_withdrawal_unique';

        $indexExists = match (DB::connection()->getDriverName()) {
            'sqlite' => collect(
                DB::select("PRAGMA index_list('invoices')")
            )->contains(
                fn ($index) => ($index->name ?? null) === $indexName
            ),
            default => collect(
                DB::select('SHOW INDEX FROM invoices')
            )->contains(
                fn ($index) => ($index->Key_name ?? null) === $indexName
            ),
        };

        if (!$indexExists) {
            Schema::table(
                'invoices',
                function (Blueprint $table) {
                    $table
                        ->unique(
                            'withdrawal_id',
                            'invoices_withdrawal_unique'
                        );
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Financial/audit fields are intentionally retained.
         */
    }
};
