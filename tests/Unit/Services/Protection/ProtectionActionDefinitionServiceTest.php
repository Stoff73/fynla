<?php

declare(strict_types=1);

use App\Models\ProtectionActionDefinition;
use App\Services\Protection\ProtectionActionDefinitionService;
use App\Services\TaxConfigService;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(ProtectionActionDefinitionSeeder::class);

    $taxConfig = app(TaxConfigService::class);
    $this->service = new ProtectionActionDefinitionService($taxConfig);
});

// =========================================================================
// Gap detection triggers
// =========================================================================

/**
 * The cover position a real comprehensive plan carries (ComprehensiveProtectionPlanService,
 * ProtectionCoverPosition), built from a fixture's coverage_analysis rows. Gap rules now fire
 * as reasons on their cover type's position card (CSJ 2026-09-29).
 */
function withPosition(array $plan): array
{
    $map = ['life' => 'life_insurance', 'critical_illness' => 'critical_illness', 'income_protection' => 'income_protection'];
    foreach ($map as $type => $row) {
        $r = $plan['coverage_analysis'][$row] ?? ['need' => 0, 'coverage' => 0, 'gap' => 0];
        $plan['cover_position'][$type] = [
            'need' => (float) $r['need'], 'own_cover' => (float) $r['coverage'], 'employer_cover' => 0.0, 'total_cover' => (float) $r['coverage'],
            'short_by' => (float) $r['gap'], 'over_by' => 0.0, 'employer_share' => 0.0, 'depends_on_job' => false,
            'status' => $r['gap'] > 0 ? 'short' : 'covered', 'unit' => $type === 'income_protection' ? 'monthly' : 'lump_sum',
        ];
    }

    return $plan;
}

describe('gap detection triggers', function () {
    it('fires life_insurance_gap when life insurance gap exceeds threshold', function () {
        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 150000, 'coverage' => 100000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 50000, 'need' => 50000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions(withPosition($plan));

        // The gap fires as a reason on the life position card (CSJ 2026-09-29).
        $rec = collect($result)->firstWhere('definition_key', 'life_cover_position');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('Life Insurance')
            ->and($rec['figures']['life_insurance_gap'] ?? false)->toBeTrue()
            ->and(collect($result)->pluck('definition_key'))->not->toContain('life_insurance_gap');
    });

    it('fires critical_illness_gap when critical illness gap exists', function () {
        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 0, 'coverage' => 250000, 'need' => 250000],
                'critical_illness' => ['gap' => 75000, 'coverage' => 25000, 'need' => 100000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions(withPosition($plan));

        $rec = collect($result)->firstWhere('definition_key', 'critical_illness_position');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('Critical Illness')
            ->and($rec['figures']['critical_illness_gap'] ?? false)->toBeTrue();
    });

    it('fires income_protection_gap when income protection gap exists', function () {
        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 0, 'coverage' => 250000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 100000, 'need' => 100000],
                'income_protection' => ['gap' => 500, 'coverage' => 1500, 'need' => 2000],
            ],
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions(withPosition($plan));

        $rec = collect($result)->firstWhere('definition_key', 'income_protection_position');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('Income Protection')
            ->and($rec['figures']['income_protection_gap'] ?? false)->toBeTrue();
    });

    it('does NOT fire gap actions when coverage is adequate', function () {
        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 0, 'coverage' => 250000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 100000, 'need' => 100000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        $gapRecs = collect($result)->filter(fn ($r) => in_array($r['category'], ['Life Insurance', 'Critical Illness', 'Income Protection']));
        // Only gap-sourced actions should not fire; strategy actions may still fire
        $gapDefinitionKeys = ['life_insurance_gap', 'critical_illness_gap', 'income_protection_gap'];
        $firedGapKeys = ProtectionActionDefinition::whereIn('key', $gapDefinitionKeys)
            ->get()
            ->filter(function ($def) use ($result) {
                return collect($result)->contains(fn ($r) => $r['action'] === $def->renderTitle([]));
            });

        expect($firedGapKeys)->toHaveCount(0);
    });
});

// =========================================================================
// Strategy recommendation triggers
// =========================================================================

describe('strategy recommendation triggers', function () {
    it('fires increase_life_cover when strategy recommends life insurance', function () {
        ProtectionActionDefinition::where('key', 'increase_life_cover')->update(['is_enabled' => true]);

        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 0, 'coverage' => 250000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 100000, 'need' => 100000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'optimized_strategy' => [
                'recommendations' => [
                    [
                        'category' => 'Life Insurance',
                        'action' => 'Increase life cover to £400,000',
                        'details' => 'Level term policy recommended',
                        'coverage_amount' => 400000,
                        'estimated_monthly_cost' => 25,
                        'priority' => 2,
                        'importance' => 'High',
                        'timeframe' => 'Within 1 month',
                    ],
                ],
            ],
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        $rec = collect($result)->first(fn ($r) => $r['category'] === 'Life Insurance' && $r['estimated_cost'] > 0);
        expect($rec)->not->toBeNull()
            ->and($rec['estimated_cost'])->toBe(25.0);
    });

    it('fires add_critical_illness when strategy recommends critical illness cover', function () {
        ProtectionActionDefinition::where('key', 'add_critical_illness')->update(['is_enabled' => true]);

        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 0, 'coverage' => 250000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 100000, 'need' => 100000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'optimized_strategy' => [
                'recommendations' => [
                    [
                        'category' => 'Critical Illness',
                        'action' => 'Add critical illness cover of £150,000',
                        'details' => 'Standalone critical illness policy recommended',
                        'coverage_amount' => 150000,
                        'estimated_monthly_cost' => 35,
                        'priority' => 2,
                        'importance' => 'High',
                        'timeframe' => 'Within 3 months',
                    ],
                ],
            ],
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        $rec = collect($result)->first(fn ($r) => $r['category'] === 'Critical Illness' && $r['estimated_cost'] > 0);
        expect($rec)->not->toBeNull()
            ->and($rec['estimated_cost'])->toBe(35.0);
    });

    it('fires add_income_protection when strategy recommends income protection', function () {
        ProtectionActionDefinition::where('key', 'add_income_protection')->update(['is_enabled' => true]);

        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 0, 'coverage' => 250000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 100000, 'need' => 100000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'optimized_strategy' => [
                'recommendations' => [
                    [
                        'category' => 'Income Protection',
                        'action' => 'Add income protection of £2,500 per month',
                        'details' => 'Long-term income protection policy recommended',
                        'monthly_benefit' => 2500,
                        'estimated_monthly_cost' => 45,
                        'priority' => 2,
                        'importance' => 'High',
                        'timeframe' => 'Within 3 months',
                    ],
                ],
            ],
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        $rec = collect($result)->first(fn ($r) => $r['category'] === 'Income Protection' && $r['estimated_cost'] > 0);
        expect($rec)->not->toBeNull()
            ->and($rec['estimated_cost'])->toBe(45.0);
    });

    it('does NOT fire strategy action when no matching recommendations exist', function () {
        // Enable all strategy definitions so we can verify they don't fire
        ProtectionActionDefinition::whereIn('key', ['increase_life_cover', 'add_critical_illness', 'add_income_protection'])
            ->update(['is_enabled' => true]);

        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 0, 'coverage' => 250000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 100000, 'need' => 100000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'optimized_strategy' => [
                'recommendations' => [],
            ],
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        $strategyRecs = collect($result)->filter(fn ($r) => $r['estimated_cost'] > 0);
        expect($strategyRecs)->toBeEmpty();
    });
});

// =========================================================================
// Policy-based triggers
// =========================================================================

describe('policy-based triggers', function () {
    it('fires review_existing_policies when policies exist but gaps remain', function () {
        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 50000, 'coverage' => 200000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 100000, 'need' => 100000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'current_coverage' => [
                'life_insurance' => [
                    'policies' => [
                        ['name' => 'Aviva Life', 'cover_amount' => 200000],
                    ],
                ],
                'critical_illness' => ['policies' => []],
                'income_protection' => ['policies' => []],
            ],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        $rec = collect($result)->first(fn ($r) => str_contains(strtolower($r['action']), 'review'));
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('Policy Review');
    });

    it('fires consolidate_policies when multiple policies exceed threshold', function () {
        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 0, 'coverage' => 250000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 100000, 'need' => 100000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'current_coverage' => [
                'life_insurance' => [
                    'policies' => [
                        ['name' => 'Policy A', 'cover_amount' => 100000],
                        ['name' => 'Policy B', 'cover_amount' => 150000],
                    ],
                ],
                'critical_illness' => [
                    'policies' => [
                        ['name' => 'Policy C', 'cover_amount' => 100000],
                    ],
                ],
                'income_protection' => ['policies' => []],
            ],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        $rec = collect($result)->first(fn ($r) => str_contains(strtolower($r['action']), 'consolidat'));
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('Policy Review');
    });

    it('does NOT fire consolidate when policy count is below threshold', function () {
        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 0, 'coverage' => 250000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 100000, 'need' => 100000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'current_coverage' => [
                'life_insurance' => [
                    'policies' => [
                        ['name' => 'Policy A', 'cover_amount' => 250000],
                    ],
                ],
                'critical_illness' => ['policies' => []],
                'income_protection' => ['policies' => []],
            ],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        $rec = collect($result)->first(fn ($r) => str_contains(strtolower($r['action']), 'consolidat'));
        expect($rec)->toBeNull();
    });
});

// =========================================================================
// Profile missing trigger
// =========================================================================

describe('profile missing trigger', function () {
    it('fires protection_profile_missing when user profile is empty', function () {
        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 0, 'coverage' => 0, 'need' => 0],
                'critical_illness' => ['gap' => 0, 'coverage' => 0, 'need' => 0],
                'income_protection' => ['gap' => 0, 'coverage' => 0, 'need' => 0],
            ],
            'current_coverage' => [],
            'user_profile' => [],
        ];

        $result = $this->service->evaluateActions($plan);

        $rec = collect($result)->first(fn ($r) => str_contains(strtolower($r['action']), 'profile') || str_contains(strtolower($r['action']), 'protection needs'));
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('Setup');
    });

    it('does NOT fire profile_missing when user profile has age', function () {
        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 0, 'coverage' => 250000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 100000, 'need' => 100000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        $rec = collect($result)->first(fn ($r) => str_contains(strtolower($r['action']), 'protection needs') && $r['category'] === 'Setup');
        expect($rec)->toBeNull();
    });
});

// =========================================================================
// No policies with gaps trigger
// =========================================================================

describe('no policies with gaps trigger', function () {
    it('fires no_policies_warning when no policies and gaps exist', function () {
        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 250000, 'coverage' => 0, 'need' => 250000],
                'critical_illness' => ['gap' => 100000, 'coverage' => 0, 'need' => 100000],
                'income_protection' => ['gap' => 2000, 'coverage' => 0, 'need' => 2000],
            ],
            'current_coverage' => [
                'life_insurance' => ['policies' => []],
                'critical_illness' => ['policies' => []],
                'income_protection' => ['policies' => []],
            ],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        $rec = collect($result)->first(fn ($r) => str_contains(strtolower($r['action']), 'no protection') || str_contains(strtolower($r['action']), 'no policies') || $r['category'] === 'Urgent');
        expect($rec)->not->toBeNull();
    });

    it('does NOT fire no_policies_warning when policies exist', function () {
        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 50000, 'coverage' => 200000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 100000, 'need' => 100000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'current_coverage' => [
                'life_insurance' => [
                    'policies' => [
                        ['name' => 'Aviva Life', 'cover_amount' => 200000],
                    ],
                ],
                'critical_illness' => ['policies' => []],
                'income_protection' => ['policies' => []],
            ],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        $rec = collect($result)->first(fn ($r) => $r['category'] === 'Urgent' && str_contains(strtolower($r['action']), 'no protection'));
        expect($rec)->toBeNull();
    });
});

// =========================================================================
// Disabled definitions
// =========================================================================

describe('disabled definitions', function () {
    it('skips disabled definitions', function () {
        ProtectionActionDefinition::where('key', 'life_insurance_gap')->update(['is_enabled' => false]);

        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 150000, 'coverage' => 100000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 100000, 'need' => 100000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        // The life insurance gap action should not fire even though gap exists
        $lifeGapDef = ProtectionActionDefinition::where('key', 'life_insurance_gap')->first();
        $rec = collect($result)->first(fn ($r) => $r['action'] === $lifeGapDef->renderTitle(['gap_amount' => '£150,000', 'need_amount' => '£250,000', 'coverage_amount' => '£100,000', 'description_text' => '']));
        expect($rec)->toBeNull();
    });

    it('includes re-enabled definitions', function () {
        // Disable then re-enable
        ProtectionActionDefinition::where('key', 'life_insurance_gap')->update(['is_enabled' => false]);
        ProtectionActionDefinition::where('key', 'life_insurance_gap')->update(['is_enabled' => true]);

        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 150000, 'coverage' => 100000, 'need' => 250000],
                'critical_illness' => ['gap' => 0, 'coverage' => 100000, 'need' => 100000],
                'income_protection' => ['gap' => 0, 'coverage' => 2000, 'need' => 2000],
            ],
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions(withPosition($plan));

        $rec = collect($result)->firstWhere('definition_key', 'life_cover_position');
        expect($rec)->not->toBeNull()
            ->and($rec['figures']['life_insurance_gap'] ?? false)->toBeTrue();
    });
});

// =========================================================================
// Template rendering
// =========================================================================

describe('template rendering', function () {
    it('renders title with gap amount placeholder', function () {
        $definition = ProtectionActionDefinition::findByKey('life_insurance_gap');

        $rendered = $definition->renderTitle([
            'gap_amount' => '£150,000',
        ]);

        expect($rendered)->toContain('£150,000');
    });

    it('renders description with multiple placeholders', function () {
        $definition = ProtectionActionDefinition::findByKey('life_insurance_gap');

        $rendered = $definition->renderDescription([
            'gap_amount' => '£150,000',
            'need_amount' => '£250,000',
            'coverage_amount' => '£100,000',
            'description_text' => 'Your current cover falls short of your calculated need by £150,000.',
        ]);

        expect($rendered)->toBeString()
            ->and(strlen($rendered))->toBeGreaterThan(0);
    });

    it('handles missing placeholders gracefully by removing them', function () {
        $definition = ProtectionActionDefinition::findByKey('life_insurance_gap');

        $rendered = $definition->renderTitle([]);

        expect($rendered)->toBeString()
            ->and($rendered)->not->toContain('{');
    });
});

// =========================================================================
// Priority sorting
// =========================================================================

describe('priority sorting', function () {
    it('returns recommendations sorted by priority', function () {
        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => 150000, 'coverage' => 100000, 'need' => 250000],
                'critical_illness' => ['gap' => 75000, 'coverage' => 25000, 'need' => 100000],
                'income_protection' => ['gap' => 500, 'coverage' => 1500, 'need' => 2000],
            ],
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        // Results should be sorted by numeric priority (1=critical, 2=high, 3=medium, 4=low)
        $priorities = collect($result)->pluck('priority')->values()->all();
        $sorted = $priorities;
        sort($sorted);

        expect($priorities)->toBe($sorted);
    });
});

// =========================================================================
// Empty data handling
// =========================================================================

describe('empty data handling', function () {
    it('does not throw errors when comprehensive plan is empty', function () {
        $result = $this->service->evaluateActions([]);

        expect($result)->toBeArray();
        // Profile missing and no-policies triggers may still fire with empty data — that is correct behaviour
        foreach ($result as $rec) {
            expect($rec)->toHaveKeys(['priority', 'category', 'action', 'rationale', 'impact', 'estimated_cost']);
        }
    });

    it('handles missing coverage_analysis gracefully', function () {
        $plan = [
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        expect($result)->toBeArray();
        // Should not throw any errors; profile_missing should not fire since age is set
        $profileMissing = collect($result)->first(fn ($r) => $r['category'] === 'Setup');
        expect($profileMissing)->toBeNull();
    });

    it('handles null coverage values without errors', function () {
        $plan = [
            'coverage_analysis' => [
                'life_insurance' => ['gap' => null, 'coverage' => null, 'need' => null],
                'critical_illness' => ['gap' => null, 'coverage' => null, 'need' => null],
                'income_protection' => ['gap' => null, 'coverage' => null, 'need' => null],
            ],
            'current_coverage' => [],
            'user_profile' => ['age' => 35],
        ];

        $result = $this->service->evaluateActions($plan);

        expect($result)->toBeArray();
    });
});
