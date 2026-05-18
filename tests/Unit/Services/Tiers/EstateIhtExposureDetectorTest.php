<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Tiers\EstateIhtExposureDetector;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('flags exposure when net estate exceeds NRB+RNRB and gives a one-line headline', function () {
    $u = User::factory()->create();
    $result = app(EstateIhtExposureDetector::class)->detect($u);

    expect($result)->toHaveKeys(['exposed', 'headline', 'estimated_liability_gbp'])
        ->and($result['exposed'])->toBeBool()
        ->and($result['headline'])->toBeString()
        ->and($result['estimated_liability_gbp'])->toBeFloat();
});

it('returns exposed=false when net worth is below the threshold', function () {
    $u = User::factory()->create();
    $result = app(EstateIhtExposureDetector::class)->detect($u);

    // New user has no assets — net worth is 0, well below NRB+RNRB
    expect($result['exposed'])->toBeFalse()
        ->and($result['estimated_liability_gbp'])->toEqual(0.0);
});

it('returns no score — only currency and a plain string headline', function () {
    $u = User::factory()->create();
    $result = app(EstateIhtExposureDetector::class)->detect($u);

    // Rule #13: no scores. Keys must be exactly these three — no 'score', no 'rating'
    expect(array_keys($result))->toEqual(['exposed', 'headline', 'estimated_liability_gbp']);
});
