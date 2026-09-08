<?php

namespace Tests\Feature\V3;

use App\Models\Finance\RecoupmentPlan;
use App\Models\User;
use App\Services\V3\RecoupmentCalculationService;
use App\Services\V3\RecoupmentManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecoupmentManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecoupmentManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service =
            new RecoupmentManagementService(
                new RecoupmentCalculationService()
            );
    }

    public function test_plan_can_be_created_with_initial_advance(): void
    {
        $user = User::factory()->create();

        $plan = $this->service->createPlan(
            $user,
            [
                'base_percentage' => 20,
                'recovery_uplift_percentage' => 10,
                'maximum_recovery_percentage' => 10,
                'initial_amount' => 100000,
                'initial_category' => 'advance',
                'initial_title' => 'Signing Advance',
            ],
            $user
        );

        $this->assertSame(
            'active',
            $plan->status
        );

        $this->assertEquals(
            100000,
            $plan->total_recoverable_amount
        );

        $this->assertEquals(
            0,
            $plan->total_recovered_amount
        );

        $this->assertEquals(
            100000,
            $plan->outstanding_amount
        );

        $this->assertDatabaseHas(
            'recoupment_expenses',
            [
                'recoupment_plan_id' => $plan->id,
                'category' => 'advance',
                'title' => 'Signing Advance',
                'is_recoverable' => 1,
            ]
        );
    }

    public function test_recoverable_expenses_increase_outstanding_balance(): void
    {
        $user = User::factory()->create();

        $plan = $this->service->createPlan(
            $user,
            [
                'base_percentage' => 20,
                'recovery_uplift_percentage' => 10,
                'initial_amount' => 100000,
            ],
            $user
        );

        $this->service->addExpense(
            $plan,
            [
                'category' => 'youtube_promotion',
                'title' => 'YouTube Promotion',
                'amount' => 20000,
                'is_recoverable' => true,
            ],
            $user
        );

        $this->service->addExpense(
            $plan,
            [
                'category' => 'meta_ads',
                'title' => 'Instagram Ads',
                'amount' => 10000,
                'is_recoverable' => true,
            ],
            $user
        );

        $plan->refresh();

        $this->assertEquals(
            130000,
            $plan->total_recoverable_amount
        );

        $this->assertEquals(
            130000,
            $plan->outstanding_amount
        );

        $this->assertSame(
            'active',
            $plan->status
        );
    }

    public function test_non_recoverable_expense_does_not_increase_outstanding(): void
    {
        $user = User::factory()->create();

        $plan = $this->service->createPlan(
            $user,
            [
                'base_percentage' => 20,
                'recovery_uplift_percentage' => 10,
                'initial_amount' => 100000,
            ],
            $user
        );

        $this->service->addExpense(
            $plan,
            [
                'category' => 'legal',
                'title' => 'Company Legal Expense',
                'amount' => 25000,
                'is_recoverable' => false,
            ],
            $user
        );

        $plan->refresh();

        $this->assertEquals(
            100000,
            $plan->total_recoverable_amount
        );

        $this->assertEquals(
            100000,
            $plan->outstanding_amount
        );

        $this->assertDatabaseHas(
            'recoupment_expenses',
            [
                'recoupment_plan_id' => $plan->id,
                'category' => 'legal',
                'is_recoverable' => 0,
            ]
        );
    }

    public function test_recovery_updates_totals_and_outstanding(): void
    {
        $user = User::factory()->create();

        $plan = $this->service->createPlan(
            $user,
            [
                'base_percentage' => 20,
                'recovery_uplift_percentage' => 10,
                'maximum_recovery_percentage' => 10,
                'initial_amount' => 100000,
            ],
            $user
        );

        $recovery = $this->service->applyRecovery(
            $plan,
            100000,
            [
                'reporting_month' => '2026-08',
                'reference' => 'TEST-RECOVERY-001',
            ]
        );

        $this->assertNotNull($recovery);

        $this->assertEquals(
            10000,
            $recovery->applied_recovery_amount
        );

        $this->assertEquals(
            100000,
            $recovery->outstanding_before
        );

        $this->assertEquals(
            90000,
            $recovery->outstanding_after
        );

        $plan->refresh();

        $this->assertEquals(
            10000,
            $plan->total_recovered_amount
        );

        $this->assertEquals(
            90000,
            $plan->outstanding_amount
        );

        $this->assertSame(
            'active',
            $plan->status
        );
    }

    public function test_final_recovery_is_capped_and_auto_completes_plan(): void
    {
        $user = User::factory()->create();

        $plan = $this->service->createPlan(
            $user,
            [
                'base_percentage' => 20,
                'recovery_uplift_percentage' => 10,
                'maximum_recovery_percentage' => 10,
                'initial_amount' => 5000,
            ],
            $user
        );

        $recovery = $this->service->applyRecovery(
            $plan,
            100000,
            [
                'reporting_month' => '2026-08',
                'reference' => 'FINAL-RECOVERY',
            ]
        );

        $this->assertNotNull($recovery);

        /*
         * 10% of 100000 = 10000 calculated,
         * but only 5000 remains outstanding.
         */
        $this->assertEquals(
            10000,
            $recovery->calculated_recovery_amount
        );

        $this->assertEquals(
            5000,
            $recovery->applied_recovery_amount
        );

        $this->assertEquals(
            0,
            $recovery->outstanding_after
        );

        $plan->refresh();

        $this->assertEquals(
            5000,
            $plan->total_recovered_amount
        );

        $this->assertEquals(
            0,
            $plan->outstanding_amount
        );

        $this->assertSame(
            'completed',
            $plan->status
        );

        $this->assertNotNull(
            $plan->completed_on
        );

        $preview =
            (new RecoupmentCalculationService())
                ->preview(
                    $plan,
                    100000
                );

        /*
         * Completed plan must automatically fall back
         * to contractual/base percentage.
         */
        $this->assertSame(
            0.0,
            $preview['recovery_percentage']
        );

        $this->assertSame(
            20.0,
            $preview[
                'effective_beneficiary_percentage'
            ]
        );

        $this->assertEquals(
            20000,
            $preview['beneficiary_payable']
        );
    }

    public function test_new_recoverable_expense_reactivates_completed_plan(): void
    {
        $user = User::factory()->create();

        $plan = $this->service->createPlan(
            $user,
            [
                'base_percentage' => 20,
                'recovery_uplift_percentage' => 10,
                'initial_amount' => 5000,
            ],
            $user
        );

        $this->service->applyRecovery(
            $plan,
            100000
        );

        $plan->refresh();

        $this->assertSame(
            'completed',
            $plan->status
        );

        $this->service->addExpense(
            $plan,
            [
                'category' => 'google_ads',
                'title' => 'Google Ads',
                'amount' => 20000,
                'is_recoverable' => true,
            ],
            $user
        );

        $plan->refresh();

        $this->assertSame(
            'active',
            $plan->status
        );

        $this->assertEquals(
            25000,
            $plan->total_recoverable_amount
        );

        $this->assertEquals(
            5000,
            $plan->total_recovered_amount
        );

        $this->assertEquals(
            20000,
            $plan->outstanding_amount
        );

        $this->assertNull(
            $plan->completed_on
        );
    }

    public function test_second_active_plan_for_same_account_is_blocked(): void
    {
        $user = User::factory()->create();

        $this->service->createPlan(
            $user,
            [
                'base_percentage' => 20,
                'recovery_uplift_percentage' => 10,
                'initial_amount' => 10000,
            ],
            $user
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->service->createPlan(
            $user,
            [
                'base_percentage' => 20,
                'recovery_uplift_percentage' => 5,
                'initial_amount' => 5000,
            ],
            $user
        );
    }

    public function test_same_idempotency_key_does_not_apply_recovery_twice(): void
    {
        $user = User::factory()->create();

        $plan = $this->service->createPlan(
            $user,
            [
                'base_percentage' => 20,
                'recovery_uplift_percentage' => 10,
                'maximum_recovery_percentage' => 10,
                'initial_amount' => 100000,
            ],
            $user
        );

        $context = [
            'reporting_month' => '2026-08',
            'reference' => 'REPORT-RETRY-001',
            'idempotency_key' =>
                'royalty-statement:test-retry-001',
        ];

        $first = $this->service->applyRecovery(
            $plan,
            100000,
            $context
        );

        $second = $this->service->applyRecovery(
            $plan->fresh(),
            100000,
            $context
        );

        $this->assertNotNull($first);
        $this->assertNotNull($second);

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            1,
            \App\Models\Finance\RecoupmentRecovery::query()
                ->where(
                    'recoupment_plan_id',
                    $plan->id
                )
                ->where(
                    'idempotency_key',
                    'royalty-statement:test-retry-001'
                )
                ->count()
        );

        $plan->refresh();

        $this->assertEquals(
            10000,
            $plan->total_recovered_amount
        );

        $this->assertEquals(
            90000,
            $plan->outstanding_amount
        );
    }

    public function test_statement_recovery_uses_allocated_payable_without_double_percentage(): void
    {
        $user = User::factory()->create();

        $plan = app(
            RecoupmentManagementService::class
        )->createPlan(
            $user,
            [
                'title' => 'Statement Recovery Test',
                'base_percentage' => 70,
                'recovery_uplift_percentage' => 10,
                'maximum_recovery_percentage' => 10,
                'initial_amount' => 1000,
            ]
        );

        $recovery = app(
            RecoupmentManagementService::class
        )->applyStatementRecovery(
            $plan,
            1750,
            1575,
            [
                'reporting_month' => '2026-07',
                'reference' => 'Statement Test',
                'idempotency_key' =>
                    'royalty-statement:test-001',
            ]
        );

        $this->assertNotNull($recovery);

        $this->assertEquals(
            2500,
            (float) $recovery->source_amount
        );

        $this->assertEquals(
            250,
            (float)
            $recovery->applied_recovery_amount
        );

        $plan->refresh();

        $this->assertEquals(
            250,
            (float)
            $plan->total_recovered_amount
        );

        $this->assertEquals(
            750,
            (float)
            $plan->outstanding_amount
        );
    }

    public function test_statement_recovery_retry_after_plan_completion_returns_original_recovery(): void
    {
        $user = User::factory()->create();

        $plan = app(
            RecoupmentManagementService::class
        )->createPlan(
            $user,
            [
                'title' => 'Completed Retry Test',
                'base_percentage' => 20,
                'recovery_uplift_percentage' => 10,
                'maximum_recovery_percentage' => 10,
                'initial_amount' => 5000,
            ]
        );

        $service = app(
            RecoupmentManagementService::class
        );

        $context = [
            'reporting_month' => '2026-07',
            'reference' => 'Completed Statement',
            'idempotency_key' =>
                'royalty-statement:completed-001',
        ];

        $first = $service
            ->applyStatementRecovery(
                $plan,
                10000,
                10000,
                $context
            );

        $this->assertNotNull($first);

        $this->assertEquals(
            5000,
            (float)
            $first->applied_recovery_amount
        );

        $plan->refresh();

        $this->assertSame(
            'completed',
            $plan->status
        );

        $this->assertEquals(
            0,
            (float)
            $plan->outstanding_amount
        );

        $second = $service
            ->applyStatementRecovery(
                $plan,
                10000,
                10000,
                $context
            );

        $this->assertNotNull($second);

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertDatabaseCount(
            'recoupment_recoveries',
            1
        );

        $plan->refresh();

        $this->assertEquals(
            5000,
            (float)
            $plan->total_recovered_amount
        );

        $this->assertEquals(
            0,
            (float)
            $plan->outstanding_amount
        );
    }

    public function test_statement_recovery_is_capped_at_statement_payable(): void
    {
        $user = User::factory()->create();

        $plan = app(
            RecoupmentManagementService::class
        )->createPlan(
            $user,
            [
                'title' => 'Payable Cap Test',
                'base_percentage' => 20,
                'recovery_uplift_percentage' => 20,
                'maximum_recovery_percentage' => 20,
                'initial_amount' => 100000,
            ]
        );

        $recovery = app(
            RecoupmentManagementService::class
        )->applyStatementRecovery(
            $plan,
            200,
            200,
            [
                'idempotency_key' =>
                    'royalty-statement:cap-001',
            ]
        );

        $this->assertNotNull($recovery);

        $this->assertEquals(
            200,
            (float)
            $recovery->applied_recovery_amount
        );

        $plan->refresh();

        $this->assertEquals(
            99800,
            (float)
            $plan->outstanding_amount
        );
    }

}
