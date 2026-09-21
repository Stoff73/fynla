<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\TaxStrategyMath;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdCostCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->calc = app(ThresholdCostCalculator::class);
});

function contextFor(User $user): ThresholdContext
{
    return new ThresholdContext($user, app(IncomeDefinitionsService::class)->calculate($user->id));
}

it('prices a salary excess with Class 1 NI and the taper, as a delta of two full computations', function () {
    $user = User::factory()->create(['annual_employment_income' => 112400]);

    $cost = $this->calc->delta(contextFor($user), 12400.0);

    // £12,400 back under £100,000 by pension: 40% relief plus half the PA restored at 40%.
    expect($cost->incomeTax)->toBe(12400 * 0.40 + 6200 * 0.40)
        ->and($cost->niClass1)->toBe(0.0) // a pension contribution does not change NI
        ->and($cost->niClass4)->toBe(0.0)
        ->and($cost->total())->toBe($cost->incomeTax);
});

it('routes a self-employed excess to self-employment, never to employment', function () {
    $user = User::factory()->create(['annual_employment_income' => 0, 'annual_self_employment_income' => 108000]);
    $context = contextFor($user);

    // The NI figures cannot move under a pension contribution, so asserting they are
    // zero proves nothing about routing. What matters is that the income reaches the
    // calculator on the self-employment leg, which is the one that carries Class 4.
    expect($this->calc->mix($context)['self_employment'])->toBe(108000.0)
        ->and($this->calc->mix($context)['employment'])->toBe(0.0)
        ->and($this->calc->delta($context, 8000.0)->incomeTax)->toBeGreaterThan(8000 * 0.40);
});

it('attributes the dividend part of the delta to dividend tax', function () {
    $user = User::factory()->create(['annual_employment_income' => 90000, 'annual_dividend_income' => 20000]);

    $cost = $this->calc->delta(contextFor($user), 10000.0);

    expect($cost->dividendTax)->toBeGreaterThan(0.0)
        ->and($cost->total())->toBe(round($cost->incomeTax + $cost->dividendTax + $cost->interestTax + $cost->niClass1 + $cost->niClass4, 2));
});

it('removes interest from the mix when the mechanism is an ISA move', function () {
    $user = User::factory()->create(['annual_employment_income' => 98000, 'annual_interest_income' => 6000]);

    $cost = $this->calc->delta(contextFor($user), 4000.0, 'isa');

    expect($cost->interestTax)->toBeGreaterThan(0.0)->and($cost->total())->toBeGreaterThan(0.0);
});

it('routes a pension in payment to other income, which carries no NI', function () {
    $user = User::factory()->create(['annual_employment_income' => 0]);
    DBPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'NHS',
        'accrued_annual_pension' => 104000,
        'scheme_status' => 'in_payment',
    ]);
    $context = contextFor($user);

    // `otherIncome` is the leg the calculator charges no NI on, so routing the pension
    // there is what makes the pensioner NI-free — not an assertion that a pension
    // contribution failed to move a figure it cannot reach.
    $cost = $this->calc->delta($context, 4000.0);

    expect($this->calc->mix($context)['other'])->toBe(104000.0)
        ->and($this->calc->mix($context)['employment'])->toBe(0.0)
        ->and($cost->incomeTax)->toBeGreaterThan(0.0)
        // With no relevant earnings at all, the basic amount is the whole ceiling, so
        // £3,600 of the £4,000 applies. Without it the cost of this line would be nil
        // and the pensioner would be told a contribution they cannot make is free.
        ->and($cost->applied)->toBe(3600.0);
});

it('caps a pension contribution at relevant UK earnings and says how much it applied', function () {
    // A director paying themselves at the allowance and taking the rest as dividends.
    // Relief is limited to earnings from work (FA 2004 s190), so £32,570 cannot be
    // pensioned however much the dividends are worth.
    $user = User::factory()->create(['annual_employment_income' => 12570, 'annual_dividend_income' => 120000]);

    $cost = $this->calc->delta(contextFor($user), 32570.0);

    expect($cost->applied)->toBe(12570.0)
        ->and($cost->requested)->toBe(32570.0)
        ->and($cost->toArray()['applied'])->toBe(12570.0)
        ->and($cost->toArray()['requested'])->toBe(32570.0);
});

it('keeps requested and applied when a benefit is added', function () {
    $cost = new ThresholdCost(incomeTax: 500.0, requested: 9000.0, applied: 4000.0);

    $withBenefit = $cost->withBenefit('Child Benefit', 'Withdrawn at 1% per £200', 1000.0);

    expect($withBenefit->requested)->toBe(9000.0)
        ->and($withBenefit->applied)->toBe(4000.0)
        ->and($withBenefit->total())->toBe(1500.0);
});

it('takes sacrificed pay out of the employment figure when the recorded pay is gross', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 145000,
        'employment_income_basis' => 'gross',
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id,
        'annual_salary' => 145000,
        'employee_contribution_percent' => 20,
        'employer_contribution_percent' => 0,
        'salary_sacrifice' => true,
    ]);

    $context = contextFor($user);
    $mix = $this->calc->mix($context);

    expect($mix['employment'])->toBe(116000.0);

    // The point of the correction: the calculator's own view of adjusted net income
    // now agrees with the definitions service. Compared through the personal allowance
    // each figure earns, which is where a disagreement would cost the user money.
    $math = app(TaxStrategyMath::class);
    $published = $math->personalAllowanceForIncome($context->adjustedNetIncome());
    $fromMix = $math->personalAllowanceForIncome(
        $mix['employment'] + $mix['self_employment'] + $mix['rental'] + $mix['dividend'] + $mix['interest'] + $mix['other']
    );

    expect($fromMix)->toBe($published);
});

it('keys strategy recommendations by type, as the arrays the calculator publishes', function () {
    $user = User::factory()->create(['annual_employment_income' => 112400]);
    $context = contextFor($user);

    $strategies = $context->strategies();

    expect($strategies)->not->toBeEmpty();

    foreach ($strategies as $type => $recommendation) {
        expect($recommendation)->toBeArray()
            ->and($recommendation['type'])->toBe($type)
            ->and($type)->not->toBe('')
            // The keys a line reads. `toArray()` merges `extra` in flat, so a
            // strategy-specific field sits beside these rather than under them.
            ->and($recommendation)->toHaveKeys(['title', 'category', 'priority', 'estimated_annual_tax_saved'])
            ->and($context->strategy($type))->toBe($recommendation);
    }

    expect($context->strategy('nothing_is_registered_under_this_type'))->toBeNull();
});

it('adds a benefit to a cost and counts it in the total', function () {
    $cost = new ThresholdCost(incomeTax: 1000.0, niClass1: 200.0);

    $withBenefit = $cost->withBenefit('Child Benefit', 'Withdrawn at 1% per £200', 1331.20);

    expect($cost->total())->toBe(1200.0)
        ->and($withBenefit->total())->toBe(2531.20)
        ->and($withBenefit->benefits)->toBe([['label' => 'Child Benefit', 'detail' => 'Withdrawn at 1% per £200', 'amount' => 1331.20]])
        ->and($cost->withBenefit('Nothing', 'Zero is not a cost', 0.0))->toBe($cost)
        ->and($withBenefit->toArray()['total'])->toBe(2531.20);
});
