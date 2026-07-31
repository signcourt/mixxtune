<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('role');
            $table->string('label_name')->nullable()->after('phone');
            $table->string('country', 100)->nullable()->after('label_name');

            $table->string('account_status')
                ->default('active')
                ->after('country');

            $table->string('kyc_status')
                ->default('pending')
                ->after('account_status');

            $table->decimal('wallet_balance', 15, 2)
                ->default(0)
                ->after('kyc_status');

            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'label_name',
                'country',
                'account_status',
                'kyc_status',
                'wallet_balance',
                'last_login_at',
            ]);
        });
    }
};
