<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function seedVerifiedDeletionSession(User $user, string $type): string
{
    $token = Str::random(64);
    Cache::put("deletion_session:{$user->id}", [
        'token' => $token,
        'verified' => true,
        'type' => $type,
        'attempts' => 0,
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
    ], now()->addMinutes(15));

    return $token;
}

it('Settings → Privacy on user with active paid sub schedules deletion at current_period_end', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'current_period_end' => now()->addDays(20),
    ]);
    Sanctum::actingAs($user);

    $sessionToken = seedVerifiedDeletionSession($user, 'account');

    $response = $this->postJson('/api/auth/gdpr/erasure/execute', [
        'session_token' => $sessionToken,
        'confirmation' => 'Delete my Account',
    ]);

    $response->assertOk();
    expect(User::find($user->id)->deletion_scheduled_for)->not->toBeNull();
    expect(User::find($user->id)->trashed())->toBeFalse();
});

it('Settings → Privacy on user with no paid sub deletes immediately', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $sessionToken = seedVerifiedDeletionSession($user, 'account');

    $response = $this->postJson('/api/auth/gdpr/erasure/execute', [
        'session_token' => $sessionToken,
        'confirmation' => 'Delete my Account',
    ]);

    $response->assertOk();
    expect(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});
