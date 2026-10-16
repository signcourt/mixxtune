<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'report_unclaimed_revenues',
            function (Blueprint $table) {
                $table->id();

                /*
                 * MIXXTUNE_UNCLAIMED_REVENUE_LEDGER_V1
                 *
                 * One financial hold per publication posting.
                 * A hold is not a wallet credit.
                 */
                $table->string(
                    'hold_key',
                    191
                )->unique();

                $table->unsignedBigInteger(
                    'report_import_id'
                );

                $table->unsignedBigInteger(
                    'report_import_posting_id'
                )->unique();

                $table->unsignedBigInteger(
                    'royalty_statement_id'
                )->nullable();

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

                $table->decimal(
                    'gross_amount',
                    24,
                    8
                )->default(0);

                $table->decimal(
                    'net_amount',
                    24,
                    8
                )->default(0);

                $table->string(
                    'status',
                    30
                )->default('held');

                $table->unsignedBigInteger(
                    'claimed_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'wallet_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'wallet_transaction_id'
                )->nullable();

                $table->timestamp(
                    'claimed_at'
                )->nullable();

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'report_import_id',
                    'rur_import_fk'
                )
                    ->references('id')
                    ->on('report_imports')
                    ->restrictOnDelete();

                $table->foreign(
                    'report_import_posting_id',
                    'rur_posting_fk'
                )
                    ->references('id')
                    ->on('report_import_postings')
                    ->restrictOnDelete();

                $table->foreign(
                    'royalty_statement_id',
                    'rur_statement_fk'
                )
                    ->references('id')
                    ->on('royalty_statements')
                    ->nullOnDelete();

                $table->foreign(
                    'claimed_by_user_id',
                    'rur_claim_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();

                $table->foreign(
                    'wallet_id',
                    'rur_wallet_fk'
                )
                    ->references('id')
                    ->on('wallets')
                    ->restrictOnDelete();

                $table->foreign(
                    'wallet_transaction_id',
                    'rur_wallet_tx_fk'
                )
                    ->references('id')
                    ->on('wallet_transactions')
                    ->restrictOnDelete();

                $table->index(
                    [
                        'status',
                        'owner_type',
                        'owner_id',
                    ],
                    'rur_status_owner_idx'
                );

                $table->index(
                    [
                        'report_import_id',
                        'status',
                    ],
                    'rur_import_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'report_unclaimed_revenues'
        );
    }
};
