<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Coordination\CrossModuleStrategyService;
use App\Services\TaxConfigService;

function createCrossModuleStrategyService(?TaxConfigService $taxConfig = null): CrossModuleStrategyService
{
    if (! $taxConfig) {
        $taxConfig = Mockery::mock(TaxConfigService::class);
        $taxConfig->shouldReceive('getISAAllowances')
            ->andReturn(['annual_allowance' => 20000]);
        $taxConfig->shouldReceive('getCapitalGainsTax')
            ->andReturn(['annual_exempt_amount' => 3000]);
        $taxConfig->shouldReceive('getIncomeTax')
            ->andReturn(['personal_allowance' => 12570, 'basic_rate_limit' => 37700]);
        $taxConfig->shouldReceive('getPensionAllowances')
            ->andReturn(['annual_allowance' => 60000]);
    }

    return new CrossModuleStrategyService($taxConfig);
}

function createMockUser(int $age = 40): User
{
    $user = new User;
    $user->date_of_birth = now()->subYears($age)->format('Y-m-d');

    return $user;
}

afterEach(function () {
    Mockery::close();
});

describe('CrossModuleStrategyService', function () {
    describe('ISA sequencing strategy', function () {
        it('generates ISA before GIA strategy when user has GIA and unused ISA', function () {
            $service = createCrossModuleStrategyService();
            $user = createMockUser(40);

            $moduleAnalysis = [
                'investment' => [
                    'full_analysis' => [
                        'accounts' => [
                            ['account_type' => 'gia', 'holdings' => []],
                        ],
                    ],
                ],
                'tax_optimisation' => [
                    'allowance_usage' => [
                        'isa' => ['used' => 5000],
                    ],
                    'strategies' => [],
                ],
                'retirement' => ['full_analysis' => []],
                'protection' => ['full_analysis' => []],
                'savings' => [],
                'goals' => ['has_goals' => false],
                'estate' => ['monthly_income' => 0],
                'user' => ['age' => 40],
            ];

            $strategies = $service->generateCrossModuleStrategies($moduleAnalysis, $user);

            $isaStrategy = collect($strategies)->firstWhere('type', 'isa_before_gia');
            expect($isaStrategy)->not->toBeNull();
            expect($isaStrategy['priority'])->toBe('high');
            expect($isaStrategy['modules'])->toContain('investment');
            expect($isaStrategy['modules'])->toContain('tax_optimisation');
        });
    });

    describe('Pension vs ISA priority for higher-rate taxpayer', function () {
        it('generates pension before ISA strategy for higher-rate taxpayer with unused AA', function () {
            $service = createCrossModuleStrategyService();
            $user = createMockUser(45);

            $moduleAnalysis = [
                'investment' => ['full_analysis' => []],
                'tax_optimisation' => [
                    'allowance_usage' => [],
                    'strategies' => [],
                ],
                'retirement' => [
                    'full_analysis' => [
                        'total_contributions' => 20000,
                    ],
                ],
                'protection' => ['full_analysis' => []],
                'savings' => [],
                'goals' => ['has_goals' => false],
                'estate' => ['monthly_income' => 6000], // £72k annual = higher rate
                'user' => ['age' => 45],
            ];

            $strategies = $service->generateCrossModuleStrategies($moduleAnalysis, $user);

            $pensionStrategy = collect($strategies)->firstWhere('type', 'pension_before_isa');
            expect($pensionStrategy)->not->toBeNull();
            expect($pensionStrategy['priority'])->toBe('high');
            expect($pensionStrategy['modules'])->toContain('retirement');
            expect($pensionStrategy['modules'])->toContain('tax_optimisation');
        });
    });

    describe('De-risking warning for near-retiree', function () {
        it('generates de-risking strategy when near retirement with high equity', function () {
            $service = createCrossModuleStrategyService();
            $user = createMockUser(64); // 3 years to retirement

            $moduleAnalysis = [
                'investment' => [
                    'full_analysis' => [
                        'asset_allocation' => ['equity' => 90],
                    ],
                ],
                'tax_optimisation' => [
                    'allowance_usage' => [],
                    'strategies' => [],
                ],
                'retirement' => ['full_analysis' => []],
                'protection' => ['full_analysis' => []],
                'savings' => [],
                'goals' => ['has_goals' => false],
                'estate' => ['monthly_income' => 0],
                'user' => ['age' => 64],
            ];

            $strategies = $service->generateCrossModuleStrategies($moduleAnalysis, $user);

            $deriskStrategy = collect($strategies)->firstWhere('type', 'derisking_near_retirement');
            expect($deriskStrategy)->not->toBeNull();
            expect($deriskStrategy['priority'])->toBe('high');
            expect($deriskStrategy['modules'])->toContain('investment');
            expect($deriskStrategy['modules'])->toContain('retirement');
        });
    });

    describe('Short-term goal cash recommendation', function () {
        it('generates cash recommendation for short-term equity-funded goal', function () {
            $service = createCrossModuleStrategyService();
            $user = createMockUser(35);

            $moduleAnalysis = [
                'investment' => ['full_analysis' => []],
                'tax_optimisation' => [
                    'allowance_usage' => [],
                    'strategies' => [],
                ],
                'retirement' => ['full_analysis' => []],
                'protection' => ['full_analysis' => []],
                'savings' => [],
                'goals' => [
                    'has_goals' => true,
                    'goals' => [
                        [
                            'timeframe_years' => 2,
                            'linked_account_type' => 'stocks_shares_isa',
                        ],
                    ],
                ],
                'estate' => ['monthly_income' => 0],
                'user' => ['age' => 35],
            ];

            $strategies = $service->generateCrossModuleStrategies($moduleAnalysis, $user);

            $goalStrategy = collect($strategies)->firstWhere('type', 'short_term_goal_cash');
            expect($goalStrategy)->not->toBeNull();
            expect($goalStrategy['priority'])->toBe('high');
            expect($goalStrategy['modules'])->toContain('investment');
            expect($goalStrategy['modules'])->toContain('goals');
        });
    });

    describe('Income protection phase-out near retirement', function () {
        it('generates protection review strategy when near retirement with income protection', function () {
            $service = createCrossModuleStrategyService();
            $user = createMockUser(60); // 7 years to retirement

            $moduleAnalysis = [
                'investment' => ['full_analysis' => []],
                'tax_optimisation' => [
                    'allowance_usage' => [],
                    'strategies' => [],
                ],
                'retirement' => ['full_analysis' => []],
                'protection' => [
                    'full_analysis' => [
                        'policies' => [
                            ['policy_type' => 'income_protection'],
                        ],
                    ],
                ],
                'savings' => [],
                'goals' => ['has_goals' => false],
                'estate' => ['monthly_income' => 0],
                'user' => ['age' => 60],
            ];

            $strategies = $service->generateCrossModuleStrategies($moduleAnalysis, $user);

            $protectionStrategy = collect($strategies)->firstWhere('type', 'income_protection_retirement');
            expect($protectionStrategy)->not->toBeNull();
            expect($protectionStrategy['priority'])->toBe('medium');
            expect($protectionStrategy['modules'])->toContain('protection');
            expect($protectionStrategy['modules'])->toContain('retirement');
        });
    });

    describe('No strategies when nothing applicable', function () {
        it('returns empty array when no cross-module opportunities exist', function () {
            $service = createCrossModuleStrategyService();
            $user = createMockUser(35);

            $moduleAnalysis = [
                'investment' => ['full_analysis' => []],
                'tax_optimisation' => [
                    'allowance_usage' => [],
                    'strategies' => [],
                ],
                'retirement' => ['full_analysis' => []],
                'protection' => ['full_analysis' => []],
                'savings' => [],
                'goals' => ['has_goals' => false],
                'estate' => ['monthly_income' => 0],
                'user' => ['age' => 35],
            ];

            $strategies = $service->generateCrossModuleStrategies($moduleAnalysis, $user);

            expect($strategies)->toBeArray();
            expect($strategies)->toBeEmpty();
        });
    });

    describe('Strategy sorting', function () {
        it('sorts strategies by priority with high first', function () {
            $service = createCrossModuleStrategyService();
            $user = createMockUser(64); // Near retirement

            $moduleAnalysis = [
                'investment' => [
                    'full_analysis' => [
                        'accounts' => [
                            ['account_type' => 'gia', 'holdings' => []],
                        ],
                        'asset_allocation' => ['equity' => 90],
                    ],
                ],
                'tax_optimisation' => [
                    'allowance_usage' => [
                        'isa' => ['used' => 5000],
                    ],
                    'strategies' => [],
                ],
                'retirement' => ['full_analysis' => []],
                'protection' => [
                    'full_analysis' => [
                        'policies' => [
                            ['policy_type' => 'income_protection'],
                        ],
                    ],
                ],
                'savings' => [],
                'goals' => ['has_goals' => false],
                'estate' => ['monthly_income' => 0],
                'user' => ['age' => 64],
            ];

            $strategies = $service->generateCrossModuleStrategies($moduleAnalysis, $user);

            expect($strategies)->not->toBeEmpty();

            // All high priority strategies should come before medium/low
            $foundMedium = false;
            foreach ($strategies as $strategy) {
                if ($strategy['priority'] === 'medium') {
                    $foundMedium = true;
                }
                if ($foundMedium && $strategy['priority'] === 'high') {
                    $this->fail('High priority strategy found after medium priority');
                }
            }

            expect(true)->toBeTrue();
        });
    });
});
