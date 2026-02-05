<?php

declare(strict_types=1);

namespace App\Http\Requests\Estate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for intestacy calculation.
 */
class CalculateIntestacyRequest extends FormRequest
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
            'estate_value' => 'nullable|numeric|min:0',
        ];
    }
}
