<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'sometimes|exists:users,id',
            'activity_type' => 'sometimes|in:email,phone,meeting,letter,suitability_report,review,note',
            'summary' => 'sometimes|string|max:500',
            'details' => 'nullable|string|max:5000',
            'activity_date' => 'sometimes|date',
            'follow_up_date' => 'nullable|date|after_or_equal:activity_date',
            'report_type' => 'nullable|required_if:activity_type,suitability_report|string|max:100',
            'report_sent_date' => 'nullable|date',
            'report_acknowledged_date' => 'nullable|date|after_or_equal:report_sent_date',
        ];
    }
}
