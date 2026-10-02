<?php

declare(strict_types=1);

namespace App\Services\Savings;

/**
 * What the savings page shows, worked out once (CSJ 2026-10-01: one figure,
 * every surface; audit docs/audits/2026-10-01-one-figure-every-surface.md items
 * 21 to 23 and 38). Web `/savings`, `/m` Bank Accounts and iOS Savings render
 * this block as sent; none of them adds up accounts, divides by spending or
 * works out a percentage.
 *
 * The runway wording was `resources/js/utils/emergencyRunway.js` (W-0276,
 * W-0495): "from cash savings", never "of cover", and a prompt rather than
 * "0 months" when no spending is recorded.
 */
final class SavingsPosition
{
    public const RUNWAY_UNAVAILABLE_LABEL = 'Add your monthly spending';

    public const RUNWAY_UNAVAILABLE_HINT = 'We need what you spend each month before we can work out how long your cash would last.';

    /**
     * @param  float  $totalCash  CrossModuleAssetAggregator::calculateCashTotal (the user's share)
     * @param  float|null  $runwayMonths  null when no spending is recorded
     * @param  array{target_months: int, target_amount: float, rationale: string}  $target
     * @param  array<string, mixed>  $isa  ISATracker::getISAAllowanceStatus
     * @param  iterable<object>  $accountsAtShare  the accounts with current_balance at the user's share
     * @return array<string, mixed>
     */
    public function build(float $totalCash, float $monthlyExpenditure, ?float $runwayMonths, array $target, array $isa, iterable $accountsAtShare = []): array
    {
        $targetAmount = (float) $target['target_amount'];
        $covered = $targetAmount > 0 ? (int) min(100, round($totalCash / $targetAmount * 100)) : null;

        $isaTotal = (float) ($isa['total_allowance'] ?? 0);
        $isaRemaining = (float) ($isa['remaining'] ?? 0);
        $isaPercent = (float) ($isa['percentage_used'] ?? 0);

        return [
            'total_cash' => round($totalCash, 2),
            // The cash page's group totals (web /net-worth/cash summed these itself).
            'group_totals' => $this->groupTotals($accountsAtShare),
            'monthly_expenditure' => round($monthlyExpenditure, 2),
            'emergency_fund' => [
                'runway_months' => $runwayMonths,
                'runway_label' => $this->runwayLabel($runwayMonths),
                // The number alone, as the label prints it (for a gauge); null with no spending.
                'runway_figure' => $runwayMonths === null ? null : $this->runwayFigure($runwayMonths),
                'runway_hint' => $runwayMonths === null ? self::RUNWAY_UNAVAILABLE_HINT : null,
                'target_months' => (int) $target['target_months'],
                'target_amount' => round($targetAmount, 2),
                'rationale' => (string) $target['rationale'],
                // Share of the target the cash covers, 0-100; null with no target.
                'covered_percent' => $covered,
                // What is still needed to reach the target; 0 once it is reached.
                'shortfall' => round(max(0.0, $targetAmount - $totalCash), 2),
                'covered_label' => $covered === null ? '' : $covered.'% of target',
                // on_track at 100%, part at 50%, low below (colour is the client's).
                'status' => match (true) {
                    $covered === null, $covered >= 100 => 'on_track',
                    $covered >= 50 => 'part',
                    default => 'low',
                },
            ],
            'isa' => [
                'total_allowance' => round($isaTotal, 2),
                'used' => round((float) ($isa['total_used'] ?? 0), 2),
                'remaining' => round($isaRemaining, 2),
                'percent_used' => round(min(100.0, $isaPercent), 2),
                // full at 100%, nearly at 80%, open below (colour is the client's).
                'status' => match (true) {
                    $isaPercent >= 100 => 'full',
                    $isaPercent >= 80 => 'nearly',
                    default => 'open',
                },
                'remaining_label' => $isaPercent >= 100 || $isaRemaining <= 0
                    ? 'Fully used'
                    : '£'.number_format($isaRemaining, 0).' remaining',
            ],
        ];
    }

    /**
     * The user's share of each group on the cash page: current accounts, savings
     * accounts, cash ISAs (an ISA is never a bank account, CSJ) and National
     * Savings and Investments.
     *
     * @param  iterable<object>  $accounts
     * @return array{current_accounts: float, savings_accounts: float, isas: float, nsi: float}
     */
    private function groupTotals(iterable $accounts): array
    {
        $totals = ['current_accounts' => 0.0, 'savings_accounts' => 0.0, 'isas' => 0.0, 'nsi' => 0.0];

        foreach ($accounts as $account) {
            $group = match (true) {
                (bool) ($account->is_isa ?? false) || in_array($account->account_type, ['cash_isa', 'junior_isa'], true) => 'isas',
                $account->account_type === 'current_account' => 'current_accounts',
                in_array($account->account_type, ['premium_bonds', 'nsi'], true) => 'nsi',
                in_array($account->account_type, ['savings_account', 'easy_access', 'instant_access', 'notice', 'fixed'], true) => 'savings_accounts',
                default => null,
            };
            if ($group !== null) {
                $totals[$group] += (float) $account->current_balance;
            }
        }

        return array_map(static fn (float $total): float => round($total, 2), $totals);
    }

    /** The runway as a sentence, or the prompt where it cannot be worked out. */
    public function runwayLabel(?float $months): string
    {
        if ($months === null) {
            return self::RUNWAY_UNAVAILABLE_LABEL;
        }

        $text = $this->runwayFigure($months);

        return $text.' '.($text === '1' ? 'month' : 'months').' from cash savings';
    }

    /** Whole months from 10, one decimal place below. */
    public function runwayFigure(float $months): string
    {
        $rounded = $months >= 10 ? round($months) : round($months, 1);

        return $rounded == floor($rounded) ? (string) (int) $rounded : (string) $rounded;
    }
}
