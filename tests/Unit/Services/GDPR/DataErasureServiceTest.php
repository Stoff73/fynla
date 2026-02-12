<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ErasureRequest;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\GDPR\DataErasureService;

beforeEach(function () {
    $this->auditService = Mockery::mock(AuditService::class);
    $this->auditService->shouldReceive('logGDPR')->byDefault();

    $this->erasureService = new DataErasureService($this->auditService);
    $this->user = User::factory()->create();
});

afterEach(function () {
    Mockery::close();
});

describe('requestErasure', function () {
    it('creates a new erasure request', function () {
        $request = $this->erasureService->requestErasure($this->user, 'Test reason');

        expect($request)->toBeInstanceOf(ErasureRequest::class);
        expect($request->user_id)->toBe($this->user->id);
        expect($request->status)->toBe(ErasureRequest::STATUS_PENDING);
        expect($request->reason)->toBe('Test reason');
    });

    it('logs the erasure request', function () {
        $this->auditService->shouldReceive('logGDPR')
            ->once()
            ->with(AuditLog::ACTION_ERASURE_REQUESTED, $this->user->id, Mockery::any());

        $this->erasureService->requestErasure($this->user, 'Test reason');
    });

    it('returns existing request if one is pending', function () {
        $existingRequest = ErasureRequest::create([
            'user_id' => $this->user->id,
            'status' => ErasureRequest::STATUS_PENDING,
        ]);

        $newRequest = $this->erasureService->requestErasure($this->user, 'New reason');

        expect($newRequest->id)->toBe($existingRequest->id);
    });

    it('creates new request if existing is completed', function () {
        $completedRequest = ErasureRequest::create([
            'user_id' => $this->user->id,
            'status' => ErasureRequest::STATUS_COMPLETED,
        ]);

        $newRequest = $this->erasureService->requestErasure($this->user, 'New reason');

        expect($newRequest->id)->not->toBe($completedRequest->id);
    });
});

describe('confirmErasure', function () {
    it('confirms a pending erasure request', function () {
        $request = ErasureRequest::create([
            'user_id' => $this->user->id,
            'status' => ErasureRequest::STATUS_PENDING,
        ]);

        $this->erasureService->confirmErasure($request);

        $request->refresh();
        expect($request->status)->toBe(ErasureRequest::STATUS_PROCESSING);
        expect($request->confirmed_at)->not->toBeNull();
    });

    it('throws exception for non-pending request', function () {
        $request = ErasureRequest::create([
            'user_id' => $this->user->id,
            'status' => ErasureRequest::STATUS_COMPLETED,
        ]);

        expect(fn () => $this->erasureService->confirmErasure($request))
            ->toThrow(RuntimeException::class);
    });
});

describe('cancelErasure', function () {
    it('cancels a pending erasure request', function () {
        $request = ErasureRequest::create([
            'user_id' => $this->user->id,
            'status' => ErasureRequest::STATUS_PENDING,
        ]);

        $this->erasureService->cancelErasure($request);

        $request->refresh();
        expect($request->status)->toBe(ErasureRequest::STATUS_CANCELLED);
        expect($request->cancelled_at)->not->toBeNull();
    });

    it('cancels a processing erasure request', function () {
        $request = ErasureRequest::create([
            'user_id' => $this->user->id,
            'status' => ErasureRequest::STATUS_PROCESSING,
        ]);

        $this->erasureService->cancelErasure($request);

        $request->refresh();
        expect($request->status)->toBe(ErasureRequest::STATUS_CANCELLED);
    });

    it('throws exception for completed request', function () {
        $request = ErasureRequest::create([
            'user_id' => $this->user->id,
            'status' => ErasureRequest::STATUS_COMPLETED,
        ]);

        expect(fn () => $this->erasureService->cancelErasure($request))
            ->toThrow(RuntimeException::class);
    });

    it('throws exception for already cancelled request', function () {
        $request = ErasureRequest::create([
            'user_id' => $this->user->id,
            'status' => ErasureRequest::STATUS_CANCELLED,
        ]);

        expect(fn () => $this->erasureService->cancelErasure($request))
            ->toThrow(RuntimeException::class);
    });
});

describe('processErasure', function () {
    it('throws exception for non-processing request', function () {
        $request = ErasureRequest::create([
            'user_id' => $this->user->id,
            'status' => ErasureRequest::STATUS_PENDING,
        ]);

        expect(fn () => $this->erasureService->processErasure($request))
            ->toThrow(RuntimeException::class);
    });

    it('processes erasure request and hard deletes user and request', function () {
        $request = ErasureRequest::create([
            'user_id' => $this->user->id,
            'status' => ErasureRequest::STATUS_PROCESSING,
            'confirmed_at' => now(),
        ]);

        $requestId = $request->id;
        $userId = $this->user->id;

        $this->erasureService->processErasure($request);

        // Request should be force-deleted
        expect(ErasureRequest::find($requestId))->toBeNull();

        // User should be hard-deleted
        expect(User::find($userId))->toBeNull();
    });

    it('hard deletes both request and user during erasure', function () {
        $request = ErasureRequest::create([
            'user_id' => $this->user->id,
            'status' => ErasureRequest::STATUS_PROCESSING,
            'confirmed_at' => now(),
        ]);

        $requestId = $request->id;
        $userId = $this->user->id;

        $this->erasureService->processErasure($request);

        // Both the request and user are hard-deleted (no audit log for completion)
        expect(ErasureRequest::find($requestId))->toBeNull();
        expect(User::find($userId))->toBeNull();
    });

    it('hard deletes request and user when processed_by is provided', function () {
        $request = ErasureRequest::create([
            'user_id' => $this->user->id,
            'status' => ErasureRequest::STATUS_PROCESSING,
            'confirmed_at' => now(),
        ]);

        $requestId = $request->id;
        $userId = $this->user->id;

        $this->erasureService->processErasure($request, 'admin@example.com');

        // Request and user are hard-deleted
        expect(ErasureRequest::find($requestId))->toBeNull();
        expect(User::find($userId))->toBeNull();
    });
});

describe('getPendingRequests', function () {
    it('returns only pending requests', function () {
        ErasureRequest::create([
            'user_id' => $this->user->id,
            'status' => ErasureRequest::STATUS_PENDING,
        ]);

        $otherUser = User::factory()->create();
        ErasureRequest::create([
            'user_id' => $otherUser->id,
            'status' => ErasureRequest::STATUS_COMPLETED,
        ]);

        $pending = $this->erasureService->getPendingRequests();

        expect($pending)->toHaveCount(1);
        expect($pending->first()->status)->toBe(ErasureRequest::STATUS_PENDING);
    });

    it('returns empty collection when no pending requests', function () {
        $pending = $this->erasureService->getPendingRequests();

        expect($pending)->toBeEmpty();
    });
});
