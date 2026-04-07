<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Services\AI\QueryClassifier;

beforeEach(function () {
    $this->classifier = new QueryClassifier;
});

describe('QueryClassifier', function () {
    describe('data_entry classification', function () {
        it('classifies "I have a pension with £50,000" as data_entry', function () {
            $result = $this->classifier->classify('I have a pension with £50,000');
            expect($result['primary'])->toBe(QuerySchemas::DATA_ENTRY);
            expect($result['related'])->toBe([]);
        });

        it('classifies "Update my ISA balance to £15,000" as data_entry', function () {
            $result = $this->classifier->classify('Update my ISA balance to £15,000');
            expect($result['primary'])->toBe(QuerySchemas::DATA_ENTRY);
        });

        it('classifies "I earn £75,000" as data_entry', function () {
            $result = $this->classifier->classify('I earn £75,000');
            expect($result['primary'])->toBe(QuerySchemas::DATA_ENTRY);
        });
    });

    describe('navigation classification', function () {
        it('classifies "Take me to estate planning" as navigation', function () {
            $result = $this->classifier->classify('Take me to estate planning');
            expect($result['primary'])->toBe(QuerySchemas::NAVIGATION);
            expect($result['related'])->toBe([]);
        });

        it('classifies "Show me my investments" as navigation', function () {
            $result = $this->classifier->classify('Show me my investments');
            expect($result['primary'])->toBe(QuerySchemas::NAVIGATION);
        });
    });

    describe('advice classification', function () {
        it('classifies "How do I maximise my pension?" as retirement_contribution with related types', function () {
            $result = $this->classifier->classify('How do I maximise my pension?');
            expect($result['primary'])->toBe(QuerySchemas::RETIREMENT_CONTRIBUTION);
            expect($result['related'])->toContain(QuerySchemas::TAX_OPTIMISATION);
            expect($result['related'])->toContain(QuerySchemas::AFFORDABILITY);
        });

        it('classifies "Do I have enough life cover?" as protection_cover', function () {
            $result = $this->classifier->classify('Do I have enough life cover?');
            expect($result['primary'])->toBe(QuerySchemas::PROTECTION_COVER);
        });

        it('classifies "What should I do with my bonus?" as holistic_health', function () {
            $result = $this->classifier->classify('What should I do with my bonus?');
            expect($result['primary'])->toBe(QuerySchemas::HOLISTIC_HEALTH);
            expect($result['related'])->toContain(QuerySchemas::SAVINGS_EMERGENCY);
            expect($result['related'])->toContain(QuerySchemas::AFFORDABILITY);
        });

        it('classifies "Should I pay off my mortgage or invest?" as savings_debt with affordability', function () {
            $result = $this->classifier->classify('Should I pay off my mortgage or invest?');
            expect($result['primary'])->toBe(QuerySchemas::SAVINGS_DEBT);
            expect($result['related'])->toContain(QuerySchemas::AFFORDABILITY);
        });

        it('classifies "How is my financial health?" as holistic_health', function () {
            $result = $this->classifier->classify('How is my financial health?');
            expect($result['primary'])->toBe(QuerySchemas::HOLISTIC_HEALTH);
        });

        it('classifies "What is my emergency fund position?" as savings_emergency', function () {
            $result = $this->classifier->classify('Do I have enough cash buffer?');
            expect($result['primary'])->toBe(QuerySchemas::SAVINGS_EMERGENCY);
            expect($result['related'])->toContain(QuerySchemas::AFFORDABILITY);
        });
    });

    describe('general classification', function () {
        it('classifies "What is my net worth?" as general', function () {
            $result = $this->classifier->classify('What is my net worth?');
            expect($result['primary'])->toBe(QuerySchemas::GENERAL);
        });
    });

    describe('route-based fallback', function () {
        it('falls back to protection_cover on /protection page', function () {
            $result = $this->classifier->classify('tell me more', '/protection');
            expect($result['primary'])->toBe(QuerySchemas::PROTECTION_COVER);
        });

        it('falls back to general on /dashboard with no keyword match', function () {
            $result = $this->classifier->classify('tell me more', '/dashboard');
            expect($result['primary'])->toBe(QuerySchemas::GENERAL);
        });

        it('falls back to retirement_readiness on /net-worth/retirement page', function () {
            $result = $this->classifier->classify('tell me more', '/net-worth/retirement');
            expect($result['primary'])->toBe(QuerySchemas::RETIREMENT_READINESS);
        });
    });

    describe('module mapping', function () {
        it('includes correct modules for retirement_contribution', function () {
            $result = $this->classifier->classify('How do I maximise my pension?');
            expect($result['modules'])->toContain('retirement');
            expect($result['modules'])->toContain('tax');
            expect($result['modules'])->toContain('savings');
        });

        it('returns empty modules for data_entry', function () {
            $result = $this->classifier->classify('I have a new ISA with £10,000');
            expect($result['modules'])->toBe([]);
        });
    });
});
