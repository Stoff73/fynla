<?php

declare(strict_types=1);

namespace App\Models\Investment;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holding extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $auditExcludeFields = ['updated_at', 'created_at'];

    protected $fillable = [
        'holdable_id',
        'holdable_type',
        'asset_type',
        'sub_type',
        'allocation_percent',
        'security_name',
        'ticker',
        'isin',
        'quantity',
        'purchase_price',
        'purchase_date',
        'current_price',
        'current_value',
        'cost_basis',
        'dividend_yield',
        'ocf_percent',
    ];

    protected $casts = [
        'allocation_percent' => 'decimal:4',
        'quantity' => 'decimal:4',
        'purchase_price' => 'decimal:2',
        'purchase_date' => 'date',
        'current_price' => 'decimal:2',
        'current_value' => 'decimal:2',
        'cost_basis' => 'decimal:2',
        'dividend_yield' => 'decimal:4',
        'ocf_percent' => 'decimal:4',
    ];

    /**
     * Get the parent holdable model (InvestmentAccount or DCPension)
     */
    public function holdable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Investment account relationship (for backward compatibility)
     *
     * This works with whereHas queries properly by not constraining with where clause.
     * The holdable_type check is done via scope instead.
     */
    public function investmentAccount(): BelongsTo
    {
        return $this->belongsTo(InvestmentAccount::class, 'holdable_id');
    }

    /**
     * Scope for holdings belonging to investment accounts
     */
    public function scopeForInvestmentAccounts(Builder $query): Builder
    {
        return $query->where('holdable_type', InvestmentAccount::class);
    }

    /**
     * Get investment account ID accessor (for backward compatibility)
     * Returns holdable_id when holdable is an InvestmentAccount
     */
    public function getInvestmentAccountIdAttribute(): ?int
    {
        return $this->holdable_type === InvestmentAccount::class
            ? $this->holdable_id
            : null;
    }
}
