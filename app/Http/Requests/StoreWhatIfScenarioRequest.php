<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatIfScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'scenario_type' => ['sometimes', Rule::in(['retirement', 'property', 'family', 'income', 'custom'])],
            'parameters' => ['required', 'array'],
            'affected_modules' => ['sometimes', 'array'],
            'affected_modules.*' => [Rule::in(['retirement', 'investment', 'estate', 'protection', 'savings', 'property', 'income'])],
            'created_via' => ['sometimes', Rule::in(['ai_chat', 'manual'])],
            'ai_narrative' => ['nullable', 'string'],
        ];
    }
}
