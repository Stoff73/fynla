<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\User;
use App\Models\UserGamification;
use App\Models\UserLevelCrossing;
use App\Services\Gamification\LevelUpCollector;
use App\Services\Gamification\PointsService;

beforeEach(function () {
    $this->user = User::factory()->create(['is_preview_user' => false]);
    $this->svc = app(PointsService::class);
});

it('awards points once per dedup key', function () {
    $r1 = $this->svc->award($this->user, 'data', 'data:savings_account:first', 20);
    $r2 = $this->svc->award($this->user, 'data', 'data:savings_account:first', 20);

    expect($r1->awarded)->toBeTrue();
    expect($r2->awarded)->toBeFalse(); // dedup
    expect(PointAward::where('user_id', $this->user->id)->count())->toBe(1);
    expect(UserGamification::where('user_id', $this->user->id)->value('total_points'))->toBe(20);
});

it('keeps total_points monotonic and recomputes level', function () {
    $this->svc->award($this->user, 'data', 'a', 40);
    $this->svc->award($this->user, 'data', 'b', 20); // total 60 -> level 2

    $g = UserGamification::where('user_id', $this->user->id)->first();
    expect($g->total_points)->toBe(60);
    expect($g->level)->toBe(2);
});

/**
 * The award path no longer writes a pending celebration. What is owed is the
 * range above `celebrated_level`, which moves only when a client has actually
 * shown the climb and acknowledged it (CSJ 2026-09-17).
 */
it('flags a level-up and records it on the collector, without banking a celebration', function () {
    $collector = app(LevelUpCollector::class);
    $r = $this->svc->award($this->user, 'data', 'big', 60); // 0 -> 60 = L1 -> L2

    expect($r->leveledUp)->toBeTrue();
    expect($r->newLevel)->toBe(2);
    expect($r->newLevelName)->toBe('Saver');
    expect($collector->highest())->toMatchArray(['level' => 2]);

    // Untouched by the award — so the dashboard sees a range of 1 -> 2 owed.
    expect(UserGamification::where('user_id', $this->user->id)->value('celebrated_level'))->toBeNull();
});

it('persists every level crossed against the immutable award in the same transaction', function () {
    $result = $this->svc->award($this->user, 'milestone', 'milestone:large-crossing', 600);
    $awardId = PointAward::where('user_id', $this->user->id)->value('id');

    expect($result->newLevel)->toBe(6)
        ->and(UserLevelCrossing::where('user_id', $this->user->id)->orderBy('level')->get(['level', 'point_award_id']))
        ->toHaveCount(5)
        ->and(UserLevelCrossing::where('user_id', $this->user->id)->orderBy('level')->pluck('level')->all())->toBe([2, 3, 4, 5, 6])
        ->and(UserLevelCrossing::where('user_id', $this->user->id)->pluck('point_award_id')->unique()->all())->toBe([$awardId]);
});

it('never awards to preview users', function () {
    $preview = User::factory()->create(['is_preview_user' => true]);
    $r = $this->svc->award($preview, 'data', 'x', 50);

    expect($r->awarded)->toBeFalse();
    expect(UserGamification::where('user_id', $preview->id)->exists())->toBeFalse();
});
