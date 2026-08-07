<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\CatalogueService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogueController extends Controller
{
    public function syncRelease(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        CatalogueService $catalogue
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'catalogue.sync'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $item = $catalogue->syncRelease(
            $release,
            $request->user()
        );

        return response()->json([
            'message' =>
                'Release synchronized with catalogue.',

            'catalogue_item' =>
                $item,
        ]);
    }

    public function bulkSync(
        Request $request,
        PermissionService $permissions,
        CatalogueService $catalogue
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'catalogue.sync'
        );

        $validated = $request->validate([
            'statuses' => [
                'nullable',
                'array',
                'min:1',
            ],

            'statuses.*' => [
                'string',
                'in:approved,processing,delivered,live,takedown_requested,taken_down',
            ],

            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:10000',
            ],
        ]);

        $result = $catalogue->syncByStatus(
            $validated['statuses']
                ?? [
                    'approved',
                    'processing',
                    'delivered',
                    'live',
                    'takedown_requested',
                ],
            $request->user(),
            $validated['limit'] ?? 1000
        );

        return response()->json([
            'message' =>
                'Catalogue synchronization completed.',

            'result' =>
                $result,
        ]);
    }
}
