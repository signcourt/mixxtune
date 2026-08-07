<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\AdminAssignmentService;
use App\Services\V2\AudioValidationService;
use App\Services\V2\DeliveryWorkflowService;
use App\Services\V2\IsrcService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseValidationService;
use App\Services\V2\UpcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProcessingController extends Controller
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
                'approved'
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
            ->withCount('tracks')
            ->whereIn(
                'status',
                [
                    'approved',
                    'processing',
                    'delivered',
                    'failed',
                ]
            );

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
            'V2/Admin/Processing/Index',
            [
                'role' => $role,

                'filters' => [
                    'status' => $status,
                    'search' => $search,
                ],

                'counts' => [
                    'approved' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'approved'
                            )
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

                    'failed' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'failed'
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
        ReleaseValidationService $validator,
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

        $release->load([
            'tracks' => function ($query) {
                $query
                    ->orderBy('disc_number')
                    ->orderBy('track_number')
                    ->orderBy('id');
            },
        ]);

        $tracks = $release->tracks;

        $audioPassed = $tracks->isNotEmpty()
            && $tracks->every(
                fn ($track) =>
                    $track->audio_validation_status
                    === 'passed'
            );

        $allIsrcAssigned = $tracks->isNotEmpty()
            && $tracks->every(
                fn ($track) =>
                    filled($track->isrc)
            );

        $submissionChecklist =
            $validator->checklist($release);

        $deliverySummary =
            $delivery->summary($release);

        return Inertia::render(
            'V2/Admin/Processing/Show',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'release' => $release,

                'checks' => [
                    'metadata' =>
                        (bool) (
                            $submissionChecklist[
                                'checks'
                            ]['metadata']
                            ?? false
                        ),

                    'artists' =>
                        (bool) (
                            $submissionChecklist[
                                'checks'
                            ]['artists']
                            ?? false
                        ),

                    'tracks' =>
                        (bool) (
                            $submissionChecklist[
                                'checks'
                            ]['tracks']
                            ?? false
                        ),

                    'distribution' =>
                        (bool) (
                            $submissionChecklist[
                                'checks'
                            ]['distribution']
                            ?? false
                        ),

                    'audio' =>
                        $audioPassed,

                    'isrc' =>
                        $allIsrcAssigned,

                    'upc' =>
                        filled($release->upc),

                    'delivery_initialised' =>
                        ($deliverySummary['total']
                            ?? 0) > 0,
                ],

                'validationErrors' =>
                    $submissionChecklist[
                        'errors'
                    ] ?? [],

                'deliverySummary' =>
                    $deliverySummary,
            ]
        );
    }

    public function generateIdentifiers(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        IsrcService $isrc,
        UpcService $upc
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $release->load('tracks');

        $isrcCount = 0;
        $upcCreated = false;

        foreach ($release->tracks as $track) {
            if (!$track->isrc) {
                $isrc->generate(
                    $track,
                    $request->user()
                );

                $isrcCount++;
            }
        }

        if (!$release->upc) {
            $upc->generate(
                $release,
                $request->user()
            );

            $upcCreated = true;
        }

        return back()->with(
            'success',
            "{$isrcCount} ISRC generated. "
            . (
                $upcCreated
                    ? 'UPC generated.'
                    : 'UPC already available.'
            )
        );
    }

    public function validateAudio(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        AudioValidationService $validator
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $tracks = $release
            ->tracks()
            ->whereNotNull('audio_path')
            ->get();

        abort_if(
            $tracks->isEmpty(),
            422,
            'No uploaded audio found.'
        );

        $result = $validator->validateMany(
            $tracks,
            $request->user()
        );

        return back()->with(
            $result['failed'] > 0
                ? 'warning'
                : 'success',
            "Audio validation completed. "
            . "Passed: {$result['passed']}, "
            . "Failed: {$result['failed']}."
        );
    }

    public function initialiseDelivery(
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

        abort_unless(
            in_array(
                $release->status,
                [
                    'approved',
                    'processing',
                ],
                true
            ),
            422,
            'Only approved or processing releases can start delivery.'
        );

        $delivery->initialise(
            $release,
            $request->user()
        );

        return redirect()
            ->route(
                'v2.admin.delivery.index',
                $release
            )
            ->with(
                'success',
                'DSP delivery records initialised.'
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
