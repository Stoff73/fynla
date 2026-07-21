<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserMilestone;
use App\Services\Mobile\MilestoneDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(MilestoneDetectionService::class);
});

it('detects all net-worth thresholds at or below the current total, once', function () {
    $user = User::factory()->create();

    $new = $this->service->detectNetWorth($user, 120000);

    expect(collect($new)->pluck('threshold')->all())->toBe([10000, 25000, 50000, 100000])
        ->and(collect($new)->every(fn ($m) => $m['share_type'] === 'net_worth_milestone'))->toBeTrue();
    expect(UserMilestone::where('user_id', $user->id)->count())->toBe(4);
});

it('does not re-fire net-worth milestones already crossed', function () {
    $user = User::factory()->create();
    $this->service->detectNetWorth($user, 120000);

    // Same total again → nothing new.
    expect($this->service->detectNetWorth($user, 120000))->toBe([]);

    // Crossing the next threshold → only the new one fires.
    $new = $this->service->detectNetWorth($user, 260000);
    expect(collect($new)->pluck('threshold')->all())->toBe([250000]);
    expect(UserMilestone::where('user_id', $user->id)->count())->toBe(5);
});

it('returns nothing when below the first threshold', function () {
    $user = User::factory()->create();
    expect($this->service->detectNetWorth($user, 5000))->toBe([]);
    expect(UserMilestone::where('user_id', $user->id)->count())->toBe(0);
});

it('detects goal-progress milestones and labels the 100% one as reached', function () {
    $user = User::factory()->create();

    $new = $this->service->detectGoal($user, 42, 100.0, 'House Deposit');

    expect(collect($new)->pluck('threshold')->all())->toBe([25, 50, 75, 100])
        ->and(end($new)['label'])->toContain('reached your goal')
        ->and($new[0]['share_type'])->toBe('goal_milestone');

    // Idempotent.
    expect($this->service->detectGoal($user, 42, 100.0, 'House Deposit'))->toBe([]);
});

it('keeps goal milestones independent per goal', function () {
    $user = User::factory()->create();
    $this->service->detectGoal($user, 1, 60.0, 'Goal A');   // 25, 50
    $new = $this->service->detectGoal($user, 2, 60.0, 'Goal B'); // 25, 50 (different goal)

    expect(collect($new)->pluck('threshold')->all())->toBe([25, 50]);
    expect(UserMilestone::where('user_id', $user->id)->where('milestone_type', 'goal')->count())->toBe(4);
});
