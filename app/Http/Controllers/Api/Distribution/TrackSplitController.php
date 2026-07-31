<?php

namespace App\Http\Controllers\Api\Distribution;

use App\Http\Controllers\Controller;
use App\Http\Requests\Distribution\SaveTrackSplitRequest;
use App\Models\Distribution\Track;
use App\Services\Royalties\TrackSplitService;
use Illuminate\Http\JsonResponse;

class TrackSplitController extends Controller
{
    public function __construct(
        protected TrackSplitService $service
    ) {
    }

    public function store(
        SaveTrackSplitRequest $request,
        Track $track
    ): JsonResponse {

        $this->service->replaceSplits(
            $track,
            $request->input('split_type'),
            $request->input('splits'),
            $request->boolean('activate'),
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Track splits saved successfully.',
        ]);
    }
}
