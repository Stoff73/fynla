<?php

declare(strict_types=1);

namespace App\Models\Investment;

use App\Models\Estate\Trust;
use App\Models\Household;
use App\Models\User;
use App\Services\Investment\EmployeeSchemeCalculationService;
use App\Traits\Auditable;
use App\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class InvestmentAccount extends Model
{
    use Auditable, HasFactory, HasJointOwnership, SoftDeletes;

    protected $auditExcludeFields = ['updated_at', 'created_at'];

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
        // Business Asset Disposal Relief (BADR) fields
        'badr_eligible',
        'badr_is_employee',
        'badr_trading_company',
        'badr_5_percent_holding',
        'badr_held_2_years',
        'badr_emi_shares',
        'badr_lifetime_used',
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

    protected $hidden = [
        'account_number',
    ];

    protected $casts = [
        'current_value' => 'decimal:2',
        'contributions_ytd' => 'decimal:2',
        'monthly_contribution_amount' => 'decimal:2',
        'planned_lump_sum_amount' => 'decimal:2',
        'planned_lump_sum_date' => 'date',
        'platform_fee_percent' => 'decimal:4',
        'platform_fee_amount' => 'decimal:2',
        'advisor_fee_percent' => 'decimal:4',
        'isa_subscription_current_year' => 'decimal:2',
        'ownership_percentage' => 'decimal:2',
        'has_custom_risk' => 'boolean',
        'rebalance_threshold_percent' => 'decimal:4',
        'include_in_retirement' => 'boolean',
        // Bond-specific casts
        'bond_purchase_date' => 'date',
        'bond_withdrawal_taken' => 'decimal:2',
        // Business Asset Disposal Relief (BADR) casts
        'badr_eligible' => 'boolean',
        'badr_is_employee' => 'boolean',
        'badr_trading_company' => 'boolean',
        'badr_5_percent_holding' => 'boolean',
        'badr_held_2_years' => 'boolean',
        'badr_emi_shares' => 'boolean',
        'badr_lifetime_used' => 'decimal:2',
        // Private Company / Crowdfunding casts
        'investment_date' => 'date',
        'investment_amount' => 'decimal:2',
        'pre_money_valuation' => 'decimal:2',
        'post_money_valuation' => 'decimal:2',
        'price_per_share' => 'decimal:4',
        'number_of_shares' => 'integer',
        'has_voting_rights' => 'boolean',
        'has_dividend_rights' => 'boolean',
        'has_anti_dilution' => 'boolean',
        'interest_rate' => 'decimal:4',
        'maturity_date' => 'date',
        'relief_claimed_date' => 'date',
        'relief_amount_claimed' => 'decimal:2',
        'disposal_restriction_date' => 'date',
        'clawback_risk' => 'boolean',
        'latest_valuation' => 'decimal:2',
        'latest_valuation_date' => 'date',
        'current_ownership_percent' => 'decimal:4',
        'exit_date' => 'date',
        'exit_gross_proceeds' => 'decimal:2',
        'exit_fees' => 'decimal:2',
        'exit_net_proceeds' => 'decimal:2',
        'exit_moic' => 'decimal:4',
        'loss_relief_eligible' => 'boolean',
        'capital_loss_amount' => 'decimal:2',
        'negligible_value_claim' => 'boolean',
        // Employee Share Scheme casts
        'employer_is_listed' => 'boolean',
        'ers_registered' => 'boolean',
        'grant_date' => 'date',
        'units_granted' => 'integer',
        'exercise_price' => 'decimal:4',
        'market_value_at_grant' => 'decimal:4',
        'option_price_paid' => 'decimal:2',
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
        'total_exercise_proceeds' => 'decimal:2',
        'total_exercise_cost' => 'decimal:2',
        'is_readily_convertible_asset' => 'boolean',
        'paye_via_payroll' => 'boolean',
        'income_tax_at_vest_exercise' => 'decimal:2',
        'ni_at_vest_exercise' => 'decimal:2',
        'csop_disqualifying_event' => 'boolean',
        'csop_three_year_date' => 'date',
        'cost_basis_for_cgt' => 'decimal:2',
        'saye_monthly_savings' => 'decimal:2',
        'saye_current_savings_balance' => 'decimal:2',
        'saye_maturity_date' => 'date',
        'saye_option_discount_percent' => 'decimal:4',
        'saye_bonus_amount' => 'decimal:2',
        'post_termination_exercise_days' => 'integer',
        'termination_date' => 'date',
        'exercise_history_json' => 'array',
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
     * Get the joint owner (if account is jointly owned and linked to system user).
     */
    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joint_owner_id');
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
     * Scope to ISA accounts only.
     */
    public function scopeIsa(Builder $query): Builder
    {
        return $query->whereNotNull('isa_type');
    }

    /**
     * Scope to a specific account type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('account_type', $type);
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
        return app(EmployeeSchemeCalculationService::class)->isHoldingPeriodComplete($this);
    }

    /**
     * Calculate paper gain/loss for private investments.
     */
    public function getPaperGainLossAttribute(): ?float
    {
        return app(EmployeeSchemeCalculationService::class)->calculatePaperGainLoss($this);
    }

    /**
     * Calculate paper return percentage for private investments.
     */
    public function getPaperReturnPercentAttribute(): ?float
    {
        return app(EmployeeSchemeCalculationService::class)->calculatePaperReturnPercent($this);
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
        return app(EmployeeSchemeCalculationService::class)->isTaxAdvantagedScheme($this);
    }

    /**
     * Calculate intrinsic value of vested options.
     * Intrinsic value = max(0, current_share_price - exercise_price) * units_vested
     */
    public function getIntrinsicValueAttribute(): ?float
    {
        return app(EmployeeSchemeCalculationService::class)->calculateIntrinsicValue($this);
    }

    /**
     * Calculate total current value of the share scheme.
     * For options: intrinsic value of vested options
     * For RSUs: current share price * vested units
     */
    public function getSchemeCurrentValueAttribute(): ?float
    {
        return app(EmployeeSchemeCalculationService::class)->calculateSchemeCurrentValue($this);
    }

    /**
     * Calculate potential value of unvested units.
     * For options: max(0, current_share_price - exercise_price) * units_unvested
     * For RSUs: current share price * units_unvested
     */
    public function getUnvestedValueAttribute(): ?float
    {
        return app(EmployeeSchemeCalculationService::class)->calculateUnvestedValue($this);
    }

    /**
     * Check if CSOP options are within the tax-advantaged exercise window.
     * CSOP tax advantages require exercise between 3 and 10 years from grant.
     */
    public function isInCsopTaxAdvantageWindow(): bool
    {
        return app(EmployeeSchemeCalculationService::class)->isInCsopTaxAdvantageWindow($this);
    }

    /**
     * Calculate remaining units available (not exercised, forfeited, or expired).
     */
    public function getRemainingUnitsAttribute(): int
    {
        return app(EmployeeSchemeCalculationService::class)->calculateRemainingUnits($this);
    }

    /**
     * Encrypted account number accessor
     */
    protected function accountNumber(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (! $value) {
                    return null;
                }
                try {
                    return Crypt::decryptString($value);
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    return $value;
                }
            },
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }
}
