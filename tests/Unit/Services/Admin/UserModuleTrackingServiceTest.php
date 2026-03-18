<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Admin\UserModuleTrackingService;

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->service = new UserModuleTrackingService;
});

it('returns complete status for user with all protection data', function () {
    $user = User::factory()->create();
    \App\Models\LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);
    \App\Models\CriticalIllnessPolicy::factory()->create(['user_id' => $user->id]);
    \App\Models\IncomeProtectionPolicy::factory()->create(['user_id' => $user->id]);

    $result = $this->service->getModuleStatus($user);

    expect($result['protection']['status'])->toBe('complete');
    expect($result['protection']['sub_areas']['life_insurance']['count'])->toBe(1);
});

it('returns partial status for user with some data', function () {
    $user = User::factory()->create();
    \App\Models\LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);

    $result = $this->service->getModuleStatus($user);

    expect($result['protection']['status'])->toBe('partial');
});

it('returns empty status for user with no data', function () {
    $user = User::factory()->create();
    $result = $this->service->getModuleStatus($user);

    expect($result['protection']['status'])->toBe('empty');
    expect($result['savings']['status'])->toBe('empty');
    expect($result['investment']['status'])->toBe('empty');
    expect($result['retirement']['status'])->toBe('empty');
    expect($result['estate']['status'])->toBe('empty');
});

it('returns correct sub-area counts and values', function () {
    $user = User::factory()->create();
    \App\Models\LifeInsurancePolicy::factory()->count(3)->create([
        'user_id' => $user->id,
        'sum_assured' => 100000,
    ]);

    $result = $this->service->getModuleStatus($user);

    expect($result['protection']['sub_areas']['life_insurance']['count'])->toBe(3);
    expect($result['protection']['sub_areas']['life_insurance']['total_cover'])->toBe(300000.0);
});

it('returns onboarding data', function () {
    $user = User::factory()->create([
        'onboarding_completed' => true,
    ]);

    $result = $this->service->getModuleStatus($user);

    expect($result['onboarding']['completed'])->toBeTrue();
});

it('handles user with no relationships loaded', function () {
    $user = User::factory()->create();
    $freshUser = User::find($user->id);

    $result = $this->service->getModuleStatus($freshUser);

    expect($result)->toHaveKeys(['protection', 'savings', 'investment', 'retirement', 'estate', 'onboarding']);
});
