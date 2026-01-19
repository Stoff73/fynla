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
    it('returns empty sessions for new user', function () {
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

    it('returns sessions for user with active sessions', function () {
        $token = $this->user->createToken('test_token');
        UserSession::create([
            'user_id' => $this->user->id,
            'token_id' => $token->accessToken->id,
            'device_name' => 'Test Browser',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'last_activity_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/auth/sessions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'sessions' => [
                        '*' => [
                            'id',
                            'device_name',
                            'ip_address',
                            'last_activity_at',
                        ],
                    ],
                ],
            ]);

        expect($response->json('data.sessions'))->toHaveCount(1);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/auth/sessions');

        $response->assertStatus(401);
    });
});

describe('Revoke Session', function () {
    it('revokes a specific session', function () {
        $token = $this->user->createToken('test_token');
        $session = UserSession::create([
            'user_id' => $this->user->id,
            'token_id' => $token->accessToken->id,
            'device_name' => 'Test Browser',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'last_activity_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/auth/sessions/{$session->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        expect(UserSession::find($session->id))->toBeNull();
    });

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

    it('requires authentication', function () {
        $response = $this->deleteJson('/api/auth/sessions/1');

        $response->assertStatus(401);
    });
});

describe('Revoke All Other Sessions', function () {
    it('revokes all sessions except current', function () {
        // Create multiple sessions
        for ($i = 0; $i < 3; $i++) {
            $token = $this->user->createToken("test_token_{$i}");
            UserSession::create([
                'user_id' => $this->user->id,
                'token_id' => $token->accessToken->id,
                'device_name' => "Device {$i}",
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'last_activity_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/auth/sessions/others/all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    });

    it('requires authentication', function () {
        $response = $this->deleteJson('/api/auth/sessions/others/all');

        $response->assertStatus(401);
    });
});
