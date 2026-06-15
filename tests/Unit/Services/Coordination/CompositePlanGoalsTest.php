<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\User;
use App\Services\Coordination\CompositePlanService;

it('deducts active goal commitments from the surplus available to strategies', function (): void {
    $user = User::factory()->create();
    Goal::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'monthly_contribution' => 300,
    ]);

    $fin = app(CompositePlanService::class)->financials($user->fresh());

    // total_goal_commitments sums active goals' monthly_contribution.
    expect($fin['goal_commitments'])->toBe(300.0)
        // effective surplus = available, less goal demands, floored at zero.
        ->and($fin['effective_surplus'])->toBe(max(0.0, $fin['available_monthly_surplus'] - 300.0))
        ->and($fin['goals'])->toHaveKey('total_goal_commitments');
});

it('reports zero goal commitments when there are no active goals', function (): void {
    $user = User::factory()->create();

    $fin = app(CompositePlanService::class)->financials($user);

    expect($fin['goal_commitments'])->toBe(0.0)
        ->and($fin['effective_surplus'])->toBe(max(0.0, $fin['available_monthly_surplus']));
});
