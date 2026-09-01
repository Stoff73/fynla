<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Property\PropertyService;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PropertyStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Queue;

/**
 * W-0173 — a jointly-owned buy-to-let's rental income reached the owner who
 * recorded the property and stopped. The co-owner's `users.annual_rental_income`
 * stayed at 0.00, so her half of the rent was credited to nobody and every
 * surface reading that column understated her income.
 *
 * The share was never in dispute — the recorder's own figure was correct. What
 * was missing was the OTHER side of the record. These tests hold both sides of a
 * shared property to the same computation, and hold a third party's share to
 * nobody at all.
 *
 * Everything here goes through PropertyStore, the canonical write path, because
 * that is where the recalc hangs: the store emits the domain event and
 * SyncOwnerRentalIncome consumes it. A factory create would bypass both and
 * prove nothing about the path a user actually takes.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);

    // The rental figure is under test; the risk-recalculation job it triggers is not.
    Queue::fake();

    $this->owner = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'annual_rental_income' => 0,
    ]);
    $this->coOwner = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'annual_rental_income' => 0,
    ]);

    $this->store = app(PropertyStore::class);
    $this->propertyService = app(PropertyService::class);
});

/**
 * £1,800/month let, half owned. Allowable letting expenses £420/month
 * (buildings insurance £35 + service charge £285 + maintenance reserve £100,
 * the reserve deductible since W-0178), so the property's annual profit is
 * (1800 - 420) x 12 = £16,560 and each owner's half is £8,280.
 */
function jointBuyToLetPayload(?User $coOwner): array
{
    return [
        'property_type' => 'buy_to_let',
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_id' => $coOwner?->id,
        'current_value' => 400_000,
        'monthly_rental_income' => 1_800,
        'monthly_building_insurance' => 35,
        'monthly_service_charge' => 285,
        'monthly_maintenance_reserve' => 100,
    ];
}

it('credits a joint buy-to-let to both owners, not just the one who recorded it', function () {
    $this->store->create(jointBuyToLetPayload($this->coOwner), $this->owner, IngestSource::FORM);

    expect((float) $this->owner->fresh()->annual_rental_income)->toBe(8280.0)
        ->and((float) $this->coOwner->fresh()->annual_rental_income)->toBe(8280.0);
});

it('splits the whole of a joint property between the two owners and loses nothing', function () {
    $this->store->create(jointBuyToLetPayload($this->coOwner), $this->owner, IngestSource::FORM);

    $fullAnnualProfit = (1_800.0 - 420.0) * 12;

    expect((float) $this->owner->fresh()->annual_rental_income + (float) $this->coOwner->fresh()->annual_rental_income)
        ->toBe($fullAnnualProfit);
});

it('agrees with the one home for the rental figure on both sides', function () {
    $this->store->create(jointBuyToLetPayload($this->coOwner), $this->owner, IngestSource::FORM);

    foreach ([$this->owner, $this->coOwner] as $user) {
        $fresh = $user->fresh();

        expect((float) $fresh->annual_rental_income)
            ->toBe(round($this->propertyService->annualRentalTaxPosition($fresh)['total'], 2));
    }
});

it('charges a third party co-owner\'s share to nobody', function () {
    // Tenants in common, 40% to the user. The other 60% belongs to someone who
    // has no account, so `joint_owner_id` is null.
    // (1350 - 223) x 12 = £13,524 annual profit; 40% of that is £5,409.60.
    $this->store->create([
        'property_type' => 'buy_to_let',
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 40,
        'joint_owner_id' => null,
        'current_value' => 295_000,
        'monthly_rental_income' => 1_350,
        'monthly_building_insurance' => 28,
        'monthly_service_charge' => 195,
    ], $this->owner, IngestSource::FORM);

    expect((float) $this->owner->fresh()->annual_rental_income)->toBe(5409.60)
        // The other user is not an owner of this property and must not inherit
        // the remainder just because somebody else holds 40% of it.
        ->and((float) $this->coOwner->fresh()->annual_rental_income)->toBe(0.0);
});

it('recomputes both owners when the rent changes', function () {
    $property = $this->store->create(jointBuyToLetPayload($this->coOwner), $this->owner, IngestSource::FORM);

    // (2400 - 420) x 12 = £23,760 annual profit; £11,880 each.
    $this->store->update($property->id, ['monthly_rental_income' => 2_400], $this->owner, IngestSource::FORM);

    expect((float) $this->owner->fresh()->annual_rental_income)->toBe(11880.0)
        ->and((float) $this->coOwner->fresh()->annual_rental_income)->toBe(11880.0);
});

it('recomputes a co-owner who has just been removed from the record', function () {
    $property = $this->store->create(jointBuyToLetPayload($this->coOwner), $this->owner, IngestSource::FORM);

    expect((float) $this->coOwner->fresh()->annual_rental_income)->toBe(8280.0);

    $this->store->update($property->id, [
        'joint_owner_id' => null,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ], $this->owner, IngestSource::FORM);

    expect((float) $this->coOwner->fresh()->annual_rental_income)->toBe(0.0)
        ->and((float) $this->owner->fresh()->annual_rental_income)->toBe(16560.0);
});

it('clears both owners when the property is deleted', function () {
    $property = $this->store->create(jointBuyToLetPayload($this->coOwner), $this->owner, IngestSource::FORM);

    $this->store->delete($property->id, $this->owner, 'sold');

    expect((float) $this->owner->fresh()->annual_rental_income)->toBe(0.0)
        // The row is gone by the time the listener runs, so the co-owner is only
        // reachable because the delete event carries them.
        ->and((float) $this->coOwner->fresh()->annual_rental_income)->toBe(0.0);
});

it('credits both owners again when a deleted property is restored', function () {
    $property = $this->store->create(jointBuyToLetPayload($this->coOwner), $this->owner, IngestSource::FORM);
    $this->store->delete($property->id, $this->owner, 'sold');

    $this->store->restore($property->id, $this->owner);

    expect((float) $this->owner->fresh()->annual_rental_income)->toBe(8280.0)
        ->and((float) $this->coOwner->fresh()->annual_rental_income)->toBe(8280.0);
});

it('leaves a main residence out of it entirely', function () {
    $this->store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_id' => $this->coOwner->id,
        'current_value' => 850_000,
    ], $this->owner, IngestSource::FORM);

    expect((float) $this->owner->fresh()->annual_rental_income)->toBe(0.0)
        ->and((float) $this->coOwner->fresh()->annual_rental_income)->toBe(0.0);
});
