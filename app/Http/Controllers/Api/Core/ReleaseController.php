<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreReleaseRequest;
use App\Http\Requests\Core\UpdateReleaseRequest;
use App\Models\Distribution\Release;
use App\Services\Core\ReleaseService;
use Illuminate\Http\Request;

class ReleaseController extends Controller
{
    public function __construct(
        protected ReleaseService $releaseService
    ) {
    }

    public function index(Request $request)
    {
        return response()->json(
            $this->releaseService->paginate($request->all())
        );
    }

    public function store(StoreReleaseRequest $request)
    {
        return response()->json(
            $this->releaseService->create($request->validated()),
            201
        );
    }

    public function show(Release $release)
    {
        return response()->json($release);
    }

    public function update(UpdateReleaseRequest $request, Release $release)
    {
        return response()->json(
            $this->releaseService->update($release, $request->validated())
        );
    }

    public function destroy(Release $release)
    {
        $this->releaseService->delete($release);

        return response()->json([
            'message' => 'Release deleted successfully.',
        ]);
    }
}
