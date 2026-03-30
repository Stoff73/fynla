<?php

declare(strict_types=1);

namespace App\Models\Investment;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskProfile extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'risk_tolerance',
        'risk_level',
        'capacity_for_loss_percent',
        'time_horizon_years',
        'knowledge_level',
        'attitude_to_volatility',
        'esg_preference',
        'risk_assessed_at',
        'is_self_assessed',
        'factor_breakdown',
    ];

    protected $casts = [
        'capacity_for_loss_percent' => 'float',
        'time_horizon_years' => 'integer',
        'esg_preference' => 'boolean',
        'risk_assessed_at' => 'datetime',
        'is_self_assessed' => 'boolean',
        'factor_breakdown' => 'array',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
