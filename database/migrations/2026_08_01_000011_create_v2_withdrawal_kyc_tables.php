<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payout_profiles')) {
            Schema::create(
                'payout_profiles',
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
                        'account_holder_name'
                    )->nullable();

                    $table->text(
                        'bank_account_number'
                    )->nullable();

                    $table->string(
                        'bank_name'
                    )->nullable();

                    $table->string(
                        'ifsc_code',
                        30
                    )->nullable();

                    $table->string(
                        'branch_name'
                    )->nullable();

                    $table->string(
                        'upi_id'
                    )->nullable();

                    $table->string(
                        'pan_number',
                        20
                    )->nullable();

                    $table->string(
                        'gst_number',
                        30
                    )->nullable();

                    $table->string(
                        'address_line_1'
                    )->nullable();

                    $table->string(
                        'address_line_2'
                    )->nullable();

                    $table->string(
                        'city',
                        100
                    )->nullable();

                    $table->string(
                        'state',
                        100
                    )->nullable();

                    $table->string(
                        'postal_code',
                        20
                    )->nullable();

                    $table->string(
                        'country_code',
                        2
                    )->default('IN');

                    $table->string(
                        'kyc_status',
                        30
                    )->default('pending');

                    $table->text(
                        'kyc_notes'
                    )->nullable();

                    $table->timestamp(
                        'verified_at'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'verified_by'
                    )->nullable();

                    $table->timestamps();

                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();

                    $table->index('kyc_status');
                }
            );
        }

        if (!Schema::hasTable('withdrawal_requests')) {
            Schema::create(
                'withdrawal_requests',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->string(
                        'request_number',
                        50
                    )->unique();

                    $table->unsignedBigInteger(
                        'user_id'
                    );

                    $table->unsignedBigInteger(
                        'wallet_account_id'
                    );

                    $table->unsignedBigInteger(
                        'payout_profile_id'
                    );

                    $table->decimal(
                        'amount',
                        20,
                        8
                    );

                    $table->decimal(
                        'fee_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'tax_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'net_amount',
                        20,
                        8
                    );

                    $table->string(
                        'currency',
                        10
                    )->default('INR');

                    $table->string(
                        'payment_method',
                        30
                    )->default('bank');

                    $table->string(
                        'status',
                        30
                    )->default('pending');

                    $table->text(
                        'request_note'
                    )->nullable();

                    $table->text(
                        'admin_note'
                    )->nullable();

                    $table->text(
                        'rejection_reason'
                    )->nullable();

                    $table->string(
                        'payment_reference',
                        150
                    )->nullable();

                    $table->timestamp(
                        'approved_at'
                    )->nullable();

                    $table->timestamp(
                        'rejected_at'
                    )->nullable();

                    $table->timestamp(
                        'paid_at'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'processed_by'
                    )->nullable();

                    $table->timestamps();

                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();

                    $table->foreign(
                        'wallet_account_id'
                    )
                        ->references('id')
                        ->on('wallet_accounts')
                        ->cascadeOnDelete();

                    $table->foreign(
                        'payout_profile_id'
                    )
                        ->references('id')
                        ->on('payout_profiles');

                    $table->index([
                        'status',
                        'created_at',
                    ]);

                    $table->index([
                        'user_id',
                        'status',
                    ]);
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Financial and KYC records should not be
         * removed automatically in production.
         */
    }
};
