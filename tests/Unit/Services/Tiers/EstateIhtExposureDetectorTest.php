<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\TaxConfigService;
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

it('does not hand the residence allowance to someone with no residence', function () {
    // W-0464. This test used to assert `exposed=false` for £500,000 of savings,
    // on the reasoning that £500,000 equals the nil rate band plus the residence
    // nil rate band. **It encoded the defect.** The residence band requires a main
    // residence passing to direct descendants (IHTA 1984 s8E–s8H) and this user has
    // neither, so their allowance is £325,000 and £175,000 of the estate is taxable.
    //
    // The old detector granted the band to everyone by adding it into a threshold,
    // which is exactly the kind of second, simpler model CSJ's "/m must never work
    // anything out" rule removes.
    $ihtConfig = app(TaxConfigService::class)->getInheritanceTax();
    $nrb = (float) ($ihtConfig['nil_rate_band'] ?? 325000);
    $rnrb = (float) ($ihtConfig['residence_nil_rate_band'] ?? 175000);

    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => $nrb + $rnrb,
    ]);

    $result = app(EstateIhtExposureDetector::class)->detect($user);

    $expectedRate = (float) ($ihtConfig['standard_rate'] ?? 0.40);

    expect($result['exposed'])->toBeTrue()
        ->and($result['estimated_liability_gbp'])->toEqualWithDelta($rnrb * $expectedRate, 0.01);
});

it('reports no exposure when the allowances actually cover the estate', function () {
    $ihtConfig = app(TaxConfigService::class)->getInheritanceTax();
    $nrb = (float) ($ihtConfig['nil_rate_band'] ?? 325000);

    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => $nrb,
    ]);

    $result = app(EstateIhtExposureDetector::class)->detect($user);

    expect($result['exposed'])->toBeFalse()
        ->and($result['estimated_liability_gbp'])->toEqual(0.0);
});

it('returns exposed=true when net worth is one pound above NRB+RNRB threshold', function () {
    $ihtConfig = app(TaxConfigService::class)->getInheritanceTax();
    $threshold = (float) ($ihtConfig['nil_rate_band'] ?? 325000) + (float) ($ihtConfig['residence_nil_rate_band'] ?? 175000);

    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => $threshold + 1.0, // just above: £500,001 (or current threshold + 1)
    ]);

    $result = app(EstateIhtExposureDetector::class)->detect($user);

    expect($result['exposed'])->toBeTrue()
        ->and($result['estimated_liability_gbp'])->toBeGreaterThan(0.0);
});
