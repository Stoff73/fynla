<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;

describe('QuerySchemas', function () {
    describe('trigger mappings', function () {
        it('retirement_contribution includes employer_match and contribution_increase', function () {
            $triggers = QuerySchemas::RELEVANT_TRIGGERS[QuerySchemas::RETIREMENT_CONTRIBUTION];
            expect($triggers)->toContain('employer_match');
            expect($triggers)->toContain('contribution_increase');
            expect($triggers)->toContain('tax_relief');
        });

        it('protection_cover includes life_insurance_gap and income_protection_gap', function () {
            $triggers = QuerySchemas::RELEVANT_TRIGGERS[QuerySchemas::PROTECTION_COVER];
            expect($triggers)->toContain('life_insurance_gap');
            expect($triggers)->toContain('income_protection_gap');
        });

        it('holistic_health returns ALL triggers from all types', function () {
            $holisticTriggers = QuerySchemas::getRelevantTriggersForClassification([
                'primary' => QuerySchemas::HOLISTIC_HEALTH,
                'related' => [],
            ]);

            // Should include triggers from multiple modules
            expect($holisticTriggers)->toContain('employer_match');
            expect($holisticTriggers)->toContain('life_insurance_gap');
            expect($holisticTriggers)->toContain('emergency_fund_critical');
            expect($holisticTriggers)->toContain('iht_exceeds_nrb');
            expect(count($holisticTriggers))->toBeGreaterThan(20);
        });
    });

    describe('required tools', function () {
        it('retirement_contribution requires pension and income tools', function () {
            $tools = QuerySchemas::REQUIRED_TOOLS[QuerySchemas::RETIREMENT_CONTRIBUTION];
            expect($tools)->toContain('get_tax_information(pension_allowances)');
            expect($tools)->toContain('get_tax_information(income_definitions)');
            expect($tools)->toContain('get_module_analysis(retirement)');
        });

        it('estate_iht requires inheritance_tax tool', function () {
            $tools = QuerySchemas::REQUIRED_TOOLS[QuerySchemas::ESTATE_IHT];
            expect($tools)->toContain('get_tax_information(inheritance_tax)');
            expect($tools)->toContain('get_module_analysis(estate)');
        });

        it('data_entry has no required tools', function () {
            expect(QuerySchemas::REQUIRED_TOOLS[QuerySchemas::DATA_ENTRY])->toBe([]);
        });

        it('merges tools from primary + related without duplicates', function () {
            $tools = QuerySchemas::getRequiredToolsForClassification([
                'primary' => QuerySchemas::RETIREMENT_CONTRIBUTION,
                'related' => [QuerySchemas::TAX_OPTIMISATION, QuerySchemas::SAVINGS_EMERGENCY],
            ]);

            // Should have retirement + tax + savings tools, deduplicated
            expect($tools)->toContain('get_tax_information(pension_allowances)');
            expect($tools)->toContain('get_tax_information(income_tax)');
            expect($tools)->toContain('get_module_analysis(savings)');

            // No duplicates
            expect(count($tools))->toBe(count(array_unique($tools)));
        });
    });

    describe('helper methods', function () {
        it('isBypassType returns true for data_entry and navigation', function () {
            expect(QuerySchemas::isBypassType(QuerySchemas::DATA_ENTRY))->toBeTrue();
            expect(QuerySchemas::isBypassType(QuerySchemas::NAVIGATION))->toBeTrue();
            expect(QuerySchemas::isBypassType(QuerySchemas::RETIREMENT_CONTRIBUTION))->toBeFalse();
        });

        it('isAdviceType returns true for advice types', function () {
            expect(QuerySchemas::isAdviceType(QuerySchemas::RETIREMENT_CONTRIBUTION))->toBeTrue();
            expect(QuerySchemas::isAdviceType(QuerySchemas::HOLISTIC_HEALTH))->toBeTrue();
            expect(QuerySchemas::isAdviceType(QuerySchemas::DATA_ENTRY))->toBeFalse();
            expect(QuerySchemas::isAdviceType(QuerySchemas::GENERAL))->toBeFalse();
        });

        it('getModulesForClassification merges primary + related modules', function () {
            $modules = QuerySchemas::getModulesForClassification([
                'primary' => QuerySchemas::RETIREMENT_CONTRIBUTION,
                'related' => [QuerySchemas::TAX_OPTIMISATION, QuerySchemas::SAVINGS_EMERGENCY],
            ]);

            expect($modules)->toContain('retirement');
            expect($modules)->toContain('tax');
            expect($modules)->toContain('savings');
        });
    });
});
