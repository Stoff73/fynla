<?php

declare(strict_types=1);

namespace App\Http\Traits;

use App\Models\User;
use App\Services\Tiers\TeaserGate;

/**
 * Shared gate guard for full-Estate sub-routes.
 *
 * Both EstateController and IHTController use this trait so the 403 message
 * and the gate check live in exactly one place (SP2 PR7 §10.2).
 * The consuming controller must have $this->teaserGate (TeaserGate) available
 * — inject it via constructor as a private readonly property.
 */
trait GatesEstateAccess
{
    /**
     * Abort 403 if the user is not on a full-Estate tier.
     * Single canonical guard for all full-engine Estate sub-routes (spec §10.2).
     */
    protected function requireFullEstate(User $user): void
    {
        /** @var TeaserGate $teaserGate */
        $teaserGate = $this->teaserGate;

        if (! $teaserGate->isFull($user, 'estate')) {
            abort(403, 'Full Estate Planning requires Tier 2 or above.');
        }
    }
}
