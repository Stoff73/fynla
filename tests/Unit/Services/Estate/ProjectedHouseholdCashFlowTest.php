<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\ExpenditureProfile;
use App\Models\RetirementProfile;
use App\Models\SavingsAccount;
use App\Models\StatePension;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\HouseholdCashFlowProjector;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\IHTFormattingService;
use App\Services\Retirement\PensionProjector;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0137 and W-0188 — the estate projection produced money that cannot exist, and
 * produced a different amount of it depending on who signed in.
 *
 * **Everything here is built from fixtures.** The persona household (users 16 and 17)
 * is held read-only by a test run and its figures move.
 *
 * **These tests deliberately do not assert numbers the code was told.** A test that
 * mocks the same wrong key the code asks for agrees with the code, disagrees with
 * reality, and stays green forever — that is how `inheritance_tax.rate` survived. So
 * where the job is to prove a value is read from configuration, the configuration is
 * VARIED and the answer is required to follow; where the job is to prove an input is
 * read at all, the input is ADDED and the answer is required to move. A literal does
 * not move, whatever a double happens to return.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(IHTCalculationService::class);
    $this->projector = app(HouseholdCashFlowProjector::class);
});

/**
 * A married household whose two members are DIFFERENT AGES, which is the condition
 * the per-login divergence needed: the horizon was taken in the longer-lived spouse's
 * age frame and then walked from the viewer's age, so it was short by the age gap for
 * exactly one of them.
 *
 * No investment accounts, so nothing here depends on a Monte Carlo simulation.
 *
 * @return array{0: User, 1: User}
 */
function cashFlowHousehold(array $userOverrides = [], array $spouseOverrides = []): array
{
    $david = User::factory()->create(array_merge([
        'first_name' => 'David',
        'marital_status' => 'married',
        'date_of_birth' => '1976-11-08',
        'gender' => 'male',
        'target_retirement_age' => 60,
        'annual_employment_income' => 145_000,
        'annual_self_employment_income' => 0,
        'annual_rental_income' => 0,
        'annual_dividend_income' => 0,
        'annual_interest_income' => 0,
        'annual_other_income' => 0,
        'annual_trust_income' => 0,
        'life_expectancy_override' => null,
    ], $userOverrides));

    $sarah = User::factory()->create(array_merge([
        'first_name' => 'Sarah',
        'marital_status' => 'married',
        'date_of_birth' => '1981-04-22',
        'gender' => 'female',
        'target_retirement_age' => 60,
        'annual_employment_income' => 120_000,
        'annual_self_employment_income' => 0,
        'annual_rental_income' => 0,
        'annual_dividend_income' => 0,
        'annual_interest_income' => 0,
        'annual_other_income' => 0,
        'annual_trust_income' => 0,
        'life_expectancy_override' => null,
        'spouse_id' => $david->id,
    ], $spouseOverrides));

    $david->update(['spouse_id' => $sarah->id]);

    SavingsAccount::factory()->create([
        'user_id' => $david->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 25_000,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $sarah->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 22_500,
    ]);

    return [$david->fresh(), $sarah->fresh()];
}

/**
 * The same household, living within its means: both members record what they spend,
 * and it is modest against their income. Used where the projected cash balance itself
 * has to move, since a household on the floor reports zero however the inputs change.
 *
 * @return array{0: User, 1: User}
 */
function solventCashFlowHousehold(): array
{
    [$david, $sarah] = cashFlowHousehold();

    ExpenditureProfile::factory()->create([
        'user_id' => $david->id,
        'total_monthly_expenditure' => 2_450,
    ]);
    ExpenditureProfile::factory()->create([
        'user_id' => $sarah->id,
        'total_monthly_expenditure' => 1_500,
    ]);

    return [$david->fresh(), $sarah->fresh()];
}

/**
 * A household modelled to spend far more in retirement than it can fund, so the
 * shortfall path is exercised rather than described.
 *
 * @return array{0: User, 1: User}
 */
function overspendingCashFlowHousehold(): array
{
    [$david, $sarah] = cashFlowHousehold();

    // A recorded retirement budget the household's income cannot support, and no
    // pensions of any kind to meet it.
    foreach ([$david, $sarah] as $member) {
        RetirementProfile::factory()->create([
            'user_id' => $member->id,
            'target_retirement_age' => 60,
            'target_retirement_income' => 150_000,
            'essential_expenditure' => 90_000,
            'lifestyle_expenditure' => 60_000,
        ]);
    }

    return [$david->fresh(), $sarah->fresh()];
}

/** Re-resolve every service after mutating the active tax configuration. */
function reloadCashFlowTaxConfiguration(): void
{
    app(TaxConfigService::class)->clearCache();
    app()->forgetInstance(TaxConfigService::class);
    app()->forgetInstance(HouseholdCashFlowProjector::class);
    app()->forgetInstance(IHTCalculationService::class);
}

/** Overwrite one dot-notation key in the active tax configuration. */
function setCashFlowTaxConfigValue(string $key, mixed $value): void
{
    $row = TaxConfiguration::where('is_active', true)->firstOrFail();
    $data = $row->config_data;
    data_set($data, $key, $value);
    $row->update(['config_data' => $data]);

    reloadCashFlowTaxConfiguration();
}

describe('W-0137 — the projection cannot produce money that does not exist', function () {
    it('never projects a negative cash balance, however large the shortfall', function () {
        [$david, $sarah] = overspendingCashFlowHousehold();

        $result = $this->service->calculate($david, $sarah, true);

        expect($result['projected_cash'])->toBeGreaterThanOrEqual(0.0);

        // A Cash ISA projected to minus £854,179 was the reported symptom, and it was
        // produced by multiplying every account by a negative household factor. The
        // floor is on the household total, so it closes both.
        $breakdown = app(IHTFormattingService::class)
            ->generateCashProjectionBreakdown($david, $sarah, true, $result);

        foreach ($breakdown['years'] as $row) {
            expect($row['running_total'])->toBeGreaterThanOrEqual(0);
        }
    });

    it('reports unmet expenditure as a positive shortfall rather than negative money', function () {
        [$david, $sarah] = overspendingCashFlowHousehold();

        $result = $this->service->calculate($david, $sarah, true);

        expect($result['projected_cash'])->toBe(0.0)
            ->and($result['projected_cash_shortfall'])->toBeGreaterThan(0.0);
    });

    it('grows the shortfall when the spending that causes it grows', function () {
        [$david, $sarah] = overspendingCashFlowHousehold();

        $modest = $this->service->calculate($david, $sarah, true)['projected_cash_shortfall'];

        $david->retirementProfile->update(['lifestyle_expenditure' => 160_000]);

        $larger = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_cash_shortfall'];

        expect($larger)->toBeGreaterThan($modest);
    });

    it('leaves no shortfall for a household that lives within its means', function () {
        [$david, $sarah] = solventCashFlowHousehold();

        $result = $this->service->calculate($david, $sarah, true);

        expect($result['projected_cash_shortfall'])->toBe(0.0)
            ->and($result['projected_cash'])->toBeGreaterThan(0.0);
    });
});

describe('W-0188 — one household, one projection, whoever signs in', function () {
    it('projects the same cash, estate and tax from either spouse login', function () {
        [$david, $sarah] = solventCashFlowHousehold();

        $fromDavid = $this->service->calculate($david, $sarah, true);
        $fromSarah = $this->service->calculate($sarah, $david, true);

        expect($fromDavid['projected_cash'])->toBe($fromSarah['projected_cash'])
            ->and($fromDavid['projected_cash_shortfall'])->toBe($fromSarah['projected_cash_shortfall'])
            ->and($fromDavid['projected_gross_assets'])->toBe($fromSarah['projected_gross_assets'])
            ->and($fromDavid['projected_net_estate'])->toBe($fromSarah['projected_net_estate'])
            ->and($fromDavid['projected_taxable_estate'])->toBe($fromSarah['projected_taxable_estate'])
            ->and($fromDavid['projected_iht_liability'])->toBe($fromSarah['projected_iht_liability']);
    });

    it('projects the same household over the same number of years from now', function () {
        [$david, $sarah] = cashFlowHousehold();

        $fromDavid = $this->service->calculate($david, $sarah, true);
        $fromSarah = $this->service->calculate($sarah, $david, true);

        // The horizon is a property of the household and must match...
        expect($fromDavid['years_to_death'])->toBe($fromSarah['years_to_death']);

        // ...while the age each of them reaches at that horizon must NOT, because they
        // are not the same age. The old code showed both of them the same age, which
        // is how a viewer-relative loop and a spouse-relative death age were mistaken
        // for one model.
        expect($fromDavid['estimated_age_at_death'])->not->toBe($fromSarah['estimated_age_at_death']);
    });

    it('keeps the two logins together as the household grows', function () {
        [$david, $sarah] = cashFlowHousehold();

        // The divergence was proportional, not fixed: £88,257 when the household was
        // one-third entered and £103,206 when it was complete. Adding assets and
        // income must not reopen it.
        SavingsAccount::factory()->create([
            'user_id' => $sarah->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 90_000,
        ]);
        $david->update(['annual_rental_income' => 40_000]);

        $fromDavid = app(IHTCalculationService::class)->calculate($david->fresh(), $sarah->fresh(), true);
        $fromSarah = app(IHTCalculationService::class)->calculate($sarah->fresh(), $david->fresh(), true);

        expect($fromDavid['projected_net_estate'])->toBe($fromSarah['projected_net_estate']);
    });

    it('does not disturb the current-year column', function () {
        [$david, $sarah] = cashFlowHousehold();

        $fromDavid = $this->service->calculate($david, $sarah, true);
        $fromSarah = $this->service->calculate($sarah, $david, true);

        // £47,500 of savings, no other current assets, no liabilities — well inside
        // the allowances, so no tax today. Regression guard for W-0188 acceptance 2.
        expect($fromDavid['total_net_estate'])->toBe(47_500.0)
            ->and($fromSarah['total_net_estate'])->toBe(47_500.0)
            ->and($fromDavid['iht_liability'])->toBe($fromSarah['iht_liability']);
    });
});

describe('R1 — a pension with a recorded value reaches the projection', function () {
    /**
     * These read the SHORTFALL rather than the cash balance. The household is
     * modelled to outspend its means, so its cash sits on the floor at zero and
     * cannot move; the unmet expenditure above the floor can, and it is the same
     * fact measured where it is still visible.
     */
    it('reduces the shortfall when a Defined Contribution pot is recorded', function () {
        [$david, $sarah] = cashFlowHousehold();

        $without = $this->service->calculate($david, $sarah, true)['projected_cash_shortfall'];

        DCPension::factory()->create([
            'user_id' => $david->id,
            'current_fund_value' => 500_000,
            'monthly_contribution_amount' => 0,
            'annual_salary' => 0,
            'employee_contribution_percent' => 0,
            'employer_contribution_percent' => 0,
            'platform_fee_percent' => 0,
            'advisor_fee_percent' => 0,
            'retirement_age' => 60,
        ]);

        $with = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_cash_shortfall'];

        // The estate read `retirement_profiles.target_retirement_income` and nothing
        // else, so half a million pounds of pension contributed exactly zero. This
        // test cannot pass against that code however the figures are chosen.
        expect($without)->toBeGreaterThan(0.0)
            ->and($with)->toBeLessThan($without);
    });

    it('reduces the shortfall when a Defined Benefit pension is recorded', function () {
        [$david, $sarah] = cashFlowHousehold();

        $without = $this->service->calculate($david, $sarah, true)['projected_cash_shortfall'];

        DBPension::factory()->create([
            'user_id' => $sarah->id,
            'accrued_annual_pension' => 35_000,
            'normal_retirement_age' => 60,
            'inflation_protection' => 'none',
        ]);

        $with = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_cash_shortfall'];

        expect($with)->toBeLessThan($without);
    });

    it('follows the size of the pot, not merely its presence', function () {
        [$david, $sarah] = cashFlowHousehold();

        $pension = DCPension::factory()->create([
            'user_id' => $david->id,
            'current_fund_value' => 200_000,
            'monthly_contribution_amount' => 0,
            'annual_salary' => 0,
            'employee_contribution_percent' => 0,
            'employer_contribution_percent' => 0,
            'platform_fee_percent' => 0,
            'advisor_fee_percent' => 0,
            'retirement_age' => 60,
        ]);

        $smaller = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_cash_shortfall'];

        $pension->update(['current_fund_value' => 800_000]);

        $larger = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_cash_shortfall'];

        expect($larger)->toBeLessThan($smaller);
    });

    it('carries a pension through to the projected estate of a solvent household', function () {
        [$david, $sarah] = solventCashFlowHousehold();

        $without = $this->service->calculate($david, $sarah, true)['projected_net_estate'];

        DCPension::factory()->create([
            'user_id' => $david->id,
            'current_fund_value' => 500_000,
            'monthly_contribution_amount' => 0,
            'annual_salary' => 0,
            'employee_contribution_percent' => 0,
            'employer_contribution_percent' => 0,
            'platform_fee_percent' => 0,
            'advisor_fee_percent' => 0,
            'retirement_age' => 60,
        ]);

        $with = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_net_estate'];

        // Pension pots are outside the estate; the income drawn from them is not, once
        // it lands in cash the household does not spend.
        expect($with)->toBeGreaterThan($without);
    });
});

describe('R2 — the State Pension is read from the column that exists', function () {
    it('reduces the shortfall when a State Pension forecast is recorded', function () {
        [$david, $sarah] = cashFlowHousehold();

        $without = $this->service->calculate($david, $sarah, true)['projected_cash_shortfall'];

        StatePension::factory()->create([
            'user_id' => $david->id,
            'state_pension_forecast_annual' => 11_973,
            'state_pension_age' => 67,
            'already_receiving' => false,
        ]);

        $with = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_cash_shortfall'];

        // `state_pensions.estimated_annual_amount` has never existed, so this was
        // always zero for every user in the application.
        expect($with)->toBeLessThan($without);
    });

    it('pays the State Pension from the recorded State Pension age, not a literal', function () {
        [$david, $sarah] = cashFlowHousehold();

        $statePension = StatePension::factory()->create([
            'user_id' => $david->id,
            'state_pension_forecast_annual' => 11_973,
            'state_pension_age' => 80,
            'already_receiving' => false,
        ]);

        $late = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_cash_shortfall'];

        $statePension->update(['state_pension_age' => 66]);

        $early = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_cash_shortfall'];

        // `users.state_pension_age` does not exist either, so the recorded age on the
        // record that does exist was never consulted. Fourteen more years of it must
        // leave less unmet.
        expect($early)->toBeLessThan($late);
    });

    it('names the absence rather than publishing an unrecorded figure as zero', function () {
        [$david, $sarah] = cashFlowHousehold();

        $result = $this->service->calculate($david, $sarah, true);

        $mentionsStatePension = collect($result['projected_cash_assumptions'])
            ->contains(fn (string $line): bool => str_contains($line, 'State Pension'));

        expect($mentionsStatePension)->toBeTrue();

        StatePension::factory()->create([
            'user_id' => $david->id,
            'state_pension_forecast_annual' => 11_973,
            'state_pension_age' => 67,
        ]);
        StatePension::factory()->create([
            'user_id' => $sarah->id,
            'state_pension_forecast_annual' => 11_973,
            'state_pension_age' => 67,
        ]);

        $recorded = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true);

        $stillMentions = collect($recorded['projected_cash_assumptions'])
            ->contains(fn (string $line): bool => str_contains($line, 'State Pension'));

        expect($stillMentions)->toBeFalse();
    });
});

describe('R5 — retirement spending follows configuration, not a literal', function () {
    it('moves with retirement.target_income_percent', function () {
        [$david, $sarah] = cashFlowHousehold();

        // Neither member has recorded expenditure or a retirement budget, so the
        // configured ratio is the rule in force.
        setCashFlowTaxConfigValue('retirement.target_income_percent', 0.20);
        $frugal = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_cash_shortfall'];

        setCashFlowTaxConfigValue('retirement.target_income_percent', 0.95);
        $lavish = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_cash_shortfall'];

        // Spending 95% of income in retirement must leave more unmet than spending
        // 20%. A hardcoded 0.50 cannot produce two different answers here, which is
        // the whole point of varying the input rather than asserting the output.
        expect($lavish)->toBeGreaterThan($frugal);
    });

    it('prefers what a household actually spends over a share of what it earns', function () {
        [$david, $sarah] = cashFlowHousehold();

        $assumed = $this->service->calculate($david, $sarah, true)['projected_cash_shortfall'];

        // David records spending £29,400 a year. Keying his retirement spending to his
        // income instead projected him at £216,127 a year — seven times his own stated
        // figure — and reported the contradiction as his financial future.
        ExpenditureProfile::factory()->create([
            'user_id' => $david->id,
            'total_monthly_expenditure' => 2_450,
        ]);

        $recorded = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_cash_shortfall'];

        expect($recorded)->toBeLessThan($assumed);
    });
});

describe('R6 — one retirement age default across the modules that share it', function () {
    it('assumes the same age the pension module projects to', function () {
        $user = User::factory()->create([
            'date_of_birth' => '1980-01-01',
            'gender' => 'male',
            'target_retirement_age' => null,
        ]);

        // The estate assumed 68 while the pension projector and the pension model were
        // deliberately aligned on 67, so a pension could count as income from one age
        // while being projected forward from another. The requirement is the
        // AGREEMENT, so that is what is asserted.
        expect($this->projector->retirementAgeFor($user))
            ->toBe(PensionProjector::DEFAULT_RETIREMENT_AGE)
            ->toBe(DBPension::DEFAULT_NORMAL_RETIREMENT_AGE);
    });

    it('still prefers an age the user has actually chosen', function () {
        $user = User::factory()->create(['target_retirement_age' => 55]);

        expect($this->projector->retirementAgeFor($user))->toBe(55);
    });
});

describe('R7 — the estate can see the life expectancy the user gave it', function () {
    it('moves the horizon when the user states a longer life', function () {
        [$david, $sarah] = cashFlowHousehold();

        $actuarial = $this->service->calculate($david, $sarah, true)['years_to_death'];

        $david->update(['life_expectancy_override' => 105]);
        $sarah->update(['life_expectancy_override' => 105]);

        $stated = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['years_to_death'];

        // `getLifeExpectancyYears(int $age, string $gender)` never receives the user
        // and therefore cannot see the override however it is written. Retirement and
        // decumulation honoured it; the inheritance tax projection could not.
        expect($stated)->toBeGreaterThan($actuarial);
    });

    it('moves the projected estate with it, not merely the reported horizon', function () {
        [$david, $sarah] = solventCashFlowHousehold();

        $short = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_net_estate'];

        $david->update(['life_expectancy_override' => 105]);
        $sarah->update(['life_expectancy_override' => 105]);

        $long = app(IHTCalculationService::class)
            ->calculate($david->fresh(), $sarah->fresh(), true)['projected_net_estate'];

        expect($long)->not->toBe($short);
    });
});

describe('Rule 20 — the table beneath the headline explains the headline', function () {
    it('ends the year-by-year projection on the figure the estate uses', function () {
        [$david, $sarah] = solventCashFlowHousehold();

        $result = app(IHTCalculationService::class)->calculate($david->fresh(), $sarah->fresh(), true);

        $breakdown = app(IHTFormattingService::class)
            ->generateCashProjectionBreakdown($david->fresh(), $sarah->fresh(), true, $result);

        // Two independent models produced these before — one with inflation and life
        // events, one without — so the table could not add up to the headline whatever
        // either of them said.
        expect((float) $breakdown['final_cash'])->toBe(round((float) $result['projected_cash'], 0))
            ->and(end($breakdown['years'])['running_total'])->toBe(round((float) $result['projected_cash'], 0))
            ->and($breakdown['years'])->toHaveCount((int) $result['years_to_death']);
    });

    it('produces the same table from either login', function () {
        [$david, $sarah] = solventCashFlowHousehold();

        $formatter = app(IHTFormattingService::class);

        $fromDavid = $formatter->generateCashProjectionBreakdown(
            $david, $sarah, true, $this->service->calculate($david, $sarah, true)
        );
        $fromSarah = $formatter->generateCashProjectionBreakdown(
            $sarah, $david, true, $this->service->calculate($sarah, $david, true)
        );

        expect($fromDavid['final_cash'])->toBe($fromSarah['final_cash'])
            ->and($fromDavid['shortfall'])->toBe($fromSarah['shortfall'])
            ->and($fromDavid['starting_cash'])->toBe($fromSarah['starting_cash'])
            ->and($fromDavid['retirement_income'])->toBe($fromSarah['retirement_income'])
            ->and($fromDavid['retirement_expenses'])->toBe($fromSarah['retirement_expenses']);
    });
});
