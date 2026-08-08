<?php

namespace App\Http\Controllers\Artist;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReleaseTrackController extends Controller
{
    private function artistProfile(Request $request)
    {
        $artist = \DB::table('artists')
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->first();

        abort_if(!$artist, 403, 'Artist profile not linked.');

        return $artist;
    }

    private function authorizeRelease(
        Request $request,
        Release $release
    ): void {
        $artist = $this->artistProfile($request);

        abort_if(
            (int) $release->artist_id !== (int) $artist->id,
            403,
            'You cannot manage this release.'
        );
    }

    private function authorizeTrack(
        Request $request,
        Track $track
    ): void {
        $track->loadMissing('release');

        abort_if(
            !$track->release,
            404,
            'Release not found.'
        );

        $this->authorizeRelease($request, $track->release);
    }

    public function store(
        Request $request,
        Release $release
    ) {
        $this->authorizeRelease($request, $release);

        abort_unless(
            in_array($release->status, ['draft', 'rejected'], true),
            403,
            'Tracks cannot be changed after submission.'
        );

        $validated = $request->validate([
            'disc_number' => ['nullable', 'integer', 'min:1'],
            'track_number' => ['nullable', 'integer', 'min:1'],

            'title' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'track_type' => [
                'nullable',
                'string',
                Rule::in([
                    'original',
                    'karaoke',
                    'medley',
                    'cover',
                    'cover_by_cover_band',
                ]),
            ],

            'primary_artist_name' => [
                'required',
                'string',
                'max:255',
            ],

            'featuring_artist_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'author_name' => ['nullable', 'string', 'max:255'],
            'composer_name' => ['nullable', 'string', 'max:255'],
            'arranger_name' => ['nullable', 'string', 'max:255'],
            'producer_name' => ['nullable', 'string', 'max:255'],
            'music_director_name' => ['nullable', 'string', 'max:255'],
            'publisher_name' => ['nullable', 'string', 'max:255'],
            'p_line' => ['nullable', 'string', 'max:255'],
            'release_year' => [
                'nullable',
                'integer',
                'min:1900',
                'max:' . (date('Y') + 1),
            ],

            'isrc' => [
                'nullable',
                'string',
                'max:20',
                'unique:tracks,isrc',
            ],

            'language' => ['nullable', 'string', 'max:100'],
            'title_language' => ['nullable', 'string', 'max:100'],
            'lyrics_language' => ['nullable', 'string', 'max:100'],
            'genre' => ['nullable', 'string', 'max:100'],
            'sub_genre' => ['nullable', 'string', 'max:100'],

            'is_explicit' => ['nullable', 'boolean'],
            'parental_advisory' => [
                'nullable',
                'string',
                Rule::in(['yes', 'no', 'cleaned']),
            ],
            'price_tier' => [
                'nullable',
                'string',
                'max:50',
            ],
            'is_instrumental' => ['nullable', 'boolean'],

            'contains_ai_generated_content' => [
                'nullable',
                'boolean',
            ],

            'duration_seconds' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'preview_start_seconds' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'lyrics' => ['nullable', 'string'],

            'audio' => [
                'required',
                'file',
                'mimes:wav',
                'max:307200',
            ],

            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $discNumber = $validated['disc_number'] ?? 1;

        $trackNumber = $validated['track_number']
            ?? (
                (int) $release->tracks()
                    ->where('disc_number', $discNumber)
                    ->max('track_number')
                + 1
            );

        $audio = $request->file('audio');

        $audioPath = $audio->store(
            'tracks/audio',
            'public'
        );

        unset($validated['audio']);

        $track = Track::create([
            ...$validated,

            'public_id' => (string) Str::ulid(),
            'release_id' => $release->id,

            'disc_number' => $discNumber,
            'track_number' => $trackNumber,

            'isrc' => $validated['isrc'] ?: null,
            'isrc_is_auto_generated' => false,

            'audio_path' => $audioPath,
            'audio_original_name' =>
                $audio->getClientOriginalName(),
            'audio_mime_type' => $audio->getMimeType(),
            'audio_size_bytes' => $audio->getSize(),
            'audio_validation_status' => 'pending',

            'status' => $validated['status'] ?? 'draft',

            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $release->update([
            'wizard_step' => max(
                2,
                (int) $release->wizard_step
            ),
            'completion_percentage' => max(
                50,
                (int) $release->completion_percentage
            ),
            'updated_by' => Auth::id(),
        ]);

        return back()->with([
            'success' => 'Track added successfully.',
            'created_track_id' => $track->id,
        ]);
    }

    public function update(
        Request $request,
        Track $track
    ) {
        $this->authorizeTrack($request, $track);

        abort_unless(
            in_array(
                $track->release->status,
                ['draft', 'rejected'],
                true
            ),
            403,
            'Tracks cannot be changed after submission.'
        );

        $validated = $request->validate([
            'disc_number' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],

            'track_number' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],

            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'version' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'track_type' => [
                'nullable',
                'string',
                Rule::in([
                    'original',
                    'karaoke',
                    'medley',
                    'cover',
                    'cover_by_cover_band',
                ]),
            ],

            'primary_artist_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'featuring_artist_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'author_name' => ['nullable', 'string', 'max:255'],
            'composer_name' => ['nullable', 'string', 'max:255'],
            'arranger_name' => ['nullable', 'string', 'max:255'],
            'producer_name' => ['nullable', 'string', 'max:255'],
            'music_director_name' => ['nullable', 'string', 'max:255'],
            'publisher_name' => ['nullable', 'string', 'max:255'],
            'p_line' => ['nullable', 'string', 'max:255'],
            'release_year' => [
                'nullable',
                'integer',
                'min:1900',
                'max:' . (date('Y') + 1),
            ],

            'isrc' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('tracks', 'isrc')
                    ->ignore($track->id),
            ],

            'language' => ['nullable', 'string', 'max:100'],
            'title_language' => ['nullable', 'string', 'max:100'],
            'lyrics_language' => ['nullable', 'string', 'max:100'],
            'genre' => ['nullable', 'string', 'max:100'],
            'sub_genre' => ['nullable', 'string', 'max:100'],

            'is_explicit' => ['nullable', 'boolean'],
            'parental_advisory' => [
                'nullable',
                'string',
                Rule::in(['yes', 'no', 'cleaned']),
            ],
            'price_tier' => [
                'nullable',
                'string',
                'max:50',
            ],
            'is_instrumental' => ['nullable', 'boolean'],

            'contains_ai_generated_content' => [
                'nullable',
                'boolean',
            ],

            'duration_seconds' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'preview_start_seconds' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'lyrics' => ['nullable', 'string'],

            'audio' => [
                'nullable',
                'file',
                'mimes:wav',
                'max:307200',
            ],

            'status' => ['nullable', 'string', 'max:30'],
        ]);

        if ($request->hasFile('audio')) {
            if ($track->audio_path) {
                Storage::disk('public')->delete(
                    $track->audio_path
                );
            }

            $audio = $request->file('audio');

            $validated['audio_path'] = $audio->store(
                'tracks/audio',
                'public'
            );

            $validated['audio_original_name'] =
                $audio->getClientOriginalName();

            $validated['audio_mime_type'] =
                $audio->getMimeType();

            $validated['audio_size_bytes'] =
                $audio->getSize();

            $validated['audio_validation_status'] =
                'pending';
        }

        unset($validated['audio']);

        $validated['isrc'] =
            $validated['isrc'] ?? $track->isrc;

        if ($validated['isrc'] === '') {
            $validated['isrc'] = null;
        }

        $track->update([
            ...$validated,
            'updated_by' => Auth::id(),
        ]);

        return back()->with(
            'success',
            'Track updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        Track $track
    ) {
        $this->authorizeTrack($request, $track);

        abort_unless(
            in_array(
                $track->release->status,
                ['draft', 'rejected'],
                true
            ),
            403,
            'Tracks cannot be changed after submission.'
        );

        if ($track->audio_path) {
            Storage::disk('public')->delete(
                $track->audio_path
            );
        }

        $track->update([
            'updated_by' => Auth::id(),
        ]);

        $track->delete();

        return back()->with(
            'success',
            'Track deleted successfully.'
        );
    }

    public function downloadAudio(
        Request $request,
        Track $track
    ) {
        $this->authorizeTrack($request, $track);

        abort_unless(
            $track->audio_path
            && Storage::disk('public')->exists(
                $track->audio_path
            ),
            404,
            'Audio file not found.'
        );

        return Storage::disk('public')->download(
            $track->audio_path,
            $track->audio_original_name
                ?: basename($track->audio_path)
        );
    }
}
