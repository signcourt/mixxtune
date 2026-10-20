<?php

namespace App\Observers;

use App\Models\Core\Label;
use App\Models\User;
use App\Services\V2\ActionOtpGuard;

/*
 * MIXXTUNE_TWO_STEP_ACTIONS_V1
 * Changing an existing label revenue % needs the e-mailed confirmation code
 * (one code covers every label changed in that one save / bulk action).
 * Setting a % for the first time (it was empty) is not gated.
 */
class LabelRateConfirmObserver
{
    public function updating(Label $label): void
    {
        if (!$label->isDirty('revenue_share_percentage')) {
            return;
        }

        $old = $label->getOriginal('revenue_share_percentage');
        $new = $label->getAttribute('revenue_share_percentage');

        if ($old === null || $old === '') {
            return;
        }

        if ($new !== null && $new !== '' && round((float) $old, 2) === round((float) $new, 2)) {
            return;
        }

        $actor = auth()->user();

        app(ActionOtpGuard::class)->require(
            $actor instanceof User ? $actor : null,
            'label.rate',
            'change label revenue %'
        );
    }
}
