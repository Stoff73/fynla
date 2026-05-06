<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Priority for SaveTax dashboard recommendations. Ordered high → medium → low
 * within each StrategyCategory on the dashboard.
 */
enum StrategyPriority: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function sortWeight(): int
    {
        return match ($this) {
            self::High => 0,
            self::Medium => 1,
            self::Low => 2,
        };
    }
}
