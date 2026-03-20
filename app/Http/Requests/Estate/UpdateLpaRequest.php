<?php

declare(strict_types=1);

namespace App\Http\Requests\Estate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLpaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lpa_type' => 'sometimes|in:property_financial,health_welfare',
            'status' => 'sometimes|in:draft,completed,registered',
            'donor_full_name' => 'sometimes|string|max:255',
            'donor_date_of_birth' => 'sometimes|date|before:today',
            'donor_address_line_1' => 'nullable|string|max:255',
            'donor_address_line_2' => 'nullable|string|max:255',
            'donor_address_city' => 'nullable|string|max:255',
            'donor_address_county' => 'nullable|string|max:255',
            'donor_address_postcode' => 'nullable|string|max:10',

            'attorney_decision_type' => 'nullable|in:jointly,jointly_and_severally,jointly_for_some',
            'jointly_for_some_details' => 'nullable|required_if:attorney_decision_type,jointly_for_some|string',
            'when_attorneys_can_act' => 'nullable|in:while_has_capacity,only_when_lost_capacity',

            'preferences' => 'nullable|string|max:5000',
            'instructions' => 'nullable|string|max:5000',
            'life_sustaining_treatment' => 'nullable|in:can_consent,cannot_consent',

            'certificate_provider_name' => 'nullable|string|max:255',
            'certificate_provider_address' => 'nullable|string|max:1000',
            'certificate_provider_relationship' => 'nullable|string|max:255',
            'certificate_provider_known_years' => 'nullable|integer|min:0|max:100',
            'certificate_provider_professional_details' => 'nullable|string|max:1000',

            'notes' => 'nullable|string|max:5000',

            // Nested attorneys
            'attorneys' => 'sometimes|array',
            'attorneys.*.attorney_type' => 'sometimes|in:primary,replacement',
            'attorneys.*.full_name' => 'sometimes|string|max:255',
            'attorneys.*.date_of_birth' => 'nullable|date|before:today',
            'attorneys.*.address_line_1' => 'nullable|string|max:255',
            'attorneys.*.address_line_2' => 'nullable|string|max:255',
            'attorneys.*.address_city' => 'nullable|string|max:255',
            'attorneys.*.address_county' => 'nullable|string|max:255',
            'attorneys.*.address_postcode' => 'nullable|string|max:10',
            'attorneys.*.relationship_to_donor' => 'nullable|string|max:255',

            // Nested notification persons
            'notification_persons' => 'sometimes|array|max:5',
            'notification_persons.*.full_name' => 'sometimes|string|max:255',
            'notification_persons.*.address_line_1' => 'nullable|string|max:255',
            'notification_persons.*.address_line_2' => 'nullable|string|max:255',
            'notification_persons.*.address_city' => 'nullable|string|max:255',
            'notification_persons.*.address_county' => 'nullable|string|max:255',
            'notification_persons.*.address_postcode' => 'nullable|string|max:10',
        ];
    }
}
