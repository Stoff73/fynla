<?php

declare(strict_types=1);

namespace App\Services\Mobile;

/**
 * What the five dashboard cards say: every figure, ring and caption, built once
 * on the server and rendered as sent by web, /m and iOS (CSJ 2026-10-01: one
 * figure, every surface; audit docs/audits/2026-10-01-one-figure-every-surface.md
 * items 5, 6, 27 and 35).
 *
 * This replaces `resources/js/utils/dashboardCards.js` (shared by web and /m)
 * and iOS `FinancePanel.panels`, which worked the rings out on each client: iOS
 * had 0.72 and 0.85 typed in, a "Trend" ring web and /m never showed, and a
 * six-month emergency fund target typed in on two surfaces although the server
 * sends it.
 *
 * Clients format `value` as money (with "/year" when `value_is_income`) and
 * render the rest as it comes. Labels, routes and colours stay per surface.
 */
final class DashboardCards
{
    /**
     * @param  array<string, array<string, mixed>>  $modules  the aggregator's module summaries
     * @param  array<string, mixed>  $netWorth  the aggregator's net worth block
     * @return array<string, array<string, mixed>>
     */
    public function build(array $modules, array $netWorth): array
    {
        $assets = (float) ($netWorth['breakdown']['total_assets'] ?? 0);

        return [
            'net_worth' => $this->netWorth($netWorth, $assets),
            'protection' => $this->protection($modules['protection'] ?? []),
            'savings' => $this->savings($modules['savings'] ?? []),
            'retirement' => $this->retirement($modules['retirement'] ?? []),
            'investment' => $this->investment($modules['investment'] ?? [], $assets),
        ];
    }

    /** @return array<string, mixed> */
    private function netWorth(array $netWorth, float $assets): array
    {
        $total = (float) ($netWorth['total'] ?? 0);
        // Share of assets owned outright, net of what is owed.
        $equity = $assets > 0 ? $this->percent($total / $assets * 100) : 0;

        return $this->card(
            value: $total,
            caption: $this->money($assets).' assets',
            visual: $this->donut($equity, $equity.'%', 'Equity'),
        );
    }

    /** @return array<string, mixed> */
    private function protection(array $module): array
    {
        $cover = (float) ($module['total_coverage'] ?? 0);
        $covered = $cover > 0;

        return $this->card(
            value: $cover,
            caption: $covered ? 'Cover in place' : 'Add your cover',
            visual: $this->donut($covered ? 100 : 0, $covered ? 'Active' : 'None', 'Cover'),
        );
    }

    /** @return array<string, mixed> */
    private function savings(array $module): array
    {
        $months = (float) ($module['emergency_fund_months'] ?? 0);
        $target = (int) ($module['emergency_fund_target_months'] ?? 0);

        $caption = $target > 0 && $months >= $target
            ? 'Emergency fund on track'
            : ($months > 0 ? 'Building your fund' : 'Start your emergency fund');

        return $this->card(
            value: (float) ($module['total_savings'] ?? 0),
            caption: $caption,
            visual: $this->bar(
                $target > 0 ? $this->percent($months / $target * 100) : 0,
                $months > 0 ? $this->oneDecimal($months) : '0',
                '/ '.$target.' months',
            ),
        );
    }

    /** @return array<string, mixed> */
    private function retirement(array $module): array
    {
        $kind = $module['headline_kind'] ?? null;

        // Someone drawing sees the Retirement page's own figure and wording:
        // this year's income, and how long the pot lasts in the middle outcome.
        if ($kind === 'drawing') {
            // The drawing position's own words (RetirementDrawdownPosition).
            $lasts = ((float) ($module['drawing_per_year'] ?? 0)) > 0
                ? (string) ($module['drawing_lasts_label'] ?? '')
                : '';

            return $this->card(
                value: (float) ($module['card_value'] ?? 0),
                valueIsIncome: true,
                caption: 'Your income this year',
                visual: $this->bar(0, $lasts, ''),
            );
        }

        $value = (float) ($module['card_value'] ?? 0);
        $isIncome = ($module['card_value_is_income'] ?? false) === true;
        $hasTarget = ((float) ($module['target_income'] ?? 0)) > 0 && ($module['progress_percent'] ?? null) !== null;
        $progress = $hasTarget ? (int) $module['progress_percent'] : 0;

        $caption = match (true) {
            $isIncome => 'Guaranteed retirement income',
            $hasTarget => 'Towards your target',
            $value > 0 => 'Your pension pot',
            default => 'Plan your retirement',
        };

        return $this->card(
            value: $value,
            valueIsIncome: $isIncome,
            caption: $caption,
            visual: $this->bar(
                $this->percent($progress),
                $hasTarget ? $progress.'%' : 'Target not set',
                $hasTarget ? 'of target' : '',
            ),
        );
    }

    /** @return array<string, mixed> */
    private function investment(array $module, float $assets): array
    {
        $value = (float) ($module['portfolio_value'] ?? 0);
        $accounts = (int) ($module['accounts_count'] ?? 0);
        $holdings = (int) ($module['holdings_count'] ?? 0);

        // Share of total assets held as investments.
        $share = $assets > 0 ? $this->percent($value / $assets * 100) : ($value > 0 ? 100 : 0);

        return $this->card(
            value: $value,
            caption: $value > 0 ? $holdings.' '.($holdings === 1 ? 'holding' : 'holdings') : 'Add your investments',
            visual: $this->donut($share, $value > 0 ? (string) $accounts : '0', $accounts === 1 ? 'Account' : 'Accounts'),
        );
    }

    /** @return array<string, mixed> */
    private function card(float $value, string $caption, array $visual, bool $valueIsIncome = false): array
    {
        return [
            'value' => round($value, 2),
            'value_is_income' => $valueIsIncome,
            'caption' => $caption,
            'visual' => $visual,
        ];
    }

    /** @return array{type: string, progress: int, number: string, label: string} */
    private function donut(int $progress, string $number, string $label): array
    {
        return ['type' => 'donut', 'progress' => $progress, 'number' => $number, 'label' => $label];
    }

    /** @return array{type: string, progress: int, number: string, label: string} */
    private function bar(int $progress, string $number, string $label): array
    {
        return ['type' => 'bar', 'progress' => $progress, 'number' => $number, 'label' => $label];
    }

    /** A 0-100 whole percentage for a ring or bar. */
    private function percent(float $value): int
    {
        return (int) max(0, min(100, round($value)));
    }

    private function oneDecimal(float $value): string
    {
        $rounded = round($value, 1);

        return $rounded == floor($rounded) ? (string) (int) $rounded : number_format($rounded, 1);
    }

    private function money(float $value): string
    {
        return ($value < 0 ? '-£' : '£').number_format(abs($value), 0);
    }
}
