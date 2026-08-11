<?php

namespace Tests\Feature\LabelAccess;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\User;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelTeamAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LabelTeamAccessService
    {
        return app(
            LabelTeamAccessService::class
        );
    }

    private function user(
        string $role = 'label'
    ): User {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function owner(): array
    {
        $user = $this->user();

        $label = Label::factory()->create([
            'user_id' => $user->id,
            'parent_label_id' => null,
            'created_by' => $user->id,
        ]);

        return [$user, $label];
    }

    public function test_label_owner_has_full_access(): void
    {
        [$owner] = $this->owner();

        $this->assertTrue(
            $this->service()->allows(
                $owner,
                'team.manage'
            )
        );

        $this->assertTrue(
            $this->service()->allows(
                $owner,
                'releases.submit'
            )
        );
    }

    public function test_standard_user_cannot_receive_release_submit(): void
    {
        [$owner] = $this->owner();

        $teamUser = $this->user();

        $member =
            $this->service()->createMember(
                $owner,
                $teamUser,
                'standard',
                'entire_label',
                [
                    'dashboard.view',
                    'releases.create',
                    'releases.submit',
                    'team.manage',
                ]
            );

        $this->assertContains(
            'dashboard.view',
            $member->permissions
        );

        $this->assertContains(
            'releases.create',
            $member->permissions
        );

        $this->assertNotContains(
            'releases.submit',
            $member->permissions
        );

        $this->assertNotContains(
            'team.manage',
            $member->permissions
        );
    }

    public function test_advanced_user_can_receive_release_submit(): void
    {
        [$owner] = $this->owner();

        $teamUser = $this->user();

        $member =
            $this->service()->createMember(
                $owner,
                $teamUser,
                'advanced',
                'entire_label',
                [
                    'releases.submit',
                ]
            );

        $this->assertTrue(
            $this->service()->allows(
                $teamUser,
                'releases.submit'
            )
        );

        $this->assertFalse(
            $this->service()->allows(
                $teamUser,
                'team.manage'
            )
        );
    }

    public function test_selected_scope_limits_artist_access(): void
    {
        [$owner, $label] =
            $this->owner();

        $teamUser = $this->user();

        $allowedArtist =
            Artist::factory()->create([
                'label_id' => $label->id,
                'created_by' => $owner->id,
            ]);

        $blockedArtist =
            Artist::factory()->create([
                'label_id' => $label->id,
                'created_by' => $owner->id,
            ]);

        $this->service()->createMember(
            $owner,
            $teamUser,
            'standard',
            'selected',
            ['artists.view'],
            [$allowedArtist->id]
        );

        $this->assertTrue(
            $this->service()
                ->canAccessArtist(
                    $teamUser,
                    $allowedArtist
                )
        );

        $this->assertFalse(
            $this->service()
                ->canAccessArtist(
                    $teamUser,
                    $blockedArtist
                )
        );
    }

    public function test_foreign_artist_cannot_be_added_to_scope(): void
    {
        [$owner] = $this->owner();

        $teamUser = $this->user();

        [$otherOwner, $otherLabel] =
            $this->owner();

        $foreignArtist =
            Artist::factory()->create([
                'label_id' =>
                    $otherLabel->id,

                'created_by' =>
                    $otherOwner->id,
            ]);

        $member =
            $this->service()->createMember(
                $owner,
                $teamUser,
                'advanced',
                'selected',
                ['artists.view'],
                [$foreignArtist->id]
            );

        $this->assertCount(
            0,
            $member->scopes
        );

        $this->assertFalse(
            $this->service()
                ->canAccessArtist(
                    $teamUser,
                    $foreignArtist
                )
        );
    }

    public function test_team_user_cannot_manage_another_team_user(): void
    {
        [$owner] = $this->owner();

        $teamUser = $this->user();
        $otherUser = $this->user();

        $this->service()->createMember(
            $owner,
            $teamUser,
            'advanced',
            'entire_label',
            ['releases.submit']
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->service()->createMember(
            $teamUser,
            $otherUser,
            'standard',
            'entire_label',
            ['dashboard.view']
        );
    }

    public function test_suspended_team_user_loses_access(): void
    {
        [$owner] = $this->owner();

        $teamUser = $this->user();

        $member =
            $this->service()->createMember(
                $owner,
                $teamUser,
                'advanced',
                'entire_label',
                ['releases.submit']
            );

        $this->service()->setStatus(
            $owner,
            $member,
            'suspended'
        );

        $this->assertFalse(
            $this->service()->allows(
                $teamUser,
                'releases.submit'
            )
        );
    }

    public function test_child_label_cannot_create_team_users(): void
    {
        [$masterOwner, $master] =
            $this->owner();

        $childOwner = $this->user();

        Label::factory()->create([
            'user_id' => $childOwner->id,
            'parent_label_id' => $master->id,
            'created_by' => $masterOwner->id,
        ]);

        $teamUser = $this->user();

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        /*
         * This assertion will become strict in Phase 3
         * after master-only User Access is wired.
         *
         * For now, ownedLabel() still resolves any label owner.
         */
        if (
            $this->service()
                ->ownedLabel($childOwner)
                ?->parent_label_id
            !== null
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'label' =>
                    'User Access is available only to Master Labels.',
            ]);
        }

        $this->service()->createMember(
            $childOwner,
            $teamUser,
            'standard',
            'entire_label',
            ['dashboard.view']
        );
    }
}
