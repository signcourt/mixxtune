<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W181 — MIXXTUNE_ADVANCE_CHARGE_RATE_V2
 *
 * Record, on each recovery, the advance-charge percentage in force and the
 * rupee amount taken as company profit at that moment. Because the charge %
 * is editable on the plan, profit must be read from these per-event values
 * (not recomputed from the plan's current %), so history stays correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recoupment_recoveries', function (Blueprint $table) {
            if (! Schema::hasColumn('recoupment_recoveries', 'advance_charge_percentage')) {
                $table->decimal('advance_charge_percentage', 8, 4)->default(0)->after('recovery_percentage');
            }
            if (! Schema::hasColumn('recoupment_recoveries', 'advance_charge_amount')) {
                $table->decimal('advance_charge_amount', 18, 8)->default(0)->after('applied_recovery_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recoupment_recoveries', function (Blueprint $table) {
            foreach (['advance_charge_percentage', 'advance_charge_amount'] as $col) {
                if (Schema::hasColumn('recoupment_recoveries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
