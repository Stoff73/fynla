<?php

declare(strict_types=1);

use App\Services\AI\ToolResultContract;
use App\Services\AI\ToolResultContractException;

/**
 * S1.6.b — per-agent tool-result output contract.
 *
 * Replaces the lossy 15-key whitelist in CoordinatingAgent::extractKeyMetrics.
 * The validator BLOCKS malformed shapes from reaching the LLM, unwraps the
 * BaseAgent success/data envelope, and pre-pends `module` so every tool
 * result the LLM sees is identifiable.
 */
beforeEach(function () {
    $this->contract = new ToolResultContract;
});

describe('happy-path required keys', function () {
    it('accepts a wrapped protection payload with every required key', function () {
        $result = $this->contract->validate('protection', [
            'success' => true,
            'message' => 'ok',
            'data' => [
                'profile' => [],
                'needs' => [],
                'coverage' => [],
                'gaps' => [],
                'adequacy_score' => [],
                'recommendations' => [],
                'scenarios' => [],
                'debt_breakdown' => [],
                'policies' => [],
                'profile_completeness' => [],
                'missing_for_quality_advice' => [],
            ],
        ]);

        expect($result)->toHaveKey('module', 'protection')
            ->and($result)->toHaveKeys(['profile', 'gaps', 'missing_for_quality_advice']);
    });

    it('accepts an unwrapped savings payload', function () {
        $result = $this->contract->validate('savings', [
            'summary' => [],
            'emergency_fund' => [],
            'isa_allowance' => [],
            'liquidity' => [],
            'rate_comparisons' => [],
            'goals' => [],
            'missing_for_quality_advice' => [],
        ]);

        expect($result)->toHaveKey('module', 'savings');
    });

    it('rejects a happy-path payload missing missing_for_quality_advice', function () {
        $this->contract->validate('investment', [
            'portfolio_summary' => [],
            'asset_allocation' => [],
            'risk_metrics' => [],
            'fee_analysis' => [],
            'tax_efficiency' => [],
            'tax_wrappers' => [],
            'goals' => [],
            // 'missing_for_quality_advice' deliberately absent
        ]);
    })->throws(ToolResultContractException::class, 'missing_for_quality_advice');

    it('rejects a happy-path payload missing one of the structured fields', function () {
        $this->contract->validate('retirement', [
            'summary' => [],
            'income_projection' => [],
            // 'breakdown' missing
            'annual_allowance' => [],
            'profile' => [],
            'missing_for_quality_advice' => [],
        ]);
    })->throws(ToolResultContractException::class, 'breakdown');
});

describe('readiness-gate path', function () {
    it('accepts a wrapped protection readiness-gate result', function () {
        $result = $this->contract->validate('protection', [
            'success' => true,
            'message' => 'Readiness check incomplete',
            'data' => [
                'can_proceed' => false,
                'readiness_checks' => ['protection_profile' => false],
                'profile' => null,
            ],
        ]);

        expect($result)->toHaveKey('module', 'protection')
            ->and($result)->toHaveKey('can_proceed', false)
            ->and($result)->toHaveKey('readiness_checks');
    });

    it('rejects a readiness-gate result without readiness_checks', function () {
        $this->contract->validate('estate', [
            'success' => true,
            'message' => 'Readiness check incomplete',
            'data' => [
                'can_proceed' => false,
                // 'readiness_checks' missing
            ],
        ]);
    })->throws(ToolResultContractException::class, 'readiness_checks');
});

describe('agent-error path (success: false)', function () {
    it('passes through retirement no-profile error verbatim', function () {
        $result = $this->contract->validate('retirement', [
            'success' => false,
            'message' => 'No retirement profile found',
            'data' => [],
        ]);

        expect($result)->toHaveKey('module', 'retirement')
            ->and($result)->toHaveKey('success', false)
            ->and($result)->toHaveKey('message', 'No retirement profile found');
    });
});

describe('module-specific empty states', function () {
    it('accepts investment with zero accounts', function () {
        $result = $this->contract->validate('investment', [
            'message' => 'No investment accounts found',
            'accounts_count' => 0,
        ]);

        expect($result)->toHaveKey('module', 'investment')
            ->and($result)->toHaveKey('accounts_count', 0);
    });

    it('accepts goals with has_goals=false', function () {
        $result = $this->contract->validate('goals', [
            'has_goals' => false,
            'message' => 'No goals found.',
            'summary' => [],
        ]);

        expect($result)->toHaveKey('module', 'goals')
            ->and($result)->toHaveKey('has_goals', false);
    });
});

describe('rejection cases', function () {
    it('rejects an unknown module', function () {
        $this->contract->validate('crypto', []);
    })->throws(ToolResultContractException::class, 'crypto');

    it('lists missing keys in the exception detail', function () {
        try {
            $this->contract->validate('goals', [
                // missing every required key
            ]);
            expect(false)->toBeTrue('contract should have thrown');
        } catch (ToolResultContractException $e) {
            expect($e->missingKeys)->toContain('has_goals')
                ->and($e->missingKeys)->toContain('summary')
                ->and($e->missingKeys)->toContain('missing_for_quality_advice');
        }
    });
});

describe('verbatim payload preservation', function () {
    it('does not strip arbitrary extra keys outside the required set', function () {
        $result = $this->contract->validate('savings', [
            'summary' => ['total_savings' => 12345.67, 'foo' => 'bar'],
            'emergency_fund' => [],
            'isa_allowance' => [],
            'liquidity' => [],
            'rate_comparisons' => [],
            'goals' => [],
            'missing_for_quality_advice' => [],
            'children_savings' => [['child_name' => 'A']],
            'psa_position' => ['some' => 'data'],
        ]);

        expect($result)->toHaveKey('children_savings')
            ->and($result)->toHaveKey('psa_position')
            ->and($result['summary']['total_savings'])->toBe(12345.67)
            ->and($result['summary']['foo'])->toBe('bar');
    });

    it('does not double-prepend module when payload already carries it', function () {
        $result = $this->contract->validate('investment', [
            'module' => 'investment',
            'message' => 'No investment accounts found',
            'accounts_count' => 0,
        ]);

        expect(array_keys($result)[0])->toBe('module')
            ->and(array_count_values(array_keys($result))['module'])->toBe(1);
    });
});

describe('holistic module', function () {
    it('accepts a holistic orchestrateAnalysis result', function () {
        $result = $this->contract->validate('holistic', [
            'user_id' => 7,
            'analysis_date' => '2026-04-27T10:00:00Z',
            'module_analysis' => ['protection' => []],
            'available_surplus' => 1500.00,
            'ranked_recommendations' => [],
            'cashflow_allocation' => [],
            'summary' => ['total_recommendations' => 0],
        ]);

        expect($result)->toHaveKey('module', 'holistic')
            ->and($result)->toHaveKey('user_id', 7);
    });

    it('rejects a holistic payload missing module_analysis', function () {
        $this->contract->validate('holistic', [
            'user_id' => 7,
            'analysis_date' => '2026-04-27T10:00:00Z',
            // 'module_analysis' missing
            'available_surplus' => 0,
            'ranked_recommendations' => [],
            'cashflow_allocation' => [],
            'summary' => [],
        ]);
    })->throws(ToolResultContractException::class, 'module_analysis');
});
