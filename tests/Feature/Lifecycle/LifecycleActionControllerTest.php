<?php

declare(strict_types=1);

use App\Models\FeedbackResponse;
use App\Models\LifecycleEmailLog;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('restartTrial via valid signed URL reactivates the trial', function () {
    $user = User::factory()->create(['plan' => 'free']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'trial_started_at' => now()->subDays(15),
        'trial_ends_at' => now()->subDays(8),
        'data_retention_starts_at' => now()->subDays(8),
    ]);

    LifecycleEmailLog::create([
        'user_id' => $user->id,
        'campaign' => 'empty_trialer',
        'sent_at' => now()->subDays(2),
    ]);

    $url = URL::temporarySignedRoute(
        'lifecycle.restart-trial',
        now()->addDays(7),
        ['user_id' => $user->id]
    );

    $response = $this->get($url);

    // SPA has no named 'login' route — controller redirects to /login
    $response->assertRedirect('/login');
    $user->refresh();
    expect($user->plan)->toBe('pro');

    $log = LifecycleEmailLog::where('user_id', $user->id)->first();
    expect($log->clicked_at)->not->toBeNull();
    expect($log->action_taken)->toBe('restarted_trial');
});

it('rejects tampered signed URL', function () {
    $url = URL::temporarySignedRoute(
        'lifecycle.restart-trial',
        now()->addDays(7),
        ['user_id' => 1]
    );

    // Change the user_id without re-signing
    $tampered = str_replace('user_id=1', 'user_id=2', $url);

    $this->get($tampered)->assertForbidden();
});

it('feedback creates a row with reason_code', function () {
    $user = User::factory()->create();
    LifecycleEmailLog::create([
        'user_id' => $user->id,
        'campaign' => 'cancelled_trialer',
        'sent_at' => now()->subDays(1),
    ]);

    $url = URL::temporarySignedRoute(
        'lifecycle.feedback',
        now()->addDays(7),
        [
            'user_id' => $user->id,
            'campaign' => 'cancelled_trialer',
            'reason' => 'too_expensive',
        ]
    );

    $this->get($url)->assertOk();

    $row = FeedbackResponse::where('user_id', $user->id)->first();
    expect($row->reason_code)->toBe('too_expensive');
    expect($row->free_text)->toBeNull();
    expect($row->clicked_at)->not->toBeNull();
});

it('feedback rejects an unknown reason code', function () {
    $user = User::factory()->create();

    $url = URL::temporarySignedRoute(
        'lifecycle.feedback',
        now()->addDays(7),
        [
            'user_id' => $user->id,
            'campaign' => 'cancelled_trialer',
            'reason' => 'invalid_reason',
        ]
    );

    $this->get($url)->assertStatus(400);
});

it('clicking a different reason on the same email replaces (not duplicates)', function () {
    $user = User::factory()->create();

    $url1 = URL::temporarySignedRoute(
        'lifecycle.feedback',
        now()->addDays(7),
        ['user_id' => $user->id, 'campaign' => 'cancelled_trialer', 'reason' => 'too_expensive']
    );
    $url2 = URL::temporarySignedRoute(
        'lifecycle.feedback',
        now()->addDays(7),
        ['user_id' => $user->id, 'campaign' => 'cancelled_trialer', 'reason' => 'missing_features']
    );

    $this->get($url1);
    $this->get($url2);

    expect(FeedbackResponse::where('user_id', $user->id)->count())->toBe(1);
    expect(FeedbackResponse::where('user_id', $user->id)->first()->reason_code)->toBe('missing_features');
});

it('updatePayment redirects to profile for logged-in user', function () {
    $user = User::factory()->create();

    LifecycleEmailLog::create([
        'user_id' => $user->id,
        'campaign' => 'lapsed_subscriber',
        'sent_at' => now()->subDays(1),
    ]);

    $url = URL::temporarySignedRoute(
        'lifecycle.update-payment',
        now()->addDays(7),
        ['user_id' => $user->id]
    );

    // SPA has no named 'account.billing' route — SubscriptionManagement lives
    // in the UserProfile view under a tab, so /profile is the closest
    // equivalent and matches the controller's redirect.
    $this->actingAs($user)->get($url)->assertRedirect('/profile');

    $log = LifecycleEmailLog::where('user_id', $user->id)->first();
    expect($log->action_taken)->toBe('clicked_update_payment');
});
