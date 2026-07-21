<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

class PensionNormaliser
{
    private const ALLOWED_DB_SCHEME_TYPES = ['final_salary', 'career_average', 'public_sector'];

    private const ALLOWED_DC_PENSION_TYPES = ['occupational', 'sipp', 'personal', 'stakeholder'];

    public function fromFormDc(array $request): array
    {
        $data = $request;
        $data['type'] = 'dc';
        $data['pension_type'] = $data['pension_type'] ?? 'occupational';
        $data['provider'] = $data['provider'] ?? ($data['scheme_name'] ?? null);

        return $data;
    }

    public function fromFormDb(array $request): array
    {
        $data = $request;
        $data['type'] = 'db';
        $rawSchemeType = $data['scheme_type'] ?? 'final_salary';
        $data['scheme_type'] = in_array($rawSchemeType, self::ALLOWED_DB_SCHEME_TYPES, true)
            ? $rawSchemeType
            : 'final_salary';

        return $data;
    }

    public function fromFormState(array $request): array
    {
        $data = $request;
        $data['type'] = 'state';

        return $data;
    }

    public function fromFynPension(array $toolParams): array
    {
        $category = $toolParams['pension_category'] ?? 'dc';

        if ($category === 'db') {
            $rawSchemeType = $toolParams['scheme_type'] ?? 'final_salary';
            $schemeType = in_array($rawSchemeType, self::ALLOWED_DB_SCHEME_TYPES, true)
                ? $rawSchemeType
                : 'final_salary';

            $canonical = [
                'type' => 'db',
                'scheme_name' => $toolParams['scheme_name'],
                'scheme_type' => $schemeType,
            ];

            foreach (['accrued_annual_pension', 'pensionable_service_years', 'pensionable_salary', 'spouse_pension_percent', 'lump_sum_entitlement'] as $f) {
                if (isset($toolParams[$f]) && is_numeric($toolParams[$f])) {
                    $canonical[$f] = (float) $toolParams[$f];
                }
            }
            if (isset($toolParams['normal_retirement_age']) && is_numeric($toolParams['normal_retirement_age'])) {
                $canonical['normal_retirement_age'] = (int) $toolParams['normal_retirement_age'];
            }
            foreach (['revaluation_method', 'inflation_protection'] as $f) {
                if (isset($toolParams[$f]) && $toolParams[$f] !== '') {
                    $canonical[$f] = $toolParams[$f];
                }
            }

            return $canonical;
        }

        $pensionType = match ($toolParams['scheme_type'] ?? 'workplace') {
            'workplace', 'occupational' => 'occupational',
            'sipp', 'self_invested' => 'sipp',
            'personal', 'personal_pension' => 'personal',
            'stakeholder' => 'stakeholder',
            default => 'occupational',
        };

        $canonical = [
            'type' => 'dc',
            'scheme_name' => $toolParams['scheme_name'],
            'pension_type' => $pensionType,
            'provider' => ! empty($toolParams['provider']) ? $toolParams['provider'] : $toolParams['scheme_name'],
        ];

        foreach (['current_fund_value', 'annual_salary', 'employee_contribution_percent', 'employer_contribution_percent', 'employer_matching_limit', 'monthly_contribution_amount', 'lump_sum_contribution', 'expected_return_percent', 'platform_fee_percent', 'advisor_fee_percent'] as $f) {
            if (isset($toolParams[$f]) && is_numeric($toolParams[$f])) {
                $canonical[$f] = (float) $toolParams[$f];
            }
        }
        if (isset($toolParams['retirement_age']) && is_numeric($toolParams['retirement_age'])) {
            $canonical['retirement_age'] = (int) $toolParams['retirement_age'];
        }
        foreach (['member_number', 'investment_strategy'] as $f) {
            if (isset($toolParams[$f]) && $toolParams[$f] !== '') {
                $canonical[$f] = $toolParams[$f];
            }
        }

        return $canonical;
    }

    public function fromFynInputHistory(array $toolParams): array
    {
        $history = $toolParams['history'] ?? $toolParams;
        if (! is_array($history)) {
            return ['type' => 'pension_input_history', 'entries' => []];
        }

        $entries = [];
        foreach ($history as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $taxYear = isset($entry['tax_year']) ? (string) $entry['tax_year'] : null;
            $amount = isset($entry['pension_input_amount']) ? (float) $entry['pension_input_amount'] : null;
            if ($taxYear === null || $taxYear === '' || $amount === null || $amount < 0) {
                continue;
            }
            $entries[] = [
                'tax_year' => $taxYear,
                'pension_input_amount' => $amount,
            ];
        }

        return [
            'type' => 'pension_input_history',
            'entries' => $entries,
        ];
    }

    public function fromUploadDc(array $extraction): array
    {
        $canonical = [
            'type' => 'dc',
            'scheme_name' => $extraction['scheme_name'] ?? $extraction['provider'] ?? 'Imported pension',
            'pension_type' => $extraction['pension_type'] ?? 'occupational',
            'provider' => $extraction['provider'] ?? ($extraction['scheme_name'] ?? null),
            'current_fund_value' => (float) ($extraction['current_fund_value'] ?? 0),
        ];

        foreach (['annual_salary', 'employee_contribution_percent', 'employer_contribution_percent', 'monthly_contribution_amount', 'retirement_age', 'member_number', 'investment_strategy', 'platform_fee_percent'] as $optional) {
            if (array_key_exists($optional, $extraction)) {
                $canonical[$optional] = $extraction[$optional];
            }
        }

        return $canonical;
    }

    public function fromUploadDb(array $extraction): array
    {
        $rawSchemeType = $extraction['scheme_type'] ?? 'final_salary';
        $schemeType = in_array($rawSchemeType, self::ALLOWED_DB_SCHEME_TYPES, true)
            ? $rawSchemeType
            : 'final_salary';

        $canonical = [
            'type' => 'db',
            'scheme_name' => $extraction['scheme_name'] ?? 'Imported DB pension',
            'scheme_type' => $schemeType,
        ];

        foreach (['accrued_annual_pension', 'pensionable_service_years', 'pensionable_salary', 'normal_retirement_age', 'spouse_pension_percent', 'lump_sum_entitlement', 'inflation_protection'] as $optional) {
            if (array_key_exists($optional, $extraction)) {
                $canonical[$optional] = $extraction[$optional];
            }
        }

        return $canonical;
    }
}
