<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

it('accounts:execute-scheduled-deletions deletes only users whose schedule has passed', function () {
    $past = User::factory()->create([
        'deletion_scheduled_for' => now()->subHour(),
        'deletion_reason' => 'user_requested',
        'deletion_source' => 'settings_privacy',
    ]);
    $future = User::factory()->create([
        'deletion_scheduled_for' => now()->addDay(),
        'deletion_reason' => 'user_requested',
        'deletion_source' => 'settings_privacy',
    ]);

    Artisan::call('accounts:execute-scheduled-deletions');

    expect(User::withTrashed()->find($past->id)->trashed())->toBeTrue();
    expect(User::find($future->id))->not->toBeNull();
});

it('accounts:execute-grace-deletions soft-deletes users whose 30-day grace ended', function () {
    $user = User::factory()->create();
    DB::table('subscriptions')->insert([
        'user_id' => $user->id,
        'plan' => 'pro',
        'billing_cycle' => 'monthly',
        'status' => 'expired',
        'data_retention_starts_at' => now()->subDays(31),
        'amount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('accounts:execute-grace-deletions');

    expect(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
    expect(User::withTrashed()->find($user->id)->deletion_reason)
        ->toBeIn(['trial_expired', 'subscription_cancelled_grace_ended']);
});

it('accounts:execute-grace-deletions skips preview users', function () {
    $user = User::factory()->create(['is_preview_user' => true]);
    DB::table('subscriptions')->insert([
        'user_id' => $user->id,
        'plan' => 'pro',
        'billing_cycle' => 'monthly',
        'status' => 'expired',
        'data_retention_starts_at' => now()->subDays(31),
        'amount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('accounts:execute-grace-deletions');

    expect(User::find($user->id))->not->toBeNull();
});
