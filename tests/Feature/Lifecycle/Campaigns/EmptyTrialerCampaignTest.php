<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Lifecycle\Campaigns\EmptyTrialerCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes a user with no data, expired trial, registered 9+ days ago', function () {
    $user = User::factory()->create([
        'created_at' => now()->subDays(9),
        'is_preview_user' => false,
    ]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_started_at' => now()->subDays(9),
        'trial_ends_at' => now()->subDays(2),
    ]);

    $campaign = app(EmptyTrialerCampaign::class);
    $eligible = $campaign->eligibleUsers();

    expect($eligible->pluck('id'))->toContain($user->id);
});

it('excludes a user with module data', function () {
    $user = User::factory()->create(['created_at' => now()->subDays(9)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDays(2),
    ]);
    Property::factory()->create(['user_id' => $user->id]);

    $campaign = app(EmptyTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('excludes a user with an active subscription', function () {
    $user = User::factory()->create(['created_at' => now()->subDays(9)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'current_period_end' => now()->addDays(30),
    ]);

    $campaign = app(EmptyTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('excludes a user registered <9 days ago', function () {
    $user = User::factory()->create(['created_at' => now()->subDays(5)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDays(2),
    ]);

    $campaign = app(EmptyTrialerCampaign::class);
    expect($campaign->eligibleUsers()->pluck('id'))->not->toContain($user->id);
});

it('has correct name and priority', function () {
    $campaign = app(EmptyTrialerCampaign::class);
    expect($campaign->name())->toBe('empty_trialer');
    expect($campaign->priority())->toBe(4);
});
