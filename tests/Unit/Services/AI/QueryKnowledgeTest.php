<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Services\AI\Prompts\QueryKnowledge;

describe('QueryKnowledge', function () {
    it('returns pension + income + affordability knowledge for retirement_contribution', function () {
        $classification = [
            'primary' => QuerySchemas::RETIREMENT_CONTRIBUTION,
            'related' => [QuerySchemas::TAX_OPTIMISATION, QuerySchemas::SAVINGS_EMERGENCY, QuerySchemas::AFFORDABILITY],
            'modules' => ['retirement', 'tax', 'savings'],
        ];

        $result = QueryKnowledge::getForClassification($classification);

        expect($result)->toContain('PENSION KNOWLEDGE');
        expect($result)->toContain('INCOME CLASSIFICATIONS');
        expect($result)->toContain('AFFORDABILITY');
        expect($result)->not->toContain('ESTATE PLANNING CONCEPTS');
        expect($result)->not->toContain('PROTECTION CONCEPTS');
    });

    it('returns only protection knowledge for protection_cover', function () {
        $classification = [
            'primary' => QuerySchemas::PROTECTION_COVER,
            'related' => [],
            'modules' => ['protection'],
        ];

        $result = QueryKnowledge::getForClassification($classification);

        expect($result)->toContain('PROTECTION CONCEPTS');
        expect($result)->not->toContain('PENSION KNOWLEDGE');
        expect($result)->not->toContain('ESTATE PLANNING CONCEPTS');
        expect($result)->not->toContain('INVESTMENT TAX WRAPPERS');
    });

    it('returns ALL knowledge domains for holistic_health', function () {
        $classification = [
            'primary' => QuerySchemas::HOLISTIC_HEALTH,
            'related' => [QuerySchemas::SAVINGS_EMERGENCY, QuerySchemas::AFFORDABILITY],
            'modules' => ['savings', 'investment', 'retirement', 'protection', 'estate'],
        ];

        $result = QueryKnowledge::getForClassification($classification);

        expect($result)->toContain('PENSION KNOWLEDGE');
        expect($result)->toContain('INCOME CLASSIFICATIONS');
        expect($result)->toContain('INVESTMENT TAX WRAPPERS');
        expect($result)->toContain('ESTATE PLANNING CONCEPTS');
        expect($result)->toContain('PROTECTION CONCEPTS');
        expect($result)->toContain('AFFORDABILITY');
    });

    it('returns empty string for data_entry', function () {
        $classification = [
            'primary' => QuerySchemas::DATA_ENTRY,
            'related' => [],
            'modules' => [],
        ];

        expect(QueryKnowledge::getForClassification($classification))->toBe('');
    });

    it('returns empty string for general queries', function () {
        $classification = [
            'primary' => QuerySchemas::GENERAL,
            'related' => [],
            'modules' => [],
        ];

        expect(QueryKnowledge::getForClassification($classification))->toBe('');
    });

    it('merges knowledge from primary + related types without duplicates', function () {
        $classification = [
            'primary' => QuerySchemas::TAX_OPTIMISATION,
            'related' => [QuerySchemas::RETIREMENT_CONTRIBUTION],
            'modules' => ['tax', 'retirement'],
        ];

        $result = QueryKnowledge::getForClassification($classification);

        // tax_optimisation has income + investment, retirement adds pension + income + affordability
        expect($result)->toContain('INCOME CLASSIFICATIONS');
        expect($result)->toContain('INVESTMENT TAX WRAPPERS');
        expect($result)->toContain('PENSION KNOWLEDGE');
        expect($result)->toContain('AFFORDABILITY');

        // Income classifications should only appear once (deduplicated)
        $count = substr_count($result, 'INCOME CLASSIFICATIONS (UK):');
        expect($count)->toBe(1);
    });

    it('returns all knowledge when classification is null (backward compat)', function () {
        $result = QueryKnowledge::getForClassification(null);

        expect($result)->toContain('PENSION KNOWLEDGE');
        expect($result)->toContain('ESTATE PLANNING CONCEPTS');
        expect($result)->toContain('PROTECTION CONCEPTS');
    });
});
