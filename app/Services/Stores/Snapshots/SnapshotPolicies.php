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
     * Returns the tier window in days; null means all retained history.
     *
     * @return array<string, int|null>
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

    public function investmentAccountValue(): SnapshotPolicy
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

    public function liabilityBalance(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null
                && (abs($new - $old) > 100 || ($old > 0 && abs($new - $old) / $old > 0.01)),
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

    // SP1 Pass 4 PR 6 — Property snapshot policies.

    public function propertyValue(): SnapshotPolicy
    {
        // Threshold: any change >£1,000 or >0.5% (whichever triggers first). Retain 7 years.
        // Property values are large; 0.5% relative threshold keeps noise down while
        // capturing meaningful market movements.
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null
                && (abs($new - $old) > 1000 || ($old > 0 && abs($new - $old) / $old > 0.005)),
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: $this->tierWindowFromStore(),
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }

    public function propertyEquity(): SnapshotPolicy
    {
        // Equity moves with both value changes and mortgage paydown — same threshold shape
        // as propertyValue(). Pass 5 will reconcile equity_gbp against MortgageStore reads.
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null
                && (abs($new - $old) > 1000 || ($old > 0 && abs($new - $old) / $old > 0.005)),
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: $this->tierWindowFromStore(),
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }

    // SP1 Pass 5 PR 6 — Mortgage snapshot policies.

    public function mortgageBalance(): SnapshotPolicy
    {
        // Threshold: any change >= £1,000 or >= 0.5% relative (whichever first). Retain 7 years.
        // Mirrors propertyValue/propertyEquity shape — balances move on each payment but we
        // only snapshot meaningful jumps to keep noise low.
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null
                && (abs($new - $old) >= 1000 || ($old > 0 && abs($new - $old) / max(abs($old), 1) >= 0.005)),
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: $this->tierWindowFromStore(),
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }

    public function mortgageRate(): SnapshotPolicy
    {
        // Rate change policy: snapshot on any rate change >= 0.25 percentage points.
        // Mortgages change rate infrequently; a tight threshold captures every fix-end reroll.
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null && abs($new - $old) >= 0.25,
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: $this->tierWindowFromStore(),
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }
}
