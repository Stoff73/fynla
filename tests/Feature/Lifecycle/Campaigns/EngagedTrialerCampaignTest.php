<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Lifecycle\Campaigns\EngagedTrialerCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes a user with module data, expired trial, registered 9+ days ago', function () {
    $user = User::factory()->create(['created_at' => now()->subDays(9)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDays(2),
    ]);
    \App\Models\Property::factory()->create(['user_id' => $user->id]);

    $campaign = app(EngagedTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->toContain($user->id);
});

it('excludes a user with NO module data (would be Campaign 1)', function () {
    $user = User::factory()->create(['created_at' => now()->subDays(9)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDays(2),
    ]);

    $campaign = app(EngagedTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('has correct name and priority', function () {
    $campaign = app(EngagedTrialerCampaign::class);
    expect($campaign->name())->toBe('engaged_trialer');
    expect($campaign->priority())->toBe(5);
});
