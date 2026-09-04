<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\Tiers\EstateIhtExposureDetector;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * W-0534. The sentence about the CURRENT column excluding pensions was written
 * inside `IHTPlanning.vue` — a component behind the upgrade gate. So a free-tier
 * user saw an Inheritance Tax figure computed WITH that exclusion and could not
 * be told about it, which is the largest single adjustment to most estates.
 *
 * Every preview persona is free tier, so it was also the first thing a
 * prospective customer saw.
 */
it('publishes the sentence when a defined contribution pension is excluded', function () {
    $user = User::factory()->create();
    DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 250000,
    ]);

    $result = app(IHTCalculationService::class)->calculate($user->fresh());

    expect($result['pension_exclusion_caveat'])->toBeString()
        ->and($result['pension_exclusion_caveat'])->toContain('250,000')
        ->and($result['pension_exclusion_caveat'])->toContain('outside the estate for Inheritance Tax');
});

it('says nothing when there is nothing left out', function () {
    // No defined contribution pension, so no exclusion and no disclosure to make —
    // the same shape `unmodelled_relief_caveat` uses, which every surface handles.
    $user = User::factory()->create();

    expect(app(IHTCalculationService::class)->calculate($user)['pension_exclusion_caveat'])->toBeNull();
});

it('takes the reversal date from configuration, never from a component', function () {
    $user = User::factory()->create();
    DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 100000,
    ]);

    $caveat = app(IHTCalculationService::class)->calculate($user->fresh())['pension_exclusion_caveat'];
    $configured = app(\App\Services\TaxConfigService::class)
        ->getInheritanceTax()['pension_iht_inclusion']['effective_date'] ?? null;

    expect($configured)->not->toBeNull()
        ->and($caveat)->toContain(date('j F Y', (int) strtotime((string) $configured)));
});

it('reaches the free-tier teaser, which is the whole point', function () {
    $user = User::factory()->create();
    DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 400000,
    ]);

    $teaser = app(EstateIhtExposureDetector::class)->detect($user->fresh());

    expect($teaser)->toHaveKey('pension_exclusion_caveat')
        ->and($teaser['pension_exclusion_caveat'])->toContain('400,000');
});

it('leaves the sentence in no frontend bundle', function () {
    // The component that used to own it now renders the published string.
    $component = (string) file_get_contents(base_path('resources/js/components/Estate/IHTPlanning.vue'));

    expect($component)->toContain('pension_exclusion_caveat')
        ->and($component)->not->toContain('of pension savings is left out of the figures');
});
