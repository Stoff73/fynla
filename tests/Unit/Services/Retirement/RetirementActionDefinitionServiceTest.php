<?php

declare(strict_types=1);

use App\Agents\RetirementAgent;
use App\Models\DCPension;
use App\Models\RetirementActionDefinition;
use App\Models\RetirementProfile;
use App\Models\User;
use App\Services\Retirement\RetirementActionDefinitionService;
use App\Services\TaxConfigService;
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

describe('dataCompletenessActions', function () {
    it('asks for a missing pension value even when the user has no retirement profile yet', function () {
        $user = User::factory()->create(['is_preview_user' => false]);
        $pension = DCPension::create([
            'user_id' => $user->id,
            'scheme_name' => 'Workplace Pension',
            'scheme_type' => 'workplace',
            'pension_type' => 'occupational',
            'current_fund_value' => 0,
        ]);

        $actions = $this->service->dataCompletenessActions($user);

        expect($actions)->toHaveCount(1)
            ->and($actions[0]['title'])->toBe('Add the current value of your Workplace Pension')
            ->and($actions[0]['account_id'])->toBe($pension->id)
            ->and($this->service->dataCompletenessActions(User::factory()->create()))->toBe([]);
    });

    it('is carried on the analysis response when the readiness gate is closed', function () {
        $user = User::factory()->create(['is_preview_user' => false]);
        DCPension::create([
            'user_id' => $user->id,
            'scheme_name' => 'Workplace Pension',
            'scheme_type' => 'workplace',
            'pension_type' => 'occupational',
            'current_fund_value' => 0,
        ]);

        $analysis = app(RetirementAgent::class)->analyze($user->id);

        expect($analysis['data']['can_proceed'] ?? null)->toBeFalse()
            ->and(collect($analysis['data']['recommendations'] ?? [])->pluck('title')->all())
            ->toBe(['Add the current value of your Workplace Pension']);
    });
});

describe('evaluateAgentActions', function () {
    it('asks for the current value of each pension whose value was never entered — one action per pension', function () {
        // CSJ 2026-09-15: not knowing the value at onboarding is fine; the
        // Retirement actions ask for it afterwards.
        $missing = DCPension::create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Aviva Workplace Pension',
            'scheme_type' => 'workplace',
            'pension_type' => 'occupational',
            'employee_contribution_percent' => 5.0,
            'employer_contribution_percent' => 5.0,
            'current_fund_value' => 0,
            'annual_salary' => 55000,
        ]);
        DCPension::create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Vanguard SIPP',
            'scheme_type' => 'personal',
            'pension_type' => 'sipp',
            'current_fund_value' => 40000,
        ]);

        $recs = $this->service->evaluateAgentActions([
            'profile' => $this->profile->toArray(),
            'summary' => ['income_gap' => 0, 'target_retirement_income' => 30000, 'target_retirement_age' => 65],
            'annual_allowance' => ['has_excess' => false, 'remaining_allowance' => 60000, 'carry_forward_available' => 0],
        ])['recommendations'];

        $valueActions = collect($recs)->where('category', 'Pension_value')->values();
        expect($valueActions)->toHaveCount(1)
            ->and($valueActions[0]['account_id'])->toBe($missing->id)
            ->and($valueActions[0]['title'])->toBe('Add the current value of your Aviva Workplace Pension')
            ->and($valueActions[0]['scope'])->toBe('account');
    });

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
            ->and($employerMatch['title'])->toBe('Check your employer match on Test Workplace')
            ->and($employerMatch['definition_key'])->toBe('employer_match')
            ->and($employerMatch['figures'])->toMatchArray(['employee_percent' => '3.0', 'additional_percent' => '2.0']);
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

    it('warns when the Annual Allowance is exceeded, which no Tax plan card does', function () {
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

        $aaRec = collect($this->service->evaluateAgentActions($analysisData)['recommendations'])->firstWhere('definition_key', 'annual_allowance_exceeded');

        expect($aaRec['title'])->toBe('You have paid £5,000 more into pensions than your allowance this year')
            ->and($aaRec['figures'])->toMatchArray(['carry_forward_years' => '3', 'mpaa_applies' => false]);
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
                'target_retirement_income' => 150000, // above what the pensions project, so there is a shortfall
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

        // CSJ 2026-10-01 (D2): one income card; the folded actions are its reasons.
        $keys = collect($recs)->pluck('definition_key')->all();
        $card = collect($recs)->firstWhere('definition_key', 'retirement_income_position');

        expect($keys)->not->toContain('start_contributions')->not->toContain('contribution_increase')
            ->and($card)->not->toBeNull()
            ->and($card['figures'])->toHaveKey('contribution_increase')
            ->and($card['figures'])->not->toHaveKey('start_contributions');
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

        $card = collect($recs)->firstWhere('definition_key', 'retirement_income_position');

        expect($card)->not->toBeNull()
            ->and($card['figures'])->toHaveKey('start_contributions')
            ->and($card['figures'])->not->toHaveKey('contribution_increase');
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
        $card = collect($result['recommendations'])->firstWhere('definition_key', 'retirement_income_position');

        expect(collect($result['recommendations'])->pluck('definition_key')->all())->not->toContain('adjust_retirement_age')
            ->and($card['figures'])->toHaveKey('adjust_retirement_age')
            // No pensions recorded: the planning projection is nothing, so the whole target is short.
            ->and($card['title'])->toBe('Your retirement income is about £30,000 a year short of your target');
    });

    it('suppresses contribution-increase from age 75 (no tax relief)', function () {
        $this->user->update([
            'date_of_birth' => now()->subYears(77)->toDateString(),
            'annual_employment_income' => 20000,
        ]);

        DCPension::create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Workplace',
            'scheme_type' => 'workplace',
            'pension_type' => 'occupational',
            'employee_contribution_percent' => 3.0,
            'employer_contribution_percent' => 3.0,
            'current_fund_value' => 50000,
            'annual_salary' => 20000,
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
                'remaining_allowance' => 58800,
                'carry_forward_available' => 0,
            ],
        ];

        $result = $this->service->evaluateAgentActions($analysisData);
        // No relief from 75 (FA 2004 s188(3)(a)): no income card either.
        expect(collect($result['recommendations'])->pluck('definition_key')->all())
            ->not->toContain('contribution_increase')->not->toContain('retirement_income_position');
    });

    it('does not suggest adjusting retirement age to a retired user', function () {
        $this->user->update(['employment_status' => 'retired']);

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
        expect(collect($result['recommendations'])->pluck('definition_key')->all())
            ->not->toContain('adjust_retirement_age')->not->toContain('retirement_income_position');
    });

    it('does not suggest adjusting retirement age past the target age', function () {
        // Already older than the target retirement age — delaying a retirement
        // that has effectively happened is not actionable advice.
        $this->user->update(['date_of_birth' => now()->subYears(72)->toDateString()]);

        $analysisData = [
            'profile' => $this->profile->toArray(),
            'summary' => [
                'income_gap' => 10000,
                'target_retirement_income' => 30000,
                'target_retirement_age' => 65,
            ],
            'annual_allowance' => [
                'has_excess' => false,
                'remaining_allowance' => 60000,
                'carry_forward_available' => 0,
            ],
        ];

        $result = $this->service->evaluateAgentActions($analysisData);
        expect(collect($result['recommendations'])->pluck('definition_key')->all())
            ->not->toContain('adjust_retirement_age')->not->toContain('retirement_income_position');
    });

    it('asks for enhanced annuity quotes from the user\'s own smoking answer, with no uplift figure (8a)', function () {
        $this->user->update(['smoking_status' => 'yes', 'health_status' => 'yes']);
        DCPension::create([
            'user_id' => $this->user->id, 'scheme_name' => 'Test Workplace', 'scheme_type' => 'workplace',
            'pension_type' => 'occupational', 'current_fund_value' => 200000,
        ]);

        $card = collect($this->service->evaluateAgentActions([
            'user_id' => $this->user->id,
            'profile' => $this->profile->toArray(),
            'summary' => ['income_gap' => 0, 'target_retirement_income' => 30000, 'target_retirement_age' => 65],
            'annual_allowance' => ['has_excess' => false, 'remaining_allowance' => 56700, 'carry_forward_available' => 0],
        ])['recommendations'])->firstWhere('definition_key', 'enhanced_annuity_eligible');

        expect($card)->not->toBeNull()
            ->and($card)->not->toHaveKey('enhancement_factor')
            ->and(json_encode($card['decision_trace']))->not->toMatch('/\d+(\.\d+)?%|enhancement factor|per year more/i');
    });

    it('gives a retiree with no retirement profile the enhanced annuity card (8a)', function () {
        $retiree = User::factory()->create(['employment_status' => 'retired', 'smoking_status' => 'never', 'health_status' => 'no_existing', 'is_preview_user' => false]);
        DCPension::create([
            'user_id' => $retiree->id, 'scheme_name' => 'SIPP', 'scheme_type' => 'personal',
            'pension_type' => 'personal', 'current_fund_value' => 200000,
        ]);

        $keys = array_column($this->service->evaluateAgentActions(['user_id' => $retiree->id])['recommendations'], 'definition_key');

        expect($keys)->toContain('enhanced_annuity_eligible');
    });

    it('gives no enhanced annuity card when smoking and health are not answered (8a)', function () {
        $this->user->update(['smoking_status' => null, 'health_status' => null]);
        DCPension::create([
            'user_id' => $this->user->id, 'scheme_name' => 'Test Workplace', 'scheme_type' => 'workplace',
            'pension_type' => 'occupational', 'current_fund_value' => 200000,
        ]);

        $keys = array_column($this->service->evaluateAgentActions([
            'user_id' => $this->user->id,
            'profile' => $this->profile->toArray(),
            'summary' => ['income_gap' => 0, 'target_retirement_income' => 30000, 'target_retirement_age' => 65],
            'annual_allowance' => ['has_excess' => false, 'remaining_allowance' => 56700, 'carry_forward_available' => 0],
        ])['recommendations'], 'definition_key');

        expect($keys)->not->toContain('enhanced_annuity_eligible');
    });

    it('carries its definition key and figures on every card', function () {
        DCPension::create([
            'user_id' => $this->user->id, 'scheme_name' => 'Test Workplace', 'scheme_type' => 'workplace',
            'pension_type' => 'occupational', 'employee_contribution_percent' => 3.0, 'employer_contribution_percent' => 3.0,
            'current_fund_value' => 50000, 'annual_salary' => 55000,
        ]);

        $recs = $this->service->evaluateAgentActions([
            'profile' => $this->profile->toArray(),
            'summary' => ['income_gap' => 0, 'target_retirement_income' => 30000, 'target_retirement_age' => 65],
            'annual_allowance' => ['has_excess' => false, 'remaining_allowance' => 56700, 'carry_forward_available' => 0],
        ])['recommendations'];

        expect($recs)->not->toBeEmpty();
        foreach ($recs as $rec) {
            expect($rec['definition_key'] ?? null)->toBeString()
                ->and(RetirementActionDefinition::where('key', $rec['definition_key'])->exists())->toBeTrue();
        }
    });

    it('folds the three charge actions on one pension into one charges card (D3)', function () {
        $pension = DCPension::create([
            'user_id' => $this->user->id, 'scheme_name' => 'Costly SIPP', 'provider' => 'Acme', 'scheme_type' => 'sipp',
            'pension_type' => 'sipp', 'current_fund_value' => 100000, 'platform_fee_percent' => 1.2,
        ]);
        $other = DCPension::create([
            'user_id' => $this->user->id, 'scheme_name' => 'Second SIPP', 'provider' => 'Acme', 'scheme_type' => 'sipp',
            'pension_type' => 'sipp', 'current_fund_value' => 50000, 'platform_fee_percent' => 1.5,
        ]);

        $recs = collect($this->service->evaluateAgentActions([
            'profile' => $this->profile->toArray(),
            'summary' => ['income_gap' => 0, 'target_retirement_income' => 30000, 'target_retirement_age' => 65],
            'annual_allowance' => ['has_excess' => false, 'remaining_allowance' => 60000, 'carry_forward_available' => 0],
        ])['recommendations']);

        $cards = $recs->where('definition_key', 'pension_charges_review')->values();
        expect($recs->pluck('definition_key')->intersect(['high_pension_total_fees', 'high_pension_platform_fees', 'high_pension_fund_fees']))->toBeEmpty()
            ->and($cards)->toHaveCount(2)
            ->and($cards->pluck('account_id')->sort()->values()->all())->toBe([$pension->id, $other->id])
            ->and($cards->firstWhere('account_id', $pension->id)['title'])->toBe('Review the charges on Acme Costly SIPP')
            ->and($cards->firstWhere('account_id', $pension->id)['description'])->toContain('total charges of 1.20% a year (£1,200)')
            ->and($cards->firstWhere('account_id', $pension->id)['description'])->toContain('(£1,200) and a platform fee of 1.20%')
            ->and($cards->firstWhere('account_id', $other->id)['description'])->toContain('1.50%');
    });

    it('makes the auto-enrolment minimum a reason on the employer match card (D3)', function () {
        DCPension::create([
            'user_id' => $this->user->id, 'scheme_name' => 'Test Workplace', 'scheme_type' => 'workplace',
            'pension_type' => 'occupational', 'employee_contribution_percent' => 2.0, 'employer_contribution_percent' => 2.0,
            'current_fund_value' => 50000, 'annual_salary' => 55000,
        ]);

        $recs = collect($this->service->evaluateAgentActions([
            'profile' => $this->profile->toArray(),
            'summary' => ['income_gap' => 0, 'target_retirement_income' => 30000, 'target_retirement_age' => 65],
            'annual_allowance' => ['has_excess' => false, 'remaining_allowance' => 57800, 'carry_forward_available' => 0],
        ])['recommendations']);

        $match = $recs->firstWhere('definition_key', 'employer_match');
        expect($recs->pluck('definition_key')->all())->not->toContain('auto_enrolment_below_minimum')
            ->and($match['figures']['auto_enrolment_below_minimum'] ?? null)->toBeTrue()
            ->and($match['figures']['minimum_percent'])->toBe('8')
            ->and($match['description'])->toContain('auto-enrolment minimum is 8%');
    });

    it('gives a retired user drawing their pension no saving cards (D4)', function () {
        $this->user->update(['employment_status' => 'retired', 'annual_employment_income' => 0, 'date_of_birth' => now()->subYears(62)->toDateString()]);
        DCPension::create([
            'user_id' => $this->user->id, 'scheme_name' => 'Old Workplace', 'scheme_type' => 'workplace',
            'pension_type' => 'occupational', 'employee_contribution_percent' => 0, 'employer_contribution_percent' => 0,
            'monthly_contribution_amount' => 0, 'current_fund_value' => 80000, 'annual_drawdown_income' => 6000,
        ]);

        $keys = collect($this->service->evaluateAgentActions([
            'profile' => $this->profile->toArray(),
            'summary' => ['income_gap' => 10000, 'target_retirement_income' => 30000, 'target_retirement_age' => 65, 'years_to_retirement' => 3],
            'annual_allowance' => ['has_excess' => false, 'remaining_allowance' => 60000, 'carry_forward_available' => 0],
        ])['recommendations'])->pluck('definition_key')->all();

        expect($keys)->not->toContain('retirement_income_position')
            ->not->toContain('start_contributions')
            ->not->toContain('contribution_increase')
            ->not->toContain('employer_match')
            ->not->toContain('auto_enrolment_below_minimum')
            ->and($keys)->toContain('state_pension_no_forecast');
    });

    it('keeps the income card for someone drawing while still working (D4)', function () {
        $this->user->update(['employment_status' => 'employed', 'date_of_birth' => now()->subYears(58)->toDateString()]);
        DCPension::create([
            'user_id' => $this->user->id, 'scheme_name' => 'Old SIPP', 'scheme_type' => 'sipp', 'pension_type' => 'sipp',
            'current_fund_value' => 40000, 'annual_drawdown_income' => 3000, 'has_flexibly_accessed' => true,
        ]);

        $keys = collect($this->service->evaluateAgentActions([
            'profile' => $this->profile->toArray(),
            'summary' => ['income_gap' => 10000, 'target_retirement_income' => 30000, 'target_retirement_age' => 65, 'years_to_retirement' => 7],
            'annual_allowance' => ['has_excess' => false, 'remaining_allowance' => 60000, 'carry_forward_available' => 0],
        ])['recommendations'])->pluck('definition_key')->all();

        expect($keys)->toContain('retirement_income_position');
    });

    it('still gives a household with no retirement profile the actions that need no target (D4)', function () {
        $this->profile->delete();
        DCPension::create([
            'user_id' => $this->user->id, 'scheme_name' => 'Costly SIPP', 'provider' => 'Acme', 'scheme_type' => 'sipp',
            'pension_type' => 'sipp', 'current_fund_value' => 100000, 'platform_fee_percent' => 1.2,
            'employee_contribution_percent' => 0, 'employer_contribution_percent' => 0, 'monthly_contribution_amount' => 0,
        ]);

        $keys = collect($this->service->evaluateAgentActions([
            'profile' => null,
            'user_id' => $this->user->id,
            'summary' => ['income_gap' => null, 'target_retirement_income' => null],
            'annual_allowance' => ['has_excess' => false, 'remaining_allowance' => 60000, 'carry_forward_available' => 0],
        ])['recommendations'])->pluck('definition_key')->all();

        expect($keys)->toContain('state_pension_no_forecast')
            ->toContain('pension_charges_review')
            ->not->toContain('start_contributions')
            ->not->toContain('retirement_income_position');
    });

    it('leaves the pension relief and salary sacrifice saving to the Tax plan (D1)', function () {
        expect(RetirementActionDefinition::whereIn('key', ['tax_relief', 'salary_sacrifice_available'])->where('is_enabled', true)->count())->toBe(0);
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

        expect($rendered)->toBe('Check your employer match on HSBC Workplace');
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

it('reads the full new State Pension from tax configuration rather than a literal', function () {
    $source = file_get_contents(app_path('Services/Retirement/RetirementActionDefinitionService.php'));
    expect($source)->not->toContain('11502');
    expect((float) app(TaxConfigService::class)->get('pension.state_pension.full_new_state_pension'))->toBeGreaterThan(12000);
});

it('never shows the care costs card: care costs were taken out (CSJ 2026-10-01)', function () {
    $this->user->update(['date_of_birth' => now()->subYears(60)->toDateString()]);
    $this->profile->update(['current_age' => 60]);
    $analysisData = [
        'profile' => $this->profile->fresh()->toArray(),
        'summary' => ['income_gap' => 0, 'target_retirement_income' => 30000, 'target_retirement_age' => 65],
        'annual_allowance' => ['has_excess' => false, 'remaining_allowance' => 60000, 'carry_forward_available' => 0],
    ];

    $keys = collect($this->service->evaluateAgentActions($analysisData)['recommendations'])->pluck('definition_key')->all();

    expect($keys)->not->toContain('care_costs_not_modelled');
});
