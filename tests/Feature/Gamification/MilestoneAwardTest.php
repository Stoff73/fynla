<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserGamification;
use App\Services\Mobile\MilestoneDetectionService;

it('awards points for a newly crossed net-worth milestone', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $svc = app(MilestoneDetectionService::class);

    $svc->detectNetWorth($user, 30000); // crosses 10k + 25k = 2 milestones
    expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(60); // 2 * 30

    // Re-running does not double-award (milestones already recorded).
    $svc->detectNetWorth($user, 30000);
    expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(60);
});
