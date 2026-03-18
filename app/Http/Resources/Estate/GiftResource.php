<?php

declare(strict_types=1);

namespace App\Http\Resources\Estate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'gift_date' => $this->gift_date?->toDateString(),
            'recipient' => $this->recipient,
            'gift_type' => $this->gift_type,
            'gift_value' => $this->gift_value,
            'status' => $this->status,
            'taper_relief_applicable' => $this->taper_relief_applicable,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
