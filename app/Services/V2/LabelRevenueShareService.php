<?php

namespace App\Services\V2;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\LabelRevenueShare;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LabelRevenueShareService
{
    public function saveForLabel(
        Label $masterLabel,
        Label $childLabel,
        float $percent,
        bool $showShare,
        User $actor
    ): LabelRevenueShare {
        $this->assertDirectChildLabel(
            $masterLabel,
            $childLabel
        );

        return $this->save(
            $masterLabel,
            'label',
            $childLabel->id,
            $percent,
            $showShare,
            $actor
        );
    }

    public function saveForArtist(
        Label $masterLabel,
        Artist $artist,
        float $percent,
        bool $showShare,
        User $actor
    ): LabelRevenueShare {
        $this->assertDirectArtist(
            $masterLabel,
            $artist
        );

        return $this->save(
            $masterLabel,
            'artist',
            $artist->id,
            $percent,
            $showShare,
            $actor
        );
    }

    public function resolve(
        Label $masterLabel,
        string $beneficiaryType,
        int $beneficiaryId
    ): ?LabelRevenueShare {
        return LabelRevenueShare::query()
            ->where(
                'master_label_id',
                $masterLabel->id
            )
            ->where(
                'beneficiary_type',
                $beneficiaryType
            )
            ->where(
                'beneficiary_id',
                $beneficiaryId
            )
            ->where('is_active', true)
            ->where(function ($query) {
                $query
                    ->whereNull(
                        'effective_from'
                    )
                    ->orWhere(
                        'effective_from',
                        '<=',
                        now()->toDateString()
                    );
            })
            ->where(function ($query) {
                $query
                    ->whereNull(
                        'effective_to'
                    )
                    ->orWhere(
                        'effective_to',
                        '>=',
                        now()->toDateString()
                    );
            })
            ->first();
    }

    private function save(
        Label $masterLabel,
        string $type,
        int $id,
        float $percent,
        bool $showShare,
        User $actor
    ): LabelRevenueShare {
        if (
            $percent < 0
            || $percent > 100
        ) {
            throw ValidationException::withMessages([
                'revenue_share_percent' =>
                    'Revenue share must be between 0 and 100.',
            ]);
        }

        return LabelRevenueShare::query()
            ->updateOrCreate(
                [
                    'master_label_id' =>
                        $masterLabel->id,

                    'beneficiary_type' =>
                        $type,

                    'beneficiary_id' =>
                        $id,
                ],
                [
                    'revenue_share_percent' =>
                        $percent,

                    'show_revenue_share' =>
                        $showShare,

                    'is_active' =>
                        true,

                    'effective_from' =>
                        now()->toDateString(),

                    'effective_to' =>
                        null,

                    'updated_by' =>
                        $actor->id,

                    'created_by' =>
                        $actor->id,
                ]
            );
    }

    private function assertDirectChildLabel(
        Label $master,
        Label $child
    ): void {
        if (
            (int) $child->parent_label_id
            !== (int) $master->id
        ) {
            throw ValidationException::withMessages([
                'label' =>
                    'This label is not a direct child of the master label.',
            ]);
        }

        /*
         * Strict two-tier hierarchy.
         * A child label can never act as another master.
         */
        if ($master->parent_label_id !== null) {
            throw ValidationException::withMessages([
                'master_label' =>
                    'Sub-labels cannot create or manage child accounts.',
            ]);
        }
    }

    private function assertDirectArtist(
        Label $master,
        Artist $artist
    ): void {
        if (
            (int) $artist->label_id
            !== (int) $master->id
        ) {
            throw ValidationException::withMessages([
                'artist' =>
                    'This artist is not directly assigned to the master label.',
            ]);
        }

        if ($master->parent_label_id !== null) {
            throw ValidationException::withMessages([
                'master_label' =>
                    'Sub-labels cannot create or manage child accounts.',
            ]);
        }
    }
}
