<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiMessage;
use App\Models\EvalProviderRun;
use App\Models\EvalRecordingSession;
use App\Services\Eval\EvalDeltaBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Read-only admin viewer for eval forensic recordings.
 *
 * - index: scenario × provider matrix across every recorded session
 * - show:  one session with both provider runs side-by-side, plus a delta
 *          report computed against the scenario YAML's expectations
 * - raw:   the unparsed JSONL fixture file content (lazy-loaded)
 * - systemPrompt: the assistant message's system_prompt (lazy-loaded)
 */
final class EvalRecordingController extends Controller
{
    public function __construct(
        private readonly EvalDeltaBuilder $deltaBuilder,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sessions = EvalRecordingSession::query()
            ->with(['evalUser:id,email', 'providerRuns:id,eval_recording_session_id,provider,model,duration_ms,sse_event_count,assistant_text,fixture_path,tool_calls,forbidden_hits'])
            ->orderByDesc('started_at')
            ->limit((int) $request->query('limit', 200))
            ->get();

        $rows = $sessions->map(function (EvalRecordingSession $s): array {
            $runs = $s->providerRuns->map(fn (EvalProviderRun $r) => [
                'id' => $r->id,
                'provider' => $r->provider,
                'model' => $r->model,
                'duration_ms' => $r->duration_ms,
                'sse_event_count' => $r->sse_event_count,
                'response_chars' => mb_strlen((string) $r->assistant_text),
                'tool_call_count' => is_array($r->tool_calls) ? count($r->tool_calls) : 0,
                'forbidden_hit_count' => is_array($r->forbidden_hits) ? count($r->forbidden_hits) : 0,
                'has_fixture' => $r->fixture_path && File::exists($r->fixture_path),
            ])->values();

            return [
                'id' => $s->id,
                'scenario_id' => $s->scenario_id,
                'status' => $s->status,
                'fynla_branch' => $s->fynla_branch,
                'fynla_sha' => $s->fynla_sha,
                'eval_user' => $s->evalUser ? [
                    'id' => $s->evalUser->id,
                    'email' => $s->evalUser->email,
                ] : null,
                'started_at' => optional($s->started_at)->toIso8601String(),
                'completed_at' => optional($s->completed_at)->toIso8601String(),
                'runs' => $runs,
            ];
        })->values();

        return response()->json(['sessions' => $rows]);
    }

    public function show(int $sessionId): JsonResponse
    {
        $session = EvalRecordingSession::with([
            'evalUser:id,email,first_name,surname,marital_status,onboarding_completed',
            'providerRuns',
        ])->findOrFail($sessionId);

        $expected = $this->parseExpectations($session->scenario_yaml);

        $runs = $session->providerRuns->map(function (EvalProviderRun $r) use ($expected): array {
            $delta = $this->deltaBuilder->build($r, $expected);

            return [
                'id' => $r->id,
                'provider' => $r->provider,
                'model' => $r->model,
                'conversation_id' => $r->conversation_id,
                'duration_ms' => $r->duration_ms,
                'sse_event_count' => $r->sse_event_count,
                'sse_event_types' => $r->sse_event_types,
                'user_message' => $r->user_message,
                'assistant_text' => $r->assistant_text,
                'tool_calls' => $r->tool_calls,
                'forbidden_hits' => $r->forbidden_hits,
                'db_writes_made' => $r->db_writes_made,
                'fixture_path' => $r->fixture_path,
                'fixture_exists' => $r->fixture_path && File::exists($r->fixture_path),
                'has_system_prompt' => AiMessage::where('conversation_id', $r->conversation_id)
                    ->where('role', 'assistant')
                    ->whereNotNull('system_prompt')
                    ->exists(),
                'started_at' => optional($r->started_at)->toIso8601String(),
                'completed_at' => optional($r->completed_at)->toIso8601String(),
                'delta' => $delta,
            ];
        })->values();

        return response()->json([
            'session' => [
                'id' => $session->id,
                'scenario_id' => $session->scenario_id,
                'scenario_path' => $session->scenario_path,
                'scenario_yaml' => $session->scenario_yaml,
                'status' => $session->status,
                'fynla_branch' => $session->fynla_branch,
                'fynla_sha' => $session->fynla_sha,
                'eval_user' => $session->evalUser,
                'start_state_snapshot' => $session->start_state_snapshot,
                'started_at' => optional($session->started_at)->toIso8601String(),
                'completed_at' => optional($session->completed_at)->toIso8601String(),
            ],
            'expected' => $expected,
            'runs' => $runs,
        ]);
    }

    public function rawFixture(int $runId): JsonResponse
    {
        $run = EvalProviderRun::findOrFail($runId);
        if (! $run->fixture_path || ! File::exists($run->fixture_path)) {
            return response()->json([
                'fixture_path' => $run->fixture_path,
                'exists' => false,
                'content' => null,
            ]);
        }

        return response()->json([
            'fixture_path' => $run->fixture_path,
            'exists' => true,
            'bytes' => File::size($run->fixture_path),
            'content' => File::get($run->fixture_path),
        ]);
    }

    public function systemPrompt(int $runId): JsonResponse
    {
        $run = EvalProviderRun::findOrFail($runId);
        $message = AiMessage::where('conversation_id', $run->conversation_id)
            ->where('role', 'assistant')
            ->whereNotNull('system_prompt')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'run_id' => $runId,
            'conversation_id' => $run->conversation_id,
            'system_prompt' => $message?->system_prompt,
            'system_prompt_length' => $message?->system_prompt ? mb_strlen($message->system_prompt) : 0,
            'input_tokens' => $message?->input_tokens,
            'output_tokens' => $message?->output_tokens,
        ]);
    }

    /**
     * Surface the parsed scenario YAML to the frontend. EvalDeltaBuilder
     * reads the raw new-shape keys directly from this payload so the
     * controller stays a thin transport.
     *
     * Includes legacy-key passthrough for older sessions whose
     * scenario_yaml predates the S1.2.l rewrite — those keys end up in
     * the same map but the builder rejects them via assertNoDeprecatedKeys.
     */
    private function parseExpectations(?string $yaml): array
    {
        if (! is_string($yaml) || $yaml === '') {
            return [];
        }

        try {
            $parsed = Yaml::parse($yaml);
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($parsed)) {
            return [];
        }

        $turns = $parsed['input']['turns'] ?? [];
        $userMessage = '';
        if (is_array($turns) && isset($turns[0]['user'])) {
            $userMessage = (string) $turns[0]['user'];
        }

        // Surface the full YAML to the builder. The builder reads the new
        // keys (expected_classification_shape, expected_response_mode, etc.)
        // and short-circuits on legacy keys via AssertionHelpers.
        return array_merge(
            $parsed,
            [
                'description' => trim((string) ($parsed['description'] ?? '')),
                'user_message' => $userMessage,
                'tags' => $parsed['tags'] ?? [],
            ],
        );
    }
}
