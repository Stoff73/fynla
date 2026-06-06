<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserGamification;

it('awards points when a savings account is created', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->create(['user_id' => $user->id]);

    expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(20);
});

it('does not award points for preview users', function () {
    $user = User::factory()->create(['is_preview_user' => true]);

    SavingsAccount::factory()->create(['user_id' => $user->id]);

    expect(UserGamification::where('user_id', $user->id)->exists())->toBeFalse();
});
