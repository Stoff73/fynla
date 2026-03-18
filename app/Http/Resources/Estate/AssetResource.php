<?php

declare(strict_types=1);

namespace App\Http\Resources\Estate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'asset_type' => $this->asset_type,
            'asset_name' => $this->asset_name,
            'current_value' => $this->current_value,
            'liquidity' => $this->liquidity,
            'is_giftable' => $this->is_giftable,
            'not_giftable_reason' => $this->not_giftable_reason,
            'is_main_residence' => $this->is_main_residence,
            'ownership_type' => $this->ownership_type,
            'beneficiary_designation' => $this->beneficiary_designation,
            'is_iht_exempt' => $this->is_iht_exempt,
            'exemption_reason' => $this->exemption_reason,
            'valuation_date' => $this->valuation_date?->toDateString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
