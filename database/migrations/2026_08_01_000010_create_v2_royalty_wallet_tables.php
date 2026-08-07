<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('royalty_statements')) {
            Schema::create(
                'royalty_statements',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->unsignedBigInteger(
                        'artist_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'label_id'
                    )->nullable();

                    $table->string(
                        'statement_month',
                        10
                    );

                    $table->string(
                        'currency',
                        10
                    )->default('INR');

                    $table->decimal(
                        'gross_earnings',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'commission_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'tax_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'other_deductions',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'net_payable',
                        20,
                        8
                    )->default(0);

                    $table->string(
                        'status',
                        30
                    )->default('pending');

                    $table->timestamp(
                        'approved_at'
                    )->nullable();

                    $table->timestamp(
                        'available_at'
                    )->nullable();

                    $table->timestamp(
                        'paid_at'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'approved_by'
                    )->nullable();

                    $table->text(
                        'notes'
                    )->nullable();

                    $table->timestamps();

                    $table->unique(
                        [
                            'artist_id',
                            'label_id',
                            'statement_month',
                            'currency',
                        ],
                        'royalty_statement_owner_month_unique'
                    );

                    $table->index([
                        'status',
                        'statement_month',
                    ]);

                    $table->index('artist_id');
                    $table->index('label_id');
                }
            );
        }

        if (!Schema::hasTable('wallet_accounts')) {
            Schema::create(
                'wallet_accounts',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->unsignedBigInteger(
                        'user_id'
                    )->unique();

                    $table->string(
                        'currency',
                        10
                    )->default('INR');

                    $table->decimal(
                        'pending_balance',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'available_balance',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'withdrawn_balance',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'lifetime_earnings',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'hold_balance',
                        20,
                        8
                    )->default(0);

                    $table->timestamps();

                    $table->index('currency');

                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                }
            );
        }

        if (!Schema::hasTable('wallet_transactions')) {
            Schema::create(
                'wallet_transactions',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->unsignedBigInteger(
                        'wallet_account_id'
                    );

                    $table->string(
                        'type',
                        30
                    );

                    $table->string(
                        'category',
                        50
                    );

                    $table->decimal(
                        'amount',
                        20,
                        8
                    );

                    $table->decimal(
                        'balance_before',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'balance_after',
                        20,
                        8
                    )->default(0);

                    $table->string(
                        'currency',
                        10
                    )->default('INR');

                    $table->string(
                        'reference_type',
                        100
                    )->nullable();

                    $table->unsignedBigInteger(
                        'reference_id'
                    )->nullable();

                    $table->string(
                        'reference_code',
                        100
                    )->nullable();

                    $table->text(
                        'description'
                    )->nullable();

                    $table->json(
                        'meta'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'created_by'
                    )->nullable();

                    $table->timestamps();

                    $table->index([
                        'wallet_account_id',
                        'created_at',
                    ]);

                    $table->index([
                        'reference_type',
                        'reference_id',
                    ]);

                    $table->foreign(
                        'wallet_account_id'
                    )
                        ->references('id')
                        ->on('wallet_accounts')
                        ->cascadeOnDelete();
                }
            );
        }

        if (!Schema::hasTable('royalty_allocations')) {
            Schema::create(
                'royalty_allocations',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->unsignedBigInteger(
                        'royalty_statement_id'
                    );

                    $table->unsignedBigInteger(
                        'report_row_id'
                    );

                    $table->unsignedBigInteger(
                        'release_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'track_id'
                    )->nullable();

                    $table->decimal(
                        'gross_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'net_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'share_percentage',
                        8,
                        4
                    )->default(100);

                    $table->timestamps();

                    $table->unique(
                        [
                            'royalty_statement_id',
                            'report_row_id',
                        ],
                        'royalty_statement_report_row_unique'
                    );

                    $table->index('release_id');
                    $table->index('track_id');

                    $table->foreign(
                        'royalty_statement_id'
                    )
                        ->references('id')
                        ->on('royalty_statements')
                        ->cascadeOnDelete();

                    $table->foreign(
                        'report_row_id'
                    )
                        ->references('id')
                        ->on('report_rows')
                        ->cascadeOnDelete();
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Financial records must not be deleted
         * automatically in production.
         */
    }
};
