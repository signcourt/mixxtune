<?php

namespace App\Http\Controllers\Admin;

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
    public function store(Request $request, Release $release)
    {
        $validated = $request->validate([
            'disc_number' => ['nullable', 'integer', 'min:1'],
            'track_number' => ['nullable', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],

            'primary_artist_name' => ['required', 'string', 'max:255'],
            'featuring_artist_name' => ['nullable', 'string', 'max:255'],

            'isrc' => ['nullable', 'string', 'max:20', 'unique:tracks,isrc'],
            'isrc_is_auto_generated' => ['nullable', 'boolean'],

            'language' => ['nullable', 'string', 'max:100'],
            'genre' => ['nullable', 'string', 'max:100'],
            'sub_genre' => ['nullable', 'string', 'max:100'],

            'is_explicit' => ['nullable', 'boolean'],
            'is_instrumental' => ['nullable', 'boolean'],
            'contains_ai_generated_content' => ['nullable', 'boolean'],

            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'preview_start_seconds' => ['nullable', 'integer', 'min:0'],
            'lyrics' => ['nullable', 'string'],

            'audio' => [
                'nullable',
                'file',
                'mimes:wav',
                'max:307200',
            ],

            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $discNumber = $validated['disc_number'] ?? 1;

        $trackNumber = $validated['track_number']
            ?? ((int) $release->tracks()
                ->where('disc_number', $discNumber)
                ->max('track_number') + 1);

        $audioData = [];

        if ($request->hasFile('audio')) {
            $audio = $request->file('audio');
            $path = $audio->store('tracks/audio', 'public');

            $audioData = [
                'audio_path' => $path,
                'audio_original_name' => $audio->getClientOriginalName(),
                'audio_mime_type' => $audio->getMimeType(),
                'audio_size_bytes' => $audio->getSize(),
                'audio_validation_status' => 'pending',
            ];
        }

        $track = Track::create([
            ...$validated,
            ...$audioData,
            'public_id' => (string) Str::ulid(),
            'release_id' => $release->id,
            'disc_number' => $discNumber,
            'track_number' => $trackNumber,
            'status' => $validated['status'] ?? 'draft',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return back()->with([
            'success' => 'Track added successfully.',
            'created_track_id' => $track->id,
        ]);
    }

    public function update(Request $request, Track $track)
    {
        $validated = $request->validate([
            'disc_number' => ['sometimes', 'required', 'integer', 'min:1'],
            'track_number' => ['sometimes', 'required', 'integer', 'min:1'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],

            'primary_artist_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'featuring_artist_name' => ['nullable', 'string', 'max:255'],

            'isrc' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('tracks', 'isrc')->ignore($track->id),
            ],
            'isrc_is_auto_generated' => ['nullable', 'boolean'],

            'language' => ['nullable', 'string', 'max:100'],
            'genre' => ['nullable', 'string', 'max:100'],
            'sub_genre' => ['nullable', 'string', 'max:100'],

            'is_explicit' => ['nullable', 'boolean'],
            'is_instrumental' => ['nullable', 'boolean'],
            'contains_ai_generated_content' => ['nullable', 'boolean'],

            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'preview_start_seconds' => ['nullable', 'integer', 'min:0'],
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
                Storage::disk('public')->delete($track->audio_path);
            }

            $audio = $request->file('audio');

            $validated['audio_path'] = $audio->store(
                'tracks/audio',
                'public'
            );
            $validated['audio_original_name'] =
                $audio->getClientOriginalName();
            $validated['audio_mime_type'] = $audio->getMimeType();
            $validated['audio_size_bytes'] = $audio->getSize();
            $validated['audio_validation_status'] = 'pending';
        }

        unset($validated['audio']);

        $track->update([
            ...$validated,
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Track updated successfully.');
    }

    public function destroy(Track $track)
    {
        if ($track->audio_path) {
            Storage::disk('public')->delete($track->audio_path);
        }

        $track->update([
            'updated_by' => Auth::id(),
        ]);

        $track->delete();

        return back()->with('success', 'Track deleted successfully.');
    }
}
