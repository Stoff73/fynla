<?php

declare(strict_types=1);

namespace App\Services\Eval;

use App\Models\CriticalIllnessPolicy;
use App\Models\DCPension;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\SavingsAccount;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The HTTP loop. Drives a scenario end-to-end against the local Laravel
 * server using the same endpoints a real browser session uses:
 *
 *  POST /api/eval/login/{persona}                         (issues bypass-preview-mode token)
 *  POST /api/ai-chat/conversations
 *  POST /api/ai-chat/conversations/{id}/messages          (per turn — SSE)
 *  GET  /api/eval/trace/{conversationId}                  (post-stream trace fetch)
 *  POST /api/auth/logout                                  (cleanup)
 *
 * Provider selection happens via the global `ai_provider` cache key —
 * the same mechanism a system admin would use. The `finally` block
 * always restores the previous value.
 *
 * Persona reset runs pre-flight (defensive — prior crash leak) and
 * post-flight when the scenario is mutating or any DB writes are
 * detected via the snapshot diff.
 */
final class EvalHttpDriver
{
    public function __construct(private readonly EvalSseConsumer $sse) {}

    /**
     * @param  array<string, mixed>  $scenario
     * @return array{events: list<array<string, mixed>>, http_log: list<array<string, mixed>>, db_writes: array<string, mixed>, engine_trace: list<array<string, mixed>>, conversation_id: int|null, start_state: array<string, mixed>, end_state: array<string, mixed>}
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
        $isMutating = (bool) ($scenario['is_mutating'] ?? false);

        $previousProvider = Cache::get('ai_provider');
        Cache::forever('ai_provider', $provider);
        $previousModel = config("services.{$provider}.chat_model");
        config(["services.{$provider}.chat_model" => $model]);

        $httpLog = [];
        $allEvents = [];
        $conversationId = null;
        $traceEvents = [];
        $dbWrites = [];
        $startState = [];
        $endState = [];

        try {
            // 0. Pre-flight reset — covers any prior leak.
            Artisan::call('preview:reset', ['persona' => $persona]);

            // 1. Login.
            $t0 = microtime(true);
            $loginResp = Http::timeout(5)->post("{$baseUrl}/api/eval/login/{$persona}");
            $httpLog[] = $this->logCall('POST', "{$baseUrl}/api/eval/login/{$persona}", $loginResp->status(), $t0);
            if (! $loginResp->successful()) {
                throw new RuntimeException("eval login failed [{$loginResp->status()}]: {$loginResp->body()}");
            }
            $token = $loginResp->json('token');
            $userId = (int) $loginResp->json('user.id');

            // 2. Create conversation.
            $t0 = microtime(true);
            $convResp = Http::withToken($token)
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
                    ->withHeaders(['Accept' => 'text/event-stream'])
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

            // 4. Fetch engine/gate trace for the conversation just completed.
            $t0 = microtime(true);
            $traceResp = Http::withToken($token)
                ->timeout(5)
                ->get("{$baseUrl}/api/eval/trace/{$conversationId}");
            $httpLog[] = $this->logCall('GET', "{$baseUrl}/api/eval/trace/{$conversationId}", $traceResp->status(), $t0);
            if ($traceResp->successful()) {
                $traceEvents = $traceResp->json('events') ?? [];
            }

            // 5. State diff.
            $endState = $this->snapshotUser($userId);
            $dbWrites = $this->diffSnapshots($startState, $endState);

            // 6. Logout.
            $t0 = microtime(true);
            $logoutResp = Http::withToken($token)
                ->timeout(5)
                ->post("{$baseUrl}/api/auth/logout");
            $httpLog[] = $this->logCall('POST', "{$baseUrl}/api/auth/logout", $logoutResp->status(), $t0);

            return [
                'events' => $allEvents,
                'http_log' => $httpLog,
                'db_writes' => $dbWrites,
                'engine_trace' => $traceEvents,
                'conversation_id' => $conversationId,
                'start_state' => $startState,
                'end_state' => $endState,
            ];
        } finally {
            // Restore provider.
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

            // Reset persona post-flight when mutating OR any write detected.
            // Belt: also reset on exception so partial-write state can't leak.
            if ($isMutating || ! empty($dbWrites)) {
                Artisan::call('preview:reset', ['persona' => $persona]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotUser(int $userId): array
    {
        $user = User::find($userId);

        return [
            'user_keys' => $user?->only(['id', 'first_name', 'date_of_birth', 'marital_status', 'onboarding_completed']) ?? [],
            'savings_count' => SavingsAccount::where('user_id', $userId)->count(),
            'protection_count' => LifeInsurancePolicy::where('user_id', $userId)->count()
                + CriticalIllnessPolicy::where('user_id', $userId)->count()
                + IncomeProtectionPolicy::where('user_id', $userId)->count(),
            'investment_count' => InvestmentAccount::where('user_id', $userId)->count(),
            'pension_count' => DCPension::where('user_id', $userId)->count(),
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
