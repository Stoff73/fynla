<?php

declare(strict_types=1);

use App\Models\Estate\Gift;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\AvailableNrbCalculator;
use App\Services\TaxConfigService;

beforeEach(function () {
    // Ensure active tax configuration exists — mirrors the pattern used in
    // GiftingStrategyTest and PersonalizedTrustStrategyServiceTest.
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }
});

it('reduces available nil-rate band by non-exempt gifts made in the last 7 years', function () {
    $user = User::factory()->create();
    $nrb = (float) app(TaxConfigService::class)->getInheritanceTax()['nil_rate_band'];

    // In-window PET (pet = potentially exempt transfer — erodes NRB)
    Gift::factory()->create([
        'user_id' => $user->id,
        'gift_value' => 100000,
        'gift_date' => now()->subYears(3),
        'gift_type' => 'pet',
        'status' => 'within_7_years',
    ]);

    // Outside window — should be ignored
    Gift::factory()->create([
        'user_id' => $user->id,
        'gift_value' => 50000,
        'gift_date' => now()->subYears(8),
        'gift_type' => 'pet',
        'status' => 'survived_7_years',
    ]);

    expect(app(AvailableNrbCalculator::class)->forUser($user->fresh()))->toBe($nrb - 100000.0);
});

it('never returns below zero', function () {
    $user = User::factory()->create();

    Gift::factory()->create([
        'user_id' => $user->id,
        'gift_value' => 999999,
        'gift_date' => now()->subYear(),
        'gift_type' => 'pet',
        'status' => 'within_7_years',
    ]);

    expect(app(AvailableNrbCalculator::class)->forUser($user->fresh()))->toBe(0.0);
});

it('ignores exempt gift types within the 7-year window', function () {
    $user = User::factory()->create();
    $nrb = (float) app(TaxConfigService::class)->getInheritanceTax()['nil_rate_band'];

    // Exempt types per DB enum: annual_exemption, small_gift, exempt
    Gift::factory()->create([
        'user_id' => $user->id,
        'gift_value' => 3000,
        'gift_date' => now()->subMonths(6),
        'gift_type' => 'annual_exemption',
        'status' => 'within_7_years',
    ]);

    Gift::factory()->create([
        'user_id' => $user->id,
        'gift_value' => 250,
        'gift_date' => now()->subMonths(6),
        'gift_type' => 'small_gift',
        'status' => 'within_7_years',
    ]);

    Gift::factory()->create([
        'user_id' => $user->id,
        'gift_value' => 10000,
        'gift_date' => now()->subMonths(6),
        'gift_type' => 'exempt',
        'status' => 'within_7_years',
    ]);

    // Full NRB — exempt gifts do not reduce it
    expect(app(AvailableNrbCalculator::class)->forUser($user->fresh()))->toBe($nrb);
});

it('includes chargeable lifetime transfers in the cumulation window', function () {
    $user = User::factory()->create();
    $nrb = (float) app(TaxConfigService::class)->getInheritanceTax()['nil_rate_band'];

    // CLTs (chargeable lifetime transfers) erode the NRB just like PETs
    Gift::factory()->create([
        'user_id' => $user->id,
        'gift_value' => 50000,
        'gift_date' => now()->subYear(),
        'gift_type' => 'clt',
        'status' => 'within_7_years',
    ]);

    Gift::factory()->create([
        'user_id' => $user->id,
        'gift_value' => 25000,
        'gift_date' => now()->subYears(2),
        'gift_type' => 'pet',
        'status' => 'within_7_years',
    ]);

    expect(app(AvailableNrbCalculator::class)->forUser($user->fresh()))->toBe($nrb - 75000.0);
});
