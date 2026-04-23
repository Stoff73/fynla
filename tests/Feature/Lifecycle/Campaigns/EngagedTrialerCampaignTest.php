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

// Engine preference-filter tests use EmptyTrialerCampaign, not EngagedTrialer,
// because EngagedTrialer::mailable() builds a lifecycle.apply-discount signed
// route which doesn't exist until Phase 9. EmptyTrialer has no routes to build
// and is equally valid for testing the preference filter in the engine.

it('engine excludes users who have opted out via notification_preferences', function () {
    \Illuminate\Support\Facades\Mail::fake();

    $user = User::factory()->create(['created_at' => now()->subDays(9)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDays(2),
    ]);
    // No module data → empty trialer

    \App\Models\NotificationPreference::create([
        'user_id' => $user->id,
        'lifecycle_empty_trialer' => false,
        'lifecycle_engaged_trialer' => true,
        'lifecycle_cancelled_trialer' => true,
        'lifecycle_churned_subscriber' => true,
        'lifecycle_lapsed_subscriber' => true,
    ]);

    config(['lifecycle.campaigns' => [\App\Services\Lifecycle\Campaigns\EmptyTrialerCampaign::class]]);

    $engine = app(\App\Services\Lifecycle\LifecycleEngine::class);
    $stats = $engine->run();

    expect($stats['empty_trialer']['sent'])->toBe(0);
    \Illuminate\Support\Facades\Mail::assertNothingSent();
});

it('engine includes users with no notification_preferences row at all', function () {
    \Illuminate\Support\Facades\Mail::fake();

    $user = User::factory()->create(['created_at' => now()->subDays(9)]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDays(2),
    ]);

    // Explicitly DO NOT create a notification_preferences row
    expect(\App\Models\NotificationPreference::where('user_id', $user->id)->exists())->toBeFalse();

    config(['lifecycle.campaigns' => [\App\Services\Lifecycle\Campaigns\EmptyTrialerCampaign::class]]);

    $engine = app(\App\Services\Lifecycle\LifecycleEngine::class);
    $stats = $engine->run();

    expect($stats['empty_trialer']['sent'])->toBe(1);
});
