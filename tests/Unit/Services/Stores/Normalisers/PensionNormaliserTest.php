<?php

declare(strict_types=1);

use App\Services\Stores\Normalisers\PensionNormaliser;

describe('PensionNormaliser::fromFormDc', function () {
    it('produces canonical DC-pension shape from HTTP form payload', function () {
        $canonical = (new PensionNormaliser)->fromFormDc([
            'scheme_name' => 'Aviva Workplace',
            'pension_type' => 'occupational',
            'provider' => 'Aviva',
            'current_fund_value' => 45000,
            'annual_salary' => 60000,
            'employee_contribution_percent' => 5,
            'employer_contribution_percent' => 5,
            'retirement_age' => 65,
            'salary_sacrifice' => true,
        ]);

        expect($canonical['type'])->toBe('dc');
        expect($canonical['scheme_name'])->toBe('Aviva Workplace');
        expect($canonical['pension_type'])->toBe('occupational');
        expect((float) $canonical['current_fund_value'])->toBe(45000.00);
        expect($canonical['salary_sacrifice'])->toBeTrue();
    });

    it('defaults pension_type to occupational when not supplied', function () {
        $canonical = (new PensionNormaliser)->fromFormDc([
            'scheme_name' => 'X',
            'current_fund_value' => 1000,
        ]);

        expect($canonical['pension_type'])->toBe('occupational');
    });

    it('defaults provider to scheme_name when not supplied', function () {
        $canonical = (new PensionNormaliser)->fromFormDc([
            'scheme_name' => 'NEST',
            'current_fund_value' => 1000,
        ]);

        expect($canonical['provider'])->toBe('NEST');
    });
});

describe('PensionNormaliser::fromFormDb', function () {
    it('produces canonical DB-pension shape from HTTP form payload', function () {
        $canonical = (new PensionNormaliser)->fromFormDb([
            'scheme_name' => 'NHS 2015',
            'scheme_type' => 'career_average',
            'accrued_annual_pension' => 12000,
            'pensionable_service_years' => 15,
            'pensionable_salary' => 45000,
            'normal_retirement_age' => 67,
            'spouse_pension_percent' => 37.5,
            'lump_sum_entitlement' => 0,
            'inflation_protection' => 'cpi',
        ]);

        expect($canonical['type'])->toBe('db');
        expect($canonical['scheme_name'])->toBe('NHS 2015');
        expect($canonical['scheme_type'])->toBe('career_average');
        expect((float) $canonical['accrued_annual_pension'])->toBe(12000.00);
    });

    it('defaults scheme_type to final_salary when not in the allowlist', function () {
        $canonical = (new PensionNormaliser)->fromFormDb([
            'scheme_name' => 'X',
            'scheme_type' => 'made_up_type',
            'accrued_annual_pension' => 1,
        ]);

        expect($canonical['scheme_type'])->toBe('final_salary');
    });
});

describe('PensionNormaliser::fromFormState', function () {
    it('produces canonical state-pension shape from HTTP form payload', function () {
        $canonical = (new PensionNormaliser)->fromFormState([
            'ni_years_completed' => 28,
            'ni_years_required' => 35,
            'state_pension_forecast_annual' => 9000,
            'state_pension_age' => 67,
            'already_receiving' => false,
        ]);

        expect($canonical['type'])->toBe('state');
        expect((int) $canonical['ni_years_completed'])->toBe(28);
        expect((int) $canonical['ni_years_required'])->toBe(35);
        expect($canonical['already_receiving'])->toBeFalse();
    });
});

describe('PensionNormaliser::fromFynPension', function () {
    it('maps Fyn create_pension DC params to canonical', function () {
        $canonical = (new PensionNormaliser)->fromFynPension([
            'pension_category' => 'dc',
            'scheme_name' => 'Aviva SIPP',
            'scheme_type' => 'sipp',
            'current_fund_value' => 25000,
            'monthly_contribution_amount' => 250,
            'employer_contribution_percent' => 3,
            'retirement_age' => 65,
        ]);

        expect($canonical['type'])->toBe('dc');
        expect($canonical['pension_type'])->toBe('sipp');
        expect($canonical['provider'])->toBe('Aviva SIPP');
    });

    it('maps Fyn create_pension DB params to canonical', function () {
        $canonical = (new PensionNormaliser)->fromFynPension([
            'pension_category' => 'db',
            'scheme_name' => 'BT Final Salary',
            'scheme_type' => 'final_salary',
            'accrued_annual_pension' => 8000,
            'pensionable_service_years' => 10,
            'normal_retirement_age' => 60,
        ]);

        expect($canonical['type'])->toBe('db');
        expect($canonical['scheme_type'])->toBe('final_salary');
    });

    it('coerces Fyn scheme_type workplace -> occupational for DC', function () {
        $canonical = (new PensionNormaliser)->fromFynPension([
            'pension_category' => 'dc',
            'scheme_name' => 'NEST',
            'scheme_type' => 'workplace',
            'current_fund_value' => 1000,
        ]);

        expect($canonical['pension_type'])->toBe('occupational');
    });
});

describe('PensionNormaliser::fromFynInputHistory', function () {
    it('maps Fyn capture_pension_history entries to canonical per-year shape', function () {
        $canonical = (new PensionNormaliser)->fromFynInputHistory([
            ['tax_year' => '2024-25', 'pension_input_amount' => 9000],
            ['tax_year' => '2025-26', 'pension_input_amount' => 12000],
            ['tax_year' => '2025-26', 'pension_input_amount' => -50],
            ['tax_year' => '', 'pension_input_amount' => 1000],
        ]);

        expect($canonical['entries'])->toHaveCount(2);
        expect($canonical['entries'][0]['tax_year'])->toBe('2024-25');
        expect((float) $canonical['entries'][0]['pension_input_amount'])->toBe(9000.00);
    });
});

describe('PensionNormaliser::fromUploadDc', function () {
    it('maps a DC pension document-extraction shape to canonical', function () {
        $canonical = (new PensionNormaliser)->fromUploadDc([
            'scheme_name' => 'Standard Life',
            'provider' => 'Standard Life',
            'current_fund_value' => 32500,
            'pension_type' => 'personal',
            'source_document_id' => 99,
        ]);

        expect($canonical['type'])->toBe('dc');
        expect($canonical['scheme_name'])->toBe('Standard Life');
        expect((float) $canonical['current_fund_value'])->toBe(32500.00);
        expect($canonical)->not->toHaveKey('source_document_id');
    });
});
