<?php

declare(strict_types=1);

use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Protection\CoverageGapAnalyzer;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;

beforeEach(function () {
    // Mock TaxConfigService
    $mockTaxConfig = Mockery::mock(TaxConfigService::class);
    $mockTaxConfig->shouldReceive('getIncomeTax')
        ->andReturn([
            'personal_allowance' => 12570,
            'bands' => [
                ['name' => 'Basic Rate', 'min' => 0, 'max' => 37700, 'rate' => 0.20],
                ['name' => 'Higher Rate', 'min' => 37700, 'max' => 112570, 'rate' => 0.40],
                ['name' => 'Additional Rate', 'min' => 112570, 'max' => null, 'rate' => 0.45],
            ],
        ]);

    $mockTaxConfig->shouldReceive('getNationalInsurance')
        ->andReturn([
            'class_1' => [
                'employee' => [
                    'primary_threshold' => 12570,
                    'upper_earnings_limit' => 50270,
                    'main_rate' => 0.08,
                    'additional_rate' => 0.02,
                ],
            ],
            'class_4' => [
                'lower_profits_limit' => 12570,
                'upper_profits_limit' => 50270,
                'main_rate' => 0.06,
                'additional_rate' => 0.02,
            ],
        ]);

    $mockTaxConfig->shouldReceive('getDividendTax')
        ->andReturn([
            'allowance' => 500,
            'basic_rate' => 0.0875,
            'higher_rate' => 0.3375,
            'additional_rate' => 0.3935,
        ]);

    // Protection-specific config values
    $mockTaxConfig->shouldReceive('get')
        ->with('protection.withdrawal_rates.human_capital', Mockery::any())
        ->andReturn(0.047);
    $mockTaxConfig->shouldReceive('get')
        ->with('protection.final_expenses', Mockery::any())
        ->andReturn(7500);
    $mockTaxConfig->shouldReceive('get')
        ->with('protection.education_cost_per_year', Mockery::any())
        ->andReturn(9000);
    $mockTaxConfig->shouldReceive('get')
        ->with('protection.income_multipliers.income_protection_max_benefit', Mockery::any())
        ->andReturn(0.60);

    // State benefit config values for SSP/ESA integration
    $mockTaxConfig->shouldReceive('get')
        ->with('benefits.ssp.weekly_rate', Mockery::any())
        ->andReturn(116.75);
    $mockTaxConfig->shouldReceive('get')
        ->with('benefits.ssp.max_weeks', Mockery::any())
        ->andReturn(28);
    $mockTaxConfig->shouldReceive('get')
        ->with('benefits.ssp.lower_earnings_limit', Mockery::any())
        ->andReturn(125);
    $mockTaxConfig->shouldReceive('get')
        ->with('benefits.ssp.not_available_for', Mockery::any())
        ->andReturn(['self_employed']);
    $mockTaxConfig->shouldReceive('get')
        ->with('benefits.esa.assessment_rate_25_plus', Mockery::any())
        ->andReturn(90.50);

    // Employer reliance threshold
    $mockTaxConfig->shouldReceive('get')
        ->with('protection.dis_reliance_percent', Mockery::any())
        ->andReturn(0.50);

    // Create UKTaxCalculator with mocked TaxConfigService
    $taxCalculator = new UKTaxCalculator($mockTaxConfig);

    // Create CoverageGapAnalyzer with mocked TaxConfigService (for both tax calc and protection config)
    $this->analyzer = new CoverageGapAnalyzer($taxCalculator, $mockTaxConfig);
});

afterEach(function () {
    Mockery::close();
});

describe('calculateHumanCapital', function () {
    it('calculates life cover capital using sustainable drawdown at 4.7%', function () {
        $annualIncomeNeed = 50000;

        $result = $this->analyzer->calculateHumanCapital($annualIncomeNeed);

        // Expected: 50000 / 0.047 = 1,063,829.79 (rounded to 2dp)
        expect(round($result, 2))->toEqual(1063829.79);
    });

    it('returns zero when income need is zero', function () {
        $result = $this->analyzer->calculateHumanCapital(0);

        expect($result)->toEqual(0.0);
    });

    it('returns zero when income need is negative', function () {
        $result = $this->analyzer->calculateHumanCapital(-5000);

        expect($result)->toEqual(0.0);
    });

    it('calculates correctly for small income need', function () {
        $annualIncomeNeed = 10000;

        $result = $this->analyzer->calculateHumanCapital($annualIncomeNeed);

        // Expected: 10000 / 0.047 = 212,765.96
        expect(round($result, 2))->toEqual(212765.96);
    });

    it('calculates correctly for high income need', function () {
        $annualIncomeNeed = 100000;

        $result = $this->analyzer->calculateHumanCapital($annualIncomeNeed);

        // Expected: 100000 / 0.047 = 2,127,659.57
        expect(round($result, 2))->toEqual(2127659.57);
    });
});

describe('calculateDebtProtectionNeed', function () {
    it('calculates debt protection need correctly', function () {
        $user = User::factory()->create();
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'mortgage_balance' => 250000,
            'other_debts' => 25000,
        ]);

        $result = $this->analyzer->calculateDebtProtectionNeed($profile);

        expect($result)->toEqual(275000.0);
    });

    it('handles zero debts', function () {
        $user = User::factory()->create();
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'mortgage_balance' => 0,
            'other_debts' => 0,
        ]);

        $result = $this->analyzer->calculateDebtProtectionNeed($profile);

        expect($result)->toEqual(0.0);
    });

    it('handles only mortgage balance', function () {
        $user = User::factory()->create();
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'mortgage_balance' => 300000,
            'other_debts' => 0,
        ]);

        $result = $this->analyzer->calculateDebtProtectionNeed($profile);

        expect($result)->toEqual(300000.0);
    });
});

describe('calculateEducationFunding', function () {
    it('calculates education funding for one child', function () {
        $numChildren = 1;
        $ages = [5];

        $result = $this->analyzer->calculateEducationFunding($numChildren, $ages);

        // Expected: 9000 * (21 - 5) = 9000 * 16 = 144,000
        expect($result)->toEqual(144000.0);
    });

    it('calculates education funding for multiple children', function () {
        $numChildren = 2;
        $ages = [5, 10];

        $result = $this->analyzer->calculateEducationFunding($numChildren, $ages);

        // Child 1: 9000 * (21 - 5) = 9000 * 16 = 144,000
        // Child 2: 9000 * (21 - 10) = 9000 * 11 = 99,000
        // Total: 243,000
        expect($result)->toEqual(243000.0);
    });

    it('returns zero for children over 21', function () {
        $numChildren = 1;
        $ages = [25];

        $result = $this->analyzer->calculateEducationFunding($numChildren, $ages);

        expect($result)->toEqual(0.0);
    });

    it('handles child at age 21', function () {
        $numChildren = 1;
        $ages = [21];

        $result = $this->analyzer->calculateEducationFunding($numChildren, $ages);

        expect($result)->toEqual(0.0);
    });

    it('handles mixed ages including above 21', function () {
        $numChildren = 3;
        $ages = [5, 18, 25];

        $result = $this->analyzer->calculateEducationFunding($numChildren, $ages);

        // Child 1: 9000 * 16 = 144,000
        // Child 2: 9000 * 3 = 27,000
        // Child 3: 0 (over 21)
        // Total: 171,000
        expect($result)->toEqual(171000.0);
    });

    it('returns zero when no children', function () {
        $numChildren = 0;
        $ages = [];

        $result = $this->analyzer->calculateEducationFunding($numChildren, $ages);

        expect($result)->toEqual(0.0);
    });
});

describe('calculateFinalExpenses', function () {
    it('returns fixed amount of £7,500', function () {
        $result = $this->analyzer->calculateFinalExpenses();

        expect($result)->toEqual(7500.0);
    });
});

describe('calculateTotalCoverage', function () {
    it('calculates coverage from all policy types', function () {
        $lifePolicies = collect([
            (object) ['sum_assured' => 100000],
            (object) ['sum_assured' => 50000],
        ]);

        $criticalIllnessPolicies = collect([
            (object) ['sum_assured' => 75000],
        ]);

        $incomeProtectionPolicies = collect([
            (object) ['benefit_amount' => 2000, 'benefit_frequency' => 'monthly'],
        ]);

        $disabilityPolicies = collect([
            (object) ['benefit_amount' => 1500, 'benefit_frequency' => 'monthly'],
        ]);

        $sicknessIllnessPolicies = collect([
            (object) ['benefit_amount' => 50000, 'benefit_frequency' => 'lump_sum'],
        ]);

        $result = $this->analyzer->calculateTotalCoverage(
            $lifePolicies,
            $criticalIllnessPolicies,
            $incomeProtectionPolicies,
            $disabilityPolicies,
            $sicknessIllnessPolicies
        );

        expect($result)->toHaveKeys([
            'life_coverage',
            'critical_illness_coverage',
            'income_protection_coverage',
            'disability_coverage',
            'sickness_illness_coverage',
            'total_coverage',
            'total_income_coverage',
        ]);

        expect($result['life_coverage'])->toEqual(150000);
        expect($result['critical_illness_coverage'])->toEqual(75000);
        expect($result['income_protection_coverage'])->toEqual(24000); // 2000 * 12
        expect($result['disability_coverage'])->toEqual(18000); // 1500 * 12
        expect($result['sickness_illness_coverage'])->toEqual(50000);
        expect($result['total_coverage'])->toEqual(225000); // life + critical illness
        expect($result['total_income_coverage'])->toEqual(92000); // 24000 + 18000 + 50000
    });

    it('handles weekly benefit frequency for income protection', function () {
        $result = $this->analyzer->calculateTotalCoverage(
            collect([]),
            collect([]),
            collect([
                (object) ['benefit_amount' => 500, 'benefit_frequency' => 'weekly'],
            ]),
            collect([]),
            collect([])
        );

        expect($result['income_protection_coverage'])->toEqual(26000); // 500 * 52
    });

    it('handles weekly benefit frequency for disability', function () {
        $result = $this->analyzer->calculateTotalCoverage(
            collect([]),
            collect([]),
            collect([]),
            collect([
                (object) ['benefit_amount' => 400, 'benefit_frequency' => 'weekly'],
            ]),
            collect([])
        );

        expect($result['disability_coverage'])->toEqual(20800); // 400 * 52
    });

    it('handles monthly benefit frequency for sickness/illness', function () {
        $result = $this->analyzer->calculateTotalCoverage(
            collect([]),
            collect([]),
            collect([]),
            collect([]),
            collect([
                (object) ['benefit_amount' => 1000, 'benefit_frequency' => 'monthly'],
            ])
        );

        expect($result['sickness_illness_coverage'])->toEqual(12000.0); // 1000 * 12
    });

    it('handles empty collections', function () {
        $result = $this->analyzer->calculateTotalCoverage(
            collect([]),
            collect([]),
            collect([]),
            collect([]),
            collect([])
        );

        expect($result['life_coverage'])->toEqual(0.0);
        expect($result['critical_illness_coverage'])->toEqual(0.0);
        expect($result['income_protection_coverage'])->toEqual(0.0);
        expect($result['disability_coverage'])->toEqual(0.0);
        expect($result['sickness_illness_coverage'])->toEqual(0.0);
        expect($result['total_coverage'])->toEqual(0.0);
        expect($result['total_income_coverage'])->toEqual(0.0);
    });
});

describe('calculateCoverageGap', function () {
    it('calculates coverage gap correctly', function () {
        $needs = [
            'human_capital' => 500000,
            'debt_protection' => 200000,
            'education_funding' => 150000,
            'final_expenses' => 7500,
            'income_protection_need' => 30000,
        ];

        $coverage = [
            'life_coverage' => 300000,
            'critical_illness_coverage' => 100000,
            'income_protection_coverage' => 20000,
            'disability_coverage' => 10000,
            'sickness_illness_coverage' => 5000,
            'total_coverage' => 400000,
            'total_income_coverage' => 35000,
        ];

        $result = $this->analyzer->calculateCoverageGap($needs, $coverage);

        expect($result)->toHaveKeys([
            'total_need',
            'total_coverage',
            'total_gap',
            'gaps_by_category',
            'coverage_percentage',
        ]);

        // Total need: 500000 + 200000 + 150000 + 7500 = 857,500
        expect($result['total_need'])->toEqual(857500.0);
        expect($result['total_coverage'])->toEqual(400000.0);
        expect($result['total_gap'])->toEqual(457500.0);

        // Coverage percentage: (400000 / 857500) * 100 = 46.65%
        expect($result['coverage_percentage'])->toBeGreaterThan(46.0);
        expect($result['coverage_percentage'])->toBeLessThan(47.0);

        expect($result['gaps_by_category'])->toHaveKeys([
            'human_capital_gap',
            'debt_protection_gap',
            'education_funding_gap',
            'income_protection_gap',
            'disability_coverage_gap',
            'sickness_illness_gap',
        ]);
    });

    it('returns zero gap when fully covered', function () {
        $needs = [
            'human_capital' => 300000,
            'debt_protection' => 100000,
            'education_funding' => 50000,
            'final_expenses' => 7500,
            'income_protection_need' => 20000,
        ];

        $coverage = [
            'life_coverage' => 500000,
            'critical_illness_coverage' => 100000,
            'income_protection_coverage' => 15000,
            'disability_coverage' => 5000,
            'sickness_illness_coverage' => 5000,
            'total_coverage' => 600000,
            'total_income_coverage' => 25000,
        ];

        $result = $this->analyzer->calculateCoverageGap($needs, $coverage);

        expect($result['total_gap'])->toEqual(0.0);
        expect($result['coverage_percentage'])->toBeGreaterThanOrEqual(100.0);
    });

    it('handles zero coverage', function () {
        $needs = [
            'human_capital' => 500000,
            'debt_protection' => 200000,
            'education_funding' => 150000,
            'final_expenses' => 7500,
            'income_protection_need' => 30000,
        ];

        $coverage = [
            'life_coverage' => 0,
            'critical_illness_coverage' => 0,
            'income_protection_coverage' => 0,
            'disability_coverage' => 0,
            'sickness_illness_coverage' => 0,
            'total_coverage' => 0,
            'total_income_coverage' => 0,
        ];

        $result = $this->analyzer->calculateCoverageGap($needs, $coverage);

        expect($result['total_gap'])->toEqual(857500.0);
        expect($result['coverage_percentage'])->toEqual(0.0);
    });
});

describe('calculateProtectionNeeds', function () {
    it('calculates all protection needs correctly', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(35),
        ]);

        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'mortgage_balance' => 250000,
            'other_debts' => 25000,
            'number_of_dependents' => 2,
            'dependents_ages' => [5, 10],
            'retirement_age' => 67,
        ]);

        $result = $this->analyzer->calculateProtectionNeeds($profile);

        expect($result)->toHaveKeys([
            'human_capital',
            'debt_protection',
            'education_funding',
            'final_expenses',
            'income_protection_need',
            'total_need',
        ]);

        // Human capital: net_income_difference / 0.047 (sustainable drawdown)
        // Net income ~£39,520 for £50k gross, so human capital ~£39,520 / 0.047 ≈ £840,851
        expect($result['human_capital'])->toBeGreaterThan(0);
        $expectedHumanCapital = $result['net_income_difference'] / 0.047;
        expect(round($result['human_capital'], 2))->toEqual(round($expectedHumanCapital, 2));

        // Debt protection: 250000 + 25000 = 275,000
        expect($result['debt_protection'])->toEqual(275000.0);

        // Education funding: (9000 * 16) + (9000 * 11) = 243,000
        expect($result['education_funding'])->toEqual(243000.0);

        // Final expenses: 7,500
        expect($result['final_expenses'])->toEqual(7500.0);

        // Income protection need: 50000 * 0.6 = 30,000
        expect($result['income_protection_need'])->toEqual(30000.0);

        // Total need = human_capital + debt + education + final_expenses
        $expectedTotal = $result['human_capital'] + 275000 + 243000 + 7500;
        expect(round($result['total_need'], 2))->toEqual(round($expectedTotal, 2));
    });

    it('handles profile with no dependents', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(40),
        ]);

        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 60000,
            'mortgage_balance' => 0,
            'other_debts' => 0,
            'number_of_dependents' => 0,
            'dependents_ages' => [],
            'retirement_age' => 67,
        ]);

        $result = $this->analyzer->calculateProtectionNeeds($profile);

        expect($result['education_funding'])->toEqual(0.0);
        expect($result['debt_protection'])->toEqual(0.0);
    });

    it('uses default age when date_of_birth is null', function () {
        $user = User::factory()->create([
            'date_of_birth' => null,
        ]);

        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'mortgage_balance' => 0,
            'other_debts' => 0,
            'number_of_dependents' => 0,
            'dependents_ages' => null,
            'retirement_age' => 67,
        ]);

        $result = $this->analyzer->calculateProtectionNeeds($profile);

        // Human capital = net_income_difference / 0.047 (sustainable drawdown)
        expect($result['human_capital'])->toBeGreaterThan(0);
        $expectedHumanCapital = $result['net_income_difference'] / 0.047;
        expect(round($result['human_capital'], 2))->toEqual(round($expectedHumanCapital, 2));
    });
});
