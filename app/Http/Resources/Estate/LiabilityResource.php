<?php

declare(strict_types=1);

namespace App\Http\Resources\Estate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'ownership_type' => $this->ownership_type,
            'joint_owner_id' => $this->joint_owner_id,
            'trust_id' => $this->trust_id,
            'liability_type' => $this->liability_type,
            'country' => $this->country,
            'liability_name' => $this->liability_name,
            'current_balance' => $this->current_balance,
            'monthly_payment' => $this->monthly_payment,
            'interest_rate' => $this->interest_rate,
            'maturity_date' => $this->maturity_date?->toDateString(),
            'secured_against' => $this->secured_against,
            'is_priority_debt' => $this->is_priority_debt,
            'mortgage_type' => $this->mortgage_type,
            'fixed_until' => $this->fixed_until?->toDateString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
