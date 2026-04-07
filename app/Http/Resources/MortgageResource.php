<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Mortgage
 */
class MortgageResource extends JsonResource
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
            'lender' => $this->lender_name,
            'lender_name' => $this->lender_name,
            'mortgage_type' => $this->mortgage_type,
            'original_amount' => $this->original_loan_amount,
            'original_loan_amount' => $this->original_loan_amount,
            'current_balance' => $this->outstanding_balance,
            'outstanding_balance' => $this->outstanding_balance,
            'interest_rate' => $this->interest_rate,
            'rate_type' => $this->rate_type,
            'monthly_payment' => $this->monthly_payment,
            'monthly_interest_portion' => $this->monthly_interest_portion,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->maturity_date?->toDateString(),
            'maturity_date' => $this->maturity_date?->toDateString(),
            'remaining_term_months' => $this->remaining_term_months,
            'ownership_type' => $this->ownership_type,
            'ownership_percentage' => $this->ownership_percentage,
            'country' => $this->country,

            // Mixed mortgage fields
            'repayment_percentage' => $this->when(
                $this->mortgage_type === 'mixed',
                $this->repayment_percentage
            ),
            'interest_only_percentage' => $this->when(
                $this->mortgage_type === 'mixed',
                $this->interest_only_percentage
            ),

            // Rate information
            'rate_fix_end_date' => $this->when(
                $this->rate_type === 'fixed',
                $this->rate_fix_end_date?->toDateString()
            ),
            'fixed_interest_rate' => $this->when(
                in_array($this->rate_type, ['fixed', 'mixed']),
                $this->fixed_interest_rate
            ),
            'variable_interest_rate' => $this->when(
                in_array($this->rate_type, ['variable', 'mixed']),
                $this->variable_interest_rate
            ),

            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Computed ownership fields (set via additional data)
            'user_share' => $this->when(isset($this->additional['user_share']), fn () => $this->additional['user_share']),
            'full_balance' => $this->when(isset($this->additional['full_balance']), fn () => $this->additional['full_balance']),
            'is_primary_owner' => $this->when(isset($this->additional['is_primary_owner']), fn () => $this->additional['is_primary_owner']),

            // Relationships
            'property' => $this->whenLoaded('property', fn () => new PropertyResource($this->property)),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),

            // Links
            'links' => [
                'self' => '/api/mortgages/'.$this->id,
            ],
        ];
    }
}
