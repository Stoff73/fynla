<?php

declare(strict_types=1);

namespace App\Http\Resources\Protection;

use App\Support\PremiumAnnualiser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SicknessIllnessPolicyResource extends JsonResource
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
            // W-0464 / Rule 20 — computed here so `/m` displays it instead of
            // annualising the premium itself in a Vue computed property. One
            // mapping, one answer, every surface.
            'annual_premium' => PremiumAnnualiser::toAnnual(
                (float) $this->premium_amount,
                $this->premium_frequency,
            ),
            'conditions_covered' => $this->conditions_covered,
            'exclusions' => $this->exclusions,
            'policy_start_date' => $this->policy_start_date?->format('Y-m-d'),
            'policy_term_years' => $this->policy_term_years,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
