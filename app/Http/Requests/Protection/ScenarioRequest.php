<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScenarioRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scenario_types' => ['nullable', 'array'],
            'scenario_types.*' => [Rule::in(['death', 'critical_illness', 'disability', 'premium_change'])],
            'new_coverage' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
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
            'scenario_types.array' => 'Scenario types must be an array.',
            'scenario_types.*.in' => 'Invalid scenario type selected.',
            'new_coverage.numeric' => 'New coverage must be a number.',
        ];
    }
}
