<?php

declare(strict_types=1);

use App\Services\Stores\Snapshots\SnapshotPolicy;

it('policy fires when threshold predicate returns true', function () {
    $policy = new SnapshotPolicy(
        triggerPredicate: fn ($old, $new) => abs($new - $old) > 100,
        retentionDays: 2555,
        surfacingWindowDays: ['free' => 90, 'tier1' => 365, 'tier2' => 1825, 'tier3' => 2555],
        maxRowsHardCap: 5000,
        recalcCadence: 'on_change',
    );

    expect($policy->shouldSnapshot(1000, 1500))->toBeTrue();
    expect($policy->shouldSnapshot(1000, 1050))->toBeFalse();
});

it('policy surfacingWindowDays per tier mirrors spec §10.3', function () {
    $policy = new SnapshotPolicy(
        triggerPredicate: fn ($old, $new) => true,
        retentionDays: 2555,
        surfacingWindowDays: ['free' => 90, 'tier1' => 365, 'tier2' => 1825, 'tier3' => 2555],
        maxRowsHardCap: 5000,
        recalcCadence: 'on_change',
    );

    expect($policy->surfacingWindow('free'))->toBe(90);
    expect($policy->surfacingWindow('tier1'))->toBe(365);
    expect($policy->surfacingWindow('tier2'))->toBe(1825);
    expect($policy->surfacingWindow('tier3'))->toBe(2555);
});
