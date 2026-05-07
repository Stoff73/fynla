<?php

declare(strict_types=1);

use App\Mail\Account\AccountDeletionCancelledEmail;
use App\Mail\Account\AccountDeletionConfirmationEmail;
use App\Mail\Account\AccountDeletionScheduledEmail;
use App\Models\AuditLog;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Account\AccountDeletionService;
use Illuminate\Support\Facades\DB;
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

it('deleteAccount soft-deletes user and preserves all financial data', function () {
    $user = User::factory()->create();
    Subscription::factory()->create(['user_id' => $user->id, 'status' => 'active']);

    // Seed some financial data
    LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);
    InvestmentAccount::factory()->create(['user_id' => $user->id]);

    $beforeAuditCount = AuditLog::where('user_id', $user->id)->count();

    app(AccountDeletionService::class)->deleteAccount(
        $user,
        'user_requested',
        'settings_privacy'
    );

    $user = User::withTrashed()->find($user->id);
    expect($user->trashed())->toBeTrue();
    expect($user->deletion_reason)->toBe('user_requested');
    expect($user->deletion_source)->toBe('settings_privacy');
    expect($user->purge_eligible_at)->not->toBeNull();
    expect($user->purge_eligible_at->isAfter(now()->addYears(6)))->toBeTrue();

    // PII intact
    expect($user->first_name)->not->toBeNull();
    expect($user->email)->not->toBeNull();

    // Financial data intact
    expect(LifeInsurancePolicy::where('user_id', $user->id)->count())->toBe(1);
    expect(InvestmentAccount::where('user_id', $user->id)->count())->toBe(1);

    // Subscription cancelled (status flipped, row preserved)
    expect(Subscription::where('user_id', $user->id)->first()->status)->toBe('cancelled');

    // Audit log appended (not anonymised)
    expect(AuditLog::where('user_id', $user->id)->count())->toBeGreaterThan($beforeAuditCount);
    expect(AuditLog::where('user_id', $user->id)
        ->where('action', AuditLog::ACTION_ACCOUNT_DELETED)->count())->toBe(1);

    // Sessions and tokens revoked
    expect(DB::table('user_sessions')->where('user_id', $user->id)->count())->toBe(0);
    expect(DB::table('personal_access_tokens')
        ->where('tokenable_type', User::class)
        ->where('tokenable_id', $user->id)->count())->toBe(0);

    Mail::assertQueued(AccountDeletionConfirmationEmail::class);
});

it('deleteAccount clears scheduled-deletion fields if previously scheduled', function () {
    $user = User::factory()->create([
        'deletion_scheduled_for' => now()->subMinute(),
        'deletion_reason' => 'user_requested',
        'deletion_source' => 'settings_privacy',
    ]);

    app(AccountDeletionService::class)->deleteAccount(
        $user,
        $user->deletion_reason,
        $user->deletion_source
    );

    $user = User::withTrashed()->find($user->id);
    expect($user->deletion_scheduled_for)->toBeNull();
});

it('deleteAccount with non-active subscription leaves status alone', function () {
    $user = User::factory()->create();
    Subscription::factory()->create(['user_id' => $user->id, 'status' => 'expired']);

    app(AccountDeletionService::class)->deleteAccount(
        $user,
        'trial_expired',
        'auto_expiration_grace'
    );

    expect(Subscription::where('user_id', $user->id)->first()->status)->toBe('expired');
});
