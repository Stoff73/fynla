<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Lifecycle\Campaigns\CancelledTrialerCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes a user who cancelled mid-trial 3 days ago', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'trial_started_at' => now()->subDays(5),
        'trial_ends_at' => now()->addDays(2),  // future — they cancelled BEFORE end
        'cancelled_at' => now()->subDays(3)->setTime(12, 0),
    ]);

    $campaign = app(CancelledTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->toContain($user->id);
});

it('excludes a user who cancelled 2 days ago (not yet 3 days)', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'trial_started_at' => now()->subDays(4),
        'trial_ends_at' => now()->addDays(3),
        'cancelled_at' => now()->subDays(2)->setTime(12, 0),
    ]);

    $campaign = app(CancelledTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('excludes a user who cancelled AFTER trial ended (would be Campaign 4)', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
        'trial_started_at' => now()->subDays(20),
        'trial_ends_at' => now()->subDays(13),
        'cancelled_at' => now()->subDays(3)->setTime(12, 0),
    ]);

    $campaign = app(CancelledTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('has correct name and priority', function () {
    $campaign = app(CancelledTrialerCampaign::class);
    expect($campaign->name())->toBe('cancelled_trialer');
    expect($campaign->priority())->toBe(1);
});
