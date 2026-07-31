<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreLabelRequest;
use App\Http\Requests\Core\UpdateLabelRequest;
use App\Models\Core\Label;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LabelController extends Controller
{
    public function index(): JsonResponse
    {
        $labels = Label::query()
            ->with('parent:id,name')
            ->withCount(['children', 'artists', 'releases'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $labels,
        ]);
    }

    public function store(StoreLabelRequest $request): JsonResponse
    {
        $data = $request->validated();

if ($response = $this->validateRoyaltySplit($data)) {
    return $response;
}

        if (
            $data['label_type'] === 'sub_label'
            && empty($data['parent_label_id'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'A parent label is required for a sub-label.',
            ], 422);
        }

        if ($data['label_type'] === 'label') {
            $data['parent_label_id'] = null;
        }

        $label = DB::transaction(function () use ($data): Label {
            return Label::create([
                ...$data,
                'public_id' => (string) Str::ulid(),
                'slug' => $this->generateUniqueSlug($data['name']),
                'minimum_withdrawal_amount' =>
                    $data['minimum_withdrawal_amount'] ?? 5000,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Label created successfully.',
            'data' => $label->load('parent:id,name'),
        ], 201);
    }

    public function show(Label $label): JsonResponse
    {
        $label->load([
            'parent:id,name',
            'children',
        ])->loadCount([
            'artists',
            'releases',
        ]);

        return response()->json([
            'success' => true,
            'data' => $label,
        ]);
    }

    public function update(
        UpdateLabelRequest $request,
        Label $label
    ): JsonResponse {
        $data = $request->validated();

        $labelType = $data['label_type'] ?? $label->label_type;
        $parentLabelId = array_key_exists('parent_label_id', $data)
            ? $data['parent_label_id']
            : $label->parent_label_id;

        if (
            $labelType === 'sub_label'
            && empty($parentLabelId)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'A parent label is required for a sub-label.',
            ], 422);
        }

        if ($labelType === 'label') {
            $data['parent_label_id'] = null;
        }

        if (
            isset($data['parent_label_id'])
            && (int) $data['parent_label_id'] === (int) $label->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'A label cannot be its own parent.',
            ], 422);
        }

        if (
            isset($data['name'])
            && $data['name'] !== $label->name
        ) {
            $data['slug'] = $this->generateUniqueSlug($data['name']);
        }

        
if ($response = $this->validateRoyaltySplit($data, $label)) {
    return $response;
}

$data['updated_by'] = auth()->id();


        DB::transaction(function () use ($label, $data): void {
            $label->update($data);
        });

        return response()->json([
            'success' => true,
            'message' => 'Label updated successfully.',
            'data' => $label->fresh()->load('parent:id,name'),
        ]);
    }

    public function destroy(Label $label): JsonResponse
    {
        if ($label->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This label has sub-labels and cannot be deleted.',
            ], 422);
        }

        if ($label->releases()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This label has releases and cannot be deleted.',
            ], 422);
        }

        $label->update([
            'updated_by' => auth()->id(),
        ]);

        $label->delete();

        return response()->json([
            'success' => true,
            'message' => 'Label deleted successfully.',
        ]);
    }


private function validateRoyaltySplit(array $data, ?Label $label = null): ?JsonResponse
{
    $royaltyShare = array_key_exists('royalty_share_percentage', $data)
        ? (float) $data['royalty_share_percentage']
        : (float) ($label?->royalty_share_percentage ?? 100);

    $parentCommission = array_key_exists('parent_commission_percentage', $data)
        ? (float) $data['parent_commission_percentage']
        : (float) ($label?->parent_commission_percentage ?? 0);

    if (round($royaltyShare + $parentCommission, 2) !== 100.00) {
        return response()->json([
            'success' => false,
            'message' => 'Royalty share and parent commission must total 100%.',
        ], 422);
    }

    return null;
}




    public function restore(int $label): JsonResponse
    {
        $record = Label::withTrashed()->findOrFail($label);

        if (! $record->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Label is not deleted.',
            ], 422);
        }

        $record->restore();

        $record->update([
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Label restored successfully.',
            'data' => $record->fresh(),
        ]);
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Label::withTrashed()
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
