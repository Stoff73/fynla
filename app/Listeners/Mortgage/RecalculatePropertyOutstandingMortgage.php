<?php

declare(strict_types=1);

namespace App\Listeners\Mortgage;

use App\Events\Mortgage\MortgageCreated;
use App\Events\Mortgage\MortgageDeleted;
use App\Events\Mortgage\MortgageRestored;
use App\Events\Mortgage\MortgageUpdated;
use App\Services\Stores\PropertyStore;

/**
 * Cross-store recalc: when any Mortgage event fires for a mortgage linked
 * to a property, recompute properties.outstanding_mortgage from the canonical
 * mortgages.outstanding_balance sum.
 *
 * One-way recalc: Mortgage → Property. PropertyStore writes do NOT trigger
 * MortgageStore writes. Loop prevention by design — the listener funnels
 * through PropertyStore::recalculateDerivedForPropertyId which writes directly
 * to the Property row via Eloquent (no Property events).
 */
class RecalculatePropertyOutstandingMortgage
{
    public function __construct(
        private readonly PropertyStore $propertyStore,
    ) {}

    public function handle(MortgageCreated|MortgageUpdated|MortgageDeleted|MortgageRestored $event): void
    {
        if ($event->entity->property_id === null) {
            return;
        }

        $this->propertyStore->recalculateDerivedForPropertyId((int) $event->entity->property_id);
    }
}
