<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

class SavingsAccountMapper extends AbstractFieldMapper
{
    protected array $fieldMappings = [
        'institution' => 'institution',
        'account_number' => 'account_number',
        'account_type' => 'account_type',
        'current_balance' => 'current_balance',
        'interest_rate' => 'interest_rate',
        'access_type' => 'access_type',
        'notice_period_days' => 'notice_period_days',
        'maturity_date' => 'maturity_date',
        'is_isa' => 'is_isa',
    ];

    public function __construct()
    {
        $this->transformations = [
            'institution' => fn ($v) => $this->normalizeString($v),
            'account_number' => fn ($v) => $this->normalizeString($v),
            'account_type' => fn ($v) => $this->normalizeString($v),
            'current_balance' => fn ($v) => $this->parseDecimal($v),
            'interest_rate' => fn ($v) => $this->parsePercentage($v),
            'access_type' => fn ($v) => $this->parseEnum($v, ['immediate', 'notice', 'fixed'], 'immediate'),
            'notice_period_days' => fn ($v) => $this->parseInt($v),
            'maturity_date' => fn ($v) => $this->parseDate($v),
            'is_isa' => fn ($v) => $this->parseBool($v),
        ];
    }

    public function getModelClass(): string
    {
        return \App\Models\SavingsAccount::class;
    }

    public function getSubtype(): string
    {
        return 'savings_account';
    }

    public function getRequiredFields(): array
    {
        return ['institution', 'current_balance'];
    }

    public function getOptionalFields(): array
    {
        return ['account_number', 'account_type', 'interest_rate', 'access_type', 'notice_period_days', 'maturity_date', 'is_isa'];
    }
}
