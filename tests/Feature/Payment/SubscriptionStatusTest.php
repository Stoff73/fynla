<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\RevolutService;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.payment_enabled' => true]);
    $this->seed(TierConfigurationSeeder::class);
});

it('returns free state with no trial fields for a free user', function () {
    $user = User::factory()->create(['tier' => 'free']);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/payment/subscription-status');

    $response->assertStatus(200)
        ->assertJson([
            'has_subscription' => false,
            'tier' => 'free',
            'tier_display_name' => 'Free',
            'subscription_status' => null,
        ]);
    expect($response->json())->not->toHaveKey('status');
    expect($response->json())->not->toHaveKey('days_remaining');
    expect($response->json())->not->toHaveKey('trial_ends_at');
});

it('returns count caps and capability matrix for the resolved tier', function () {
    $user = User::factory()->create(['tier' => 'free']);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/payment/subscription-status');

    $response->assertStatus(200);
    // Free tier is count-capped on savings; the frontend reads these to gate "Add".
    expect($response->json('count_caps.savings_account'))->toBe(2);
    expect($response->json('capability_matrix.property'))->toBe('limited');
});

it('reports a pending one-time checkout without renewal claims', function () {
    $user = User::factory()->create(['tier' => 'free', 'revolut_customer_id' => 'pending_status_customer']);
    Sanctum::actingAs($user);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('createOrderWithCustomer')->once()->andReturn([
        'id' => 'pending_status_order',
        'token' => 'pending_status_token',
        'state' => 'pending',
    ]);
    app()->instance(RevolutService::class, $revolut);

    $this->postJson('/api/payment/create-order', [
        'plan' => 'premium',
        'billing_cycle' => 'monthly',
    ])->assertOk();

    $this->getJson('/api/payment/subscription-status')
        ->assertOk()
        ->assertJsonPath('subscription_status', 'pending')
        ->assertJsonPath('auto_renew', false)
        ->assertJsonPath('next_renewal_date', null);
});

it('returns active paid state for a subscriber', function () {
    $user = User::factory()->create(['tier' => 'premium']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'status' => 'active',
        'current_period_end' => now()->addYear(),
    ]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/payment/subscription-status');
    $response->assertStatus(200)
        ->assertJson([
            'has_subscription' => true,
            'tier' => 'premium',
            'tier_display_name' => 'Premium',
            'subscription_status' => 'active',
        ]);
    expect($response->json())->not->toHaveKey('status');
});

it('returns not found for the retired trial-status path for guests and authenticated users', function () {
    $this->getJson('/api/payment/trial-status')->assertNotFound();

    $user = User::factory()->create(['tier' => 'free']);
    Sanctum::actingAs($user);

    $this->getJson('/api/payment/trial-status')->assertNotFound();
});
