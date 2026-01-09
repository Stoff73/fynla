<?php

declare(strict_types=1);

namespace App\Http\Requests\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStatePensionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'ni_years_completed' => ['nullable', 'integer', 'min:0', 'max:50'],
            'ni_years_required' => ['nullable', 'integer', 'min:0', 'max:50'],
            'state_pension_forecast_annual' => ['nullable', 'numeric', 'min:0'],
            'state_pension_age' => ['nullable', 'integer', 'min:60', 'max:70'],
            'ni_gaps' => ['nullable', 'array'],
            'gap_fill_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'ni_years_completed.required' => 'Please enter the number of NI years completed.',
            'ni_years_required.required' => 'Please enter the number of NI years required.',
            'ni_years_completed.min' => 'NI years completed cannot be negative.',
            'ni_years_completed.max' => 'NI years completed cannot exceed 50.',
        ];
    }
}
