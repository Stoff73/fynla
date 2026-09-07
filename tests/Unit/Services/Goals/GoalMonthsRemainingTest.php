<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\User;
use App\Services\Goals\GoalProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0414. `GoalPlanService:170,279` read `$progress['months_remaining']` from an array
 * `GoalProgressService::calculateProgress()` has never returned. So the first was
 * always `null` and the second always fell back to **12 months — for every goal,
 * whatever its date**.
 *
 * This is the silent-absence family (`tests/CLAUDE.md` §4): an array read of a missing
 * key returns null quietly, and `?? 12` then supplies a number that is plausible and
 * never varies. **A goal nine years out and a goal three months out were planned on the
 * same horizon**, and no test could see it — the plan rendered, the figure looked
 * reasonable, and it was the same figure every time.
 *
 * Two goals with deliberately different horizons, because a single-goal test cannot
 * distinguish "computed correctly" from "always returns the same number".
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = app(GoalProgressService::class);
});

it('returns months remaining, and a different number for a different horizon', function () {
    $near = Goal::factory()->create([
        'user_id' => $this->user->id,
        'target_date' => now()->addMonths(3),
        'target_amount' => 10000,
        'current_amount' => 1000,
    ]);
    $far = Goal::factory()->create([
        'user_id' => $this->user->id,
        'target_date' => now()->addYears(9),
        'target_amount' => 10000,
        'current_amount' => 1000,
    ]);

    $nearMonths = $this->service->calculateProgress($near)['months_remaining'];
    $farMonths = $this->service->calculateProgress($far)['months_remaining'];

    expect($nearMonths)->toBeLessThan(6)
        ->and($farMonths)->toBeGreaterThan(100)
        // The assertion the old code would have failed: the two horizons differ.
        ->and($nearMonths)->not->toBe($farMonths);
});

/**
 * The key must be PRESENT, not merely correct when present — absence is the defect, and
 * a value assertion alone passes on an array that omits it entirely once a `??` default
 * is reintroduced downstream.
 */
it('emits the key so no consumer has to default it', function () {
    $goal = Goal::factory()->create([
        'user_id' => $this->user->id,
        'target_date' => now()->addMonths(18),
    ]);

    expect($this->service->calculateProgress($goal))->toHaveKey('months_remaining');
});

/**
 * One derivation, two access paths. The array value and the model accessor both come
 * from `GoalCalculationService`, so they cannot disagree about the same goal — which is
 * what a second implementation in the progress service would have produced.
 */
it('agrees with the model accessor for the same goal', function () {
    $goal = Goal::factory()->create([
        'user_id' => $this->user->id,
        'target_date' => now()->addMonths(30),
    ]);

    expect($this->service->calculateProgress($goal)['months_remaining'])
        ->toBe($goal->fresh()->months_remaining);
});
