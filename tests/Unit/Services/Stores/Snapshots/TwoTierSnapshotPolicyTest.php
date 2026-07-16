<?php

declare(strict_types=1);

use App\Services\Stores\Snapshots\SnapshotPolicies;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('surfaces 90 days for Free and all retained history for Premium', function () {
    $this->seed(TierConfigurationSeeder::class);

    $policy = app(SnapshotPolicies::class)->savingsAccountBalance();

    expect($policy->surfacingWindow('free'))->toBe(90)
        ->and($policy->surfacingWindow('premium'))->toBeNull()
        ->and($policy->retentionDays)->toBe(2555)
        ->and($policy->maxRowsHardCap)->toBe(5000);
});
