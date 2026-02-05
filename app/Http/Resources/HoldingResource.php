<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Investment\Holding
 */
class HoldingResource extends JsonResource
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
            'asset_type' => $this->asset_type,
            'security_name' => $this->security_name,
            'ticker' => $this->ticker,
            'isin' => $this->isin,
            'allocation_percent' => $this->allocation_percent,
            'quantity' => $this->quantity,
            'current_value' => $this->current_value,
            'current_price' => $this->current_price,
            'cost_basis' => $this->cost_basis,
            'purchase_price' => $this->purchase_price,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'dividend_yield' => $this->dividend_yield,
            'ocf_percent' => $this->ocf_percent,

            // Computed fields
            'gain_loss' => $this->when(
                $this->cost_basis !== null && $this->current_value !== null,
                fn () => $this->current_value - $this->cost_basis
            ),
            'gain_loss_percent' => $this->when(
                $this->cost_basis !== null && $this->cost_basis > 0 && $this->current_value !== null,
                fn () => round((($this->current_value - $this->cost_basis) / $this->cost_basis) * 100, 2)
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'holdable' => $this->whenLoaded('holdable', function () {
                return [
                    'id' => $this->holdable->id,
                    'type' => class_basename($this->holdable),
                ];
            }),
        ];
    }
}
