<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Estate\Trust;
use App\Traits\Auditable;
use App\Traits\HasJointOwnership;
use Carbon\Carbon;
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
        'joint_owner_name',
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

    /**
     * Companies House filing dates are deliberately absent from $fillable —
     * they are read from the register via CompaniesHouseService::sync(), never
     * accepted from a request, so a crafted payload cannot fake a deadline.
     */
    protected $casts = [
        'valuation_date' => 'date',
        'accounts_due_on' => 'date',
        'confirmation_statement_due_on' => 'date',
        'companies_house_synced_at' => 'datetime',
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
     * The soonest Companies House filing deadline, or null when this company
     * has never been synced against the register.
     *
     * One home for the "which filing is next, and how close is it" question so
     * that the web card, the /m list and the API resource all answer it
     * identically instead of each doing its own date arithmetic.
     *
     * days_until is negative once the deadline has passed.
     *
     * @return array{type: string, due_on: string, days_until: int}|null
     */
    public function nextFiling(): ?array
    {
        $due = collect([
            'accounts' => $this->accounts_due_on,
            'confirmation' => $this->confirmation_statement_due_on,
        ])->filter()->sort();

        if ($due->isEmpty()) {
            return null;
        }

        return [
            'type' => (string) $due->keys()->first(),
            'due_on' => $due->first()->toDateString(),
            'days_until' => (int) Carbon::today()->diffInDays($due->first()->copy()->startOfDay(), false),
        ];
    }

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
        return $this->belongsTo(User::class, 'joint_owner_id')->withTrashed();
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
