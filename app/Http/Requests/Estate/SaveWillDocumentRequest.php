<?php

declare(strict_types=1);

namespace App\Http\Requests\Estate;

use Illuminate\Foundation\Http\FormRequest;

class SaveWillDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $step = $this->input('step');

        $baseRules = [
            'step' => 'sometimes|string|in:intro,personal,executors,guardians,gifts,residuary,funeral,digital',
        ];

        return match ($step) {
            'intro' => array_merge($baseRules, [
                'will_type' => 'sometimes|in:simple,mirror',
                'domicile_confirmed' => 'sometimes|in:england_wales,scotland,northern_ireland,other',
            ]),
            'personal' => array_merge($baseRules, [
                'testator_full_name' => 'sometimes|string|max:255',
                'testator_address' => 'nullable|string|max:1000',
                'testator_date_of_birth' => 'nullable|date|before:today',
                'testator_occupation' => 'nullable|string|max:255',
            ]),
            'executors' => array_merge($baseRules, [
                'executors' => 'sometimes|array|min:1|max:4',
                'executors.*.name' => 'sometimes|string|max:255',
                'executors.*.address' => 'nullable|string|max:1000',
                'executors.*.relationship' => 'nullable|string|max:255',
                'executors.*.phone' => 'nullable|string|max:50',
            ]),
            'guardians' => array_merge($baseRules, [
                'guardians' => 'nullable|array|max:4',
                'guardians.*.name' => 'sometimes|string|max:255',
                'guardians.*.address' => 'nullable|string|max:1000',
                'guardians.*.relationship' => 'nullable|string|max:255',
            ]),
            'gifts' => array_merge($baseRules, [
                'specific_gifts' => 'nullable|array',
                'specific_gifts.*.beneficiary_name' => 'sometimes|string|max:255',
                'specific_gifts.*.type' => 'sometimes|in:cash,item',
                'specific_gifts.*.amount' => 'nullable|numeric|min:0',
                'specific_gifts.*.description' => 'nullable|string|max:1000',
                'specific_gifts.*.conditions' => 'nullable|string|max:1000',
            ]),
            'residuary' => array_merge($baseRules, [
                'residuary_estate' => 'sometimes|array|min:1',
                'residuary_estate.*.beneficiary_name' => 'sometimes|string|max:255',
                'residuary_estate.*.percentage' => 'sometimes|numeric|min:0|max:100',
                'residuary_estate.*.substitution_beneficiary' => 'nullable|string|max:255',
            ]),
            'funeral' => array_merge($baseRules, [
                'funeral_preference' => 'nullable|in:burial,cremation,no_preference',
                'funeral_wishes_notes' => 'nullable|string|max:2000',
            ]),
            'digital' => array_merge($baseRules, [
                'digital_executor_name' => 'nullable|string|max:255',
                'digital_assets_instructions' => 'nullable|string|max:2000',
            ]),
            default => $baseRules,
        };
    }
}
