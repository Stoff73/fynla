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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'surname' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
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
            'education_level' => ['sometimes', 'nullable', Rule::in(['secondary', 'a_level', 'undergraduate', 'postgraduate', 'professional', 'other'])],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'national_insurance_number.regex' => 'National Insurance number must be in format: AB123456C',
            'postcode.regex' => 'Please enter a valid UK postcode.',
            'phone.regex' => 'Please enter a valid UK phone number (e.g., 07700900123 or +447700900123).',
        ];
    }
}
