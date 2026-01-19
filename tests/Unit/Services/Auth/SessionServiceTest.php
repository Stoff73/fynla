<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserSession;
use App\Services\Auth\SessionService;

beforeEach(function () {
    $this->sessionService = new SessionService;
    $this->user = User::factory()->create();
});

describe('getUserSessions', function () {
    it('returns empty collection when user has no sessions', function () {
        $sessions = $this->sessionService->getUserSessions($this->user);

        expect($sessions)->toBeEmpty();
    });

    it('returns sessions for user', function () {
        $token = $this->user->createToken('test_token');
        UserSession::create([
            'user_id' => $this->user->id,
            'token_id' => $token->accessToken->id,
            'device_name' => 'Test Device',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'last_activity_at' => now(),
        ]);

        $sessions = $this->sessionService->getUserSessions($this->user);

        expect($sessions)->toHaveCount(1);
        expect($sessions->first()['device_name'])->toBe('Test Device');
    });

    it('returns sessions with correct structure', function () {
        $token = $this->user->createToken('test_token');
        UserSession::create([
            'user_id' => $this->user->id,
            'token_id' => $token->accessToken->id,
            'device_name' => 'Test Device',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'last_activity_at' => now(),
        ]);

        $sessions = $this->sessionService->getUserSessions($this->user);
        $session = $sessions->first();

        expect($session)->toHaveKeys([
            'id', 'device_name', 'ip_address', 'last_activity',
            'last_activity_at', 'created_at', 'is_current',
        ]);
    });
});

describe('revokeSession', function () {
    it('revokes a session', function () {
        $token = $this->user->createToken('test_token');
        $session = UserSession::create([
            'user_id' => $this->user->id,
            'token_id' => $token->accessToken->id,
            'device_name' => 'Test Device',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'last_activity_at' => now(),
        ]);

        $this->sessionService->revokeSession($session);

        // Session should be soft deleted
        expect(UserSession::find($session->id))->toBeNull();
    });
});

describe('revokeAllSessions', function () {
    it('revokes all sessions for user', function () {
        // Create multiple sessions
        for ($i = 0; $i < 3; $i++) {
            $token = $this->user->createToken("test_token_{$i}");
            UserSession::create([
                'user_id' => $this->user->id,
                'token_id' => $token->accessToken->id,
                'device_name' => "Device {$i}",
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Agent',
                'last_activity_at' => now(),
            ]);
        }

        expect($this->sessionService->getSessionCount($this->user))->toBe(3);

        $revokedCount = $this->sessionService->revokeAllSessions($this->user);

        expect($revokedCount)->toBe(3);
        expect($this->sessionService->getSessionCount($this->user))->toBe(0);
    });

    it('returns 0 when user has no sessions', function () {
        $revokedCount = $this->sessionService->revokeAllSessions($this->user);

        expect($revokedCount)->toBe(0);
    });
});

describe('findSession', function () {
    it('finds session by ID for user', function () {
        $token = $this->user->createToken('test_token');
        $session = UserSession::create([
            'user_id' => $this->user->id,
            'token_id' => $token->accessToken->id,
            'device_name' => 'Test Device',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'last_activity_at' => now(),
        ]);

        $found = $this->sessionService->findSession($this->user, $session->id);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($session->id);
    });

    it('returns null for session belonging to another user', function () {
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('test_token');
        $session = UserSession::create([
            'user_id' => $otherUser->id,
            'token_id' => $token->accessToken->id,
            'device_name' => 'Other Device',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'last_activity_at' => now(),
        ]);

        $found = $this->sessionService->findSession($this->user, $session->id);

        expect($found)->toBeNull();
    });

    it('returns null for non-existent session', function () {
        $found = $this->sessionService->findSession($this->user, 99999);

        expect($found)->toBeNull();
    });
});

describe('getSessionCount', function () {
    it('returns correct session count', function () {
        expect($this->sessionService->getSessionCount($this->user))->toBe(0);

        $token = $this->user->createToken('test_token');
        UserSession::create([
            'user_id' => $this->user->id,
            'token_id' => $token->accessToken->id,
            'device_name' => 'Test Device',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'last_activity_at' => now(),
        ]);

        expect($this->sessionService->getSessionCount($this->user))->toBe(1);
    });
});

describe('cleanupOrphanedSessions', function () {
    it('deletes sessions without tokens', function () {
        // Create a session without a valid token
        UserSession::create([
            'user_id' => $this->user->id,
            'token_id' => 99999, // Non-existent token
            'device_name' => 'Orphaned Device',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'last_activity_at' => now(),
        ]);

        $count = $this->sessionService->cleanupOrphanedSessions();

        expect($count)->toBe(1);
    });
});
