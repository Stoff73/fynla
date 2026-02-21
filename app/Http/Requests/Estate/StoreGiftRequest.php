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
            'gift_date' => 'required|date|before_or_equal:today',
            'recipient' => 'required|string|max:255',
            'gift_type' => 'required|in:pet,clt,exempt,small_gift,annual_exemption',
            'gift_value' => 'required|numeric|min:0',
            'status' => 'sometimes|in:within_7_years,survived_7_years',
            'taper_relief_applicable' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }
}
