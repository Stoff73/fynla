<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Property;
use App\Models\User;
use App\Services\TaxConfigService;
use App\Support\SharedOwnership;
use App\Traits\CalculatesOwnershipShare;

/**
 * W-0368 — an undivided share in land co-owned with a non-spouse is worth less than
 * the arithmetic fraction of the whole.
 *
 * A half share of a house is not half a house. The buyer of a part share cannot sell,
 * occupy or mortgage it freely, so for Inheritance Tax the share is valued with a
 * discount for that restricted marketability — **IHTM15071, SVM113040**, HMRC's
 * typical figure being 10%.
 *
 * **NOT between spouses.** IHTA 1984 **s161** related property rules value a couple's
 * shares together, which removes the very restriction the discount pays for. So the
 * discount turns entirely on whether the co-owner is a spouse, and that is the only
 * question this class asks beyond "is it shared at all".
 *
 * **The ~15% case is unreachable and that is deliberate.** The higher discount applies
 * where the co-owner is in OCCUPATION and not a spouse. Nothing on `properties`
 * records who lives there — the ownership columns are `user_id`, `joint_owner_id`,
 * `joint_owner_name`, `household_id`, `ownership_type`, `joint_ownership_type`,
 * `ownership_percentage`, and none of them is occupation. Inferring it would be
 * inventing a fact about a person's living arrangements from a percentage. Applying
 * 10% throughout discounts LESS, so it overstates tax rather than understating it —
 * the conservative direction, and the same direction the defect erred in before this
 * class existed. **Do not "fix" this by guessing occupation from `property_type`.**
 *
 * **Inheritance Tax only.** A user's share of a property for NET WORTH is genuinely
 * the arithmetic fraction — they own what they own. The discount is a valuation rule
 * for a taxable transfer, so it belongs to the Inheritance Tax path
 * (`EstateAssetAggregatorService::gatherUserAssets`, which the cross-module aggregator
 * already names as that path) and must never reach `calculateUserShare` itself, which
 * savings, investments, chattels and the net-worth surfaces all read.
 */
class UndividedShareDiscount
{
    use CalculatesOwnershipShare;

    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {}

    /**
     * The discount rate as a fraction, from configuration (Rule 2).
     */
    public function rate(): float
    {
        return (float) ($this->taxConfig->getInheritanceTax()['undivided_share_discount_percent'] ?? 0.0);
    }

    /**
     * Does an undivided-share discount apply to this user's interest in this property?
     *
     * Two conditions, both necessary: the property is genuinely co-owned, and the
     * co-owner is not a spouse (s161).
     */
    public function applies(Property $property, User $user): bool
    {
        if (! SharedOwnership::isShared($property->ownership_type)) {
            return false;
        }

        $coOwnerId = $this->coOwnerId($property, $user->id);

        if ($coOwnerId === null) {
            // Shared by type but with nobody identified — a `joint_owner_name` with no
            // account, typically. The share is still restricted, and the co-owner is by
            // definition not a linked spouse, so the discount applies.
            return true;
        }

        return $coOwnerId !== $user->liveSpouseId();
    }

    /**
     * This user's share of the property as Inheritance Tax values it.
     *
     * The undiscounted share where the discount does not apply, so callers can use
     * this unconditionally rather than branching at every site.
     */
    public function shareValue(Property $property, User $user): float
    {
        $share = $this->calculateUserShare($property, $user->id);

        if (! $this->applies($property, $user)) {
            return $share;
        }

        return $share * (1 - $this->rate());
    }

    /**
     * The amount taken off, for surfaces that show the working rather than the total.
     */
    public function discountAmount(Property $property, User $user): float
    {
        return $this->calculateUserShare($property, $user->id) - $this->shareValue($property, $user);
    }

    /**
     * Every property this user holds a share of, valued as Inheritance Tax values it.
     *
     * **This exists so the projected column cannot drift from the current one.**
     * F-0026 §1 records those two diverging once already, and the projection reads
     * `CrossModuleAssetAggregator::calculatePropertyTotal()`, which is shared with
     * net worth and the Letter to Spouse and must therefore stay undiscounted. This
     * is the Inheritance Tax equivalent of that total, and the projection reads it
     * instead.
     */
    public function propertyTotal(User $user, iterable $properties): float
    {
        $total = 0.0;

        foreach ($properties as $property) {
            $total += $this->shareValue($property, $user);
        }

        return $total;
    }

    /**
     * The other party to a shared property, or null where none is identified.
     */
    private function coOwnerId(Property $property, int $userId): ?int
    {
        if ((int) $property->user_id === $userId) {
            return $property->joint_owner_id === null ? null : (int) $property->joint_owner_id;
        }

        return (int) $property->user_id;
    }
}
