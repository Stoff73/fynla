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

test('get overview endpoint returns net worth data', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000,
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

test('get overview requires authentication', function () {
    // Reset app to clear Sanctum auth from beforeEach
    $this->app = $this->createApplication();

    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->getJson('/api/net-worth/overview');

    $response->assertStatus(401);
});

test('get breakdown endpoint returns asset percentages', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000,
        'ownership_percentage' => 100,
    ]);

    InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 100000,
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

test('get trend endpoint returns 12 months by default', function () {
    $response = $this->getJson('/api/net-worth/trend');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonCount(12, 'data');
});

test('get trend endpoint accepts months parameter', function () {
    $response = $this->getJson('/api/net-worth/trend?months=6');

    $response->assertStatus(200)
        ->assertJsonCount(6, 'data');
});

test('get trend endpoint validates months parameter', function () {
    $response = $this->getJson('/api/net-worth/trend?months=50');

    $response->assertStatus(422);
});

test('get assets summary endpoint returns counts and totals', function () {
    Property::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'current_value' => 200000,
        'ownership_percentage' => 100,
    ]);

    SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 25000,
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

test('get joint assets endpoint returns only joint assets', function () {
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
        'ownership_percentage' => 100,
        'ownership_type' => 'individual',
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

test('refresh endpoint invalidates cache and recalculates', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000,
        'ownership_percentage' => 100,
    ]);

    $response = $this->postJson('/api/net-worth/refresh');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Net worth refreshed successfully',
        ]);
});

test('user can only access their own net worth', function () {
    $otherUser = User::factory()->create();

    Property::factory()->create([
        'user_id' => $otherUser->id,
        'current_value' => 1000000,
        'ownership_percentage' => 100,
    ]);

    $response = $this->getJson('/api/net-worth/overview');

    $response->assertStatus(200);

    $data = $response->json('data');
    // Should not include other user's property
    expect($data['total_assets'])->toEqual(0.0);
});
