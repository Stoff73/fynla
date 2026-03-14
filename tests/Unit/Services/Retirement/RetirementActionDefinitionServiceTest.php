<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\RetirementActionDefinition;
use App\Models\RetirementProfile;
use App\Models\User;
use App\Services\Retirement\RetirementActionDefinitionService;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(RetirementActionDefinitionSeeder::class);

    $this->service = app(RetirementActionDefinitionService::class);

    $this->user = User::factory()->create([
        'annual_employment_income' => 55000,
        'is_preview_user' => true,
    ]);

    $this->profile = RetirementProfile::create([
        'user_id' => $this->user->id,
        'current_age' => 35,
        'target_retirement_age' => 65,
        'target_retirement_income' => 30000,
        'current_annual_salary' => 55000,
    ]);
});

describe('evaluateAgentActions', function () {
    it('produces employer match recommendation when employee contribution below threshold', function () {
        DCPension::create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Test Workplace',
            'scheme_type' => 'workplace',
            'pension_type' => 'occupational',
            'employee_contribution_percent' => 3.0,
            'employer_contribution_percent' => 3.0,
            'current_fund_value' => 50000,
            'annual_salary' => 55000,
        ]);

        $analysisData = [
            'profile' => $this->profile->toArray(),
            'summary' => [
                'income_gap' => 5000,
                'target_retirement_income' => 30000,
                'target_retirement_age' => 65,
            ],
            'annual_allowance' => [
                'has_excess' => false,
                'remaining_allowance' => 56700, // £60k - £3,300 contributions
                'carry_forward_available' => 0,
            ],
        ];

        $result = $this->service->evaluateAgentActions($analysisData);

        $recs = $result['recommendations'];
        $employerMatch = collect($recs)->firstWhere('category', 'Employer_match');

        expect($employerMatch)->not->toBeNull()
            ->and($employerMatch['scope'])->toBe('account')
            ->and($employerMatch['title'])->toContain('Maximise Employer Pension Match');
    });

    it('does not produce employer match when contribution meets threshold', function () {
        DCPension::create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Good Workplace',
            'scheme_type' => 'workplace',
            'pension_type' => 'occupational',
            'employee_contribution_percent' => 6.0,
            'employer_contribution_percent' => 6.0,
            'current_fund_value' => 50000,
            'annual_salary' => 55000,
        ]);

        $analysisData = [
            'profile' => $this->profile->toArray(),
            'summary' => [
                'income_gap' => 0,
                'target_retirement_income' => 30000,
                'target_retirement_age' => 65,
            ],
            'annual_allowance' => ['has_excess' => false],
        ];

        $result = $this->service->evaluateAgentActions($analysisData);
        $employerMatch = collect($result['recommendations'])->firstWhere('category', 'Employer_match');

        expect($employerMatch)->toBeNull();
    });

    it('skips disabled definitions', function () {
        RetirementActionDefinition::where('key', 'employer_match')->update(['is_enabled' => false]);

        DCPension::create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Workplace',
            'scheme_type' => 'workplace',
            'pension_type' => 'occupational',
            'employee_contribution_percent' => 2.0,
            'employer_contribution_percent' => 2.0,
            'current_fund_value' => 50000,
            'annual_salary' => 55000,
        ]);

        $analysisData = [
            'profile' => $this->profile->toArray(),
            'summary' => [
                'income_gap' => 5000,
                'target_retirement_income' => 30000,
                'target_retirement_age' => 65,
            ],
            'annual_allowance' => [
                'has_excess' => false,
                'remaining_allowance' => 57800, // £60k - £2,200 contributions
                'carry_forward_available' => 0,
            ],
        ];

        $result = $this->service->evaluateAgentActions($analysisData);
        $employerMatch = collect($result['recommendations'])->firstWhere('category', 'Employer_match');

        expect($employerMatch)->toBeNull();
    });

    it('produces annual allowance exceeded recommendation', function () {
        $analysisData = [
            'profile' => $this->profile->toArray(),
            'summary' => [
                'income_gap' => 0,
                'target_retirement_income' => 30000,
                'target_retirement_age' => 65,
            ],
            'annual_allowance' => [
                'has_excess' => true,
                'excess_contributions' => 5000,
            ],
        ];

        $result = $this->service->evaluateAgentActions($analysisData);
        $aaRec = collect($result['recommendations'])->first(fn ($r) => str_contains($r['title'] ?? '', 'Annual Allowance'));

        expect($aaRec)->not->toBeNull()
            ->and($aaRec['description'])->toContain('5,000');
    });

    it('shows only increase contributions when user has a contributing pension and a dormant one', function () {
        // Active workplace pension (contributing — £23,200/yr = 16% of £145k)
        DCPension::create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Workplace Pension',
            'scheme_type' => 'workplace',
            'pension_type' => 'occupational',
            'employee_contribution_percent' => 8.0,
            'employer_contribution_percent' => 8.0,
            'current_fund_value' => 180000,
            'annual_salary' => 145000,
        ]);

        // Dormant SIPP (fund value but zero contributions)
        DCPension::create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Old SIPP',
            'scheme_type' => 'sipp',
            'pension_type' => 'personal',
            'employee_contribution_percent' => 0,
            'employer_contribution_percent' => 0,
            'monthly_contribution_amount' => 0,
            'current_fund_value' => 50000,
        ]);

        $analysisData = [
            'profile' => $this->profile->toArray(),
            'summary' => [
                'income_gap' => 10000,
                'target_retirement_income' => 30000,
                'target_retirement_age' => 65,
            ],
            'annual_allowance' => [
                'has_excess' => false,
                'remaining_allowance' => 36800, // £60k AA - £23,200 contributions
                'carry_forward_available' => 0,
            ],
        ];

        $result = $this->service->evaluateAgentActions($analysisData);
        $recs = $result['recommendations'];

        $startContrib = collect($recs)->firstWhere('category', 'Start_contributions');
        $increaseContrib = collect($recs)->firstWhere('category', 'Contribution_increase');

        expect($startContrib)->toBeNull('start_contributions should be suppressed when user has a contributing pension')
            ->and($increaseContrib)->not->toBeNull('increase contributions should still show');
    });

    it('shows only start contributions when user has no contributing pensions', function () {
        // Dormant pension only (fund value but zero contributions)
        DCPension::create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Old Workplace',
            'scheme_type' => 'workplace',
            'pension_type' => 'occupational',
            'employee_contribution_percent' => 0,
            'employer_contribution_percent' => 0,
            'monthly_contribution_amount' => 0,
            'current_fund_value' => 80000,
            'annual_salary' => 55000,
        ]);

        $analysisData = [
            'profile' => $this->profile->toArray(),
            'summary' => [
                'income_gap' => 10000,
                'target_retirement_income' => 30000,
                'target_retirement_age' => 65,
            ],
            'annual_allowance' => [
                'has_excess' => false,
                'remaining_allowance' => 60000, // Full AA available
                'carry_forward_available' => 0,
            ],
        ];

        $result = $this->service->evaluateAgentActions($analysisData);
        $recs = $result['recommendations'];

        $startContrib = collect($recs)->firstWhere('category', 'Start_contributions');
        $increaseContrib = collect($recs)->firstWhere('category', 'Contribution_increase');

        expect($increaseContrib)->toBeNull('contribution_increase should be suppressed when user has no contributing pensions')
            ->and($startContrib)->not->toBeNull('start contributions should still show');
    });

    it('produces retirement age adjustment when gap exceeds threshold', function () {
        $analysisData = [
            'profile' => $this->profile->toArray(),
            'summary' => [
                'income_gap' => 10000,
                'target_retirement_income' => 30000,
                'target_retirement_age' => 60,
            ],
            'annual_allowance' => [
                'has_excess' => false,
                'remaining_allowance' => 60000,
                'carry_forward_available' => 0,
            ],
        ];

        $result = $this->service->evaluateAgentActions($analysisData);
        $ageRec = collect($result['recommendations'])->firstWhere('category', 'Retirement Planning');

        expect($ageRec)->not->toBeNull()
            ->and($ageRec['title'])->toContain('Consider Adjusting Retirement Age');
    });
});

describe('evaluateGoalActions', function () {
    it('produces no-contribution recommendation for goals with zero contribution', function () {
        $goals = [
            [
                'id' => 1,
                'name' => 'Early Retirement Fund',
                'progress_percentage' => 30,
                'monthly_contribution' => 0,
                'required_monthly_contribution' => 200,
                'target_amount' => 100000,
                'is_on_track' => false,
                'months_remaining' => 60,
            ],
        ];

        $result = $this->service->evaluateGoalActions($goals);

        expect($result)->toHaveCount(1)
            ->and($result[0]['title'])->toContain('Start contributing')
            ->and($result[0]['source'])->toBe('goal')
            ->and($result[0]['goal_id'])->toBe(1);
    });

    it('produces behind-schedule recommendation for off-track goals', function () {
        $goals = [
            [
                'id' => 2,
                'name' => 'Pension Top-up',
                'progress_percentage' => 40,
                'monthly_contribution' => 100,
                'required_monthly_contribution' => 250,
                'target_amount' => 50000,
                'is_on_track' => false,
                'months_remaining' => 24,
            ],
        ];

        $result = $this->service->evaluateGoalActions($goals);

        expect($result)->toHaveCount(1)
            ->and($result[0]['title'])->toContain('behind schedule');
    });

    it('produces deadline-approaching recommendation for on-track goals near deadline with low progress', function () {
        $goals = [
            [
                'id' => 3,
                'name' => 'Retirement Pot',
                'progress_percentage' => 50,
                'monthly_contribution' => 100,
                'required_monthly_contribution' => 100,
                'target_amount' => 20000,
                'is_on_track' => true,
                'months_remaining' => 4,
            ],
        ];

        $result = $this->service->evaluateGoalActions($goals);

        expect($result)->toHaveCount(1)
            ->and($result[0]['title'])->toContain('target date is approaching');
    });

    it('suppresses no-contribution when pension contributions exist', function () {
        $goals = [
            [
                'id' => 1,
                'name' => 'Max Pension Contributions',
                'progress_percentage' => 30,
                'monthly_contribution' => 0,
                'required_monthly_contribution' => 200,
                'target_amount' => 60000,
                'is_on_track' => false,
                'months_remaining' => 12,
            ],
        ];

        // Simulate a workplace pension contributing £1,933/month (£23,200/yr)
        $dcPensions = collect([
            DCPension::create([
                'user_id' => $this->user->id,
                'scheme_name' => 'Workplace Pension',
                'scheme_type' => 'workplace',
                'pension_type' => 'occupational',
                'employee_contribution_percent' => 8.0,
                'employer_contribution_percent' => 8.0,
                'current_fund_value' => 180000,
                'annual_salary' => 145000,
            ]),
        ]);

        $result = $this->service->evaluateGoalActions($goals, $dcPensions);

        // Should not trigger no-contribution because pension contributions exist
        $noContrib = collect($result)->first(fn ($r) => str_contains($r['title'] ?? '', 'Start contributing'));
        expect($noContrib)->toBeNull();
    });

    it('suppresses behind-schedule when pension contributions cover the shortfall', function () {
        $goals = [
            [
                'id' => 2,
                'name' => 'Max Pension Contributions',
                'progress_percentage' => 40,
                'monthly_contribution' => 100,
                'required_monthly_contribution' => 1500,
                'target_amount' => 60000,
                'is_on_track' => false,
                'months_remaining' => 12,
            ],
        ];

        // Pension contributes £1,933/month — effective total £2,033 >= required £1,500
        $dcPensions = collect([
            DCPension::create([
                'user_id' => $this->user->id,
                'scheme_name' => 'Workplace Pension',
                'scheme_type' => 'workplace',
                'pension_type' => 'occupational',
                'employee_contribution_percent' => 8.0,
                'employer_contribution_percent' => 8.0,
                'current_fund_value' => 180000,
                'annual_salary' => 145000,
            ]),
        ]);

        $result = $this->service->evaluateGoalActions($goals, $dcPensions);

        // Should not trigger behind-schedule because pension contributions cover the shortfall
        $behindSchedule = collect($result)->first(fn ($r) => str_contains($r['title'] ?? '', 'behind schedule'));
        expect($behindSchedule)->toBeNull();
    });

    it('still triggers behind-schedule when pension contributions are insufficient', function () {
        $goals = [
            [
                'id' => 2,
                'name' => 'Max Pension Contributions',
                'progress_percentage' => 40,
                'monthly_contribution' => 100,
                'required_monthly_contribution' => 5000,
                'target_amount' => 60000,
                'is_on_track' => false,
                'months_remaining' => 12,
            ],
        ];

        // Pension contributes £1,933/month — effective total £2,033 < required £5,000
        $dcPensions = collect([
            DCPension::create([
                'user_id' => $this->user->id,
                'scheme_name' => 'Workplace Pension',
                'scheme_type' => 'workplace',
                'pension_type' => 'occupational',
                'employee_contribution_percent' => 8.0,
                'employer_contribution_percent' => 8.0,
                'current_fund_value' => 180000,
                'annual_salary' => 145000,
            ]),
        ]);

        $result = $this->service->evaluateGoalActions($goals, $dcPensions);

        $behindSchedule = collect($result)->first(fn ($r) => str_contains($r['title'] ?? '', 'behind schedule'));
        expect($behindSchedule)->not->toBeNull();
    });

    it('skips completed goals', function () {
        $goals = [
            [
                'id' => 4,
                'name' => 'Completed Goal',
                'progress_percentage' => 100,
                'monthly_contribution' => 200,
                'required_monthly_contribution' => 200,
                'target_amount' => 50000,
                'is_on_track' => true,
                'months_remaining' => 0,
            ],
        ];

        $result = $this->service->evaluateGoalActions($goals);

        expect($result)->toBeEmpty();
    });
});

describe('getWhatIfImpactType', function () {
    it('returns contribution for Employer_match category', function () {
        $result = $this->service->getWhatIfImpactType('Employer_match');

        expect($result)->toBe('contribution');
    });

    it('returns tax_optimisation for Tax Planning category', function () {
        $result = $this->service->getWhatIfImpactType('Tax Planning');

        expect($result)->toBe('tax_optimisation');
    });

    it('returns default for unknown category', function () {
        $result = $this->service->getWhatIfImpactType('Unknown Category');

        expect($result)->toBe('default');
    });
});

describe('template rendering', function () {
    it('renders title with placeholders', function () {
        $definition = RetirementActionDefinition::findByKey('employer_match');

        $rendered = $definition->renderTitle([
            'additional_percent' => '2.0',
            'scheme_name' => 'HSBC Workplace',
        ]);

        expect($rendered)->toContain('Maximise Employer Pension Match');
    });

    it('renders description with placeholders', function () {
        $definition = RetirementActionDefinition::findByKey('goal_no_contribution');

        $rendered = $definition->renderDescription([
            'goal_name' => 'Early Retirement',
            'required_monthly' => '£200',
            'target_amount' => '£100,000',
        ]);

        expect($rendered)->toContain('Early Retirement')
            ->and($rendered)->toContain('£200');
    });
});
