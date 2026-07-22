<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates advisor activity updates.
 *
 * Mirrors StoreClientActivityRequest but deliberately omits the ownership keys
 * (advisor_id, client_id, advisor_client_id): an update must never let an
 * advisor re-point an activity at a different client or advisor. Those columns
 * are set once, server-side, at creation time. Rules are `sometimes` so partial
 * updates (e.g. marking a follow-up complete) are accepted, and every legitimate
 * updatable column is listed so validated() does not silently drop it.
 */
class UpdateClientActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity_type' => 'sometimes|required|in:email,phone,meeting,letter,suitability_report,review,note',
            'summary' => 'sometimes|required|string|max:500',
            'details' => 'nullable|string|max:5000',
            'activity_date' => 'sometimes|required|date',
            'follow_up_date' => 'nullable|date|after_or_equal:activity_date',
            'follow_up_completed' => 'sometimes|boolean',
            'report_type' => 'nullable|string|max:100',
            'report_sent_date' => 'nullable|date',
            'report_acknowledged_date' => 'nullable|date|after_or_equal:report_sent_date',
        ];
    }
}
