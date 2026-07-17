<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\RevolutSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

afterEach(fn () => Mockery::close());

it('does not reconcile one-time Premium through a legacy recurring agreement', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'auto_renew' => false,
        'revolut_subscription_id' => 'legacy_one_time_agreement',
        'current_period_end' => now()->subMinute(),
    ]);

    $revolut = Mockery::mock(RevolutSubscriptionService::class);
    $revolut->shouldNotReceive('getSubscription');
    app()->instance(RevolutSubscriptionService::class, $revolut);

    $this->artisan('subscriptions:check-overdue')->assertExitCode(0);

    expect($subscription->fresh()->status)->toBe('active');
});
