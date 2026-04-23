<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Lifecycle\Campaigns\LapsedSubscriberCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes a user with status=past_due for at least 5 days', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'past_due',
        'current_period_end' => now()->subDays(6),
    ]);

    $campaign = app(LapsedSubscriberCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->toContain($user->id);
});

it('excludes a user past_due for only 4 days', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'past_due',
        'current_period_end' => now()->subDays(4),
    ]);

    $campaign = app(LapsedSubscriberCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('excludes a user with status=active', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'current_period_end' => now()->addDays(20),
    ]);

    $campaign = app(LapsedSubscriberCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('has correct name and priority', function () {
    $campaign = app(LapsedSubscriberCampaign::class);
    expect($campaign->name())->toBe('lapsed_subscriber');
    expect($campaign->priority())->toBe(3);
});
