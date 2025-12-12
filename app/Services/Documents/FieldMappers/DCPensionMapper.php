<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

class DCPensionMapper extends AbstractFieldMapper
{
    protected array $fieldMappings = [
        'provider' => 'provider',
        'scheme_name' => 'scheme_name',
        'member_number' => 'member_number',
        'pension_type' => 'pension_type',
        'current_fund_value' => 'current_fund_value',
        'annual_salary' => 'annual_salary',
        'employee_contribution_percent' => 'employee_contribution_percent',
        'employer_contribution_percent' => 'employer_contribution_percent',
        'monthly_contribution_amount' => 'monthly_contribution_amount',
        'platform_fee_percent' => 'platform_fee_percent',
        'retirement_age' => 'retirement_age',
        'projected_value_at_retirement' => 'projected_value_at_retirement',
        'investment_strategy' => 'investment_strategy',
    ];

    public function __construct()
    {
        $this->transformations = [
            'provider' => fn ($v) => $this->normalizeString($v),
            'scheme_name' => fn ($v) => $this->normalizeString($v),
            'member_number' => fn ($v) => $this->normalizeString($v),
            'pension_type' => fn ($v) => $this->normalizePensionType($v),
            'current_fund_value' => fn ($v) => $this->parseDecimal($v),
            'annual_salary' => fn ($v) => $this->parseDecimal($v),
            'employee_contribution_percent' => fn ($v) => $this->parseContributionPercent($v),
            'employer_contribution_percent' => fn ($v) => $this->parseContributionPercent($v),
            'monthly_contribution_amount' => fn ($v) => $this->parseDecimal($v),
            'platform_fee_percent' => fn ($v) => $this->parsePercentage($v),
            'retirement_age' => fn ($v) => $this->parseInt($v),
            'projected_value_at_retirement' => fn ($v) => $this->parseDecimal($v),
            'investment_strategy' => fn ($v) => $this->normalizeString($v),
        ];
    }

    public function getModelClass(): string
    {
        return \App\Models\DCPension::class;
    }

    public function getSubtype(): string
    {
        return 'dc_pension';
    }

    public function getRequiredFields(): array
    {
        return [
            'pension_type',
            'current_fund_value',
        ];
    }

    public function getOptionalFields(): array
    {
        return [
            'provider',
            'scheme_name',
            'member_number',
            'annual_salary',
            'employee_contribution_percent',
            'employer_contribution_percent',
            'monthly_contribution_amount',
            'platform_fee_percent',
            'retirement_age',
            'projected_value_at_retirement',
            'investment_strategy',
        ];
    }

    /**
     * Normalize pension type to canonical values.
     */
    private function normalizePensionType(?string $type): ?string
    {
        if (! $type) {
            return 'personal'; // Default
        }

        $type = strtolower(trim($type));

        return match (true) {
            str_contains($type, 'occupational') || str_contains($type, 'workplace') => 'occupational',
            str_contains($type, 'sipp') => 'sipp',
            str_contains($type, 'stakeholder') => 'stakeholder',
            str_contains($type, 'personal') => 'personal',
            default => 'personal',
        };
    }

    /**
     * Parse contribution percentage - handles both decimal (0.05) and whole number (5) formats.
     */
    private function parseContributionPercent(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $percent = $this->parsePercentage($value);

        if ($percent === null) {
            return null;
        }

        // The database stores as whole numbers (5 for 5%), not decimals
        // But AI may return either format
        if ($percent < 1) {
            // It's in decimal form (0.05), convert to whole number
            return $percent * 100;
        }

        // Already a whole number
        return $percent;
    }
}
