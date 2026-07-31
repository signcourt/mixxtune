<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->decimal('royalty_share_percentage', 5, 2)
                ->default(100.00)
                ->after('minimum_withdrawal_amount');

            $table->decimal('parent_commission_percentage', 5, 2)
                ->default(0.00)
                ->after('royalty_share_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->dropColumn([
                'royalty_share_percentage',
                'parent_commission_percentage',
            ]);
        });
    }
};
