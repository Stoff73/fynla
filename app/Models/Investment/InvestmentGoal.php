<?php

declare(strict_types=1);

namespace App\Models\Investment;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentGoal extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'goal_name',
        'goal_type',
        'target_amount',
        'target_date',
        'priority',
        'is_essential',
        'linked_account_ids',
    ];

    protected $casts = [
        'target_amount' => 'float',
        'target_date' => 'date',
        'is_essential' => 'boolean',
        'linked_account_ids' => 'array',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
