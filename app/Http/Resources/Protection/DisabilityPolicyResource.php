<?php

declare(strict_types=1);

namespace App\Http\Resources\Protection;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisabilityPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'provider' => $this->provider,
            'policy_number' => $this->policy_number,
            'benefit_amount' => (float) $this->benefit_amount,
            'benefit_frequency' => $this->benefit_frequency,
            'deferred_period_weeks' => $this->deferred_period_weeks,
            'benefit_period_months' => $this->benefit_period_months,
            'premium_amount' => (float) $this->premium_amount,
            'premium_frequency' => $this->premium_frequency,
            'occupation_class' => $this->occupation_class,
            'coverage_type' => $this->coverage_type,
            'policy_start_date' => $this->policy_start_date?->format('Y-m-d'),
            'policy_term_years' => $this->policy_term_years,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
