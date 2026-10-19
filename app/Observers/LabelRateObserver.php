<?php

namespace App\Observers;

use App\Models\Core\Label;
use App\Services\V2\LabelRateHistoryService;

/* MIXXTUNE_LABEL_RATE_HISTORY_V1: log every saved change of a label's revenue %. */
class LabelRateObserver
{
    public function updated(Label $label): void
    {
        if (LabelRateHistoryService::isMuted() || !$label->wasChanged('revenue_share_percentage')) {
            return;
        }

        app(LabelRateHistoryService::class)->record(
            $label,
            $label->getOriginal('revenue_share_percentage'),
            $label->revenue_share_percentage
        );
    }

    public function created(Label $label): void
    {
        if (LabelRateHistoryService::isMuted() || $label->revenue_share_percentage === null) {
            return;
        }

        app(LabelRateHistoryService::class)->record($label, null, $label->revenue_share_percentage, null, 'Label created');
    }
}
