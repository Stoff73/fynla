<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Account\AccountDeletionService;

it('login of soft-deleted user with correct password returns restorable response', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-pass')]);
    app(AccountDeletionService::class)->deleteAccount($user, 'user_requested', 'settings_privacy');

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'correct-pass',
    ]);

    $response->assertOk()
        ->assertJsonPath('account_deleted_restorable', true)
        ->assertJsonStructure(['restoration_token', 'deleted_at', 'deletion_reason', 'first_name']);
});

it('login of soft-deleted user with WRONG password returns generic 401', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-pass')]);
    app(AccountDeletionService::class)->deleteAccount($user, 'user_requested', 'settings_privacy');

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-pass',
    ]);

    $response->assertStatus(401);
    expect($response->json('account_deleted_restorable'))->toBeNull(); // no enumeration leak
});

it('login of legacy_purged user returns generic 401', function () {
    $user = User::factory()->create([
        'password' => bcrypt('correct-pass'),
        'deleted_at' => now()->subYear(),
        'deletion_reason' => 'legacy_purged',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'correct-pass',
    ]);

    $response->assertStatus(401);
});

it('register with email of soft-deleted user returns restorable response', function () {
    $existing = User::factory()->create(['password' => bcrypt('old-pass')]);
    app(AccountDeletionService::class)->deleteAccount($existing, 'user_requested', 'settings_privacy');

    $response = $this->postJson('/api/auth/register', [
        'email' => $existing->email,
        'password' => 'NewAttemptedPass123!',
        'password_confirmation' => 'NewAttemptedPass123!',
        'first_name' => 'Different',
        'surname' => 'Person',
    ]);

    $response->assertOk()
        ->assertJsonPath('account_deleted_restorable', true)
        ->assertJsonPath('requires_password_verification', true);
});
