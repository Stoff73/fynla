<?php

declare(strict_types=1);

use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('requires authentication', function () {
    $this->getJson('/api/tax-strategy')->assertStatus(401);
});

it('returns full payload for an authenticated single user', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single',
        'annual_employment_income' => 50000,
    ]);

    $response = $this->actingAs($user)->getJson('/api/tax-strategy');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'tax_year',
                'calculation_mode',
                'user_allowances',
                'spouse_allowances',
                'recommendations',
                'delta_vs_baseline',
            ],
        ]);
    expect($response->json('data.calculation_mode'))->toBe('single');
    expect($response->json('data.spouse_allowances'))->toBeNull();
    // Canonical: 6 base allowances. SRS tapered to £0 (£50k > £17,570 floor),
    // Marriage Allowance hidden for non-partnered users. See
    // TaxStrategyCalculatorTest 'Path A' for the same contract.
    expect($response->json('data.user_allowances'))->toHaveCount(6);
    expect($response->json('data.recommendations'))->toBe([]);
});

it('returns household-category recommendations for single_earner_couple users', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 100000,
        'marriage_allowance_eligible' => true,
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/tax-strategy');

    $recommendations = $response->json('data.recommendations');
    $household = collect($recommendations)->where('category', 'household')->values()->all();

    expect($recommendations)->not->toBe([])
        ->and($household)->not->toBe([]);

    foreach ($recommendations as $rec) {
        expect($rec)->toHaveKeys(['type', 'category', 'priority', 'title', 'description'])
            ->and($rec['category'])->toBeIn(['income_band', 'allowance', 'household', 'lifecycle', 'warning']);
    }
});

it('returns spouse_allowances for dual_earner users', function () {
    $user = User::factory()->create(['household_calculation_mode' => 'dual_earner']);
    TaxStrategyHouseholdInput::create([
        'user_id' => $user->id,
        'spouse_annual_income' => 45000,
        'spouse_psa_band' => 'basic',
    ]);

    $response = $this->actingAs($user)->getJson('/api/tax-strategy');

    expect($response->json('data.calculation_mode'))->toBe('dual_earner');
    expect($response->json('data.spouse_allowances'))->toHaveCount(8);
});

it('returns household-category recommendations under recommendations[] for single_earner_couple users', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 100000,
        'marriage_allowance_eligible' => true,
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/tax-strategy');

    expect($response->json('data.calculation_mode'))->toBe('single_earner_couple');
    expect($response->json('data'))->not->toHaveKey('asset_shifting_suggestions') // dropped Phase 2
        ->not->toHaveKey('cross_spouse_suggestions');

    $household = collect($response->json('data.recommendations'))->where('category', 'household')->values()->all();
    expect($household)->not->toBe([]);
});
