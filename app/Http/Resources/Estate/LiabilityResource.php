<?php

declare(strict_types=1);

namespace App\Http\Resources\Estate;

use App\Models\User;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiabilityResource extends JsonResource
{
    use CalculatesOwnershipShare;

    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->id;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'ownership_type' => $this->ownership_type,
            'ownership_percentage' => $this->ownership_percentage,
            'joint_owner_id' => $this->joint_owner_id,
            'joint_owner_deactivated' => $this->joint_owner_id
                ? User::withTrashed()->where('id', $this->joint_owner_id)->whereNotNull('deleted_at')->exists()
                : false,
            'trust_id' => $this->trust_id,
            'liability_type' => $this->liability_type,
            'country' => $this->country,
            'liability_name' => $this->liability_name,
            'current_balance' => $this->current_balance,
            // What THIS viewer owes of it. The list totalled `current_balance`
            // whole, so a shared debt was charged to both owners in full and a
            // third party's share was charged to the user (W-0237). Emitted from
            // the one home rather than recomputed client-side.
            'user_share' => $viewerId !== null
                ? round($this->calculateUserShare($this->resource, $viewerId), 2)
                : null,
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
            'is_primary_owner' => $request->user()?->id === $this->user_id,
        ];
    }
}
