<?php

declare(strict_types=1);

namespace App\Services\Stores\Recalc;

use App\Constants\TaxDefaults;
use App\Models\SavingsAccount;
use App\Services\TaxConfigService;

class SavingsAccountDerivedColumnCalculator
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /** @return array{balance_gbp:float, annual_interest_projected_gbp:?float, isa_allowance_used_pct:?float} */
    public function calculate(SavingsAccount $account): array
    {
        // For pass 1, all savings stored in GBP — currency conversion lands in
        // sub-project's later passes (currency-rate ref data). So balance_gbp == current_balance.
        $balanceGbp = (float) $account->current_balance;

        $annualInterestGbp = null;
        // savings_accounts.interest_rate is NOT NULL DEFAULT 0.0000, so a row
        // created without a rate presents as null in-memory (pre-DB-default)
        // but 0.0000 after a ->fresh() reload. Treat null AND 0 identically as
        // "no projected interest" so create() and update() materialise the
        // same value for the same logical state (no £0.00 snapshot noise).
        if ($account->interest_rate !== null && (float) $account->interest_rate > 0) {
            // interest_rate convention is mixed across the codebase: the factory
            // writes decimals (0.04) while seeders + onboarding write percent (4.0).
            // Normalise the SAME way TaxStrategyMath::estimateAnnualInterest does
            // so the derived column matches what read-consumers compute.
            $rate = (float) $account->interest_rate;
            if ($rate > 1) {
                $rate /= 100;
            }
            $annualInterestGbp = round($balanceGbp * $rate, 2);
        }

        $isaAllowanceUsedPct = null;
        if ($account->is_isa && $account->isa_subscription_amount !== null) {
            // ISA-allowance-used % is a supplementary derived metric, not
            // load-bearing for the write itself. This calculator runs on EVERY
            // SavingsStore write — including persona/preview seeding paths that
            // legitimately have no active TaxConfiguration. TaxConfigService
            // throws (not returns null) when no active config exists, so the
            // codebase's standard `?? TaxDefaults::ISA_ALLOWANCE` array-key
            // fallback is insufficient here. Degrade gracefully to the canonical
            // TaxDefaults constant rather than aborting the entire savings
            // persist (Rule #3: still no hardcoded value — TaxDefaults is the
            // codebase's documented ISA-allowance fallback).
            try {
                $isaAllowance = (float) ($this->taxConfig->getISAAllowances()['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE);
            } catch (\RuntimeException) {
                $isaAllowance = (float) TaxDefaults::ISA_ALLOWANCE;
            }
            if ($isaAllowance > 0) {
                $isaAllowanceUsedPct = round((float) $account->isa_subscription_amount / $isaAllowance * 100, 2);
            }
        }

        return [
            'balance_gbp' => $balanceGbp,
            'annual_interest_projected_gbp' => $annualInterestGbp,
            'isa_allowance_used_pct' => $isaAllowanceUsedPct,
        ];
    }
}
