<?php

declare(strict_types=1);

namespace App\Listeners\Property;

use App\Events\Property\PropertyCreated;
use App\Events\Property\PropertyDeleted;
use App\Events\Property\PropertyRestored;
use App\Events\Property\PropertyUpdated;
use App\Models\User;
use App\Services\Property\PropertyService;

/**
 * Cross-record recalc: when a property changes, recompute
 * `users.annual_rental_income` for EVERY user that record reaches.
 *
 * `users.annual_rental_income` is a denormalised cache of a figure whose one
 * home is PropertyService::annualRentalTaxPosition() (W-0175). Two paths wrote
 * it and both wrote only the acting user, so a joint buy-to-let's rent reached
 * the owner who recorded the property and stopped: the co-owner's column stayed
 * at 0.00 and every surface reading it — estate, retirement, protection,
 * coordination, the plans, Fyn, `/m` and native — credited her half of the rent
 * to nobody (W-0173).
 *
 * **Why a listener and not an Eloquent observer.** The first version of this was
 * an observer on the model, which put `App\Models\Property` inside
 * `app/Observers/` and turned the property store-boundary suite red. The
 * boundary was right and the observer was the wrong mechanism: `PropertyStore`
 * is the canonical write path, it already emits domain events, and
 * `RecalculatePropertyOutstandingMortgage` is the established shape for exactly
 * this job — react to a store event, recompute a derived value elsewhere. This
 * listener never names the model; it reads what the event carries.
 *
 * One-way recalc: Property → User. Nothing here writes a Property, so there is
 * no feedback loop.
 *
 * The arithmetic is not repeated. The figure written is the rental PROFIT at the
 * user's ownership share — rent less allowable letting expenses — the same
 * figure the income page and the tax computation use, and the correct base for
 * total income (ITA 2007 s23 Step 1 over ITTOIA 2005 Part 3). A third party's
 * share falls out uncredited because `annualRentalTaxPosition` resolves the
 * requesting user's side of the split and no other.
 */
class SyncOwnerRentalIncome
{
    public function __construct(
        private readonly PropertyService $propertyService,
    ) {}

    public function handle(PropertyCreated|PropertyUpdated|PropertyDeleted|PropertyRestored $event): void
    {
        foreach ($this->affectedUserIds($event) as $userId) {
            $this->sync($userId);
        }
    }

    /**
     * Every user the write touched — both sides of the record, and on an update
     * both sides as they were BEFORE it too, so a co-owner who has just been
     * removed stops being credited with rent they no longer receive.
     *
     * @return list<int>
     */
    private function affectedUserIds(PropertyCreated|PropertyUpdated|PropertyDeleted|PropertyRestored $event): array
    {
        if ($event instanceof PropertyDeleted) {
            // The row is gone; the event carries who it reached.
            return array_values(array_unique(array_filter([$event->user->id, $event->jointOwnerId])));
        }

        $ids = [$event->entity->user_id, $event->entity->joint_owner_id];

        if ($event instanceof PropertyUpdated) {
            $ids[] = $event->previous['user_id'] ?? null;
            $ids[] = $event->previous['joint_owner_id'] ?? null;
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($id): ?int => $id === null ? null : (int) $id,
            $ids,
        ))));
    }

    private function sync(int $userId): void
    {
        $user = User::find($userId);

        if (! $user) {
            return;
        }

        $total = round($this->propertyService->annualRentalTaxPosition($user)['total'], 2);

        if (abs((float) ($user->annual_rental_income ?? 0) - $total) < 0.005) {
            return;
        }

        $user->update(['annual_rental_income' => $total]);
    }
}
