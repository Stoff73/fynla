<?php

declare(strict_types=1);

namespace App\Http\Requests\Estate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for creating/updating wills.
 */
class StoreWillRequest extends FormRequest
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
            'has_will' => 'nullable|boolean',
            'spouse_primary_beneficiary' => 'boolean',
            'spouse_bequest_percentage' => 'nullable|numeric|min:0|max:100',
            'executor_name' => 'nullable|string|max:255',
            'executor_notes' => 'nullable|string',
            'will_last_updated' => 'nullable|date',
            'last_reviewed_date' => 'nullable|date',
        ];
    }
}
