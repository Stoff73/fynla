<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TierConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'tier', 'display_name',
        'price_monthly_pence', 'price_annual_pence', 'revolut_plan_variation_id',
        'capability_matrix', 'count_caps',
        'document_upload_allowance', 'document_storage_gb',
        'fyn_weekly_token_budget', 'fyn_daily_hard_backstop',
        'currency_display_mode', 'snapshot_surfacing_window_days',
        'open_api_affordance', 'is_active', 'updated_by',
    ];

    protected $casts = [
        'capability_matrix' => 'array',
        'count_caps' => 'array',
        'price_monthly_pence' => 'integer',
        'price_annual_pence' => 'integer',
        'document_upload_allowance' => 'integer',
        'document_storage_gb' => 'decimal:2',
        'fyn_weekly_token_budget' => 'integer',
        'fyn_daily_hard_backstop' => 'integer',
        'snapshot_surfacing_window_days' => 'integer',
        'open_api_affordance' => 'boolean',
        'is_active' => 'boolean',
    ];
}
