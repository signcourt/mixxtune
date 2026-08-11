<?php

namespace Tests\Feature\LabelAccess;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelTeamTrackScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_track_does_not_expose_sibling_track(): void
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

        $artist = Artist::factory()->create([
            'label_id' => $label->id,
            'created_by' => $owner->id,
        ]);

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'status' => 'submitted',
            'created_by' => $owner->id,
        ]);

        $trackA = Track::factory()->create([
            'release_id' => $release->id,
            'disc_number' => 1,
            'track_number' => 1,
            'created_by' => $owner->id,
        ]);

        $trackB = Track::factory()->create([
            'release_id' => $release->id,
            'disc_number' => 1,
            'track_number' => 2,
            'created_by' => $owner->id,
        ]);

        $teamUser = User::factory()->create([
            'role' => 'artist',
        ]);

        $service =
            app(LabelTeamAccessService::class);

        $member = $service->createMember(
            $owner,
            $teamUser,
            'standard',
            'selected',
            ['releases.view'],
            [],
            [],
            [$trackA->id]
        );

        $member->refresh()->load('scopes');

        $ids =
            $service->accessibleTrackIds(
                $teamUser
            );

        $this->assertTrue(
            $ids->contains($trackA->id)
        );

        $this->assertFalse(
            $ids->contains($trackB->id)
        );

        $this->assertTrue(
            $service->canAccessTrack(
                $teamUser,
                $trackA
            )
        );

        $this->assertFalse(
            $service->canAccessTrack(
                $teamUser,
                $trackB
            )
        );
    }

    public function test_entire_label_user_can_access_all_tracks(): void
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

        $artist = Artist::factory()->create([
            'label_id' => $label->id,
            'created_by' => $owner->id,
        ]);

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'status' => 'submitted',
            'created_by' => $owner->id,
        ]);

        $trackA = Track::factory()->create([
            'release_id' => $release->id,
            'disc_number' => 1,
            'track_number' => 1,
            'created_by' => $owner->id,
        ]);

        $trackB = Track::factory()->create([
            'release_id' => $release->id,
            'disc_number' => 1,
            'track_number' => 2,
            'created_by' => $owner->id,
        ]);

        $teamUser = User::factory()->create([
            'role' => 'artist',
        ]);

        $service =
            app(LabelTeamAccessService::class);

        $service->createMember(
            $owner,
            $teamUser,
            'standard',
            'entire_label',
            ['releases.view']
        );

        $ids =
            $service->accessibleTrackIds(
                $teamUser
            );

        $this->assertTrue(
            $ids->contains($trackA->id)
        );

        $this->assertTrue(
            $ids->contains($trackB->id)
        );
    }
}
