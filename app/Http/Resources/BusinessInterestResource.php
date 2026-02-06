<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\BusinessInterest
 */
class BusinessInterestResource extends JsonResource
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
            'business_name' => $this->business_name,
            'business_type' => $this->business_type,
            'company_number' => $this->company_number,
            'ownership_type' => $this->ownership_type,
            'ownership_percentage' => $this->ownership_percentage,
            'current_valuation' => $this->current_valuation,
            'valuation_date' => $this->valuation_date?->toDateString(),
            'valuation_method' => $this->valuation_method,
            'annual_revenue' => $this->annual_revenue,
            'annual_profit' => $this->annual_profit,
            'annual_dividend_income' => $this->annual_dividend_income,
            'description' => $this->description,
            'country' => $this->country,

            // Tax & Compliance
            'vat_registered' => $this->vat_registered,
            'vat_number' => $this->when($this->vat_registered, $this->vat_number),
            'tax_year_end' => $this->tax_year_end?->toDateString(),
            'employee_count' => $this->employee_count,
            'trading_status' => $this->trading_status,

            // Exit Planning / BPR
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'acquisition_cost' => $this->acquisition_cost,
            'bpr_eligible' => $this->bpr_eligible,
            'industry_sector' => $this->industry_sector,

            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'joint_owner' => $this->whenLoaded('jointOwner', fn () => new UserResource($this->jointOwner)),
            'household' => $this->whenLoaded('household'),
            'trust' => $this->whenLoaded('trust'),

            // Links
            'links' => [
                'self' => '/api/business-interests/'.$this->id,
            ],
        ];
    }
}
