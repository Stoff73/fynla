<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmExtractionRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'data' => ['sometimes', 'array'],
            'data.*' => ['nullable'], // Allow any fields, model validation will handle specifics
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'data.required' => 'Please provide the confirmed data.',
            'data.array' => 'Data must be provided as an object.',
        ];
    }
}
