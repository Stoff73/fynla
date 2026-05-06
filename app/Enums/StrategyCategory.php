<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Strategy category for SaveTax dashboard recommendations.
 *
 * Backed by the same string literals previously used as inline strings in
 * TaxStrategyCalculator + StrategyRecommendation. Order in this declaration
 * is the canonical render order on the dashboard (income-band first, warnings
 * last).
 */
enum StrategyCategory: string
{
    case IncomeBand = 'income_band';
    case Allowance = 'allowance';
    case Household = 'household';
    case Lifecycle = 'lifecycle';
    case Warning = 'warning';

    /**
     * Sort weight for canonical render order (lower = surfaces first).
     */
    public function sortWeight(): int
    {
        return match ($this) {
            self::Warning => 0,
            self::IncomeBand => 1,
            self::Allowance => 2,
            self::Household => 3,
            self::Lifecycle => 4,
        };
    }
}
