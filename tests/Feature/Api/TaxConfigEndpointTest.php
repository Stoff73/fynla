<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * /api/tax/config — full snapshot the frontend taxConfig Vuex store hydrates
 * from on app boot, so components no longer have to import hardcoded constants
 * from `resources/js/constants/taxConfig.js`.
 *
 * Authoritative values come from the active TaxConfiguration row via
 * TaxConfigService. The 2026/27 expectations below are exactly the numbers
 * seeded by TaxConfigurationSeeder for the active tax year — if any of these
 * tests fail because the seed values shifted, fix the seeder OR update the
 * test, but DO NOT introduce a hardcoded controller default to mask the gap.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/tax/config')->assertUnauthorized();
});

it('returns the full payload structure for an authenticated user', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/tax/config')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'tax_year',
                'effective_from',
                'effective_to',
                'isa' => ['annual_allowance', 'lifetime_isa_allowance', 'junior_isa_allowance'],
                'pension' => [
                    'annual_allowance',
                    'money_purchase_annual_allowance',
                    'lifetime_allowance',
                    'taper_threshold_income',
                    'taper_adjusted_income',
                    'tax_free_cash_rate',
                    'tax_free_lump_sum_limit',
                    'state_pension_annual',
                    'state_pension_weekly',
                ],
                'income_tax' => [
                    'personal_allowance',
                    'personal_allowance_taper_threshold',
                    'higher_rate_threshold',
                    'additional_rate_threshold',
                    'basic_rate',
                    'higher_rate',
                    'additional_rate',
                    'savings_allowance_basic',
                    'savings_allowance_higher',
                    'marriage_allowance',
                ],
                'national_insurance' => [
                    'primary_threshold',
                    'upper_earnings_limit',
                    'basic_rate',
                    'additional_rate',
                ],
                'capital_gains_tax' => [
                    'annual_allowance',
                    'basic_rate',
                    'higher_rate',
                    'badr_rate',
                    'badr_lifetime_limit',
                ],
                'inheritance_tax' => [
                    'nil_rate_band',
                    'residence_nil_rate_band',
                    'rnrb_taper_threshold',
                    'standard_rate',
                    'reduced_rate',
                ],
                'dividend_tax' => ['allowance', 'basic_rate', 'higher_rate', 'additional_rate'],
                'gifting_exemptions' => ['annual_exemption', 'small_gift_exemption'],
                'other' => ['hicbc_threshold', 'ssp_weekly_rate'],
            ],
        ]);
});

it('returns the seeded 2026/27 ISA, pension and income tax values', function () {
    Sanctum::actingAs(User::factory()->create());

    $data = $this->getJson('/api/tax/config')->json('data');

    expect($data['tax_year'])->toBe('2026/27');

    expect($data['isa']['annual_allowance'])->toBe(20000)
        ->and($data['isa']['lifetime_isa_allowance'])->toBe(4000)
        ->and($data['isa']['junior_isa_allowance'])->toBe(9000);

    expect($data['pension']['annual_allowance'])->toBe(60000)
        ->and($data['pension']['money_purchase_annual_allowance'])->toBe(10000)
        ->and($data['pension']['lifetime_allowance'])->toBeNull()
        ->and($data['pension']['taper_threshold_income'])->toBe(200000)
        ->and($data['pension']['taper_adjusted_income'])->toBe(260000)
        ->and($data['pension']['tax_free_cash_rate'])->toBe(0.25)
        ->and($data['pension']['tax_free_lump_sum_limit'])->toBe(268275);

    expect($data['income_tax']['personal_allowance'])->toBe(12570)
        ->and($data['income_tax']['higher_rate_threshold'])->toBe(50270)
        ->and($data['income_tax']['additional_rate_threshold'])->toBe(125140)
        ->and($data['income_tax']['basic_rate'])->toBe(0.20)
        ->and($data['income_tax']['higher_rate'])->toBe(0.40)
        ->and($data['income_tax']['additional_rate'])->toBe(0.45)
        ->and($data['income_tax']['savings_allowance_basic'])->toBe(1000)
        ->and($data['income_tax']['savings_allowance_higher'])->toBe(500)
        ->and($data['income_tax']['marriage_allowance'])->toBe(1260);
});

it('returns the seeded 2026/27 CGT, IHT and dividend values', function () {
    Sanctum::actingAs(User::factory()->create());

    $data = $this->getJson('/api/tax/config')->json('data');

    expect($data['capital_gains_tax']['annual_allowance'])->toBe(3000)
        ->and($data['capital_gains_tax']['basic_rate'])->toBe(0.18)
        ->and($data['capital_gains_tax']['higher_rate'])->toBe(0.24)
        ->and($data['capital_gains_tax']['badr_lifetime_limit'])->toBe(1000000);

    expect($data['inheritance_tax']['nil_rate_band'])->toBe(325000)
        ->and($data['inheritance_tax']['residence_nil_rate_band'])->toBe(175000)
        ->and($data['inheritance_tax']['rnrb_taper_threshold'])->toBe(2000000)
        ->and($data['inheritance_tax']['standard_rate'])->toBe(0.40)
        ->and($data['inheritance_tax']['reduced_rate'])->toBe(0.36);

    expect($data['dividend_tax']['allowance'])->toBe(500);
});

it('returns gifting exemptions and HICBC threshold from the seeder', function () {
    Sanctum::actingAs(User::factory()->create());

    $data = $this->getJson('/api/tax/config')->json('data');

    expect($data['gifting_exemptions']['annual_exemption'])->toBe(3000)
        ->and($data['gifting_exemptions']['small_gift_exemption'])->toBe(250);

    expect($data['other']['hicbc_threshold'])->toBe(60000);
});

it('derives weekly state pension from the seeded annual figure', function () {
    Sanctum::actingAs(User::factory()->create());

    $data = $this->getJson('/api/tax/config')->json('data');

    // 2026/27 seeded value: full_new_state_pension = 12547.60 → weekly ≈ 241.30
    expect($data['pension']['state_pension_annual'])->toBe(12547.6)
        ->and($data['pension']['state_pension_weekly'])->toBe(241.30);
});

it('reflects edits to the active config across requests', function () {
    Sanctum::actingAs(User::factory()->create());

    expect($this->getJson('/api/tax/config')->json('data.isa.annual_allowance'))
        ->toBe(20000);

    \App\Models\TaxConfiguration::query()
        ->where('is_active', true)
        ->update([
            'config_data' => array_replace_recursive(
                \App\Models\TaxConfiguration::query()->where('is_active', true)->first()->config_data,
                ['isa' => ['annual_allowance' => 22000]],
            ),
        ]);

    // Real requests get a fresh app container, but feature tests reuse it.
    // Drop the request-scoped cache to simulate the cross-request boundary —
    // proves the controller has no extra layer of caching of its own.
    app(\App\Services\TaxConfigService::class)->clearCache();

    expect($this->getJson('/api/tax/config')->json('data.isa.annual_allowance'))
        ->toBe(22000);
});
