<?php

declare(strict_types=1);

use App\Http\Requests\RegisterRequest;
use App\Models\PendingRegistration;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Marketing-channel attribution. The frontend captures
 * ?utm_source=<platform> on first page load (sourceCapture.js) and
 * submits it with the registration form. Backend validates against
 * an allowlist, persists on PendingRegistration through the verify
 * step, then copies onto the new User row when MFA confirms.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
});

function buildRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Source',
        'surname' => 'Test',
        'email' => 'source-test-' . uniqid() . '@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ], $overrides);
}

it('persists signup_source on pending_registrations when on the allowlist', function (string $platform) {
    $payload = buildRegistrationPayload([
        'email' => "signup-source-{$platform}@example.com",
        'signup_source' => $platform,
    ]);

    $this->postJson('/api/auth/register', $payload)->assertStatus(201);

    $this->assertDatabaseHas('pending_registrations', [
        'email' => "signup-source-{$platform}@example.com",
        'signup_source' => $platform,
    ]);
})->with([
    'linkedin' => ['linkedin'],
    'facebook' => ['facebook'],
    'instagram' => ['instagram'],
    'tiktok' => ['tiktok'],
    'x' => ['x'],
    'youtube' => ['youtube'],
]);

it('rejects registration when signup_source is not on the allowlist', function () {
    $payload = buildRegistrationPayload([
        'email' => 'rejected-source@example.com',
        'signup_source' => 'spotify',  // not in allowlist
    ]);

    $this->postJson('/api/auth/register', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['signup_source']);

    $this->assertDatabaseMissing('pending_registrations', [
        'email' => 'rejected-source@example.com',
    ]);
});

it('accepts registration when signup_source is omitted entirely (organic signup)', function () {
    $payload = buildRegistrationPayload(['email' => 'organic@example.com']);

    $this->postJson('/api/auth/register', $payload)->assertStatus(201);

    $pending = PendingRegistration::where('email', 'organic@example.com')->first();
    expect($pending)->not->toBeNull();
    expect($pending->signup_source)->toBeNull();
});

it('copies signup_source from pending_registrations onto users when MFA verifies', function () {
    // Step 1: register with a campaign source
    $email = 'verify-source@example.com';
    $registerResponse = $this->postJson('/api/auth/register', buildRegistrationPayload([
        'email' => $email,
        'signup_source' => 'linkedin',
    ]));
    $registerResponse->assertStatus(201);
    $pendingId = $registerResponse->json('data.pending_id');

    // Step 2: verify with the code from the DB
    $code = PendingRegistration::find($pendingId)->verification_code;

    $this->postJson('/api/auth/verify-code', [
        'type' => 'registration',
        'pending_id' => $pendingId,
        'code' => $code,
    ])->assertOk();

    // Step 3: signup_source must be on the new user row
    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull();
    expect($user->signup_source)->toBe('linkedin');

    // Pending row deleted post-verify; signup_source survives only on the user.
    $this->assertDatabaseMissing('pending_registrations', ['email' => $email]);
});

it('exposes the canonical allowlist via the RegisterRequest constant', function () {
    expect(RegisterRequest::ALLOWED_SIGNUP_SOURCES)->toBe([
        'linkedin',
        'facebook',
        'instagram',
        'tiktok',
        'x',
        'youtube',
    ]);
});
