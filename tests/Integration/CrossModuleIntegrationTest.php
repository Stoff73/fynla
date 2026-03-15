<?php

declare(strict_types=1);

use App\Models\InvestmentActionDefinition;
use App\Models\SavingsActionDefinition;
use App\Models\TaxConfiguration;
use App\Services\Coordination\PriorityRanker;

describe('Cross-Module Integration', function () {

    // ── 5.3.1: Emergency fund ownership ────────────────────────────────────

    describe('Emergency Fund Ownership', function () {

        it('emergency fund is owned by Savings engine, not Investment', function () {
            // Seed the action definitions so the tables are populated
            $this->seed(\Database\Seeders\InvestmentActionDefinitionSeeder::class);
            $this->seed(\Database\Seeders\SavingsActionDefinitionSeeder::class);

            // Check SavingsActionDefinition has emergency fund triggers that are enabled
            $savingsTriggers = SavingsActionDefinition::where('key', 'like', 'emergency_fund_%')
                ->where('is_enabled', true)
                ->count();
            expect($savingsTriggers)->toBeGreaterThan(0);

            // Check Investment emergency fund triggers are DISABLED
            // The SavingsActionDefinitionSeeder disables these overlapping Investment keys
            $investmentEmergencyTriggers = InvestmentActionDefinition::whereIn('key', [
                'emergency_fund_critical',
                'emergency_fund_grow',
            ])->where('is_enabled', true)->count();
            expect($investmentEmergencyTriggers)->toBe(0);
        });

        it('SafetyCheckService gates surplus without producing standalone emergency fund recommendations', function () {
            // Ensure TaxConfiguration exists for SafetyCheckService
            TaxConfiguration::factory()->create(['is_active' => true]);
            app()->forgetInstance(\App\Services\TaxConfigService::class);

            $service = app(\App\Services\Investment\Recommendation\SafetyCheckService::class);

            // Context with critically low emergency fund
            $context = [
                'financial' => [
                    'monthly_disposable' => 500,
                    'disposable_income' => 6000,
                ],
                'debt' => [
                    'high_interest' => ['total_balance' => 0, 'total_monthly_payment' => 0],
                    'medium_interest' => ['total_balance' => 0, 'total_monthly_payment' => 0],
                ],
                'emergency_fund' => [
                    'runway_months' => 0.5,
                    'shortfall' => 10000,
                ],
                'personal' => ['employment_status' => 'employed'],
                'pensions' => ['dc_pensions' => []],
                'life_events' => [],
            ];

            $result = $service->check($context);

            // Should set surplus to zero when emergency fund is critically low
            expect($result['adjusted_surplus'])->toBe(0.0);
            expect($result['can_invest'])->toBeFalse();

            // Should not contain any standalone recommendation — only checks
            expect($result)->toHaveKeys([
                'adjusted_surplus',
                'original_surplus',
                'checks',
                'context_notes',
                'can_invest',
            ]);

            // Verify it produced a check (not a recommendation card)
            $emergencyCheck = collect($result['checks'])->firstWhere('check', 'emergency_fund_critical');
            expect($emergencyCheck)->not->toBeNull();
            expect($emergencyCheck['triggered'])->toBeTrue();
        });
    });

    // ── 5.3.2: ISA allowance shared across modules ────────────────────────

    describe('ISA Allowance Shared Across Modules', function () {

        it('ISA allowance is shared between Cash ISA and Stocks & Shares ISA', function () {
            // Ensure TaxConfiguration exists for TaxConfigService
            TaxConfiguration::factory()->create(['is_active' => true]);
            app()->forgetInstance(\App\Services\TaxConfigService::class);

            $user = \App\Models\User::factory()->create([
                'date_of_birth' => now()->subYears(35),
            ]);

            $taxConfig = app(\App\Services\TaxConfigService::class);
            $isaAllowance = $taxConfig->getISAAllowances()['annual_allowance'] ?? 20000;
            $taxYear = $taxConfig->getTaxYear();

            // Create a Cash ISA (Savings) with £8,000 subscribed this year
            \App\Models\SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'account_type' => 'cash_isa',
                'current_balance' => 8000,
                'isa_subscription_year' => $taxYear,
                'isa_subscription_amount' => 8000,
            ]);

            // Create a S&S ISA (Investment) with £7,000 subscribed this year
            \App\Models\Investment\InvestmentAccount::factory()->create([
                'user_id' => $user->id,
                'account_type' => 'isa',
                'current_value' => 7000,
                'isa_subscription_current_year' => 7000,
            ]);

            // Build context via UserContextBuilder
            $contextBuilder = app(\App\Services\Investment\Recommendation\UserContextBuilder::class);
            $context = $contextBuilder->build($user);

            // Total ISA used should be 8000 + 7000 = 15000
            expect($context['allowances']['isa_used'])->toBe(15000.0);

            // Remaining should be allowance - 15000
            $expectedRemaining = max(0, $isaAllowance - 15000);
            expect($context['allowances']['isa_remaining'])->toBe((float) $expectedRemaining);

            // The total must not exceed the annual ISA allowance
            expect($context['allowances']['isa_used'])->toBeLessThanOrEqual($isaAllowance);
        });

        it('ISA context accounts for both modules when allowance is fully used', function () {
            // Ensure TaxConfiguration exists for TaxConfigService
            TaxConfiguration::factory()->create(['is_active' => true]);
            app()->forgetInstance(\App\Services\TaxConfigService::class);

            $user = \App\Models\User::factory()->create([
                'date_of_birth' => now()->subYears(40),
            ]);

            $taxConfig = app(\App\Services\TaxConfigService::class);
            $taxYear = $taxConfig->getTaxYear();

            // Cash ISA: £12,000
            \App\Models\SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'account_type' => 'cash_isa',
                'current_balance' => 12000,
                'isa_subscription_year' => $taxYear,
                'isa_subscription_amount' => 12000,
            ]);

            // S&S ISA: £8,000
            \App\Models\Investment\InvestmentAccount::factory()->create([
                'user_id' => $user->id,
                'account_type' => 'isa',
                'current_value' => 8000,
                'isa_subscription_current_year' => 8000,
            ]);

            $contextBuilder = app(\App\Services\Investment\Recommendation\UserContextBuilder::class);
            $context = $contextBuilder->build($user);

            // Total = 20000, which equals the ISA allowance
            expect($context['allowances']['isa_used'])->toBe(20000.0);
            expect($context['allowances']['isa_remaining'])->toBe(0.0);
        });
    });

    // ── 5.3.3: CoordinatingAgent priority ordering ────────────────────────

    describe('PriorityRanker Cross-Module Ordering', function () {

        it('ranks recommendations from all modules', function () {
            $ranker = new PriorityRanker;

            // Simulate recommendations from all 6 modules
            $allRecommendations = [
                'protection' => [
                    ['title' => 'Increase life cover', 'coverage_gap' => 200000, 'adequacy_score' => 40],
                ],
                'savings' => [
                    ['title' => 'Build emergency fund', 'emergency_fund_months' => 1.5, 'emergency_fund_shortfall' => 8000],
                ],
                'investment' => [
                    ['title' => 'Open ISA', 'expected_benefit' => 15000, 'goal_probability' => 60],
                ],
                'retirement' => [
                    ['title' => 'Increase pension contributions', 'income_gap' => 12000, 'years_to_retirement' => 8],
                ],
                'estate' => [
                    ['title' => 'Write a will', 'iht_liability' => 100000, 'action_type' => 'will'],
                ],
                'goals' => [
                    ['title' => 'Emergency fund goal', 'category' => 'Safety Net'],
                ],
            ];

            $userContext = [];

            $ranked = $ranker->rankRecommendations($allRecommendations, $userContext);

            // Should return all 6 recommendations
            expect($ranked)->toHaveCount(6);

            // Each recommendation should have the expected scoring fields
            foreach ($ranked as $rec) {
                expect($rec)->toHaveKeys([
                    'module',
                    'priority_score',
                    'urgency_score',
                    'impact_score',
                    'ease_score',
                    'user_priority_score',
                    'timeline',
                ]);
            }

            // Should be sorted by priority_score descending
            for ($i = 0; $i < count($ranked) - 1; $i++) {
                expect($ranked[$i]['priority_score'])->toBeGreaterThanOrEqual($ranked[$i + 1]['priority_score']);
            }

            // All 6 modules should be represented
            $modules = array_unique(array_column($ranked, 'module'));
            expect($modules)->toContain('protection');
            expect($modules)->toContain('savings');
            expect($modules)->toContain('investment');
            expect($modules)->toContain('retirement');
            expect($modules)->toContain('estate');
            expect($modules)->toContain('goals');
        });

        it('skips non-recommendation keys like module_scores and available_surplus', function () {
            $ranker = new PriorityRanker;

            $allRecommendations = [
                'protection' => [
                    ['title' => 'Get life insurance', 'coverage_gap' => 300000],
                ],
                'module_scores' => ['protection' => 60, 'savings' => 40],
                'available_surplus' => 5000,
            ];

            $ranked = $ranker->rankRecommendations($allRecommendations, []);

            // Only the protection recommendation should be ranked
            expect($ranked)->toHaveCount(1);
            expect($ranked[0]['module'])->toBe('protection');
        });

        it('creates action plan with timeline grouping', function () {
            $ranker = new PriorityRanker;

            $allRecommendations = [
                'protection' => [
                    ['title' => 'Critical cover gap', 'coverage_gap' => 600000, 'adequacy_score' => 20],
                ],
                'savings' => [
                    ['title' => 'Low emergency fund', 'emergency_fund_months' => 0.5, 'emergency_fund_shortfall' => 15000],
                ],
                'estate' => [
                    ['title' => 'Long-term planning', 'iht_liability' => 30000, 'action_type' => 'trust'],
                ],
            ];

            $ranked = $ranker->rankRecommendations($allRecommendations, []);
            $plan = $ranker->createActionPlan($ranked);

            expect($plan)->toHaveKeys(['action_plan', 'summary']);
            expect($plan['action_plan'])->toHaveKeys(['immediate', 'short_term', 'medium_term', 'long_term']);
            expect($plan['summary']['total_actions'])->toBe(3);
        });

        it('groups recommendations by category', function () {
            $ranker = new PriorityRanker;

            $allRecommendations = [
                'protection' => [
                    ['title' => 'Protection rec 1'],
                    ['title' => 'Protection rec 2'],
                ],
                'savings' => [
                    ['title' => 'Savings rec 1'],
                ],
            ];

            $ranked = $ranker->rankRecommendations($allRecommendations, []);
            $grouped = $ranker->groupByCategory($ranked);

            expect($grouped)->toHaveKeys(['protection', 'savings', 'investment', 'retirement', 'estate', 'goals']);
            expect($grouped['protection'])->toHaveCount(2);
            expect($grouped['savings'])->toHaveCount(1);
            expect($grouped['investment'])->toHaveCount(0);
        });
    });

    // ── 5.3.4: No duplicate recommendations across modules ────────────────

    describe('No Duplicate Recommendations Across Modules', function () {

        it('no duplicate recommendations across Savings and Investment engines', function () {
            // Seed both sets of action definitions
            $this->seed(\Database\Seeders\InvestmentActionDefinitionSeeder::class);
            $this->seed(\Database\Seeders\SavingsActionDefinitionSeeder::class);

            // The 7 Investment savings triggers that overlap with Savings engine must be disabled
            $disabledKeys = [
                'emergency_fund_critical',
                'emergency_fund_grow',
                'switch_savings_rate',
                'isa_allowance_remaining',
                'surplus_to_isa',
                'surplus_to_pension',
                'surplus_to_bond',
            ];

            foreach ($disabledKeys as $key) {
                $def = InvestmentActionDefinition::where('key', $key)->first();
                expect($def)->not->toBeNull("Investment action definition '{$key}' should exist");
                expect($def->is_enabled)->toBeFalse("Investment trigger '{$key}' should be disabled");
            }
        });

        it('Savings engine emergency fund triggers remain enabled after seeding', function () {
            $this->seed(\Database\Seeders\SavingsActionDefinitionSeeder::class);

            $savingsEmergencyKeys = [
                'emergency_fund_critical',
                'emergency_fund_low',
                'emergency_fund_building',
                'emergency_fund_excess',
                'emergency_fund_no_data',
            ];

            foreach ($savingsEmergencyKeys as $key) {
                $def = SavingsActionDefinition::where('key', $key)->first();
                expect($def)->not->toBeNull("Savings action definition '{$key}' should exist");
                expect($def->is_enabled)->toBeTrue("Savings trigger '{$key}' should be enabled");
            }
        });

        it('Investment engine core investment triggers remain enabled', function () {
            $this->seed(\Database\Seeders\InvestmentActionDefinitionSeeder::class);
            $this->seed(\Database\Seeders\SavingsActionDefinitionSeeder::class);

            // These Investment-specific triggers should remain enabled
            $enabledKeys = [
                'risk_profile_missing',
                'no_holdings',
                'low_diversification',
                'high_total_fees',
                'high_fund_fees',
                'high_platform_fees',
                'rebalance_portfolio',
                'tax_loss_harvesting',
                'open_isa',
                'use_isa_allowance',
                'consider_bonds',
            ];

            foreach ($enabledKeys as $key) {
                $def = InvestmentActionDefinition::where('key', $key)->first();
                expect($def)->not->toBeNull("Investment action definition '{$key}' should exist");
                expect($def->is_enabled)->toBeTrue("Investment trigger '{$key}' should remain enabled");
            }
        });
    });

    // ── Additional: Data readiness services exist for all modules ──────────

    describe('Data Readiness Services', function () {

        it('all 5 modules have data readiness services', function () {
            expect(class_exists(\App\Services\Savings\SavingsDataReadinessService::class))->toBeTrue();
            expect(class_exists(\App\Services\Estate\EstateDataReadinessService::class))->toBeTrue();
            expect(class_exists(\App\Services\Investment\Recommendation\DataReadinessService::class))->toBeTrue();
            expect(class_exists(\App\Services\Protection\ProtectionDataReadinessService::class))->toBeTrue();
            expect(class_exists(\App\Services\Retirement\RetirementDataReadinessService::class))->toBeTrue();
        });
    });
});
