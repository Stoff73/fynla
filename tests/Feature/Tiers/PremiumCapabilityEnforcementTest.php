<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.payment_enabled' => true]);
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
});

it('denies Free users at every implemented Premium capability boundary', function (string $method, string $uri) {
    $user = User::factory()->create(['tier' => 'free']);

    $response = $this->actingAs($user, 'sanctum')->json($method, $uri);

    $response->assertForbidden()
        ->assertJsonPath('error', 'capability_denied')
        ->assertJsonPath('required_tier', 'premium');
})->with([
    'Estate Planning' => ['POST', '/api/estate/profile'],
    'Holistic Plan' => ['POST', '/api/holistic/analyze'],
    'What If' => ['POST', '/api/what-if-scenarios'],
    'statement extraction' => ['POST', '/api/documents/upload'],
    'document upload' => ['POST', '/api/documents/upload-only'],
    'investment cost analysis' => ['GET', '/api/investment/fees/analyze'],
    'joint household view' => ['GET', '/api/household/net-worth'],
    'Letter to Spouse' => ['PUT', '/api/user/letter-to-spouse'],
]);

it('does not stop Premium requests at the capability boundary', function (string $method, string $uri) {
    $user = User::factory()->withActivePremiumSubscription()->create();

    $response = $this->actingAs($user, 'sanctum')->json($method, $uri);

    expect($response->status())->not->toBe(403)
        ->and($response->json('error'))->not->toBe('capability_denied');
})->with([
    'Estate Planning' => ['POST', '/api/estate/profile'],
    'Holistic Plan' => ['POST', '/api/holistic/analyze'],
    'What If' => ['POST', '/api/what-if-scenarios'],
    'statement extraction' => ['POST', '/api/documents/upload'],
    'document upload' => ['POST', '/api/documents/upload-only'],
    'investment cost analysis' => ['GET', '/api/investment/fees/analyze'],
    'joint household view' => ['GET', '/api/household/net-worth'],
    'Letter to Spouse' => ['PUT', '/api/user/letter-to-spouse'],
]);

it('removes detailed expenditure fields from Free responses', function () {
    $free = User::factory()->create([
        'tier' => 'free',
        'monthly_expenditure' => 1000,
        'food_groceries' => 300,
    ]);

    $this->actingAs($free, 'sanctum')
        ->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonMissingPath('data.user.food_groceries');

    $this->actingAs($free, 'sanctum')
        ->getJson('/api/user/profile')
        ->assertOk()
        ->assertJsonMissingPath('data.expenditure.categories');
});

it('returns detailed expenditure fields to Premium', function () {
    $premium = User::factory()->withActivePremiumSubscription()->create([
        'monthly_expenditure' => 1000,
        'food_groceries' => 300,
    ]);

    $this->actingAs($premium, 'sanctum')
        ->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.user.food_groceries', '300.00');

    $this->actingAs($premium, 'sanctum')
        ->getJson('/api/user/profile')
        ->assertOk()
        ->assertJsonPath('data.expenditure.categories.food_groceries', '300.00');
});

it('allows base expenditure but denies detailed category writes for Free', function () {
    $free = User::factory()->create(['tier' => 'free']);

    $this->actingAs($free, 'sanctum')
        ->putJson('/api/user/profile/expenditure', ['monthly_expenditure' => 1200])
        ->assertOk();

    $this->actingAs($free, 'sanctum')
        ->putJson('/api/user/profile/expenditure', ['food_groceries' => 300])
        ->assertForbidden()
        ->assertJsonPath('error', 'capability_denied')
        ->assertJsonPath('required_tier', 'premium');
});
