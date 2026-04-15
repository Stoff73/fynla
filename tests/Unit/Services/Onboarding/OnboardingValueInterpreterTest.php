<?php

declare(strict_types=1);

use App\Services\Onboarding\OnboardingValueInterpreter;

describe('OnboardingValueInterpreter::parseDateOfBirth', function () {
    it('parses long-form British dates', function () {
        expect(OnboardingValueInterpreter::parseDateOfBirth('12 January 1985'))
            ->toBe('1985-01-12');
    });

    it('parses slashed DD/MM/YYYY format', function () {
        expect(OnboardingValueInterpreter::parseDateOfBirth('12/01/1985'))
            ->not->toBeNull();
    });

    it('parses ISO format', function () {
        expect(OnboardingValueInterpreter::parseDateOfBirth('1985-01-12'))
            ->toBe('1985-01-12');
    });

    it('parses abbreviated month names', function () {
        expect(OnboardingValueInterpreter::parseDateOfBirth('12 Jan 1980'))
            ->toBe('1980-01-12');
    });

    it('rejects relative references', function () {
        expect(OnboardingValueInterpreter::parseDateOfBirth('yesterday'))->toBeNull()
            ->and(OnboardingValueInterpreter::parseDateOfBirth('today'))->toBeNull();
    });

    it('rejects users younger than 18', function () {
        $tooYoung = now()->subYears(10)->format('Y-m-d');
        expect(OnboardingValueInterpreter::parseDateOfBirth($tooYoung))->toBeNull();
    });

    it('rejects users older than 105', function () {
        $tooOld = now()->subYears(110)->format('Y-m-d');
        expect(OnboardingValueInterpreter::parseDateOfBirth($tooOld))->toBeNull();
    });

    it('rejects future dates', function () {
        $future = now()->addYear()->format('Y-m-d');
        expect(OnboardingValueInterpreter::parseDateOfBirth($future))->toBeNull();
    });

    it('rejects empty and null input', function () {
        expect(OnboardingValueInterpreter::parseDateOfBirth(null))->toBeNull()
            ->and(OnboardingValueInterpreter::parseDateOfBirth(''))->toBeNull()
            ->and(OnboardingValueInterpreter::parseDateOfBirth('   '))->toBeNull();
    });

    it('rejects nonsense strings', function () {
        expect(OnboardingValueInterpreter::parseDateOfBirth('banana'))->toBeNull();
    });
});

describe('OnboardingValueInterpreter::parseIncomeAmount', function () {
    it('strips pound signs and commas', function () {
        expect(OnboardingValueInterpreter::parseIncomeAmount('£75,000'))->toBe(75000.0);
    });

    it('accepts plain numerics', function () {
        expect(OnboardingValueInterpreter::parseIncomeAmount('75000'))->toBe(75000.0);
    });

    it('accepts k suffix', function () {
        expect(OnboardingValueInterpreter::parseIncomeAmount('75k'))->toBe(75000.0);
    });

    it('accepts £ plus k suffix', function () {
        expect(OnboardingValueInterpreter::parseIncomeAmount('£75k'))->toBe(75000.0);
    });

    it('accepts m suffix', function () {
        expect(OnboardingValueInterpreter::parseIncomeAmount('1.5m'))->toBe(1500000.0);
    });

    it('accepts decimal amounts with pence', function () {
        expect(OnboardingValueInterpreter::parseIncomeAmount('£75,000.50'))->toBe(75000.5);
    });

    it('rejects word amounts', function () {
        expect(OnboardingValueInterpreter::parseIncomeAmount('seventy-five thousand'))->toBeNull();
    });

    it('rejects non-numeric input', function () {
        expect(OnboardingValueInterpreter::parseIncomeAmount('asdf'))->toBeNull();
    });

    it('rejects amounts beyond the ceiling', function () {
        expect(OnboardingValueInterpreter::parseIncomeAmount('200000000'))->toBeNull();
    });

    it('rejects empty and null input', function () {
        expect(OnboardingValueInterpreter::parseIncomeAmount(null))->toBeNull()
            ->and(OnboardingValueInterpreter::parseIncomeAmount(''))->toBeNull();
    });
});

describe('OnboardingValueInterpreter::parseExpenditureAmount', function () {
    it('handles the same shapes as income', function () {
        expect(OnboardingValueInterpreter::parseExpenditureAmount('£2,500'))->toBe(2500.0)
            ->and(OnboardingValueInterpreter::parseExpenditureAmount('2.5k'))->toBe(2500.0);
    });

    it('rejects amounts above the monthly ceiling', function () {
        expect(OnboardingValueInterpreter::parseExpenditureAmount('10000000'))->toBeNull();
    });
});

describe('OnboardingValueInterpreter::parseRetirementDate', function () {
    it('accepts year-only input', function () {
        expect(OnboardingValueInterpreter::parseRetirementDate('2020'))->toBe('2020-01-01');
    });

    it('accepts a full date', function () {
        expect(OnboardingValueInterpreter::parseRetirementDate('15 March 2022'))->toBe('2022-03-15');
    });

    it('rejects relative phrases', function () {
        expect(OnboardingValueInterpreter::parseRetirementDate('yesterday'))->toBeNull();
    });

    it('rejects empty input', function () {
        expect(OnboardingValueInterpreter::parseRetirementDate(null))->toBeNull()
            ->and(OnboardingValueInterpreter::parseRetirementDate(''))->toBeNull();
    });
});

describe('OnboardingValueInterpreter::parseMaritalFromText', function () {
    it('detects civil partnership', function () {
        expect(OnboardingValueInterpreter::parseMaritalFromText('Civil partnership'))
            ->toBe('civil_partnership');
        expect(OnboardingValueInterpreter::parseMaritalFromText("I'm in a civil partnership"))
            ->toBe('civil_partnership');
    });

    it('detects married before single (prefix safety)', function () {
        expect(OnboardingValueInterpreter::parseMaritalFromText('Married'))->toBe('married');
    });

    it('detects single', function () {
        expect(OnboardingValueInterpreter::parseMaritalFromText('single'))->toBe('single');
    });

    it('detects divorced and separated', function () {
        expect(OnboardingValueInterpreter::parseMaritalFromText('Divorced'))->toBe('divorced')
            ->and(OnboardingValueInterpreter::parseMaritalFromText('Separated'))->toBe('divorced');
    });

    it('detects widowed', function () {
        expect(OnboardingValueInterpreter::parseMaritalFromText('Widowed'))->toBe('widowed')
            ->and(OnboardingValueInterpreter::parseMaritalFromText('widow'))->toBe('widowed');
    });

    it('rejects unknown input', function () {
        expect(OnboardingValueInterpreter::parseMaritalFromText('who knows'))->toBeNull()
            ->and(OnboardingValueInterpreter::parseMaritalFromText(''))->toBeNull();
    });
});

describe('OnboardingValueInterpreter::parseEmploymentFromText', function () {
    it('detects self-employed with hyphen, space, or underscore', function () {
        expect(OnboardingValueInterpreter::parseEmploymentFromText('Self-employed'))->toBe('self_employed')
            ->and(OnboardingValueInterpreter::parseEmploymentFromText('self employed'))->toBe('self_employed')
            ->and(OnboardingValueInterpreter::parseEmploymentFromText('self_employed'))->toBe('self_employed');
    });

    it('detects part-time variants', function () {
        expect(OnboardingValueInterpreter::parseEmploymentFromText('Part-time'))->toBe('part_time')
            ->and(OnboardingValueInterpreter::parseEmploymentFromText('part time'))->toBe('part_time');
    });

    it('detects full-time variants', function () {
        expect(OnboardingValueInterpreter::parseEmploymentFromText('Full-time'))->toBe('full_time');
    });

    it('detects retired', function () {
        expect(OnboardingValueInterpreter::parseEmploymentFromText('Retired'))->toBe('retired');
    });

    it('detects unemployed and synonyms', function () {
        expect(OnboardingValueInterpreter::parseEmploymentFromText('Unemployed'))->toBe('unemployed')
            ->and(OnboardingValueInterpreter::parseEmploymentFromText('Not working'))->toBe('unemployed')
            ->and(OnboardingValueInterpreter::parseEmploymentFromText('Out of work'))->toBe('unemployed');
    });

    it('maps student to other (no student enum value)', function () {
        expect(OnboardingValueInterpreter::parseEmploymentFromText('Student'))->toBe('other');
    });

    it('detects plain employed', function () {
        expect(OnboardingValueInterpreter::parseEmploymentFromText('Employed'))->toBe('employed');
    });

    it('rejects unknown input', function () {
        expect(OnboardingValueInterpreter::parseEmploymentFromText('something else'))->toBeNull();
    });
});
