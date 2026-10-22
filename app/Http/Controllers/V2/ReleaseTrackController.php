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
use App\Services\V2\ReleaseAdminEditService;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
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
    /* MIXXTUNE_SUPER_ADMIN_FULL_EDIT: fields written to the audit log */
    private const AUDIT_FIELDS = [
        'disc_number', 'track_number', 'title', 'version', 'subtitle',
        'primary_artist_name', 'featuring_artist_name', 'isrc', 'language',
        'genre', 'sub_genre', 'is_explicit', 'is_instrumental',
        'contains_ai_generated_content', 'duration_seconds',
        'preview_start_seconds', 'audio_path', 'audio_original_name',
        'track_type', 'author_name', 'composer_name', 'arranger_name', 'producer_name',
        'music_director_name', 'publisher_name', 'p_line', 'release_year',
        'title_language', 'lyrics_language', 'parental_advisory',
    ];

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

        /* W87: the WAV may already be uploaded in the background (token) */
        $audioFile = $this->takeAudio($request, $release);
        abort_unless($audioFile, 422, 'Upload the WAV master first.');

        unset($validated['audio'], $validated['audio_upload_token']);

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
                ($validated['isrc'] ?? null) ?: null,

            'isrc_is_auto_generated' =>
                false,

            'audio_path' =>
                $audioFile['path'],

            'audio_original_name' =>
                $audioFile['name'],

            'audio_mime_type' =>
                $audioFile['mime'],

            'audio_size_bytes' =>
                $audioFile['size'],

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

        $adminEditService = app(ReleaseAdminEditService::class);

        if ($adminEditService->active($request, $release)) {
            $adminEditService->record(
                $release,
                'tracks (added track #'.$track->id.')',
                [],
                $track->fresh()->only(self::AUDIT_FIELDS),
                $request
            );
        }

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

        $adminEditService = app(ReleaseAdminEditService::class);
        $adminEdit = $adminEditService->active($request, $track->release);
        $before = $adminEdit ? $track->only(self::AUDIT_FIELDS) : [];

        /* W87: a new WAV comes as a file or as a background-upload token */
        $audioFile = $this->takeAudio($request, $track->release);

        if ($audioFile) {
            /* Super Admin edit keeps the old audio file on disk (recoverable). */
            if ($track->audio_path && !$adminEdit) {
                Storage::disk('public')->delete(
                    $track->audio_path
                );
            }

            $validated['audio_path'] = $audioFile['path'];
            $validated['audio_original_name'] = $audioFile['name'];
            $validated['audio_mime_type'] = $audioFile['mime'];
            $validated['audio_size_bytes'] = $audioFile['size'];
            $validated['audio_validation_status'] = 'pending';
        }

        unset($validated['audio'], $validated['audio_upload_token']);

        if (
            array_key_exists('isrc', $validated)
            && $validated['isrc'] === ''
        ) {
            $validated['isrc'] = null;
        }

        $audioWasReplaced =
            $audioFile !== null;

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

        if ($adminEdit) {
            $adminEditService->record(
                $track->release,
                'track #'.$track->id.($audioWasReplaced ? ' (audio replaced)' : ''),
                $before,
                $track->fresh()->only(self::AUDIT_FIELDS),
                $request
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

        $adminEditService = app(ReleaseAdminEditService::class);
        $adminEdit = $adminEditService->active($request, $track->release);

        if ($adminEdit) {
            /*
             * A track that already has revenue rows must stay, or old
             * statements would lose their track. Edit or replace it instead.
             */
            abort_if(
                $adminEditService->trackHasRevenue((int) $track->id),
                422,
                'This track already has revenue in reports, so it cannot be deleted. Edit it or replace its audio instead.'
            );

            $before = $track->only(self::AUDIT_FIELDS);
        }

        if ($track->audio_path && !$adminEdit) {
            Storage::disk('public')->delete(
                $track->audio_path
            );
        }

        $track->update([
            'updated_by' => Auth::id(),
        ]);

        $track->delete();

        if ($adminEdit) {
            $adminEditService->record(
                $track->release,
                'tracks (deleted track #'.$track->id.')',
                $before,
                [],
                $request
            );
        }

        return back()->with(
            'success',
            'Track deleted successfully.'
        );
    }

    public function stream(
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

        $teamAccess = app(
            LabelTeamAccessService::class
        );

        if ($teamAccess->membership($request->user())) {
            abort_unless(
                $teamAccess->canAccessTrack(
                    $request->user(),
                    $track
                ),
                403,
                'You cannot access this track.'
            );
        }

        abort_unless(
            $track->audio_path
            && Storage::disk('public')
                ->exists($track->audio_path),
            404,
            'Audio file not found.'
        );

        $path = Storage::disk('public')
            ->path($track->audio_path);

        $mime = $track->audio_mime_type
            ?: 'audio/wav';

        return response()->file(
            $path,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'
                    . addslashes(
                        $track->audio_original_name
                        ?: basename($track->audio_path)
                    )
                    . '"',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, no-store',
            ]
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

        $teamAccess = app(
            LabelTeamAccessService::class
        );

        if ($teamAccess->membership($request->user())) {
            abort_unless(
                $teamAccess->canAccessTrack(
                    $request->user(),
                    $track
                ),
                403,
                'You cannot access this track.'
            );
        }

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

    /*
     * W87 MIXXTUNE_BACKGROUND_WAV_UPLOAD
     * The WAV is sent as soon as it is picked; the owner fills the track
     * details meanwhile. The file is checked like a normal upload and kept
     * for 24 hours under a one-time token tied to this user and release.
     */
    public function uploadAudio(
        Request $request,
        Release $release,
        ReleaseAccessService $access
    ): \Illuminate\Http\JsonResponse {
        $access->authorizeUpdate($request->user(), $release);

        $request->validate([
            'audio' => [
                'required',
                'file',
                'mimes:wav,wave',
                'max:'.(\App\Services\V2\PlatformRules::audioMaxMb() * 1024),
            ],
        ], [
            'audio.mimes' => 'Only WAV files can be uploaded.',
        ]);

        $audio = $request->file('audio');
        $token = Str::random(40);

        \Illuminate\Support\Facades\Cache::put('track-audio-upload:'.$token, [
            'user_id' => (int) $request->user()->id,
            'release_id' => (int) $release->id,
            'path' => $audio->store('tracks/audio', 'public'),
            'name' => $audio->getClientOriginalName(),
            'mime' => $audio->getMimeType(),
            'size' => $audio->getSize(),
        ], now()->addDay());

        return response()->json([
            'token' => $token,
            'name' => $audio->getClientOriginalName(),
            'size' => $audio->getSize(),
        ]);
    }

    /** the WAV of this request: a file sent now, or one uploaded before with a token */
    private function takeAudio(Request $request, Release $release): ?array
    {
        if ($request->hasFile('audio')) {
            $audio = $request->file('audio');

            return [
                'path' => $audio->store('tracks/audio', 'public'),
                'name' => $audio->getClientOriginalName(),
                'mime' => $audio->getMimeType(),
                'size' => $audio->getSize(),
            ];
        }

        $token = (string) $request->input('audio_upload_token', '');
        if ($token === '') {
            return null;
        }

        $upload = \Illuminate\Support\Facades\Cache::get('track-audio-upload:'.$token);

        if (
            !is_array($upload)
            || (int) $upload['user_id'] !== (int) $request->user()->id
            || (int) $upload['release_id'] !== (int) $release->id
            || !Storage::disk('public')->exists($upload['path'])
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'audio' => 'The uploaded WAV was not found (it is kept for 24 hours). Please choose the file again.',
            ]);
        }

        /* used once - only by the user and release it was made for */
        \Illuminate\Support\Facades\Cache::forget('track-audio-upload:'.$token);

        return $upload;
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

            /*
             * W65 MIXXTUNE_TRACK_CREDITS_SAVED: the form sent these but they
             * were not in this list, so they were silently dropped
             * (lyricist, composer, producer ... were never saved).
             */
            'track_type' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['original', 'karaoke', 'medley', 'cover', 'cover_by_cover_band'])],
            'author_name' => ['nullable', 'string', 'max:255'],
            'composer_name' => ['nullable', 'string', 'max:255'],
            'arranger_name' => ['nullable', 'string', 'max:255'],
            'producer_name' => ['nullable', 'string', 'max:255'],
            'music_director_name' => ['nullable', 'string', 'max:255'],
            'publisher_name' => ['nullable', 'string', 'max:255'],
            'p_line' => ['nullable', 'string', 'max:255'],
            'release_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'title_language' => ['nullable', 'string', 'max:100'],
            'lyrics_language' => ['nullable', 'string', 'max:100'],
            'parental_advisory' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['yes', 'no', 'cleaned'])],

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
                /* MIXXTUNE_SETTINGS_WIRED_V1: Master Settings > Release Rules > Allow Explicit Content */
                function ($attribute, $value, $fail) {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN) && !\App\Services\V2\PlatformRules::allowExplicit() && (request()->user()?->role ?? null) !== 'super_admin') {
                        $fail('Explicit content is switched off in Master Settings. Untick "Explicit" for this track.');
                    }
                },
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

            /* W87: uploaded in the background while the details are filled */
            'audio_upload_token' => [
                'nullable',
                'string',
                'size:40',
            ],

            'audio' => [
                $audioRequired && !$request->filled('audio_upload_token')
                    ? 'required'
                    : 'nullable',

                'file',
                'mimes:wav,wave',
                /* MIXXTUNE_SETTINGS_WIRED_V1: Master Settings > Upload Rules > Audio Maximum MB */
                'max:'.(\App\Services\V2\PlatformRules::audioMaxMb() * 1024),
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
