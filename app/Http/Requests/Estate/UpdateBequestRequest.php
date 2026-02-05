<?php

declare(strict_types=1);

namespace App\Http\Requests\Estate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for updating bequests.
 */
class UpdateBequestRequest extends FormRequest
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
            'beneficiary_name' => 'sometimes|string|max:255',
            'beneficiary_user_id' => 'nullable|exists:users,id',
            'bequest_type' => 'sometimes|in:percentage,specific_amount,specific_asset,residuary',
            'percentage_of_estate' => 'nullable|numeric|min:0|max:100',
            'specific_amount' => 'nullable|numeric|min:0',
            'specific_asset_description' => 'nullable|string',
            'asset_id' => 'nullable|exists:assets,id',
            'priority_order' => 'nullable|integer|min:1',
            'conditions' => 'nullable|string',
        ];
    }
}
