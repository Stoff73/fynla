<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

class ProtectionMapper extends AbstractFieldMapper
{
    protected array $fieldMappings = [
        'provider' => 'provider',
        'policy_number' => 'policy_number',
        'policy_type' => 'policy_type',
        'sum_assured' => 'sum_assured',
        'premium_amount' => 'premium_amount',
        'premium_frequency' => 'premium_frequency',
        'policy_start_date' => 'policy_start_date',
        'policy_end_date' => 'policy_end_date',
        'term_years' => 'policy_term_years',
        'in_trust' => 'in_trust',
    ];

    public function __construct()
    {
        $this->transformations = [
            'provider' => fn ($v) => $this->normalizeString($v),
            'policy_number' => fn ($v) => $this->normalizeString($v),
            'policy_type' => fn ($v) => $this->normalizePolicyType($v),
            'sum_assured' => fn ($v) => $this->parseDecimal($v),
            'premium_amount' => fn ($v) => $this->parseDecimal($v),
            'premium_frequency' => fn ($v) => $this->parseEnum($v, ['monthly', 'quarterly', 'annually'], 'monthly'),
            'policy_start_date' => fn ($v) => $this->parseDate($v),
            'policy_end_date' => fn ($v) => $this->parseDate($v),
            'term_years' => fn ($v) => $this->parseInt($v),
            'in_trust' => fn ($v) => $this->parseBool($v),
        ];
    }

    public function getModelClass(): string
    {
        return \App\Models\LifeInsurancePolicy::class;
    }

    /**
     * Get the correct model class based on policy type.
     */
    public function getModelClassForType(string $type): string
    {
        return match ($type) {
            'critical_illness', 'standalone', 'accelerated', 'additional' => \App\Models\CriticalIllnessPolicy::class,
            'income_protection' => \App\Models\IncomeProtectionPolicy::class,
            default => \App\Models\LifeInsurancePolicy::class,
        };
    }

    public function getSubtype(): string
    {
        return 'protection';
    }

    public function getRequiredFields(): array
    {
        return ['provider', 'sum_assured'];
    }

    public function getOptionalFields(): array
    {
        return ['policy_number', 'policy_type', 'premium_amount', 'premium_frequency', 'policy_start_date', 'policy_end_date', 'term_years', 'in_trust'];
    }

    private function normalizePolicyType(?string $type): ?string
    {
        if (! $type) {
            return 'term';
        }

        $type = strtolower(trim($type));

        return match (true) {
            str_contains($type, 'whole') => 'whole_of_life',
            str_contains($type, 'decreasing') => 'decreasing_term',
            str_contains($type, 'level') => 'level_term',
            str_contains($type, 'family') || str_contains($type, 'income benefit') => 'family_income_benefit',
            str_contains($type, 'critical') || str_contains($type, 'ci') => 'critical_illness',
            str_contains($type, 'income protection') || str_contains($type, 'ip') => 'income_protection',
            str_contains($type, 'term') => 'term',
            default => 'term',
        };
    }
}
