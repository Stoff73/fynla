<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TierConfigurationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'tier' => $this->tier,
            'display_name' => $this->display_name,
            'price_monthly_pence' => $this->price_monthly_pence,
            'price_annual_pence' => $this->price_annual_pence,
            'capability_matrix' => $this->capability_matrix,
            'count_caps' => $this->count_caps,
            'document_upload_allowance' => $this->document_upload_allowance,
            'document_storage_gb' => $this->document_storage_gb,
            'fyn_weekly_token_budget' => $this->fyn_weekly_token_budget,
            'fyn_daily_hard_backstop' => $this->fyn_daily_hard_backstop,
            'currency_display_mode' => $this->currency_display_mode,
            'snapshot_surfacing_window_days' => $this->snapshot_surfacing_window_days,
            'open_api_affordance' => $this->open_api_affordance,
            'is_active' => $this->is_active,
        ];
    }
}
