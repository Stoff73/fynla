<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiMessage;
use App\Models\EvalProviderRun;
use App\Models\EvalRecordingSession;
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
            $delta = $this->buildDelta($r, $expected);

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
     * Pull the per-scenario expectations the eval framework cares about
     * out of the YAML so the frontend doesn't need to parse YAML itself.
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

        return [
            'description' => trim((string) ($parsed['description'] ?? '')),
            'user_message' => $userMessage,
            'classifications' => $parsed['expected_classifications'] ?? [],
            'tool_calls' => $parsed['expected_tool_calls'] ?? [],
            'sse_events' => $parsed['expected_sse_events'] ?? [],
            'advice_response' => $parsed['expected_advice_response'] ?? null,
            'forbidden_outputs' => $parsed['forbidden_outputs'] ?? [],
            'forbidden_tools' => $parsed['forbidden_tools'] ?? [],
            'timing_budget_ms' => $parsed['timing_budget_ms'] ?? null,
            'tags' => $parsed['tags'] ?? [],
        ];
    }

    /**
     * Compute the actual-vs-expected delta for a single provider run.
     * This is the data the frontend renders as the "what went wrong" report.
     */
    private function buildDelta(EvalProviderRun $run, array $expected): array
    {
        $expectedTools = collect($expected['tool_calls'] ?? [])
            ->pluck('tool')
            ->filter(fn ($t) => is_string($t) && $t !== '')
            ->values()
            ->all();

        $actualTools = collect($run->tool_calls ?? [])
            ->pluck('name')
            ->filter(fn ($t) => is_string($t) && $t !== '')
            ->values()
            ->all();

        $missingTools = array_values(array_diff($expectedTools, $actualTools));
        $extraTools = array_values(array_diff($actualTools, $expectedTools));

        $forbiddenTools = $expected['forbidden_tools'] ?? [];
        $forbiddenToolHits = array_values(array_intersect($forbiddenTools, $actualTools));

        $budget = $expected['timing_budget_ms'] ?? null;
        $duration = (int) ($run->duration_ms ?? 0);
        $timingStatus = 'unknown';
        $timingOverage = 0;
        if (is_int($budget) && $budget > 0) {
            $timingStatus = $duration <= $budget ? 'within_budget' : 'over_budget';
            $timingOverage = $duration - $budget;
        }

        $expectedEventTypes = collect($expected['sse_events'] ?? [])
            ->pluck('type')
            ->filter(fn ($t) => is_string($t) && $t !== '')
            ->unique()
            ->values()
            ->all();

        $actualEventTypes = is_array($run->sse_event_types) ? array_keys($run->sse_event_types) : [];
        $missingEventTypes = array_values(array_diff($expectedEventTypes, $actualEventTypes));

        // Heuristic root-cause hints + suggested fixes. Pattern matching, not
        // certainty — the frontend prints both so a human can sanity-check fast.
        $hints = [];
        $fixes = [];

        if ($missingTools !== [] && $actualTools === []) {
            $hints[] = 'Provider made no tool calls at all — model ignored the available tool palette, or tools were not advertised in this provider'."'".'s request.';
            if ($run->provider === 'xai') {
                $fixes[] = 'Verify XaiToolDefinitions exposes the same tools as AiToolDefinitions for this scenario type. Anthropic + xAI tool catalogues drift easily.';
                $fixes[] = 'Confirm the request to Grok includes a non-empty tools array AND tool_choice (HasAiChat sends tool_choice only when tools are present). Reasoning models may need tool_choice="auto" explicit.';
                $fixes[] = 'Check whether AdvicePromptBuilder includes a tool-use directive ("Call get_module_analysis before answering") for this query type — Grok-4-1-fast-reasoning tends to default to text-only unless directly instructed.';
            } else {
                $fixes[] = 'Inspect this provider\'s request payload — likely tools=[] or tool_choice not set. Compare HasAiChat\'s xai vs anthropic branch.';
            }
        } elseif ($missingTools !== [] && $extraTools !== []) {
            $hints[] = 'Provider called different tools than expected. The model is reaching for a lower-level enumeration tool ('.implode(', ', array_unique($extraTools)).') instead of the engine-routed analysis tools ('.implode(', ', $missingTools).').';
            $fixes[] = 'Strengthen AdvicePromptBuilder to direct the model toward engine-level tools first ("For protection cover questions, call get_module_analysis(protection) before list_records").';
            $fixes[] = 'Consider removing or scope-limiting list_records in AdviceFyn::buildToolList for this query type — the engine-routed tools should be the primary path.';
            $fixes[] = 'Audit the tool descriptions in AiToolDefinitions — list_records may be over-described and crowding out get_module_analysis.';
        } elseif ($missingTools !== []) {
            $hints[] = 'Provider partially completed: missing '.implode(', ', $missingTools).'.';
            $fixes[] = 'The model called some expected tools but stopped early. Check tool result chaining — a tool result may be triggering early termination.';
        }
        if ($forbiddenToolHits !== []) {
            $hints[] = 'BLOCKER — forbidden tools were called: '.implode(', ', $forbiddenToolHits).'. This likely violates an INV-2 read-only invariant.';
            $fixes[] = 'AdviceFyn::WRITE_TOOLS list is incomplete or AdviceFyn::buildToolList is leaking write tools. Verify against canonical contract spec/00-canonical.md.';
        }
        if (is_array($run->forbidden_hits) && $run->forbidden_hits !== []) {
            $hints[] = 'Forbidden phrases present in response: '.implode(', ', $run->forbidden_hits).'.';
            $fixes[] = 'Tighten the no-personalised-advice section of CoreIdentity / AdvicePromptBuilder. The model is leaking opinion phrasing forbidden by Rubric-A D1.';
        }
        if ($timingStatus === 'over_budget') {
            $hints[] = "Over timing budget by {$timingOverage}ms (budget {$budget}ms, actual {$duration}ms).";
            $fixes[] = 'Investigate tool latency — every additional tool call adds a roundtrip. If multiple list_records were called, consolidate via get_module_analysis (single call).';
        }
        if ($missingEventTypes !== []) {
            $hints[] = 'Missing required SSE event types: '.implode(', ', $missingEventTypes).'.';
            $fixes[] = 'HasAiChat may have aborted before emitting expected events. Check for early returns or exceptions in handleStream.';
        }

        $passes = $missingTools === []
            && $forbiddenToolHits === []
            && (! is_array($run->forbidden_hits) || $run->forbidden_hits === [])
            && $timingStatus !== 'over_budget'
            && $missingEventTypes === [];

        return [
            'passes' => $passes,
            'expected_tools' => $expectedTools,
            'actual_tools' => $actualTools,
            'missing_tools' => $missingTools,
            'extra_tools' => $extraTools,
            'forbidden_tool_hits' => $forbiddenToolHits,
            'forbidden_output_hits' => is_array($run->forbidden_hits) ? array_values($run->forbidden_hits) : [],
            'timing_budget_ms' => $budget,
            'duration_ms' => $duration,
            'timing_status' => $timingStatus,
            'timing_overage_ms' => $timingOverage,
            'expected_sse_event_types' => $expectedEventTypes,
            'actual_sse_event_types' => $actualEventTypes,
            'missing_sse_event_types' => $missingEventTypes,
            'diagnosis_hints' => $hints,
            'suggested_fixes' => $fixes,
        ];
    }
}
