<?php

declare(strict_types=1);

use App\Exceptions\FinancialCalculationException;
use App\Models\Estate\Liability;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Support\SecuringPropertyResolver;
use App\Traits\CalculatesOwnershipShare;

/**
 * W-0228 — **a debt is shared exactly as the asset securing it is shared**
 * (CSJ ruling, 2026-08-22).
 *
 * The defect these pin was not a missing share. Both records carried one and
 * they disagreed: a property held `tenants_in_common` at 40% carried a mortgage
 * row saying `joint` at 50%, and whichever record a given screen happened to
 * read decided what the user was told they owed. One household, one debt, two
 * figures, four inches apart.
 *
 * **Every case here changes the property and asserts the answer MOVES.** A test
 * that sets up 40% and asserts 40% of the balance passes just as happily against
 * code that reads the mortgage's own 50% and happens to be handed a fixture where
 * the two agree. The fixtures below make them disagree on purpose.
 */
class MortgageShareProbe
{
    use CalculatesOwnershipShare;

    public function share(object $mortgage, int $userId): float
    {
        return $this->calculateUserMortgageShare($mortgage, $userId);
    }

    public function monthlyShare(object $mortgage, int $userId): float
    {
        return $this->calculateUserMortgageMonthlyPaymentShare($mortgage, $userId);
    }

    public function fraction(object $asset, int $userId): float
    {
        return $this->userShareFraction($asset, $userId);
    }
}

beforeEach(function () {
    app(SecuringPropertyResolver::class)->forget();

    $this->probe = new MortgageShareProbe;

    $this->owner = User::factory()->create();
    $this->spouse = User::factory()->create();

    $this->owner->spouse_id = $this->spouse->id;
    $this->owner->save();
    $this->spouse->spouse_id = $this->owner->id;
    $this->spouse->save();
});

/**
 * A property held with an OFF-PLATFORM co-owner, carrying a mortgage whose own
 * columns say something different. This is the persona's Manchester unit.
 */
function millPropertyWithContradictoryMortgage(User $owner, float $propertyShare = 40): Mortgage
{
    $property = Property::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => null,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => $propertyShare,
        'current_value' => 295000,
    ]);

    return Mortgage::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => null,
        'property_id' => $property->id,
        'outstanding_balance' => 120000,
        'monthly_payment' => 750,
        // Deliberately contradicts the property, which is the defect's actual shape.
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);
}

describe('the property decides, not the mortgage row', function () {
    it('charges the owner their property share, ignoring the mortgage row saying 50%', function () {
        $mortgage = millPropertyWithContradictoryMortgage($this->owner);

        // 40% of £120,000. Reading the mortgage's own columns would give £60,000.
        expect($this->probe->share($mortgage, $this->owner->id))->toEqualWithDelta(48000.0, 0.01);
    });

    it('MOVES when the property share moves, with the mortgage row left untouched', function () {
        $atForty = millPropertyWithContradictoryMortgage($this->owner, 40);
        $fortyShare = $this->probe->share($atForty, $this->owner->id);

        $property = Property::find($atForty->property_id);
        $property->ownership_percentage = 60;
        $property->save();
        app(SecuringPropertyResolver::class)->forget();

        $sixtyShare = $this->probe->share($atForty->fresh(), $this->owner->id);

        // The mortgage row still says joint/50 in both readings. If the answer
        // did not move, the property is not being read at all.
        expect($fortyShare)->toEqualWithDelta(48000.0, 0.01)
            ->and($sixtyShare)->toEqualWithDelta(72000.0, 0.01)
            ->and($sixtyShare)->toBeGreaterThan($fortyShare);
    });

    it('shares the monthly payment on the same basis as the balance', function () {
        $mortgage = millPropertyWithContradictoryMortgage($this->owner);

        // 40% of £750, not the £375 the mortgage row's 50% would give.
        expect($this->probe->monthlyShare($mortgage, $this->owner->id))->toEqualWithDelta(300.0, 0.01);
    });
});

describe('a co-owner who is not on the mortgage', function () {
    it('charges an off-platform third party to nobody', function () {
        $mortgage = millPropertyWithContradictoryMortgage($this->owner);

        $ownerShare = $this->probe->share($mortgage, $this->owner->id);
        $spouseShare = $this->probe->share($mortgage, $this->spouse->id);

        // The remaining 60% belongs to someone with no account here. It reduces
        // the owner's figure without being credited to anyone — least of all the
        // spouse, who has no interest in this property at all.
        expect($spouseShare)->toEqualWithDelta(0.0, 0.01)
            ->and($ownerShare + $spouseShare)->toBeLessThan(120000.0);
    });

    it('charges a property co-owner their share even though the mortgage row does not name them', function () {
        $property = Property::factory()->create([
            'user_id' => $this->owner->id,
            'joint_owner_id' => $this->spouse->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'current_value' => 850000,
        ]);

        $mortgage = Mortgage::factory()->create([
            'user_id' => $this->owner->id,
            'joint_owner_id' => null, // the spouse is NOT on the mortgage row
            'property_id' => $property->id,
            'outstanding_balance' => 65000,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        // Under the ruling she owns half the property, so she owes half the debt
        // secured on it. Reading the mortgage row would have given her nothing.
        expect($this->probe->share($mortgage, $this->spouse->id))->toEqualWithDelta(32500.0, 0.01)
            ->and($this->probe->share($mortgage, $this->owner->id))->toEqualWithDelta(32500.0, 0.01);
    });
});

describe('the household total', function () {
    it('reaches the co-owner through the property and splits the debt exactly once', function () {
        $property = Property::factory()->create([
            'user_id' => $this->owner->id,
            'joint_owner_id' => $this->spouse->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'current_value' => 850000,
        ]);

        Mortgage::factory()->create([
            'user_id' => $this->owner->id,
            'joint_owner_id' => $this->spouse->id,
            'property_id' => $property->id,
            'outstanding_balance' => 65000,
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
        ]);

        millPropertyWithContradictoryMortgage($this->owner);

        $aggregator = app(CrossModuleAssetAggregator::class);
        $ownerTotal = $aggregator->calculateMortgageTotal($this->owner->id);
        $spouseTotal = $aggregator->calculateMortgageTotal($this->spouse->id);

        // Owner: half of 65,000 plus 40% of 120,000. Spouse: half of 65,000 and
        // nothing of the Manchester unit she has no share in.
        expect($ownerTotal)->toEqualWithDelta(80500.0, 0.01)
            ->and($spouseTotal)->toEqualWithDelta(32500.0, 0.01)
            // The 60% belonging to the third party appears in neither total.
            ->and($ownerTotal + $spouseTotal)->toEqualWithDelta(113000.0, 0.01);
    });
});

describe('a mortgage with no property to resolve against', function () {
    it('falls back to its own columns, which are the only information that exists', function () {
        $mortgage = new Mortgage([
            'outstanding_balance' => 100000,
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
        ]);
        $mortgage->user_id = $this->owner->id;
        $mortgage->joint_owner_id = $this->spouse->id;
        $mortgage->property_id = null;

        expect($this->probe->share($mortgage, $this->owner->id))->toEqualWithDelta(50000.0, 0.01)
            ->and($this->probe->share($mortgage, $this->spouse->id))->toEqualWithDelta(50000.0, 0.01);
    });
});

describe('the fraction helper refuses the question it cannot answer', function () {
    it('throws rather than answering from the mortgage row', function () {
        $mortgage = millPropertyWithContradictoryMortgage($this->owner);

        expect(fn () => $this->probe->fraction($mortgage, $this->owner->id))
            ->toThrow(FinancialCalculationException::class);
    });

    it('still answers for an asset whose share is a property of the record itself', function () {
        $property = Property::factory()->create([
            'user_id' => $this->owner->id,
            'joint_owner_id' => $this->spouse->id,
            'ownership_type' => 'tenants_in_common',
            'ownership_percentage' => 40,
            'current_value' => 295000,
        ]);

        expect($this->probe->fraction($property, $this->owner->id))->toEqualWithDelta(0.40, 0.0001)
            ->and($this->probe->fraction($property, $this->spouse->id))->toEqualWithDelta(0.60, 0.0001);
    });
});

describe('a non-mortgage liability — a row this persona does not have', function () {
    it('charges each owner their share, and the answer moves when the share moves', function () {
        // The peak_earners household holds ZERO `liabilities` rows, so every
        // assertion over this path passes trivially unless one is created here.
        $liability = Liability::factory()->create([
            'user_id' => $this->owner->id,
            'joint_owner_id' => $this->spouse->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'liability_type' => 'personal_loan',
            'current_balance' => 20000,
        ]);

        $aggregator = app(CrossModuleAssetAggregator::class);
        $atFifty = $aggregator->calculateLiabilityTotals($this->owner->id)['other'];

        $liability->ownership_percentage = 75;
        $liability->save();

        $atSeventyFive = $aggregator->calculateLiabilityTotals($this->owner->id)['other'];

        expect($atFifty)->toEqualWithDelta(10000.0, 0.01)
            ->and($atSeventyFive)->toEqualWithDelta(15000.0, 0.01)
            ->and($atSeventyFive)->toBeGreaterThan($atFifty);
    });
});
