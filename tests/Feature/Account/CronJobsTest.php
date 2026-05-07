<?php

declare(strict_types=1);

use App\Models\User;
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
