<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SavingsMarketRateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'rate_key' => $this->rate_key,
            'label' => $this->label,
            'rate' => (float) $this->rate,
            'tax_year' => $this->tax_year,
            'effective_from' => $this->effective_from?->toDateString() ?? $this->effective_from,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
