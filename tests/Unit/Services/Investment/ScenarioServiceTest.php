<?php

declare(strict_types=1);

use App\Services\Investment\ScenarioService;

beforeEach(function () {
    $this->service = new ScenarioService;
});

describe('ScenarioService', function () {
    describe('getTemplates', function () {
        it('returns array of scenario templates', function () {
            $templates = $this->service->getTemplates();

            expect($templates)->toBeArray();
            expect($templates)->not->toBeEmpty();
        });

        it('has required fields in each template', function () {
            $templates = $this->service->getTemplates();

            foreach ($templates as $template) {
                expect($template)->toHaveKey('id');
                expect($template)->toHaveKey('name');
                expect($template)->toHaveKey('description');
                expect($template)->toHaveKey('category');
                expect($template)->toHaveKey('parameters');
                expect($template['parameters'])->toBeArray();
            }
        });

        it('has unique template IDs', function () {
            $templates = $this->service->getTemplates();
            $ids = array_column($templates, 'id');

            expect(count($ids))->toBe(count(array_unique($ids)));
        });

        it('includes market crash template', function () {
            $templates = $this->service->getTemplates();
            $ids = array_column($templates, 'id');

            expect($ids)->toContain('market_crash');
        });

        it('includes early retirement template', function () {
            $templates = $this->service->getTemplates();
            $ids = array_column($templates, 'id');

            expect($ids)->toContain('early_retirement');
        });

        it('includes contribution-related templates', function () {
            $templates = $this->service->getTemplates();
            $ids = array_column($templates, 'id');

            expect($ids)->toContain('increased_contributions');
            expect($ids)->toContain('lump_sum_contribution');
        });

        it('includes allocation templates', function () {
            $templates = $this->service->getTemplates();
            $ids = array_column($templates, 'id');

            expect($ids)->toContain('aggressive_allocation');
            expect($ids)->toContain('conservative_allocation');
        });
    });

    describe('getTemplate', function () {
        it('returns template by valid ID', function () {
            $template = $this->service->getTemplate('market_crash');

            expect($template)->not->toBeNull();
            expect($template['id'])->toBe('market_crash');
            expect($template['name'])->toBe('Market Crash Recovery');
            expect($template['category'])->toBe('market_conditions');
        });

        it('returns null for invalid template ID', function () {
            $template = $this->service->getTemplate('nonexistent_template');

            expect($template)->toBeNull();
        });

        it('has correct parameters for market crash', function () {
            $template = $this->service->getTemplate('market_crash');

            expect($template['parameters'])->toHaveKey('return_adjustment');
            expect($template['parameters']['return_adjustment'])->toBe(-30);
            expect($template['parameters'])->toHaveKey('recovery_period_years');
        });

        it('has correct parameters for early retirement', function () {
            $template = $this->service->getTemplate('early_retirement');

            expect($template['parameters'])->toHaveKey('retirement_age');
            expect($template['parameters']['retirement_age'])->toBe(55);
            expect($template['parameters'])->toHaveKey('withdrawal_rate');
        });

        it('has correct parameters for fee reduction', function () {
            $template = $this->service->getTemplate('fee_reduction');

            expect($template['parameters'])->toHaveKey('fee_reduction');
            expect($template['parameters']['fee_reduction'])->toBe(0.5);
            expect($template['parameters'])->toHaveKey('projection_years');
        });
    });
});
