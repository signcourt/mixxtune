<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'report_import_publications',
            function (Blueprint $table) {
                $table->id();

                /*
                 * One publication lifecycle per import.
                 */
                $table->unsignedBigInteger(
                    'report_import_id'
                )->unique();

                $table->string(
                    'status',
                    30
                )->default('pending');

                $table->timestamp(
                    'scheduled_for'
                )->nullable();

                $table->timestamp(
                    'started_at'
                )->nullable();

                $table->timestamp(
                    'completed_at'
                )->nullable();

                $table->timestamp(
                    'failed_at'
                )->nullable();

                $table->unsignedInteger(
                    'attempt_count'
                )->default(0);

                /*
                 * Frozen publication reconciliation.
                 */
                $table->unsignedBigInteger(
                    'row_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'posting_count'
                )->default(0);

                $table->decimal(
                    'collected_revenue',
                    24,
                    8
                )->default(0);

                $table->decimal(
                    'earnings',
                    24,
                    8
                )->default(0);

                $table->text(
                    'last_error'
                )->nullable();

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'report_import_id',
                    'rip_report_import_fk'
                )
                    ->references('id')
                    ->on('report_imports')
                    ->restrictOnDelete();

                $table->index(
                    [
                        'status',
                        'scheduled_for',
                    ],
                    'rip_status_schedule_idx'
                );
            }
        );

        Schema::create(
            'report_import_postings',
            function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger(
                    'publication_id'
                );

                $table->unsignedBigInteger(
                    'report_import_id'
                );

                /*
                 * Canonical financial beneficiary.
                 */
                $table->string(
                    'owner_type',
                    20
                );

                $table->unsignedBigInteger(
                    'owner_id'
                );

                $table->string(
                    'currency',
                    3
                );

                /*
                 * Stable publisher idempotency key.
                 */
                $table->string(
                    'posting_key',
                    191
                )->unique();

                $table->string(
                    'status',
                    30
                )->default('pending');

                $table->unsignedBigInteger(
                    'row_count'
                )->default(0);

                $table->decimal(
                    'collected_revenue',
                    24,
                    8
                )->default(0);

                $table->decimal(
                    'earnings',
                    24,
                    8
                )->default(0);

                /*
                 * Accounting links populated only
                 * when the publisher actually posts.
                 */
                $table->unsignedBigInteger(
                    'royalty_statement_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'wallet_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'wallet_transaction_id'
                )->nullable();

                $table->timestamp(
                    'posted_at'
                )->nullable();

                $table->text(
                    'last_error'
                )->nullable();

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'publication_id',
                    'ripost_publication_fk'
                )
                    ->references('id')
                    ->on(
                        'report_import_publications'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'report_import_id',
                    'ripost_report_import_fk'
                )
                    ->references('id')
                    ->on('report_imports')
                    ->restrictOnDelete();

                /*
                 * Hard DB idempotency:
                 * one posting per import/owner/currency.
                 */
                $table->unique(
                    [
                        'report_import_id',
                        'owner_type',
                        'owner_id',
                        'currency',
                    ],
                    'ripost_import_owner_currency_unique'
                );

                $table->index(
                    [
                        'publication_id',
                        'status',
                    ],
                    'ripost_publication_status_idx'
                );

                $table->index(
                    'royalty_statement_id',
                    'ripost_statement_idx'
                );

                $table->index(
                    'wallet_transaction_id',
                    'ripost_wallet_tx_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'report_import_postings'
        );

        Schema::dropIfExists(
            'report_import_publications'
        );
    }
};
