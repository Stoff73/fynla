<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Lifecycle\Campaigns\ChurnedSubscriberCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes a user who cancelled three days ago after a completed payment', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'cancelled_at' => now()->subDays(3)->setTime(12, 0),
    ]);
    Payment::factory()->create([
        'subscription_id' => $subscription->id,
        'user_id' => $user->id,
        'status' => 'completed',
    ]);

    $campaign = app(ChurnedSubscriberCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->toContain($user->id);
});

it('excludes a cancelled user without a completed payment', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'cancelled_at' => now()->subDays(3)->setTime(12, 0),
    ]);
    Payment::factory()->pending()->create([
        'subscription_id' => $subscription->id,
        'user_id' => $user->id,
    ]);

    $campaign = app(ChurnedSubscriberCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('has correct name and priority', function () {
    $campaign = app(ChurnedSubscriberCampaign::class);
    expect($campaign->name())->toBe('churned_subscriber');
    expect($campaign->priority())->toBe(2);
});
