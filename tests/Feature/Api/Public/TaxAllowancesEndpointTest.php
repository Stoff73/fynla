<?php

declare(strict_types=1);

use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * Public tax allowances endpoint — backs the /savetax landing page
 * (and any future campaign page) with live values from the active
 * TaxConfiguration row. Must work without authentication.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    Cache::flush();
});

it('returns the tax-allowances payload without auth', function () {
    $response = $this->getJson('/api/public/tax-allowances');

    $response->assertOk()
        ->assertJsonStructure([
            'tax_year',
            'income_allowances' => [
                ['key', 'label', 'note', 'amount'],
            ],
            'investment_allowances' => [
                ['key', 'label', 'note', 'amount'],
            ],
            'thresholds' => [
                'hicbc_threshold',
                'starting_rate_for_savings_income_limit',
            ],
        ]);
});

it('returns the four canonical income allowances with current 2026/27 values', function () {
    $response = $this->getJson('/api/public/tax-allowances');
    $payload = $response->json();

    expect($payload['tax_year'])->toBe('2026/27');

    $income = collect($payload['income_allowances'])->keyBy('key');
    expect($income)->toHaveKey('personal_allowance')
        ->and($income['personal_allowance']['amount'])->toBe(12570)
        ->and($income['savings_allowance']['amount'])->toBe(1000)
        ->and($income['starting_rate_for_savings']['amount'])->toBe(5000)
        ->and($income['marriage_allowance']['amount'])->toBe(1260);
});

it('returns the four canonical investment allowances with current 2026/27 values', function () {
    $response = $this->getJson('/api/public/tax-allowances');
    $investment = collect($response->json('investment_allowances'))->keyBy('key');

    expect($investment['isa_allowance']['amount'])->toBe(20000)
        ->and($investment['cgt_allowance']['amount'])->toBe(3000)
        ->and($investment['dividend_allowance']['amount'])->toBe(500)
        ->and($investment['pension_annual_allowance']['amount'])->toBe(60000);
});

it('returns the HICBC threshold and starting-rate-for-savings income limit', function () {
    $response = $this->getJson('/api/public/tax-allowances');
    $thresholds = $response->json('thresholds');

    // HICBC personal allowance taper kicks in at £100k
    expect($thresholds['hicbc_threshold'])->toBe(100000);
    // Starting rate for savings 0% band tapers out at PA + SRS band = £17,570
    expect($thresholds['starting_rate_for_savings_income_limit'])->toBe(17570);
});

it('does not require an authenticated user', function () {
    // No actingAs / no token — must still 200.
    $response = $this->getJson('/api/public/tax-allowances');

    $response->assertOk();
    expect($response->headers->get('WWW-Authenticate'))->toBeNull();
});

it('caches the payload across calls within the same tax year', function () {
    $this->getJson('/api/public/tax-allowances')->assertOk();
    expect(Cache::has('public.tax-allowances.2026/27'))->toBeTrue();
});
