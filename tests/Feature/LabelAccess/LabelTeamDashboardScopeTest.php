<?php

namespace Tests\Feature\LabelAccess;

use App\Models\User;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use Tests\TestCase;

class LabelTeamDashboardScopeTest extends TestCase
{
    public function test_dashboard_controller_uses_team_access_service(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/V2/DashboardController.php')
        );

        $this->assertStringContainsString(
            LabelTeamAccessService::class,
            $source
        );

        $this->assertStringContainsString(
            "->effectiveLabel(\$user)",
            $source
        );

        $this->assertStringContainsString(
            "->accessibleLabelIds(\$user)",
            $source
        );

        $this->assertStringContainsString(
            "->accessibleArtistIds(\$user)",
            $source
        );

        $this->assertStringContainsString(
            "'dashboard.view'",
            $source
        );
    }
}
