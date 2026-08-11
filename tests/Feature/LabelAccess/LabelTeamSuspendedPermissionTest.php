<?php

namespace Tests\Feature\LabelAccess;

use App\Models\Core\Label;
use App\Models\LabelAccess\LabelTeamMember;
use App\Models\User;
use App\Services\V2\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LabelTeamSuspendedPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function setupMember(
        string $status,
        array $permissions
    ): array {
        $owner = User::factory()->create([
            'role' => 'label',
        ]);

        $label = Label::query()->create([
            'public_id' => (string) Str::ulid(),
            'user_id' => $owner->id,
            'name' => 'Permission Security Label',
            'slug' =>
                'permission-security-'
                . Str::lower(Str::random(8)),
            'status' => 'active',
        ]);

        $teamUser = User::factory()->create([
            /*
             * Artist is intentionally used because this
             * previously caused role fallback.
             */
            'role' => 'artist',
        ]);

        LabelTeamMember::query()->create([
            'label_id' => $label->id,
            'user_id' => $teamUser->id,
            'created_by' => $owner->id,
            'permission_level' => 'advanced',
            'scope_level' => 'entire_label',
            'status' => $status,
            'permissions' => $permissions,
        ]);

        return [
            $owner,
            $label,
            $teamUser,
        ];
    }

    public function test_active_team_user_receives_only_granted_permissions(): void
    {
        [
            ,
            ,
            $teamUser,
        ] = $this->setupMember(
            'active',
            [
                'dashboard.view',
                'wallet.view',
            ]
        );

        $service = app(
            PermissionService::class
        );

        $this->assertTrue(
            $service->allows(
                $teamUser,
                'wallet.view'
            )
        );

        $this->assertFalse(
            $service->allows(
                $teamUser,
                'withdrawals.create'
            )
        );

        $this->assertEqualsCanonicalizing(
            [
                'dashboard.view',
                'wallet.view',
            ],
            $service->permissions(
                $teamUser
            )
        );
    }

    public function test_suspended_team_user_cannot_fallback_to_artist_permissions(): void
    {
        [
            ,
            ,
            $teamUser,
        ] = $this->setupMember(
            'suspended',
            [
                'wallet.view',
                'withdrawals.create',
            ]
        );

        $service = app(
            PermissionService::class
        );

        $this->assertFalse(
            $service->allows(
                $teamUser,
                'wallet.view'
            )
        );

        $this->assertFalse(
            $service->allows(
                $teamUser,
                'withdrawals.create'
            )
        );

        $this->assertSame(
            [],
            $service->permissions(
                $teamUser
            )
        );
    }

    public function test_disabled_team_user_cannot_fallback_to_artist_permissions(): void
    {
        [
            ,
            ,
            $teamUser,
        ] = $this->setupMember(
            'disabled',
            ['wallet.view']
        );

        $service = app(
            PermissionService::class
        );

        $this->assertFalse(
            $service->allows(
                $teamUser,
                'wallet.view'
            )
        );

        $this->assertSame(
            [],
            $service->permissions(
                $teamUser
            )
        );
    }
}
