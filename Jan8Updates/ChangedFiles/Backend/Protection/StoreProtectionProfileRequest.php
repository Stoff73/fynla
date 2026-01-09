<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Foundation\Http\FormRequest;

class StoreProtectionProfileRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'annual_income' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'monthly_expenditure' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'mortgage_balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'other_debts' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'number_of_dependents' => ['nullable', 'integer', 'min:0', 'max:20'],
            'dependents_ages' => ['nullable', 'array'],
            'dependents_ages.*' => ['integer', 'min:0', 'max:100'],
            'retirement_age' => ['nullable', 'integer', 'min:50', 'max:85'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'smoker_status' => ['nullable', 'boolean'],
            'health_status' => ['nullable', 'string', 'in:excellent,good,fair,poor'],
            'has_no_policies' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'annual_income.required' => 'Annual income is required.',
            'annual_income.numeric' => 'Annual income must be a number.',
            'monthly_expenditure.required' => 'Monthly expenditure is required.',
            'number_of_dependents.required' => 'Number of dependents is required.',
            'retirement_age.required' => 'Retirement age is required.',
            'retirement_age.min' => 'Retirement age must be at least 50.',
            'retirement_age.max' => 'Retirement age cannot exceed 85.',
        ];
    }
}
