<?php

namespace Tests\Feature\Factories;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseFactoryFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_factory_chain_can_be_created(): void
    {
        $owner = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
        ]);

        $label = Label::factory()->create([
            'created_by' => $owner->id,
        ]);

        $artist = Artist::factory()->create([
            'user_id' => $owner->id,
            'label_id' => $label->id,
            'created_by' => $owner->id,
        ]);

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'primary_artist_name' => $artist->stage_name,
            'created_by' => $owner->id,
        ]);

        $track = Track::factory()->create([
            'release_id' => $release->id,
            'primary_artist_name' => $artist->stage_name,
            'created_by' => $owner->id,
        ]);

        $this->assertDatabaseHas('labels', [
            'id' => $label->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('artists', [
            'id' => $artist->id,
            'label_id' => $label->id,
        ]);

        $this->assertDatabaseHas('releases', [
            'id' => $release->id,
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('tracks', [
            'id' => $track->id,
            'release_id' => $release->id,
            'status' => 'draft',
        ]);

        $this->assertTrue(
            $track->release->is($release)
        );

        $this->assertTrue(
            $release->artist->is($artist)
        );
    }

    public function test_factory_states_are_available(): void
    {
        $submitted = Release::factory()
            ->submitted()
            ->create();

        $approved = Release::factory()
            ->approved()
            ->create();

        $audioTrack = Track::factory()
            ->withAudio()
            ->create();

        $this->assertSame(
            'submitted',
            $submitted->status
        );

        $this->assertSame(
            'approved',
            $approved->status
        );

        $this->assertSame(
            'passed',
            $audioTrack->audio_validation_status
        );

        $this->assertNotNull(
            $audioTrack->audio_path
        );
    }
}
