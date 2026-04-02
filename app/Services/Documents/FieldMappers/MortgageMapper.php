<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

class MortgageMapper extends AbstractFieldMapper
{
    protected array $fieldMappings = [
        'lender_name' => 'lender_name',
        'mortgage_account_number' => 'mortgage_account_number',
        'mortgage_type' => 'mortgage_type',
        'original_loan_amount' => 'original_loan_amount',
        'outstanding_balance' => 'outstanding_balance',
        'interest_rate' => 'interest_rate',
        'rate_type' => 'rate_type',
        'monthly_payment' => 'monthly_payment',
        'start_date' => 'start_date',
        'maturity_date' => 'end_date',
        'remaining_term_months' => 'remaining_term_months',
    ];

    public function __construct()
    {
        $this->transformations = [
            'lender_name' => fn ($v) => $this->normalizeString($v),
            'mortgage_account_number' => fn ($v) => $this->normalizeString($v),
            'mortgage_type' => fn ($v) => $this->parseEnum($v, ['repayment', 'interest_only', 'mixed'], 'repayment'),
            'original_loan_amount' => fn ($v) => $this->parseDecimal($v),
            'outstanding_balance' => fn ($v) => $this->parseDecimal($v),
            'interest_rate' => fn ($v) => $this->parsePercentage($v),
            'rate_type' => fn ($v) => $this->parseEnum($v, ['fixed', 'variable', 'tracker', 'discount', 'mixed'], 'variable'),
            'monthly_payment' => fn ($v) => $this->parseDecimal($v),
            'start_date' => fn ($v) => $this->parseDate($v),
            'maturity_date' => fn ($v) => $this->parseDate($v),
            'remaining_term_months' => fn ($v) => $this->parseInt($v),
        ];
    }

    public function getModelClass(): string
    {
        return \App\Models\Mortgage::class;
    }

    public function getSubtype(): string
    {
        return 'mortgage';
    }

    public function getRequiredFields(): array
    {
        return ['lender_name', 'outstanding_balance'];
    }

    public function getOptionalFields(): array
    {
        return ['mortgage_account_number', 'mortgage_type', 'original_loan_amount', 'interest_rate', 'rate_type', 'monthly_payment', 'start_date', 'maturity_date', 'remaining_term_months'];
    }
}
