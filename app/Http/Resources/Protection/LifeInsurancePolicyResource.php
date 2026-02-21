<?php

declare(strict_types=1);

namespace App\Http\Resources\Protection;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LifeInsurancePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'policy_type' => $this->policy_type,
            'provider' => $this->provider,
            'policy_number' => $this->policy_number,
            'sum_assured' => (float) $this->sum_assured,
            'premium_amount' => (float) $this->premium_amount,
            'premium_frequency' => $this->premium_frequency,
            'policy_start_date' => $this->policy_start_date?->format('Y-m-d'),
            'policy_end_date' => $this->policy_end_date?->format('Y-m-d'),
            'policy_term_years' => $this->policy_term_years,
            'in_trust' => (bool) $this->in_trust,
            'is_mortgage_protection' => (bool) $this->is_mortgage_protection,
            'beneficiaries' => $this->beneficiaries,
            'indexation_rate' => $this->indexation_rate ? (float) $this->indexation_rate : null,
            'start_value' => $this->start_value ? (float) $this->start_value : null,
            'decreasing_rate' => $this->decreasing_rate ? (float) $this->decreasing_rate : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
