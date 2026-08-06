<?php

namespace Database\Factories\Distribution;

use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
{
    protected $model = Track::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'release_id' => Release::factory(),
            'disc_number' => 1,
            'track_number' => 1,
            'title' => fake()->unique()->sentence(3),
            'version' => null,
            'subtitle' => null,
            'primary_artist_name' => fake()->name(),
            'featuring_artist_name' => null,
            'isrc' => null,
            'isrc_is_auto_generated' => false,
            'language' => 'Hindi',
            'genre' => 'Devotional',
            'sub_genre' => null,
            'is_explicit' => false,
            'is_instrumental' => false,
            'contains_ai_generated_content' => false,
            'duration_seconds' => 240,
            'preview_start_seconds' => 30,
            'lyrics' => null,
            'audio_path' => null,
            'audio_original_name' => null,
            'audio_mime_type' => null,
            'audio_size_bytes' => null,
            'sample_rate' => null,
            'bit_depth' => null,
            'channels' => null,
            'audio_validation_status' => 'pending',
            'audio_validation_errors' => null,
            'status' => 'draft',
            'created_by' => User::factory()->state([
                'role' => 'artist',
                'account_status' => 'active',
            ]),
            'updated_by' => null,
        ];
    }

    public function withAudio(): static
    {
        return $this->state(fn (): array => [
            'audio_path' => 'tracks/audio/test-track.wav',
            'audio_original_name' => 'test-track.wav',
            'audio_mime_type' => 'audio/wav',
            'audio_size_bytes' => 1048576,
            'sample_rate' => 44100,
            'bit_depth' => 24,
            'channels' => 2,
            'audio_validation_status' => 'passed',
            'audio_codec' => 'pcm_s24le',
            'audio_sample_rate' => 44100,
            'audio_bit_depth' => 24,
            'audio_channels' => 2,
            'audio_channel_layout' => 'stereo',
            'audio_duration_seconds' => 240,
            'audio_validated_at' => now(),
        ]);
    }
}
