<?php

declare(strict_types=1);

namespace App\Services\Stores\Recalc;

use App\Constants\TaxDefaults;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Models\User;
use App\Services\TaxConfigService;

class PensionDerivedColumnCalculator
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /**
     * @return array{
     *     current_fund_value_gbp: float,
     *     projected_value_at_retirement_gbp: ?float,
     *     annual_contribution_gbp: ?float,
     *     years_to_drawdown: ?int,
     *     annual_allowance_used_gbp: ?float
     * }
     */
    public function calculateDc(DCPension $pension, User $user): array
    {
        // Pass 3 — all DC fund values stored in GBP; currency conversion for
        // pensions lands in a later sub-project pass. _gbp == raw current_fund_value.
        $currentGbp = (float) $pension->current_fund_value;

        // Annual contribution: prefer monthly_contribution_amount × 12;
        // fall back to (employee% + employer%) × annual_salary.
        $annualContribution = null;
        if ($pension->monthly_contribution_amount !== null && (float) $pension->monthly_contribution_amount > 0) {
            $annualContribution = round((float) $pension->monthly_contribution_amount * 12, 2);
        } elseif ($pension->annual_salary !== null && (float) $pension->annual_salary > 0) {
            $pct = ((float) ($pension->employee_contribution_percent ?? 0))
                + ((float) ($pension->employer_contribution_percent ?? 0));
            if ($pct > 0) {
                $annualContribution = round((float) $pension->annual_salary * $pct / 100, 2);
            }
        }

        // Years to drawdown — retirement_age vs current user age.
        $yearsToDrawdown = null;
        if ($pension->retirement_age !== null && $user->date_of_birth !== null) {
            $age = (int) now()->diffInYears($user->date_of_birth);
            $yearsToDrawdown = max(0, (int) $pension->retirement_age - $age);
        }

        // Projected value at retirement — compounded growth + future contributions.
        $projected = null;
        if ($yearsToDrawdown !== null && $pension->expected_return_percent !== null) {
            $r = (float) $pension->expected_return_percent / 100;
            $futureCurrent = $currentGbp * (1 + $r) ** $yearsToDrawdown;

            $contrib = $annualContribution ?? 0.0;
            $futureContribs = $r > 0
                ? $contrib * (((1 + $r) ** $yearsToDrawdown - 1) / $r)
                : $contrib * $yearsToDrawdown;

            $projected = round($futureCurrent + $futureContribs, 2);
        }

        // Annual allowance used — % of AA consumed by this year's contribution.
        // Degrade gracefully if no active TaxConfiguration (matches Savings recalc).
        $aaUsed = null;
        if ($annualContribution !== null) {
            try {
                $aa = (float) ($this->taxConfig->getPensionAllowances()['annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE);
            } catch (\RuntimeException) {
                $aa = (float) TaxDefaults::PENSION_ANNUAL_ALLOWANCE;
            }
            if ($aa > 0) {
                $aaUsed = round($annualContribution / $aa * 100, 2);
            }
        }

        return [
            'current_fund_value_gbp' => $currentGbp,
            'projected_value_at_retirement_gbp' => $projected,
            'annual_contribution_gbp' => $annualContribution,
            'years_to_drawdown' => $yearsToDrawdown,
            'annual_allowance_used_gbp' => $aaUsed,
        ];
    }

    /**
     * @return array{
     *     projected_annual_pension_at_nra_gbp: ?float,
     *     spouse_pension_projected_gbp: ?float
     * }
     */
    public function calculateDb(DBPension $pension, User $user): array
    {
        $annual = $pension->accrued_annual_pension !== null
            ? round((float) $pension->accrued_annual_pension, 2)
            : null;

        $spouse = null;
        if ($annual !== null && $pension->spouse_pension_percent !== null) {
            $spouse = round($annual * (float) $pension->spouse_pension_percent / 100, 2);
        }

        return [
            'projected_annual_pension_at_nra_gbp' => $annual,
            'spouse_pension_projected_gbp' => $spouse,
        ];
    }

    /**
     * @return array{
     *     state_pension_forecast_annual_gbp: ?float,
     *     ni_completion_pct: ?float,
     *     years_to_state_pension_age: ?int
     * }
     */
    public function calculateState(StatePension $state, User $user): array
    {
        $forecast = $state->state_pension_forecast_annual !== null
            ? round((float) $state->state_pension_forecast_annual, 2)
            : null;

        $completion = null;
        if ($state->ni_years_required !== null
            && (int) $state->ni_years_required > 0
            && $state->ni_years_completed !== null) {
            $completion = round((float) $state->ni_years_completed / (float) $state->ni_years_required * 100, 2);
        }

        $years = null;
        if ($state->state_pension_age !== null && $user->date_of_birth !== null) {
            $age = (int) now()->diffInYears($user->date_of_birth);
            $years = max(0, (int) $state->state_pension_age - $age);
        }

        return [
            'state_pension_forecast_annual_gbp' => $forecast,
            'ni_completion_pct' => $completion,
            'years_to_state_pension_age' => $years,
        ];
    }
}
