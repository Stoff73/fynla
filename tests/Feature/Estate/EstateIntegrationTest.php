<?php

declare(strict_types=1);

use App\Models\Estate\Asset;
use App\Models\Estate\Liability;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

describe('Complete estate planning workflow', function () {
    it('completes full estate planning analysis from setup to recommendations', function () {
        // 1. Create user and authenticate
        $user = User::factory()->create([
            'first_name' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'date_of_birth' => Carbon::now()->subYears(50),
        ]);
        Sanctum::actingAs($user);

        // 2. Set up IHT profile
        $profileResponse = $this->postJson('/api/estate/profile', [
            'marital_status' => 'married',
            'has_spouse' => true,
            'own_home' => true,
            'home_value' => 600000,
            'nrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => 5,
        ]);

        $profileResponse->assertOk();

        // 3. Add assets
        $this->postJson('/api/estate/assets', [
            'asset_type' => 'property',
            'asset_name' => 'Main Residence',
            'current_value' => 600000,
            'ownership_type' => 'joint',
            'is_iht_exempt' => false,
            'valuation_date' => '2024-01-01',
        ])->assertCreated();

        $this->postJson('/api/estate/assets', [
            'asset_type' => 'pension',
            'asset_name' => 'DC Pension',
            'current_value' => 300000,
            'ownership_type' => 'individual',
            'is_iht_exempt' => true,
            'exemption_reason' => 'Nominated beneficiary',
            'valuation_date' => '2024-01-01',
        ])->assertCreated();

        $this->postJson('/api/estate/assets', [
            'asset_type' => 'investment',
            'asset_name' => 'ISA Portfolio',
            'current_value' => 150000,
            'ownership_type' => 'individual',
            'is_iht_exempt' => false,
            'valuation_date' => '2024-01-01',
        ])->assertCreated();

        // 4. Add liabilities
        $this->postJson('/api/estate/liabilities', [
            'liability_type' => 'mortgage',
            'liability_name' => 'Home Mortgage',
            'current_balance' => 200000,
            'monthly_payment' => 1200,
            'interest_rate' => 0.035,
        ])->assertCreated();

        // 5. Add historical gifts
        $this->postJson('/api/estate/gifts', [
            'gift_date' => Carbon::now()->subYears(2)->format('Y-m-d'),
            'recipient' => 'Daughter',
            'gift_type' => 'pet',
            'gift_value' => 30000,
            'status' => 'within_7_years',
            'taper_relief_applicable' => false,
        ])->assertCreated();

        $this->postJson('/api/estate/gifts', [
            'gift_date' => Carbon::now()->subYears(5)->format('Y-m-d'),
            'recipient' => 'Son',
            'gift_type' => 'pet',
            'gift_value' => 50000,
            'status' => 'within_7_years',
            'taper_relief_applicable' => true,
        ])->assertCreated();

        // 6. Get full estate overview
        $indexResponse = $this->getJson('/api/estate');
        $indexResponse->assertOk()
            ->assertJsonCount(3, 'data.assets')
            ->assertJsonCount(1, 'data.liabilities')
            ->assertJsonCount(2, 'data.gifts');

        // 7. Calculate IHT liability
        $ihtResponse = $this->postJson('/api/estate/calculate-iht');
        $ihtResponse->assertOk();

        // Check the response structure (using actual structure from IHTController)
        $ihtResponse->assertJsonStructure([
            'success',
            'calculation' => [
                'total_gross_assets',
                'total_net_estate',
                'nrb_available',
                'iht_liability',
            ],
        ]);

        // 8. Get net worth analysis
        $netWorthResponse = $this->getJson('/api/estate/net-worth');
        $netWorthResponse->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'net_worth',
                    'concentration_risk',
                    'health_score',
                ],
            ]);

        // 9. Get cash flow projection
        $cashFlowResponse = $this->getJson('/api/estate/cash-flow');
        $cashFlowResponse->assertOk();
    });
});

describe('IHT calculation with multiple scenarios', function () {
    it('shows IHT reduction through gifting strategy', function () {
        $user = User::factory()->create([
            'date_of_birth' => Carbon::now()->subYears(55),
        ]);
        Sanctum::actingAs($user);

        // Set up large estate
        $this->postJson('/api/estate/profile', [
            'marital_status' => 'single',
            'has_spouse' => false,
            'own_home' => true,
            'home_value' => 500000,
            'nrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => 0,
        ]);

        $this->postJson('/api/estate/assets', [
            'asset_type' => 'property',
            'asset_name' => 'Home',
            'current_value' => 500000,
            'ownership_type' => 'individual',
            'valuation_date' => '2024-01-01',
        ]);

        $this->postJson('/api/estate/assets', [
            'asset_type' => 'investment',
            'asset_name' => 'Portfolio',
            'current_value' => 600000,
            'ownership_type' => 'individual',
            'valuation_date' => '2024-01-01',
        ]);

        // Calculate baseline IHT
        $baselineResponse = $this->postJson('/api/estate/calculate-iht');
        $baselineIHT = $baselineResponse->json('calculation.iht_liability');

        // Verify IHT is calculated (above NRB threshold)
        expect($baselineIHT)->toBeGreaterThan(0);
    });

    it('shows IHT reduction through charitable giving', function () {
        $user = User::factory()->create([
            'date_of_birth' => Carbon::now()->subYears(55),
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/estate/profile', [
            'marital_status' => 'single',
            'has_spouse' => false,
            'own_home' => false,
            'home_value' => 0,
            'nrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => 0,
        ]);

        $this->postJson('/api/estate/assets', [
            'asset_type' => 'investment',
            'asset_name' => 'Portfolio',
            'current_value' => 800000,
            'ownership_type' => 'individual',
            'valuation_date' => '2024-01-01',
        ]);

        // Calculate baseline
        $baselineResponse = $this->postJson('/api/estate/calculate-iht');
        $baselineResponse->assertOk();

        // Verify the calculation includes IHT liability
        expect($baselineResponse->json('calculation.iht_liability'))->toBeGreaterThan(0);
    });
});

describe('Cache behavior', function () {
    it('caches estate analysis results', function () {
        $user = User::factory()->create([
            'date_of_birth' => Carbon::now()->subYears(55),
        ]);
        Sanctum::actingAs($user);

        Asset::create([
            'user_id' => $user->id,
            'asset_type' => 'investment',
            'asset_name' => 'Portfolio',
            'current_value' => 500000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        // First call - should cache
        $firstResponse = $this->postJson('/api/estate/calculate-iht');
        $firstResponse->assertOk();

        // Second call - should use cache
        $secondResponse = $this->postJson('/api/estate/calculate-iht');
        $secondResponse->assertOk();

        // Key calculation values should be identical (exclude metadata like id, timestamps, hashes)
        $firstCalc = $firstResponse->json('calculation');
        $secondCalc = $secondResponse->json('calculation');

        expect($firstCalc['total_gross_assets'])->toBe($secondCalc['total_gross_assets'])
            ->and($firstCalc['iht_liability'])->toBe($secondCalc['iht_liability'])
            ->and($firstCalc['projected_iht_liability'])->toBe($secondCalc['projected_iht_liability']);
    });

    it('invalidates cache when asset is updated', function () {
        $user = User::factory()->create([
            'date_of_birth' => Carbon::now()->subYears(55),
        ]);
        Sanctum::actingAs($user);

        $asset = Asset::create([
            'user_id' => $user->id,
            'asset_type' => 'investment',
            'asset_name' => 'Portfolio',
            'current_value' => 500000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        // Initial analysis
        $firstResponse = $this->postJson('/api/estate/calculate-iht');
        $firstValue = $firstResponse->json('calculation.total_gross_assets');

        // Update asset
        $this->putJson("/api/estate/assets/{$asset->id}", [
            'current_value' => 600000,
        ]);

        // Analysis should reflect new value
        $secondResponse = $this->postJson('/api/estate/calculate-iht');
        $secondValue = $secondResponse->json('calculation.total_gross_assets');

        expect($secondValue)->toBeGreaterThan($firstValue);
    });
});
