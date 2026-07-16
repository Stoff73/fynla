<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\SubscriptionExpiryService;
use App\Services\Payment\TrialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('expires ended paid access and revokes the user to Free', function () {
    $user = User::factory()->create(['plan' => 'premium', 'tier' => 'premium']);
    $subscription = Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'current_period_end' => now()->subMinute(),
        'data_retention_starts_at' => null,
    ]);

    expect(app(SubscriptionExpiryService::class)->expireCancelledSubscriptions())->toBe(1)
        ->and($subscription->fresh()->status)->toBe('expired')
        ->and($subscription->fresh()->data_retention_starts_at)->not->toBeNull()
        ->and($user->fresh()->plan)->toBe('free')
        ->and($user->fresh()->tier)->toBeNull();
});

it('runs the neutral command while retaining the deprecated aliases', function () {
    $user = User::factory()->create(['plan' => 'premium', 'tier' => 'premium']);
    $subscription = Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'past_due',
        'current_period_end' => now()->subMinute(),
        'data_retention_starts_at' => null,
    ]);

    expect(Artisan::call('subscriptions:expire'))->toBe(0)
        ->and($subscription->fresh()->status)->toBe('expired');

    $secondUser = User::factory()->create(['plan' => 'premium', 'tier' => 'premium']);
    $second = Subscription::factory()->plan('premium')->create([
        'user_id' => $secondUser->id,
        'status' => 'cancelled',
        'current_period_end' => now()->subMinute(),
        'data_retention_starts_at' => null,
    ]);

    expect(app(TrialService::class)->expireCancelledSubscriptions())->toBe(1)
        ->and($second->fresh()->status)->toBe('expired')
        ->and(Artisan::all())->toHaveKeys(['subscriptions:expire', 'trials:expire']);
});
