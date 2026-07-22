<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\PensionInputHistory;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\Tax\Strategies\PensionAACarryForwardStrategy;
use App\Services\Tax\Strategies\TaxStrategyContext;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    app()->forgetScopedInstances();
});

/**
 * The active (calendar) tax year, mirroring AnnualAllowanceChecker's private
 * getCalendarTaxYear() so contributions are attributed to it. Uniquely named to
 * avoid clashing with helpers declared in sibling Pest files.
 */
function aaMpaaCalendarTaxYear(): string
{
    $now = Carbon::now();
    $startYear = $now->lt(Carbon::create($now->year, 4, 6)) ? $now->year - 1 : $now->year;

    return $startYear.'/'.substr((string) ($startYear + 1), -2);
}

function aaMpaaPriorYears(string $currentTaxYear): array
{
    $start = (int) substr($currentTaxYear, 0, 4);

    return [
        ($start - 3).'/'.substr((string) ($start - 2), -2),
        ($start - 2).'/'.substr((string) ($start - 1), -2),
        ($start - 1).'/'.substr((string) $start, -2),
    ];
}

/**
 * Create the arrange fixtures with model events muted. These unit tests verify
 * the annual-allowance math only; the RecommendationCacheObserver that fires on
 * financial-model writes (agent cache invalidation) is irrelevant here and is
 * skipped so the test is deterministic and self-contained.
 */
function aaMpaaWithoutEvents(callable $fn)
{
    return Model::withoutEvents($fn);
}

describe('MPAA cap in checkAnnualAllowance', function () {
    it('caps the money-purchase allowance at the MPAA once a DC pension is flexibly accessed', function () {
        $user = aaMpaaWithoutEvents(function () {
            $user = User::factory()->create(['annual_employment_income' => 40000]);
            DCPension::factory()->create([
                'user_id' => $user->id,
                'annual_salary' => 40000,
                'employee_contribution_percent' => 5.00,
                'employer_contribution_percent' => 3.00,
                'monthly_contribution_amount' => 1250, // £15,000/year DC contribution
                'has_flexibly_accessed' => true,
                'flexible_access_date' => now()->subYear()->toDateString(),
            ]);

            return $user;
        });

        $result = app(AnnualAllowanceChecker::class)
            ->checkAnnualAllowance($user->id, aaMpaaCalendarTaxYear());

        expect($result['mpaa_applies'])->toBeTrue()
            ->and($result['mpaa_amount'])->toBe(10000.0)
            ->and($result['available_allowance'])->toBe(10000.0)
            ->and($result['total_contributions'])->toBe(15000.0)
            ->and($result['carry_forward_available'])->toBe(0.0)
            ->and($result['excess_contributions'])->toBe(5000.0)
            ->and($result['has_excess'])->toBeTrue();
    });

    it('keeps the full standard allowance when no DC pension has been flexibly accessed', function () {
        $user = aaMpaaWithoutEvents(function () {
            $user = User::factory()->create(['annual_employment_income' => 40000]);
            DCPension::factory()->create([
                'user_id' => $user->id,
                'annual_salary' => 40000,
                'employee_contribution_percent' => 5.00,
                'employer_contribution_percent' => 3.00,
                'monthly_contribution_amount' => 1250, // £15,000/year DC contribution
                'has_flexibly_accessed' => false,
            ]);

            return $user;
        });

        $result = app(AnnualAllowanceChecker::class)
            ->checkAnnualAllowance($user->id, aaMpaaCalendarTaxYear());

        expect($result['mpaa_applies'])->toBeFalse()
            ->and($result['mpaa_amount'])->toBeNull()
            ->and($result['available_allowance'])->toBe(60000.0)
            ->and($result['excess_contributions'])->toBe(0.0)
            ->and($result['has_excess'])->toBeFalse();
    });
});

describe('carry-forward taper cap in checkAnnualAllowance', function () {
    it('values a tapered user\'s prior-year unused allowance at the tapered allowance, not the full standard AA', function () {
        $taxYear = aaMpaaCalendarTaxYear();

        // Employment £300k, no pension → threshold 300000 / adjusted 300000.
        // Both taper triggers met: reduction = (300000-260000)/2 = 20000 → AA = 40000.
        $user = aaMpaaWithoutEvents(function () use ($taxYear) {
            $user = User::factory()->create(['annual_employment_income' => 300000]);
            foreach (aaMpaaPriorYears($taxYear) as $year) {
                PensionInputHistory::create([
                    'user_id' => $user->id,
                    'tax_year' => $year,
                    'pension_input_amount' => 20000,
                ]);
            }

            return $user;
        });

        $result = app(AnnualAllowanceChecker::class)->checkAnnualAllowance($user->id, $taxYear);

        // Capped: 3 × max(0, 40000 - 20000) = 60000 (the old code credited
        // 3 × (60000 - 20000) = 120000 against the full standard AA).
        expect($result['is_tapered'])->toBeTrue()
            ->and($result['available_allowance'])->toBe(40000.0)
            ->and($result['carry_forward_available'])->toBe(60000.0);
    });
});

describe('MPAA gate in PensionAACarryForwardStrategy', function () {
    it('recommends a carry-forward top-up for an eligible higher-rate earner (control)', function () {
        $taxYear = aaMpaaCalendarTaxYear();
        $user = aaMpaaWithoutEvents(function () use ($taxYear) {
            $user = User::factory()->create([
                'household_calculation_mode' => 'single',
                'annual_employment_income' => 80000,
            ]);
            SavingsAccount::factory()->for($user)->create([
                'is_isa' => false,
                'current_balance' => 200000,
                'interest_rate' => 0,
            ]);
            $amounts = [20000, 35000, 45000];
            foreach (aaMpaaPriorYears($taxYear) as $i => $year) {
                PensionInputHistory::create([
                    'user_id' => $user->id,
                    'tax_year' => $year,
                    'pension_input_amount' => $amounts[$i],
                ]);
            }

            return $user;
        });

        $recommendations = app(PensionAACarryForwardStrategy::class)
            ->generate(new TaxStrategyContext($user, null, null, 'single'));

        expect($recommendations)->toHaveCount(1);
    });

    it('suppresses the recommendation once the user has flexibly accessed a DC pension', function () {
        $taxYear = aaMpaaCalendarTaxYear();
        $user = aaMpaaWithoutEvents(function () use ($taxYear) {
            $user = User::factory()->create([
                'household_calculation_mode' => 'single',
                'annual_employment_income' => 80000,
            ]);
            SavingsAccount::factory()->for($user)->create([
                'is_isa' => false,
                'current_balance' => 200000,
                'interest_rate' => 0,
            ]);
            DCPension::factory()->create([
                'user_id' => $user->id,
                'has_flexibly_accessed' => true,
                'flexible_access_date' => now()->subYear()->toDateString(),
            ]);
            $amounts = [20000, 35000, 45000];
            foreach (aaMpaaPriorYears($taxYear) as $i => $year) {
                PensionInputHistory::create([
                    'user_id' => $user->id,
                    'tax_year' => $year,
                    'pension_input_amount' => $amounts[$i],
                ]);
            }

            return $user;
        });

        $recommendations = app(PensionAACarryForwardStrategy::class)
            ->generate(new TaxStrategyContext($user, null, null, 'single'));

        expect($recommendations)->toBe([]);
    });
});
