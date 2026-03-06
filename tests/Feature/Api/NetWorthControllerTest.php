<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('returns net worth data from overview endpoint', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $response = $this->getJson('/api/net-worth/overview');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'total_assets' => 400000.0,
                'total_liabilities' => 0.0,
                'net_worth' => 400000.0,
            ],
        ]);
});

it('requires authentication for overview endpoint', function () {
    // Reset app to clear Sanctum auth from beforeEach
    $this->app = $this->createApplication();

    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->getJson('/api/net-worth/overview');

    $response->assertStatus(401);
});

it('returns asset percentages from breakdown endpoint', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 100000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $response = $this->getJson('/api/net-worth/breakdown');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonStructure([
            'success',
            'data' => [
                'property' => ['value', 'percentage'],
                'investments' => ['value', 'percentage'],
                'cash' => ['value', 'percentage'],
                'business' => ['value', 'percentage'],
                'chattels' => ['value', 'percentage'],
            ],
        ]);

    $data = $response->json('data');
    expect($data['property']['percentage'])->toEqual(80.0)
        ->and($data['investments']['percentage'])->toEqual(20.0);
});

it('returns counts and totals from assets summary endpoint', function () {
    Property::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'current_value' => 200000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 25000,
        'ownership_type' => 'individual',
    ]);

    $response = $this->getJson('/api/net-worth/assets-summary');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'property' => [
                    'count' => 2,
                    'total_value' => 400000.0,
                ],
                'cash' => [
                    'count' => 1,
                    'total_value' => 25000.0,
                ],
            ],
        ]);
});

it('returns only joint assets from joint assets endpoint', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000,
        'ownership_percentage' => 50,
        'ownership_type' => 'joint',
        'address_line_1' => '123 Test Street',
    ]);

    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 200000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $response = $this->getJson('/api/net-worth/joint-assets');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonCount(1, 'data');

    $data = $response->json('data');
    expect($data[0]['ownership_percentage'])->toEqual(50);
});

it('invalidates cache and recalculates on refresh', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $response = $this->postJson('/api/net-worth/refresh');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Net worth refreshed successfully',
        ]);
});

it('restricts user to their own net worth data', function () {
    $otherUser = User::factory()->create();

    Property::factory()->create([
        'user_id' => $otherUser->id,
        'current_value' => 1000000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $response = $this->getJson('/api/net-worth/overview');

    $response->assertStatus(200);

    $data = $response->json('data');
    // Should not include other user's property
    expect($data['total_assets'])->toEqual(0.0);
});
