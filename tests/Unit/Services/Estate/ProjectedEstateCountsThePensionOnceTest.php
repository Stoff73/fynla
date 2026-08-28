<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\Retirement\RetirementProjectionService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0482 — the pension money is counted exactly ONCE.
 *
 * This replaces `ProjectedPensionExclusionIsStatedTest`, which asserted the interim
 * caveat W-0363 published while the figure was knowingly understated. The figure is now
 * correct, so the caveat is gone — a caveat that outlives its cause becomes noise, and
 * noise is what makes real ones ignored.
 *
 * **The trap this guards.** `HouseholdCashFlowProjector` already turns the pension into
 * income and carries it in `projected_cash`. The obvious implementation — add the pot at
 * today's value — is wrong in a way no error surfaces: the same money appears once as
 * the income it becomes and once as the fund it came from. What belongs in the estate is
 * the UNUSED fund at the modelled death date.
 *
 * The two ends of the range are what make this falsifiable. A household that has drawn
 * its fund to nothing must add nothing; a household that draws none of it must add the
 * grown fund. A single mid-range assertion would pass against a double count.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/** A single person, dying well after the configured inclusion date. */
function pensionHolder(int $fundValue): User
{
    $user = User::factory()->create([
        'marital_status' => 'single',
        'date_of_birth' => '1980-01-01',
        'gender' => 'female',
    ]);

    if ($fundValue > 0) {
        // Contributions are switched OFF deliberately. The factory's defaults would
        // grow a deliberately small pot into a large one and the depletion case below
        // could not be built at all (tests/CLAUDE.md §4, Fixture).
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_fund_value' => $fundValue,
            'monthly_contribution_amount' => 0,
            'employee_contribution_percent' => 0,
            'employer_contribution_percent' => 0,
        ]);
    }

    return $user->fresh();
}

it('adds the unused fund the retirement engine models, not the pot', function () {
    $user = pensionHolder(400_000);

    $result = app(IHTCalculationService::class)->calculate($user, null, false);

    $ageAtDeath = Carbon\Carbon::parse($user->date_of_birth)->age + (int) $result['years_to_death'];
    $residual = app(RetirementProjectionService::class)->unusedDcFundAtAge($user, $ageAtDeath);

    // The one mechanism, read rather than re-modelled (Rule 20).
    expect($result['projected_unused_pension'])->toBe(round($residual['amount'], 2));

    // And it is NOT the pot. A pot of £400,000 that has funded a retirement is not
    // £400,000 at death; if these were equal the estate would be adding today's value.
    expect($result['projected_unused_pension'])->not->toBe(400000.00);
});

it('adds nothing for a household with no defined contribution pension', function () {
    $result = app(IHTCalculationService::class)->calculate(pensionHolder(0), null, false);

    expect($result['projected_unused_pension'])->toBe(0.0)
        ->and($result['projected_unused_pension_basis'])->toBe('no_pension');
});

it('adds effectively nothing once the fund has been drawn out', function () {
    // Acceptance 3, the first end of the range: a household whose pot is spent adds
    // nothing to its estate.
    //
    // **Why this asserts "under £1" rather than exactly zero.** The engine's drawdown is
    // `remaining * (1 + growth) - min(needed, remaining)`, so once the need exceeds the
    // fund the balance is multiplied by the growth rate each year — 2% of what is left,
    // for ever. It approaches zero and never arrives, so `fund_depletion_age` is never
    // set and `fund_depleted` is never true. That is a defect in the retirement engine's
    // own reporting (W-0510), not in this figure: £2.31 left of a £20,000 pot is spent
    // money by any reading, and what matters here is that the ESTATE is not carrying the
    // pot. Pinning the exact pennies would pin the growth rate instead.
    $user = pensionHolder(20_000);

    // A target income is what makes drawdown happen at all: the engine draws what the
    // household needs and nothing more, so a household that has recorded no target
    // draws nothing and its fund is never touched. Recorded here explicitly rather
    // than relying on a factory default (tests/CLAUDE.md §4, Fixture).
    RetirementProfile::factory()->create([
        'user_id' => $user->id,
        'target_retirement_income' => 45_000,
    ]);

    $projection = app(RetirementProjectionService::class);
    $pot = $projection->projectPensionPot($user);

    $residual = $projection->unusedDcFundAtAge($user, (int) $pot['retirement_age'] + 20);

    expect($residual['amount'])->toBeLessThan(1.0)
        ->and($residual['basis'])->toBe('drawdown_residual');

    // And the estate agrees — this household's projected estate carries no pension.
    $result = app(IHTCalculationService::class)->calculate($user->fresh(), null, false);
    expect($result['projected_unused_pension'])->toBeLessThan(1.0);
});

it('is the grown fund for a death before any of it has been drawn', function () {
    // The other end: a household that draws NONE of it adds the grown fund. Death a
    // year before retirement, so no drawdown has happened and the whole projected pot
    // is unused.
    $user = pensionHolder(400_000);
    $projection = app(RetirementProjectionService::class);

    $pot = $projection->projectPensionPot($user);
    $ageBeforeRetirement = (int) $pot['retirement_age'] - 1;

    $residual = $projection->unusedDcFundAtAge($user, $ageBeforeRetirement);
    $row = $pot['year_by_year'][$ageBeforeRetirement - (int) $pot['current_age']];

    expect($residual['basis'])->toBe('pre_retirement_growth')
        ->and($residual['amount'])->toBe((float) $row['percentile_20'])
        ->and($residual['amount'])->toBeGreaterThan(0.0);
});

it('counts the pension money once, not once as income and again as a fund', function () {
    // The double count made measurable. A larger pension raises BOTH the projected cash
    // (it becomes income) and the unused fund (what income did not spend). Take the cash
    // movement out and what is left must be exactly the residual — no more, which is
    // what adding the pot at today's value would have produced.
    $withFund = app(IHTCalculationService::class)->calculate(pensionHolder(2_000_000), null, false);
    $withNoFund = app(IHTCalculationService::class)->calculate(pensionHolder(0), null, false);

    $grossMovement = $withFund['projected_gross_assets'] - $withNoFund['projected_gross_assets'];
    $cashMovement = $withFund['projected_cash'] - $withNoFund['projected_cash'];

    expect($withFund['projected_unused_pension'])->toBeGreaterThan(0.0)
        ->and(round($grossMovement - $cashMovement, 2))->toBe($withFund['projected_unused_pension']);
});

it('adds nothing when the modelled death falls before the configured effective date', function () {
    // The amendment is months away, so no living person's modelled death precedes it
    // any more. The clock is moved instead of the configuration: the branch is about
    // the date of death against the effective date, and that is what this exercises.
    // The date itself stays configuration (Rule 2) and is never restated here.
    $this->travelTo(Carbon\Carbon::parse('2015-01-01'));

    $user = User::factory()->create([
        'marital_status' => 'single',
        'date_of_birth' => '1925-01-01',
        'gender' => 'male',
    ]);
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 400_000]);

    $result = app(IHTCalculationService::class)->calculate($user->fresh(), null, false);

    expect($result['projected_unused_pension'])->toBe(0.0)
        ->and($result['projected_unused_pension_basis'])->toBe('before_effective_date');
});

it('no longer publishes the interim exclusion caveat', function () {
    $result = app(IHTCalculationService::class)->calculate(pensionHolder(400_000), null, false);

    // W-0482 acceptance 6 — the caveat is removed in the same change as its cause.
    expect($result)->not->toHaveKey('projected_pension_exclusion_caveat');
});
