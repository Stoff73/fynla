<?php

declare(strict_types=1);

namespace App\Http\Requests\BusinessInterest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessInterestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Core business info
            'business_name' => ['sometimes', 'string', 'max:255'],
            'business_type' => ['sometimes', Rule::in(['sole_trader', 'partnership', 'limited_company', 'llp', 'other'])],
            'company_number' => ['nullable', 'string', 'max:50'],
            'industry_sector' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],

            // Ownership
            'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'trust'])],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'joint_owner_id' => ['nullable', 'exists:users,id'],
            'household_id' => ['nullable', 'exists:households,id'],
            'trust_id' => ['nullable', 'exists:trusts,id'],

            // Valuation
            'current_valuation' => ['sometimes', 'numeric', 'min:0'],
            'valuation_date' => ['sometimes', 'date'],
            'valuation_method' => ['nullable', 'string', 'max:255'],

            // Financials
            'annual_revenue' => ['nullable', 'numeric', 'min:0'],
            'annual_profit' => ['nullable', 'numeric'],
            'annual_dividend_income' => ['nullable', 'numeric', 'min:0'],

            // Tax & Compliance
            'vat_registered' => ['nullable', 'boolean'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'utr_number' => ['nullable', 'string', 'max:50'],
            'tax_year_end' => ['nullable', 'date'],
            'employee_count' => ['nullable', 'integer', 'min:0'],
            'paye_reference' => ['nullable', 'string', 'max:50'],
            'trading_status' => ['nullable', Rule::in(['trading', 'dormant', 'pre_trading'])],

            // Exit Planning / BADR
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'bpr_eligible' => ['nullable', 'boolean'],

            // Notes
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'business_name' => 'business name',
            'business_type' => 'business type',
            'company_number' => 'company number',
            'current_valuation' => 'current valuation',
            'valuation_date' => 'valuation date',
            'annual_revenue' => 'annual revenue',
            'annual_profit' => 'annual profit',
            'vat_registered' => 'VAT registered',
            'vat_number' => 'VAT number',
            'utr_number' => 'UTR number',
            'tax_year_end' => 'tax year end',
            'employee_count' => 'employee count',
            'paye_reference' => 'PAYE reference',
            'acquisition_date' => 'acquisition date',
            'acquisition_cost' => 'acquisition cost',
            'bpr_eligible' => 'BPR eligible',
        ];
    }
}
