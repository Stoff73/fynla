<?php

declare(strict_types=1);

use App\Models\User;

it('allows authenticated user to retrieve their profile', function () {
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

it('prevents unauthenticated user from retrieving profile', function () {
    $response = $this->getJson('/api/auth/user');

    $response->assertStatus(401);
});

it('prevents profile retrieval with invalid token', function () {
    $response = $this->withToken('invalid-token-xyz')
        ->getJson('/api/auth/user');

    $response->assertStatus(401);
});

it('includes all required fields in user profile', function () {
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

it('tells the mobile client a fresh null onboarding state is not paused', function () {
    $freshUser = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_context' => null,
    ]);
    $freshToken = $freshUser->createToken('auth_token');

    $this->withToken($freshToken->plainTextToken)
        ->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.user.onboarding_fyn_paused', false);
});

it('tells the mobile client a deliberately parked onboarding state is paused', function () {
    $pausedUser = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_context' => ['paused_at_step' => 'campaign_income'],
    ]);
    $pausedToken = $pausedUser->createToken('auth_token');

    $this->withToken($pausedToken->plainTextToken)
        ->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.user.onboarding_fyn_paused', true);
});

/**
 * MB-26. Whether a signed-in user should be started on Fyn's onboarding is one
 * decision, made once, here — web and /m both read it. Before this flag the
 * /m mixin computed it from three fields and the web panel computed something
 * different (step set only), so a user who registered but never took the first
 * turn was onboarded on /m and given the advice chat on web.
 */
it('tells every client a fresh incomplete user needs onboarding to start', function () {
    $freshUser = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_context' => null,
    ]);

    $this->withToken($freshUser->createToken('auth_token')->plainTextToken)
        ->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.user.onboarding_fyn_needs_start', true);
});

it('does not ask a client to start onboarding for a paused, mid-walk or completed user', function () {
    $paused = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_context' => ['paused_at_step' => 'campaign_income'],
    ]);
    $midWalk = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_work',
    ]);
    $completed = User::factory()->create([
        'onboarding_completed' => true,
        'onboarding_fyn_step' => null,
    ]);

    foreach ([$paused, $midWalk, $completed] as $user) {
        $this->withToken($user->createToken('auth_token')->plainTextToken)
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.user.onboarding_fyn_needs_start', false);
    }
});
