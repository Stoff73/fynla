<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

/**
 * One line in the tax or benefit system that a user can stand near or over.
 * `evaluate` returns null when the line does not apply to this user: no data,
 * or not within the window. Nothing about the set of lines is fixed per user.
 */
interface ThresholdLine
{
    public function key(): string;

    public function evaluate(ThresholdContext $context): ?ThresholdResult;
}
