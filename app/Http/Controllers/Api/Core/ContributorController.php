<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreContributorRequest;
use App\Http\Requests\Core\UpdateContributorRequest;
use App\Models\Distribution\Contributor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContributorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $contributors = Contributor::query()
            ->with([
                'artist:id,stage_name',
                'user:id,name,email',
            ])
            ->when(
                $request->filled('search'),
                function ($query) use ($request): void {
                    $search = $request->string('search')->toString();

                    $query->where(function ($subQuery) use ($search): void {
                        $subQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('legal_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('ipi_number', 'like', "%{$search}%")
                            ->orWhere('isni', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $request->filled('artist_id'),
                fn ($query) => $query->where(
                    'artist_id',
                    $request->integer('artist_id')
                )
            )
            ->when(
                $request->filled('primary_role'),
                fn ($query) => $query->where(
                    'primary_role',
                    $request->string('primary_role')->toString()
                )
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $contributors,
        ]);
    }

    public function store(StoreContributorRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $contributor = DB::transaction(
            fn (): Contributor => Contributor::create($data)
        );

        return response()->json([
            'success' => true,
            'message' => 'Contributor created successfully.',
            'data' => $contributor->load([
                'artist:id,stage_name',
                'user:id,name,email',
            ]),
        ], 201);
    }

    public function show(Contributor $contributor): JsonResponse
    {
        $contributor->load([
            'artist:id,stage_name',
            'user:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'data' => $contributor,
        ]);
    }

    public function update(
        UpdateContributorRequest $request,
        Contributor $contributor
    ): JsonResponse {
        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        DB::transaction(function () use ($contributor, $data): void {
            $contributor->update($data);
        });

        return response()->json([
            'success' => true,
            'message' => 'Contributor updated successfully.',
            'data' => $contributor->fresh()->load([
                'artist:id,stage_name',
                'user:id,name,email',
            ]),
        ]);
    }

    public function destroy(Contributor $contributor): JsonResponse
    {
        $contributor->update([
            'updated_by' => auth()->id(),
        ]);

        $contributor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contributor deleted successfully.',
        ]);
    }

    public function restore(int $contributor): JsonResponse
    {
        $record = Contributor::withTrashed()->findOrFail($contributor);

        if (! $record->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Contributor is not deleted.',
            ], 422);
        }

        $record->restore();

        $record->update([
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contributor restored successfully.',
            'data' => $record->fresh(),
        ]);
    }
}
