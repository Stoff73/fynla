<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserGamification;
use App\Services\Gamification\PointsService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create(['is_preview_user' => false]);
    $this->svc = app(PointsService::class);
});

it('awards the daily login once per calendar day', function () {
    Carbon::setTestNow('2026-06-06 09:00:00');
    $this->svc->recordLogin($this->user);
    $this->svc->recordLogin($this->user); // same day -> no second award

    $g = UserGamification::where('user_id', $this->user->id)->first();
    expect($g->total_points)->toBe(5);
    expect($g->login_streak_days)->toBe(1);
    Carbon::setTestNow();
});

it('increments the streak on consecutive days and awards the day-3 bonus', function () {
    Carbon::setTestNow('2026-06-06 09:00:00');
    $this->svc->recordLogin($this->user);
    Carbon::setTestNow('2026-06-07 09:00:00');
    $this->svc->recordLogin($this->user);
    Carbon::setTestNow('2026-06-08 09:00:00');
    $this->svc->recordLogin($this->user); // streak hits 3 -> +5 daily +15 bonus

    $g = UserGamification::where('user_id', $this->user->id)->first();
    expect($g->login_streak_days)->toBe(3);
    // 3 daily (5*3=15) + day-3 streak bonus (15) = 30
    expect($g->total_points)->toBe(30);
    Carbon::setTestNow();
});

it('resets the streak after a missed day', function () {
    Carbon::setTestNow('2026-06-06 09:00:00');
    $this->svc->recordLogin($this->user);
    Carbon::setTestNow('2026-06-09 09:00:00'); // skipped 7th and 8th
    $this->svc->recordLogin($this->user);

    expect(UserGamification::where('user_id', $this->user->id)->value('login_streak_days'))->toBe(1);
    Carbon::setTestNow();
});
