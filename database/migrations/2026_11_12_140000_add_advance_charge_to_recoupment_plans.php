<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W170: advance charge on a recoupment plan.
 *
 * While an advance is being recovered, the owner's applicable (label) rate is
 * reduced by this many points — e.g. 80% with a 10-point advance charge pays the
 * owner at 70% and routes the extra 10 points to the publisher's profit. When
 * the advance clears (outstanding = 0) the charge no longer applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recoupment_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('recoupment_plans', 'advance_charge_percentage')) {
                $table->decimal('advance_charge_percentage', 7, 4)->default(0)->after('maximum_recovery_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recoupment_plans', function (Blueprint $table) {
            if (Schema::hasColumn('recoupment_plans', 'advance_charge_percentage')) {
                $table->dropColumn('advance_charge_percentage');
            }
        });
    }
};
