<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonalInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by auth middleware
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && $this->phone) {
            $this->merge([
                'phone' => preg_replace('/[\s\-]/', '', $this->phone),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $minAge18 = now()->subYears(18)->format('Y-m-d');
        $maxAge105 = now()->subYears(105)->format('Y-m-d');

        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'surname' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before_or_equal:'.$minAge18, 'after:'.$maxAge105],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'marital_status' => ['sometimes', 'nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'national_insurance_number' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z]{2}[0-9]{6}[A-Z]{1}$/'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'county' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postcode' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z]{1,2}[0-9]{1,2}[A-Z]?\s?[0-9][A-Z]{2}$/i'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^(\+44|0)[0-9]{10}$/'],
            'good_health' => ['sometimes', 'nullable', 'boolean'],
            'smoker' => ['sometimes', 'nullable', 'boolean'],
            'education_level' => ['sometimes', 'nullable', Rule::in(['secondary', 'a_level', 'undergraduate', 'postgraduate', 'doctorate', 'foundation', 'hnd', 'professional', 'other'])],
            'charitable_bequest' => ['sometimes', 'nullable', 'boolean'],
            'is_registered_blind' => ['nullable', 'boolean'],
            'life_expectancy_override' => ['sometimes', 'nullable', 'integer', 'min:60', 'max:110'],
            'monthly_expenditure' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'annual_expenditure' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'expenditure_entry_mode' => ['sometimes', 'nullable', 'string', 'in:simple,category'],
            'university' => ['sometimes', 'nullable', 'string', 'max:255'],
            'student_number' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'date_of_birth.before_or_equal' => 'You must be at least 18 years old.',
            'date_of_birth.after' => 'Date of birth cannot be more than 105 years ago.',
            'national_insurance_number.regex' => 'National Insurance number must be in format: AB123456C',
            'postcode.regex' => 'Please enter a valid UK postcode.',
            'phone.regex' => 'Please enter a valid UK phone number (e.g., 07700 900123 or +44 7700 900123).',
        ];
    }
}
