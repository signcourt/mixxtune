<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Models\ReleaseStoreDelivery;
use App\Services\V2\DeliveryWorkflowService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryManagementController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $user = $request->user();

        abort_unless(
            $user,
            401,
            'Authentication required.'
        );

        $role = $permissions->role($user);

        abort_unless(
            in_array(
                $role,
                ['admin', 'super_admin'],
                true
            ),
            403,
            'Admin access required.'
        );

        $status = trim(
            (string) $request->input(
                'status',
                'processing'
            )
        );

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        $query = Release::query()
            ->whereNull('deleted_at')
            ->whereIn(
                'status',
                [
                    'approved',
                    'processing',
                    'delivered',
                    'live',
                    'failed',
                    'takedown_requested',
                    'taken_down',
                ]
            )
            ->withCount([
                'tracks',
            ]);

        if (
            $role === 'admin'
            && Schema::hasColumn(
                'artists',
                'assigned_admin_id'
            )
        ) {
            $artistIds = DB::table('artists')
                ->where(
                    'assigned_admin_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->pluck('id');

            $query->whereIn(
                'artist_id',
                $artistIds
            );
        }

        if ($status !== '') {
            $query->where(
                'status',
                $status
            );
        }

        if ($search !== '') {
            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'title',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'primary_artist_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'upc',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'catalog_number',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        $countQuery = Release::query()
            ->whereNull('deleted_at');

        return Inertia::render(
            'V2/Admin/Delivery/Index',
            [
                'role' => $role,

                'filters' => [
                    'status' => $status,
                    'search' => $search,
                ],

                'counts' => [
                    'processing' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'processing'
                            )
                            ->count(),

                    'delivered' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'delivered'
                            )
                            ->count(),

                    'live' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'live'
                            )
                            ->count(),

                    'failed' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'failed'
                            )
                            ->count(),

                    'takedown_requested' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'takedown_requested'
                            )
                            ->count(),
                ],

                'releases' =>
                    $query
                        ->orderByDesc('id')
                        ->paginate(25)
                        ->withQueryString(),
            ]
        );
    }

    public function show(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): Response {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $deliveries =
            $delivery->deliveries(
                $release
            );

        return Inertia::render(
            'V2/Admin/Delivery/Show',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'release' => [
                    'id' =>
                        $release->id,

                    'title' =>
                        $release->title,

                    'primary_artist_name' =>
                        $release
                            ->primary_artist_name,

                    'status' =>
                        $release->status,

                    'upc' =>
                        $release->upc,

                    'catalog_number' =>
                        $release
                            ->catalog_number,
                ],

                'summary' =>
                    $delivery->summary(
                        $release
                    ),

                'deliveries' =>
                    $deliveries,
            ]
        );
    }

    public function initialise(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $delivery->initialise(
            $release,
            $request->user()
        );

        return redirect()
            ->route(
                'v2.admin.delivery-management.show',
                $release
            )
            ->with(
                'success',
                'DSP delivery records created.'
            );
    }

    public function update(
        Request $request,
        ReleaseStoreDelivery $deliveryRecord,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
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
                'in:processing,delivered,live,failed,takedown_requested,taken_down',
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

        $delivery->transition(
            $deliveryRecord,
            $validated['status'],
            $request->user(),
            $validated
        );

        return back()->with(
            'success',
            'DSP delivery status updated.'
        );
    }

    public function bulkUpdate(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
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
                'in:processing,delivered,live,failed,takedown_requested,taken_down',
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

        $delivery->bulkTransition(
            $release,
            $validated['delivery_ids'],
            $validated['status'],
            $request->user(),
            $validated
        );

        return back()->with(
            'success',
            'Selected DSP deliveries updated.'
        );
    }

    private function authorizeAdmin(
        Request $request,
        PermissionService $permissions
    ): void {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );
    }
}
