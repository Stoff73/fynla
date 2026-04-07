<?php

declare(strict_types=1);

namespace App\Models\Investment;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RebalancingAction extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'holding_id',
        'investment_account_id',
        'action_type',
        'security_name',
        'ticker',
        'isin',
        'shares_to_trade',
        'trade_value',
        'current_price',
        'current_holding',
        'target_value',
        'target_weight',
        'priority',
        'rationale',
        'cgt_cost_basis',
        'cgt_gain_or_loss',
        'cgt_liability',
        'status',
        'executed_at',
        'executed_price',
        'executed_shares',
        'notes',
    ];

    protected $casts = [
        'shares_to_trade' => 'float',
        'trade_value' => 'float',
        'current_price' => 'float',
        'current_holding' => 'float',
        'target_value' => 'float',
        'target_weight' => 'float',
        'priority' => 'integer',
        'cgt_cost_basis' => 'float',
        'cgt_gain_or_loss' => 'float',
        'cgt_liability' => 'float',
        'executed_at' => 'datetime',
        'executed_price' => 'float',
        'executed_shares' => 'float',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Holding relationship
     */
    public function holding(): BelongsTo
    {
        return $this->belongsTo(Holding::class);
    }

    /**
     * Investment account relationship
     */
    public function investmentAccount(): BelongsTo
    {
        return $this->belongsTo(InvestmentAccount::class);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by action type
     */
    public function scopeActionType(Builder $query, string $type): Builder
    {
        return $query->where('action_type', $type);
    }

    /**
     * Scope: Pending actions
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Executed actions
     */
    public function scopeExecuted(Builder $query): Builder
    {
        return $query->where('status', 'executed');
    }
}
