<?php

namespace Tests\Feature\LabelAccess;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelTeamReleaseAccessTest extends TestCase
{
    use RefreshDatabase;

    private function setupCatalogue(): array
    {
        $owner = User::factory()->create([
            'role' => 'label',
            'account_status' => 'active',
        ]);

        $master = Label::factory()->create([
            'user_id' => $owner->id,
            'parent_label_id' => null,
            'created_by' => $owner->id,
        ]);

        $artistA = Artist::factory()->create([
            'label_id' => $master->id,
            'created_by' => $owner->id,
        ]);

        $artistB = Artist::factory()->create([
            'label_id' => $master->id,
            'created_by' => $owner->id,
        ]);

        return [
            $owner,
            $master,
            $artistA,
            $artistB,
        ];
    }

    public function test_selected_team_user_can_access_only_selected_artist_release(): void
    {
        [
            $owner,
            $master,
            $artistA,
            $artistB,
        ] = $this->setupCatalogue();

        $teamUser = User::factory()->create([
            'role' => 'artist',
        ]);

        app(LabelTeamAccessService::class)
            ->createMember(
                $owner,
                $teamUser,
                'standard',
                'selected',
                [
                    'releases.view',
                    'releases.edit',
                ],
                [$artistA->id]
            );

        $allowed = Release::factory()->create([
            'artist_id' => $artistA->id,
            'label_id' => $master->id,
            'status' => 'submitted',
            'created_by' => $owner->id,
        ]);

        $blocked = Release::factory()->create([
            'artist_id' => $artistB->id,
            'label_id' => $master->id,
            'status' => 'submitted',
            'created_by' => $owner->id,
        ]);

        $access = app(
            ReleaseAccessService::class
        );

        $this->assertTrue(
            $access->canAccess(
                $teamUser,
                $allowed
            )
        );

        $this->assertFalse(
            $access->canAccess(
                $teamUser,
                $blocked
            )
        );
    }

    public function test_team_user_cannot_see_another_users_draft(): void
    {
        [
            $owner,
            $master,
            $artistA,
        ] = $this->setupCatalogue();

        $teamUser = User::factory()->create([
            'role' => 'artist',
        ]);

        app(LabelTeamAccessService::class)
            ->createMember(
                $owner,
                $teamUser,
                'standard',
                'entire_label',
                [
                    'releases.view',
                    'releases.edit',
                ]
            );

        $ownerDraft =
            Release::factory()->create([
                'artist_id' => $artistA->id,
                'label_id' => $master->id,
                'status' => 'draft',
                'created_by' => $owner->id,
            ]);

        $this->assertFalse(
            app(ReleaseAccessService::class)
                ->canAccess(
                    $teamUser,
                    $ownerDraft
                )
        );
    }

    public function test_team_user_can_access_own_draft(): void
    {
        [
            $owner,
            $master,
            $artistA,
        ] = $this->setupCatalogue();

        $teamUser = User::factory()->create([
            'role' => 'artist',
        ]);

        app(LabelTeamAccessService::class)
            ->createMember(
                $owner,
                $teamUser,
                'standard',
                'entire_label',
                [
                    'releases.view',
                    'releases.edit',
                ]
            );

        $draft =
            Release::factory()->create([
                'artist_id' => $artistA->id,
                'label_id' => $master->id,
                'status' => 'draft',
                'created_by' => $teamUser->id,
            ]);

        $this->assertTrue(
            app(ReleaseAccessService::class)
                ->canAccess(
                    $teamUser,
                    $draft
                )
        );
    }

    public function test_standard_team_user_cannot_submit_release(): void
    {
        [
            $owner,
            $master,
            $artistA,
        ] = $this->setupCatalogue();

        $teamUser = User::factory()->create([
            'role' => 'artist',
        ]);

        app(LabelTeamAccessService::class)
            ->createMember(
                $owner,
                $teamUser,
                'standard',
                'entire_label',
                [
                    'releases.view',
                    'releases.edit',
                    'releases.submit',
                ]
            );

        $draft =
            Release::factory()->create([
                'artist_id' => $artistA->id,
                'label_id' => $master->id,
                'status' => 'draft',
                'created_by' => $teamUser->id,
            ]);

        $this->expectException(
            \Symfony\Component\HttpKernel\Exception\HttpException::class
        );

        app(ReleaseAccessService::class)
            ->authorizeSubmit(
                $teamUser,
                $draft
            );
    }

    public function test_release_index_applies_selected_team_scope(): void
    {
        [
            $owner,
            $master,
            $artistA,
            $artistB,
        ] = $this->setupCatalogue();

        $teamUser = User::factory()->create([
            'role' => 'artist',
        ]);

        app(LabelTeamAccessService::class)
            ->createMember(
                $owner,
                $teamUser,
                'standard',
                'selected',
                ['releases.view'],
                [$artistA->id]
            );

        $allowed = Release::factory()->create([
            'artist_id' => $artistA->id,
            'label_id' => $master->id,
            'status' => 'submitted',
            'created_by' => $owner->id,
        ]);

        Release::factory()->create([
            'artist_id' => $artistB->id,
            'label_id' => $master->id,
            'status' => 'submitted',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($teamUser)
            ->get(route('v2.releases.index'))
            ->assertSuccessful()
            ->assertInertia(
                fn ($page) =>
                    $page
                        ->component(
                            'V2/Releases/Index'
                        )
                        ->has(
                            'releases.data',
                            1
                        )
                        ->where(
                            'releases.data.0.id',
                            $allowed->id
                        )
            );
    }
}
