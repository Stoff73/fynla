<?php

declare(strict_types=1);

namespace App\Http\Requests\Estate;

use Illuminate\Foundation\Http\FormRequest;

class StoreGiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gift_date' => 'sometimes|date|before_or_equal:today',
            'recipient' => 'sometimes|string|max:255',
            'gift_type' => 'sometimes|in:pet,clt,exempt,small_gift,annual_exemption',
            'gift_value' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:within_7_years,survived_7_years',
            'taper_relief_applicable' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }
}
