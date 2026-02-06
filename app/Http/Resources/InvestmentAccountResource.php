<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Investment\InvestmentAccount
 */
class InvestmentAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_name' => $this->account_name,
            'account_type' => $this->account_type,
            'account_type_other' => $this->when(
                $this->account_type === 'other',
                $this->account_type_other
            ),
            'provider' => $this->provider,
            'platform' => $this->platform,
            'current_value' => $this->current_value,
            'contributions_ytd' => $this->contributions_ytd,
            'monthly_contribution_amount' => $this->monthly_contribution_amount,
            'contribution_frequency' => $this->contribution_frequency,
            'ownership_type' => $this->ownership_type,
            'ownership_percentage' => $this->ownership_percentage,
            'country' => $this->country,
            'tax_year' => $this->tax_year,
            'platform_fee_percent' => $this->platform_fee_percent,
            'advisor_fee_percent' => $this->advisor_fee_percent,
            'risk_preference' => $this->risk_preference,
            'has_custom_risk' => $this->has_custom_risk,
            'include_in_retirement' => $this->include_in_retirement,

            // ISA-specific fields
            'isa_type' => $this->when(
                str_contains($this->account_type ?? '', 'isa'),
                $this->isa_type
            ),
            'isa_subscription_current_year' => $this->when(
                str_contains($this->account_type ?? '', 'isa'),
                $this->isa_subscription_current_year
            ),

            // Employee Share Scheme fields
            'employer_name' => $this->when(
                $this->isEmployeeShareScheme(),
                $this->employer_name
            ),
            'grant_date' => $this->when(
                $this->isEmployeeShareScheme(),
                $this->grant_date?->toDateString()
            ),
            'units_granted' => $this->when(
                $this->isEmployeeShareScheme(),
                $this->units_granted
            ),
            'units_vested' => $this->when(
                $this->isEmployeeShareScheme(),
                $this->units_vested
            ),
            'exercise_price' => $this->when(
                $this->isEmployeeShareScheme(),
                $this->exercise_price
            ),
            'current_share_price' => $this->when(
                $this->isEmployeeShareScheme(),
                $this->current_share_price
            ),
            'intrinsic_value' => $this->when(
                $this->isEmployeeShareScheme(),
                $this->intrinsic_value
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'holdings' => HoldingResource::collection($this->whenLoaded('holdings')),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'joint_owner' => $this->whenLoaded('jointOwner', fn () => new UserResource($this->jointOwner)),

            // Links
            'links' => [
                'self' => '/api/investment/accounts/'.$this->id,
            ],
        ];
    }
}
