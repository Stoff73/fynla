<?php

declare(strict_types=1);

use App\Models\User;

test('authenticated user can retrieve their profile', function () {
    $user = User::factory()->create([
        'first_name' => 'Test',
        'middle_name' => null,
        'surname' => 'User',
        'email' => 'testuser@example.com',
        'date_of_birth' => '1990-05-15',
        'gender' => 'male',
        'marital_status' => 'single',
    ]);

    $token = $user->createToken('auth_token');

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/auth/user');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => 'Test User',
                    'email' => 'testuser@example.com',
                    'gender' => 'male',
                    'marital_status' => 'single',
                ],
            ],
        ]);
});

test('unauthenticated user cannot retrieve profile', function () {
    $response = $this->getJson('/api/auth/user');

    $response->assertStatus(401);
});

test('invalid token cannot retrieve profile', function () {
    $response = $this->withToken('invalid-token-xyz')
        ->getJson('/api/auth/user');

    $response->assertStatus(401);
});

test('user profile includes all required fields', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token');

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/auth/user');

    $response->assertJsonStructure([
        'success',
        'data' => [
            'user' => [
                'id',
                'name',
                'email',
                'email_verified_at',
                'date_of_birth',
                'gender',
                'marital_status',
                'created_at',
                'updated_at',
            ],
        ],
    ]);
});
