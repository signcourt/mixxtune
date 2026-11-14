<?php

namespace Tests\Feature\V3;

use App\Models\Finance\RecoupmentPlan;
use App\Models\User;
use App\Services\V3\RecoupmentCalculationService;
use App\Services\V3\RecoupmentManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 * W181 — MIXXTUNE_ADVANCE_CHARGE_RATE_V2
 * The advance charge reduces the label rate by C percentage points (charge =
 * C% of GROSS). Recovery is a clean 100% of the (reduced) net. Each recovery
 * records the charge % + amount so an editable charge % keeps correct history.
 */
class AdvanceChargeRateV2Test extends TestCase
{
    use RefreshDatabase;

    private RecoupmentManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecoupmentManagementService(new RecoupmentCalculationService());
    }

    /**
     * The charge is C percentage points off the label rate: on ₹100 gross at
     * label 80% with a 1% charge, the owner's net is ₹79 (not ₹79.20) and the
     * charge is ₹1 — i.e. charge = C% of gross, not C% of net.
     */
    public function test_charge_is_a_percentage_point_off_the_label_rate(): void
    {
        $gross = 100.0;
        $labelRate = 80.0;   // L
        $chargePct = 1.0;    // C

        $net = round($gross * ($labelRate / 100), 8);            // 80
        $charge = round($gross * ($chargePct / 100), 8);         // 1  (W181: % of gross)
        $ownerNet = round($net - $charge, 8);                    // 79

        $this->assertEqualsWithDelta(1.0, $charge, 0.0001);
        $this->assertEqualsWithDelta(79.0, $ownerNet, 0.0001);
        // effective owner rate on gross = 79% = L - C
        $this->assertEqualsWithDelta($labelRate - $chargePct, $ownerNet / $gross * 100, 0.0001);
    }

    /**
     * applyStatementRecovery records the charge % and amount passed in context,
     * and recovers a clean 100% of the net (no commission carve-out).
     */
    public function test_recovery_records_charge_and_is_a_clean_sweep(): void
    {
        $user = User::factory()->create();

        $plan = $this->service->createPlan(
            $user,
            [
                'base_percentage' => 80,
                'recovery_uplift_percentage' => 100,   // recover 100% of net
                'maximum_recovery_percentage' => 100,
                'advance_charge_percentage' => 1,
                'initial_amount' => 10000,
                'initial_category' => 'advance',
                'initial_title' => 'Advance',
            ],
            $user
        );

        $this->assertSame('active', $plan->status);

        // Owner earned ₹100 gross -> net ₹80 -> charge ₹1 (of gross) -> ₹79 net.
        $recovery = $this->service->applyStatementRecovery(
            $plan,
            100.0,   // allocatedGross
            79.0,    // currentPayable (net after the ₹1 charge)
            [
                'reporting_month' => now()->format('Y-m'),
                'reference' => 'W181 test',
                'idempotency_key' => 'w181-test-1',
                'advance_charge_amount' => 1.0,
                'advance_charge_percentage' => 1.0,
            ]
        );

        $this->assertNotNull($recovery);
        // 100% of the ₹79 net is recovered into the advance.
        $this->assertEqualsWithDelta(79.0, (float) $recovery->applied_recovery_amount, 0.0001);
        // the charge is recorded on the recovery for the profit view
        $this->assertEqualsWithDelta(1.0, (float) $recovery->advance_charge_amount, 0.0001);
        $this->assertEqualsWithDelta(1.0, (float) $recovery->advance_charge_percentage, 0.0001);

        $this->assertDatabaseHas('recoupment_recoveries', [
            'id' => $recovery->id,
            'recoupment_plan_id' => $plan->id,
        ]);
    }

    /** The per-recovery charge amounts sum to the company's profit for the plan. */
    public function test_charge_profit_sums_from_recovery_rows(): void
    {
        $user = User::factory()->create();
        $plan = $this->service->createPlan(
            $user,
            [
                'base_percentage' => 80,
                'recovery_uplift_percentage' => 100,
                'maximum_recovery_percentage' => 100,
                'advance_charge_percentage' => 1,
                'initial_amount' => 10000,
                'initial_category' => 'advance',
                'initial_title' => 'Advance',
            ],
            $user
        );

        foreach ([['g' => 100.0, 'n' => 79.0, 'c' => 1.0], ['g' => 200.0, 'n' => 158.0, 'c' => 2.0]] as $i => $row) {
            $this->service->applyStatementRecovery($plan, $row['g'], $row['n'], [
                'reporting_month' => now()->format('Y-m'),
                'idempotency_key' => 'w181-profit-'.$i,
                'advance_charge_amount' => $row['c'],
                'advance_charge_percentage' => 1.0,
            ]);
        }

        $profit = (float) DB::table('recoupment_recoveries')
            ->where('recoupment_plan_id', $plan->id)
            ->sum('advance_charge_amount');

        $this->assertEqualsWithDelta(3.0, $profit, 0.0001);
    }
}
