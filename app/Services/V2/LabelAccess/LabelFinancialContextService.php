<?php

namespace App\Services\V2\LabelAccess;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LabelFinancialContextService
{
    public function __construct(
        private readonly LabelTeamAccessService $teamAccess
    ) {
    }

    /**
     * Resolve the financial owner for Label Panel operations.
     *
     * Label owner:
     *     Uses own financial identity.
     *
     * Label team user:
     *     Uses the effective label owner's financial identity.
     *
     * This service does NOT grant permission.
     * Permission checks remain separate.
     */
    public function owner(User $actor): User
    {
        $label = $this->teamAccess->effectiveLabel($actor);

        if (!$label) {
            return $actor;
        }

        $ownerId = (int) $label->user_id;

        if ($ownerId <= 0) {
            throw new HttpException(
                403,
                'The label financial owner could not be resolved.'
            );
        }

        if ((int) $actor->id === $ownerId) {
            return $actor;
        }

        $owner = User::query()->find($ownerId);

        if (!$owner) {
            throw new HttpException(
                403,
                'The label financial owner could not be resolved.'
            );
        }

        return $owner;
    }

    /**
     * True when actor is operating through an active
     * Label Team membership rather than as label owner.
     */
    public function isTeamActor(User $actor): bool
    {
        $membership = $this->teamAccess->membership($actor);

        return $membership !== null
            && $membership->isActive();
    }
}
