<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Property
 */
class PropertyResource extends JsonResource
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
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'county' => $this->county,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'property_type' => $this->property_type,
            'tenure_type' => $this->tenure_type,
            'current_value' => $this->current_value,
            'purchase_price' => $this->purchase_price,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'valuation_date' => $this->valuation_date?->toDateString(),
            'ownership_type' => $this->ownership_type,
            'ownership_percentage' => $this->ownership_percentage,
            'joint_ownership_type' => $this->joint_ownership_type,
            'equity' => $this->equity,
            'outstanding_mortgage' => $this->outstanding_mortgage,
            'monthly_rental_income' => $this->when(
                $this->property_type === 'buy_to_let',
                $this->monthly_rental_income
            ),
            'lease_remaining_years' => $this->when(
                $this->tenure_type === 'leasehold',
                $this->lease_remaining_years
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'mortgages' => MortgageResource::collection($this->whenLoaded('mortgages')),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'joint_owner' => $this->whenLoaded('jointOwner', fn () => new UserResource($this->jointOwner)),

            // Links
            'links' => [
                'self' => '/api/properties/'.$this->id,
            ],
        ];
    }
}
