<?php

declare(strict_types=1);

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for creating investment holdings.
 */
class StoreHoldingRequest extends FormRequest
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
            'investment_account_id' => 'sometimes|exists:investment_accounts,id',
            'asset_type' => ['sometimes', Rule::in($this->getAssetTypes())],
            'sub_type' => ['nullable', 'string', 'required_if:asset_type,fund', Rule::in($this->getSubTypes())],
            'security_name' => 'sometimes|string|max:255',
            'ticker' => 'nullable|string|max:50',
            'isin' => 'nullable|string|max:50',
            'allocation_percent' => 'sometimes|numeric|min:0|max:100',
            'purchase_price' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'current_price' => 'nullable|numeric|min:0',
            'current_value' => 'sometimes|numeric|min:0',
            'dividend_yield' => 'nullable|numeric|min:0|max:100',
            'ocf_percent' => 'nullable|numeric|min:0|max:100',
        ];
    }

    /**
     * Get valid asset types.
     */
    private function getAssetTypes(): array
    {
        return [
            'equity',
            'bond',
            'fund',
            'etf',
            'alternative',
            'uk_equity',
            'us_equity',
            'international_equity',
            'cash',
            'property',
        ];
    }

    /**
     * Get valid sub types for fund holdings.
     */
    private function getSubTypes(): array
    {
        return [
            'equity_fund',
            'bond_fund',
            'mixed_fund',
            'income_fund',
            'index_fund',
            'money_market_fund',
            'property_fund',
        ];
    }
}
