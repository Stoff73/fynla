<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonalAccountLineItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default period dates if not provided (current tax year: Apr 6 to Apr 5)
        if (! $this->has('period_start') || $this->period_start === null) {
            $now = now();
            $year = $now->month >= 4 && $now->day >= 6 ? $now->year : $now->year - 1;
            $this->merge([
                'period_start' => "{$year}-04-06",
                'period_end' => ($year + 1).'-04-05',
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'account_type' => ['nullable', Rule::in(['profit_and_loss', 'cashflow', 'balance_sheet'])],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'line_item' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['income', 'expense', 'asset', 'liability', 'equity', 'cash_inflow', 'cash_outflow'])],
            'amount' => ['required', 'numeric', 'min:-9999999999.99', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'line_item.required' => 'Please enter a line item description.',
            'category.required' => 'Please select a category.',
            'amount.required' => 'Please enter an amount.',
        ];
    }
}
