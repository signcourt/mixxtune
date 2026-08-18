<?php

namespace App\Services\V2;

use App\Models\Core\Label;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LabelHierarchyService
{
    /**
     * Return label + every descendant recursively.
     *
     * Example:
     *
     * Sanatan
     *   -> X
     *      -> X1
     *         -> X1A
     *   -> Y
     *
     * descendants(Sanatan, true)
     * = Sanatan, X, X1, X1A, Y
     */
    public function descendantIds(
        int $labelId,
        bool $includeSelf = true
    ): Collection {
        $result = collect();
        $visited = collect();

        $frontier = collect([$labelId]);

        if ($includeSelf) {
            $result->push($labelId);
        }

        while ($frontier->isNotEmpty()) {
            $parentIds = $frontier
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            $parentIds = $parentIds
                ->reject(
                    fn ($id) =>
                        $visited->contains($id)
                )
                ->values();

            if ($parentIds->isEmpty()) {
                break;
            }

            $visited = $visited
                ->merge($parentIds)
                ->unique()
                ->values();

            $children = DB::table('labels')
                ->whereIn(
                    'parent_label_id',
                    $parentIds->all()
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            $newChildren = $children
                ->reject(
                    fn ($id) =>
                        $visited->contains($id)
                )
                ->values();

            $result = $result
                ->merge($newChildren)
                ->unique()
                ->values();

            $frontier = $newChildren;
        }

        return $result
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Return label + complete parent chain.
     *
     * X1A -> X1 -> X -> Sanatan
     */
    public function ancestorIds(
        int $labelId,
        bool $includeSelf = true
    ): Collection {
        $result = collect();
        $visited = collect();

        $currentId = $labelId;

        if ($includeSelf) {
            $result->push($currentId);
        }

        while ($currentId > 0) {
            if ($visited->contains($currentId)) {
                break;
            }

            $visited->push($currentId);

            $parentId = DB::table('labels')
                ->where('id', $currentId)
                ->whereNull('deleted_at')
                ->value('parent_label_id');

            if (!$parentId) {
                break;
            }

            $parentId = (int) $parentId;

            if ($visited->contains($parentId)) {
                break;
            }

            $result->push($parentId);

            $currentId = $parentId;
        }

        return $result
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Resolve the root/master label for any node.
     */
    public function rootLabelId(
        int $labelId
    ): ?int {
        $chain = $this->ancestorIds(
            $labelId,
            true
        );

        if ($chain->isEmpty()) {
            return null;
        }

        return (int) $chain->last();
    }

    public function rootLabel(
        int $labelId
    ): ?Label {
        $rootId = $this->rootLabelId(
            $labelId
        );

        if (!$rootId) {
            return null;
        }

        return Label::query()
            ->whereKey($rootId)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Resolve the master login user from any
     * catalogue level.
     */
    public function masterUserId(
        int $labelId
    ): ?int {
        $root = $this->rootLabel(
            $labelId
        );

        if (!$root || !$root->user_id) {
            return null;
        }

        return (int) $root->user_id;
    }

    /**
     * Is $possibleAncestorId anywhere above
     * $labelId in the tree?
     */
    public function isAncestorOf(
        int $possibleAncestorId,
        int $labelId
    ): bool {
        if ($possibleAncestorId === $labelId) {
            return false;
        }

        return $this
            ->ancestorIds($labelId, false)
            ->contains($possibleAncestorId);
    }

    /**
     * Strict two-tier hierarchy:
     *
     * Root/Master
     *   -> direct Child Label
     *
     * A child label can never become a parent.
     */
    public function assertRootParent(
        int $parentLabelId
    ): void {
        $parent = DB::table('labels')
            ->where('id', $parentLabelId)
            ->whereNull('deleted_at')
            ->first([
                'id',
                'parent_label_id',
            ]);

        if (!$parent) {
            throw ValidationException::withMessages([
                'parent_label_id' => [
                    'The selected master label does not exist.',
                ],
            ]);
        }

        if ($parent->parent_label_id !== null) {
            throw ValidationException::withMessages([
                'parent_label_id' => [
                    'A child label cannot contain another child label. Only a master label can create direct children.',
                ],
            ]);
        }
    }

    public function isDirectChildOf(
        int $masterLabelId,
        int $childLabelId
    ): bool {
        return DB::table('labels')
            ->where('id', $childLabelId)
            ->whereNull('deleted_at')
            ->where(
                'parent_label_id',
                $masterLabelId
            )
            ->exists();
    }

    /**
     * Prevent self-parenting and circular trees.
     */
    public function assertValidParent(
        int $labelId,
        ?int $parentLabelId
    ): void {
        if ($parentLabelId === null) {
            return;
        }

        if ($labelId === $parentLabelId) {
            throw ValidationException::withMessages([
                'parent_label_id' => [
                    'A level cannot be its own parent.',
                ],
            ]);
        }

        if (
            $this->isAncestorOf(
                $labelId,
                $parentLabelId
            )
        ) {
            throw ValidationException::withMessages([
                'parent_label_id' => [
                    'This parent would create a circular label hierarchy.',
                ],
            ]);
        }
    }

    /**
     * Labels visible from a node:
     *
     * own label + unlimited descendants.
     */
    public function visibleLabelIds(
        int $labelId
    ): Collection {
        return $this->descendantIds(
            $labelId,
            true
        );
    }

    /**
     * Release IDs inside a complete subtree.
     */
    public function releaseIds(
        int $labelId
    ): Collection {
        $labelIds =
            $this->visibleLabelIds($labelId);

        if ($labelIds->isEmpty()) {
            return collect();
        }

        return DB::table('releases')
            ->whereIn(
                'label_id',
                $labelIds->all()
            )
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Track IDs inside a complete subtree.
     *
     * tracks do not contain label_id.
     * Relationship is:
     *
     * Track -> Release -> Label
     */
    public function trackIds(
        int $labelId
    ): Collection {
        $labelIds =
            $this->visibleLabelIds($labelId);

        if ($labelIds->isEmpty()) {
            return collect();
        }

        return DB::table('tracks')
            ->join(
                'releases',
                'releases.id',
                '=',
                'tracks.release_id'
            )
            ->whereIn(
                'releases.label_id',
                $labelIds->all()
            )
            ->whereNull(
                'releases.deleted_at'
            )
            ->whereNull(
                'tracks.deleted_at'
            )
            ->pluck('tracks.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Artist IDs inside a complete subtree.
     */
    public function artistIds(
        int $labelId
    ): Collection {
        $labelIds =
            $this->visibleLabelIds($labelId);

        if ($labelIds->isEmpty()) {
            return collect();
        }

        return DB::table('artists')
            ->whereIn(
                'label_id',
                $labelIds->all()
            )
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
