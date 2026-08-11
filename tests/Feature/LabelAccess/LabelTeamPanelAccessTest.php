<?php

namespace Tests\Feature\LabelAccess;

use App\Models\Core\Label;
use App\Models\User;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use App\Services\V2\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelTeamPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    private function master(): array
    {
        $owner = User::factory()->create([
            'role' => 'label',
            'account_status' => 'active',
        ]);

        $label = Label::factory()->create([
            'user_id' => $owner->id,
            'parent_label_id' => null,
            'created_by' => $owner->id,
        ]);

        return [$owner, $label];
    }

    public function test_active_team_user_gets_effective_label_role(): void
    {
        [$owner] = $this->master();

        $teamUser = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
        ]);

        app(LabelTeamAccessService::class)
            ->createMember(
                $owner,
                $teamUser,
                'standard',
                'entire_label',
                [
                    'dashboard.view',
                    'releases.view',
                ]
            );

        $permissions = app(
            PermissionService::class
        );

        $this->assertSame(
            'label',
            $permissions->role($teamUser)
        );

        $this->assertTrue(
            $permissions->allows(
                $teamUser,
                'dashboard.view'
            )
        );

        $this->assertTrue(
            $permissions->allows(
                $teamUser,
                'releases.view'
            )
        );

        $this->assertFalse(
            $permissions->allows(
                $teamUser,
                'releases.submit'
            )
        );

        $this->assertFalse(
            $permissions->allows(
                $teamUser,
                'team.manage'
            )
        );
    }

    public function test_team_user_can_pass_label_role_middleware(): void
    {
        [$owner] = $this->master();

        $teamUser = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
        ]);

        app(LabelTeamAccessService::class)
            ->createMember(
                $owner,
                $teamUser,
                'standard',
                'entire_label',
                ['dashboard.view']
            );

        $this->actingAs($teamUser)
            ->get('/label/dashboard')
            ->assertSuccessful();
    }

    public function test_team_user_cannot_enter_admin_panel(): void
    {
        [$owner] = $this->master();

        $teamUser = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
        ]);

        app(LabelTeamAccessService::class)
            ->createMember(
                $owner,
                $teamUser,
                'advanced',
                'entire_label',
                [
                    'dashboard.view',
                    'releases.submit',
                ]
            );

        $this->actingAs($teamUser)
            ->get('/admin/dashboard')
            ->assertForbidden();

        $this->actingAs($teamUser)
            ->get('/super-admin/dashboard')
            ->assertForbidden();
    }

    public function test_suspended_team_user_loses_label_panel_access(): void
    {
        [$owner] = $this->master();

        $teamUser = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
        ]);

        $member = app(
            LabelTeamAccessService::class
        )->createMember(
            $owner,
            $teamUser,
            'standard',
            'entire_label',
            ['dashboard.view']
        );

        app(LabelTeamAccessService::class)
            ->setStatus(
                $owner,
                $member,
                'suspended'
            );

        $this->actingAs($teamUser)
            ->get('/label/dashboard')
            ->assertForbidden();
    }

    public function test_child_label_cannot_create_team_member(): void
    {
        [$masterOwner, $master] =
            $this->master();

        $childOwner = User::factory()->create([
            'role' => 'label',
            'account_status' => 'active',
        ]);

        Label::factory()->create([
            'user_id' => $childOwner->id,
            'parent_label_id' => $master->id,
            'created_by' => $masterOwner->id,
        ]);

        $teamUser = User::factory()->create([
            'role' => 'artist',
        ]);

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        app(LabelTeamAccessService::class)
            ->createMember(
                $childOwner,
                $teamUser,
                'standard',
                'entire_label',
                ['dashboard.view']
            );
    }
}
