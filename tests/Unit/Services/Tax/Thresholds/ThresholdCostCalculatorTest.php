<?php

declare(strict_types=1);

use App\DataTransferObjects\StrategyRecommendation;
use App\Models\DBPension;
use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\TaxStrategyCalculator;
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

it('prices a self-employed excess without Class 1', function () {
    $user = User::factory()->create(['annual_employment_income' => 0, 'annual_self_employment_income' => 108000]);

    $cost = $this->calc->delta(contextFor($user), 8000.0);

    expect($cost->niClass1)->toBe(0.0)
        ->and($cost->incomeTax)->toBeGreaterThan(8000 * 0.40);
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

it('prices a pensioner with no NI at all', function () {
    $user = User::factory()->create(['annual_employment_income' => 0]);
    DBPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'NHS',
        'accrued_annual_pension' => 104000,
        'scheme_status' => 'in_payment',
    ]);

    $cost = $this->calc->delta(contextFor($user), 4000.0);

    expect($cost->niClass1)->toBe(0.0)->and($cost->niClass4)->toBe(0.0)->and($cost->incomeTax)->toBeGreaterThan(0.0);
});

it('keys strategy recommendations by type as typed objects', function () {
    $user = User::factory()->create(['annual_employment_income' => 112400]);

    $strategies = contextFor($user)->strategies();

    expect($strategies)->not->toBeEmpty();

    foreach ($strategies as $type => $recommendation) {
        expect($recommendation)->toBeInstanceOf(StrategyRecommendation::class)
            ->and($recommendation->type)->toBe($type)
            ->and($type)->not->toBe('');
    }
});

it('round-trips a recommendation through the context without losing its extras', function () {
    $user = User::factory()->create(['annual_employment_income' => 112400]);
    $context = contextFor($user);

    $published = collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->keyBy('type');

    foreach ($context->strategies() as $type => $recommendation) {
        expect($recommendation->toArray())->toBe($published[$type]);
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
