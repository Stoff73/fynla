<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Cross-module significance thresholds.
 *
 * These are heuristic £ values used by Coordination services and the
 * Dashboard aggregator to classify how prominently a recommendation /
 * concern / opportunity should surface in the UI. They are NOT tax
 * values (use TaxConfigService for those) — they reflect product
 * decisions about what counts as a "small / important / critical"
 * planning concern across modules.
 *
 * Surfaced + promoted during the 2026-05-23 tech-debt audit (B43)
 * after the same £100k / £200k literals were found duplicated 12+
 * times across PriorityRanker, HolisticPlanner, DashboardAggregator.
 */
final class SignificanceThresholds
{
    /**
     * A planning concern at or above this level is "important" — should
     * appear prominently on the dashboard and in the priority list.
     *
     * Used for: protection coverage gaps, estate liabilities, IHT
     * exposure, salary-sacrifice savings opportunities, etc.
     */
    public const IMPORTANT = 100000;

    /**
     * A planning concern at or above this level is "critical" — top of
     * the priority list and may be flagged with elevated severity in
     * recommendations.
     */
    public const CRITICAL = 200000;
}
