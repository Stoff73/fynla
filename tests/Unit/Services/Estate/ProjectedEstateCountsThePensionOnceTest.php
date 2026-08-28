<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\Retirement\RetirementProjectionService;
use App\Services\Settings\AssumptionsService;
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
 * the income it becomes and once as the fund it came from.
 *
 * **The first version of this suite could not fail.** It asserted
 * `grossMovement - cashMovement === residual`, which is an identity: the estate is built
 * as `cash + ... + residual`, so it holds for ANY residual, including today's whole pot.
 * The `tax-compliance-reviewer` gate found it, and found the double count still live —
 * the residual came from a DEPLETING drawdown model while the cash projector pays a
 * PERPETUITY, so the pension was counted roughly twice for a household whose guaranteed
 * income already meets its target.
 *
 * What replaces it is the accounting complement: the estate adds the grown fund LESS the
 * income already credited to cash. The assertions below are the ones the old
 * implementation fails — that the residual is strictly less than the grown fund once
 * income has been credited, and zero once that income has exhausted it.
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

it('adds the grown fund less the income the cash projection already credited', function () {
    // The assertion the old implementation fails. A household whose guaranteed income
    // covers its target draws nothing in the drawdown model, so the previous version
    // returned the WHOLE grown pot — while `projected_cash` had separately been credited
    // 4% of that same pot every year since retirement. Here the credited income is
    // subtracted, so the residual is strictly less than the fund.
    $user = pensionHolder(500_000);

    // A defined benefit pension large enough that the drawdown model would draw nothing.
    DBPension::factory()->create([
        'user_id' => $user->id,
        'accrued_annual_pension' => 60_000,
    ]);

    $projection = app(RetirementProjectionService::class);
    $inflation = (float) app(AssumptionsService::class)->getEstateAssumptions($user)['inflation_rate'] / 100;
    $pot = $projection->projectPensionPot($user->fresh());

    $residual = $projection->unusedDcFundAtAge($user->fresh(), (int) $pot['retirement_age'] + 15, $inflation);

    expect($residual['credited'])->toBeGreaterThan(0.0)
        ->and($residual['grown_fund'])->toBeGreaterThan(0.0)
        ->and($residual['amount'])->toBeLessThan($residual['grown_fund'])
        // A delta, not an identity: these are floats off a compounding loop, and an exact
        // comparison fails by one unit in the last place depending on what ran before it.
        // A penny is the resolution that matters for money.
        ->and($residual['amount'])
        ->toEqualWithDelta(max(0.0, $residual['grown_fund'] - $residual['credited']), 0.01);
});

it('adds nothing once the credited income has exhausted the fund', function () {
    // Far enough past retirement that the income already counted in cash exceeds the
    // fund it came from. The estate adds nothing rather than a negative, and says so.
    $user = pensionHolder(400_000);
    $projection = app(RetirementProjectionService::class);
    $inflation = (float) app(AssumptionsService::class)->getEstateAssumptions($user)['inflation_rate'] / 100;
    $pot = $projection->projectPensionPot($user);

    $residual = $projection->unusedDcFundAtAge($user, (int) $pot['retirement_age'] + 45, $inflation);

    expect($residual['credited'])->toBeGreaterThan($residual['grown_fund'])
        ->and($residual['amount'])->toBe(0.0)
        ->and($residual['basis'])->toBe('exhausted');
});

it('adds the whole projected pot for a death before any income is credited', function () {
    // Before retirement the cash projection has credited nothing, so there is nothing to
    // subtract and the whole projected fund is unused.
    $user = pensionHolder(400_000);
    $projection = app(RetirementProjectionService::class);
    $inflation = (float) app(AssumptionsService::class)->getEstateAssumptions($user)['inflation_rate'] / 100;
    $pot = $projection->projectPensionPot($user);
    $ageBeforeRetirement = (int) $pot['retirement_age'] - 1;

    $residual = $projection->unusedDcFundAtAge($user, $ageBeforeRetirement, $inflation);
    $row = $pot['year_by_year'][$ageBeforeRetirement - (int) $pot['current_age']];

    expect($residual['basis'])->toBe('pre_retirement_growth')
        ->and($residual['credited'])->toBe(0.0)
        ->and($residual['amount'])->toBe((float) $row['percentile_20']);
});

it('publishes in the estate exactly what the complement computes', function () {
    $user = pensionHolder(400_000);

    $result = app(IHTCalculationService::class)->calculate($user, null, false);

    $inflation = (float) app(AssumptionsService::class)->getEstateAssumptions($user)['inflation_rate'] / 100;
    $ageAtDeath = Carbon\Carbon::parse($user->date_of_birth)->age + (int) $result['years_to_death'];
    $residual = app(RetirementProjectionService::class)->unusedDcFundAtAge($user, $ageAtDeath, $inflation);

    // One mechanism, read rather than re-derived (Rule 20).
    expect($result['projected_unused_pension'])->toBe(round($residual['amount'], 2))
        // And never the pot: a fund that has been paying an income for decades is not
        // its own starting value at death.
        ->and($result['projected_unused_pension'])->not->toBe(400000.00);
});

it('adds nothing for a household with no defined contribution pension', function () {
    $result = app(IHTCalculationService::class)->calculate(pensionHolder(0), null, false);

    expect($result['projected_unused_pension'])->toBe(0.0)
        ->and($result['projected_unused_pension_basis'])->toBe('no_pension');
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

it('says what the figure still does not include', function () {
    // `05-perimeter.md` §4 — the W-0363 caveat went with its cause, and the fix brought
    // new incompletenesses with it: defined benefit lump sum death benefits are not
    // modelled, the income tax due on a death at or after 75 is not modelled, and the
    // charge falls on whoever receives the pension rather than on the rest of the estate.
    // Raised by the tax-compliance-reviewer gate. One sentence, from the engine.
    $result = app(IHTCalculationService::class)->calculate(pensionHolder(400_000), null, false);

    expect($result['projected_pension_inclusion_caveat'])->toBeString()
        ->and($result['projected_pension_inclusion_caveat'])->toContain('defined benefit')
        ->and($result['projected_pension_inclusion_caveat'])->toContain('75')
        ->and($result['projected_pension_inclusion_caveat'])->toContain('whoever receives the pension');
});

it('says nothing to a household the figure is not about', function () {
    // A caveat shown to everyone is noise, and noise is what makes real ones ignored.
    $result = app(IHTCalculationService::class)->calculate(pensionHolder(0), null, false);

    expect($result['projected_pension_inclusion_caveat'])->toBeNull();
});

it('no longer publishes the interim exclusion caveat', function () {
    $result = app(IHTCalculationService::class)->calculate(pensionHolder(400_000), null, false);

    // W-0482 acceptance 6 — the caveat is removed in the same change as its cause.
    expect($result)->not->toHaveKey('projected_pension_exclusion_caveat');
});
