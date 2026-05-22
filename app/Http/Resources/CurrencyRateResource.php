<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyRateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'from_ccy' => $this->from_ccy,
            'to_ccy' => $this->to_ccy,
            'rate' => (float) $this->rate,
            'effective_at' => $this->effective_at?->toIso8601String() ?? $this->effective_at,
            'source' => $this->source,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
