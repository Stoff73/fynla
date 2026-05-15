<?php

declare(strict_types=1);

namespace App\Services\Stores\Snapshots;

class SnapshotPolicies
{
    private const TIER_WINDOW = [
        'free' => 90,
        'tier1' => 365,
        'tier2' => 1825,
        'tier3' => 2555,
    ];

    private const RETENTION_DAYS = 2555;

    public static function savingsAccountBalance(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null && (abs($new - $old) > 100 || ($old > 0 && abs($new - $old) / $old > 0.01)),
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: self::TIER_WINDOW,
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }

    public static function savingsAnnualInterestProjected(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null && abs($new - $old) > 10,
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: self::TIER_WINDOW,
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }

    public static function savingsIsaAllowanceUsedPct(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null && abs($new - $old) > 1.0,
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: self::TIER_WINDOW,
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }
}
