<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\DeliveryWorkflowService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function show(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'releases.view'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        return response()->json([
            'release' => [
                'id' => $release->id,
                'title' => $release->title,
                'status' => $release->status,
                'upc' => $release->upc,
                'catalog_number' =>
                    $release->catalog_number,
            ],

            'summary' =>
                $delivery->summary($release),

            'deliveries' =>
                $delivery->deliveries(
                    $release
                ),
        ]);
    }
}
