<?php

declare(strict_types=1);

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for account projections with optional risk level override.
 */
class AccountProjectionsRequest extends FormRequest
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
            'risk_level' => ['nullable', 'string', Rule::in(['low', 'lower_medium', 'medium', 'upper_medium', 'high'])],
        ];
    }
}
