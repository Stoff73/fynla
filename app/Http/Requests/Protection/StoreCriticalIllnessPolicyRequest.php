<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCriticalIllnessPolicyRequest extends FormRequest
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
            'policy_type' => ['required', Rule::in(['standalone', 'accelerated', 'additional'])],
            'provider' => ['required', 'string', 'max:255'],
            'policy_number' => ['nullable', 'string', 'max:255'],
            'sum_assured' => ['required', 'numeric', 'min:1000'],
            'premium_amount' => ['required', 'numeric', 'min:0'],
            'premium_frequency' => ['required', Rule::in(['monthly', 'quarterly', 'annually'])],
            'policy_start_date' => ['nullable', 'date', 'before_or_equal:today'],
            'policy_end_date' => ['nullable', 'date', 'after:policy_start_date'],
            'policy_term_years' => ['nullable', 'integer', 'min:1', 'max:50'],
            'conditions_covered' => ['nullable', 'array'],
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
            'policy_type.required' => 'Policy type is required.',
            'policy_type.in' => 'Invalid policy type. Choose from standalone, accelerated, or additional.',
            'provider.required' => 'Provider is required.',
            'sum_assured.required' => 'Sum assured is required.',
            'sum_assured.min' => 'Sum assured must be at least £1,000.',
            'premium_amount.required' => 'Premium amount is required.',
            'premium_frequency.required' => 'Premium frequency is required.',
            'policy_end_date.after' => 'Policy end date must be after the start date.',
        ];
    }
}
