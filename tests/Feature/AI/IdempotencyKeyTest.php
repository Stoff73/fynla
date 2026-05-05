<?php

declare(strict_types=1);

use App\Http\Middleware\IdempotencyKeyMiddleware;
use App\Jobs\AiIdempotencyCleanupJob;
use App\Models\AiRequestIdempotency;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

/**
 * Sprint 0.11.3 — IdempotencyKeyMiddleware.
 *
 * Validates the middleware's behaviour in isolation using a hand-rolled
 * Request + closure. End-to-end SSE testing of the live /messages route
 * is covered by the broader chat tests.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->middleware = new IdempotencyKeyMiddleware;
});

function makeIdempotencyTestRequest(User $user, string $method, string $uri, string $body, ?string $idempotencyKey): Request
{
    $request = Request::create("/{$uri}", $method, [], [], [], [], $body);
    $request->setUserResolver(fn () => $user);
    if ($idempotencyKey !== null) {
        $request->headers->set('Idempotency-Key', $idempotencyKey);
    }

    return $request;
}

describe('IdempotencyKeyMiddleware (S0.11.3)', function () {
    it('passes through requests with no Idempotency-Key header', function () {
        $user = User::factory()->create();
        $request = makeIdempotencyTestRequest($user, 'POST', 'api/ai-chat/conversations/1/messages', '{"msg":"hi"}', null);

        $response = $this->middleware->handle($request, function () {
            return new JsonResponse(['ok' => true], 200);
        });

        expect($response->getStatusCode())->toBe(200)
            ->and(AiRequestIdempotency::count())->toBe(0);
    });

    it('first request with an Idempotency-Key passes through and stores the row', function () {
        $user = User::factory()->create();
        $request = makeIdempotencyTestRequest($user, 'POST', 'api/ai-chat/conversations/1/messages', '{"msg":"hi"}', 'abc-123');

        $response = $this->middleware->handle($request, function () {
            return new JsonResponse(['ok' => true, 'id' => 7], 201);
        });

        expect($response->getStatusCode())->toBe(201);

        $row = AiRequestIdempotency::first();
        expect($row)->not->toBeNull()
            ->and($row->client_key)->toBe('abc-123')
            ->and($row->method)->toBe('POST')
            ->and($row->response_status)->toBe(201)
            ->and($row->response_payload)->toEqual(['ok' => true, 'id' => 7])
            ->and($row->expires_at->greaterThan(now()->addHours(23)))->toBeTrue();
    });

    it('duplicate request within 24h replays the cached JSON response', function () {
        $user = User::factory()->create();
        $body = '{"msg":"hi"}';

        // First call — passes through, response captured.
        $this->middleware->handle(
            makeIdempotencyTestRequest($user, 'POST', 'api/ai-chat/conversations/1/messages', $body, 'dup-key'),
            fn () => new JsonResponse(['ok' => true, 'id' => 42], 201)
        );

        // Second call — controller closure should NOT run.
        $controllerCalled = false;
        $response = $this->middleware->handle(
            makeIdempotencyTestRequest($user, 'POST', 'api/ai-chat/conversations/1/messages', $body, 'dup-key'),
            function () use (&$controllerCalled) {
                $controllerCalled = true;

                return new JsonResponse(['fresh' => true], 200);
            }
        );

        expect($controllerCalled)->toBeFalse()
            ->and($response->getStatusCode())->toBe(201)
            ->and($response->headers->get('X-Idempotent-Replay'))->toBe('1')
            ->and(json_decode($response->getContent(), true))->toEqual(['ok' => true, 'id' => 42]);
    });

    it('different bodies under the same key are NOT treated as duplicates', function () {
        $user = User::factory()->create();

        $this->middleware->handle(
            makeIdempotencyTestRequest($user, 'POST', 'api/ai-chat/conversations/1/messages', '{"msg":"first"}', 'shared-key'),
            fn () => new JsonResponse(['ok' => true, 'first' => true], 200)
        );

        $controllerCalled = false;
        $response = $this->middleware->handle(
            makeIdempotencyTestRequest($user, 'POST', 'api/ai-chat/conversations/1/messages', '{"msg":"different"}', 'shared-key'),
            function () use (&$controllerCalled) {
                $controllerCalled = true;

                return new JsonResponse(['ok' => true, 'second' => true], 200);
            }
        );

        expect($controllerCalled)->toBeTrue()
            ->and(json_decode($response->getContent(), true))->toBe(['ok' => true, 'second' => true]);
    });

    it('different users with the same key are isolated', function () {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->middleware->handle(
            makeIdempotencyTestRequest($alice, 'POST', 'api/ai-chat/conversations/1/messages', '{}', 'shared-key'),
            fn () => new JsonResponse(['user' => 'alice'], 200)
        );

        $controllerCalled = false;
        $response = $this->middleware->handle(
            makeIdempotencyTestRequest($bob, 'POST', 'api/ai-chat/conversations/1/messages', '{}', 'shared-key'),
            function () use (&$controllerCalled) {
                $controllerCalled = true;

                return new JsonResponse(['user' => 'bob'], 200);
            }
        );

        expect($controllerCalled)->toBeTrue()
            ->and(json_decode($response->getContent(), true))->toBe(['user' => 'bob']);
    });

    it('expired rows are NOT treated as duplicates', function () {
        $user = User::factory()->create();
        $body = '{"msg":"hi"}';

        $this->middleware->handle(
            makeIdempotencyTestRequest($user, 'POST', 'api/ai-chat/conversations/1/messages', $body, 'old-key'),
            fn () => new JsonResponse(['ok' => true, 'first' => true], 200)
        );

        // Manually expire the row.
        AiRequestIdempotency::query()->update(['expires_at' => now()->subHour()]);

        $controllerCalled = false;
        $response = $this->middleware->handle(
            makeIdempotencyTestRequest($user, 'POST', 'api/ai-chat/conversations/1/messages', $body, 'old-key'),
            function () use (&$controllerCalled) {
                $controllerCalled = true;

                return new JsonResponse(['ok' => true, 'second' => true], 200);
            }
        );

        expect($controllerCalled)->toBeTrue();
    });

    it('SSE / streamed responses store a null payload and replay generic ack on duplicate', function () {
        $user = User::factory()->create();
        $body = '{"msg":"hi"}';

        $this->middleware->handle(
            makeIdempotencyTestRequest($user, 'POST', 'api/ai-chat/conversations/1/messages', $body, 'sse-key'),
            fn () => new StreamedResponse(function () { /* no-op */
            }, 200)
        );

        $row = AiRequestIdempotency::first();
        expect($row->response_payload)->toBeNull();

        $controllerCalled = false;
        $response = $this->middleware->handle(
            makeIdempotencyTestRequest($user, 'POST', 'api/ai-chat/conversations/1/messages', $body, 'sse-key'),
            function () use (&$controllerCalled) {
                $controllerCalled = true;

                return new StreamedResponse(function () { /* no-op */
                }, 200);
            }
        );

        $payload = json_decode($response->getContent(), true);
        expect($controllerCalled)->toBeFalse()
            ->and($response->getStatusCode())->toBe(200)
            ->and($payload['idempotent'] ?? null)->toBeTrue()
            ->and($payload['message'] ?? '')->toContain('Duplicate request');
    });

    it('rejects an oversized client key by passing through (no storage abuse)', function () {
        $user = User::factory()->create();
        $hugeKey = str_repeat('x', 300);

        $response = $this->middleware->handle(
            makeIdempotencyTestRequest($user, 'POST', 'api/ai-chat/conversations/1/messages', '{}', $hugeKey),
            fn () => new JsonResponse(['ok' => true], 200)
        );

        expect($response->getStatusCode())->toBe(200)
            ->and(AiRequestIdempotency::count())->toBe(0);
    });
});

describe('AiIdempotencyCleanupJob (S0.11.3)', function () {
    it('deletes only expired rows', function () {
        $user = User::factory()->create();

        // Live row.
        AiRequestIdempotency::create([
            'user_id' => $user->id,
            'key_hash' => str_repeat('a', 64),
            'client_key' => 'live',
            'method' => 'POST',
            'request_uri' => 'api/x',
            'body_hash' => str_repeat('b', 64),
            'response_status' => 200,
            'response_payload' => null,
            'expires_at' => now()->addHours(1),
        ]);

        // Expired row.
        AiRequestIdempotency::create([
            'user_id' => $user->id,
            'key_hash' => str_repeat('c', 64),
            'client_key' => 'expired',
            'method' => 'POST',
            'request_uri' => 'api/x',
            'body_hash' => str_repeat('d', 64),
            'response_status' => 200,
            'response_payload' => null,
            'expires_at' => now()->subHour(),
        ]);

        $deleted = (new AiIdempotencyCleanupJob)->handle();

        expect($deleted)->toBe(1)
            ->and(AiRequestIdempotency::count())->toBe(1)
            ->and(AiRequestIdempotency::first()->client_key)->toBe('live');
    });
});
