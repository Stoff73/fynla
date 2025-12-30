<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

test('user can register with valid data', function () {
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
                'user_id',
                'email',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'first_name' => 'John',
        'surname' => 'Doe',
    ]);
});

test('user registration creates verification code', function () {
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
    expect($response->json('data.user_id'))->not()->toBeNull();

    // Verification code should be created
    $this->assertDatabaseHas('email_verification_codes', [
        'user_id' => $response->json('data.user_id'),
        'type' => 'registration',
        'verified_at' => null,
    ]);
});

test('user cannot register with existing email', function () {
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

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('user registration requires required fields', function () {
    $response = $this->postJson('/api/auth/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'first_name',
            'surname',
            'email',
            'password',
        ]);
});

test('user registration requires valid email format', function () {
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

test('user registration requires password confirmation', function () {
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

test('user registration requires minimum password length', function () {
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
