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

    // Create UKTaxCalculator with mocked TaxConfigService
    $taxCalculator = new UKTaxCalculator($mockTaxConfig);

    // Create CoverageGapAnalyzer with mocked UKTaxCalculator
    $this->analyzer = new CoverageGapAnalyzer($taxCalculator);
});

describe('calculateHumanCapital', function () {
    it('calculates human capital correctly for standard case', function () {
        $income = 50000;
        $age = 35;
        $retirementAge = 67;

        $result = $this->analyzer->calculateHumanCapital($income, $age, $retirementAge);

        // Expected: 50000 * 10 * 10 = 5,000,000
        expect($result)->toEqual(5000000.0);
    });

    it('limits effective years to 10 even if more years to retirement', function () {
        $income = 50000;
        $age = 25;
        $retirementAge = 67; // 42 years to retirement

        $result = $this->analyzer->calculateHumanCapital($income, $age, $retirementAge);

        // Should use max 10 years: 50000 * 10 * 10 = 5,000,000
        expect($result)->toEqual(5000000.0);
    });

    it('uses actual years when less than 10 years to retirement', function () {
        $income = 50000;
        $age = 62;
        $retirementAge = 67; // 5 years to retirement

        $result = $this->analyzer->calculateHumanCapital($income, $age, $retirementAge);

        // Expected: 50000 * 10 * 5 = 2,500,000
        expect($result)->toEqual(2500000.0);
    });

    it('returns zero when already at retirement age', function () {
        $income = 50000;
        $age = 67;
        $retirementAge = 67;

        $result = $this->analyzer->calculateHumanCapital($income, $age, $retirementAge);

        expect($result)->toEqual(0.0);
    });

    it('returns zero when past retirement age', function () {
        $income = 50000;
        $age = 70;
        $retirementAge = 67;

        $result = $this->analyzer->calculateHumanCapital($income, $age, $retirementAge);

        expect($result)->toEqual(0.0);
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

        // Human capital: 50000 * 10 * 10 = 5,000,000
        expect($result['human_capital'])->toEqual(5000000.0);

        // Debt protection: 250000 + 25000 = 275,000
        expect($result['debt_protection'])->toEqual(275000.0);

        // Education funding: (9000 * 16) + (9000 * 11) = 243,000
        expect($result['education_funding'])->toEqual(243000.0);

        // Final expenses: 7,500
        expect($result['final_expenses'])->toEqual(7500.0);

        // Income protection need: 50000 * 0.6 = 30,000
        expect($result['income_protection_need'])->toEqual(30000.0);

        // Total need
        expect($result['total_need'])->toEqual(5525500.0);
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

        // Should use default age 40, so: 50000 * 10 * 10 = 5,000,000
        expect($result['human_capital'])->toEqual(5000000.0);
    });
});
