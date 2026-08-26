<?php

declare(strict_types=1);

use App\Models\Estate\Liability;
use App\Models\Investment\InvestmentAccount;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\Investment\InvestmentProjectionService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0331 — the projected estate must own the same things the current estate owns.
 *
 * `calculate()` reports two estates in one response: the estate as it stands and
 * the estate projected to the second death. The headline reads
 * `EstateAssetAggregatorService::gatherUserAssets()` — `forUserOrJoint` reach at
 * `calculateUserShare` fraction. The projection read
 * `InvestmentAccount::where('user_id', …)->sum('current_value')` at 100%. Two
 * ownership rules, one response.
 *
 * **What the census called a double count is not one.** Two `where('user_id', …)`
 * queries over the two members are disjoint — a row carries exactly one `user_id`,
 * so nothing was ever counted twice by that pair. The real failures are the two
 * this file pins:
 *
 *   1. **A stranger's share lands in the household's estate.** A shared account
 *      whose co-owner has no account here (`joint_owner_id` NULL) was taken whole.
 *      Investments carry this as `ownership_type: 'joint'` — the schema's enum is
 *      `('individual','joint','trust')`, so `tenants_in_common` is property-only,
 *      which is where the persona's £177,000 version of this lives.
 *   2. **With data sharing off, the recorder is charged with the co-owner's half**
 *      and the co-owner is shown none of an account they part-own.
 *
 * Plus a third that only appears between the two code paths: the simulation is
 * share-aware and the fallback was not, so a run where one member simulated and
 * the other fell back counted a joint record at more than its value.
 *
 * ## Why these fixtures look the way they do
 *
 * **Asymmetric splits only (75/25, 70/30).** At 50/50 the primary owner's share and
 * the co-owner's are the same number and no assertion here can tell a correct
 * implementation from a broken one (`tests/CLAUDE.md` §4, Collision).
 *
 * **The simulation is stubbed to force the fallback branch.** That is not the Mock
 * variant: the stub supplies no ownership figure and asserts nothing about one. It
 * removes Monte Carlo variance so the compounded fallback — the branch under test —
 * produces a figure that can be compared exactly. Nothing here assumes a growth
 * rate or a horizon; every assertion compares two households that differ only in
 * the ownership of one account, so the growth cancels.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

/**
 * A married household, both spouses the same age so the projection horizon is
 * identical between the households built in one test.
 *
 * @return array{0: User, 1: User}
 */
function ownershipProjectionHousehold(): array
{
    $primary = User::factory()->create([
        'marital_status' => 'married',
        'date_of_birth' => '1970-01-01',
        'gender' => 'male',
        'monthly_expenditure' => 2_000,
    ]);
    $partner = User::factory()->create([
        'marital_status' => 'married',
        'date_of_birth' => '1970-01-01',
        'gender' => 'female',
        'spouse_id' => $primary->id,
        'monthly_expenditure' => 2_000,
    ]);
    $primary->update(['spouse_id' => $partner->id]);

    return [$primary->fresh(), $partner->fresh()];
}

/**
 * Bind an investment projection service whose simulation yields a figure only for
 * the users named; everyone else falls through to the compounded fallback.
 *
 * @param  array<int, float>  $p20ByUserId  user id => simulated value
 */
function stubProjections(array $p20ByUserId = []): void
{
    $stub = Mockery::mock(InvestmentProjectionService::class);
    $stub->shouldReceive('getPortfolioProjections')
        ->andReturnUsing(function (User $user, array $periods) use ($p20ByUserId) {
            if (! array_key_exists($user->id, $p20ByUserId)) {
                return ['portfolio' => null, 'accounts' => [], 'message' => 'No investment accounts found'];
            }

            return ['portfolio' => ['projections' => [max($periods) => ['percentiles' => ['p20' => $p20ByUserId[$user->id]]]]]];
        });

    app()->instance(InvestmentProjectionService::class, $stub);
}

/**
 * The projected investment figure the user is shown.
 */
function projectedInvestments(User $viewer, ?User $spouse, bool $dataSharingEnabled): float
{
    return (float) app(IHTCalculationService::class)
        ->calculate($viewer, $spouse, $dataSharingEnabled)['projected_investments'];
}

describe('a share held with someone outside the household', function () {
    it('carries only the household\'s share into the projected estate', function () {
        [$david, $sarah] = ownershipProjectionHousehold();
        [$control, $controlPartner] = ownershipProjectionHousehold();
        stubProjections();

        // £100,000 held jointly, 70% to David, the other 30% belonging to someone
        // with no account here — `joint_owner_id` NULL on a shared record.
        //
        // Not `tenants_in_common`: `investment_accounts.ownership_type` is
        // `enum('individual','joint','trust')`, so that type is property-only and
        // the schema rejects it. The shape below is the one investments can carry,
        // and it occurs in real data — the persona household holds a mortgage
        // recorded exactly this way.
        InvestmentAccount::factory()->create([
            'user_id' => $david->id,
            'joint_owner_id' => null,
            'ownership_type' => 'joint',
            'ownership_percentage' => 70,
            'current_value' => 100_000,
        ]);

        // A 70% share of £100,000 and £70,000 owned outright are the same estate,
        // so the two households must project the same investment figure.
        InvestmentAccount::factory()->create([
            'user_id' => $control->id,
            'joint_owner_id' => null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 70_000,
        ]);

        $withThirdParty = projectedInvestments($david, $sarah, true);
        $ownedOutright = projectedInvestments($control, $controlPartner, true);

        expect($ownedOutright)->toBeGreaterThan(0.0);
        // Under the defect the first household projects the whole £100,000 — the
        // stranger's £30,000 taxed on this household's death — and the ratio
        // between the two figures is 100/70 rather than 1.
        expect($withThirdParty)->toEqualWithDelta($ownedOutright, 0.01);
    });
});

describe('a joint record between the two spouses', function () {
    it('reaches both of them at their own share when data sharing is off', function () {
        [$david, $sarah] = ownershipProjectionHousehold();
        stubProjections();

        // 75/25, deliberately not 50/50: at 50/50 David's share, Sarah's share and
        // half the account are one number and nothing below could fail.
        InvestmentAccount::factory()->create([
            'user_id' => $david->id,
            'joint_owner_id' => $sarah->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 75,
            'current_value' => 100_000,
        ]);

        $davidAlone = projectedInvestments($david, null, false);
        $sarahAlone = projectedInvestments($sarah, null, false);
        $pooled = projectedInvestments($david, $sarah, true);

        expect($davidAlone)->toBeGreaterThan(0.0);
        // Sarah is the joint owner, not the `user_id`. The old query returned her
        // nothing at all, so this is the assertion that moves off £0.
        expect($sarahAlone)->toBeGreaterThan(0.0);
        expect($sarahAlone / $davidAlone)->toEqualWithDelta(25 / 75, 0.0001);

        // The two private views add up to the household view — the same account,
        // seen three ways, worth the same money. This crosses the two code paths
        // rather than restating the ratio above.
        expect($davidAlone + $sarahAlone)->toEqualWithDelta($pooled, 0.01);
    });

    it('is counted once, not once per spouse, when data sharing is on', function () {
        [$david, $sarah] = ownershipProjectionHousehold();
        [$control, $controlPartner] = ownershipProjectionHousehold();
        stubProjections();

        InvestmentAccount::factory()->create([
            'user_id' => $david->id,
            'joint_owner_id' => $sarah->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 75,
            'current_value' => 100_000,
        ]);
        InvestmentAccount::factory()->create([
            'user_id' => $control->id,
            'joint_owner_id' => null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 100_000,
        ]);

        // The household holds the whole account, exactly as if one of them owned
        // all of it — no more (a double count) and no less (the primary's share
        // alone, with the co-owner's quarter dropped).
        expect(projectedInvestments($david, $sarah, true))
            ->toEqualWithDelta(projectedInvestments($control, $controlPartner, true), 0.01);

        // COLLISION WARNING, stated because this case otherwise looks like it
        // proves more than it does: the pooled figure was ALREADY correct before
        // the fix, because the two `where('user_id', …)` queries were disjoint.
        // Nothing here can fail against the original defect. It is here to catch
        // the OPPOSITE error — a "fix" that credits the household only the primary
        // owner's 75% and silently loses Sarah's quarter — which it does fail.
    });
});

describe('when one spouse simulates and the other falls back', function () {
    it('still counts a joint account once', function () {
        [$david, $sarah] = ownershipProjectionHousehold();
        [$control, $controlPartner] = ownershipProjectionHousehold();

        // Sarah's simulation succeeds and returns her own £25,000 share, exactly as
        // the real service does (`accountsWithUserShare` + `calculateUserShare`).
        // David's does not, so his leg is compounded from the database.
        stubProjections([$sarah->id => 25_000.0]);

        InvestmentAccount::factory()->create([
            'user_id' => $david->id,
            'joint_owner_id' => $sarah->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 75,
            'current_value' => 100_000,
        ]);

        // The control establishes what the fallback does to £75,000 — David's
        // share — without assuming the rate or the horizon.
        InvestmentAccount::factory()->create([
            'user_id' => $control->id,
            'joint_owner_id' => null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 75_000,
        ]);

        $mixed = projectedInvestments($david, $sarah, true);
        $davidsLeg = projectedInvestments($control, $controlPartner, true);

        expect($davidsLeg)->toBeGreaterThan(0.0);
        // David's compounded 75% plus Sarah's simulated 25%. Under the defect
        // David's leg compounds the WHOLE £100,000 while Sarah's simulation has
        // already contributed her quarter, so the account lands at more than its
        // value and this figure comes out larger.
        expect($mixed)->toEqualWithDelta($davidsLeg + 25_000.0, 0.01);
    });
});

/**
 * The property figure the user is shown, projected to the second death.
 */
function projectedProperties(User $viewer, ?User $spouse, bool $dataSharingEnabled): float
{
    return (float) app(IHTCalculationService::class)
        ->calculate($viewer, $spouse, $dataSharingEnabled)['projected_properties'];
}

/**
 * The liability figure remaining at the second death.
 */
function projectedLiabilities(User $viewer, ?User $spouse, bool $dataSharingEnabled): float
{
    return (float) app(IHTCalculationService::class)
        ->calculate($viewer, $spouse, $dataSharingEnabled)['projected_liabilities'];
}

describe('property held with someone outside the household', function () {
    it('leaves the third party\'s share out of the projected estate', function () {
        [$david, $sarah] = ownershipProjectionHousehold();
        [$control, $controlPartner] = ownershipProjectionHousehold();
        stubProjections();

        // The persona's Victoria Mill, to the pound: £295,000 held tenants in
        // common, 40% to David, `joint_owner_id` NULL because the other 60%
        // belongs to someone with no account here.
        Property::factory()->create([
            'user_id' => $david->id,
            'joint_owner_id' => null,
            // W-0368 — stated, not inferred: David identified this co-owner as a
            // third party on the form. Without the answer the valuation takes no
            // discount, which is the conservative default and a different test.
            'joint_owner_is_spouse' => false,
            'property_type' => 'buy_to_let',
            'ownership_type' => 'tenants_in_common',
            'ownership_percentage' => 40,
            'current_value' => 295_000,
        ]);

        // 40% of £295,000 is £118,000. £118,000 owned OUTRIGHT is a different
        // estate from a 40% undivided share, and W-0368 is why: a part share
        // cannot be sold, occupied or mortgaged freely, so Inheritance Tax values
        // it at a discount for that restricted marketability (IHTM15071).
        Property::factory()->create([
            'user_id' => $control->id,
            'joint_owner_id' => null,
            'property_type' => 'buy_to_let',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 118_000,
        ]);

        $withThirdParty = projectedProperties($david, $sarah, true);
        $ownedOutright = projectedProperties($control, $controlPartner, true);
        $discount = (float) app(TaxConfigService::class)
            ->getInheritanceTax()['undivided_share_discount_percent'];

        expect($ownedOutright)->toBeGreaterThan(0.0);

        // **W-0333's protection, stated explicitly rather than left implicit.** Under
        // that defect the household projected the whole £295,000 — £177,000 of a
        // stranger's money grown for 36 years and taxed at 40% on this household's
        // death. The share must stay near its own 40%, nowhere near the whole.
        expect($withThirdParty)->toBeLessThan($ownedOutright * 1.5);

        // **W-0368's refinement.** The two were asserted equal until the undivided
        // share discount existed; they are now separated by exactly that discount,
        // and this is the assertion that would catch it being applied to the wrong
        // side or at the wrong rate.
        expect($withThirdParty)->toEqualWithDelta($ownedOutright * (1 - $discount), 0.01)
            ->and($withThirdParty)->toBeLessThan($ownedOutright);
    });

    it('still counts a property the two spouses share exactly once', function () {
        // This is what `5278a2457` protected, and it must keep holding. Calling
        // the joint-aware store for both members matched a joint property TWICE;
        // that regression must not come back through this door.
        [$david, $sarah] = ownershipProjectionHousehold();
        [$control, $controlPartner] = ownershipProjectionHousehold();
        stubProjections();

        Property::factory()->create([
            'user_id' => $david->id,
            'joint_owner_id' => $sarah->id,
            'property_type' => 'main_residence',
            'ownership_type' => 'joint',
            'ownership_percentage' => 75,
            'current_value' => 400_000,
        ]);
        Property::factory()->create([
            'user_id' => $control->id,
            'joint_owner_id' => null,
            'property_type' => 'main_residence',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 400_000,
        ]);

        // Once — not twice (£800,000, the pre-May regression) and not
        // three-quarters (£300,000, losing the co-owner's share).
        expect(projectedProperties($david, $sarah, true))
            ->toEqualWithDelta(projectedProperties($control, $controlPartner, true), 0.01);
    });
});

describe('debts that outlive the household', function () {
    /**
     * A liability still running at the second death, so a residual survives to be
     * measured. `projectSingleLiability` returns £0 for anything maturing before
     * the horizon — which is why the persona's three mortgages project to zero and
     * why this case has to be built rather than observed.
     */
    $longDatedLiability = function (User $owner, array $overrides = []): void {
        Liability::factory()->create(array_merge([
            'user_id' => $owner->id,
            'liability_type' => 'personal_loan',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'joint_owner_id' => null,
            'current_balance' => 100_000,
            'maturity_date' => now()->addYears(60)->format('Y-m-d'),
        ], $overrides));
    };

    it('discharges a debt the spouses share once, from both sides', function () use ($longDatedLiability) {
        [$david, $sarah] = ownershipProjectionHousehold();
        [$control, $controlPartner] = ownershipProjectionHousehold();
        stubProjections();

        $longDatedLiability($david, [
            'joint_owner_id' => $sarah->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 70,
        ]);
        $longDatedLiability($control);

        $davidAlone = projectedLiabilities($david, null, false);
        $sarahAlone = projectedLiabilities($sarah, null, false);
        $pooled = projectedLiabilities($david, $sarah, true);

        expect($pooled)->toBeGreaterThan(0.0);
        // Sarah is the joint owner, not the `user_id`. `$user->liabilities` is a
        // plain `user_id` relation, so her side of the debt was invisible: £0.
        expect($sarahAlone)->toBeGreaterThan(0.0);
        expect($sarahAlone / $davidAlone)->toEqualWithDelta(30 / 70, 0.0001);

        // 70% + 30% = the whole debt, discharged once.
        expect($pooled)->toEqualWithDelta(projectedLiabilities($control, $controlPartner, true), 0.01);
        expect($davidAlone + $sarahAlone)->toEqualWithDelta($pooled, 0.01);
    });

    it('does not discharge a stranger\'s share of a shared debt', function () use ($longDatedLiability) {
        [$david, $sarah] = ownershipProjectionHousehold();
        [$control, $controlPartner] = ownershipProjectionHousehold();
        stubProjections();

        // Tenants in common with someone who has no account here.
        $longDatedLiability($david, [
            'joint_owner_id' => null,
            'ownership_type' => 'tenants_in_common',
            'ownership_percentage' => 70,
        ]);
        $longDatedLiability($control, ['current_balance' => 70_000]);

        // £70,000 of a £100,000 debt, not the whole of it. An over-counted debt
        // shrinks the estate, so the defect here UNDERSTATED the tax — the
        // opposite direction to the property and investment faults, which is why
        // fixing those alone would have corrected one side of the estate only.
        expect(projectedLiabilities($david, $sarah, true))
            ->toEqualWithDelta(projectedLiabilities($control, $controlPartner, true), 0.01);
    });
});

describe('a mortgage recorded against one spouse on a home they both own', function () {
    it('still deducts the whole debt from the household estate', function () {
        // Raised by the tax-compliance review of this change, not by a tester, and
        // **the persona cannot exercise it** — all three of its mortgage rows name
        // both spouses.
        //
        // A mortgage is REACHED by the mortgage row's own `user_id` /
        // `joint_owner_id`, but its share is resolved from the SECURING PROPERTY
        // (W-0228: a debt is shared exactly as the asset securing it is shared).
        // When those disagree, debt vanishes silently — and a missing liability
        // inflates the estate, so the user is quoted MORE tax, not less.
        //
        // This is the shape where the old 100%-of-the-row read was accidentally
        // right, so applying the share without widening the reach would have been
        // a regression introduced by the very change that fixed the asset side.
        [$david, $sarah] = ownershipProjectionHousehold();
        [$control, $controlPartner] = ownershipProjectionHousehold();
        stubProjections();

        $home = Property::factory()->create([
            'user_id' => $david->id,
            'joint_owner_id' => $sarah->id,
            'property_type' => 'main_residence',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'current_value' => 600_000,
        ]);

        // The row names David only. Sarah owns half the house and half the debt,
        // and the mortgage record says nothing about her.
        Mortgage::factory()->create([
            'user_id' => $david->id,
            'joint_owner_id' => null,
            'property_id' => $home->id,
            'outstanding_balance' => 200_000,
            // `maturity_date`, not `end_date` — the projection read a column the
            // `mortgages` table does not have, so every mortgage fell through to
            // "assume cleared at retirement age". W-0339.
            'maturity_date' => now()->addYears(60)->format('Y-m-d'),
        ]);

        $controlHome = Property::factory()->create([
            'user_id' => $control->id,
            'joint_owner_id' => null,
            'property_type' => 'main_residence',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 600_000,
        ]);
        Mortgage::factory()->create([
            'user_id' => $control->id,
            'joint_owner_id' => null,
            'property_id' => $controlHome->id,
            'outstanding_balance' => 200_000,
            // `maturity_date`, not `end_date` — the projection read a column the
            // `mortgages` table does not have, so every mortgage fell through to
            // "assume cleared at retirement age". W-0339.
            'maturity_date' => now()->addYears(60)->format('Y-m-d'),
        ]);

        $household = projectedLiabilities($david, $sarah, true);

        expect($household)->toBeGreaterThan(0.0);
        // The whole £200,000, not David's £100,000 half with the rest deducted by
        // nobody. `CrossModuleAssetAggregator::getMortgages()` reaches it through
        // the property on its second leg; the one-leg reader cannot.
        expect($household)
            ->toEqualWithDelta(projectedLiabilities($control, $controlPartner, true), 0.01);
    });
});

describe('a mortgage\'s own maturity date', function () {
    it('decides whether any of it survives to the second death', function () {
        // W-0339. `projectLiabilities` and `projectMainResidenceNetValue` both read
        // `$mortgage->end_date`. **`mortgages` has no such column** — it is
        // `maturity_date`. So the value was always null, and every mortgage in the
        // estate projection fell through to `projectSingleLiability`'s "assume
        // cleared at retirement age" default, whatever its real term.
        //
        // A float carries no absence: nothing threw, nothing logged, and the
        // resulting figure looked entirely plausible. The persona cannot see it —
        // its mortgages mature inside the horizon, so the wrong branch and the
        // right one both give £0.
        //
        // Two households identical but for the maturity date. If the column is
        // being read, they differ; if it is not, both take the retirement-age
        // default and the test cannot tell them apart.
        [$longDated, $longPartner] = ownershipProjectionHousehold();
        [$shortDated, $shortPartner] = ownershipProjectionHousehold();
        stubProjections();

        foreach ([[$longDated, 60], [$shortDated, 10]] as [$owner, $years]) {
            $home = Property::factory()->create([
                'user_id' => $owner->id,
                'joint_owner_id' => null,
                'property_type' => 'main_residence',
                'ownership_type' => 'individual',
                'ownership_percentage' => 100,
                'current_value' => 600_000,
            ]);
            Mortgage::factory()->create([
                'user_id' => $owner->id,
                'joint_owner_id' => null,
                'property_id' => $home->id,
                'outstanding_balance' => 200_000,
                'maturity_date' => now()->addYears($years)->format('Y-m-d'),
            ]);
        }

        // Running past the ~36-year horizon, so part of it is still owed.
        expect(projectedLiabilities($longDated, $longPartner, true))->toBeGreaterThan(0.0);
        // Repaid long before death, so nothing survives into the estate.
        expect(projectedLiabilities($shortDated, $shortPartner, true))->toBe(0.0);
    });
});
