<?php

declare(strict_types=1);

use App\Models\PendingRegistration;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('allows user to register with valid data', function () {
    $userData = [
        'first_name' => 'John',
        'surname' => 'Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'date_of_birth' => '1990-01-15',
        'gender' => 'male',
        'marital_status' => 'single',
    ];

    $response = $this->postJson('/api/auth/register', $userData);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'requires_verification' => true,
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'requires_verification',
            'data' => [
                'pending_id',
                'email',
            ],
        ]);

    // Registration creates a pending registration, not a user directly
    $this->assertDatabaseHas('pending_registrations', [
        'email' => 'john@example.com',
        'first_name' => 'John',
        'surname' => 'Doe',
    ]);
});

it('creates verification code during registration', function () {
    $userData = [
        'first_name' => 'Jane',
        'surname' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'date_of_birth' => '1995-03-20',
        'gender' => 'female',
        'marital_status' => 'married',
    ];

    $response = $this->postJson('/api/auth/register', $userData);

    $response->assertStatus(201);
    expect($response->json('requires_verification'))->toBeTrue();
    expect($response->json('data.pending_id'))->not()->toBeNull();

    // Verification code is stored on the pending registration record
    $pendingId = $response->json('data.pending_id');
    $this->assertDatabaseHas('pending_registrations', [
        'id' => $pendingId,
        'email' => 'jane@example.com',
    ]);

    // Verify the pending registration has a verification code
    $pending = PendingRegistration::find($pendingId);
    expect($pending->verification_code)->not()->toBeNull();
    expect(strlen($pending->verification_code))->toBe(6);
});

it('returns a stable unavailable error when a pending registration no longer exists', function () {
    $this->postJson('/api/auth/verify-code', [
        'type' => 'registration',
        'pending_id' => 2147483647,
        'code' => '123456',
    ])->assertUnprocessable()
        ->assertExactJson([
            'success' => false,
            'error' => 'registration_unavailable',
            'message' => 'Registration is no longer available. Please register again.',
        ]);
});

it('prevents registration with existing email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $userData = [
        'first_name' => 'Test',
        'surname' => 'User',
        'email' => 'existing@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'date_of_birth' => '1990-01-15',
        'gender' => 'male',
        'marital_status' => 'single',
    ];

    $response = $this->postJson('/api/auth/register', $userData);

    // Returns 422 with email_exists flag
    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'email_exists' => true,
        ]);
});

it('requires all required fields for registration', function () {
    $response = $this->postJson('/api/auth/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'first_name',
            'surname',
            'email',
            'password',
        ]);
});

it('requires valid email format for registration', function () {
    $userData = [
        'first_name' => 'Test',
        'surname' => 'User',
        'email' => 'invalid-email',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'date_of_birth' => '1990-01-15',
        'gender' => 'male',
        'marital_status' => 'single',
    ];

    $response = $this->postJson('/api/auth/register', $userData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('requires password confirmation for registration', function () {
    $userData = [
        'first_name' => 'Test',
        'surname' => 'User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'DifferentPassword123!',
        'date_of_birth' => '1990-01-15',
        'gender' => 'male',
        'marital_status' => 'single',
    ];

    $response = $this->postJson('/api/auth/register', $userData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('requires minimum password length for registration', function () {
    $userData = [
        'first_name' => 'Test',
        'surname' => 'User',
        'email' => 'test@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
        'date_of_birth' => '1990-01-15',
        'gender' => 'male',
        'marital_status' => 'single',
    ];

    $response = $this->postJson('/api/auth/register', $userData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects registration intents that are not Premium checkout intents', function (string $plan) {
    $this->postJson('/api/auth/register', [
        'first_name' => 'Plan',
        'surname' => 'Validation',
        'email' => "registration.{$plan}@example.com",
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'plan' => $plan,
        'billing_cycle' => 'monthly',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('plan');
})->with(['free', 'student', 'standard', 'family', 'pro', 'tier1', 'tier2', 'tier3', 'arbitrary']);

it('requires a canonical billing cycle exactly when a Premium checkout intent is present', function (array $checkoutIntent, string $invalidField) {
    $this->postJson('/api/auth/register', [
        'first_name' => 'Checkout',
        'surname' => 'Validation',
        'email' => 'checkout.'.md5(json_encode($checkoutIntent)).'@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        ...$checkoutIntent,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors($invalidField);
})->with([
    'Premium without billing cycle' => [['plan' => 'premium'], 'billing_cycle'],
    'billing cycle without Premium' => [['billing_cycle' => 'monthly'], 'billing_cycle'],
    'unknown billing cycle' => [['plan' => 'premium', 'billing_cycle' => 'weekly'], 'billing_cycle'],
]);

it('accepts the Premium registration intent only with a canonical billing cycle', function () {
    $this->postJson('/api/auth/register', [
        'first_name' => 'Premium',
        'surname' => 'Registration',
        'email' => 'premium.registration@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'plan' => 'premium',
        'billing_cycle' => 'yearly',
    ])->assertCreated();

    $this->assertDatabaseHas('pending_registrations', [
        'email' => 'premium.registration@example.com',
        'plan' => 'premium',
        'billing_cycle' => 'yearly',
    ]);
});

it('creates a free-tier user with no trial on verified registration', function () {
    $pending = PendingRegistration::create([
        'first_name' => 'Free',
        'surname' => 'Signup',
        'email' => 'free.signup@example.com',
        'password' => Hash::make('Password1!'),
        'verification_code' => '123456',
        'verification_attempts' => 0,
        'signup_source' => 'web',
    ]);

    $response = $this->postJson('/api/auth/verify-code', [
        'type' => 'registration',
        'pending_id' => $pending->id,
        'code' => '123456',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'checkout_intent' => null,
            ],
        ]);

    $user = User::where('email', 'free.signup@example.com')->firstOrFail();
    expect($user->tier)->toBe('free');
    expect(Subscription::where('user_id', $user->id)->exists())->toBeFalse();
});

it('returns only the persisted Premium checkout intent after verified registration', function (string $billingCycle) {
    $pending = PendingRegistration::create([
        'first_name' => 'Premium',
        'surname' => 'Signup',
        'email' => "premium.{$billingCycle}@example.com",
        'password' => Hash::make('Password1!'),
        'verification_code' => '123456',
        'verification_attempts' => 0,
        'signup_source' => 'web',
        'plan' => 'premium',
        'billing_cycle' => $billingCycle,
    ]);

    $response = $this->postJson('/api/auth/verify-code?plan=free&billing_cycle=yearly', [
        'type' => 'registration',
        'pending_id' => $pending->id,
        'code' => '123456',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'checkout_intent' => [
                    'tier' => 'premium',
                    'billing_cycle' => $billingCycle,
                ],
            ],
        ]);

    $user = User::where('email', "premium.{$billingCycle}@example.com")->firstOrFail();
    expect($user->tier)->toBe('free');
    expect(Subscription::where('user_id', $user->id)->exists())->toBeFalse();
    expect(PendingRegistration::find($pending->id))->toBeNull();
})->with(['monthly', 'yearly']);

it('does not add a Premium checkout intent while verifying a Free pending registration', function () {
    $pending = PendingRegistration::create([
        'first_name' => 'Free',
        'surname' => 'Tamper Check',
        'email' => 'free.tamper@example.com',
        'password' => Hash::make('Password1!'),
        'verification_code' => '123456',
        'verification_attempts' => 0,
        'signup_source' => 'web',
    ]);

    $response = $this->postJson('/api/auth/verify-code?plan=premium&billing_cycle=yearly', [
        'type' => 'registration',
        'pending_id' => $pending->id,
        'code' => '123456',
        'plan' => 'premium',
        'billing_cycle' => 'yearly',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.checkout_intent', null);

    $user = User::where('email', 'free.tamper@example.com')->firstOrFail();
    expect($user->tier)->toBe('free')
        ->and(Subscription::where('user_id', $user->id)->exists())->toBeFalse();
});
