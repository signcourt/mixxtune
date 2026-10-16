<?php

namespace App\Services\V2;

use App\Models\Core\Label;
use Illuminate\Support\Collection;

class LabelIdentityService
{
    /**
     * Logical identity key.
     *
     * Examples:
     * 31 Beats
     * 31-Beats
     * 31_Beats
     * 31beats
     *
     * => 31beats
     */
    public function normalize(?string $name): string
    {
        $name = mb_strtolower(
            trim((string) $name)
        );

        return (string) preg_replace(
            '/[^a-z0-9]+/i',
            '',
            $name
        );
    }

    /**
     * Find every non-deleted physical label belonging
     * to the same logical label identity.
     */
    public function matches(
        string $name,
        ?int $exceptLabelId = null
    ): Collection {
        $key = $this->normalize($name);

        if ($key === '') {
            return collect();
        }

        return Label::query()
            ->whereNull('deleted_at')
            ->get([
                'id',
                'name',
                'user_id',
                'parent_label_id',
                'status',
            ])
            ->filter(function (Label $label) use (
                $key,
                $exceptLabelId
            ) {
                if (
                    $exceptLabelId !== null
                    && (int) $label->id === $exceptLabelId
                ) {
                    return false;
                }

                return $this->normalize(
                    $label->name
                ) === $key;
            })
            ->values();
    }

    public function exists(
        string $name,
        ?int $exceptLabelId = null
    ): bool {
        return $this
            ->matches(
                $name,
                $exceptLabelId
            )
            ->isNotEmpty();
    }

    /**
     * Deterministic representative:
     * active first, then lowest physical ID.
     *
     * No records are merged or deleted.
     */
    public function representative(
        string $name,
        ?int $exceptLabelId = null
    ): ?Label {
        $matches = $this->matches(
            $name,
            $exceptLabelId
        );

        return $matches
            ->sortBy(function (Label $label) {
                return [
                    $label->status === 'active'
                        ? 0
                        : 1,
                    (int) $label->id,
                ];
            })
            ->first();
    }

    public function ids(string $name): Collection
    {
        return $this
            ->matches($name)
            ->pluck('id')
            ->map(
                fn ($id) => (int) $id
            )
            ->values();
    }
}
