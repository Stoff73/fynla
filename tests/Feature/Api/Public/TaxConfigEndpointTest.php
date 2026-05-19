<?php

declare(strict_types=1);

use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * /api/public/tax-config — unauthenticated counterpart of /api/tax/config.
 *
 * Same payload shape (built by `TaxConfigSnapshotService`), no auth gate.
 * PublicLayout dispatches it on mount so unauthenticated pages
 * (CalculatorsPage etc.) hydrate the shared `taxConfig` Vuex store and
 * stop relying on the hardcoded constants in
 * `resources/js/constants/taxConfig.js`.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('serves the snapshot without authentication', function () {
    $this->getJson('/api/public/tax-config')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'tax_year',
                'isa' => ['annual_allowance'],
                'pension' => ['annual_allowance'],
                'income_tax' => ['personal_allowance'],
                'capital_gains_tax' => ['annual_allowance'],
                'inheritance_tax' => ['nil_rate_band'],
                'stamp_duty' => [
                    'england' => ['standard_bands', 'ftb_bands', 'ftb_max_price', 'additional_surcharge', 'non_uk_surcharge'],
                    'scotland' => ['lbtt_bands', 'lbtt_additional_surcharge', 'lbtt_non_uk_surcharge'],
                    'wales' => ['ltt_bands', 'ltt_additional_surcharge', 'ltt_non_uk_surcharge'],
                ],
                'student_loan' => ['repayment_rate'],
            ],
        ]);
});

it('returns the same payload as the authenticated endpoint', function () {
    $publicData = $this->getJson('/api/public/tax-config')->json('data');

    \Laravel\Sanctum\Sanctum::actingAs(\App\Models\User::factory()->create());
    $authData = $this->getJson('/api/tax/config')->json('data');

    expect($publicData)->toEqual($authData);
});

it('returns the seeded 2026/27 active tax year', function () {
    $data = $this->getJson('/api/public/tax-config')->json('data');

    expect($data['tax_year'])->toBe('2026/27')
        ->and($data['isa']['annual_allowance'])->toBe(20000)
        ->and($data['stamp_duty']['england']['ftb_max_price'])->toBe(500000)
        ->and($data['stamp_duty']['scotland']['lbtt_additional_surcharge'])->toBe(8)
        ->and($data['stamp_duty']['wales']['ltt_additional_surcharge'])->toBe(5)
        ->and($data['student_loan']['repayment_rate'])->toBe(0.09);
});
