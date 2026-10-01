<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\SavingsAccount;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Investment\MonteCarloSimulator;
use App\Services\Onboarding\RecordEditForms;
use App\Services\Retirement\RetirementAgeResolver;
use App\Services\Retirement\RetirementDrawdownPosition;
use App\Services\Risk\RiskPreferenceService;
use App\Services\Shared\MonteCarloEngine;
use App\Services\Tax\TaxStrategyMath;
use Database\Seeders\ActuarialLifeTablesSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/**
 * The Retirement page showed a saver's view to someone drawing their pension:
 * "Years to go 1", "Retirement age 67", a required capital, and a pot that
 * ignored the income drawn (csjones, Pat: born 1958, retired 2020, £200,000
 * drawing £30,000). CSJ 2026-10-01: "Income + how long it lasts", for "anyone
 * drawing from a pension".
 * Spec: docs/superpowers/specs/2026-10-01-retirement-drawing-view-design.md
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-01');
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(ActuarialLifeTablesSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function drawer(array $user = [], array $pension = []): User
{
    $u = User::factory()->create($user + [
        'date_of_birth' => '1958-03-10',
        'employment_status' => 'retired',
        'retirement_date' => '2020-01-01',
        'gender' => 'male',
        'annual_employment_income' => 0,
        'marital_status' => 'single',
    ]);
    DCPension::factory()->create($pension + [
        'user_id' => $u->id,
        'scheme_name' => 'Aviva personal pension',
        'current_fund_value' => 200000,
        'annual_drawdown_income' => 30000,
        'has_flexibly_accessed' => true,
        'monthly_contribution_amount' => 0,
        'employee_contribution_percent' => 0,
        'employer_contribution_percent' => 0,
        'risk_preference' => null,
        'has_custom_risk' => false,
    ]);

    return $u->fresh();
}

describe('who gets the drawing view', function () {
    it('gives it to someone retired, past a retirement date, or drawing while working', function (): void {
        $position = app(RetirementDrawdownPosition::class);

        $retired = User::factory()->create(['employment_status' => 'retired']);
        $pastDate = User::factory()->create(['employment_status' => 'employed', 'retirement_date' => '2025-06-01']);
        $working = drawer(['employment_status' => 'employed', 'retirement_date' => null, 'annual_employment_income' => 40000]);
        $saver = User::factory()->create(['employment_status' => 'employed', 'retirement_date' => '2035-01-01']);
        DCPension::factory()->create(['user_id' => $saver->id, 'annual_drawdown_income' => null, 'has_flexibly_accessed' => false]);

        expect($position->isDrawing($retired->fresh()))->toBeTrue()
            ->and($position->isDrawing($pastDate->fresh()))->toBeTrue()
            ->and($position->isDrawing($working))->toBeTrue()
            ->and($position->isDrawing($saver->fresh()))->toBeFalse()
            ->and($position->for($saver->fresh()))->toBeNull();
    });
});

describe('Pat: retired 2020, £200,000 drawing £30,000', function () {
    it('shows the income, the tax on it and the missing State Pension', function (): void {
        $pat = drawer();
        $view = app(RetirementDrawdownPosition::class)->for($pat);

        // £30,000 of pension income: £12,570 allowance, £17,430 at 20% = £3,486.
        expect($view['retired_since'])->toBe(['date' => '2020-01-01', 'age' => 61])
            ->and($view['income']['lines'])->toHaveCount(1)
            ->and($view['income']['lines'][0]['label'])->toBe('Drawdown from Aviva personal pension')
            ->and($view['income']['total'])->toBe(30000.0)
            ->and($view['income']['income_tax'])->toBe(3486.0)
            ->and($view['income']['income_tax'])->toBe(round(app(TaxStrategyMath::class)->incomeTaxNow($pat), 2))
            ->and($view['income']['national_insurance'])->toBe(0.0)
            ->and($view['income']['take_home'])->toBe(26514.0)
            // Born March 1958: State Pension age 66, nothing recorded as received.
            ->and($view['income']['state_pension_status'])->toBe('missing');
    });

    it('draws the pot down from today, never below £0, and says when it runs out', function (): void {
        $pot = app(RetirementDrawdownPosition::class)->for(drawer())['pot'];

        expect($pot['value'])->toBe(200000.0)
            ->and($pot['drawing_per_year'])->toBe(30000.0)
            ->and($pot['current_age'])->toBe(68)
            ->and($pot['year_by_year'][0]['percentile_50'])->toBe(200000.0)
            ->and($pot['lasts_to_age']['middle'])->toBeInt()->toBeGreaterThan(68)->toBeLessThan(85)
            ->and($pot['lasts_to_age']['lower'])->toBeInt()->toBeLessThanOrEqual($pot['lasts_to_age']['middle']);
        foreach ($pot['year_by_year'] as $row) {
            expect($row['percentile_10'])->toBeGreaterThanOrEqual(0.0);
        }
        // The middle outcome is £0 from the year it runs out.
        $out = collect($pot['year_by_year'])->firstWhere('year_number', $pot['lasts_to_age']['middle'] - 68);
        expect($out['percentile_50'])->toBe(0.0);
    });

    it('works out the income that lasts to life expectancy in 4 out of 5 outcomes', function (): void {
        $pot = app(RetirementDrawdownPosition::class)->for(drawer())['pot'];
        $income = $pot['income_to_last_to_life_expectancy'];

        // ONS tables: a man of 68 has about 17 years left.
        expect($pot['life_expectancy']['source'])->toBe('ons')
            ->and($pot['life_expectancy']['age'])->toBeGreaterThan(80)
            ->and($income)->toBeGreaterThan(0.0)->toBeLessThan(30000.0)
            ->and(fmod($income, 100.0))->toBe(0.0);

        // At that income the lower outcome lasts; £200 more a year and it does not.
        $years = $pot['life_expectancy']['age'] - 68;
        $lower = function (float $draw) use ($years): float {
            $sim = app(MonteCarloSimulator::class);
            $params = app(RiskPreferenceService::class)->getReturnParameters('medium');
            $bands = $sim->extractProbabilityBands($sim->simulate(200000, -$draw / 12, $params['expected_return_typical'] / 100, $params['volatility'] / 100, $years, 1000, null, [], MonteCarloEngine::BAND_PERCENTILES));

            return (float) end($bands)['percentile_20'];
        };
        expect($lower($income))->toBeGreaterThan(0.0)
            ->and($lower($income + 200))->toBe(0.0);
    });
});

it('says a pot lasts beyond the horizon when it does', function (): void {
    $pot = app(RetirementDrawdownPosition::class)->for(drawer([], ['annual_drawdown_income' => 2000]))['pot'];

    expect($pot['lasts_to_age'])->toBe(['middle' => null, 'lower' => null]);
});

it('counts pay and National Insurance for someone still working who draws', function (): void {
    $view = app(RetirementDrawdownPosition::class)->for(
        drawer(['employment_status' => 'employed', 'retirement_date' => null, 'annual_employment_income' => 40000, 'date_of_birth' => '1964-05-01'])
    );

    expect(collect($view['income']['lines'])->pluck('key')->all())->toContain('employment')
        ->and($view['income']['total'])->toBe(70000.0)
        ->and($view['income']['national_insurance'])->toBeGreaterThan(0.0)
        ->and($view['retired_since'])->toBeNull();
});

it('takes the retirement age of someone already retired from their retirement date', function (): void {
    $pat = drawer();
    $pat->forceFill(['target_retirement_age' => 67])->save();

    expect(app(RetirementAgeResolver::class)->withSource($pat->fresh()))
        ->toBe(['age' => 61, 'source' => RetirementAgeResolver::SOURCE_RETIREMENT_DATE]);
});

it('serves the view in the projections response, and null for a saver', function (): void {
    $pat = drawer();
    Sanctum::actingAs($pat);
    $data = $this->getJson('/api/retirement/projections')->assertOk()->json('data');

    expect($data['drawdown_position']['income']['take_home'])->toEqual(26514)
        ->and($data['drawdown_position']['pot']['drawing_per_year'])->toEqual(30000);

    $saver = User::factory()->create(['employment_status' => 'employed', 'date_of_birth' => '1985-01-01']);
    Sanctum::actingAs($saver);
    expect($this->getJson('/api/retirement/projections')->assertOk()->json('data.drawdown_position'))->toBeNull();
});

it('records from the web form that the State Pension is being paid, and counts it', function (): void {
    $pat = drawer();
    Sanctum::actingAs($pat);

    $this->postJson('/api/retirement/state-pension', [
        'ni_years_completed' => 35, 'ni_years_required' => 35,
        'state_pension_forecast_annual' => 11502.4, 'already_receiving' => true,
    ])->assertOk();

    $income = app(RetirementDrawdownPosition::class)->for($pat->fresh())['income'];
    expect(collect($income['lines'])->firstWhere('key', 'state_pension')['amount'])->toBe(11502.4)
        ->and($income['state_pension_status'])->toBe('paid')
        ->and($income['total'])->toBe(41502.4);
});

it('says a recorded State Pension is not recorded as being paid, and leaves it out of income', function (): void {
    $pat = drawer();
    StatePension::create(['user_id' => $pat->id, 'state_pension_forecast_annual' => 11502]);

    $income = app(RetirementDrawdownPosition::class)->for($pat->fresh())['income'];

    expect($income['state_pension_status'])->toBe('not_paid')
        ->and(collect($income['lines'])->pluck('key')->all())->not->toContain('state_pension')
        ->and($income['total'])->toBe(30000.0);
});

it('has no State Pension status before State Pension age', function (): void {
    $view = app(RetirementDrawdownPosition::class)->for(drawer(['date_of_birth' => '1966-01-01', 'employment_status' => 'retired', 'retirement_date' => '2025-01-01']));

    expect($view['income']['state_pension_status'])->toBeNull();
});

describe('tax review fixes (2026-10-01)', function () {
    it('charges no National Insurance on pay past State Pension age (SSCBA 1992 s6(3))', function (): void {
        // Born 1958: State Pension age 66, so 68 today. £30,000 pay and £20,000 drawn.
        $view = app(RetirementDrawdownPosition::class)->for(
            drawer(['employment_status' => 'employed', 'retirement_date' => null, 'annual_employment_income' => 30000], ['annual_drawdown_income' => 20000])
        );

        expect($view['income']['national_insurance'])->toBe(0.0)
            ->and($view['income']['take_home'])->toBe($view['income']['total'] - $view['income']['income_tax']);
    });

    it('adds up the lines to the income the tax is worked out on, estimated interest included', function (): void {
        $pat = drawer();
        SavingsAccount::factory()->create([
            'user_id' => $pat->id, 'current_balance' => 100000, 'interest_rate' => 4.0, 'is_isa' => false,
            'ownership_type' => 'individual', 'joint_owner_id' => null,
        ]);
        $pat = $pat->fresh();
        $parts = app(TaxStrategyMath::class)->incomePartsFor($pat);
        $income = app(RetirementDrawdownPosition::class)->for($pat)['income'];

        expect(collect($income['lines'])->firstWhere('key', 'interest')['amount'])->toEqualWithDelta($parts['interest'], 0.01)
            ->and($income['total'])->toEqualWithDelta($parts['non_savings'] + $parts['interest'] + $parts['dividends'] + $parts['trust'], 0.01);
    });

    it('extends the band for Gift Aid rather than taking it off income (ITA 2007 s414)', function (): void {
        // £30,000 drawn, £1,000 given with Gift Aid (£1,250 gross): still £3,486 tax,
        // since the charity claims the basic rate and the bands only move up.
        $pat = drawer();
        $pat->forceFill(['annual_charitable_donations' => 1000, 'is_gift_aid' => true])->save();

        expect(app(RetirementDrawdownPosition::class)->for($pat->fresh())['income']['income_tax'])->toBe(3486.0);
    });

    it('asks for the amount of a State Pension marked as paid without one', function (): void {
        $pat = drawer();
        StatePension::create(['user_id' => $pat->id, 'already_receiving' => true]);

        expect(app(RetirementDrawdownPosition::class)->for($pat->fresh())['income']['state_pension_status'])->toBe('no_amount');
    });

    it('leaves "is it being paid?" unanswered on the edit form unless it is known', function (): void {
        $pat = drawer();
        StatePension::create(['user_id' => $pat->id, 'state_pension_forecast_annual' => 11502.4]);
        $form = app(RecordEditForms::class)->formForResource($pat->fresh(), 'state_pension');

        expect($form['answers']['_lead'])->not->toHaveKey('already_receiving')
            ->and((float) $form['answers']['_lead']['forecast_annual'])->toBe(11502.4);
    });
});
