<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\User;
use App\Models\UserGamification;
use App\Services\Gamification\PointsService;

beforeEach(function () {
    $this->user = User::factory()->create(['is_preview_user' => false]);
    $this->svc = app(PointsService::class);
});

it('awards the first-in-category bonus once', function () {
    $this->svc->awardDataEntry($this->user, 'savings_account', 101);
    $this->svc->awardDataEntry($this->user, 'savings_account', 101); // same record id -> dedup

    expect(PointAward::where('user_id', $this->user->id)->count())->toBe(1);
    expect(UserGamification::where('user_id', $this->user->id)->value('total_points'))->toBe(20); // first-in-category
});

it('awards capped extra-record points after the first', function () {
    // first in category + 4 more distinct records; cap on extras is 3.
    $this->svc->awardDataEntry($this->user, 'savings_account', 1);  // +20 (first)
    $this->svc->awardDataEntry($this->user, 'savings_account', 2);  // +5
    $this->svc->awardDataEntry($this->user, 'savings_account', 3);  // +5
    $this->svc->awardDataEntry($this->user, 'savings_account', 4);  // +5
    $this->svc->awardDataEntry($this->user, 'savings_account', 5);  // capped -> +0

    // 20 + 5*3 = 35
    expect(UserGamification::where('user_id', $this->user->id)->value('total_points'))->toBe(35);
});
