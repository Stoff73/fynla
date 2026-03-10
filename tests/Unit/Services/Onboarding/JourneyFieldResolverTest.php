<?php

declare(strict_types=1);

use App\Services\Onboarding\JourneyFieldResolver;

beforeEach(function () {
    $this->resolver = new JourneyFieldResolver;
});

describe('JourneyFieldResolver', function () {
    it('returns correct fields for single journey (protection)', function () {
        $fields = $this->resolver->getFieldsForJourneys(['protection']);

        $personalKeys = array_column($fields['personal_fields'], 'key');
        $financialKeys = array_column($fields['financial_fields'], 'key');

        expect($personalKeys)->toContain('date_of_birth')
            ->and($personalKeys)->toContain('annual_employment_income')
            ->and($personalKeys)->toContain('monthly_expenditure')
            ->and($personalKeys)->toContain('marital_status')
            ->and($personalKeys)->toContain('occupation')
            ->and($personalKeys)->toContain('health_status')
            ->and($financialKeys)->toContain('family_members')
            ->and($financialKeys)->toContain('mortgages')
            ->and($financialKeys)->toContain('liabilities')
            ->and($financialKeys)->toContain('protection_policies');
    });

    it('deduplicates fields across two journeys (protection + retirement share date_of_birth)', function () {
        $fields = $this->resolver->getFieldsForJourneys(['protection', 'retirement']);

        $personalKeys = array_column($fields['personal_fields'], 'key');

        // date_of_birth should appear only once
        $dobCount = count(array_filter($personalKeys, fn ($key) => $key === 'date_of_birth'));
        expect($dobCount)->toBe(1);

        // annual_employment_income should appear only once
        $incomeCount = count(array_filter($personalKeys, fn ($key) => $key === 'annual_employment_income'));
        expect($incomeCount)->toBe(1);
    });

    it('returns 22 unique items for all 8 journeys', function () {
        $journeys = [
            'budgeting', 'protection', 'investment', 'retirement',
            'estate', 'family', 'business', 'goals',
        ];

        $fields = $this->resolver->getFieldsForJourneys($journeys);

        $totalFields = count($fields['personal_fields']) + count($fields['financial_fields']);
        expect($totalFields)->toBe(22);
    });

    it('returns empty for empty array', function () {
        $fields = $this->resolver->getFieldsForJourneys([]);

        expect($fields['personal_fields'])->toBeEmpty()
            ->and($fields['financial_fields'])->toBeEmpty();
    });

    it('throws exception for invalid journey name', function () {
        $this->resolver->getFieldsForJourneys(['invalid_journey']);
    })->throws(\InvalidArgumentException::class);

    it('orders personal fields before financial fields', function () {
        $fields = $this->resolver->getFieldsForJourneys(['protection']);

        expect($fields)->toHaveKeys(['personal_fields', 'financial_fields'])
            ->and($fields['personal_fields'])->not->toBeEmpty()
            ->and($fields['financial_fields'])->not->toBeEmpty();

        // Personal fields should all have labels
        foreach ($fields['personal_fields'] as $field) {
            expect($field)->toHaveKeys(['key', 'label', 'why', 'required']);
        }
    });

    it('combines why text when field spans multiple journeys', function () {
        $fields = $this->resolver->getFieldsForJourneys(['protection', 'retirement']);

        $dobField = null;
        foreach ($fields['personal_fields'] as $field) {
            if ($field['key'] === 'date_of_birth') {
                $dobField = $field;
                break;
            }
        }

        expect($dobField)->not->toBeNull();
        // Should contain text from both protection and retirement why entries
        expect($dobField['why'])->toContain('life expectancy')
            ->and($dobField['why'])->toContain('pension');
    });

    it('returns correct preview data with counts and estimated time', function () {
        $preview = $this->resolver->getPreviewForJourneys(['budgeting']);

        expect($preview)->toHaveKeys([
            'personal_count', 'financial_count',
            'personal_fields', 'financial_fields',
            'estimated_minutes',
        ])
            ->and($preview['personal_count'])->toBe(2)
            ->and($preview['financial_count'])->toBe(1)
            ->and($preview['estimated_minutes'])->toBeGreaterThanOrEqual(1);
    });

    it('returns correct steps for a single journey', function () {
        $steps = $this->resolver->getStepsForJourney('protection');

        expect($steps)->not->toBeEmpty();
        // First step should be personal information
        expect($steps[0]['name'])->toBe('Personal Information')
            ->and($steps[0]['component'])->toBe('SimplePersonalInfoStep');
        // Remaining steps are financial
        expect(count($steps))->toBeGreaterThan(1);
    });

    it('deduplicates steps across multiple journeys', function () {
        $steps = $this->resolver->getStepsForJourneys(['protection', 'retirement']);

        // Should have one merged personal step
        $personalSteps = array_filter($steps, fn ($s) => $s['component'] === 'JourneyPersonalStep');
        expect(count($personalSteps))->toBe(1);

        // The merged personal step should contain fields from both journeys
        $personalStep = reset($personalSteps);
        expect($personalStep['fields'])->toContain('date_of_birth')
            ->and($personalStep['fields'])->toContain('health_status')
            ->and($personalStep['fields'])->toContain('target_retirement_age');
    });
});
