<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreArtistRequest;
use App\Http\Requests\Core\UpdateArtistRequest;
use App\Models\Core\Artist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArtistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $artists = Artist::query()
            ->with('label:id,name')
            ->when(
                $request->filled('search'),
                function ($query) use ($request): void {
                    $search = $request->string('search')->toString();

                    $query->where(function ($subQuery) use ($search): void {
                        $subQuery
                            ->where('stage_name', 'like', "%{$search}%")
                            ->orWhere('legal_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $request->filled('label_id'),
                fn ($query) => $query->where(
                    'label_id',
                    $request->integer('label_id')
                )
            )
            ->when(
                $request->filled('account_status'),
                fn ($query) => $query->where(
                    'account_status',
                    $request->string('account_status')->toString()
                )
            )
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $artists,
        ]);
    }

    public function store(StoreArtistRequest $request): JsonResponse
    {
        $data = $request->validated();

        $artist = DB::transaction(function () use ($data): Artist {
            return Artist::create([
                ...$data,
                'public_id' => (string) Str::ulid(),
                'slug' => $this->generateUniqueSlug($data['stage_name']),
                'account_status' => $data['account_status'] ?? 'active',
                'kyc_status' => $data['kyc_status'] ?? 'pending',
                'can_receive_splits' => $data['can_receive_splits'] ?? true,
                'can_create_releases' => $data['can_create_releases'] ?? true,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Artist created successfully.',
            'data' => $artist->load('label:id,name'),
        ], 201);
    }

    public function show(Artist $artist): JsonResponse
    {
        $artist->load([
            'label:id,name',
            'user:id,name,email',
        ])->loadCount([
            'releases',
            'royaltyLedgers',
            'walletTransactions',
        ]);

        return response()->json([
            'success' => true,
            'data' => $artist,
        ]);
    }

    public function update(
        UpdateArtistRequest $request,
        Artist $artist
    ): JsonResponse {
        $data = $request->validated();

        if (
            isset($data['stage_name']) &&
            $data['stage_name'] !== $artist->stage_name
        ) {
            $data['slug'] = $this->generateUniqueSlug($data['stage_name']);
        }

        $data['updated_by'] = auth()->id();

        DB::transaction(function () use ($artist, $data): void {
            $artist->update($data);
        });

        return response()->json([
            'success' => true,
            'message' => 'Artist updated successfully.',
            'data' => $artist->fresh()->load('label:id,name'),
        ]);
    }

    public function destroy(Artist $artist): JsonResponse
    {
        if ($artist->releases()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This artist has releases and cannot be deleted.',
            ], 422);
        }

        if ($artist->royaltyLedgers()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This artist has royalty records and cannot be deleted.',
            ], 422);
        }

        $artist->update([
            'updated_by' => auth()->id(),
        ]);

        $artist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Artist deleted successfully.',
        ]);
    }

    public function restore(int $artist): JsonResponse
    {
        $record = Artist::withTrashed()->findOrFail($artist);

        if (! $record->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Artist is not deleted.',
            ], 422);
        }

        $record->restore();

        $record->update([
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Artist restored successfully.',
            'data' => $record->fresh()->load('label:id,name'),
        ]);
    }

    private function generateUniqueSlug(string $stageName): string
    {
        $baseSlug = Str::slug($stageName);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Artist::withTrashed()
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
