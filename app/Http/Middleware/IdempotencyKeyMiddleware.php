<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AiRequestIdempotency;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * S0.11.3 — Idempotency-Key middleware for AI write endpoints.
 *
 * If the request carries an `Idempotency-Key` header, hashes it together
 * with (user_id, method, URI, body) into a canonical key_hash. Looks up
 * `ai_request_idempotency` — a hit within the 24h window short-circuits
 * with the stored response (for JSON endpoints) or a generic
 * duplicate-acknowledgement (for SSE / streamed endpoints, since you
 * can't re-stream a closed connection).
 *
 * Misses pass through to the controller; on completion, the response is
 * captured and stored against the key_hash so the next retry hits the
 * cache.
 *
 * Requests without an Idempotency-Key pass through unchanged — the
 * middleware is opt-in by header.
 */
class IdempotencyKeyMiddleware
{
    private const TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $clientKey = $request->header('Idempotency-Key');

        // Opt-in by header — absence means "no idempotency", pass through.
        if ($clientKey === null || $clientKey === '') {
            return $next($request);
        }

        // Header must be a reasonable length to avoid storage bloat / abuse.
        $clientKey = (string) $clientKey;
        if (mb_strlen($clientKey) > 191) {
            return $next($request);
        }

        $user = $request->user();
        if ($user === null) {
            // No authenticated user → can't scope safely. Skip idempotency
            // rather than risk cross-user collisions.
            return $next($request);
        }

        $bodyHash = hash('sha256', (string) $request->getContent());
        $keyHash = hash('sha256', sprintf(
            '%d|%s|%s|%s|%s',
            $user->id,
            $request->method(),
            $request->path(),
            $bodyHash,
            $clientKey
        ));

        $existing = AiRequestIdempotency::query()
            ->where('key_hash', $keyHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing !== null) {
            return $this->buildDuplicateResponse($existing);
        }

        $response = $next($request);

        $this->storeRecord(
            $user->id,
            $clientKey,
            $keyHash,
            $request->method(),
            $request->path(),
            $bodyHash,
            $response
        );

        return $response;
    }

    private function buildDuplicateResponse(AiRequestIdempotency $existing): JsonResponse
    {
        // For non-streaming captures (response_payload is non-null) we can
        // replay the original JSON body verbatim. For SSE / streamed
        // responses we don't have a body to replay — return a canonical
        // duplicate-acknowledgement so the client knows their retry was
        // a no-op.
        if ($existing->response_payload !== null) {
            return new JsonResponse(
                $existing->response_payload,
                $existing->response_status,
                ['X-Idempotent-Replay' => '1']
            );
        }

        return new JsonResponse([
            'idempotent' => true,
            'message' => 'Duplicate request — original was processed at '.$existing->created_at->toIso8601String().'.',
            'original_request_at' => $existing->created_at->toIso8601String(),
        ], 200, ['X-Idempotent-Replay' => '1']);
    }

    private function storeRecord(
        int $userId,
        string $clientKey,
        string $keyHash,
        string $method,
        string $uri,
        string $bodyHash,
        Response $response
    ): void {
        // Capture body for non-streaming JsonResponse only. SSE / file /
        // binary streams cannot be replayed, so we store a null payload
        // and rely on the generic acknowledgement on hit.
        $payload = null;
        if ($response instanceof JsonResponse) {
            $decoded = json_decode($response->getContent() ?: 'null', true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        try {
            AiRequestIdempotency::create([
                'user_id' => $userId,
                'key_hash' => $keyHash,
                'client_key' => $clientKey,
                'method' => $method,
                'request_uri' => $uri,
                'body_hash' => $bodyHash,
                'response_status' => $response->getStatusCode(),
                'response_payload' => $payload,
                'expires_at' => now()->addHours(self::TTL_HOURS),
            ]);
        } catch (\Throwable $e) {
            // Best-effort write: a unique-key clash means another concurrent
            // worker stored the row first — that's fine, dedup still works
            // on the next read. Don't fail the in-flight response.
        }
    }
}
