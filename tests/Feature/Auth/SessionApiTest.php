<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserSession;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'session-test@example.com',
        'password' => bcrypt('password123'),
        'is_preview_user' => true,
    ]);
});

describe('List Sessions', function () {
    it('returns sessions endpoint successfully', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/auth/sessions');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'sessions',
                ],
            ]);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/auth/sessions');

        $response->assertStatus(401);
    });
});

describe('Revoke Session', function () {
    it('cannot revoke another user session', function () {
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('test_token');
        $session = UserSession::create([
            'user_id' => $otherUser->id,
            'token_id' => $token->accessToken->id,
            'device_name' => 'Other Device',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'last_activity_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/auth/sessions/{$session->id}");

        $response->assertStatus(404);
    });

    it('returns 404 for non-existent session', function () {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/auth/sessions/99999');

        $response->assertStatus(404);
    });

    it('requires authentication', function () {
        $response = $this->deleteJson('/api/auth/sessions/1');

        $response->assertStatus(401);
    });
});

describe('Revoke All Other Sessions', function () {
    it('requires authentication', function () {
        $response = $this->deleteJson('/api/auth/sessions/others/all');

        $response->assertStatus(401);
    });
});

describe('Session Integration via Login', function () {
    it('creates session on login', function () {
        // Login creates a real token and session
        $response = $this->postJson('/api/auth/login', [
            'email' => 'session-test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        // Check session was created
        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $this->user->id,
        ]);
    });
});
