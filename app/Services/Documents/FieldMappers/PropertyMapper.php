<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

class PropertyMapper extends AbstractFieldMapper
{
    protected array $fieldMappings = [
        'address' => 'address',
        'current_value' => 'current_value',
        'property_type' => 'property_type',
        'ownership_type' => 'ownership_type',
        'rental_income_monthly' => 'rental_income_monthly',
        'mortgage_outstanding' => 'mortgage_outstanding',
        'purchase_price' => 'purchase_price',
        'purchase_date' => 'purchase_date',
    ];

    public function __construct()
    {
        $this->transformations = [
            'address' => fn ($v) => $this->normalizeString($v),
            'current_value' => fn ($v) => $this->parseDecimal($v),
            'property_type' => fn ($v) => $this->parseEnum($v, [
                'main_residence', 'secondary_residence', 'buy_to_let',
            ], 'main_residence'),
            'ownership_type' => fn ($v) => $this->parseEnum($v, [
                'individual', 'joint', 'tenants_in_common', 'trust',
            ], 'individual'),
            'rental_income_monthly' => fn ($v) => $this->parseDecimal($v),
            'mortgage_outstanding' => fn ($v) => $this->parseDecimal($v),
            'purchase_price' => fn ($v) => $this->parseDecimal($v),
            'purchase_date' => fn ($v) => $this->parseDate($v),
        ];
    }

    public function getModelClass(): string
    {
        return \App\Models\Property::class;
    }

    public function getSubtype(): string
    {
        return 'property';
    }

    public function getRequiredFields(): array
    {
        return ['address', 'current_value'];
    }

    public function getOptionalFields(): array
    {
        return ['property_type', 'ownership_type', 'rental_income_monthly', 'mortgage_outstanding', 'purchase_price', 'purchase_date'];
    }
}
