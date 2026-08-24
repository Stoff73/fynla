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
            // W-0394. These two were absent, so `validated()` dropped them and
            // every bequest was stored with the schema default 'individual' —
            // including both of the peak_earners household's charitable
            // legacies. Bequest::isCharitable() re-derives the answer from the
            // beneficiary name on read, which hid it; a charity the name list
            // does not recognise had no such second chance and was worth
            // nothing to the charitable total that decides the reduced
            // Inheritance Tax rate.
            'beneficiary_type' => 'sometimes|in:individual,charity,trust,organization',
            'charity_registration_number' => 'nullable|string|max:50',
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
