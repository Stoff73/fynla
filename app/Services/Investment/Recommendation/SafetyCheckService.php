<?php

declare(strict_types=1);

namespace App\Services\Investment\Recommendation;

use App\Services\TaxConfigService;

/**
 * Safety checks that can reduce the surplus available to the waterfall.
 *
 * CRITICAL: This service does NOT produce standalone emergency fund recommendations.
 * Emergency fund action cards are owned by the Savings engine (SavingsActionDefinitionService).
 * SafetyCheckService only:
 *  1. Reduces the remaining_surplus figure passed to the waterfall
 *  2. Adds context notes to waterfall recommendations
 *  3. Surfaces the employer match recommendation
 */
class SafetyCheckService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Run all safety checks against the user context.
     *
     * @param  array  $context  User context from UserContextBuilder
     * @return array{
     *     adjusted_surplus: float,
     *     original_surplus: float,
     *     checks: array,
     *     context_notes: string[],
     *     employer_match: array|null,
     *     can_invest: bool
     * }
     */
    public function check(array $context): array
    {
        $monthlyDisposable = $context['financial']['monthly_disposable'] ?? 0;
        $annualDisposable = $context['financial']['disposable_income'] ?? 0;
        $surplus = $annualDisposable;
        $originalSurplus = $surplus;

        $safetyConfig = $this->taxConfig->get('investment.safety', []);
        $criticalDebtRate = (float) ($safetyConfig['critical_debt_rate'] ?? 0.15);
        $mediumDebtRateLow = (float) ($safetyConfig['medium_debt_rate_low'] ?? 0.05);
        $mediumDebtRateHigh = (float) ($safetyConfig['medium_debt_rate_high'] ?? 0.15);

        $checks = [];
        $contextNotes = [];
        $canInvest = true;

        // ── Check 1: Critical debt (>15% APR) ──
        $highInterestBalance = $context['debt']['high_interest']['total_balance'] ?? 0;
        $highInterestMonthly = $context['debt']['high_interest']['total_monthly_payment'] ?? 0;
        if ($highInterestBalance > 0) {
            $annualDebtPayment = $highInterestMonthly * 12;
            $surplus = max(0, $surplus - $annualDebtPayment);
            $checks[] = [
                'check' => 'critical_debt',
                'triggered' => true,
                'impact' => 'surplus_reduced',
                'reduction' => round($annualDebtPayment, 2),
                'note' => sprintf(
                    'High-interest debt of %s at rates above %s%% — surplus reduced by annual payments of %s.',
                    number_format($highInterestBalance, 0, '.', ','),
                    number_format($criticalDebtRate * 100, 0),
                    number_format($annualDebtPayment, 0, '.', ',')
                ),
            ];
            $contextNotes[] = sprintf(
                'Surplus limited because %s of high-interest debt requires %s per year in repayments.',
                number_format($highInterestBalance, 0, '.', ','),
                number_format($annualDebtPayment, 0, '.', ',')
            );

            if ($surplus <= 0) {
                $canInvest = false;
            }
        } else {
            $checks[] = [
                'check' => 'critical_debt',
                'triggered' => false,
                'impact' => 'none',
                'reduction' => 0,
                'note' => 'No high-interest debt detected.',
            ];
        }

        // ── Check 2: Emergency fund shortfall ──
        $emergencyRunway = $context['emergency_fund']['runway_months'] ?? 0;
        $emergencyShortfall = $context['emergency_fund']['shortfall'] ?? 0;
        if ($emergencyRunway < 1) {
            // Critical — no emergency fund at all
            $surplus = 0;
            $canInvest = false;
            $checks[] = [
                'check' => 'emergency_fund_critical',
                'triggered' => true,
                'impact' => 'surplus_zero',
                'reduction' => $surplus,
                'note' => 'Emergency fund covers less than 1 month of expenditure — surplus set to zero.',
            ];
            $contextNotes[] = 'Emergency fund is critically low — all surplus directed to reserves.';
        } elseif ($emergencyRunway < 3) {
            // Low — cap surplus at 50%
            $reduction = $surplus * 0.5;
            $surplus = max(0, $surplus - $reduction);
            $checks[] = [
                'check' => 'emergency_fund_low',
                'triggered' => true,
                'impact' => 'surplus_halved',
                'reduction' => round($reduction, 2),
                'note' => sprintf('Emergency fund covers only %.0f months — surplus reduced by 50%%.', $emergencyRunway),
            ];
            $contextNotes[] = sprintf('Emergency fund covers only %.0f months of expenditure — surplus reduced.', $emergencyRunway);
        } elseif ($emergencyShortfall > 0) {
            // Building towards target — no cap, but note it
            $checks[] = [
                'check' => 'emergency_fund_building',
                'triggered' => true,
                'impact' => 'note_only',
                'reduction' => 0,
                'note' => sprintf('Emergency fund covers %.0f months but shortfall of %s to reach 6-month target.', $emergencyRunway, number_format($emergencyShortfall, 0, '.', ',')),
            ];
            $contextNotes[] = sprintf('Emergency fund shortfall of %s to 6-month target — building in parallel with investments.', number_format($emergencyShortfall, 0, '.', ','));
        } else {
            $checks[] = [
                'check' => 'emergency_fund',
                'triggered' => false,
                'impact' => 'none',
                'reduction' => 0,
                'note' => sprintf('Emergency fund covers %.0f months — adequate.', $emergencyRunway),
            ];
        }

        // ── Check 3: Employer pension match ──
        $employerMatch = $this->checkEmployerMatch($context);
        if ($employerMatch !== null) {
            $checks[] = [
                'check' => 'employer_match',
                'triggered' => true,
                'impact' => 'note_only',
                'reduction' => 0,
                'note' => $employerMatch['note'],
            ];
            // Employer match does NOT reduce surplus — it is noted for the waterfall
        } else {
            $checks[] = [
                'check' => 'employer_match',
                'triggered' => false,
                'impact' => 'none',
                'reduction' => 0,
                'note' => 'No employer pension match detected or already maximised.',
            ];
        }

        // ── Check 4: Income protection gap ──
        $hasProtection = ! empty($context['personal']['employment_status'])
            && $context['personal']['employment_status'] === 'employed';
        $checks[] = [
            'check' => 'income_protection',
            'triggered' => false,
            'impact' => 'note_only',
            'reduction' => 0,
            'note' => 'Income protection check — review in Protection module.',
        ];

        // ── Check 5: Insufficient disposable income ──
        if ($originalSurplus <= 0) {
            $canInvest = false;
            $checks[] = [
                'check' => 'insufficient_income',
                'triggered' => true,
                'impact' => 'cannot_invest',
                'reduction' => 0,
                'note' => 'Disposable income is zero or negative — no surplus available for investment.',
            ];
            $contextNotes[] = 'Disposable income is not sufficient for new investment contributions.';
        } else {
            $checks[] = [
                'check' => 'insufficient_income',
                'triggered' => false,
                'impact' => 'none',
                'reduction' => 0,
                'note' => sprintf('Disposable income of %s per year available.', number_format($originalSurplus, 0, '.', ',')),
            ];
        }

        // ── Check 6: Near-term commitments ──
        $nearTermExpenses = $this->calculateNearTermCommitments($context);
        if ($nearTermExpenses > 0) {
            $annualReserve = $nearTermExpenses; // Spread across remaining months
            $surplus = max(0, $surplus - $annualReserve);
            $checks[] = [
                'check' => 'near_term_commitments',
                'triggered' => true,
                'impact' => 'surplus_reduced',
                'reduction' => round($annualReserve, 2),
                'note' => sprintf('Near-term commitments of %s — surplus reduced to reserve funds.', number_format($nearTermExpenses, 0, '.', ',')),
            ];
            $contextNotes[] = sprintf('Near-term expenses of %s reserved from investable surplus.', number_format($nearTermExpenses, 0, '.', ','));
        } else {
            $checks[] = [
                'check' => 'near_term_commitments',
                'triggered' => false,
                'impact' => 'none',
                'reduction' => 0,
                'note' => 'No significant near-term commitments detected.',
            ];
        }

        // ── Check 7: Medium-interest debt ──
        $mediumInterestBalance = $context['debt']['medium_interest']['total_balance'] ?? 0;
        $mediumInterestMonthly = $context['debt']['medium_interest']['total_monthly_payment'] ?? 0;
        if ($mediumInterestBalance > 0) {
            // Reduce surplus by 50% of medium-interest debt payments
            $halfAnnualPayment = ($mediumInterestMonthly * 12) * 0.5;
            $surplus = max(0, $surplus - $halfAnnualPayment);
            $checks[] = [
                'check' => 'medium_interest_debt',
                'triggered' => true,
                'impact' => 'surplus_reduced',
                'reduction' => round($halfAnnualPayment, 2),
                'note' => sprintf(
                    'Medium-interest debt of %s — surplus partially reduced to accelerate repayment.',
                    number_format($mediumInterestBalance, 0, '.', ',')
                ),
            ];
            $contextNotes[] = sprintf(
                'Medium-interest debt of %s — half of annual payments reserved from surplus.',
                number_format($mediumInterestBalance, 0, '.', ',')
            );
        } else {
            $checks[] = [
                'check' => 'medium_interest_debt',
                'triggered' => false,
                'impact' => 'none',
                'reduction' => 0,
                'note' => 'No medium-interest debt detected.',
            ];
        }

        return [
            'adjusted_surplus' => round(max(0, $surplus), 2),
            'original_surplus' => round($originalSurplus, 2),
            'checks' => $checks,
            'context_notes' => $contextNotes,
            'employer_match' => $employerMatch,
            'can_invest' => $canInvest,
        ];
    }

    /**
     * Check if the user is not maximising their employer pension match.
     */
    private function checkEmployerMatch(array $context): ?array
    {
        $dcPensions = $context['pensions']['dc_pensions'] ?? [];

        foreach ($dcPensions as $pension) {
            $employerPercent = (float) ($pension['employer_contribution_percent'] ?? 0);
            $employeePercent = (float) ($pension['employee_contribution_percent'] ?? 0);
            $matchingLimit = (float) ($pension['employer_matching_limit'] ?? 0);

            if ($employerPercent <= 0) {
                continue;
            }

            // If there is a matching limit and the employee is not contributing up to it
            if ($matchingLimit > 0 && $employeePercent < $matchingLimit) {
                $gap = $matchingLimit - $employeePercent;

                return [
                    'pension_id' => $pension['id'],
                    'scheme_name' => $pension['scheme_name'] ?? 'Workplace pension',
                    'employee_percent' => $employeePercent,
                    'employer_percent' => $employerPercent,
                    'matching_limit' => $matchingLimit,
                    'gap_percent' => round($gap, 2),
                    'note' => sprintf(
                        'You contribute %.1f%% to %s but your employer matches up to %.1f%% — increasing your contribution by %.1f%% would unlock additional employer contributions.',
                        $employeePercent,
                        $pension['scheme_name'] ?? 'your workplace pension',
                        $matchingLimit,
                        $gap
                    ),
                    'priority' => 'high',
                ];
            }
        }

        return null;
    }

    /**
     * Calculate near-term financial commitments from life events (within 12 months).
     */
    private function calculateNearTermCommitments(array $context): float
    {
        $lifeEvents = $context['life_events'] ?? [];
        $total = 0;

        foreach ($lifeEvents as $event) {
            if (($event['impact_type'] ?? '') !== 'expense') {
                continue;
            }

            $expectedDate = $event['expected_date'] ?? null;
            if (! $expectedDate) {
                continue;
            }

            $monthsUntil = now()->diffInMonths(\Carbon\Carbon::parse($expectedDate), false);
            if ($monthsUntil >= 0 && $monthsUntil <= 12) {
                $total += (float) ($event['amount'] ?? 0);
            }
        }

        return $total;
    }
}
