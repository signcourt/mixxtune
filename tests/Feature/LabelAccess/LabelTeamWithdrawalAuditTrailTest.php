<?php

namespace Tests\Feature\LabelAccess;

use Tests\TestCase;

class LabelTeamWithdrawalAuditTrailTest extends TestCase
{
    public function test_withdrawal_service_separates_financial_owner_from_actor(): void
    {
        $source = file_get_contents(
            app_path(
                'Services/V2/WithdrawalService.php'
            )
        );

        $this->assertStringContainsString(
            '?User $actor = null',
            $source
        );

        $this->assertStringContainsString(
            '$actor ??= $user;',
            $source
        );

        $this->assertMatchesRegularExpression(
            "/'user_id'\\s*=>\\s*\\\$user->id/",
            $source
        );

        $this->assertMatchesRegularExpression(
            "/'created_by'\\s*=>\\s*\\\$actor->id/",
            $source
        );
    }

    public function test_withdrawal_controller_passes_authenticated_actor(): void
    {
        $source = file_get_contents(
            app_path(
                'Http/Controllers/V2/WithdrawalController.php'
            )
        );

        $this->assertStringContainsString(
            '$financialContext->owner(',
            $source
        );

        $this->assertMatchesRegularExpression(
            '/\\$withdrawals->create\\('
            . '.*?\\$financialOwner,'
            . '.*?\\$actor'
            . '\\s*\\);/s',
            $source
        );
    }
}
