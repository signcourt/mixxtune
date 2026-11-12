<?php

namespace Tests\Unit\V3;

use App\Models\Finance\RecoupmentPlan;
use App\Services\V3\RecoupmentCalculationService;
use PHPUnit\Framework\TestCase;

class RecoupmentCalculationServiceTest extends TestCase
{
    private RecoupmentCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service =
            new RecoupmentCalculationService();
    }

    public function test_active_plan_applies_recovery_uplift(): void
    {
        $plan = new RecoupmentPlan([
            'base_percentage' => 20,
            'recovery_uplift_percentage' => 10,
            'maximum_recovery_percentage' => 10,
            'outstanding_amount' => 145000,
            'status' => 'active',
        ]);

        $result = $this->service->preview(
            $plan,
            100000
        );

        $this->assertSame(
            20.0,
            $result['base_percentage']
        );

        $this->assertSame(
            10.0,
            $result['recovery_percentage']
        );

        $this->assertSame(
            10.0,
            $result[
                'effective_beneficiary_percentage'
            ]
        );

        $this->assertSame(
            20000.0,
            $result['base_beneficiary_amount']
        );

        $this->assertSame(
            10000.0,
            $result['applied_recovery_amount']
        );

        $this->assertSame(
            10000.0,
            $result['beneficiary_payable']
        );

        $this->assertSame(
            135000.0,
            $result['outstanding_after']
        );

        $this->assertFalse(
            $result['will_complete']
        );
    }

    public function test_recovery_never_exceeds_outstanding_balance(): void
    {
        $plan = new RecoupmentPlan([
            'base_percentage' => 20,
            'recovery_uplift_percentage' => 10,
            'maximum_recovery_percentage' => 10,
            'outstanding_amount' => 5000,
            'status' => 'active',
        ]);

        $result = $this->service->preview(
            $plan,
            100000
        );

        $this->assertSame(
            10000.0,
            $result['calculated_recovery_amount']
        );

        $this->assertSame(
            5000.0,
            $result['applied_recovery_amount']
        );

        $this->assertSame(
            15000.0,
            $result['beneficiary_payable']
        );

        $this->assertEquals(
            0.0,
            $result['outstanding_after']
        );

        $this->assertTrue(
            $result['will_complete']
        );
    }

    public function test_completed_plan_reverts_to_base_percentage(): void
    {
        $plan = new RecoupmentPlan([
            'base_percentage' => 20,
            'recovery_uplift_percentage' => 10,
            'maximum_recovery_percentage' => 10,
            'outstanding_amount' => 0,
            'status' => 'completed',
        ]);

        $result = $this->service->preview(
            $plan,
            100000
        );

        $this->assertSame(
            20.0,
            $result[
                'effective_beneficiary_percentage'
            ]
        );

        $this->assertSame(
            0.0,
            $result['recovery_percentage']
        );

        $this->assertSame(
            0.0,
            $result['applied_recovery_amount']
        );

        $this->assertSame(
            20000.0,
            $result['beneficiary_payable']
        );

        $this->assertEquals(
            0.0,
            $result['outstanding_after']
        );
    }

    public function test_recovery_uplift_is_no_longer_capped_by_base_percentage(): void
    {
        // MIXXTUNE_RECOVERY_NET_BASIS_V1: the old "recovery <= owner base share"
        // cap is gone, so a recovery % above base is honoured.
        $plan = new RecoupmentPlan([
            'base_percentage' => 20,
            'recovery_uplift_percentage' => 50,
            'maximum_recovery_percentage' => null,
            'outstanding_amount' => 100000,
            'status' => 'active',
        ]);

        $result = $this->service->preview(
            $plan,
            100000
        );

        $this->assertSame(
            50.0,
            $result['recovery_percentage']
        );
    }

    public function test_maximum_recovery_percentage_caps_uplift(): void
    {
        $plan = new RecoupmentPlan([
            'base_percentage' => 40,
            'recovery_uplift_percentage' => 20,
            'maximum_recovery_percentage' => 12,
            'outstanding_amount' => 100000,
            'status' => 'active',
        ]);

        $result = $this->service->preview(
            $plan,
            100000
        );

        $this->assertSame(
            12.0,
            $result['recovery_percentage']
        );

        $this->assertSame(
            28.0,
            $result[
                'effective_beneficiary_percentage'
            ]
        );

        $this->assertSame(
            12000.0,
            $result['applied_recovery_amount']
        );

        $this->assertSame(
            28000.0,
            $result['beneficiary_payable']
        );
    }

    public function test_negative_source_amount_is_treated_as_zero(): void
    {
        $plan = new RecoupmentPlan([
            'base_percentage' => 20,
            'recovery_uplift_percentage' => 10,
            'outstanding_amount' => 100000,
            'status' => 'active',
        ]);

        $result = $this->service->preview(
            $plan,
            -5000
        );

        $this->assertSame(
            0.0,
            $result['source_amount']
        );

        $this->assertSame(
            0.0,
            $result['applied_recovery_amount']
        );

        $this->assertSame(
            0.0,
            $result['beneficiary_payable']
        );

        $this->assertSame(
            100000.0,
            $result['outstanding_after']
        );
    }
    public function test_statement_payable_preview_reconstructs_source_base(): void
    {
        $plan = new RecoupmentPlan([
            'base_percentage' => 70,
            'recovery_uplift_percentage' => 10,
            'maximum_recovery_percentage' => 10,
            'outstanding_amount' => 1000,
            'status' => 'active',
        ]);

        $result = app(
            RecoupmentCalculationService::class
        )->previewStatementPayable(
            $plan,
            1750
        );

        $this->assertEquals(
            2500,
            $result['reconstructed_source_amount']
        );

        $this->assertEquals(
            250,
            $result['calculated_recovery_amount']
        );

        $this->assertEquals(
            250,
            $result['applied_recovery_amount']
        );

        $this->assertEquals(
            1500,
            $result['statement_payable_after']
        );

        $this->assertEquals(
            750,
            $result['outstanding_after']
        );
    }

    public function test_statement_recovery_is_capped_by_outstanding(): void
    {
        $plan = new RecoupmentPlan([
            'base_percentage' => 70,
            'recovery_uplift_percentage' => 10,
            'maximum_recovery_percentage' => 10,
            'outstanding_amount' => 100,
            'status' => 'active',
        ]);

        $result = app(
            RecoupmentCalculationService::class
        )->previewStatementPayable(
            $plan,
            1750
        );

        $this->assertEquals(
            100,
            $result['applied_recovery_amount']
        );

        $this->assertEquals(
            1650,
            $result['statement_payable_after']
        );

        $this->assertEquals(
            0,
            $result['outstanding_after']
        );

        $this->assertTrue(
            $result['will_complete']
        );
    }

    public function test_statement_recovery_never_exceeds_payable(): void
    {
        $plan = new RecoupmentPlan([
            'base_percentage' => 20,
            'recovery_uplift_percentage' => 20,
            'maximum_recovery_percentage' => 20,
            'outstanding_amount' => 100000,
            'status' => 'active',
        ]);

        $result = app(
            RecoupmentCalculationService::class
        )->previewStatementPayable(
            $plan,
            200
        );

        $this->assertEquals(
            1000,
            $result['reconstructed_source_amount']
        );

        $this->assertEquals(
            200,
            $result['applied_recovery_amount']
        );

        $this->assertEquals(
            0,
            $result['statement_payable_after']
        );
    }

    public function test_zero_base_statement_preview_is_safe(): void
    {
        $plan = new RecoupmentPlan([
            'base_percentage' => 0,
            'recovery_uplift_percentage' => 0,
            'maximum_recovery_percentage' => 0,
            'outstanding_amount' => 1000,
            'status' => 'active',
        ]);

        $result = app(
            RecoupmentCalculationService::class
        )->previewStatementPayable(
            $plan,
            500
        );

        $this->assertEquals(
            0,
            $result['applied_recovery_amount']
        );

        $this->assertEquals(
            500,
            $result['statement_payable_after']
        );

        $this->assertEquals(
            1000,
            $result['outstanding_after']
        );
    }

    public function test_allocated_statement_recovery_is_percent_of_net_earning(): void
    {
        // MIXXTUNE_RECOVERY_NET_BASIS_V1: recovery is a % of the owner's NET
        // earning (what is left after the label rate), not of reconstructed source.
        $plan = new RecoupmentPlan([
            'base_percentage' => 70,
            'recovery_uplift_percentage' => 10,
            'maximum_recovery_percentage' => 10,
            'outstanding_amount' => 1000,
            'status' => 'active',
        ]);

        $result = app(
            RecoupmentCalculationService::class
        )->previewAllocatedStatement(
            $plan,
            1750,
            1575
        );

        // Recovery = 10% of the net earning 1575 = 157.5.
        $this->assertEquals(
            157.5,
            $result['calculated_recovery_amount']
        );

        $this->assertEquals(
            157.5,
            $result['applied_recovery_amount']
        );

        $this->assertEquals(
            1417.5,
            $result['current_payable_after']
        );

        $this->assertEquals(
            842.5,
            $result['outstanding_after']
        );
    }

    public function test_allocated_statement_recovery_is_capped_by_current_net_payable(): void
    {
        // 100% recovery of the net earning: the whole earning goes to the advance
        // and net payable never goes negative.
        $plan = new RecoupmentPlan([
            'base_percentage' => 20,
            'recovery_uplift_percentage' => 100,
            'maximum_recovery_percentage' => null,
            'outstanding_amount' => 100000,
            'status' => 'active',
        ]);

        $result = app(
            RecoupmentCalculationService::class
        )->previewAllocatedStatement(
            $plan,
            200,
            50
        );

        // Recovery = 100% of net 50 = 50; outstanding drops by 50.
        $this->assertEquals(
            50,
            $result['calculated_recovery_amount']
        );

        $this->assertEquals(
            50,
            $result['applied_recovery_amount']
        );

        $this->assertEquals(
            0,
            $result['current_payable_after']
        );

        $this->assertEquals(
            99950,
            $result['outstanding_after']
        );
    }

    public function test_allocated_statement_zero_payable_does_not_recover(): void
    {
        $plan = new RecoupmentPlan([
            'base_percentage' => 70,
            'recovery_uplift_percentage' => 10,
            'maximum_recovery_percentage' => 10,
            'outstanding_amount' => 1000,
            'status' => 'active',
        ]);

        $result = app(
            RecoupmentCalculationService::class
        )->previewAllocatedStatement(
            $plan,
            1750,
            0
        );

        $this->assertEquals(
            0,
            $result['applied_recovery_amount']
        );

        $this->assertEquals(
            0,
            $result['current_payable_after']
        );

        $this->assertEquals(
            1000,
            $result['outstanding_after']
        );
    }

}
