<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Track;
use App\Services\V2\AudioValidationService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AudioValidationController extends Controller
{
    public function show(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'audio_validation.view'
        );

        $track->loadMissing('release');

        $access->authorizeView(
            $request->user(),
            $track->release
        );

        return response()->json([
            'track' => [
                'id' =>
                    $track->id,

                'title' =>
                    $track->title,

                'audio_path' =>
                    $track->audio_path,

                'validation_status' =>
                    $track
                        ->audio_validation_status,

                'validation_errors' =>
                    $track
                        ->audio_validation_errors,

                'metadata' =>
                    $track->audio_metadata,

                'validated_at' =>
                    $track->audio_validated_at,
            ],
        ]);
    }

    public function validateTrack(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        AudioValidationService $validator
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'audio_validation.run'
        );

        $track->loadMissing('release');

        $access->authorizeView(
            $request->user(),
            $track->release
        );

        $result = $validator->validate(
            $track,
            $request->user()
        );

        return response()->json([
            'message' =>
                $result['passed']
                    ? 'Audio validation passed.'
                    : 'Audio validation failed.',

            ...$result,
        ]);
    }

    public function bulkValidate(
        Request $request,
        PermissionService $permissions,
        AudioValidationService $validator
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'audio_validation.run'
        );

        $validated = $request->validate([
            'track_ids' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],

            'track_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:tracks,id',
            ],
        ]);

        $tracks = Track::query()
            ->whereIn(
                'id',
                $validated['track_ids']
            )
            ->whereNotNull('audio_path')
            ->get();

        return response()->json(
            $validator->validateMany(
                $tracks,
                $request->user()
            )
        );
    }
}
