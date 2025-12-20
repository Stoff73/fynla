<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCriticalIllnessPolicyRequest extends FormRequest
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
            'policy_type' => ['sometimes', Rule::in(['standalone', 'accelerated', 'additional'])],
            'provider' => ['sometimes', 'string', 'max:255'],
            'policy_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sum_assured' => ['sometimes', 'numeric', 'min:1000'],
            'premium_amount' => ['sometimes', 'numeric', 'min:0'],
            'premium_frequency' => ['sometimes', Rule::in(['monthly', 'quarterly', 'annually'])],
            'policy_start_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'policy_end_date' => ['sometimes', 'nullable', 'date', 'after:policy_start_date'],
            'policy_term_years' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'conditions_covered' => ['sometimes', 'nullable', 'array'],
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
            'policy_type.in' => 'Invalid policy type. Choose from standalone, accelerated, or additional.',
            'sum_assured.min' => 'Sum assured must be at least £1,000.',
            'policy_end_date.after' => 'Policy end date must be after the start date.',
        ];
    }
}
