<?php

namespace Tests\Feature\Withdrawals;

use App\Services\V2\WithdrawalService;
use Carbon\Carbon;
use Tests\TestCase;

/*
 * MIXXTUNE_PAYOUT_CYCLE_V1 (W185b-2)
 *
 * The quarterly payout gate's pure core: monthly owners can request any month;
 * quarterly owners only in a quarter-end month (Mar/Jun/Sep/Dec). These checks
 * target cycleAllowsRequestNow(), which has no DB dependency (clock injected),
 * so they are fast and deterministic. The DB-backed resolver payoutCycleFor()
 * (any-quarterly-entity -> quarterly) and the full create() gate are verified
 * on staging against real data.
 */
class PayoutCycleGateTest extends TestCase
{
    private function service(): WithdrawalService
    {
        return app(WithdrawalService::class);
    }

    public function test_monthly_is_allowed_in_every_month(): void
    {
        $svc = $this->service();
        foreach (range(1, 12) as $m) {
            $now = Carbon::create(2026, $m, 10);
            $this->assertTrue(
                $svc->cycleAllowsRequestNow('monthly', $now),
                "monthly should be allowed in month $m"
            );
        }
    }

    public function test_quarterly_is_allowed_only_in_quarter_end_months(): void
    {
        $svc = $this->service();
        $allowed = [3, 6, 9, 12];
        foreach (range(1, 12) as $m) {
            $now = Carbon::create(2026, $m, 10);
            $this->assertSame(
                in_array($m, $allowed, true),
                $svc->cycleAllowsRequestNow('quarterly', $now),
                "quarterly month $m eligibility mismatch"
            );
        }
    }

    public function test_quarterly_boundary_first_and_last_day(): void
    {
        $svc = $this->service();

        // 1st and last day of a quarter-end month are allowed.
        $this->assertTrue($svc->cycleAllowsRequestNow('quarterly', Carbon::create(2026, 3, 1)));
        $this->assertTrue($svc->cycleAllowsRequestNow('quarterly', Carbon::create(2026, 3, 31)));

        // Day in a non-quarter-end month is blocked.
        $this->assertFalse($svc->cycleAllowsRequestNow('quarterly', Carbon::create(2026, 11, 17)));
    }

    public function test_unknown_cycle_defaults_to_allowed(): void
    {
        // Any non-'quarterly' value (incl. 'monthly' or empty) is treated as monthly.
        $this->assertTrue($this->service()->cycleAllowsRequestNow('monthly'));
        $this->assertTrue($this->service()->cycleAllowsRequestNow(''));
    }
}
