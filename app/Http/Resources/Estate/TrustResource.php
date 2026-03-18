<?php

declare(strict_types=1);

namespace App\Http\Resources\Estate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrustResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'household_id' => $this->household_id,
            'trust_name' => $this->trust_name,
            'trust_type' => $this->trust_type,
            'other_type_description' => $this->other_type_description,
            'country' => $this->country,
            'trust_creation_date' => $this->trust_creation_date?->toDateString(),
            'initial_value' => $this->initial_value,
            'current_value' => $this->current_value,
            'discount_amount' => $this->discount_amount,
            'retained_income_annual' => $this->retained_income_annual,
            'loan_amount' => $this->loan_amount,
            'loan_interest_bearing' => $this->loan_interest_bearing,
            'loan_interest_rate' => $this->loan_interest_rate,
            'sum_assured' => $this->sum_assured,
            'annual_premium' => $this->annual_premium,
            'is_relevant_property_trust' => $this->is_relevant_property_trust,
            'last_periodic_charge_date' => $this->last_periodic_charge_date?->toDateString(),
            'last_periodic_charge_amount' => $this->last_periodic_charge_amount,
            'last_valuation_date' => $this->last_valuation_date?->toDateString(),
            'next_tax_return_due' => $this->next_tax_return_due?->toDateString(),
            'total_asset_value' => $this->total_asset_value,
            'beneficiaries' => $this->beneficiaries,
            'trustees' => $this->trustees,
            'settlor' => $this->settlor,
            'purpose' => $this->purpose,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
