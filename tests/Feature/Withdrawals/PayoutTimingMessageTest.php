<?php

namespace Tests\Feature\Withdrawals;

use App\Services\V2\WithdrawalService;
use Carbon\Carbon;
use Tests\TestCase;

/*
 * MIXXTUNE_PAYOUT_TIMING_V1 (W185a)
 *
 * Unit-style checks for WithdrawalService::payoutTimingMessage() — the "20th"
 * rule that drives the owner-facing payout-timing message. No DB needed; the
 * helper is a pure function with an injectable $requestedAt.
 */
class PayoutTimingMessageTest extends TestCase
{
    private function service(): WithdrawalService
    {
        return app(WithdrawalService::class);
    }

    public function test_request_before_the_20th_is_payable_after_this_months_20th(): void
    {
        $requestedAt = Carbon::create(2026, 3, 5, 10, 0, 0);

        $result = $this->service()->payoutTimingMessage($requestedAt);

        $this->assertSame('2026-03-20', $result['payable_after']);
        $this->assertStringContainsString('20th Mar 2026', $result['message']);
    }

    public function test_request_on_the_20th_is_still_payable_after_the_20th(): void
    {
        $requestedAt = Carbon::create(2026, 3, 20, 23, 0, 0);

        $result = $this->service()->payoutTimingMessage($requestedAt);

        $this->assertSame('2026-03-20', $result['payable_after']);
    }

    public function test_request_after_the_20th_has_no_promised_date(): void
    {
        $requestedAt = Carbon::create(2026, 3, 21, 9, 0, 0);

        $result = $this->service()->payoutTimingMessage($requestedAt);

        $this->assertNull($result['payable_after']);
        $this->assertStringContainsString('being processed', $result['message']);
    }

    public function test_late_december_request_has_no_promised_date(): void
    {
        $requestedAt = Carbon::create(2026, 12, 28, 9, 0, 0);

        $result = $this->service()->payoutTimingMessage($requestedAt);

        $this->assertNull($result['payable_after']);
    }

    public function test_null_requested_at_falls_back_to_now(): void
    {
        $result = $this->service()->payoutTimingMessage(null);

        // Either a this-month 20th date or null, but always a non-empty message.
        $this->assertArrayHasKey('payable_after', $result);
        $this->assertNotEmpty($result['message']);
    }
}
