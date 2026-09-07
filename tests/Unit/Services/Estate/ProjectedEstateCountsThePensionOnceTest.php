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
        //
        // `retirement_age` and `investment_strategy` are pinned for the same reason, found
        // when this file went intermittently red: `DCPensionFactory` randomises the
        // retirement age across 60–68 and the strategy across five risk profiles, so the
        // LENGTH of retirement and the GROWTH RATE both moved between runs. With a
        // depleting drawdown that decides whether the fund is exhausted by the modelled
        // death — and therefore whether the caveat below is published at all. A fixture
        // that re-rolls the variable under test cannot hold a contract.
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_fund_value' => $fundValue,
            'monthly_contribution_amount' => 0,
            'employee_contribution_percent' => 0,
            'employer_contribution_percent' => 0,
            'retirement_age' => 65,
            'investment_strategy' => 'Balanced Growth',
        ]);
    }

    return $user->fresh();
}

it('keeps no growth on the pounds the drawdown already withdrew', function () {
    // W-0517, and the assertion the NOMINAL SUBTRACTION fails.
    //
    // The residual used to be `grown fund − Σ withdrawals at nominal value`. That grows
    // the fund as though nothing had been taken out of it and then removes the
    // withdrawals without their growth, so the estate keeps the return on pounds that
    // were withdrawn — pounds which, once in cash, earn nothing at all
    // (`HouseholdCashFlowProjector:171` is `$balance += $surplus`, no return applied).
    //
    // Carrying the fund forward year by year removes that growth with the pound, so the
    // residual must come out STRICTLY BELOW the nominal subtraction. Anything equal to it
    // is the old arithmetic; anything above it is worse than the old arithmetic.
    $user = pensionHolder(500_000);

    // A defined benefit pension large enough that a target-income model would draw
    // nothing from the pot — the shape that made the first version of this figure carry
    // the whole grown fund.
    DBPension::factory()->create([
        'user_id' => $user->id,
        'accrued_annual_pension' => 60_000,
    ]);

    $projection = app(RetirementProjectionService::class);
    $inflation = (float) app(AssumptionsService::class)->getEstateAssumptions($user)['inflation_rate'] / 100;
    $pot = $projection->projectPensionPot($user->fresh());

    $residual = $projection->unusedDcFundAtAge($user->fresh(), (int) $pot['retirement_age'] + 15, $inflation);

    $nominalSubtraction = max(0.0, $residual['grown_fund'] - $residual['credited']);

    expect($residual['credited'])->toBeGreaterThan(0.0)
        ->and($residual['grown_fund'])->toBeGreaterThan(0.0)
        // Still less than the untouched fund — the W-0482 property, which must not regress.
        ->and($residual['amount'])->toBeLessThan($residual['grown_fund'])
        // And less than the old subtraction, which is the W-0517 property.
        ->and($residual['amount'])->toBeLessThan($nominalSubtraction)
        ->and($residual['basis'])->toBe('fund_remaining_after_drawdown');
});

it('adds nothing once the drawdown has emptied the fund', function () {
    // Far enough past retirement that the withdrawals have exhausted the pot. The estate
    // adds nothing rather than a negative, and says so.
    //
    // W-0512 — `credited` is no longer allowed to exceed what the fund could pay. Under
    // the perpetuity it could, and did: the cash flow was credited an income the pension
    // did not hold. Here it is capped by the fund, so the meaningful assertion is that
    // the withdrawals stopped, not that they overran.
    $user = pensionHolder(400_000);
    $projection = app(RetirementProjectionService::class);
    $inflation = (float) app(AssumptionsService::class)->getEstateAssumptions($user)['inflation_rate'] / 100;
    $pot = $projection->projectPensionPot($user);

    $residual = $projection->unusedDcFundAtAge($user, (int) $pot['retirement_age'] + 45, $inflation);

    $drawdown = $projection->projectSafeWithdrawalDrawdown(
        $user,
        $pot,
        $inflation,
        (int) $pot['retirement_age'] + 45,
    );

    expect($residual['amount'])->toBe(0.0)
        ->and($residual['basis'])->toBe('exhausted')
        // The fund ran out at a modelled age rather than paying on for ever.
        ->and($drawdown['depletion_age'])->not->toBeNull()
        ->and($drawdown['depletion_age'])->toBeLessThanOrEqual((int) $pot['retirement_age'] + 45)
        // And the perpetuity is gone: the household was never credited more than the
        // pension held.
        ->and($residual['credited'])->toBeLessThanOrEqual($residual['grown_fund']);
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
