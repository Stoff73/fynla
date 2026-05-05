<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Lifecycle\Campaigns\ChurnedSubscriberCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes a user who cancelled paid sub 3 days ago (cancelled_at >= trial_ends_at)', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'trial_started_at' => now()->subDays(60),
        'trial_ends_at' => now()->subDays(53),
        'cancelled_at' => now()->subDays(3)->setTime(12, 0),
    ]);

    $campaign = app(ChurnedSubscriberCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->toContain($user->id);
});

it('excludes a user who cancelled mid-trial (would be Campaign 3)', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'trial_started_at' => now()->subDays(5),
        'trial_ends_at' => now()->addDays(2),
        'cancelled_at' => now()->subDays(3)->setTime(12, 0),
    ]);

    $campaign = app(ChurnedSubscriberCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('has correct name and priority', function () {
    $campaign = app(ChurnedSubscriberCampaign::class);
    expect($campaign->name())->toBe('churned_subscriber');
    expect($campaign->priority())->toBe(2);
});
