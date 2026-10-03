<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\RecommendationTracking;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserGamification;
use App\Services\UserProfile\UserProfileService;

it('backfills data + completed recommendations quietly and idempotently', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    SavingsAccount::factory()->create(['user_id' => $user->id]);
    RecommendationTracking::create([
        'user_id' => $user->id, 'recommendation_id' => 'r1', 'module' => 'savings',
        'recommendation_text' => 'x', 'priority_score' => 50, 'timeline' => 'short_term',
        'status' => 'completed', 'completed_at' => now(),
    ]);

    // Simulate a pre-launch user: wipe what the live hooks already awarded.
    UserGamification::where('user_id', $user->id)->delete();
    PointAward::where('user_id', $user->id)->delete();

    $this->artisan('gamification:backfill')->assertExitCode(0);

    $g = UserGamification::where('user_id', $user->id)->first();
    // savings first-in-category (20) + recommendation (25) = 45
    expect($g->total_points)->toBe(45);
    // Quiet: the earned level is marked as already celebrated, so a
    // backfilled user has no climb to replay on the dashboard.
    expect($g->celebrated_level)->toBe($g->level);

    // Re-run awards nothing more.
    $this->artisan('gamification:backfill')->assertExitCode(0);
    expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(45);
});

it('skips preview users entirely', function () {
    $preview = User::factory()->create(['is_preview_user' => true]);
    SavingsAccount::factory()->create(['user_id' => $preview->id]);
    UserGamification::where('user_id', $preview->id)->delete();

    $this->artisan('gamification:backfill')->assertExitCode(0);

    expect(UserGamification::where('user_id', $preview->id)->exists())->toBeFalse();
});

// TODO item 7a (2026-10-03): interest worked out from a savings account is not
// income the user recorded, so it neither earns the first-income award nor
// stops it firing when they then enter their pay.
it('gives the first-income award when pay is entered, not for interest worked out from savings', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'annual_employment_income' => null, 'annual_self_employment_income' => null, 'annual_dividend_income' => null, 'annual_interest_income' => null, 'annual_other_income' => null, 'annual_trust_income' => null, 'annual_rental_income' => null]);
    SavingsAccount::factory()->create(['user_id' => $user->id, 'is_isa' => false, 'current_balance' => 20000, 'interest_rate' => 4.0]);
    $profiles = app(UserProfileService::class);

    expect($profiles->totalGrossAnnualIncome($user->fresh()))->toBe(0.0);

    $profiles->updateIncomeOccupation($user->fresh(), ['annual_employment_income' => 40000]);

    expect(PointAward::where('user_id', $user->id)->where('dedup_key', 'data:income:first')->exists())->toBeTrue();
});
