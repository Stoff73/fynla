<?php

declare(strict_types=1);

use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Protection\CoverageGapAnalyzer;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
use Database\Seeders\TaxConfigurationSeeder;

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

    // Protection needs: the seeder's one home for the figures (item 8b).
    $mockTaxConfig->shouldReceive('getProtectionNeeds')
        ->andReturn(TaxConfigurationSeeder::protectionNeedsCalculation());

    // State benefit config values for SSP/ESA, as TaxConfigurationSeeder seeds
    // 2026/27. Each key is expected with ONE argument, so a literal fallback
    // passed as a second argument (Rule 2) fails to match. A test swaps the
    // year by overwriting $this->benefits before calling the analyzer.
    $this->benefits = [
        'benefits.ssp.weekly_rate' => 123.25,
        'benefits.ssp.max_weeks' => 28,
        'benefits.ssp.lower_earnings_limit' => null,
        'benefits.ssp.lower_earner_rate' => 0.80,
        'benefits.esa.assessment_rate_25_plus' => 95.55,
    ];
    foreach (array_keys($this->benefits) as $key) {
        $mockTaxConfig->shouldReceive('get')
            ->with($key)
            ->andReturnUsing(fn () => $this->benefits[$key]);
    }

    // W-0511 — the analyzer asks the config service what allowance each person is
    // entitled to. None of these fixtures is registered blind, so the real answer is
    // zero and the mock gives the same one rather than a stub that would hide a
    // change in the entitlement rule.
    $mockTaxConfig->shouldReceive('blindPersonsAllowanceFor')
        ->andReturnUsing(fn (?User $person) => $person?->is_registered_blind ? 3250.0 : 0.0);

    // Create UKTaxCalculator with mocked TaxConfigService
    $taxCalculator = new UKTaxCalculator($mockTaxConfig);

    // Create CoverageGapAnalyzer with mocked TaxConfigService (for both tax calc and protection config)
    // The asset aggregator is the REAL one — these tests use real records, and a
    // mocked debt total would assert only what the mock was told to say.
    $this->analyzer = new CoverageGapAnalyzer($taxCalculator, $mockTaxConfig, app(CrossModuleAssetAggregator::class));
});

afterEach(function () {
    Mockery::close();
});

describe('calculateHumanCapital', function () {
    // gap x (1 - (1 + r)^-n) / r at the Personal Injury Discount Rate, 0.5% (D1).
    it('turns a yearly income gap into the lump sum that pays it for the term', function () {
        expect(round($this->analyzer->calculateHumanCapital(30000, 25), 2))->toEqual(703369.14)
            ->and(round($this->analyzer->calculateHumanCapital(30000, 10.5), 2))->toEqual(306129.28);
    });

    it('is never a perpetuity: a longer term costs more, a shorter one less', function () {
        expect($this->analyzer->calculateHumanCapital(10000, 1))->toBeLessThan(10000.0)
            ->and(round($this->analyzer->calculateHumanCapital(10000, 1), 2))->toEqual(9950.25);
    });

    it('returns zero with no gap or no years left', function () {
        expect($this->analyzer->calculateHumanCapital(0, 20))->toEqual(0.0)
            ->and($this->analyzer->calculateHumanCapital(-5000, 20))->toEqual(0.0)
            ->and($this->analyzer->calculateHumanCapital(30000, 0))->toEqual(0.0);
    });
});

describe('income protection and critical illness needs', function () {
    // D6: Legal & General, 60% of the first £60,000 and 50% above (QGI16002 04/25).
    it('applies the insurer limit band by band', function () {
        expect($this->analyzer->incomeProtectionNeed(50000))->toEqual(30000.0)
            ->and($this->analyzer->incomeProtectionNeed(60000))->toEqual(36000.0)
            ->and($this->analyzer->incomeProtectionNeed(100000))->toEqual(56000.0)
            ->and($this->analyzer->benefitTiersInWords())->toBe('60% of the first £60,000 and 50% above');
    });

    // D3: three times gross earned income, a rule of thumb.
    it('works out critical illness as the rule-of-thumb multiple of gross income', function () {
        expect($this->analyzer->criticalIllnessNeed(75000))->toEqual(225000.0)
            ->and($this->analyzer->criticalIllnessNeed(-1))->toEqual(0.0);
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

describe('calculateFinalExpenses', function () {
    // D4: SunLife Cost of Dying Report 2025, the cost of dying.
    it('is the configured cost of dying', function () {
        expect($this->analyzer->calculateFinalExpenses())->toEqual(9797.0);
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

describe('the published critical gap count', function () {
    /**
     * W-0479 — every consumer of this count re-derived it from a shape this analyzer
     * has never emitted: `$gap['gap']` on `/m` and native, `$gap['shortfall']` on web,
     * both iterating `gaps` as if it were a map of category => object. Both read ZERO
     * for every household in the application's history, and the count gates
     * `MilestoneDetectionService::detectProtectionAdequate()` — which therefore told
     * any household with one policy that "your protection now covers what your family
     * would need". Three such milestones were awarded on the development database, one
     * of them to a household with a £21,000 income protection shortfall.
     *
     * The analyzer publishes the number now. These pin what it means.
     */
    it('counts one gap per distinct shortfall, not one per category name', function () {
        // Fully covered for life, critical illness and debt; £21,000 of income is
        // uncovered. `income_protection_gap`, `disability_coverage_gap` and
        // `sickness_illness_gap` all carry that same £21,000 — the analyzer's own
        // comment says IP is primary and the other two are supplementary — so this is
        // ONE gap, not three.
        $result = $this->analyzer->calculateCoverageGap(
            [
                'human_capital' => 100000,
                'debt_protection' => 0,
                'education_funding' => 0,
                'final_expenses' => 0,
                'income_protection_need' => 21000,
            ],
            [
                'life_coverage' => 500000,
                'critical_illness_coverage' => 0,
                'income_protection_coverage' => 0,
                'disability_coverage' => 0,
                'sickness_illness_coverage' => 0,
                'total_coverage' => 500000,
                'total_income_coverage' => 0,
            ],
        );

        expect($result['gaps_by_category']['income_protection_gap'])->toEqual(21000.0);
        expect($result['gaps_by_category']['disability_coverage_gap'])->toEqual(21000.0);
        expect($result['critical_gap_count'])->toBe(1);
    });

    it('counts each genuinely separate shortfall', function () {
        $result = $this->analyzer->calculateCoverageGap(
            [
                'human_capital' => 500000,
                'debt_protection' => 200000,
                'education_funding' => 0,
                'final_expenses' => 0,
                'income_protection_need' => 30000,
            ],
            [
                'life_coverage' => 0,
                'critical_illness_coverage' => 0,
                'income_protection_coverage' => 0,
                'disability_coverage' => 0,
                'sickness_illness_coverage' => 0,
                'total_coverage' => 0,
                'total_income_coverage' => 0,
            ],
        );

        // Human capital, debt protection and income protection are three different
        // things to be uninsured for.
        expect($result['critical_gap_count'])->toBe(3);
    });

    it('reports no gap for a household that has none', function () {
        $result = $this->analyzer->calculateCoverageGap(
            [
                'human_capital' => 100000,
                'debt_protection' => 0,
                'education_funding' => 0,
                'final_expenses' => 0,
                'income_protection_need' => 20000,
            ],
            [
                'life_coverage' => 500000,
                'critical_illness_coverage' => 0,
                'income_protection_coverage' => 25000,
                'disability_coverage' => 0,
                'sickness_illness_coverage' => 0,
                'total_coverage' => 500000,
                'total_income_coverage' => 25000,
            ],
        );

        expect($result['critical_gap_count'])->toBe(0);
    });
});

describe('calculateCoverageGap', function () {
    it('calculates coverage gap correctly', function () {
        $needs = [
            'human_capital' => 500000,
            'debt_protection' => 200000,
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

        // Total need: 500000 + 200000 + 7500 = 707,500 (no education figure, D5)
        expect($result['total_need'])->toEqual(707500.0);
        expect($result['total_coverage'])->toEqual(400000.0);
        expect($result['total_gap'])->toEqual(307500.0);

        // Coverage percentage: (400000 / 707500) * 100 = 56.54%
        expect($result['coverage_percentage'])->toBeGreaterThan(56.0);
        expect($result['coverage_percentage'])->toBeLessThan(57.0);

        expect($result['gaps_by_category'])->toHaveKeys([
            'human_capital_gap',
            'debt_protection_gap',
            'income_protection_gap',
            'disability_coverage_gap',
            'sickness_illness_gap',
        ]);
    });

    it('returns zero gap when fully covered', function () {
        $needs = [
            'human_capital' => 300000,
            'debt_protection' => 100000,
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

        expect($result['total_gap'])->toEqual(707500.0);
        expect($result['coverage_percentage'])->toEqual(0.0);
    });
});

describe('calculateProtectionNeeds', function () {
    // D7: the family's income gap is household living costs less income that
    // continues, paid until State Pension age (D2) at 0.5% (D1).
    it('works the life need out from spending, the term and the configured figures', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(35),
            'expenditure_entry_mode' => 'simple',
            'monthly_expenditure' => 2500,
            'annual_employment_income' => 50000,
        ]);
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'mortgage_balance' => 250000,
            'other_debts' => 25000,
            'number_of_dependents' => 2,
            'dependents_ages' => [5, 10],
        ]);

        $result = $this->analyzer->calculateProtectionNeeds($profile);
        $replacement = $result['income_replacement'];

        expect($result)->not->toHaveKey('education_funding')
            ->and($replacement['spending_recorded'])->toBeTrue()
            ->and($replacement['household_living_costs'])->toEqual(30000.0)
            ->and($replacement['income_gap'])->toEqual(30000.0)
            ->and($replacement['term_years'])->toBeGreaterThan(31.0)
            ->and($replacement['discount_rate'])->toEqual(0.005)
            ->and(round($result['human_capital'], 2))->toEqual(round($this->analyzer->calculateHumanCapital(30000, $replacement['term_years']), 2))
            ->and($result['debt_protection'])->toEqual(275000.0)
            ->and($result['final_expenses'])->toEqual(9797.0)
            ->and(round($result['total_need'], 2))->toEqual(round($result['human_capital'] + 275000 + 9797, 2))
            ->and($result['income_protection_need'])->toEqual(30000.0)
            ->and($result['critical_illness_need'])->toEqual(150000.0)
            ->and($result['income_protection_basis'])->toContain('60% of the first £60,000 and 50% above')
            ->and($result['critical_illness_basis'])->toContain('rule of thumb');
    });

    it('leaves the income part out, and says so, when spending is not recorded', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(40),
            'expenditure_entry_mode' => 'simple',
            'monthly_expenditure' => 0,
            'annual_expenditure' => 0,
            'annual_employment_income' => 60000,
        ]);
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 60000,
            'mortgage_balance' => 0,
            'other_debts' => 0,
            'number_of_dependents' => 0,
            'dependents_ages' => [],
        ]);

        $result = $this->analyzer->calculateProtectionNeeds($profile);

        expect($result['income_replacement']['spending_recorded'])->toBeFalse()
            ->and($result['human_capital'])->toEqual(0.0)
            ->and($result['total_need'])->toEqual(9797.0);
    });

    it('loses no earnings past State Pension age', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(70),
            'expenditure_entry_mode' => 'simple',
            'monthly_expenditure' => 2000,
        ]);
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 0,
            'mortgage_balance' => 0,
            'other_debts' => 0,
            'number_of_dependents' => 0,
            'dependents_ages' => [],
        ]);

        $result = $this->analyzer->calculateProtectionNeeds($profile);

        expect($result['income_replacement']['term_years'])->toEqual(0.0)
            ->and($result['human_capital'])->toEqual(0.0);
    });
});

describe('state benefits: Statutory Sick Pay', function () {
    // https://www.gov.uk/statutory-sick-pay/what-youll-get: "£123.25 a week
    // Statutory Sick Pay (SSP) or 80% of your normal weekly earnings - whichever
    // is lower", "for up to 28 weeks". The rate, the 80% and the weeks come from
    // `benefits.ssp` in tax config.
    $needsFor = function (object $test, array $income): array {
        $user = User::factory()->create(array_merge([
            'date_of_birth' => now()->subYears(40),
            'annual_employment_income' => 0,
            'annual_self_employment_income' => 0,
        ], $income));
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => array_sum($income),
            'mortgage_balance' => 0,
            'other_debts' => 0,
            'number_of_dependents' => 0,
            'dependents_ages' => [],
        ]);

        return $test->analyzer->calculateProtectionNeeds($profile)['state_benefits'];
    };

    it('pays the flat weekly rate to an employee whose 80% is higher', function () use ($needsFor) {
        $ssp = $needsFor($this, ['annual_employment_income' => 60000]);

        expect($ssp['ssp_eligible'])->toBeTrue()
            ->and($ssp['ssp_weekly_rate'])->toEqual(123.25)
            ->and($ssp['ssp_max_weeks'])->toBe(28)
            ->and($ssp['ssp_total_entitlement'])->toEqual(123.25 * 28);
    });

    it('pays 80% of weekly earnings when that is lower than the flat rate (2026/27)', function () use ($needsFor) {
        // £5,200 a year = £100 a week; 80% = £80, below £123.25. Before April
        // 2026 this employee was under the £125 lower earnings limit and got nothing.
        $ssp = $needsFor($this, ['annual_employment_income' => 5200]);

        expect($ssp['ssp_eligible'])->toBeTrue()
            ->and($ssp['ssp_weekly_rate'])->toEqual(80.0)
            ->and($ssp['ssp_total_entitlement'])->toEqual(80.0 * 28);
    });

    it('applies the lower earnings limit in a year that has one (2025/26)', function () use ($needsFor) {
        $this->benefits['benefits.ssp.weekly_rate'] = 118.75;
        $this->benefits['benefits.ssp.lower_earnings_limit'] = 125;
        $this->benefits['benefits.ssp.lower_earner_rate'] = null;

        $below = $needsFor($this, ['annual_employment_income' => 5200]);
        $above = $needsFor($this, ['annual_employment_income' => 30000]);

        expect($below['ssp_eligible'])->toBeFalse()
            ->and($below['ssp_total_entitlement'])->toEqual(0.0)
            ->and($above['ssp_eligible'])->toBeTrue()
            ->and($above['ssp_weekly_rate'])->toEqual(118.75)
            ->and($above['ssp_total_entitlement'])->toEqual(118.75 * 28);
    });

    it('gives a self-employed person no Statutory Sick Pay', function () use ($needsFor) {
        $ssp = $needsFor($this, ['annual_self_employment_income' => 40000]);

        expect($ssp['ssp_eligible'])->toBeFalse()
            ->and($ssp['is_self_employed'])->toBeTrue()
            ->and($ssp['ssp_total_entitlement'])->toEqual(0.0);
    });

    it('treats a missing rate as no entitlement rather than a guessed figure', function () use ($needsFor) {
        $this->benefits['benefits.ssp.weekly_rate'] = null;

        $ssp = $needsFor($this, ['annual_employment_income' => 60000]);

        expect($ssp['ssp_eligible'])->toBeFalse()
            ->and($ssp['ssp_total_entitlement'])->toEqual(0.0);
    });

    it('reads the ESA rate from config', function () use ($needsFor) {
        $ssp = $needsFor($this, ['annual_employment_income' => 60000]);

        expect($ssp['esa_monthly_equivalent'])->toEqual((95.55 * 52) / 12);
    });
});
