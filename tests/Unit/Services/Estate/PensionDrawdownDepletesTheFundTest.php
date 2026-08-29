<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Estate\HouseholdCashFlowProjector;
use App\Services\Retirement\RetirementProjectionService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0512 and W-0517 — the pension is paid from a fund that shrinks.
 *
 * The cash flow used to credit `pot × safe withdrawal rate` every retired year, inflated,
 * out of a fund nothing ever reduced. Thirty years or more of an income the pension could
 * not have paid, accumulating into `final_cash` and from there into the projected estate
 * (W-0512). The estate's own unused-fund figure then subtracted those withdrawals at their
 * NOMINAL value from a fund grown as though untouched, so the growth on withdrawn pounds
 * stayed in the estate (W-0517).
 *
 * `RetirementProjectionService::projectSafeWithdrawalDrawdown()` is the one mechanism both
 * ends now read: the cash flow takes `income_by_age`, the estate takes `fund_by_age`.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function drawdownHolder(float $fundValue, int $currentAge = 45, int $retirementAge = 65): User
{
    $user = User::factory()->create([
        'date_of_birth' => now()->subYears($currentAge)->format('Y-m-d'),
        'gender' => 'male',
        'marital_status' => 'single',
        'target_retirement_age' => $retirementAge,
    ]);

    DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => $fundValue,
        'monthly_contribution_amount' => 0,
        'employee_contribution_percent' => 0,
        'employer_contribution_percent' => 0,
        'retirement_age' => $retirementAge,
    ]);

    return $user->fresh();
}

it('stops paying the pension once the drawdown has emptied the fund', function () {
    // The assertion the perpetuity fails. A pot drawn at the safe withdrawal rate,
    // uprated with inflation and growing at the conservative rate, runs out — and after
    // that the household lives on whatever else it has. Under the old scalar the income
    // never stopped, because nothing was ever taken out of the fund.
    $user = drawdownHolder(300_000);

    $projection = app(RetirementProjectionService::class);
    $pot = $projection->projectPensionPot($user);

    $drawdown = $projection->projectSafeWithdrawalDrawdown($user, $pot, 0.025, 110);

    expect($drawdown['depletion_age'])->not->toBeNull();

    $afterDepletion = array_filter(
        $drawdown['income_by_age'],
        fn (int $age) => $age > $drawdown['depletion_age'],
        ARRAY_FILTER_USE_KEY
    );

    expect($afterDepletion)->not->toBeEmpty()
        ->and(array_sum($afterDepletion))->toBe(0.0)
        // And the fund is genuinely gone rather than left holding a year's growth it
        // could never have paid out.
        ->and($drawdown['fund_by_age'][$drawdown['depletion_age']])->toBe(0.0);
});

it('never pays out more than the fund can fund', function () {
    // The defining property of W-0512: the total withdrawn cannot exceed the pot plus the
    // growth it earned while it was still being held. A perpetuity can and does.
    $user = drawdownHolder(300_000);

    $projection = app(RetirementProjectionService::class);
    $pot = $projection->projectPensionPot($user);
    $drawdown = $projection->projectSafeWithdrawalDrawdown($user, $pot, 0.025, 110);

    $totalDrawn = array_sum($drawdown['income_by_age']);
    $years = count($drawdown['income_by_age']);

    // The upper bound: the whole pot compounded for the entire drawdown, which is what it
    // would be worth if nothing were withdrawn until the very last year.
    $mostItCouldEverPay = (float) $pot['percentile_20_at_retirement']
        * pow(1 + $drawdown['growth_rate'], $years);

    expect($totalDrawn)->toBeGreaterThan(0.0)
        ->and($totalDrawn)->toBeLessThan($mostItCouldEverPay);
});

it('credits the household cash flow from that same drawdown, not a flat figure', function () {
    // Rule 20 — one mechanism. The cash flow's retired-year income must fall when the
    // drawdown says the fund has run out. A flat scalar cannot fall.
    $user = drawdownHolder(300_000);

    $projection = app(RetirementProjectionService::class);
    $pot = $projection->projectPensionPot($user);
    $drawdown = $projection->projectSafeWithdrawalDrawdown($user, $pot, 0.025, 110);

    $currentAge = 45;
    $depletionYear = $drawdown['depletion_age'] - $currentAge;

    $cashFlow = app(HouseholdCashFlowProjector::class)
        ->project($user, null, false, $depletionYear + 5, 0.025);

    $rows = collect($cashFlow['years'])->keyBy('year');

    // A retired year while the fund was still paying, against a retired year after it ran
    // out. Both are in the same phase, so the only thing that can have changed is the
    // pension.
    $whilePaying = $rows[$depletionYear - 1];
    $afterEmpty = $rows[$depletionYear + 1];

    expect($whilePaying['phase'])->toBe('Retired')
        ->and($afterEmpty['phase'])->toBe('Retired')
        ->and($afterEmpty['income'])->toBeLessThan($whilePaying['income']);
});
