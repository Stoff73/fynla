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

it('restore endpoint with valid token un-soft-deletes the user and returns Sanctum token', function () {
    $user = User::factory()->create(['password' => bcrypt('p')]);
    app(AccountDeletionService::class)->deleteAccount($user, 'user_requested', 'settings_privacy');

    // Get the restoration token via login flow
    $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'p']);
    $token = $login->json('restoration_token');

    $response = $this->postJson('/api/auth/restore', ['restoration_token' => $token]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'email'], 'redirect_to']);

    expect(User::find($user->id))->not->toBeNull();
    expect(User::find($user->id)->trashed())->toBeFalse();
});

it('restore endpoint with invalid token returns 401', function () {
    $response = $this->postJson('/api/auth/restore', ['restoration_token' => 'definitely-not-real']);
    $response->assertStatus(401);
});

it('restore/check endpoint with email + password returns a fresh restoration_token', function () {
    $user = User::factory()->create(['password' => bcrypt('p')]);
    app(AccountDeletionService::class)->deleteAccount($user, 'user_requested', 'settings_privacy');

    $response = $this->postJson('/api/auth/restore/check', [
        'email' => $user->email, 'password' => 'p',
    ]);

    $response->assertOk()->assertJsonStructure(['restoration_token']);
});
