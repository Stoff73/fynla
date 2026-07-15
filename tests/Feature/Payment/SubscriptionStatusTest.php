<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
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
            'status' => null,
        ]);
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
            'status' => 'active',
        ]);
});

it('keeps the trial-status compatibility alias byte-equivalent', function () {
    $user = User::factory()->create(['tier' => 'premium']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'status' => 'active',
        'current_period_end' => now()->addYear(),
    ]);
    Sanctum::actingAs($user);

    $canonical = $this->getJson('/api/payment/subscription-status');
    $compatibility = $this->getJson('/api/payment/trial-status');

    $canonical->assertOk();
    $compatibility->assertOk();
    expect($compatibility->getContent())->toBe($canonical->getContent());
});
