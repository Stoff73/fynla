<?php

declare(strict_types=1);

namespace App\Models\Investment;

use App\Models\Household;
use App\Models\Trust;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InvestmentAccount extends Model
{
    use Auditable, HasFactory, HasJointOwnership;

    protected $fillable = [
        'user_id',
        'account_name',
        'joint_owner_id',
        'household_id',
        'trust_id',
        'ownership_type',
        'ownership_percentage',
        'account_type',
        'account_type_other',
        'country',
        'provider',
        'account_number',
        'platform',
        'current_value',
        'contributions_ytd',
        'monthly_contribution_amount',
        'contribution_frequency',
        'planned_lump_sum_amount',
        'planned_lump_sum_date',
        'tax_year',
        'platform_fee_percent',
        'platform_fee_amount',
        'platform_fee_type',
        'platform_fee_frequency',
        'advisor_fee_percent',
        'isa_type',
        'isa_subscription_current_year',
        'risk_preference',
        'has_custom_risk',
        'rebalance_threshold_percent',
        // Private Company / Crowdfunding fields
        'company_legal_name',
        'company_registration_number',
        'company_country',
        'company_website',
        'company_trading_name',
        'company_sector',
        'crowdfunding_platform',
        'investment_date',
        'investment_amount',
        'investment_currency',
        'funding_round',
        'pre_money_valuation',
        'post_money_valuation',
        'price_per_share',
        'number_of_shares',
        'instrument_type',
        'share_class',
        'has_voting_rights',
        'has_dividend_rights',
        'liquidation_preference',
        'has_anti_dilution',
        'holding_structure',
        'nominee_name',
        'conversion_terms',
        'interest_rate',
        'maturity_date',
        'tax_relief_type',
        'eis3_certificate_number',
        'hmrc_reference',
        'relief_claimed_date',
        'relief_amount_claimed',
        'disposal_restriction_date',
        'clawback_risk',
        'clawback_notes',
        'latest_valuation',
        'latest_valuation_date',
        'current_ownership_percent',
        'company_status',
        'status_notes',
        'exit_type',
        'exit_date',
        'exit_gross_proceeds',
        'exit_fees',
        'exit_net_proceeds',
        'exit_moic',
        'loss_relief_eligible',
        'capital_loss_amount',
        'negligible_value_claim',
    ];

    protected $casts = [
        'current_value' => 'float',
        'contributions_ytd' => 'float',
        'monthly_contribution_amount' => 'float',
        'planned_lump_sum_amount' => 'float',
        'planned_lump_sum_date' => 'date',
        'platform_fee_percent' => 'float',
        'platform_fee_amount' => 'float',
        'advisor_fee_percent' => 'float',
        'isa_subscription_current_year' => 'float',
        'ownership_percentage' => 'decimal:2',
        'has_custom_risk' => 'boolean',
        'rebalance_threshold_percent' => 'float',
        // Private Company / Crowdfunding casts
        'investment_date' => 'date',
        'investment_amount' => 'float',
        'pre_money_valuation' => 'float',
        'post_money_valuation' => 'float',
        'price_per_share' => 'float',
        'number_of_shares' => 'integer',
        'has_voting_rights' => 'boolean',
        'has_dividend_rights' => 'boolean',
        'has_anti_dilution' => 'boolean',
        'interest_rate' => 'float',
        'maturity_date' => 'date',
        'relief_claimed_date' => 'date',
        'relief_amount_claimed' => 'float',
        'disposal_restriction_date' => 'date',
        'clawback_risk' => 'boolean',
        'latest_valuation' => 'float',
        'latest_valuation_date' => 'date',
        'current_ownership_percent' => 'float',
        'exit_date' => 'date',
        'exit_gross_proceeds' => 'float',
        'exit_fees' => 'float',
        'exit_net_proceeds' => 'float',
        'exit_moic' => 'float',
        'loss_relief_eligible' => 'boolean',
        'capital_loss_amount' => 'float',
        'negligible_value_claim' => 'boolean',
    ];

    protected $attributes = [
        'contributions_ytd' => 0,
        'platform_fee_percent' => 0,
        'advisor_fee_percent' => 0,
        'isa_subscription_current_year' => 0,
        'has_custom_risk' => false,
        'rebalance_threshold_percent' => 10.00,
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Holdings relationship (polymorphic)
     */
    public function holdings(): MorphMany
    {
        return $this->morphMany(Holding::class, 'holdable');
    }

    /**
     * Get the household this investment account belongs to (for joint ownership).
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the trust that holds this investment account (if applicable).
     */
    public function trust(): BelongsTo
    {
        return $this->belongsTo(Trust::class);
    }

    /**
     * Check if this is a private company or crowdfunding investment.
     */
    public function isPrivateInvestment(): bool
    {
        return in_array($this->account_type, ['private_company', 'crowdfunding']);
    }

    /**
     * Check if tax relief holding period has been met (3 years for EIS/SEIS).
     */
    public function isHoldingPeriodComplete(): bool
    {
        if (!$this->disposal_restriction_date) {
            return false;
        }

        return now()->gte($this->disposal_restriction_date);
    }

    /**
     * Calculate paper gain/loss for private investments.
     */
    public function getPaperGainLossAttribute(): ?float
    {
        if (!$this->investment_amount || !$this->latest_valuation) {
            return null;
        }

        return $this->latest_valuation - $this->investment_amount;
    }

    /**
     * Calculate paper return percentage for private investments.
     */
    public function getPaperReturnPercentAttribute(): ?float
    {
        if (!$this->investment_amount || $this->investment_amount == 0) {
            return null;
        }

        return (($this->latest_valuation - $this->investment_amount) / $this->investment_amount) * 100;
    }
}
