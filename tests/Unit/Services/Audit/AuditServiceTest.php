<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(AuditService::class);
    $this->user = User::factory()->create();
});

describe('logAuth', function () {
    it('logs authentication event', function () {
        $this->service->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $this->user);

        $log = AuditLog::where('user_id', $this->user->id)
            ->where('event_type', AuditLog::EVENT_AUTH)
            ->where('action', AuditLog::ACTION_LOGIN_SUCCESS)
            ->first();

        expect($log)->not->toBeNull();
    });

    it('logs failed login attempt', function () {
        $this->service->logAuth(AuditLog::ACTION_LOGIN_FAILED, $this->user);

        $log = AuditLog::where('user_id', $this->user->id)
            ->where('event_type', AuditLog::EVENT_AUTH)
            ->where('action', AuditLog::ACTION_LOGIN_FAILED)
            ->first();

        expect($log)->not->toBeNull();
    });

    it('logs logout event', function () {
        $this->service->logAuth(AuditLog::ACTION_LOGOUT, $this->user);

        expect(AuditLog::where('user_id', $this->user->id)
            ->where('action', AuditLog::ACTION_LOGOUT)
            ->exists())->toBeTrue();
    });

    it('logs MFA enabled event', function () {
        $this->service->logAuth(AuditLog::ACTION_MFA_ENABLED, $this->user);

        expect(AuditLog::where('user_id', $this->user->id)
            ->where('action', AuditLog::ACTION_MFA_ENABLED)
            ->exists())->toBeTrue();
    });

    it('logs MFA disabled event', function () {
        $this->service->logAuth(AuditLog::ACTION_MFA_DISABLED, $this->user);

        expect(AuditLog::where('user_id', $this->user->id)
            ->where('action', AuditLog::ACTION_MFA_DISABLED)
            ->exists())->toBeTrue();
    });

    it('stores metadata with event', function () {
        $metadata = ['reason' => 'test'];

        $this->service->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $this->user, $metadata);

        $log = AuditLog::where('user_id', $this->user->id)->first();
        expect($log->metadata)->toBe($metadata);
    });

    it('can log without user', function () {
        $this->service->logAuth(AuditLog::ACTION_LOGIN_FAILED, null, ['email' => 'test@example.com']);

        $log = AuditLog::where('action', AuditLog::ACTION_LOGIN_FAILED)->first();
        expect($log->user_id)->toBeNull();
    });
});

describe('logGDPR', function () {
    it('logs data export request', function () {
        $this->service->logGDPR(AuditLog::ACTION_EXPORT_REQUESTED, $this->user->id);

        expect(AuditLog::where('user_id', $this->user->id)
            ->where('event_type', AuditLog::EVENT_GDPR)
            ->where('action', AuditLog::ACTION_EXPORT_REQUESTED)
            ->exists())->toBeTrue();
    });
});

describe('getUserLogs', function () {
    it('returns logs for a specific user', function () {
        $this->service->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $this->user);
        $this->service->logAuth(AuditLog::ACTION_LOGOUT, $this->user);

        $otherUser = User::factory()->create();
        $this->service->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $otherUser);

        $logs = $this->service->getUserLogs($this->user);

        expect($logs)->toHaveCount(2);
    });

    it('returns logs in descending order', function () {
        $this->service->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $this->user);
        $this->service->logAuth(AuditLog::ACTION_LOGOUT, $this->user);

        $logs = $this->service->getUserLogs($this->user);

        expect($logs->first()->action)->toBe(AuditLog::ACTION_LOGOUT);
    });

    it('respects limit parameter', function () {
        for ($i = 0; $i < 5; $i++) {
            $this->service->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $this->user);
        }

        $logs = $this->service->getUserLogs($this->user, 3);

        expect($logs)->toHaveCount(3);
    });
});

describe('getRecentAuthLogs', function () {
    it('returns only auth event logs', function () {
        $this->service->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $this->user);
        $this->service->logGDPR(AuditLog::ACTION_EXPORT_REQUESTED, $this->user->id);

        $logs = $this->service->getRecentAuthLogs();

        expect($logs)->toHaveCount(1);
        expect($logs->first()->event_type)->toBe(AuditLog::EVENT_AUTH);
    });
});

describe('getFailedLoginAttempts', function () {
    it('returns only failed login attempts', function () {
        $this->service->logAuth(AuditLog::ACTION_LOGIN_SUCCESS, $this->user);
        $this->service->logAuth(AuditLog::ACTION_LOGIN_FAILED, $this->user);
        $this->service->logAuth(AuditLog::ACTION_LOGIN_FAILED, $this->user);

        $logs = $this->service->getFailedLoginAttempts();

        expect($logs)->toHaveCount(2);
        foreach ($logs as $log) {
            expect($log->action)->toBe(AuditLog::ACTION_LOGIN_FAILED);
        }
    });
});
