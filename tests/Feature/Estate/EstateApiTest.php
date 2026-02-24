<?php

declare(strict_types=1);

use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\IHTProfile;
use App\Models\Estate\Liability;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('GET /api/estate', function () {
    it('returns all estate data for authenticated user', function () {
        Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'property',
            'asset_name' => 'Home',
            'current_value' => 500000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        Liability::create([
            'user_id' => $this->user->id,
            'liability_type' => 'mortgage',
            'liability_name' => 'Home Mortgage',
            'current_balance' => 200000,
        ]);

        $response = $this->getJson('/api/estate');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'assets',
                    'liabilities',
                    'gifts',
                    'iht_profile',
                ],
            ]);
    });

    it('requires authentication', function () {
        // Use a fresh app instance without auth from beforeEach
        $this->app = $this->createApplication();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/estate');

        $response->assertUnauthorized();
    });
});

describe('GET /api/estate/trust-recommendations', function () {
    it('returns personalized trust recommendations', function () {
        Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'property',
            'asset_name' => 'Home',
            'current_value' => 600000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        $this->user->update(['date_of_birth' => Carbon::now()->subYears(50)]);

        $response = $this->getJson('/api/estate/trust-recommendations');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    });
});

describe('POST /api/estate/calculate-iht', function () {
    it('calculates IHT liability', function () {
        Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'property',
            'asset_name' => 'Home',
            'current_value' => 600000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        // Set user date_of_birth for life expectancy calculation
        $this->user->update(['date_of_birth' => Carbon::now()->subYears(50)]);

        $response = $this->postJson('/api/estate/calculate-iht');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'calculation' => [
                    'total_gross_assets',
                    'total_net_estate',
                    'nrb_available',
                    'rnrb_available',
                    'taxable_estate',
                    'iht_liability',
                ],
                'iht_summary' => [
                    'current',
                    'projected',
                ],
            ]);
    });

    it('handles missing user date_of_birth gracefully', function () {
        // Even without date_of_birth, should still calculate
        Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'property',
            'asset_name' => 'Home',
            'current_value' => 600000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        $response = $this->postJson('/api/estate/calculate-iht');

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);
    });
});

describe('GET /api/estate/net-worth', function () {
    it('returns net worth analysis', function () {
        Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'property',
            'asset_name' => 'Home',
            'current_value' => 500000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        $response = $this->getJson('/api/estate/net-worth');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'net_worth',
                    'concentration_risk',
                    'health_score',
                ],
            ]);
    });
});

describe('GET /api/estate/cash-flow', function () {
    it('returns cash flow projection', function () {
        $response = $this->getJson('/api/estate/cash-flow');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    });
});

describe('Asset CRUD operations', function () {
    it('creates a new asset', function () {
        $response = $this->postJson('/api/estate/assets', [
            'asset_type' => 'property',
            'asset_name' => 'Investment Property',
            'current_value' => 350000,
            'ownership_type' => 'individual',
            'is_iht_exempt' => false,
            'valuation_date' => '2024-01-01',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['success' => true])
            ->assertJsonPath('data.asset_name', 'Investment Property');
    });

    it('validates required fields when creating asset', function () {
        $response = $this->postJson('/api/estate/assets', [
            'asset_type' => 'property',
            // Missing required fields
        ]);

        $response->assertUnprocessable();
    });

    it('updates an asset', function () {
        $asset = Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'property',
            'asset_name' => 'Home',
            'current_value' => 500000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        $response = $this->putJson("/api/estate/assets/{$asset->id}", [
            'current_value' => 550000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_value', 550000);
    });

    it('prevents updating another users asset', function () {
        $otherUser = User::factory()->create();
        $asset = Asset::create([
            'user_id' => $otherUser->id,
            'asset_type' => 'property',
            'asset_name' => 'Home',
            'current_value' => 500000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        $response = $this->putJson("/api/estate/assets/{$asset->id}", [
            'current_value' => 550000,
        ]);

        $response->assertNotFound();
    });

    it('deletes an asset', function () {
        $asset = Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'property',
            'asset_name' => 'Home',
            'current_value' => 500000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        $response = $this->deleteJson("/api/estate/assets/{$asset->id}");

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    });
});

describe('Liability CRUD operations', function () {
    it('creates a new liability', function () {
        $response = $this->postJson('/api/estate/liabilities', [
            'liability_type' => 'mortgage',
            'liability_name' => 'Home Mortgage',
            'current_balance' => 200000,
            'monthly_payment' => 1000,
            'interest_rate' => 0.035,
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['success' => true])
            ->assertJsonPath('data.liability_name', 'Home Mortgage');
    });

    it('updates a liability', function () {
        $liability = Liability::create([
            'user_id' => $this->user->id,
            'liability_type' => 'mortgage',
            'liability_name' => 'Mortgage',
            'current_balance' => 200000,
        ]);

        $response = $this->putJson("/api/estate/liabilities/{$liability->id}", [
            'current_balance' => 190000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_balance', 190000);
    });

    it('deletes a liability', function () {
        $liability = Liability::create([
            'user_id' => $this->user->id,
            'liability_type' => 'loan',
            'liability_name' => 'Car Loan',
            'current_balance' => 15000,
        ]);

        $response = $this->deleteJson("/api/estate/liabilities/{$liability->id}");

        $response->assertOk();
        $this->assertSoftDeleted('liabilities', ['id' => $liability->id]);
    });
});

describe('Gift CRUD operations', function () {
    it('creates a new gift', function () {
        $response = $this->postJson('/api/estate/gifts', [
            'gift_date' => '2024-01-15',
            'recipient' => 'Child',
            'gift_type' => 'pet',
            'gift_value' => 50000,
            'status' => 'within_7_years',
            'taper_relief_applicable' => false,
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['success' => true])
            ->assertJsonPath('data.recipient', 'Child');
    });

    it('updates a gift', function () {
        $gift = Gift::create([
            'user_id' => $this->user->id,
            'gift_date' => Carbon::now()->subYears(3),
            'recipient' => 'Child',
            'gift_type' => 'pet',
            'gift_value' => 50000,
        ]);

        $response = $this->putJson("/api/estate/gifts/{$gift->id}", [
            'gift_value' => 55000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.gift_value', 55000);
    });

    it('deletes a gift', function () {
        $gift = Gift::create([
            'user_id' => $this->user->id,
            'gift_date' => Carbon::now(),
            'recipient' => 'Friend',
            'gift_type' => 'small_gift',
            'gift_value' => 250,
        ]);

        $response = $this->deleteJson("/api/estate/gifts/{$gift->id}");

        $response->assertOk();
        $this->assertSoftDeleted('gifts', ['id' => $gift->id]);
    });
});

describe('POST /api/estate/profile', function () {
    it('creates IHT profile', function () {
        $response = $this->postJson('/api/estate/profile', [
            'marital_status' => 'married',
            'has_spouse' => true,
            'own_home' => true,
            'home_value' => 500000,
            'nrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => 10,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonPath('data.marital_status', 'married');
    });

    it('updates existing IHT profile', function () {
        IHTProfile::create([
            'user_id' => $this->user->id,
            'marital_status' => 'single',
            'has_spouse' => false,
            'own_home' => false,
            'home_value' => 0,
            'nrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => 0,
        ]);

        $response = $this->postJson('/api/estate/profile', [
            'marital_status' => 'married',
            'has_spouse' => true,
            'own_home' => true,
            'home_value' => 600000,
            'nrb_transferred_from_spouse' => 325000,
            'charitable_giving_percent' => 15,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.marital_status', 'married')
            ->assertJsonPath('data.home_value', 600000);

        $this->assertDatabaseCount('iht_profiles', 1);
    });
});
