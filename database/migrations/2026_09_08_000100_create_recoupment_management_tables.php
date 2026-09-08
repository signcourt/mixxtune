<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recoupment_plans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users');

            $table->foreignId('label_id')
                ->nullable()
                ->constrained('labels')
                ->nullOnDelete();

            $table->foreignId('artist_id')
                ->nullable()
                ->constrained('artists')
                ->nullOnDelete();

            $table->string('plan_number')->unique();

            $table->string('title')->nullable();

            /*
             * Contractual/base beneficiary percentage.
             * This value is a snapshot and must never be overwritten
             * merely because a recovery plan becomes active.
             */
            $table->decimal('base_percentage', 8, 4);

            /*
             * Additional percentage retained for recoupment.
             *
             * Example:
             * beneficiary base share = 20%
             * recovery uplift        = 10 percentage points
             * beneficiary payable    = 10% while recouping
             *
             * The exact calculation is performed by the
             * recoupment service, not by mutating the agreement.
             */
            $table->decimal(
                'recovery_uplift_percentage',
                8,
                4
            )->default(0);

            $table->decimal(
                'maximum_recovery_percentage',
                8,
                4
            )->nullable();

            $table->decimal(
                'total_recoverable_amount',
                18,
                8
            )->default(0);

            $table->decimal(
                'total_recovered_amount',
                18,
                8
            )->default(0);

            $table->decimal(
                'outstanding_amount',
                18,
                8
            )->default(0);

            $table->string('recovery_method')
                ->default('percentage');

            $table->string('status')
                ->default('active');

            $table->date('starts_on')->nullable();
            $table->date('completed_on')->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'user_id',
                'status',
            ]);

            $table->index([
                'label_id',
                'status',
            ]);

            $table->index([
                'artist_id',
                'status',
            ]);
        });

        Schema::create(
            'recoupment_expenses',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('recoupment_plan_id')
                    ->constrained('recoupment_plans')
                    ->cascadeOnDelete();

                /*
                 * advance
                 * youtube_promotion
                 * meta_ads
                 * google_ads
                 * influencer
                 * pr
                 * marketing
                 * production
                 * legal
                 * distribution
                 * other
                 */
                $table->string('category');

                $table->string('reference_number')
                    ->nullable();

                $table->string('title');

                $table->text('description')
                    ->nullable();

                $table->decimal('amount', 18, 8);

                $table->boolean('is_recoverable')
                    ->default(true);

                $table->date('expense_date')
                    ->nullable();

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index([
                    'recoupment_plan_id',
                    'category',
                ]);
            }
        );

        Schema::create(
            'recoupment_recoveries',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('recoupment_plan_id')
                    ->constrained('recoupment_plans');

                $table->foreignId('royalty_statement_id')
                    ->nullable()
                    ->constrained('royalty_statements')
                    ->nullOnDelete();

                $table->foreignId('wallet_transaction_id')
                    ->nullable()
                    ->constrained('wallet_transactions')
                    ->nullOnDelete();

                $table->decimal(
                    'source_amount',
                    18,
                    8
                )->default(0);

                $table->decimal(
                    'base_percentage',
                    8,
                    4
                );

                $table->decimal(
                    'recovery_percentage',
                    8,
                    4
                );

                $table->decimal(
                    'calculated_recovery_amount',
                    18,
                    8
                );

                $table->decimal(
                    'applied_recovery_amount',
                    18,
                    8
                );

                $table->decimal(
                    'outstanding_before',
                    18,
                    8
                );

                $table->decimal(
                    'outstanding_after',
                    18,
                    8
                );

                $table->string('reporting_month')
                    ->nullable();

                $table->string('reference')
                    ->nullable();

                /*
                 * Stable source identifier used to make
                 * automated royalty/report recovery posting
                 * safe to retry.
                 *
                 * NULL remains allowed for manual recovery
                 * entries that have no external source key.
                 */
                $table->string(
                    'idempotency_key',
                    191
                )->nullable();

                $table->timestamps();

                $table->index([
                    'recoupment_plan_id',
                    'reporting_month',
                ]);

                $table->unique(
                    [
                        'recoupment_plan_id',
                        'idempotency_key',
                    ],
                    'recoupment_recoveries_idempotency_unique'
                );
            }
        );

        Schema::create(
            'recoupment_documents',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('recoupment_plan_id')
                    ->constrained('recoupment_plans')
                    ->cascadeOnDelete();

                $table->foreignId('recoupment_expense_id')
                    ->nullable()
                    ->constrained('recoupment_expenses')
                    ->nullOnDelete();

                $table->string('document_type');
                $table->string('original_name');
                $table->string('storage_disk')
                    ->default('local');
                $table->string('storage_path');
                $table->string('mime_type')
                    ->nullable();

                $table->unsignedBigInteger('file_size')
                    ->nullable();

                $table->foreignId('uploaded_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index([
                    'recoupment_plan_id',
                    'document_type',
                ]);
            }
        );

        Schema::create(
            'financial_adjustments',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')
                    ->constrained('users');

                $table->foreignId('wallet_transaction_id')
                    ->nullable()
                    ->constrained('wallet_transactions')
                    ->nullOnDelete();

                $table->foreignId('reversal_of_id')
                    ->nullable()
                    ->constrained('financial_adjustments')
                    ->nullOnDelete();

                $table->string('adjustment_number')
                    ->unique();

                /*
                 * Stable client/request key used to make
                 * financial adjustment posting idempotent.
                 *
                 * A browser retry or double-submit with the
                 * same key must never mutate the wallet twice.
                 */
                $table->string(
                    'idempotency_key',
                    191
                )->nullable();

                $table->unique(
                    'idempotency_key',
                    'financial_adjustments_idempotency_unique'
                );

                $table->string('type');

                /*
                 * credit = increases beneficiary wallet
                 * debit  = decreases beneficiary wallet
                 */
                $table->string('direction');

                $table->decimal('amount', 18, 8);

                $table->string('reference_number')
                    ->nullable();

                $table->text('reason');

                $table->text('internal_note')
                    ->nullable();

                $table->date('effective_date')
                    ->nullable();

                $table->string('status')
                    ->default('posted');

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('reversed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('reversed_at')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'user_id',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_adjustments');
        Schema::dropIfExists('recoupment_documents');
        Schema::dropIfExists('recoupment_recoveries');
        Schema::dropIfExists('recoupment_expenses');
        Schema::dropIfExists('recoupment_plans');
    }
};
