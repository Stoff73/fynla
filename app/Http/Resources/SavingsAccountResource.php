<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SavingsAccount
 */
class SavingsAccountResource extends JsonResource
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
            'provider' => $this->institution,
            'institution' => $this->institution,
            'account_type' => $this->account_type,
            'current_balance' => $this->current_balance,
            'interest_rate' => $this->interest_rate,
            'access_type' => $this->access_type,
            'ownership_type' => $this->ownership_type,
            'ownership_percentage' => $this->ownership_percentage,
            'country' => $this->country,
            'is_emergency_fund' => $this->is_emergency_fund,
            'include_in_retirement' => $this->include_in_retirement,

            // Notice/fixed term fields
            'notice_period_days' => $this->when(
                $this->access_type === 'notice',
                $this->notice_period_days
            ),
            'maturity_date' => $this->when(
                $this->access_type === 'fixed',
                $this->maturity_date?->toDateString()
            ),

            // ISA fields
            'is_isa' => $this->is_isa,
            'isa_type' => $this->when($this->is_isa, $this->isa_type),
            'isa_subscription_year' => $this->when($this->is_isa, $this->isa_subscription_year),
            'isa_subscription_amount' => $this->when($this->is_isa, $this->isa_subscription_amount),

            // Contribution fields
            'regular_contribution_amount' => $this->regular_contribution_amount,
            'contribution_frequency' => $this->contribution_frequency,
            'planned_lump_sum_amount' => $this->planned_lump_sum_amount,
            'planned_lump_sum_date' => $this->planned_lump_sum_date?->toDateString(),

            // Junior ISA beneficiary fields
            'beneficiary_name' => $this->when(
                $this->isa_type === 'junior',
                $this->beneficiary_name
            ),
            'beneficiary_dob' => $this->when(
                $this->isa_type === 'junior',
                $this->beneficiary_dob?->toDateString()
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'joint_owner' => $this->whenLoaded('jointOwner', fn () => new UserResource($this->jointOwner)),
            'beneficiary' => $this->whenLoaded('beneficiary'),

            // Links
            'links' => [
                'self' => '/api/savings/accounts/'.$this->id,
            ],
        ];
    }
}
