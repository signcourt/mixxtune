<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MIXXTUNE_PAYOUT_CYCLE_V1 (W185b-1)
 *
 * Give artists the same payout-cycle field labels already have, so a per-owner
 * monthly/quarterly cycle can cover artist owners too. This migration ONLY adds
 * the column (default 'monthly'); no withdrawal behaviour reads it yet — that
 * wiring lands in a later, separate wave. Existing artists default to 'monthly'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('artists', 'payout_cycle')) {
            return;
        }

        Schema::table('artists', function (Blueprint $table) {
            $table->string('payout_cycle', 20)
                ->default('monthly')
                ->after('currency')
                ->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('artists', 'payout_cycle')) {
            return;
        }

        Schema::table('artists', function (Blueprint $table) {
            $table->dropIndex(['payout_cycle']);
            $table->dropColumn('payout_cycle');
        });
    }
};
