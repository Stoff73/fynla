<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

use App\Models\DBPension;

class DBPensionMapper extends AbstractFieldMapper
{
    protected array $fieldMappings = [
        'scheme_name' => 'scheme_name',
        'scheme_type' => 'scheme_type',
        'accrued_annual_pension' => 'accrued_annual_pension',
        'pensionable_service_years' => 'pensionable_service_years',
        'pensionable_salary' => 'pensionable_salary',
        'normal_retirement_age' => 'normal_retirement_age',
        'spouse_pension_percent' => 'spouse_pension_percent',
        'lump_sum_entitlement' => 'lump_sum_entitlement',
        'inflation_protection' => 'inflation_protection',
        'revaluation_method' => 'revaluation_method',
    ];

    public function __construct()
    {
        $this->transformations = [
            'scheme_name' => fn ($v) => $this->normalizeString($v),
            'scheme_type' => fn ($v) => $this->normalizeSchemeType($v),
            'accrued_annual_pension' => fn ($v) => $this->parseDecimal($v),
            'pensionable_service_years' => fn ($v) => $this->parseDecimal($v),
            'pensionable_salary' => fn ($v) => $this->parseDecimal($v),
            'normal_retirement_age' => fn ($v) => $this->parseInt($v),
            'spouse_pension_percent' => fn ($v) => $this->parseSpousePercent($v),
            'lump_sum_entitlement' => fn ($v) => $this->parseDecimal($v),
            'inflation_protection' => fn ($v) => $this->normalizeInflationProtection($v),
            'revaluation_method' => fn ($v) => $this->normalizeString($v),
        ];
    }

    public function getModelClass(): string
    {
        return DBPension::class;
    }

    public function getSubtype(): string
    {
        return 'db_pension';
    }

    public function getRequiredFields(): array
    {
        return [
            'scheme_name',
            'scheme_type',
            'accrued_annual_pension',
        ];
    }

    public function getOptionalFields(): array
    {
        return [
            'pensionable_service_years',
            'pensionable_salary',
            'normal_retirement_age',
            'spouse_pension_percent',
            'lump_sum_entitlement',
            'inflation_protection',
            'revaluation_method',
        ];
    }

    /**
     * Normalize scheme type to canonical values.
     */
    private function normalizeSchemeType(?string $type): ?string
    {
        if (! $type) {
            return null;
        }

        $type = strtolower(trim($type));

        return match (true) {
            str_contains($type, 'final') || str_contains($type, 'salary') => 'final_salary',
            str_contains($type, 'career') || str_contains($type, 'average') || str_contains($type, 'care') => 'career_average',
            str_contains($type, 'public') || str_contains($type, 'nhs') ||
            str_contains($type, 'teacher') || str_contains($type, 'civil') ||
            str_contains($type, 'local gov') || str_contains($type, 'lgps') => 'public_sector',
            default => 'final_salary',
        };
    }

    /**
     * Parse spouse pension percentage - stored as decimal in DB (0.50 for 50%).
     */
    /**
     * `spouse_pension_percent` is stored in PERCENTAGE POINTS — 50 for 50%.
     *
     * This method used to do the exact opposite, converting 50 to 0.50 on the
     * strength of a comment that said "DB stores as decimal". It did not: the
     * column is validated `min:0|max:100` by both StoreDBPensionRequest and
     * PensionStore, the factory seeds 50.0 / 66.67 / 100.0, and both
     * HouseholdPlanningService and PensionDerivedColumnCalculator divide by 100
     * when they use it. Only this mapper and the extraction prompt that feeds it
     * believed otherwise, so a Defined Benefit pension imported from a document
     * projected the spouse's pension at a hundredth of the real figure (W-0030).
     */
    private function parseSpousePercent(mixed $value): ?float
    {
        return $this->parsePercentagePoints($value);
    }

    /**
     * Normalize inflation protection type.
     */
    private function normalizeInflationProtection(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = strtolower(trim($value));

        return match (true) {
            str_contains($value, 'cpi') => 'cpi',
            str_contains($value, 'rpi') => 'rpi',
            str_contains($value, 'fixed') => 'fixed',
            str_contains($value, 'none') || str_contains($value, 'no ') => 'none',
            default => null,
        };
    }
}
