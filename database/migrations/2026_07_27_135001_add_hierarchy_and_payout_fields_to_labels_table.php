<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->foreignId('parent_label_id')
                ->nullable()
                ->after('id')
                ->constrained('labels')
                ->nullOnDelete();

            $table->string('label_type', 30)
                ->default('label')
                ->after('parent_label_id')
                ->index();

            $table->string('payout_cycle', 20)
                ->default('monthly')
                ->after('currency')
                ->index();

            $table->decimal('minimum_withdrawal_amount', 12, 2)
                ->default(5000)
                ->after('payout_cycle');

            $table->boolean('can_access_catalogue')
                ->default(true);

            $table->boolean('can_access_royalties')
                ->default(true);

            $table->boolean('can_access_reports')
                ->default(true);

            $table->boolean('can_access_wallet')
                ->default(true);

            $table->boolean('can_withdraw')
                ->default(true);

            $table->index(['parent_label_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->dropForeign(['parent_label_id']);
            $table->dropIndex(['parent_label_id', 'status']);

            $table->dropColumn([
                'parent_label_id',
                'label_type',
                'payout_cycle',
                'minimum_withdrawal_amount',
                'can_access_catalogue',
                'can_access_royalties',
                'can_access_reports',
                'can_access_wallet',
                'can_withdraw',
            ]);
        });
    }
};
