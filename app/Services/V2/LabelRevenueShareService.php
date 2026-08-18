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
        $this->assertDescendantLabel(
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
        $this->assertDescendantArtist(
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

    private function assertDescendantLabel(
        Label $master,
        Label $child
    ): void {
        /*
         * Revenue contracts always originate from
         * the ROOT financial owner.
         */
        if ($master->parent_label_id !== null) {
            throw ValidationException::withMessages([
                'master_label' =>
                    'Revenue distribution can only originate from the root master label.',
            ]);
        }

        if (
            (int) $master->id
            === (int) $child->id
        ) {
            throw ValidationException::withMessages([
                'label' =>
                    'The master label cannot be its own beneficiary.',
            ]);
        }

        /*
         * STRICT TWO-TIER:
         * beneficiary label must be a DIRECT child.
         */
        if (
            (int) $child->parent_label_id
            !== (int) $master->id
        ) {
            throw ValidationException::withMessages([
                'label' =>
                    'Revenue share can only be assigned to a direct child label of the selected master.',
            ]);
        }
    }

    private function assertDescendantArtist(
        Label $master,
        Artist $artist
    ): void {
        if ($master->parent_label_id !== null) {
            throw ValidationException::withMessages([
                'master_label' =>
                    'Revenue distribution can only originate from the root master label.',
            ]);
        }

        if (!$artist->label_id) {
            throw ValidationException::withMessages([
                'artist' =>
                    'This artist is not assigned to a catalogue level.',
            ]);
        }

        /*
         * STRICT TWO-TIER:
         *
         * Artist may belong directly to the master
         * catalogue only. It cannot sit below a
         * child label for financial splitting.
         */
        if (
            (int) $artist->label_id
            !== (int) $master->id
        ) {
            throw ValidationException::withMessages([
                'artist' =>
                    'Revenue share can only be assigned to an artist directly attached to the selected master label.',
            ]);
        }
    }

}
