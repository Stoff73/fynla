<?php

declare(strict_types=1);

namespace App\Services\Eval;

use App\Models\CriticalIllnessPolicy;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\PensionStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

/**
 * The HTTP loop. Drives a scenario end-to-end against the local Laravel
 * server using the same endpoints a real browser session uses:
 *
 *  POST /api/eval/login/{persona}                         (issues bypass-preview-mode token)
 *  POST /api/ai-chat/conversations
 *  POST /api/ai-chat/conversations/{id}/messages          (per turn — SSE)
 *  POST /api/auth/logout                                  (cleanup)
 *
 * Exactly 4 HTTP calls per recording. The engine/gate trace is handed
 * off in-process via the file cache (key `eval_trace:conversation:{id}`,
 * 10-minute TTL): the chat-send controller persists the request-scoped
 * EvalTraceCollector to that key in its `finally` block, and this
 * driver pulls it back here. Doing the hand-off through the cache
 * avoids the cross-request emptying of the scoped collector that broke
 * the original /api/eval/trace endpoint.
 *
 * Provider selection happens via the global `ai_provider` cache key —
 * the same mechanism a system admin would use. The `finally` block
 * always restores the previous value.
 *
 * NO PERSONA RESET happens in this driver. The persona's seeded data
 * is the input; for read-only advice scenarios it must be preserved
 * across recordings. Reset orchestration (between providers for
 * mutating scenarios, after the whole session) lives in the caller
 * (EvalRecordCommand) where it can run AFTER EvalProviderRun rows are
 * persisted, so the forensic chain is not wiped before it's recorded.
 */
final class EvalHttpDriver
{
    public function __construct(private readonly EvalSseConsumer $sse) {}

    /**
     * @param  array<string, mixed>  $scenario
     * @return array{events: list<array<string, mixed>>, http_log: list<array<string, mixed>>, db_writes: array<string, mixed>, engine_trace: list<array<string, mixed>>, conversation_id: int|null, user_id: int|null, start_state: array<string, mixed>, end_state: array<string, mixed>}
     */
    public function run(
        array $scenario,
        string $provider,
        string $model,
        string $baseUrl = 'http://localhost:8000',
    ): array {
        $persona = $scenario['persona']
            ?? throw new RuntimeException('scenario.persona is required');
        $turns = $scenario['input']['turns'] ?? [];

        $previousProvider = Cache::get('ai_provider');
        Cache::forever('ai_provider', $provider);
        $previousModel = config("services.{$provider}.chat_model");
        config(["services.{$provider}.chat_model" => $model]);

        $httpLog = [];
        $allEvents = [];
        $conversationId = null;
        $userId = null;
        $traceEvents = [];
        $dbWrites = [];
        $startState = [];
        $endState = [];

        // April30Updates F-12 — every authenticated eval request must
        // carry the X-Eval-Run-Id header. The PreviewWriteInterceptor
        // and EvalBypassGate now require both the Sanctum ability AND
        // the header, so a leaked token alone cannot bypass preview
        // protections. We mint one run-id per evaluate() call.
        $evalRunId = 'eval-'.bin2hex(random_bytes(8));
        $evalHeaders = ['X-Eval-Run-Id' => $evalRunId];

        try {
            // 0. Pre-flight — fail fast if the dev server is not responding.
            // /api/preview/personas is unauthenticated and cheap; a non-200
            // here means the rest of the loop will fail with confusing
            // errors. 2-second budget keeps the failure mode tight.
            $healthResp = Http::connectTimeout(2)->timeout(2)->get("{$baseUrl}/api/preview/personas");
            if (! $healthResp->successful()) {
                throw new RuntimeException("eval pre-flight failed: {$baseUrl} returned [{$healthResp->status()}]; is `./dev.sh` running?");
            }

            // 0.5. Token cleanup — purge eval-issued tokens older than an
            // hour so the personal_access_tokens table doesn't accumulate
            // dead rows from earlier recording runs.
            PersonalAccessToken::where('name', 'like', 'eval-%')
                ->where('created_at', '<', now()->subHour())
                ->delete();

            // 1. Login.
            $t0 = microtime(true);
            $loginResp = Http::connectTimeout(5)->timeout(5)->post("{$baseUrl}/api/eval/login/{$persona}");
            $httpLog[] = $this->logCall('POST', "{$baseUrl}/api/eval/login/{$persona}", $loginResp->status(), $t0);
            if (! $loginResp->successful()) {
                throw new RuntimeException("eval login failed [{$loginResp->status()}]: {$loginResp->body()}");
            }
            $token = $loginResp->json('token');
            $userId = (int) $loginResp->json('user.id');
            // Defer to the caller — userId is part of the return shape.

            // 2. Create conversation.
            $t0 = microtime(true);
            $convResp = Http::withToken($token)
                ->withHeaders($evalHeaders)
                ->connectTimeout(5)
                ->timeout(5)
                ->post("{$baseUrl}/api/ai-chat/conversations", [
                    'title' => 'Eval recording — '.($scenario['id'] ?? 'unknown'),
                    'model_used' => $model,
                ]);
            $httpLog[] = $this->logCall('POST', "{$baseUrl}/api/ai-chat/conversations", $convResp->status(), $t0);
            if (! $convResp->successful()) {
                throw new RuntimeException("create conversation failed [{$convResp->status()}]: {$convResp->body()}");
            }
            $conversationId = (int) ($convResp->json('data.id') ?? $convResp->json('id'));
            if ($conversationId <= 0) {
                throw new RuntimeException("create conversation returned no id: {$convResp->body()}");
            }

            // Snapshot start state for diff.
            $startState = $this->snapshotUser($userId);

            // 3. Per-turn message send + SSE consume.
            foreach ($turns as $turn) {
                $message = $turn['user'] ?? null;
                if (! is_string($message) || $message === '') {
                    continue;
                }

                $payload = ['message' => $message];
                if (array_key_exists('current_route', $turn)) {
                    $payload['current_route'] = $turn['current_route'];
                }

                $t0 = microtime(true);
                $sendResp = Http::withToken($token)
                    ->withHeaders(array_merge(['Accept' => 'text/event-stream'], $evalHeaders))
                    ->connectTimeout(5)
                    ->timeout(120)
                    ->post(
                        "{$baseUrl}/api/ai-chat/conversations/{$conversationId}/messages",
                        $payload
                    );

                $httpLog[] = $this->logCall(
                    'POST',
                    "{$baseUrl}/api/ai-chat/conversations/{$conversationId}/messages",
                    $sendResp->status(),
                    $t0,
                );

                if (! $sendResp->successful()) {
                    throw new RuntimeException("send message failed [{$sendResp->status()}]: {$sendResp->body()}");
                }

                $events = $this->sse->consume($sendResp->body());
                $allEvents = array_merge($allEvents, $events);
            }

            // 4. Pull engine/gate trace handed off via the file cache by
            // AiChatController's `finally` block. No HTTP call —
            // sidesteps the cross-request scoped-collector bug that the
            // old /api/eval/trace endpoint had.
            $traceEvents = Cache::pull(EvalTraceCollector::cacheKey($conversationId), []);

            // 5. State diff.
            $endState = $this->snapshotUser($userId);
            $dbWrites = $this->diffSnapshots($startState, $endState);

            // 6. Logout.
            $t0 = microtime(true);
            $logoutResp = Http::withToken($token)
                ->withHeaders($evalHeaders)
                ->connectTimeout(5)
                ->timeout(5)
                ->post("{$baseUrl}/api/auth/logout");
            $httpLog[] = $this->logCall('POST', "{$baseUrl}/api/auth/logout", $logoutResp->status(), $t0);

            return [
                'events' => $allEvents,
                'http_log' => $httpLog,
                'db_writes' => $dbWrites,
                'engine_trace' => $traceEvents,
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'start_state' => $startState,
                'end_state' => $endState,
            ];
        } finally {
            // Restore provider — that's it. No persona reset; the caller
            // owns reset orchestration so the forensic chain
            // (eval_recording_sessions + eval_provider_runs) gets a chance
            // to persist BEFORE any cleanup runs.
            if ($previousProvider === null) {
                Cache::forget('ai_provider');
            } else {
                Cache::forever('ai_provider', $previousProvider);
            }
            if ($previousModel === null) {
                config(["services.{$provider}.chat_model" => null]);
            } else {
                config(["services.{$provider}.chat_model" => $previousModel]);
            }
        }
    }

    /**
     * Capture row counts only — no Carbon-serialised columns. Date casts
     * round-trip through different formats depending on whether the
     * model came from a fresh `find()` or was hydrated post-update,
     * which trips spurious diffs in `diffSnapshots` and falsely flags
     * non-mutating recordings as having writes.
     *
     * @return array<string, int>
     */
    private function snapshotUser(int $userId): array
    {
        return [
            'user_exists' => User::where('id', $userId)->exists() ? 1 : 0,
            'savings_count' => SavingsAccount::where('user_id', $userId)->count(),
            'protection_count' => LifeInsurancePolicy::where('user_id', $userId)->count()
                + CriticalIllnessPolicy::where('user_id', $userId)->count()
                + IncomeProtectionPolicy::where('user_id', $userId)->count(),
            'investment_count' => InvestmentAccount::where('user_id', $userId)->count(),
            'pension_count' => app(PensionStore::class)->forUserByType(User::findOrFail($userId), 'dc')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    private function diffSnapshots(array $before, array $after): array
    {
        $diff = [];
        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;
            if ($previous !== $value) {
                $diff[$key] = ['from' => $previous, 'to' => $value];
            }
        }

        return $diff;
    }

    /**
     * @return array<string, mixed>
     */
    private function logCall(string $method, string $url, int $status, float $startMicrotime): array
    {
        return [
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $startMicrotime) * 1000),
            'at' => now()->toIso8601String(),
        ];
    }
}
