<?php

declare(strict_types=1);

use App\Models\ExpenditureProfile;
use App\Models\User;
use App\Models\UserGamification;
use App\Services\UserProfile\UserProfileService;

it('awards the first-capture bonus when an expenditure profile is created', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    ExpenditureProfile::factory()->create(['user_id' => $user->id]);

    expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(20);
});

it('does not award expenditure points for preview users', function () {
    $user = User::factory()->create(['is_preview_user' => true]);

    ExpenditureProfile::factory()->create(['user_id' => $user->id]);

    expect(UserGamification::where('user_id', $user->id)->exists())->toBeFalse();
});

it('awards the first-capture bonus when income transitions from empty to set', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'annual_employment_income' => 0,
    ]);
    $svc = app(UserProfileService::class);

    $svc->updateIncomeOccupation($user, ['annual_employment_income' => 45000]);

    expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(20);
});

it('awards the income first-capture bonus exactly once across repeated updates', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'annual_employment_income' => 0,
    ]);
    $svc = app(UserProfileService::class);

    $svc->updateIncomeOccupation($user->fresh(), ['annual_employment_income' => 45000]);
    $svc->updateIncomeOccupation($user->fresh(), ['annual_employment_income' => 60000]);

    expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(20);
});

it('does not award income points for preview users', function () {
    $user = User::factory()->create([
        'is_preview_user' => true,
        'annual_employment_income' => 0,
    ]);
    $svc = app(UserProfileService::class);

    $svc->updateIncomeOccupation($user, ['annual_employment_income' => 45000]);

    expect(UserGamification::where('user_id', $user->id)->exists())->toBeFalse();
});
