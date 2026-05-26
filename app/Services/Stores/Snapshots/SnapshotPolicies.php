<?php

declare(strict_types=1);

namespace App\Services\Stores\Snapshots;

use App\Services\Stores\TierConfigurationStore;

/**
 * SP2 PR8 §13 — Snapshot policies with per-tier surfacing windows read from
 * TierConfigurationStore (single source of truth). Retention ceiling stays at
 * 7 years (2555 days) for all tiers (SP1 §10.3 floor — unchanged).
 *
 * Upgrading widens the visible window with no recompute (SP1 §10.3 guarantee).
 */
class SnapshotPolicies
{
    private const RETENTION_DAYS = 2555;

    public function __construct(
        private readonly TierConfigurationStore $store,
    ) {}

    /**
     * Build the per-tier surfacing window map from the store.
     * Returns ['free' => int, 'tier1' => int, 'tier2' => int, 'tier3' => int].
     *
     * @return array<string, int>
     */
    private function tierWindowFromStore(): array
    {
        $window = [];
        foreach (TierConfigurationStore::TIERS as $tier) {
            $window[$tier] = $this->store->forTier($tier)->snapshot_surfacing_window_days;
        }

        return $window;
    }

    public function savingsAccountBalance(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null && (abs($new - $old) > 100 || ($old > 0 && abs($new - $old) / $old > 0.01)),
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: $this->tierWindowFromStore(),
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }

    public function savingsAnnualInterestProjected(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null && abs($new - $old) > 10,
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: $this->tierWindowFromStore(),
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }

    public function savingsIsaAllowanceUsedPct(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null && abs($new - $old) > 1.0,
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: $this->tierWindowFromStore(),
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }

    // SP1 Pass 3 / PR 6 — Pension snapshot policies.

    public function dcPensionFundValue(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null
                && (abs($new - $old) > 500 || ($old > 0 && abs($new - $old) / $old > 0.02)),
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: $this->tierWindowFromStore(),
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }

    public function dcPensionProjectedValue(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null
                && (abs($new - $old) > 1000 || ($old > 0 && abs($new - $old) / $old > 0.05)),
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: $this->tierWindowFromStore(),
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }

    public function dbPensionAnnualValue(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null
                && (abs($new - $old) > 100 || ($old > 0 && abs($new - $old) / $old > 0.02)),
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: $this->tierWindowFromStore(),
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }

    public function statePensionForecast(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null && abs($new - $old) > 50,
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: $this->tierWindowFromStore(),
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }
}
