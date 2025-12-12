<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

class LifeInsuranceMapper extends AbstractFieldMapper
{
    protected array $fieldMappings = [
        'provider' => 'provider',
        'policy_number' => 'policy_number',
        'policy_type' => 'life_policy_type',
        'sum_assured' => 'sum_assured',
        'premium_amount' => 'premium_amount',
        'premium_frequency' => 'premium_frequency',
        'policy_start_date' => 'policy_start_date',
        'policy_end_date' => 'policy_end_date',
        'policy_term_years' => 'policy_term_years',
        'indexation_rate' => 'indexation_rate',
        'in_trust' => 'in_trust',
        'beneficiaries' => 'beneficiaries',
    ];

    public function __construct()
    {
        $this->transformations = [
            'provider' => fn ($v) => $this->normalizeString($v),
            'policy_number' => fn ($v) => $this->normalizeString($v),
            'policy_type' => fn ($v) => $this->normalizePolicyType($v),
            'sum_assured' => fn ($v) => $this->parseDecimal($v),
            'premium_amount' => fn ($v) => $this->parseDecimal($v),
            'premium_frequency' => fn ($v) => $this->normalizeFrequency($v),
            'policy_start_date' => fn ($v) => $this->parseDate($v),
            'policy_end_date' => fn ($v) => $this->parseDate($v),
            'policy_term_years' => fn ($v) => $this->parseInt($v),
            'indexation_rate' => fn ($v) => $this->parsePercentage($v),
            'in_trust' => fn ($v) => $this->parseBool($v),
            'beneficiaries' => fn ($v) => $this->normalizeString($v),
        ];
    }

    public function getModelClass(): string
    {
        return \App\Models\LifeInsurancePolicy::class;
    }

    public function getSubtype(): string
    {
        return 'life_insurance';
    }

    public function getRequiredFields(): array
    {
        return [
            'provider',
            'life_policy_type',
            'sum_assured',
            'premium_amount',
            'premium_frequency',
        ];
    }

    public function getOptionalFields(): array
    {
        return [
            'policy_number',
            'policy_start_date',
            'policy_end_date',
            'policy_term_years',
            'indexation_rate',
            'in_trust',
            'beneficiaries',
        ];
    }

    /**
     * Normalize policy type to canonical values.
     */
    private function normalizePolicyType(?string $type): ?string
    {
        if (! $type) {
            return 'term';
        }

        $type = strtolower(trim($type));

        return match (true) {
            str_contains($type, 'decreasing') => 'decreasing_term',
            str_contains($type, 'level') => 'level_term',
            str_contains($type, 'whole') || str_contains($type, 'life') => 'whole_of_life',
            str_contains($type, 'family') || str_contains($type, 'income') || str_contains($type, 'fib') => 'family_income_benefit',
            str_contains($type, 'term') => 'term',
            default => 'term',
        };
    }

    /**
     * Normalize payment frequency.
     */
    private function normalizeFrequency(?string $frequency): ?string
    {
        if (! $frequency) {
            return 'monthly';
        }

        $frequency = strtolower(trim($frequency));

        return match (true) {
            str_contains($frequency, 'month') => 'monthly',
            str_contains($frequency, 'quarter') => 'quarterly',
            str_contains($frequency, 'annual') || str_contains($frequency, 'year') => 'annually',
            default => 'monthly',
        };
    }
}
