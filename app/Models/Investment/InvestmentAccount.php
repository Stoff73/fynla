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
        'include_in_retirement',
        // Bond-specific fields (onshore/offshore bonds)
        'bond_purchase_date',
        'bond_withdrawal_taken',
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
        // Employee Share Scheme fields
        // Group 1: Employer Details
        'employer_name',
        'employer_registration',
        'employer_ticker',
        'employer_is_listed',
        'parent_company_name',
        'parent_company_country',
        'ers_scheme_reference',
        'ers_registered',
        // Group 2: Grant Details
        'grant_date',
        'grant_reference',
        'units_granted',
        'exercise_price',
        'market_value_at_grant',
        'share_class_scheme',
        'grant_currency',
        'option_price_paid',
        'scheme_start_date',
        'scheme_duration_months',
        // Group 3: Vesting Schedule
        'vesting_type',
        'cliff_date',
        'cliff_percentage',
        'vesting_period_months',
        'vesting_frequency_months',
        'has_performance_conditions',
        'performance_conditions_description',
        'performance_period_end',
        'performance_vesting_min_percent',
        'performance_vesting_max_percent',
        'full_vest_date',
        'accelerated_vesting_allowed',
        // Group 4: Current Status
        'units_vested',
        'units_unvested',
        'units_exercised',
        'units_forfeited',
        'units_expired',
        'scheme_status',
        'current_share_price',
        'share_price_date',
        // Group 5: Exercise & Expiry
        'exercise_window_start',
        'exercise_window_end',
        'last_exercise_date',
        'total_exercise_proceeds',
        'total_exercise_cost',
        'exercise_history_json',
        // Group 6: Tax Treatment
        'tax_treatment',
        'is_readily_convertible_asset',
        'paye_via_payroll',
        'income_tax_at_vest_exercise',
        'ni_at_vest_exercise',
        'csop_disqualifying_event',
        'csop_three_year_date',
        'cost_basis_for_cgt',
        // Group 7: SAYE-Specific
        'saye_monthly_savings',
        'saye_current_savings_balance',
        'saye_maturity_date',
        'saye_option_discount_percent',
        'saye_bonus_amount',
        // Group 8: Leaver Terms
        'leaver_category',
        'post_termination_exercise_days',
        'termination_date',
        'leaver_notes',
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
        'include_in_retirement' => 'boolean',
        // Bond-specific casts
        'bond_purchase_date' => 'date',
        'bond_withdrawal_taken' => 'float',
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
        // Employee Share Scheme casts
        'employer_is_listed' => 'boolean',
        'ers_registered' => 'boolean',
        'grant_date' => 'date',
        'units_granted' => 'integer',
        'exercise_price' => 'decimal:4',
        'market_value_at_grant' => 'decimal:4',
        'option_price_paid' => 'float',
        'scheme_start_date' => 'date',
        'scheme_duration_months' => 'integer',
        'cliff_date' => 'date',
        'cliff_percentage' => 'integer',
        'vesting_period_months' => 'integer',
        'vesting_frequency_months' => 'integer',
        'has_performance_conditions' => 'boolean',
        'performance_period_end' => 'date',
        'performance_vesting_min_percent' => 'integer',
        'performance_vesting_max_percent' => 'integer',
        'full_vest_date' => 'date',
        'accelerated_vesting_allowed' => 'boolean',
        'units_vested' => 'integer',
        'units_unvested' => 'integer',
        'units_exercised' => 'integer',
        'units_forfeited' => 'integer',
        'units_expired' => 'integer',
        'current_share_price' => 'decimal:4',
        'share_price_date' => 'date',
        'exercise_window_start' => 'date',
        'exercise_window_end' => 'date',
        'last_exercise_date' => 'date',
        'total_exercise_proceeds' => 'float',
        'total_exercise_cost' => 'float',
        'is_readily_convertible_asset' => 'boolean',
        'paye_via_payroll' => 'boolean',
        'income_tax_at_vest_exercise' => 'float',
        'ni_at_vest_exercise' => 'float',
        'csop_disqualifying_event' => 'boolean',
        'csop_three_year_date' => 'date',
        'cost_basis_for_cgt' => 'float',
        'saye_monthly_savings' => 'float',
        'saye_current_savings_balance' => 'float',
        'saye_maturity_date' => 'date',
        'saye_option_discount_percent' => 'float',
        'saye_bonus_amount' => 'float',
        'post_termination_exercise_days' => 'integer',
        'termination_date' => 'date',
    ];

    protected $attributes = [
        'contributions_ytd' => 0,
        'platform_fee_percent' => 0,
        'advisor_fee_percent' => 0,
        'isa_subscription_current_year' => 0,
        'has_custom_risk' => false,
        'rebalance_threshold_percent' => 10.00,
        'include_in_retirement' => false,
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
        if (! $this->disposal_restriction_date) {
            return false;
        }

        return now()->gte($this->disposal_restriction_date);
    }

    /**
     * Calculate paper gain/loss for private investments.
     */
    public function getPaperGainLossAttribute(): ?float
    {
        if (! $this->investment_amount || ! $this->latest_valuation) {
            return null;
        }

        return $this->latest_valuation - $this->investment_amount;
    }

    /**
     * Calculate paper return percentage for private investments.
     */
    public function getPaperReturnPercentAttribute(): ?float
    {
        if (! $this->investment_amount || $this->investment_amount == 0) {
            return null;
        }

        return (($this->latest_valuation - $this->investment_amount) / $this->investment_amount) * 100;
    }

    /**
     * Check if this is an employee share scheme account.
     */
    public function isEmployeeShareScheme(): bool
    {
        return in_array($this->account_type, ['saye', 'csop', 'emi', 'unapproved_options', 'rsu']);
    }

    /**
     * Check if this is an options-based scheme (vs RSUs which vest directly).
     */
    public function isOptionsScheme(): bool
    {
        return in_array($this->account_type, ['saye', 'csop', 'emi', 'unapproved_options']);
    }

    /**
     * Check if this is a tax-advantaged employee share scheme.
     * SAYE, CSOP, and EMI all have tax advantages when rules are followed.
     */
    public function isTaxAdvantagedScheme(): bool
    {
        return in_array($this->account_type, ['saye', 'csop', 'emi']);
    }

    /**
     * Calculate intrinsic value of vested options.
     * Intrinsic value = max(0, current_share_price - exercise_price) * units_vested
     */
    public function getIntrinsicValueAttribute(): ?float
    {
        if (! $this->isOptionsScheme() || ! $this->current_share_price || ! $this->exercise_price) {
            return null;
        }

        $spreadPerShare = max(0, (float) $this->current_share_price - (float) $this->exercise_price);
        $vestedUnits = (int) ($this->units_vested ?? 0);

        return $spreadPerShare * $vestedUnits;
    }

    /**
     * Calculate total current value of the share scheme.
     * For options: intrinsic value of vested options
     * For RSUs: current share price * vested units
     */
    public function getSchemeCurrentValueAttribute(): ?float
    {
        if (! $this->isEmployeeShareScheme() || ! $this->current_share_price) {
            return null;
        }

        $vestedUnits = (int) ($this->units_vested ?? 0);

        if ($this->isOptionsScheme()) {
            // Options: intrinsic value (gain on exercise)
            return $this->intrinsic_value;
        }

        // RSUs: direct share value
        return (float) $this->current_share_price * $vestedUnits;
    }

    /**
     * Calculate potential value of unvested units.
     * For options: max(0, current_share_price - exercise_price) * units_unvested
     * For RSUs: current share price * units_unvested
     */
    public function getUnvestedValueAttribute(): ?float
    {
        if (! $this->isEmployeeShareScheme() || ! $this->current_share_price) {
            return null;
        }

        $unvestedUnits = (int) ($this->units_unvested ?? 0);

        if ($this->isOptionsScheme()) {
            $spreadPerShare = max(0, (float) $this->current_share_price - (float) $this->exercise_price);

            return $spreadPerShare * $unvestedUnits;
        }

        // RSUs: direct share value
        return (float) $this->current_share_price * $unvestedUnits;
    }

    /**
     * Check if CSOP options are within the tax-advantaged exercise window.
     * CSOP tax advantages require exercise between 3 and 10 years from grant.
     */
    public function isInCsopTaxAdvantageWindow(): bool
    {
        if ($this->account_type !== 'csop' || ! $this->grant_date) {
            return false;
        }

        $grantDate = $this->grant_date instanceof \Carbon\Carbon
            ? $this->grant_date
            : \Carbon\Carbon::parse($this->grant_date);

        $now = now();
        $threeYearsFromGrant = $grantDate->copy()->addYears(3);
        $tenYearsFromGrant = $grantDate->copy()->addYears(10);

        return $now->gte($threeYearsFromGrant) && $now->lte($tenYearsFromGrant);
    }

    /**
     * Calculate remaining units available (not exercised, forfeited, or expired).
     */
    public function getRemainingUnitsAttribute(): int
    {
        $granted = (int) ($this->units_granted ?? 0);
        $exercised = (int) ($this->units_exercised ?? 0);
        $forfeited = (int) ($this->units_forfeited ?? 0);
        $expired = (int) ($this->units_expired ?? 0);

        return max(0, $granted - $exercised - $forfeited - $expired);
    }
}
