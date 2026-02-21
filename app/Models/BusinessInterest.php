<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Estate\Trust;
use App\Traits\Auditable;
use App\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessInterest extends Model
{
    use Auditable, HasFactory, HasJointOwnership, SoftDeletes;

    protected $fillable = [
        'user_id',
        'joint_owner_id',
        'household_id',
        'trust_id',
        'business_name',
        'company_number',
        'business_type',
        'ownership_type',
        'ownership_percentage',
        'country',
        'current_valuation',
        'valuation_date',
        'valuation_method',
        'annual_revenue',
        'annual_profit',
        'annual_dividend_income',
        'description',
        'notes',
        // Tax & Compliance fields
        'vat_registered',
        'vat_number',
        'utr_number',
        'tax_year_end',
        'employee_count',
        'paye_reference',
        'trading_status',
        // Exit Planning / BADR fields
        'acquisition_date',
        'acquisition_cost',
        'bpr_eligible',
        'industry_sector',
    ];

    protected $casts = [
        'valuation_date' => 'date',
        'current_valuation' => 'decimal:2',
        'ownership_percentage' => 'decimal:2',
        'annual_revenue' => 'decimal:2',
        'annual_profit' => 'decimal:2',
        'annual_dividend_income' => 'decimal:2',
        // New tax/compliance casts
        'vat_registered' => 'boolean',
        'tax_year_end' => 'date',
        'employee_count' => 'integer',
        'acquisition_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'bpr_eligible' => 'boolean',
    ];

    /**
     * Get the user that owns this business interest.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the joint owner of this business interest.
     */
    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joint_owner_id');
    }

    /**
     * Get the household this business interest belongs to (for joint ownership).
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the trust that holds this business interest (if applicable).
     */
    public function trust(): BelongsTo
    {
        return $this->belongsTo(Trust::class);
    }
}
