<?php

namespace Tests\Feature\LabelAccess;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\User;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelTeamCatalogueScopeTest extends TestCase
{
    use RefreshDatabase;

    private function hierarchy(): array
    {
        $owner = User::factory()->create([
            'role' => 'label',
        ]);

        $master = Label::factory()->create([
            'user_id' => $owner->id,
            'parent_label_id' => null,
            'created_by' => $owner->id,
        ]);

        $childUserA = User::factory()->create([
            'role' => 'label',
        ]);

        $childA = Label::factory()->create([
            'user_id' => $childUserA->id,
            'parent_label_id' => $master->id,
            'created_by' => $owner->id,
        ]);

        $childUserB = User::factory()->create([
            'role' => 'label',
        ]);

        $childB = Label::factory()->create([
            'user_id' => $childUserB->id,
            'parent_label_id' => $master->id,
            'created_by' => $owner->id,
        ]);

        $masterArtist = Artist::factory()->create([
            'label_id' => $master->id,
        ]);

        $artistA = Artist::factory()->create([
            'label_id' => $childA->id,
        ]);

        $artistB = Artist::factory()->create([
            'label_id' => $childB->id,
        ]);

        return [
            $owner,
            $master,
            $childA,
            $childB,
            $masterArtist,
            $artistA,
            $artistB,
        ];
    }

    public function test_master_owner_sees_strict_two_tier_catalogue(): void
    {
        [
            $owner,
            $master,
            $childA,
            $childB,
            $masterArtist,
            $artistA,
            $artistB,
        ] = $this->hierarchy();

        $grandchildUser =
            User::factory()->create([
                'role' => 'label',
            ]);

        $grandchild =
            Label::factory()->create([
                'user_id' =>
                    $grandchildUser->id,
                'parent_label_id' =>
                    $childA->id,
                'created_by' =>
                    $owner->id,
            ]);

        $grandchildArtist =
            Artist::factory()->create([
                'label_id' =>
                    $grandchild->id,
            ]);

        $service = app(
            LabelTeamAccessService::class
        );

        $labelIds =
            $service->accessibleLabelIds(
                $owner
            );

        $artistIds =
            $service->accessibleArtistIds(
                $owner
            );

        $this->assertTrue(
            $labelIds->contains($master->id)
        );

        $this->assertTrue(
            $labelIds->contains($childA->id)
        );

        $this->assertTrue(
            $labelIds->contains($childB->id)
        );

        $this->assertFalse(
            $labelIds->contains(
                $grandchild->id
            )
        );

        $this->assertTrue(
            $artistIds->contains(
                $masterArtist->id
            )
        );

        $this->assertTrue(
            $artistIds->contains(
                $artistA->id
            )
        );

        $this->assertTrue(
            $artistIds->contains(
                $artistB->id
            )
        );

        $this->assertFalse(
            $artistIds->contains(
                $grandchildArtist->id
            )
        );
    }

    public function test_entire_label_team_user_sees_two_tier_catalogue(): void
    {
        [
            $owner,
            $master,
            $childA,
            $childB,
            $masterArtist,
            $artistA,
            $artistB,
        ] = $this->hierarchy();

        $teamUser =
            User::factory()->create();

        app(LabelTeamAccessService::class)
            ->createMember(
                $owner,
                $teamUser,
                'standard',
                'entire_label',
                ['dashboard.view']
            );

        $service = app(
            LabelTeamAccessService::class
        );

        $labelIds =
            $service->accessibleLabelIds(
                $teamUser
            );

        $artistIds =
            $service->accessibleArtistIds(
                $teamUser
            );

        $this->assertEqualsCanonicalizing(
            [
                $master->id,
                $childA->id,
                $childB->id,
            ],
            $labelIds->all()
        );

        $this->assertEqualsCanonicalizing(
            [
                $masterArtist->id,
                $artistA->id,
                $artistB->id,
            ],
            $artistIds->all()
        );
    }

    public function test_selected_team_scope_is_enforced(): void
    {
        [
            $owner,
            $master,
            $childA,
            $childB,
            $masterArtist,
            $artistA,
            $artistB,
        ] = $this->hierarchy();

        $teamUser =
            User::factory()->create();

        app(LabelTeamAccessService::class)
            ->createMember(
                $owner,
                $teamUser,
                'standard',
                'selected',
                ['dashboard.view'],
                [$masterArtist->id],
                [$childA->id]
            );

        $service = app(
            LabelTeamAccessService::class
        );

        $labelIds =
            $service->accessibleLabelIds(
                $teamUser
            );

        $artistIds =
            $service->accessibleArtistIds(
                $teamUser
            );

        $this->assertTrue(
            $labelIds->contains(
                $master->id
            )
        );

        $this->assertTrue(
            $labelIds->contains(
                $childA->id
            )
        );

        $this->assertFalse(
            $labelIds->contains(
                $childB->id
            )
        );

        $this->assertTrue(
            $artistIds->contains(
                $masterArtist->id
            )
        );

        /*
         * Selecting childA exposes its
         * direct artists.
         */
        $this->assertTrue(
            $artistIds->contains(
                $artistA->id
            )
        );

        $this->assertFalse(
            $artistIds->contains(
                $artistB->id
            )
        );
    }

    public function test_child_label_owner_remains_isolated(): void
    {
        [
            $owner,
            $master,
            $childA,
            $childB,
        ] = $this->hierarchy();

        $childOwner = User::query()
            ->findOrFail(
                $childA->user_id
            );

        $service = app(
            LabelTeamAccessService::class
        );

        $labelIds =
            $service->accessibleLabelIds(
                $childOwner
            );

        $this->assertSame(
            [$childA->id],
            $labelIds->all()
        );

        $this->assertFalse(
            $labelIds->contains(
                $master->id
            )
        );

        $this->assertFalse(
            $labelIds->contains(
                $childB->id
            )
        );
    }
}
