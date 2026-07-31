<?php

namespace App\Services\Royalties;

use App\Models\Distribution\Track;
use App\Models\Distribution\TrackSplit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrackSplitService
{
    /**
     * Replace splits for one track and one split type.
     *
     * Draft mode allows total below 100%.
     * Active mode requires total exactly 100%.
     */
    public function replaceSplits(
        Track $track,
        string $splitType,
        array $splits,
        bool $activate = false,
        ?int $userId = null
    ): void {
        $this->validateSplitType($splitType);
        $this->validateSplits($splits, $activate);

        DB::transaction(function () use (
            $track,
            $splitType,
            $splits,
            $activate,
            $userId
        ): void {
            TrackSplit::query()
                ->where('track_id', $track->id)
                ->where('split_type', $splitType)
                ->delete();

            foreach ($splits as $split) {
                TrackSplit::create([
                    'public_id' => (string) Str::ulid(),
                    'track_id' => $track->id,
                    'contributor_id' => $split['contributor_id'],
                    'split_type' => $splitType,
                    'percentage' => $split['percentage'],
                    'is_recoupable' => $split['is_recoupable'] ?? false,
                    'effective_from' => $split['effective_from'] ?? null,
                    'effective_to' => $split['effective_to'] ?? null,
                    'status' => $activate ? 'active' : 'draft',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
        });
    }

    public function totalForTrack(
        Track $track,
        string $splitType,
        ?int $excludeSplitId = null
    ): float {
        $query = TrackSplit::query()
            ->where('track_id', $track->id)
            ->where('split_type', $splitType);

        if ($excludeSplitId !== null) {
            $query->whereKeyNot($excludeSplitId);
        }

        return round((float) $query->sum('percentage'), 2);
    }

    private function validateSplitType(string $splitType): void
    {
        $allowedTypes = [
            'master',
            'publishing',
            'mechanical',
            'performance',
            'youtube',
        ];

        if (! in_array($splitType, $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'split_type' => 'The selected split type is invalid.',
            ]);
        }
    }

    private function validateSplits(array $splits, bool $activate): void
    {
        if ($splits === []) {
            throw ValidationException::withMessages([
                'splits' => 'At least one revenue split is required.',
            ]);
        }

        $contributorIds = [];
        $total = 0.0;

        foreach ($splits as $index => $split) {
            $contributorId = $split['contributor_id'] ?? null;
            $percentage = $split['percentage'] ?? null;

            if (! is_numeric($contributorId)) {
                throw ValidationException::withMessages([
                    "splits.$index.contributor_id" =>
                        'A valid contributor is required.',
                ]);
            }

            if (in_array((int) $contributorId, $contributorIds, true)) {
                throw ValidationException::withMessages([
                    "splits.$index.contributor_id" =>
                        'The same contributor cannot be added twice.',
                ]);
            }

            if (! is_numeric($percentage)) {
                throw ValidationException::withMessages([
                    "splits.$index.percentage" =>
                        'A valid split percentage is required.',
                ]);
            }

            $percentage = round((float) $percentage, 2);

            if ($percentage <= 0 || $percentage > 100) {
                throw ValidationException::withMessages([
                    "splits.$index.percentage" =>
                        'Percentage must be greater than 0 and not exceed 100.',
                ]);
            }

            $contributorIds[] = (int) $contributorId;
            $total += $percentage;
        }

        $total = round($total, 2);

        if ($total > 100) {
            throw ValidationException::withMessages([
                'splits' => "The total split cannot exceed 100%. Current total: {$total}%.",
            ]);
        }

        if ($activate && $total !== 100.0) {
            throw ValidationException::withMessages([
                'splits' => "Active splits must total exactly 100%. Current total: {$total}%.",
            ]);
        }
    }
}
