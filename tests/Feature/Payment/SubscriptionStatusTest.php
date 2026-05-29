<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns free state with no trial fields for a free user', function () {
    $user = User::factory()->create(['tier' => 'free']);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/payment/trial-status');

    $response->assertStatus(200)
        ->assertJson(['has_subscription' => false, 'tier' => 'free']);
    expect($response->json())->not->toHaveKey('days_remaining');
    expect($response->json())->not->toHaveKey('trial_ends_at');
});

it('returns active paid state for a subscriber', function () {
    $user = User::factory()->create(['tier' => 'tier3']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'tier3',
        'status' => 'active',
        'current_period_end' => now()->addYear(),
    ]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/payment/trial-status');
    $response->assertStatus(200)
        ->assertJson(['has_subscription' => true, 'status' => 'active']);
});
