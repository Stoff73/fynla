<?php

declare(strict_types=1);

use App\Services\Stores\Snapshots\SnapshotPolicy;

it('policy fires when threshold predicate returns true', function () {
    $policy = new SnapshotPolicy(
        triggerPredicate: fn ($old, $new) => abs($new - $old) > 100,
        retentionDays: 2555,
        surfacingWindowDays: ['free' => 90, 'premium' => null],
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
        surfacingWindowDays: ['free' => 90, 'premium' => null],
        maxRowsHardCap: 5000,
        recalcCadence: 'on_change',
    );

    expect($policy->surfacingWindow('free'))->toBe(90);
    expect($policy->surfacingWindow('premium'))->toBeNull();
});
