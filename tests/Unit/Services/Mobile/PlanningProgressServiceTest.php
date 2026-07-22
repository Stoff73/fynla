<?php

declare(strict_types=1);

use App\Models\ExpenditureProfile;
use App\Models\User;
use App\Services\Mobile\PlanningProgressService;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('scores between 0 and 100', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $score = app(PlanningProgressService::class)->scoreFor($user);

    expect($score)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});

it('bounds the percentile to 1..99', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $pct = app(PlanningProgressService::class)->percentileFor($user);

    expect($pct)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(99);
});

it('ranks a more-complete user above a blank one in the same cohort', function () {
    $blank = User::factory()->create(['is_preview_user' => false]);
    $rich = User::factory()->create([
        'is_preview_user' => false,
        'date_of_birth' => '1980-01-01',
        'marital_status' => 'married',
        'employment_status' => 'employed',
        'annual_employment_income' => 60000,
        'monthly_expenditure' => 2000,
    ]);

    $svc = app(PlanningProgressService::class);

    expect($svc->scoreFor($rich))->toBeGreaterThan($svc->scoreFor($blank));
});

it('counts expenditure entered via ExpenditureProfile even when the raw column is null', function () {
    // No expenditure anywhere.
    $without = User::factory()->create([
        'is_preview_user' => false,
        'monthly_expenditure' => null,
    ]);

    // Expenditure only via the canonical ExpenditureProfile source.
    $withProfile = User::factory()->create([
        'is_preview_user' => false,
        'monthly_expenditure' => null,
    ]);
    ExpenditureProfile::factory()->create([
        'user_id' => $withProfile->id,
        'total_monthly_expenditure' => 1800,
    ]);

    $svc = app(PlanningProgressService::class);

    // The only difference between the two users is the ExpenditureProfile, so
    // the profile user must score strictly higher (the expenditure flag fires
    // via ResolvesExpenditure, not the null raw column).
    expect($svc->scoreFor($withProfile))->toBeGreaterThan($svc->scoreFor($without));
});
