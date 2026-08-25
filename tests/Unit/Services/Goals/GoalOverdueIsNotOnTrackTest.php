<?php

declare(strict_types=1);

use App\Agents\GoalsAgent;
use App\Models\Goal;
use App\Models\User;
use App\Services\Goals\GoalCalculationService;
use App\Services\Goals\GoalProgressService;

/**
 * W-0411 — every overdue goal reported "On track" and the goals page
 * congratulated the user on it.
 *
 * `start_date` is stamped with today at creation, so a goal recorded against a
 * date already in the past stores a span that runs BACKWARDS. On Carbon 2
 * `diffInDays()` is ABSOLUTE — verified here, not assumed — so the inverted
 * range came back POSITIVE and the `$totalDays <= 0` guard in
 * `calculateIsOnTrack()`, which exists precisely to catch a non-positive span,
 * never fired. Elapsed time read as almost none, expected progress as almost
 * none, and any progress at all cleared the bar.
 *
 * The page summary is `on_track_count === total_goals`, so one wrong boolean
 * per goal became "All goals on track! Keep up the great progress" over a goal
 * four and a half months past its date at 75%.
 *
 * FIXTURE NOTE (tests/CLAUDE.md §4, Fixture variant). A goal suite whose goals
 * are all future-dated never enters the inverted-range branch at all — which is
 * how this survived. The fixture below therefore holds an overdue goal AND an
 * overdue-but-fully-funded one, because those are different answers: missed
 * versus achieved late. It also holds healthy goals, so the assertions can
 * distinguish "the rule fires" from "everything is false now".
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = app(GoalCalculationService::class);
});

/**
 * A goal exactly as the app records one against a date that has already gone:
 * `start_date` today, `target_date` behind it.
 */
function pastDatedGoal(User $user, string $targetDate, float $current, float $target, array $overrides = []): Goal
{
    return Goal::factory()->create(array_merge([
        'user_id' => $user->id,
        'goal_name' => 'Past-dated goal',
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'target_date' => $targetDate,
        'current_amount' => $current,
        'target_amount' => $target,
    ], $overrides));
}

describe('the Carbon behaviour this defect rests on', function () {
    it('returns an ABSOLUTE day count by default, which is what inverted the span', function () {
        $later = Carbon\Carbon::parse('2026-08-22');
        $earlier = Carbon\Carbon::parse('2026-08-01');

        // 21, not -21. The whole defect is here.
        expect($later->diffInDays($earlier))->toBe(21)
            ->and($later->diffInDays($earlier, false))->toBe(-21);
    });
});

describe('GoalCalculationService::isOverdue', function () {
    it('is true once the target date has passed', function () {
        $goal = pastDatedGoal($this->user, now()->subWeeks(3)->toDateString(), 12_000, 15_000);

        expect($this->service->isOverdue($goal))->toBeTrue();
    });

    it('is false on the target date itself — the day has not passed', function () {
        $goal = pastDatedGoal($this->user, now()->toDateString(), 12_000, 15_000);

        expect($this->service->isOverdue($goal))->toBeFalse();
    });

    it('is false for a goal that was reached, whenever it was reached', function () {
        $goal = pastDatedGoal($this->user, now()->subMonths(4)->toDateString(), 15_000, 15_000, [
            'status' => 'completed',
        ]);

        expect($this->service->isOverdue($goal))->toBeFalse();
    });

    it('is false when there is no target date to have passed', function () {
        // `goals.target_date` is NOT NULL, so this shape only ever reaches the
        // service unsaved — which is exactly why the guard has to stay.
        $goal = Goal::factory()->make([
            'user_id' => $this->user->id,
            'target_date' => null,
            'start_date' => now()->subYear()->toDateString(),
        ]);

        expect($this->service->isOverdue($goal))->toBeFalse()
            ->and($this->service->calculateIsOnTrack($goal))->toBeFalse();
    });
});

describe('GoalCalculationService::calculateIsOnTrack', function () {
    it('is false for a goal four and a half months past its date at 75%', function () {
        // The live row: target 2026-04-05, 75% funded, reported "On track".
        $goal = pastDatedGoal($this->user, now()->subMonths(4)->subDays(15)->toDateString(), 45_000, 60_000);

        expect($goal->progress_percentage)->toBe(75.0)
            ->and($this->service->calculateIsOnTrack($goal))->toBeFalse();
    });

    it('is false for an overdue goal that IS fully funded — that is achieved late, not on track', function () {
        $goal = pastDatedGoal($this->user, now()->subWeeks(3)->toDateString(), 15_000, 15_000);

        expect($goal->progress_percentage)->toBe(100.0)
            ->and($this->service->calculateIsOnTrack($goal))->toBeFalse();
    });

    it('is still TRUE for a healthy future-dated goal — the rule fires on overdue, not on everything', function () {
        $goal = Goal::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'start_date' => now()->subMonths(6)->toDateString(),
            'target_date' => now()->addMonths(6)->toDateString(),
            'current_amount' => 5_000,
            'target_amount' => 10_000,
        ]);

        expect($this->service->calculateIsOnTrack($goal))->toBeTrue();
    });

    it('is false for a future-dated goal that is genuinely behind', function () {
        $goal = Goal::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'start_date' => now()->subMonths(9)->toDateString(),
            'target_date' => now()->addMonths(3)->toDateString(),
            'current_amount' => 1_000,
            'target_amount' => 10_000,
        ]);

        // 10% funded against 75% of the time spent.
        expect($this->service->calculateIsOnTrack($goal))->toBeFalse();
    });

    it('still requires 100% when the span is genuinely zero-length', function () {
        $sameDay = Goal::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'target_date' => now()->toDateString(),
            'current_amount' => 15_000,
            'target_amount' => 15_000,
        ]);

        $sameDayShort = Goal::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'target_date' => now()->toDateString(),
            'current_amount' => 7_000,
            'target_amount' => 15_000,
        ]);

        expect($this->service->calculateIsOnTrack($sameDay))->toBeTrue()
            ->and($this->service->calculateIsOnTrack($sameDayShort))->toBeFalse();
    });
});

describe('GoalCalculationService::calculateStatusLabel', function () {
    it('separates a missed goal from one achieved late', function () {
        $missed = pastDatedGoal($this->user, now()->subMonths(4)->toDateString(), 45_000, 60_000);
        $achievedLate = pastDatedGoal($this->user, now()->subMonths(4)->toDateString(), 60_000, 60_000);

        expect($this->service->calculateStatusLabel($missed))->toBe('Overdue')
            ->and($this->service->calculateStatusLabel($achievedLate))->toBe('Achieved late');
    });

    it('never says On track about a goal whose date has gone', function () {
        $goal = pastDatedGoal($this->user, now()->subDay()->toDateString(), 14_999, 15_000);

        expect($this->service->calculateStatusLabel($goal))->not->toBe('On track');
    });

    it('keeps the ordinary vocabulary for goals whose date has not gone', function () {
        $healthy = Goal::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'start_date' => now()->subMonths(6)->toDateString(),
            'target_date' => now()->addMonths(6)->toDateString(),
            'current_amount' => 5_000,
            'target_amount' => 10_000,
        ]);
        $notStarted = Goal::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
            'target_date' => now()->addYear()->toDateString(),
            'current_amount' => 0,
            'target_amount' => 10_000,
        ]);
        $paused = Goal::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'paused',
            'target_date' => now()->addYear()->toDateString(),
        ]);

        expect($this->service->calculateStatusLabel($healthy))->toBe('On track')
            ->and($this->service->calculateStatusLabel($notStarted))->toBe('Not started')
            ->and($this->service->calculateStatusLabel($paused))->toBe('Paused');
    });
});

describe('the Goal model exposes the same answer every surface reads', function () {
    it('appends is_overdue and status_label alongside is_on_track', function () {
        $goal = pastDatedGoal($this->user, now()->subWeeks(3)->toDateString(), 12_000, 15_000);

        $serialised = $goal->fresh()->toArray();

        expect($serialised)->toHaveKeys(['is_on_track', 'is_overdue', 'status_label'])
            ->and($serialised['is_on_track'])->toBeFalse()
            ->and($serialised['is_overdue'])->toBeTrue()
            ->and($serialised['status_label'])->toBe('Overdue');
    });
});

describe('GoalProgressService no longer decides this a second time', function () {
    it('reports the same is_on_track as GoalCalculationService for an overdue goal', function () {
        $goal = pastDatedGoal($this->user, now()->subWeeks(3)->toDateString(), 12_000, 15_000);

        $progress = app(GoalProgressService::class)->calculateProgress($goal);

        expect($progress['is_on_track'])->toBe($this->service->calculateIsOnTrack($goal))
            ->toBeFalse()
            ->and($progress['is_overdue'])->toBeTrue()
            ->and($progress['status_label'])->toBe('Overdue');
    });

    it('spends the whole period once the date has gone, rather than none of it', function () {
        $goal = pastDatedGoal($this->user, now()->subWeeks(3)->toDateString(), 12_000, 15_000);

        $progress = app(GoalProgressService::class)->calculateProgress($goal);

        // The inverted span used to leave expected progress at 0, so an overdue
        // goal read as AHEAD of a schedule that had already ended.
        expect($progress['expected_progress'])->toBe(100.0)
            ->and($progress['time_progress_percentage'])->toBe(100.0)
            ->and($progress['progress_delta'])->toBe(-20.0)
            ->and($progress['status'])->toBe('behind');
    });
});

describe('the page summary that produced "All goals on track!"', function () {
    it('does not count an overdue goal towards on_track_count', function () {
        // Two healthy, two overdue — so on_track_count can be neither 0 nor all.
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'assigned_module' => 'savings',
            'start_date' => now()->subMonths(6)->toDateString(),
            'target_date' => now()->addMonths(6)->toDateString(),
            'current_amount' => 5_000,
            'target_amount' => 10_000,
        ]);
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'assigned_module' => 'savings',
            'start_date' => now()->subMonths(2)->toDateString(),
            'target_date' => now()->addYears(2)->toDateString(),
            'current_amount' => 4_000,
            'target_amount' => 10_000,
        ]);
        pastDatedGoal($this->user, now()->subMonths(4)->toDateString(), 45_000, 60_000, ['assigned_module' => 'savings']);
        pastDatedGoal($this->user, now()->subWeeks(3)->toDateString(), 12_000, 15_000, ['assigned_module' => 'savings']);

        $overview = app(GoalsAgent::class)->getDashboardOverview($this->user->id);

        expect($overview['total_goals'])->toBe(4)
            ->and($overview['on_track_count'])->toBe(2)
            // The banner's own condition, stated as the page states it.
            ->and($overview['on_track_count'] === $overview['total_goals'])->toBeFalse();
    });

    it('carries the overdue vocabulary through to the surfaces', function () {
        pastDatedGoal($this->user, now()->subMonths(4)->toDateString(), 45_000, 60_000, ['assigned_module' => 'savings']);

        $overview = app(GoalsAgent::class)->getDashboardOverview($this->user->id);
        $goal = $overview['top_goals'][0];

        expect($goal)->toHaveKeys(['is_on_track', 'is_overdue', 'status_label'])
            ->and($goal['is_overdue'])->toBeTrue()
            ->and($goal['status_label'])->toBe('Overdue');
    });
});
