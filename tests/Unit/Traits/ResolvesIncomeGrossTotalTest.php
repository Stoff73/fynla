<?php

declare(strict_types=1);

use App\Models\User;
use App\Traits\ResolvesIncome;

/**
 * Consolidation guard (jul6 audit, Fix 2): HouseholdPlanningService,
 * LifeStageService and PrerequisiteGateService each reimplemented the same
 * seven-column gross-income sum. They now all delegate to
 * ResolvesIncome::resolveGrossAnnualIncome. These tests pin that the shared
 * trait totals the identical seven columns with identical null handling, so the
 * switch changes no total.
 */

/** Anonymous harness exposing the protected trait method for assertion. */
function grossIncomeHarness(): object
{
    return new class
    {
        use ResolvesIncome;

        public function total(User $user): float
        {
            return $this->resolveGrossAnnualIncome($user);
        }
    };
}

it('sums all seven annual income columns', function () {
    $user = new User;
    $user->annual_employment_income = 50000;
    $user->annual_self_employment_income = 10000;
    $user->annual_rental_income = 8000;
    $user->annual_dividend_income = 3000;
    $user->annual_interest_income = 1500;
    $user->annual_other_income = 2000;
    $user->annual_trust_income = 500;

    $expected = (float) (50000 + 10000 + 8000 + 3000 + 1500 + 2000 + 500);

    expect(grossIncomeHarness()->total($user))->toBe($expected);
});

it('treats null income columns as zero', function () {
    $user = new User;
    $user->annual_employment_income = 40000;
    // The other six columns are left null.

    expect(grossIncomeHarness()->total($user))->toBe(40000.0);
});

it('returns zero when the user has no income at all', function () {
    expect(grossIncomeHarness()->total(new User))->toBe(0.0);
});
