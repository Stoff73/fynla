<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Services\AI\AdviceFyn;

/**
 * S1.8 — Response-mode classifier (INV-2.3.1).
 *
 * Every `QuerySchemas` constant must map to exactly one of three modes:
 * `'factual'`, `'recommendation'`, or `'out_of_remit'`. The map is the
 * single source of truth for whether a turn emits an `advice_response`
 * SSE panel.
 */
describe('AdviceFyn::classifyResponseMode — exhaustive coverage', function () {
    it('maps every QuerySchemas constant to a known mode', function () {
        $reflection = new ReflectionClass(QuerySchemas::class);
        $constants = $reflection->getConstants();

        // Filter to only string constants matching the query-type pattern
        // (UPPER_SNAKE_CASE for actual query types, ignoring map constants).
        $queryTypeConstants = array_filter(
            $constants,
            fn ($value, $key) => is_string($value)
                && ! str_ends_with($key, '_TYPES')
                && ! str_ends_with($key, '_MAP')
                && ! str_ends_with($key, '_PATTERNS')
                && ! str_ends_with($key, '_KYC')
                && ! str_ends_with($key, '_TOOLS')
                && ! str_ends_with($key, '_TRIGGERS')
                && ! str_ends_with($key, '_DOMAINS')
                && ! str_ends_with($key, '_RECORD_TYPES')
                && ! str_ends_with($key, '_RELATED')
                && ! str_ends_with($key, '_PRIORITY')
                && $key !== 'RECORD_TYPES',
            ARRAY_FILTER_USE_BOTH,
        );

        expect($queryTypeConstants)->not->toBeEmpty();

        foreach ($queryTypeConstants as $constName => $constValue) {
            $mode = AdviceFyn::classifyResponseMode($constValue);
            expect($mode)->toBeIn(['factual', 'recommendation', 'out_of_remit'], "Constant {$constName} ('{$constValue}') mapped to invalid mode '{$mode}'");
        }
    });

    it('maps the full 24 query-type universe (no constant unmapped)', function () {
        // Lock the universe size so adding a constant in QuerySchemas
        // forces the contributor to also extend the response-mode map.
        $allConstants = [
            QuerySchemas::PROTECTION_COVER, QuerySchemas::PROTECTION_POLICY,
            QuerySchemas::SAVINGS_EMERGENCY, QuerySchemas::SAVINGS_ACCOUNTS, QuerySchemas::SAVINGS_DEBT,
            QuerySchemas::RETIREMENT_CONTRIBUTION, QuerySchemas::RETIREMENT_READINESS, QuerySchemas::RETIREMENT_DECUMULATION,
            QuerySchemas::INVESTMENT_PORTFOLIO, QuerySchemas::INVESTMENT_FEES, QuerySchemas::INVESTMENT_TAX,
            QuerySchemas::ESTATE_IHT, QuerySchemas::ESTATE_PLANNING,
            QuerySchemas::GOALS_PROGRESS, QuerySchemas::TAX_OPTIMISATION,
            QuerySchemas::PROPERTY, QuerySchemas::INCOME,
            QuerySchemas::HOLISTIC_HEALTH, QuerySchemas::AFFORDABILITY,
            QuerySchemas::GENERAL, QuerySchemas::DATA_ENTRY, QuerySchemas::NAVIGATION,
            QuerySchemas::BILLING, QuerySchemas::OUT_OF_REMIT,
        ];

        expect($allConstants)->toHaveCount(24);

        foreach ($allConstants as $type) {
            $mode = AdviceFyn::classifyResponseMode($type);
            expect($mode)->not->toBeEmpty();
        }
    });

    it('throws when given an unknown query type', function () {
        AdviceFyn::classifyResponseMode('crypto_advice');
    })->throws(InvalidArgumentException::class, 'crypto_advice');
});

describe('AdviceFyn::classifyResponseMode — per-constant correctness', function () {
    it('classifies module-scoped advice queries as recommendation', function () {
        $recommendationTypes = [
            QuerySchemas::PROTECTION_COVER, QuerySchemas::PROTECTION_POLICY,
            QuerySchemas::SAVINGS_EMERGENCY, QuerySchemas::SAVINGS_ACCOUNTS, QuerySchemas::SAVINGS_DEBT,
            QuerySchemas::RETIREMENT_CONTRIBUTION, QuerySchemas::RETIREMENT_READINESS, QuerySchemas::RETIREMENT_DECUMULATION,
            QuerySchemas::INVESTMENT_PORTFOLIO, QuerySchemas::INVESTMENT_FEES, QuerySchemas::INVESTMENT_TAX,
            QuerySchemas::ESTATE_IHT, QuerySchemas::ESTATE_PLANNING,
            QuerySchemas::GOALS_PROGRESS, QuerySchemas::TAX_OPTIMISATION,
            QuerySchemas::PROPERTY, QuerySchemas::HOLISTIC_HEALTH, QuerySchemas::AFFORDABILITY,
        ];

        foreach ($recommendationTypes as $type) {
            expect(AdviceFyn::classifyResponseMode($type))->toBe('recommendation', "Type '{$type}' should be recommendation mode");
        }
    });

    it('classifies INCOME as factual despite being an advice type (no engine call needed)', function () {
        // INCOME is in QuerySchemas::ADVICE_TYPES (FCA process applies)
        // but its REQUIRED_TOOLS only specify get_tax_information(income_tax)
        // — no module analysis. Per INV-2.3.1, factual mode bypasses
        // orchestrateAnalysis, so INCOME is factual.
        expect(AdviceFyn::classifyResponseMode(QuerySchemas::INCOME))->toBe('factual')
            ->and(QuerySchemas::isAdviceType(QuerySchemas::INCOME))->toBeTrue();
    });

    it('classifies BILLING, GENERAL, DATA_ENTRY, NAVIGATION as factual', function () {
        foreach ([QuerySchemas::BILLING, QuerySchemas::GENERAL, QuerySchemas::DATA_ENTRY, QuerySchemas::NAVIGATION] as $type) {
            expect(AdviceFyn::classifyResponseMode($type))->toBe('factual', "Type '{$type}' should be factual");
        }
    });

    it('classifies OUT_OF_REMIT as out_of_remit (the only constant in this mode)', function () {
        expect(AdviceFyn::classifyResponseMode(QuerySchemas::OUT_OF_REMIT))->toBe('out_of_remit');

        // No other constant should resolve to out_of_remit
        foreach ([
            QuerySchemas::PROTECTION_COVER, QuerySchemas::SAVINGS_EMERGENCY,
            QuerySchemas::INCOME, QuerySchemas::HOLISTIC_HEALTH,
            QuerySchemas::BILLING, QuerySchemas::GENERAL,
        ] as $type) {
            expect(AdviceFyn::classifyResponseMode($type))->not->toBe('out_of_remit', "Type '{$type}' should not be out_of_remit");
        }
    });
});

describe('AdviceFyn::classifyResponseMode — invariant cross-checks', function () {
    it('every isAdviceType constant maps to recommendation OR factual (never out_of_remit)', function () {
        foreach (QuerySchemas::ADVICE_TYPES as $type) {
            $mode = AdviceFyn::classifyResponseMode($type);
            expect($mode)->toBeIn(['recommendation', 'factual'], "Advice type '{$type}' should not map to out_of_remit");
        }
    });

    it('isAdviceType ⊆ recommendation only excludes INCOME', function () {
        $factualAdviceTypes = array_filter(
            QuerySchemas::ADVICE_TYPES,
            fn ($t) => AdviceFyn::classifyResponseMode($t) === 'factual',
        );

        expect(array_values($factualAdviceTypes))->toBe([QuerySchemas::INCOME]);
    });
});
