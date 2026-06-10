<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Models\DCPension;
use App\Models\Investment\InvestmentAccount;
use App\Models\PensionInputHistory;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Stores\SavingsStore;
use App\Services\Tax\TaxStrategyMath;

/**
 * Household-aware data availability + marginal rates for the strategy
 * catalogue. availability() keys are the canonical required_data vocabulary
 * seeded on tax_action_definitions — a strategy whose required_data are not
 * all true is "locked": surfaced as an unlock prompt, never silently skipped.
 *
 * The 13 vocabulary keys are fixed to match the seeds:
 *   annual_income, charitable_giving, date_of_birth, dividend_income,
 *   employment_status, gia_holdings, isa_subscriptions_ytd, marital_status,
 *   pension_contributions, pension_input_history, savings_balances,
 *   spouse_income, workplace_pension
 */
final class HouseholdFinancialContext
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly SavingsStore $savingsStore,
    ) {}

    /**
     * Returns a map of every catalogue data-point key to a boolean indicating
     * whether that data is available for the given user.
     *
     * @return array<string, bool>
     */
    public function availability(User $user): array
    {
        $hasDcPension = $this->hasDcPension($user);

        return [
            'annual_income' => $this->hasAnnualIncome($user),
            'charitable_giving' => $user->annual_charitable_donations !== null,
            'date_of_birth' => $user->date_of_birth !== null,
            'dividend_income' => ((float) ($user->annual_dividend_income ?? 0)) > 0,
            'employment_status' => filled($user->employment_status),
            'gia_holdings' => $this->hasGiaHoldings($user),
            'isa_subscriptions_ytd' => $this->hasIsaAccount($user),
            'marital_status' => filled($user->marital_status),
            'pension_contributions' => $hasDcPension,
            'pension_input_history' => PensionInputHistory::where('user_id', $user->id)->exists(),
            'savings_balances' => $this->hasSavingsBalance($user),
            'spouse_income' => $this->spouseIncomeKnown($user),
            'workplace_pension' => $hasDcPension,
        ];
    }

    /**
     * Marginal income-tax rate for the user, derived from their full taxable
     * income (employment + dividends + estimated savings interest).
     */
    public function marginalRateFor(User $user): float
    {
        return $this->math->bandRateFor($user);
    }

    /**
     * Marginal income-tax rate for the user's spouse.
     *
     * Returns 0.0 when the household mode is single_earner_couple (the spouse
     * is a known non-earner and their income is £0 by definition).
     *
     * Returns a float derived from spouse_annual_income for dual_earner mode
     * when that field is populated on the household input row.
     *
     * Returns null when the spouse's income is genuinely unknown (single mode,
     * no household input row, or dual_earner with no income recorded yet).
     */
    public function spouseMarginalRate(User $user): ?float
    {
        $mode = $user->household_calculation_mode;

        if ($mode === 'single_earner_couple') {
            return 0.0;
        }

        if ($mode === 'dual_earner') {
            $input = TaxStrategyHouseholdInput::where('user_id', $user->id)->first();
            if ($input !== null && $input->spouse_annual_income !== null) {
                return $this->math->bandRateFromIncome((float) $input->spouse_annual_income);
            }
        }

        return null;
    }

    // ---------- Private helpers ----------

    private function hasAnnualIncome(User $user): bool
    {
        return ((float) ($user->annual_employment_income ?? 0)) > 0
            || ((float) ($user->annual_self_employment_income ?? 0)) > 0;
    }

    /**
     * Non-ISA investment accounts (GIA, bonds, VCT, EIS, etc.) — mirrors the
     * query used by BedAndIsaStrategy and DividendAllowanceHarvestStrategy.
     */
    private function hasGiaHoldings(User $user): bool
    {
        return InvestmentAccount::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('account_type')->orWhere('account_type', '!=', 'isa');
            })
            ->exists();
    }

    /**
     * Any user-owned ISA savings account — the subscription amount lives per
     * account; an ISA existing means the question is answerable.
     * Uses forUser() (joint-aware) then filters to user_id owned accounts,
     * mirroring IsaTopUpStrategy's pattern.
     */
    private function hasIsaAccount(User $user): bool
    {
        return $this->savingsStore->forUser($user)
            ->where('user_id', $user->id)
            ->where('is_isa', true)
            ->isNotEmpty();
    }

    /**
     * Any user-owned savings account (ISA or non-ISA counts — the balance
     * data itself is available). Same joint-aware forUser() source as
     * IsaTopUpStrategy uses for non-ISA balance.
     */
    private function hasSavingsBalance(User $user): bool
    {
        return $this->savingsStore->forUser($user)
            ->where('user_id', $user->id)
            ->isNotEmpty();
    }

    /**
     * DC pension records exist for this user — covers both workplace_pension
     * and pension_contributions keys (both need a DC pension on record).
     */
    private function hasDcPension(User $user): bool
    {
        return DCPension::where('user_id', $user->id)->exists();
    }

    /**
     * Spouse income is known when:
     * - single_earner_couple mode: spouse income is definitionally £0
     * - dual_earner mode: household input row exists with spouse_annual_income set
     */
    private function spouseIncomeKnown(User $user): bool
    {
        $mode = $user->household_calculation_mode;

        if ($mode === 'single_earner_couple') {
            return true;
        }

        if ($mode === 'dual_earner') {
            return TaxStrategyHouseholdInput::where('user_id', $user->id)
                ->whereNotNull('spouse_annual_income')
                ->exists();
        }

        return false;
    }
}
