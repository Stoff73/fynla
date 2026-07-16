<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;

it('prints stable safe JSON and exits zero when no trial remnants exist', function () {
    $expected = [
        'provisional_shape' => 0,
        'historical_trial_shape' => 0,
        'paid_shape' => 0,
        'safe_to_remove' => true,
    ];

    $this->artisan('subscriptions:audit-trial-remnants --json')
        ->expectsOutput(json_encode($expected, JSON_THROW_ON_ERROR))
        ->assertExitCode(0);
});

it('reports a provisional trial row separately and exits non-zero without changing it', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'status' => 'trialing',
        'trial_started_at' => null,
        'trial_ends_at' => null,
        'current_period_start' => null,
        'current_period_end' => null,
    ]);
    $before = $subscription->fresh()->getAttributes();
    $expected = [
        'provisional_shape' => 1,
        'historical_trial_shape' => 1,
        'paid_shape' => 0,
        'safe_to_remove' => false,
    ];

    $this->artisan('subscriptions:audit-trial-remnants --json')
        ->expectsOutput(json_encode($expected, JSON_THROW_ON_ERROR))
        ->assertExitCode(1);

    expect($subscription->fresh()->getAttributes())->toBe($before);
});

it('reports trial dates as historical even when the status is no longer trialing', function () {
    $user = User::factory()->create(['tier' => 'free']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'status' => 'pending',
        'trial_started_at' => now()->subDay(),
        'trial_ends_at' => now()->addDays(6),
        'current_period_start' => null,
        'current_period_end' => null,
    ]);

    $expected = [
        'provisional_shape' => 0,
        'historical_trial_shape' => 1,
        'paid_shape' => 0,
        'safe_to_remove' => false,
    ];

    $this->artisan('subscriptions:audit-trial-remnants --json')
        ->expectsOutput(json_encode($expected, JSON_THROW_ON_ERROR))
        ->assertExitCode(1);
});

it('does not count an ordinary paid row as a paid trial remnant', function () {
    $user = User::factory()->create(['tier' => 'premium']);
    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'status' => 'active',
        'trial_started_at' => null,
        'trial_ends_at' => null,
    ]);
    Payment::factory()->create([
        'user_id' => $user->id,
        'subscription_id' => $subscription->id,
        'status' => 'completed',
    ]);

    $expected = [
        'provisional_shape' => 0,
        'historical_trial_shape' => 0,
        'paid_shape' => 0,
        'safe_to_remove' => true,
    ];

    $this->artisan('subscriptions:audit-trial-remnants --json')
        ->expectsOutput(json_encode($expected, JSON_THROW_ON_ERROR))
        ->assertExitCode(0);
});

it('blocks removal when a completed payer still carries trial-only data', function () {
    $user = User::factory()->create(['tier' => 'premium']);
    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'status' => 'active',
        'trial_started_at' => now()->subDays(8),
        'trial_ends_at' => now()->subDay(),
    ]);
    Payment::factory()->create([
        'user_id' => $user->id,
        'subscription_id' => $subscription->id,
        'status' => 'completed',
    ]);

    $expected = [
        'provisional_shape' => 0,
        'historical_trial_shape' => 1,
        'paid_shape' => 1,
        'safe_to_remove' => false,
    ];

    $this->artisan('subscriptions:audit-trial-remnants --json')
        ->expectsOutput(json_encode($expected, JSON_THROW_ON_ERROR))
        ->assertExitCode(1);
});
