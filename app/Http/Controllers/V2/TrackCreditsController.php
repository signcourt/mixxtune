<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Contributor;
use App\Models\Distribution\Track;
use App\Models\Distribution\TrackContributor;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseAuditService;
use App\Services\V2\TrackCreditsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackCreditsController extends Controller
{
    public function show(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        TrackCreditsService $credits
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'contributors.view'
        );

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

        return response()->json([
            'track' => $track,
            ...$credits->trackCredits(
                $track
            ),
        ]);
    }

    public function createContributor(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        TrackCreditsService $credits
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'contributors.manage'
        );

        $track->loadMissing('release');

        $access->authorizeUpdate(
            $request->user(),
            $track->release
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'legal_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'ipi_number' => [
                'nullable',
                'string',
                'max:50',
            ],

            'isni' => [
                'nullable',
                'string',
                'max:50',
            ],

            'society' => [
                'nullable',
                'string',
                'max:100',
            ],

            'country_code' => [
                'nullable',
                'string',
                'size:2',
            ],

            'platform_ids' => [
                'nullable',
                'array',
            ],
        ]);

        $contributor =
            $credits->createContributor(
                $validated,
                $request->user()
            );

        return response()->json([
            'message' =>
                'Contributor created.',

            'contributor' =>
                $contributor,
        ]);
    }

    public function attachContributor(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        TrackCreditsService $credits,
        ReleaseAuditService $audit
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'contributors.manage'
        );

        $track->loadMissing('release');

        $access->authorizeUpdate(
            $request->user(),
            $track->release
        );

        $validated = $request->validate([
            'contributor_id' => [
                'required',
                'integer',
                'exists:contributors,id',
            ],

            'role' => [
                'required',
                'string',
                'max:100',
            ],

            'credited_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_primary' => [
                'nullable',
                'boolean',
            ],

            'is_featured' => [
                'nullable',
                'boolean',
            ],

            'display_order' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'metadata' => [
                'nullable',
                'array',
            ],

            // Backward-compatible request fields.
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $contributor =
            Contributor::query()->findOrFail(
                $validated[
                    'contributor_id'
                ]
            );

        $credit =
            $credits->attachContributor(
                $track,
                $contributor,
                $validated['role'],
                $request->user(),
                $validated
            );

        $audit->log(
            $track->release,
            'track.contributor_added',
            'Contributor added',
            "{$contributor->name} added as {$credit->role}.",
            [
                'category' =>
                    'credits',

                'track_id' =>
                    $track->id,

                'user_id' =>
                    $request->user()->id,

                'new_values' => [
                    'contributor_id' =>
                        $contributor->id,

                    'role' =>
                        $credit->role,
                ],
            ]
        );

        return response()->json([
            'message' =>
                'Contributor attached to track.',

            'credit' =>
                $credit->load(
                    'contributor'
                ),
        ]);
    }

    public function updateCredit(
        Request $request,
        TrackContributor $credit,
        PermissionService $permissions,
        ReleaseAccessService $access,
        TrackCreditsService $credits
    ): JsonResponse {
        $credit->loadMissing(
            'track.release'
        );

        $permissions->authorize(
            $request->user(),
            'contributors.manage'
        );

        $access->authorizeUpdate(
            $request->user(),
            $credit->track->release
        );

        $validated = $request->validate([
            'role' => [
                'nullable',
                'string',
                'max:100',
            ],

            'credited_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_primary' => [
                'nullable',
                'boolean',
            ],

            'is_featured' => [
                'nullable',
                'boolean',
            ],

            'display_order' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'metadata' => [
                'nullable',
                'array',
            ],

            // Backward-compatible request fields.
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $credit =
            $credits
                ->updateContributorCredit(
                    $credit,
                    $validated,
                    $request->user()
                );

        return response()->json([
            'message' =>
                'Contributor credit updated.',

            'credit' =>
                $credit,
        ]);
    }

    public function deleteCredit(
        Request $request,
        TrackContributor $credit,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): JsonResponse {
        $credit->loadMissing(
            'track.release'
        );

        $permissions->authorize(
            $request->user(),
            'contributors.manage'
        );

        $access->authorizeUpdate(
            $request->user(),
            $credit->track->release
        );

        $credit->update([
            'updated_by' =>
                $request->user()->id,
        ]);

        $credit->delete();

        return response()->json([
            'message' =>
                'Contributor credit deleted.',
        ]);
    }

    public function replaceSplits(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        TrackCreditsService $credits,
        ReleaseAuditService $audit
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'splits.manage'
        );

        $track->loadMissing('release');

        $access->authorizeUpdate(
            $request->user(),
            $track->release
        );

        $validated = $request->validate([
            'split_type' => [
                'required',
                'string',
                'max:50',
            ],

            'splits' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],

            'splits.*.contributor_id' => [
                'nullable',
                'integer',
                'exists:contributors,id',
            ],

            'splits.*.recipient_name' => [
                'required',
                'string',
                'max:255',
            ],

            'splits.*.recipient_email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'splits.*.percentage' => [
                'required',
                'numeric',
                'gt:0',
                'lte:100',
            ],

            'splits.*.notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $splits =
            $credits->replaceSplits(
                $track,
                $validated['split_type'],
                $validated['splits'],
                $request->user()
            );

        $audit->log(
            $track->release,
            'track.splits_updated',
            'Royalty splits updated',
            ucfirst(
                $validated['split_type']
            )
                . ' splits updated for '
                . $track->title
                . '.',
            [
                'category' =>
                    'splits',

                'track_id' =>
                    $track->id,

                'user_id' =>
                    $request->user()->id,

                'new_values' => [
                    'split_type' =>
                        $validated[
                            'split_type'
                        ],

                    'splits' =>
                        $validated['splits'],
                ],
            ]
        );

        return response()->json([
            'message' =>
                'Track splits updated.',

            'splits' =>
                $splits,

            'validation' =>
                $credits
                    ->validateTrackSplits(
                        $track
                    ),
        ]);
    }
}
