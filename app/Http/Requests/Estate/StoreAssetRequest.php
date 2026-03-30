<?php

declare(strict_types=1);

namespace App\Http\Requests\Estate;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_type' => 'required|in:property,pension,investment,savings,business,life_insurance,personal,other',
            'asset_name' => 'required|string|max:255',
            'current_value' => 'required|numeric|min:0',
            'ownership_type' => 'sometimes|in:individual,joint,trust',
            'beneficiary_designation' => 'nullable|string|max:255',
            'is_iht_exempt' => 'boolean',
            'exemption_reason' => 'nullable|string|max:255',
            'valuation_date' => 'sometimes|date',
            'property_address' => 'nullable|string|max:500',
            'mortgage_outstanding' => 'nullable|numeric|min:0',
            'is_main_residence' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }
}
