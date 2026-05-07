<?php

declare(strict_types=1);

use App\Mail\Account\AccountDeletionCancelledEmail;
use App\Mail\Account\AccountDeletionScheduledEmail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Account\AccountDeletionService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

it('scheduleDeletion sets columns, audit logs, and queues email', function () {
    $user = User::factory()->create();
    $executesAt = now()->addDays(14);

    app(AccountDeletionService::class)->scheduleDeletion(
        $user,
        'user_requested',
        'settings_privacy',
        $executesAt
    );

    $user->refresh();
    expect($user->deletion_scheduled_for->toDateTimeString())->toBe($executesAt->toDateTimeString());
    expect($user->deletion_reason)->toBe('user_requested');
    expect($user->deletion_source)->toBe('settings_privacy');
    expect($user->deleted_at)->toBeNull();

    Mail::assertQueued(AccountDeletionScheduledEmail::class);

    expect(AuditLog::where('user_id', $user->id)
        ->where('action', AuditLog::ACTION_ACCOUNT_DELETION_SCHEDULED)
        ->count())->toBe(1);
});

it('scheduleDeletion refuses if user is already scheduled', function () {
    $user = User::factory()->create(['deletion_scheduled_for' => now()->addDays(7)]);

    expect(fn () => app(AccountDeletionService::class)->scheduleDeletion(
        $user,
        'user_requested',
        'settings_privacy',
        now()->addDays(14)
    ))->toThrow(RuntimeException::class, 'already scheduled');
});

it('scheduleDeletion refuses if user is already deleted', function () {
    $user = User::factory()->create();
    $user->delete();

    expect(fn () => app(AccountDeletionService::class)->scheduleDeletion(
        $user,
        'user_requested',
        'settings_privacy',
        now()->addDays(14)
    ))->toThrow(RuntimeException::class, 'already deleted');
});

it('cancelScheduledDeletion clears columns, audit logs, queues email', function () {
    $user = User::factory()->create([
        'deletion_scheduled_for' => now()->addDays(7),
        'deletion_reason' => 'user_requested',
        'deletion_source' => 'settings_privacy',
    ]);

    app(AccountDeletionService::class)->cancelScheduledDeletion($user);

    $user->refresh();
    expect($user->deletion_scheduled_for)->toBeNull();
    expect($user->deletion_reason)->toBeNull();
    expect($user->deletion_source)->toBeNull();

    Mail::assertQueued(AccountDeletionCancelledEmail::class);

    expect(AuditLog::where('user_id', $user->id)
        ->where('action', AuditLog::ACTION_ACCOUNT_DELETION_CANCELLED)
        ->count())->toBe(1);
});

it('cancelScheduledDeletion refuses if user is not scheduled', function () {
    $user = User::factory()->create();

    expect(fn () => app(AccountDeletionService::class)->cancelScheduledDeletion($user))
        ->toThrow(RuntimeException::class, 'not scheduled');
});
