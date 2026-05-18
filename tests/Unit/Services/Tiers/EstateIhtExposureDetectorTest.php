<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Tiers\EstateIhtExposureDetector;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('flags exposure and returns positive liability when net worth exceeds NRB+RNRB', function () {
    // NRB (325k) + RNRB (175k) = 500k threshold. Create a savings balance well above it.
    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 700_000.00, // £700k — well above £500k threshold
    ]);

    $result = app(EstateIhtExposureDetector::class)->detect($user);

    expect($result['exposed'])->toBeTrue()
        ->and($result['estimated_liability_gbp'])->toBeGreaterThan(0.0)
        ->and($result['headline'])->toBeString()->not->toBeEmpty()
        // Rule #10: user-facing text must spell out "Inheritance Tax"
        ->and($result['headline'])->toContain('Inheritance Tax');
});

it('returns exposed=false when net worth is below the threshold', function () {
    // New user has no assets — net worth is 0, well below NRB+RNRB (£500k).
    $u = User::factory()->create();
    $result = app(EstateIhtExposureDetector::class)->detect($u);

    expect($result['exposed'])->toBeFalse()
        ->and($result['estimated_liability_gbp'])->toEqual(0.0);
});

it('returns no score — only currency and a plain string headline', function () {
    $u = User::factory()->create();
    $result = app(EstateIhtExposureDetector::class)->detect($u);

    // Rule #13: no scores. Keys must be exactly these three — no 'score', no 'rating'
    expect(array_keys($result))->toEqual(['exposed', 'headline', 'estimated_liability_gbp']);
});
