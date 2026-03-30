<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDomicileInfoRequest extends FormRequest
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
            'domicile_status' => 'required|in:uk_domiciled,non_uk_domiciled',
            'country_of_birth' => 'required|string|max:255',
            'uk_arrival_date' => [
                'required_if:domicile_status,non_uk_domiciled',
                'nullable',
                'date',
                'before_or_equal:today',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'domicile_status.required' => 'Please select your domicile status.',
            'domicile_status.in' => 'Invalid domicile status selected.',
            'country_of_birth.required' => 'Please enter your country of birth.',
            'country_of_birth.max' => 'Country of birth must not exceed 255 characters.',
            'uk_arrival_date.required_if' => 'UK arrival date is required when you are non-UK domiciled.',
            'uk_arrival_date.date' => 'UK arrival date must be a valid date.',
            'uk_arrival_date.before_or_equal' => 'UK arrival date cannot be in the future.',
        ];
    }
}
