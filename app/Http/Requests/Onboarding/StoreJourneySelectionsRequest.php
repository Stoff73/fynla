<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Services\Onboarding\JourneyStateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJourneySelectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'journeys' => 'sometimes|array|min:1',
            'journeys.*' => ['sometimes', 'string', Rule::in(JourneyStateService::JOURNEYS)],
        ];
    }

    public function messages(): array
    {
        return [
            'journeys.required' => 'Please select at least one focus area.',
            'journeys.min' => 'Please select at least one focus area.',
            'journeys.*.in' => 'Invalid focus area selected.',
        ];
    }
}
