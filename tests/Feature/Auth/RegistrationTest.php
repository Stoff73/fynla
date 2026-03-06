<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;

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
    $pending = \App\Models\PendingRegistration::find($pendingId);
    expect($pending->verification_code)->not()->toBeNull();
    expect(strlen($pending->verification_code))->toBe(6);
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
