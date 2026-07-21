<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('isScheduledForDeletion returns true only when scheduled and not deleted', function () {
    $u = User::factory()->create(['deletion_scheduled_for' => Carbon::tomorrow()]);
    expect($u->isScheduledForDeletion())->toBeTrue();

    $u->delete();
    $u->refresh();
    expect($u->isScheduledForDeletion())->toBeFalse();
});

it('canBeRestored returns false for legacy_purged users', function () {
    $u = User::factory()->create([
        'deleted_at' => Carbon::yesterday(),
        'deletion_reason' => 'legacy_purged',
        'purge_eligible_at' => Carbon::yesterday(),
    ]);
    $u = User::withTrashed()->find($u->id);
    expect($u->canBeRestored())->toBeFalse();
});

it('canBeRestored returns true for normal soft-deleted user', function () {
    $u = User::factory()->create([
        'deleted_at' => now(),
        'deletion_reason' => 'user_requested',
        'purge_eligible_at' => now()->addYears(7),
    ]);
    $u = User::withTrashed()->find($u->id);
    expect($u->canBeRestored())->toBeTrue();
});
