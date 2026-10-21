<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Models\ReleaseStoreDelivery;
use App\Services\V2\AdminAssignmentService;
use App\Services\V2\DeliveryWorkflowService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryManagementController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        AdminAssignmentService $assignments
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

        /* W58: "all", plus releases with a failed / takedown store */
        $storeFilter = in_array($status, ['store_failed', 'store_takedown'], true) ? $status : null;
        if ($status === 'all' || $storeFilter) {
            $status = $storeFilter ?? 'all';
        }

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        $query = Release::query()
            ->select(['releases.id', 'releases.public_id', 'releases.title', 'releases.primary_artist_name', 'releases.upc', 'releases.catalog_number', 'releases.status', 'releases.artwork_path', 'releases.artist_id', 'releases.label_id', 'releases.updated_at'])
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

        $artistIds = collect();
        $labelIds = collect();

        if ($role === 'admin') {
            $artistIds =
                $assignments->artistIds($user);

            $labelIds =
                $assignments->labelIds($user);

            if (
                $artistIds->isEmpty()
                && $labelIds->isEmpty()
            ) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(
                    function ($builder) use (
                        $artistIds,
                        $labelIds
                    ) {
                        if ($artistIds->isNotEmpty()) {
                            $builder->whereIn(
                                'artist_id',
                                $artistIds
                            );
                        }

                        if ($labelIds->isNotEmpty()) {
                            if ($artistIds->isNotEmpty()) {
                                $builder->orWhereIn(
                                    'label_id',
                                    $labelIds
                                );
                            } else {
                                $builder->whereIn(
                                    'label_id',
                                    $labelIds
                                );
                            }
                        }
                    }
                );
            }
        }

        if ($status === 'store_failed') {
            $query->whereHas('storeDeliveries', fn ($d) => $d->where('status', 'failed'));
        } elseif ($status === 'store_takedown') {
            $query->whereHas('storeDeliveries', fn ($d) => $d->whereIn('status', ['takedown_requested']));
        } elseif ($status !== '' && $status !== 'all') {
            $query->where(
                'status',
                $status
            );
        }

        $query->withCount([
            'storeDeliveries as stores_total',
            'storeDeliveries as stores_live' => fn ($d) => $d->where('status', 'live'),
            'storeDeliveries as stores_failed' => fn ($d) => $d->where('status', 'failed'),
        ]);

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

        if ($role === 'admin') {
            if (
                $artistIds->isEmpty()
                && $labelIds->isEmpty()
            ) {
                $countQuery->whereRaw('1 = 0');
            } else {
                $countQuery->where(
                    function ($builder) use (
                        $artistIds,
                        $labelIds
                    ) {
                        if ($artistIds->isNotEmpty()) {
                            $builder->whereIn(
                                'artist_id',
                                $artistIds
                            );
                        }

                        if ($labelIds->isNotEmpty()) {
                            if ($artistIds->isNotEmpty()) {
                                $builder->orWhereIn(
                                    'label_id',
                                    $labelIds
                                );
                            } else {
                                $builder->whereIn(
                                    'label_id',
                                    $labelIds
                                );
                            }
                        }
                    }
                );
            }
        }

        return Inertia::render(
            'V2/Admin/Delivery/Index',
            [
                'role' => $role,

                'filters' => [
                    'status' => $status,
                    'search' => $search,
                ],

                'counts' => [
                    /* W58 */
                    'approved' =>
                        (clone $countQuery)
                            ->where('status', 'approved')
                            ->count(),
                    'store_failed' =>
                        (clone $countQuery)
                            ->whereHas('storeDeliveries', fn ($d) => $d->where('status', 'failed'))
                            ->count(),
                    'store_takedown' =>
                        (clone $countQuery)
                            ->whereHas('storeDeliveries', fn ($d) => $d->where('status', 'takedown_requested'))
                            ->count(),
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

                /* W58 */
                'readiness' => $deliveries->isEmpty() ? $delivery->readiness($release) : null,

                'history' => !\Illuminate\Support\Facades\Schema::hasTable('audit_logs') ? [] : \App\Models\System\AuditLog::query()
                    ->where('module', 'delivery')
                    ->where('action', 'delivery.status_changed')
                    ->where('auditable_type', \App\Models\ReleaseStoreDelivery::class)
                    ->whereIn('auditable_id', $deliveries->pluck('id'))
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get(['id', 'user_id', 'auditable_id', 'old_values', 'new_values', 'created_at'])
                    ->map(function ($log) use ($deliveries) {
                        $d = $deliveries->firstWhere('id', $log->auditable_id);

                        return [
                            'id' => $log->id,
                            'store' => $d?->store?->name ?? '#'.$log->auditable_id,
                            'from' => $log->old_values['status'] ?? null,
                            'to' => $log->new_values['status'] ?? null,
                            'note' => $log->new_values['note'] ?? ($log->new_values['error'] ?? null),
                            'by' => \App\Models\User::query()->whereKey($log->user_id)->value('name') ?? '—',
                            'at' => optional($log->created_at)->timezone('Asia/Kolkata')?->format('d M Y, h:i A'),
                        ];
                    })
                    ->values(),

                'canStart' => in_array($release->status, ['approved', 'processing', 'delivered', 'live'], true),
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

        /*
         * Validate delivery eligibility before release visibility.
         *
         * Draft releases are creator-private in ReleaseAccessService.
         * For this workflow, an authorised admin attempting to initialise
         * an ineligible release must receive the delivery-specific 422
         * response rather than a draft-access 403.
         */
        abort_unless(
            in_array(
                $release->status,
                [
                    'approved',
                    'processing',
                    'delivered',
                    'live',
                ],
                true
            ),
            422,
            'Release must be approved before delivery starts.'
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

        $this->authorizeStatus($request, $permissions, $validated['status']);

        /* W58: live / takedown need the e-mailed code */
        if (in_array($validated['status'], self::CONFIRM_STATUSES, true)) {
            app(\App\Services\V2\ActionOtpGuard::class)->require(
                $request->user(),
                'delivery.'.$validated['status'].':'.$deliveryRecord->id,
                'mark store delivery #'.$deliveryRecord->id.' of "'.$deliveryRecord->release->title.'" as '.str_replace('_', ' ', $validated['status'])
            );
        }

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

        $this->authorizeStatus($request, $permissions, $validated['status']);

        if (in_array($validated['status'], self::CONFIRM_STATUSES, true)) {
            $ids = collect($validated['delivery_ids'])->map(fn ($v) => (int) $v)->sort()->values()->implode(',');
            app(\App\Services\V2\ActionOtpGuard::class)->require(
                $request->user(),
                'delivery.bulk.'.$validated['status'].':'.$release->id.':'.sha1($ids),
                'mark '.count($validated['delivery_ids']).' store(s) of "'.$release->title.'" as '.str_replace('_', ' ', $validated['status'])
            );
        }

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

    private const CONFIRM_STATUSES = ['live', 'takedown_requested', 'taken_down'];

    /* W58: delivery permissions really apply (an admin without "takedown" cannot take down) */
    private function authorizeStatus(Request $request, PermissionService $permissions, string $status): void
    {
        $permissions->authorize($request->user(), 'delivery.manage');

        $specific = match ($status) {
            'delivered' => 'delivery.mark_delivered',
            'live' => 'delivery.mark_live',
            'failed' => 'delivery.mark_failed',
            'takedown_requested', 'taken_down' => 'delivery.takedown',
            default => null,
        };

        if ($specific) {
            $permissions->authorize($request->user(), $specific);
        }
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
