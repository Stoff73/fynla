<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Household tax-strategy inputs captured during the SaveTax campaign onboarding.
 *
 * One row per user. Different field subsets populated per household_calculation_mode:
 * - dual_earner: spouse_annual_income / spouse_employment_status / spouse_isa_balance / spouse_psa_band /
 *                spouse_unrealised_gains / spouse_annual_dividends / spouse_pension_input_annual
 * - single_earner_couple: spouse_existing_isa_balance / spouse_existing_savings_balance /
 *                         spouse_existing_investment_balance / spouse_existing_dividend_holdings_value /
 *                         spouse_existing_pension_balance
 */
class TaxStrategyHouseholdInput extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        // dual_earner fields
        'spouse_annual_income',
        'spouse_employment_status',
        'spouse_isa_balance',
        'spouse_psa_band',
        'spouse_unrealised_gains',
        'spouse_annual_dividends',
        'spouse_pension_input_annual',
        // single_earner_couple fields
        'spouse_existing_isa_balance',
        'spouse_existing_savings_balance',
        'spouse_existing_investment_balance',
        'spouse_existing_dividend_holdings_value',
        'spouse_existing_pension_balance',
    ];

    protected $casts = [
        'spouse_annual_income' => 'decimal:2',
        'spouse_isa_balance' => 'decimal:2',
        'spouse_unrealised_gains' => 'decimal:2',
        'spouse_annual_dividends' => 'decimal:2',
        'spouse_pension_input_annual' => 'decimal:2',
        'spouse_existing_isa_balance' => 'decimal:2',
        'spouse_existing_savings_balance' => 'decimal:2',
        'spouse_existing_investment_balance' => 'decimal:2',
        'spouse_existing_dividend_holdings_value' => 'decimal:2',
        'spouse_existing_pension_balance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
