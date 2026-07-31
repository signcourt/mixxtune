<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wallets')) {
            Schema::create('wallets', function (Blueprint $table) {
                $table->id();
                $table->string('public_id', 40)->unique();

                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('artist_id')
                    ->nullable()
                    ->constrained('artists')
                    ->nullOnDelete();

                $table->foreignId('label_id')
                    ->nullable()
                    ->constrained('labels')
                    ->nullOnDelete();

                $table->string('currency', 3)->default('INR');

                $table->decimal('available_balance', 18, 8)
                    ->default(0);

                $table->decimal('pending_balance', 18, 8)
                    ->default(0);

                $table->decimal('lifetime_credits', 18, 8)
                    ->default(0);

                $table->decimal('lifetime_debits', 18, 8)
                    ->default(0);

                $table->string('status', 30)->default('active');

                $table->timestamps();

                $table->unique(
                    ['label_id', 'currency'],
                    'wallets_label_currency_unique'
                );

                $table->unique(
                    ['artist_id', 'currency'],
                    'wallets_artist_currency_unique'
                );

                $table->index(['user_id', 'currency']);
            });
        }

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn(
                'wallet_transactions',
                'wallet_id'
            )) {
                $table->foreignId('wallet_id')
                    ->nullable()
                    ->after('public_id')
                    ->constrained('wallets')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn(
                'wallet_transactions',
                'label_id'
            )) {
                $table->foreignId('label_id')
                    ->nullable()
                    ->after('artist_id')
                    ->constrained('labels')
                    ->nullOnDelete();
            }
        });

        if (!Schema::hasTable('withdrawals')) {
            Schema::create('withdrawals', function (Blueprint $table) {
                $table->id();
                $table->string('public_id', 40)->unique();
                $table->string('withdrawal_number', 40)->unique();

                $table->foreignId('wallet_id')
                    ->constrained('wallets')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('artist_id')
                    ->nullable()
                    ->constrained('artists')
                    ->nullOnDelete();

                $table->foreignId('label_id')
                    ->nullable()
                    ->constrained('labels')
                    ->nullOnDelete();

                $table->decimal('amount', 18, 8);
                $table->string('currency', 3)->default('INR');

                $table->string('status', 30)
                    ->default('pending');

                $table->string('payment_method', 50)
                    ->nullable();

                $table->string('payment_reference', 100)
                    ->nullable();

                $table->text('note')->nullable();

                $table->timestamp('requested_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('rejected_at')->nullable();

                $table->foreignId('approved_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['wallet_id', 'status']);
                $table->index(['label_id', 'status']);
                $table->index(['artist_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('withdrawals')) {
            Schema::dropIfExists('withdrawals');
        }

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (Schema::hasColumn(
                'wallet_transactions',
                'label_id'
            )) {
                $table->dropConstrainedForeignId('label_id');
            }

            if (Schema::hasColumn(
                'wallet_transactions',
                'wallet_id'
            )) {
                $table->dropConstrainedForeignId('wallet_id');
            }
        });

        Schema::dropIfExists('wallets');
    }
};
