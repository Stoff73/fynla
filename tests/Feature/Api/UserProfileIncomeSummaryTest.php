<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * The /m Income screen reads getCompleteProfile()'s `income_summary` block —
 * a flat per-source breakdown for the user (and spouse when linked).
 */
it('returns a flat income_summary block for the user', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 80000,
        'annual_self_employment_income' => 5000,
        'annual_dividend_income' => 1000,
        'annual_interest_income' => 0,
        'annual_other_income' => 0,
    ]);

    $response = $this->actingAs($user)->getJson('/api/user/profile');

    $response->assertOk();
    $summary = $response->json('data.income_summary');

    // JSON round-trips whole floats as ints (80000.0 → "80000" → 80000), so use
    // loose equality on the numeric fields.
    expect($summary)->toHaveKeys(['user', 'spouse'])
        ->and($summary['user']['employment'])->toEqual(80000)
        ->and($summary['user']['self_employment'])->toEqual(5000)
        ->and($summary['user']['dividend'])->toEqual(1000)
        ->and($summary['user']['total'])->toEqual(86000)
        ->and($summary['spouse'])->toBeNull();
});
