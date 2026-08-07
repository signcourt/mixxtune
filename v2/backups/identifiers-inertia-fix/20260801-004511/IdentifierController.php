<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Services\V2\IsrcService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\UpcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdentifierController extends Controller
{
    public function pending(
        Request $request,
        PermissionService $permissions
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.view'
        );

        $trackQuery = Track::query()
            ->with([
                'release:id,title,status,artist_id,label_id',
            ])
            ->whereNull('isrc')
            ->whereNull('deleted_at');

        $releaseQuery = Release::query()
            ->whereNull('upc')
            ->whereNull('deleted_at');

        return response()->json([
            'pending_isrc' =>
                $trackQuery
                    ->orderBy('id')
                    ->paginate(
                        50,
                        ['*'],
                        'isrc_page'
                    ),

            'pending_upc' =>
                $releaseQuery
                    ->orderBy('id')
                    ->paginate(
                        50,
                        ['*'],
                        'upc_page'
                    ),
        ]);
    }

    public function assignIsrc(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        IsrcService $isrc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.assign'
        );

        $track->loadMissing('release');

        $access->authorizeView(
            $request->user(),
            $track->release
        );

        $validated = $request->validate([
            'isrc' => [
                'required',
                'string',
                'max:20',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $track = $isrc->assignManual(
            $track,
            $validated['isrc'],
            $request->user(),
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' =>
                'ISRC assigned successfully.',

            'track' => $track,
        ]);
    }

    public function generateIsrc(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        IsrcService $isrc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.generate'
        );

        $track->loadMissing('release');

        $access->authorizeView(
            $request->user(),
            $track->release
        );

        $validated = $request->validate([
            'country_code' => [
                'nullable',
                'string',
                'size:2',
            ],

            'registrant_code' => [
                'nullable',
                'string',
                'size:3',
            ],

            'reference_year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $track = $isrc->generate(
            $track,
            $request->user(),
            $validated['country_code']
                ?? 'IN',
            $validated['registrant_code']
                ?? 'MXT',
            $validated['reference_year']
                ?? null,
            $validated['notes']
                ?? null
        );

        return response()->json([
            'message' =>
                'ISRC generated successfully.',

            'track' => $track,
        ]);
    }

    public function assignUpc(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        UpcService $upc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.assign'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $validated = $request->validate([
            'upc' => [
                'required',
                'string',
                'max:20',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $release = $upc->assignManual(
            $release,
            $validated['upc'],
            $request->user(),
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' =>
                'UPC assigned successfully.',

            'release' => $release,
        ]);
    }

    public function generateUpc(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        UpcService $upc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.generate'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $validated = $request->validate([
            'prefix' => [
                'nullable',
                'string',
                'min:6',
                'max:10',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $release = $upc->generate(
            $release,
            $request->user(),
            $validated['prefix']
                ?? '890000',
            $validated['notes']
                ?? null
        );

        return response()->json([
            'message' =>
                'UPC generated successfully.',

            'release' => $release,
        ]);
    }

    public function bulkGenerateIsrc(
        Request $request,
        PermissionService $permissions,
        IsrcService $isrc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.bulk_assign'
        );

        $validated = $request->validate([
            'track_ids' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'track_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:tracks,id',
            ],

            'country_code' => [
                'nullable',
                'string',
                'size:2',
            ],

            'registrant_code' => [
                'nullable',
                'string',
                'size:3',
            ],

            'reference_year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],
        ]);

        $tracks = Track::query()
            ->whereIn(
                'id',
                $validated['track_ids']
            )
            ->whereNull('isrc')
            ->get();

        $assigned = [];

        foreach ($tracks as $track) {
            $assigned[] = $isrc->generate(
                $track,
                $request->user(),
                $validated['country_code']
                    ?? 'IN',
                $validated['registrant_code']
                    ?? 'MXT',
                $validated['reference_year']
                    ?? null
            );
        }

        return response()->json([
            'message' =>
                count($assigned)
                . ' ISRC codes generated.',

            'tracks' => $assigned,
        ]);
    }

    public function bulkGenerateUpc(
        Request $request,
        PermissionService $permissions,
        UpcService $upc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.bulk_assign'
        );

        $validated = $request->validate([
            'release_ids' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'release_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:releases,id',
            ],

            'prefix' => [
                'nullable',
                'string',
                'min:6',
                'max:10',
            ],
        ]);

        $releases = Release::query()
            ->whereIn(
                'id',
                $validated['release_ids']
            )
            ->whereNull('upc')
            ->get();

        $assigned = [];

        foreach ($releases as $release) {
            $assigned[] = $upc->generate(
                $release,
                $request->user(),
                $validated['prefix']
                    ?? '890000'
            );
        }

        return response()->json([
            'message' =>
                count($assigned)
                . ' UPC codes generated.',

            'releases' => $assigned,
        ]);
    }
}
