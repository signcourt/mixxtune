<?php

namespace Tests\Feature\LabelAccess;

use App\Models\Core\Label;
use App\Models\LabelAccess\LabelTeamMember;
use App\Models\User;
use App\Services\V2\LabelAccess\LabelFinancialContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LabelFinancialContextServiceTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'label'): User
    {
        return User::factory()->create([
            'role' => $role,
        ]);
    }

    private function label(User $owner): Label
    {
        return Label::query()->create([
            'public_id' => (string) Str::ulid(),
            'user_id' => $owner->id,
            'name' => 'Financial Test Label',
            'slug' => 'financial-test-' . Str::lower(Str::random(8)),
            'status' => 'active',
        ]);
    }

    public function test_label_owner_resolves_to_self(): void
    {
        $owner = $this->user();
        $this->label($owner);

        $resolved = app(
            LabelFinancialContextService::class
        )->owner($owner);

        $this->assertSame(
            $owner->id,
            $resolved->id
        );
    }

    public function test_team_user_resolves_to_label_owner(): void
    {
        $owner = $this->user();
        $label = $this->label($owner);

        $teamUser = $this->user('artist');

        LabelTeamMember::query()->create([
            'label_id' => $label->id,
            'user_id' => $teamUser->id,
            'created_by' => $owner->id,
            'permission_level' => 'advanced',
            'scope_level' => 'entire_label',
            'status' => 'active',
            'permissions' => [
                'wallet.view',
                'withdrawals.view',
                'withdrawals.create',
            ],
        ]);

        $resolved = app(
            LabelFinancialContextService::class
        )->owner($teamUser);

        $this->assertSame(
            $owner->id,
            $resolved->id
        );

        $this->assertTrue(
            app(
                LabelFinancialContextService::class
            )->isTeamActor($teamUser)
        );
    }

    public function test_unrelated_user_keeps_own_financial_identity(): void
    {
        $user = $this->user('artist');

        $resolved = app(
            LabelFinancialContextService::class
        )->owner($user);

        $this->assertSame(
            $user->id,
            $resolved->id
        );
    }

    public function test_suspended_team_user_does_not_use_label_owner(): void
    {
        $owner = $this->user();
        $label = $this->label($owner);

        $teamUser = $this->user('artist');

        LabelTeamMember::query()->create([
            'label_id' => $label->id,
            'user_id' => $teamUser->id,
            'created_by' => $owner->id,
            'permission_level' => 'advanced',
            'scope_level' => 'entire_label',
            'status' => 'suspended',
            'permissions' => [
                'wallet.view',
            ],
        ]);

        $resolved = app(
            LabelFinancialContextService::class
        )->owner($teamUser);

        $this->assertSame(
            $teamUser->id,
            $resolved->id
        );

        $this->assertFalse(
            app(
                LabelFinancialContextService::class
            )->isTeamActor($teamUser)
        );
    }
}
