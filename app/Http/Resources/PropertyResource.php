<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Property;
use App\Services\Property\PropertyCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Property
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
            // W-0368. Published here rather than merged in the controller, which
            // hand-builds extra keys on top of this resource in four places
            // (PropertyController :79, :179, :215, :300) — one home, four readers.
            //
            // The edit form NEEDS this back: without it `populateForm()` cannot tell
            // a named spouse from a third party and re-selects "Other", so re-saving
            // would silently flip the answer and change an Inheritance Tax valuation.
            // A field that is stored, correct and cannot reach the user is the
            // W-0351 shape (app/Http/CLAUDE.md, axis 7).
            //
            // NOT `?? false` — NULL is "never asked" and must arrive as null.
            'joint_owner_is_spouse' => $this->joint_owner_is_spouse,
            'joint_owner_deactivated' => $this->relationLoaded('jointOwner') && $this->jointOwner && ! is_null($this->jointOwner->deleted_at),
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
            // W-0533 — the configured leasehold bands reach the user here, so web
            // and `/m` read the same sentences from the same place rather than
            // each deciding what a short lease means (Rules 2, 19, 20). Null for a
            // freehold, and for a leasehold whose remaining term is not recorded:
            // the question does not arise, which is not the same as "no warning".
            'leasehold_warnings' => app(PropertyCalculationService::class)->leaseholdWarnings($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'mortgages' => MortgageResource::collection($this->whenLoaded('mortgages')),
            'user' => $this->whenLoaded('user', fn () => new MinimalUserResource($this->user)),
            'joint_owner' => $this->whenLoaded('jointOwner', fn () => new MinimalUserResource($this->jointOwner)),

            // Links
            'links' => [
                'self' => '/api/properties/'.$this->id,
            ],
        ];
    }
}
