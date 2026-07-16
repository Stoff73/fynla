<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\SubscriptionRenewalService;

it('ignores legacy recurring webhooks for one-time Premium access', function () {
    $user = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    $subscription = Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'auto_renew' => false,
        'revolut_subscription_id' => 'detached_legacy_agreement',
        'current_period_end' => now()->addMonth(),
    ]);

    app(SubscriptionRenewalService::class)->handleSubscriptionFinished([
        'id' => 'detached_legacy_agreement',
    ]);

    expect($subscription->fresh()->status)->toBe('active')
        ->and($user->fresh()->tier)->toBe('premium');
});
