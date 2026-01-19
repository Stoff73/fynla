<?php

declare(strict_types=1);

use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\Auth\LoginLockoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(LoginLockoutService::class);
});

describe('isLocked', function () {
    it('returns false when user does not exist', function () {
        expect($this->service->isLocked('nonexistent@example.com'))->toBeFalse();
    });

    it('returns false when user has no failed attempts', function () {
        User::factory()->create(['email' => 'test@example.com']);

        expect($this->service->isLocked('test@example.com'))->toBeFalse();
    });

    it('returns true when user is locked until future time', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'locked_until' => now()->addMinutes(5),
        ]);

        expect($this->service->isLocked('test@example.com'))->toBeTrue();
    });

    it('returns false when lockout has expired', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'locked_until' => now()->subMinutes(1),
        ]);

        expect($this->service->isLocked('test@example.com'))->toBeFalse();
    });
});

describe('getRemainingLockoutSeconds', function () {
    it('returns 0 when user does not exist', function () {
        expect($this->service->getRemainingLockoutSeconds('nonexistent@example.com'))->toBe(0);
    });

    it('returns 0 when user is not locked', function () {
        User::factory()->create(['email' => 'test@example.com']);

        expect($this->service->getRemainingLockoutSeconds('test@example.com'))->toBe(0);
    });

    it('returns remaining seconds when locked', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'locked_until' => now()->addMinutes(5),
        ]);

        $remaining = $this->service->getRemainingLockoutSeconds('test@example.com');

        expect($remaining)->toBeGreaterThan(240); // Should be close to 300 seconds
        expect($remaining)->toBeLessThanOrEqual(300);
    });

    it('returns 0 when lockout has expired', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'locked_until' => now()->subMinutes(1),
        ]);

        expect($this->service->getRemainingLockoutSeconds('test@example.com'))->toBe(0);
    });
});

describe('recordFailedAttempt', function () {
    it('creates a login attempt record', function () {
        User::factory()->create(['email' => 'test@example.com']);

        $this->service->recordFailedAttempt('test@example.com');

        expect(LoginAttempt::where('email', 'test@example.com')->count())->toBe(1);
    });

    it('increments failed login count', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'failed_login_count' => 0,
        ]);

        $this->service->recordFailedAttempt('test@example.com');
        $this->service->recordFailedAttempt('test@example.com');

        $user->refresh();
        expect($user->failed_login_count)->toBe(2);
    });

    it('locks account after 3 failed attempts (1 minute)', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'failed_login_count' => 2,
        ]);

        $this->service->recordFailedAttempt('test@example.com');

        $user->refresh();
        expect($user->locked_until)->not->toBeNull();
    });

    it('locks account for 5 minutes after 5 failed attempts', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'failed_login_count' => 4,
        ]);

        $this->service->recordFailedAttempt('test@example.com');

        $user->refresh();
        // Should be locked for 5 minutes (5 failures = 5 minute lockout)
        expect($user->locked_until)->not->toBeNull();
        $lockoutSeconds = now()->diffInSeconds($user->locked_until);
        // Allow for some timing variance: should be between 4 and 5 minutes
        expect($lockoutSeconds)->toBeGreaterThan(240); // > 4 minutes
        expect($lockoutSeconds)->toBeLessThanOrEqual(300); // <= 5 minutes
    });
});

describe('recordSuccessfulLogin', function () {
    it('resets failed login count', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'failed_login_count' => 5,
            'locked_until' => now()->addMinutes(5),
        ]);

        $this->service->recordSuccessfulLogin('test@example.com');

        $user->refresh();
        expect($user->failed_login_count)->toBe(0);
        expect($user->locked_until)->toBeNull();
    });

    it('records successful login attempt', function () {
        User::factory()->create(['email' => 'test@example.com']);

        $this->service->recordSuccessfulLogin('test@example.com');

        expect(LoginAttempt::where('email', 'test@example.com')
            ->where('successful', true)
            ->exists())->toBeTrue();
    });
});

describe('resetFailedAttempts', function () {
    it('resets all failed attempt data', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'failed_login_count' => 10,
            'locked_until' => now()->addHours(24),
            'last_failed_login_at' => now()->subMinutes(5),
        ]);

        $this->service->resetFailedAttempts($user);

        $user->refresh();
        expect($user->failed_login_count)->toBe(0);
        expect($user->locked_until)->toBeNull();
        expect($user->last_failed_login_at)->toBeNull();
    });
});

describe('getLockoutInfo', function () {
    it('returns unlocked status when not locked', function () {
        User::factory()->create(['email' => 'test@example.com']);

        $info = $this->service->getLockoutInfo('test@example.com');

        expect($info['locked'])->toBeFalse();
        expect($info['remaining_seconds'])->toBe(0);
        expect($info['message'])->toBeNull();
    });

    it('returns locked status with remaining time', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'locked_until' => now()->addMinutes(5),
        ]);

        $info = $this->service->getLockoutInfo('test@example.com');

        expect($info['locked'])->toBeTrue();
        expect($info['remaining_seconds'])->toBeGreaterThan(0);
        expect($info['message'])->toContain('minute');
    });
});
