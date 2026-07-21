<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Services\AI\AdviceFyn;

/**
 * S1.8 — Engine call granularity (INV-2.3.6).
 *
 * Every `QuerySchemas` constant maps to exactly one of three engine
 * call levels:
 *
 *  - `'holistic'` — invokes `CoordinatingAgent::orchestrateAnalysis`.
 *    HOLISTIC_HEALTH is the ONLY constant at this level — that
 *    enforces the canonical contract that orchestrateAnalysis is
 *    cross-module by design and never wasted on a single-module turn.
 *  - `'module'` — invokes a single `{Module}Agent::analyze`.
 *  - `'factual'` — no engine call.
 */
describe('AdviceFyn::engineCallLevel — exhaustive coverage', function () {
    it('maps every QuerySchemas constant to a valid level', function () {
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
            $level = AdviceFyn::engineCallLevel($type);
            expect($level)->toBeIn(['holistic', 'module', 'factual'], "Constant '{$type}' mapped to invalid level '{$level}'");
        }
    });

    it('throws when given an unknown query type', function () {
        AdviceFyn::engineCallLevel('crypto_advice');
    })->throws(InvalidArgumentException::class, 'crypto_advice');
});

describe('AdviceFyn::engineCallLevel — orchestrateAnalysis is reserved for holistic only', function () {
    it('HOLISTIC_HEALTH is the only constant at level holistic', function () {
        expect(AdviceFyn::engineCallLevel(QuerySchemas::HOLISTIC_HEALTH))->toBe('holistic');

        // No other constant — including TAX_OPTIMISATION which spans
        // multiple modules conceptually — should be holistic.
        $nonHolisticConstants = [
            QuerySchemas::PROTECTION_COVER, QuerySchemas::PROTECTION_POLICY,
            QuerySchemas::SAVINGS_EMERGENCY, QuerySchemas::SAVINGS_ACCOUNTS, QuerySchemas::SAVINGS_DEBT,
            QuerySchemas::RETIREMENT_CONTRIBUTION, QuerySchemas::RETIREMENT_READINESS, QuerySchemas::RETIREMENT_DECUMULATION,
            QuerySchemas::INVESTMENT_PORTFOLIO, QuerySchemas::INVESTMENT_FEES, QuerySchemas::INVESTMENT_TAX,
            QuerySchemas::ESTATE_IHT, QuerySchemas::ESTATE_PLANNING,
            QuerySchemas::GOALS_PROGRESS, QuerySchemas::TAX_OPTIMISATION,
            QuerySchemas::PROPERTY, QuerySchemas::INCOME, QuerySchemas::AFFORDABILITY,
            QuerySchemas::GENERAL, QuerySchemas::DATA_ENTRY, QuerySchemas::NAVIGATION,
            QuerySchemas::BILLING, QuerySchemas::OUT_OF_REMIT,
        ];

        foreach ($nonHolisticConstants as $type) {
            expect(AdviceFyn::engineCallLevel($type))->not->toBe('holistic', "Type '{$type}' should not be holistic");
        }
    });
});

describe('AdviceFyn::engineCallLevel — module-scoped advice is the bulk of the catalogue', function () {
    it('classifies all module-scoped advice queries as module-level', function () {
        $moduleScoped = [
            QuerySchemas::PROTECTION_COVER, QuerySchemas::PROTECTION_POLICY,
            QuerySchemas::SAVINGS_EMERGENCY, QuerySchemas::SAVINGS_ACCOUNTS, QuerySchemas::SAVINGS_DEBT,
            QuerySchemas::RETIREMENT_CONTRIBUTION, QuerySchemas::RETIREMENT_READINESS, QuerySchemas::RETIREMENT_DECUMULATION,
            QuerySchemas::INVESTMENT_PORTFOLIO, QuerySchemas::INVESTMENT_FEES, QuerySchemas::INVESTMENT_TAX,
            QuerySchemas::ESTATE_IHT, QuerySchemas::ESTATE_PLANNING,
            QuerySchemas::GOALS_PROGRESS, QuerySchemas::TAX_OPTIMISATION,
            QuerySchemas::PROPERTY, QuerySchemas::AFFORDABILITY,
        ];

        foreach ($moduleScoped as $type) {
            expect(AdviceFyn::engineCallLevel($type))->toBe('module', "Type '{$type}' should be module-level");
        }
    });
});

describe('AdviceFyn::engineCallLevel — factual queries make no engine call', function () {
    it('classifies INCOME, GENERAL, BILLING, DATA_ENTRY, NAVIGATION, OUT_OF_REMIT as factual', function () {
        $factual = [
            QuerySchemas::INCOME, QuerySchemas::GENERAL,
            QuerySchemas::DATA_ENTRY, QuerySchemas::NAVIGATION,
            QuerySchemas::BILLING, QuerySchemas::OUT_OF_REMIT,
        ];

        foreach ($factual as $type) {
            expect(AdviceFyn::engineCallLevel($type))->toBe('factual', "Type '{$type}' should be factual-level");
        }
    });
});

describe('AdviceFyn::engineCallLevel — cross-check with classifyResponseMode', function () {
    it('recommendation mode ↔ engine level in {holistic, module}', function () {
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

        foreach ($allConstants as $type) {
            $mode = AdviceFyn::classifyResponseMode($type);
            $level = AdviceFyn::engineCallLevel($type);

            if ($mode === 'recommendation') {
                expect($level)->toBeIn(['holistic', 'module'], "Recommendation-mode type '{$type}' must call holistic or module engine, got '{$level}'");
            } else {
                expect($level)->toBe('factual', "Non-recommendation type '{$type}' must be factual engine level, got '{$level}'");
            }
        }
    });
});
