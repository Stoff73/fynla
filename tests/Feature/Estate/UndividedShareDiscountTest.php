<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\UndividedShareDiscount;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PropertyStore;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

/**
 * W-0368 — a half share of a house is not half a house.
 *
 * The buyer of an undivided share cannot sell, occupy or mortgage it freely, so for
 * Inheritance Tax the share is valued with a discount for that restricted
 * marketability under IHTA 1984 s160. **s161 substitutes a valuation
 * basis between spouses** — related property is valued as a proportion of the
 * combined whole, which leaves no restriction for a discount to price. It is a
 * different basis, not a refusal.
 *
 * The spouse case is the one that must never regress: applying a discount there would
 * UNDERSTATE tax, which is the direction that matters.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->discount = app(UndividedShareDiscount::class);
    $this->rate = app(TaxConfigService::class)->getInheritanceTax()['undivided_share_discount_percent'];
});

function coOwnedProperty(
    User $owner,
    ?User $coOwner,
    float $value = 295000,
    float $sharePct = 40,
    ?bool $isSpouse = false
): Property {
    return Property::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => $coOwner?->id,
        'joint_owner_name' => $coOwner === null ? 'Ruth Alderton' : null,
        // The user is asked this on the property form and the answer is stored.
        // Defaulting the fixture to `false` states "not my spouse" deliberately —
        // leaving it null would mean "never asked", which takes no discount.
        'joint_owner_is_spouse' => $isSpouse,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => $sharePct,
        'current_value' => $value,
    ]);
}

describe('the discount applies to a share held with a non-spouse', function () {
    it('values the share below the arithmetic fraction', function () {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $property = coOwnedProperty($user, $friend);

        // 40% of £295,000 is £118,000 — the figure W-0368 was raised against.
        $undiscounted = 295000 * 0.40;

        expect($this->discount->applies($property, $user))->toBeTrue()
            ->and($this->discount->shareValue($property, $user))
            ->toEqualWithDelta($undiscounted * (1 - $this->rate), 0.01)
            ->and($this->discount->shareValue($property, $user))->toBeLessThan($undiscounted);
    });

    it('takes the discount from configuration rather than a literal', function () {
        // Rule 2. Move the configured rate and the valuation must move with it —
        // a hardcoded 0.10 would survive this and is what the assertion exists to catch.
        $user = User::factory()->create();
        $property = coOwnedProperty($user, User::factory()->create());

        $atConfigured = $this->discount->shareValue($property, $user);

        config(['dummy' => null]);
        $service = app(TaxConfigService::class);
        $config = $service->getInheritanceTax();
        $config['undivided_share_discount_percent'] = 0.25;
        app()->instance(TaxConfigService::class, new class($config) extends TaxConfigService
        {
            public function __construct(private array $iht) {}

            public function getInheritanceTax(): array
            {
                return $this->iht;
            }
        });

        $atQuarter = app(UndividedShareDiscount::class)->shareValue($property->fresh(), $user);

        expect($atQuarter)->toBeLessThan($atConfigured);
    });

    it('discounts a share co-owned with a named third party the user identified as such', function () {
        // `joint_owner_name` with no linked account, and the user picked "Other
        // (Enter Name)" rather than the spouse option. That stated answer is what
        // makes the discount safe — the absence of a linked account never did.
        $user = User::factory()->create();
        $property = coOwnedProperty($user, null, isSpouse: false);

        expect($this->discount->applies($property, $user))->toBeTrue();
    });

    it('discounts the co-owner\'s side too, not only the primary owner\'s', function () {
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        $property = coOwnedProperty($owner, $friend);

        expect($this->discount->applies($property, $friend))->toBeTrue()
            ->and($this->discount->shareValue($property, $friend))
            ->toEqualWithDelta(295000 * 0.60 * (1 - $this->rate), 0.01);
    });
});

describe('an unanswered question is not a "no"', function () {
    // The heart of W-0368's C2. `SpouseLinkingService` writes no `spouse_id` on
    // either side until an invitation is ACCEPTED, so "married, unlinked" is the
    // app's designed state for every married user mid-invitation — not an edge.
    // Treating unknown as "not a spouse" would discount their home and UNDERSTATE
    // Inheritance Tax, which is the direction that gets a user into trouble.
    it('takes no discount where the user was never asked', function () {
        $user = User::factory()->create();
        $property = coOwnedProperty($user, null, isSpouse: null);

        expect($property->joint_owner_is_spouse)->toBeNull()
            ->and($this->discount->applies($property, $user))->toBeFalse()
            ->and($this->discount->shareValue($property, $user))
            ->toEqualWithDelta(295000 * 0.40, 0.01);
    });

    it('takes no discount for a spouse recorded only by name', function () {
        // The measured case: property 70 on live data, `joint_owner_name` = "wife",
        // no linked account, owner marked `single`. Neither marital status nor the
        // name distinguishes it — the stored answer does.
        $user = User::factory()->create(['marital_status' => 'single']);
        $property = coOwnedProperty($user, null, isSpouse: true);

        expect($this->discount->applies($property, $user))->toBeFalse();
    });
});

describe('s161 substitutes a different basis between spouses', function () {
    it('does not discount a share held with a live spouse', function () {
        $user = User::factory()->create();
        $spouse = User::factory()->create();
        $user->update(['spouse_id' => $spouse->id]);
        $spouse->update(['spouse_id' => $user->id]);

        $property = coOwnedProperty($user->fresh(), $spouse);

        // Related property: the two shares are valued together, so the restriction
        // the discount pays for does not exist. Discounting here UNDERSTATES tax.
        expect($this->discount->applies($property, $user->fresh()))->toBeFalse()
            ->and($this->discount->shareValue($property, $user->fresh()))
            ->toEqualWithDelta(295000 * 0.40, 0.01);
    });

    it('does not discount an individually owned property', function () {
        $user = User::factory()->create();
        $property = Property::factory()->create([
            'user_id' => $user->id,
            'joint_owner_id' => null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 295000,
        ]);

        expect($this->discount->applies($property, $user))->toBeFalse()
            ->and($this->discount->shareValue($property, $user))->toEqualWithDelta(295000.0, 0.01);
    });
});

describe('the current and projected columns value it the same way', function () {
    // Acceptance 3, and the reason it is written that way: F-0026 section 1 records
    // these two diverging once already. The projection used to read
    // CrossModuleAssetAggregator::calculatePropertyTotal(), which is shared with net
    // worth and stays undiscounted by design.
    it('carries the discounted share into the estate asset gathering', function () {
        $user = User::factory()->create();
        $property = coOwnedProperty($user, User::factory()->create());

        $assets = app(EstateAssetAggregatorService::class)->gatherUserAssets($user->fresh());
        $propertyAsset = collect($assets)->firstWhere('asset_type', 'property');

        expect($propertyAsset)->not->toBeNull()
            ->and((float) $propertyAsset->current_value)
            ->toEqualWithDelta(295000 * 0.40 * (1 - $this->rate), 0.01)
            ->and((float) $propertyAsset->undiscounted_share)->toEqualWithDelta(118000.0, 0.01)
            ->and((float) $propertyAsset->undivided_share_discount)->toBeGreaterThan(0.0);
    });

    it('gives propertyTotal the same answer the per-property valuation gives', function () {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $a = coOwnedProperty($user, $friend, 295000, 40);
        $b = coOwnedProperty($user, null, 180000, 50);

        $total = $this->discount->propertyTotal($user, collect([$a, $b]));

        expect($total)->toEqualWithDelta(
            $this->discount->shareValue($a, $user) + $this->discount->shareValue($b, $user),
            0.01
        );
    });
});

describe('what this deliberately does not do', function () {
    // Pinned so a later reader does not "finish" it by inferring occupation.
    it('applies one rate rather than guessing whether a co-owner is in occupation', function () {
        $user = User::factory()->create();
        $friend = User::factory()->create();

        $home = coOwnedProperty($user, $friend);
        $home->update(['property_type' => 'main_residence']);
        $rental = coOwnedProperty($user, $friend);
        $rental->update(['property_type' => 'buy_to_let']);

        // The ~15% occupation case is unreachable: nothing on `properties` records who
        // lives there. Property type is not occupation and must not be read as it.
        expect($this->discount->shareValue($home->fresh(), $user) / (295000 * 0.40))
            ->toEqualWithDelta($this->discount->shareValue($rental->fresh(), $user) / (295000 * 0.40), 0.0001);
    });
});

describe('a deleted account does not end a marriage', function () {
    // W-0368 C2 route (c). `applies()` asked a RELATIONSHIP question — is this
    // co-owner my spouse — through `liveSpouseId()`, which answers a different
    // question: may I show their data. Those diverge the moment the spouse deletes
    // their account, because `spouse_id` deliberately survives the deletion while
    // the soft-delete filter makes `liveSpouse()` null. The comparison then
    // succeeded and discounted a share held with the user's own spouse.
    //
    // Deleting an account is not a divorce. `spouse_id` is nulled on both sides
    // when the link is actually broken (FamilyMembersController), and that is the
    // event that should turn the discount on — not a data-retention state.
    it('does not discount a share held with a spouse whose account is deleted', function () {
        $user = User::factory()->create();
        $spouse = User::factory()->create();
        $user->update(['spouse_id' => $spouse->id]);
        $spouse->update(['spouse_id' => $user->id]);

        $property = coOwnedProperty($user->fresh(), $spouse);

        $spouse->delete();

        $user = $user->fresh();

        expect($user->liveSpouseId())->toBeNull()
            ->and($user->spouse_id)->toBe($spouse->id)
            ->and($this->discount->applies($property, $user))->toBeFalse()
            ->and($this->discount->shareValue($property, $user))
            ->toEqualWithDelta(295000 * 0.40, 0.01);
    });

    it('discounts once the link is actually broken', function () {
        $user = User::factory()->create();
        $former = User::factory()->create();
        $user->update(['spouse_id' => $former->id]);
        $former->update(['spouse_id' => $user->id]);

        $property = coOwnedProperty($user->fresh(), $former);

        // The unlink nulls `spouse_id` on both sides. They are now two people who
        // happen to co-own, and the share is restricted like any other.
        $user->update(['spouse_id' => null]);
        $former->update(['spouse_id' => null]);

        expect($this->discount->applies($property, $user->fresh()))->toBeTrue();
    });
});

describe('the answer is about one co-owner and does not outlive them', function () {
    // W-0368 C2 route (b). "Not my spouse" was true of Ruth Alderton. Replace the
    // co-owner and it is an answer about somebody else, still standing — the
    // re-gate measured £18,000 discounted off a spouse's undivided share that way.
    //
    // **Reachability, stated accurately.** These drive PropertyStore::update()
    // directly, as the re-gate's probe did. Today that method has exactly one
    // caller — PropertyController::update(), behind the single PUT
    // /api/properties/{id} — and the web form always sends the field, so no
    // CURRENT user route reaches the stale answer. There is no `update_property`
    // Fyn tool (Fyn creates properties only) and `/m` issues no PUT at all, so the
    // re-gate's "every Fyn write leaves the old answer standing" does not hold for
    // updates as written.
    //
    // These are therefore a GUARD at the boundary, not the closure of a live hole,
    // and they are worth having there rather than in each writer (Rule 20): the
    // next writer is the `update_property` tool `/m` and native need, and
    // `PropertyNormaliser::fromFyn()` whitelists `joint_owner_name` without the
    // answer, so that writer arrives holding exactly this defect.
    beforeEach(function () {
        $this->seed(TierConfigurationSeeder::class);
        $this->store = app(PropertyStore::class);
    });

    it('forgets the answer when a write replaces the co-owner by name', function () {
        $user = User::factory()->create();
        $property = coOwnedProperty($user, null, 360000, 50);

        expect($property->joint_owner_is_spouse)->toBeFalse();

        // The shape a writer that carries the name without the answer produces.
        $updated = $this->store->update(
            $property->id,
            ['joint_owner_name' => 'Jane (my wife)'],
            $user,
            IngestSource::FYN_AI,
        );

        expect($updated->joint_owner_is_spouse)->toBeNull()
            ->and($this->discount->applies($updated, $user))->toBeFalse()
            ->and($this->discount->shareValue($updated, $user))
            ->toEqualWithDelta(180000.0, 0.01);
    });

    it('forgets the answer when the co-owner becomes a linked account', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $property = coOwnedProperty($user, null, 360000, 50);

        $updated = $this->store->update(
            $property->id,
            ['joint_owner_id' => $other->id, 'joint_owner_name' => null],
            $user,
            IngestSource::FYN_AI,
        );

        expect($updated->joint_owner_is_spouse)->toBeNull();
    });

    it('keeps an answer the same write supplies alongside the new co-owner', function () {
        // The web form always sends the field, and its value is a live assertion:
        // the select offers "<name> (Spouse)" and "Other (Enter Name)" as separate
        // choices, so whichever is showing describes whoever is named beneath it.
        $user = User::factory()->create();
        $property = coOwnedProperty($user, null, 360000, 50);

        $updated = $this->store->update(
            $property->id,
            ['joint_owner_name' => 'Jane Isley', 'joint_owner_is_spouse' => true],
            $user,
            IngestSource::FORM,
        );

        expect($updated->joint_owner_is_spouse)->toBeTrue();
    });

    it('leaves the answer alone when the co-owner does not change', function () {
        $user = User::factory()->create();
        $property = coOwnedProperty($user, null, 360000, 50);

        $updated = $this->store->update(
            $property->id,
            ['current_value' => 400000],
            $user,
            IngestSource::FYN_AI,
        );

        expect($updated->joint_owner_is_spouse)->toBeFalse()
            ->and($this->discount->applies($updated, $user))->toBeTrue();
    });
});
