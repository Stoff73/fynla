<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('converts trial-origin users to Free and halts any deletion countdown', function () {
    $trialing = User::factory()->create(['tier' => null, 'plan' => 'standard']);
    Subscription::factory()->trialing()->create(['user_id' => $trialing->id, 'plan' => 'standard']);

    $expiredTrial = User::factory()->create(['tier' => null, 'plan' => 'standard']);
    Subscription::factory()->expired()->create([
        'user_id' => $expiredTrial->id, 'plan' => 'standard',
        'data_retention_starts_at' => now()->subDays(5),
    ]);

    $this->artisan('freemium:convert-trial-users')->assertExitCode(0);

    foreach ([$trialing, $expiredTrial] as $u) {
        $u->refresh();
        expect($u->tier)->toBe('free');
        expect($u->plan)->toBe('free');
        expect($u->trial_ends_at)->toBeNull();
        expect(Subscription::where('user_id', $u->id)->exists())->toBeFalse();

        $historical = Subscription::withTrashed()->where('user_id', $u->id)->firstOrFail();
        expect($historical->status)->toBe('expired');
        expect($historical->trial_started_at)->toBeNull();
        expect($historical->trial_ends_at)->toBeNull();
        expect($historical->data_retention_starts_at)->toBeNull();
    }
});

it('leaves genuinely paid users untouched', function () {
    $paidActive = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    $sub = Subscription::factory()->create(['user_id' => $paidActive->id, 'plan' => 'premium', 'status' => 'active']);
    Payment::factory()->create(['user_id' => $paidActive->id, 'subscription_id' => $sub->id, 'status' => 'completed']);

    $paidChurned = User::factory()->create(['tier' => null, 'plan' => 'pro']);
    $sub2 = Subscription::factory()->expired()->create([
        'user_id' => $paidChurned->id, 'plan' => 'pro',
        'data_retention_starts_at' => now()->subDays(5),
    ]);
    Payment::factory()->create(['user_id' => $paidChurned->id, 'subscription_id' => $sub2->id, 'status' => 'completed']);

    $this->artisan('freemium:convert-trial-users')->assertExitCode(0);

    $paidActive->refresh();
    expect($paidActive->tier)->toBe('premium');
    expect(Subscription::where('user_id', $paidActive->id)->exists())->toBeTrue();

    $paidChurned->refresh();
    expect(Subscription::where('user_id', $paidChurned->id)->where('status', 'expired')->exists())->toBeTrue();
});

it('dry-run reports counts and changes nothing', function () {
    $trialing = User::factory()->create(['tier' => null, 'plan' => 'standard']);
    Subscription::factory()->trialing()->create(['user_id' => $trialing->id, 'plan' => 'standard']);

    $this->artisan('freemium:convert-trial-users', ['--dry-run' => true])->assertExitCode(0);

    $trialing->refresh();
    expect($trialing->tier)->toBeNull();
    expect(Subscription::where('user_id', $trialing->id)->exists())->toBeTrue();
});
