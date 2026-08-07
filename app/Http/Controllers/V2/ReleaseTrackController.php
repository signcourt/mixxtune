<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Services\V2\AdminAssignmentService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\AudioValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReleaseTrackController extends Controller
{
    public function store(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        AudioValidationService $audioValidator
    ): RedirectResponse {
        $access->authorizeUpdate(
            $request->user(),
            $release
        );

        $validated = $this->validateTrack(
            $request,
            null,
            true
        );

        $discNumber = (int) (
            $validated['disc_number'] ?? 1
        );

        $trackNumber = (int) (
            $validated['track_number']
            ?? (
                (int) $release
                    ->tracks()
                    ->where(
                        'disc_number',
                        $discNumber
                    )
                    ->max('track_number')
                + 1
            )
        );

        $audio = $request->file('audio');

        $audioPath = $audio->store(
            'tracks/audio',
            'public'
        );

        unset($validated['audio']);

        $track = Track::query()->create([
            ...$validated,

            'public_id' =>
                (string) Str::ulid(),

            'release_id' =>
                $release->id,

            'disc_number' =>
                $discNumber,

            'track_number' =>
                $trackNumber,

            'isrc' =>
                $validated['isrc'] ?: null,

            'isrc_is_auto_generated' =>
                false,

            'audio_path' =>
                $audioPath,

            'audio_original_name' =>
                $audio->getClientOriginalName(),

            'audio_mime_type' =>
                $audio->getMimeType(),

            'audio_size_bytes' =>
                $audio->getSize(),

            'audio_validation_status' =>
                'pending',

            'status' =>
                $validated['status'] ?? 'draft',

            'created_by' =>
                Auth::id(),

            'updated_by' =>
                Auth::id(),
        ]);

        $release->update([
            'wizard_step' => max(
                2,
                (int) $release->wizard_step
            ),

            'completion_percentage' => max(
                50,
                (int) $release
                    ->completion_percentage
            ),

            'updated_by' =>
                Auth::id(),
        ]);

        $audioValidator->validate(
            $track,
            $request->user()
        );

        return back()->with([
            'success' =>
                'Track added successfully.',

            'created_track_id' =>
                $track->id,
        ]);
    }

    public function update(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        AudioValidationService $audioValidator
    ): RedirectResponse {
        $track->loadMissing('release');

        abort_unless(
            $track->release,
            404,
            'Release not found.'
        );

        $access->authorizeUpdate(
            $request->user(),
            $track->release
        );

        $validated = $this->validateTrack(
            $request,
            $track,
            false
        );

        if ($request->hasFile('audio')) {
            if ($track->audio_path) {
                Storage::disk('public')->delete(
                    $track->audio_path
                );
            }

            $audio = $request->file('audio');

            $validated['audio_path'] =
                $audio->store(
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

        if (
            array_key_exists('isrc', $validated)
            && $validated['isrc'] === ''
        ) {
            $validated['isrc'] = null;
        }

        $audioWasReplaced =
            $request->hasFile('audio');

        $track->update([
            ...$validated,
            'updated_by' => Auth::id(),
        ]);

        if ($audioWasReplaced) {
            $audioValidator->validate(
                $track->fresh(),
                $request->user()
            );
        }

        return back()->with(
            'success',
            'Track updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): RedirectResponse {
        $track->loadMissing('release');

        abort_unless(
            $track->release,
            404,
            'Release not found.'
        );

        $access->authorizeUpdate(
            $request->user(),
            $track->release
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

    public function download(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access
    ) {
        $track->loadMissing('release');

        abort_unless(
            $track->release,
            404,
            'Release not found.'
        );

        $access->authorizeView(
            $request->user(),
            $track->release
        );

        abort_unless(
            $track->audio_path
            && Storage::disk('public')
                ->exists($track->audio_path),
            404,
            'Audio file not found.'
        );

        return Storage::disk('public')->download(
            $track->audio_path,
            $track->audio_original_name
                ?: basename($track->audio_path)
        );
    }

    private function validateTrack(
        Request $request,
        ?Track $track,
        bool $audioRequired
    ): array {
        return $request->validate([
            'disc_number' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'track_number' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'version' => [
                'nullable',
                'string',
                'max:255',
            ],

            'subtitle' => [
                'nullable',
                'string',
                'max:255',
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

            'isrc' => [
                'nullable',
                'string',
                'max:20',

                $track
                    ? Rule::unique(
                        'tracks',
                        'isrc'
                    )->ignore($track->id)
                    : Rule::unique(
                        'tracks',
                        'isrc'
                    ),
            ],

            'language' => [
                'nullable',
                'string',
                'max:100',
            ],

            'genre' => [
                'nullable',
                'string',
                'max:100',
            ],

            'sub_genre' => [
                'nullable',
                'string',
                'max:100',
            ],

            'is_explicit' => [
                'nullable',
                'boolean',
            ],

            'is_instrumental' => [
                'nullable',
                'boolean',
            ],

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

            'lyrics' => [
                'nullable',
                'string',
            ],

            'audio' => [
                $audioRequired
                    ? 'required'
                    : 'nullable',

                'file',
                'mimes:wav,wave',
                'max:307200',
            ],

            'status' => [
                'nullable',
                'string',
                'max:30',
            ],
        ]);
    }

    private function ensureEditable(
        Release $release
    ): void {
        abort_unless(
            in_array(
                $release->status,
                [
                    'draft',
                    'changes_requested',
                    'rejected',
                ],
                true
            ),
            403,
            'This release is locked for editing.'
        );
    }

    private function authorizeRelease(
        Request $request,
        Release $release,
        PermissionService $permissions
    ): void {
        $role = $permissions->role(
            $request->user()
        );

        if ($role === 'super_admin') {
            return;
        }

        if ($role === 'artist') {
            $artist = Artist::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->firstOrFail();

            abort_unless(
                (int) $release->artist_id
                    === (int) $artist->id,
                403,
                'You cannot manage this release.'
            );

            return;
        }

        if ($role === 'label') {
            $label = Label::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->firstOrFail();

            abort_unless(
                (int) $release->label_id
                    === (int) $label->id,
                403,
                'You cannot manage this release.'
            );

            return;
        }

        if ($role === 'admin') {
            $assignments = app(
                AdminAssignmentService::class
            );

            abort_unless(
                $assignments->canAccessRelease(
                    $request->user(),
                    $release->artist_id
                        ? (int) $release->artist_id
                        : null,
                    $release->label_id
                        ? (int) $release->label_id
                        : null
                ),
                403,
                'This release is not assigned to you.'
            );
        }
    }
}
