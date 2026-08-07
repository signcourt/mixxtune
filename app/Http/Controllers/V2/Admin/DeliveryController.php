<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Models\ReleaseStoreDelivery;
use App\Services\V2\DeliveryWorkflowService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function index(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'delivery.view'
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

    public function initialise(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'delivery.manage'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $deliveries =
            $delivery->initialise(
                $release,
                $request->user()
            );

        return response()->json([
            'message' =>
                'Delivery records initialised.',

            'summary' =>
                $delivery->summary(
                    $release
                ),

            'deliveries' =>
                $deliveries,
        ]);
    }

    public function update(
        Request $request,
        ReleaseStoreDelivery $deliveryRecord,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'delivery.manage'
        );

        $deliveryRecord->loadMissing(
            'release'
        );

        abort_unless(
            $deliveryRecord->release,
            404,
            'Release not found.'
        );

        $access->authorizeView(
            $request->user(),
            $deliveryRecord->release
        );

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:pending,processing,delivered,live,failed,takedown_requested,taken_down',
            ],

            'delivery_note' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'error_message' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'external_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $record = $delivery->transition(
            $deliveryRecord,
            $validated['status'],
            $request->user(),
            $validated
        );

        return response()->json([
            'message' =>
                'Store delivery status updated.',

            'delivery' =>
                $record,

            'summary' =>
                $delivery->summary(
                    $record->release
                ),
        ]);
    }

    public function bulkUpdate(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'delivery.manage'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $validated = $request->validate([
            'delivery_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'delivery_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:release_store_deliveries,id',
            ],

            'status' => [
                'required',
                'string',
                'in:pending,processing,delivered,live,failed,takedown_requested,taken_down',
            ],

            'delivery_note' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'error_message' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'external_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $deliveries =
            $delivery->bulkTransition(
                $release,
                $validated['delivery_ids'],
                $validated['status'],
                $request->user(),
                $validated
            );

        return response()->json([
            'message' =>
                'Selected store deliveries updated.',

            'summary' =>
                $delivery->summary(
                    $release
                ),

            'deliveries' =>
                $deliveries,
        ]);
    }
}
