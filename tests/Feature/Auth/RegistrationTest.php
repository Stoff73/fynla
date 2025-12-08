<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

test('user can register with valid data', function () {
    $userData = [
        'name' => 'John Doe',
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
            'message' => 'User registered successfully',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'date_of_birth',
                    'gender',
                    'marital_status',
                ],
                'access_token',
                'token_type',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'name' => 'John Doe',
    ]);
});

test('user registration creates authentication token', function () {
    $userData = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'date_of_birth' => '1995-03-20',
        'gender' => 'female',
        'marital_status' => 'married',
    ];

    $response = $this->postJson('/api/auth/register', $userData);

    expect($response->json('data.access_token'))->not()->toBeNull();
    expect($response->json('data.token_type'))->toBe('Bearer');

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_type' => User::class,
        'name' => 'auth_token',
    ]);
});

test('user cannot register with existing email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $userData = [
        'name' => 'Test User',
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
            'name',
            'email',
            'password',
        ]);
});

test('user registration requires valid email format', function () {
    $userData = [
        'name' => 'Test User',
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
        'name' => 'Test User',
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
        'name' => 'Test User',
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
