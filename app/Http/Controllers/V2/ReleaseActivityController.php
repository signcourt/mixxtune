<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReleaseActivityController extends Controller
{
    public function index(
        Request $request,
        Release $release,
        ReleaseAccessService $access,
        ReleaseAuditService $audit
    ): JsonResponse {
        $access->authorizeView(
            $request->user(),
            $release
        );

        return response()->json([
            'release' => [
                'id' =>
                    $release->id,

                'title' =>
                    $release->title,

                'status' =>
                    $release->status,
            ],

            'timeline' =>
                $audit->timeline(
                    $release,
                    (int) $request->input(
                        'limit',
                        200
                    )
                ),
        ]);
    }
}
