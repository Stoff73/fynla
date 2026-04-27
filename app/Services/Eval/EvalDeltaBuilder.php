<?php

declare(strict_types=1);

namespace App\Services\Eval;

use App\Constants\QuerySchemas;
use App\Models\EvalProviderRun;
use App\Services\AI\AdviceFyn;
use App\Services\AI\QueryClassifier;
use Tests\Feature\Fyn\Eval\AssertionHelpers;

/**
 * Builds the actual-vs-expected delta for a recorded eval provider run,
 * driving the admin dashboard's pass/fail verdict per scenario YAML.
 *
 * Wires the new AssertionHelpers methods (S1.2.l) to the captured run
 * data so re-recording a scenario produces a graded result against the
 * new YAML contract (classification_shape, response_mode, kyc_state,
 * per-tool result_path, structural SSE rules, assistant_text rules,
 * per-provider per-path timing budget).
 *
 * Legacy YAMLs (containing expected_advice_response, flat
 * expected_classifications, single-int timing_budget_ms, or list-shape
 * expected_sse_events) are detected and short-circuited with a
 * deprecation hint — those scenarios must be re-recorded against the
 * new shape before the dashboard can grade them.
 *
 * Source: April27Updates/eval-expectations-rewrite.md §7 + §9.6.
 */
final class EvalDeltaBuilder
{
    public function __construct(
        private readonly QueryClassifier $classifier,
    ) {}

    /**
     * @param  array<string, mixed>  $expected  parsed YAML expectations (new shape)
     * @return array<string, mixed>
     */
    public function build(EvalProviderRun $run, array $expected): array
    {
        // Reject legacy YAMLs up-front — re-record before grading.
        [$cleanShape, $deprecationMsg] = AssertionHelpers::assertNoDeprecatedKeys($expected);
        if (! $cleanShape) {
            return $this->shellDelta(false, [
                'deprecated_yaml' => $deprecationMsg,
            ], [
                'Re-record this scenario after the YAML is rewritten to the S1.2.l shape (see eval-expectations-rewrite.md §3).',
            ]);
        }

        // Compute derived actuals.
        $actualClassification = $this->classifier->classify((string) $run->user_message);
        $actualPrimary = $actualClassification['primary'] ?? '';
        $actualResponseMode = $actualPrimary !== '' ? AdviceFyn::classifyResponseMode($actualPrimary) : '';
        $actualEngineCallLevel = $actualPrimary !== '' ? AdviceFyn::engineCallLevel($actualPrimary) : '';

        // Normalise captured tool calls — DB stores `name`, helpers expect `tool`.
        $actualToolCalls = $this->normaliseToolCalls(is_array($run->tool_calls) ? $run->tool_calls : []);

        // Detect the dominant ToolResultContract path actually taken (used for
        // timing-budget lookup). Order: KYC blocked > success_false >
        // readiness_blocked > empty_state > happy.
        $detectedPath = $this->detectPath($expected, $actualToolCalls);

        // Run each assertion. Collect failures keyed by metric name.
        $failures = [];

        if (isset($expected['expected_classification_shape']) && is_array($expected['expected_classification_shape'])) {
            [$ok, $msg] = AssertionHelpers::assertClassificationShape($expected['expected_classification_shape'], $actualClassification);
            if (! $ok) {
                $failures['classification_shape'] = $msg;
            }
        }

        if (isset($expected['expected_response_mode']) && is_string($expected['expected_response_mode'])) {
            [$ok, $msg] = AssertionHelpers::assertResponseModeMatches($expected['expected_response_mode'], $actualResponseMode);
            if (! $ok) {
                $failures['response_mode'] = $msg;
            }
        }

        if (isset($expected['expected_engine_call_level']) && is_string($expected['expected_engine_call_level'])) {
            [$ok, $msg] = AssertionHelpers::assertEngineCallLevelMatches($expected['expected_engine_call_level'], $actualEngineCallLevel);
            if (! $ok) {
                $failures['engine_call_level'] = $msg;
            }
        }

        if (isset($expected['expected_tool_calls']) && is_array($expected['expected_tool_calls'])) {
            [$ok, $msg] = AssertionHelpers::assertToolCallsMatchRules($expected['expected_tool_calls'], $actualToolCalls);
            if (! $ok) {
                $failures['tool_calls'] = $msg;
            }
        }

        if (isset($expected['expected_tool_calls_absent']) && is_array($expected['expected_tool_calls_absent'])) {
            [$ok, $msg] = AssertionHelpers::assertToolCallsAbsent($expected['expected_tool_calls_absent'], $actualToolCalls);
            if (! $ok) {
                $failures['tool_calls_absent'] = $msg;
            }
        }

        // SSE — adapt {type: count} to a flat event list the helper expects.
        if (isset($expected['expected_sse_events']) && is_array($expected['expected_sse_events'])) {
            $actualEvents = $this->expandEventCounts(is_array($run->sse_event_types) ? $run->sse_event_types : []);
            [$ok, $msg] = AssertionHelpers::assertSseStructural($expected['expected_sse_events'], $actualEvents);
            if (! $ok) {
                $failures['sse_events'] = $msg;
            }
        }

        if (isset($expected['expected_assistant_text']) && is_array($expected['expected_assistant_text'])) {
            [$ok, $msg] = AssertionHelpers::assertAssistantTextRules($expected['expected_assistant_text'], (string) $run->assistant_text);
            if (! $ok) {
                $failures['assistant_text'] = $msg;
            }
        }

        if (isset($expected['timing_budget_ms']) && is_array($expected['timing_budget_ms'])) {
            [$ok, $msg] = AssertionHelpers::assertTimingBudgetWithinPath(
                $expected['timing_budget_ms'],
                (string) $run->provider,
                $detectedPath,
                (float) ($run->duration_ms ?? 0),
            );
            if (! $ok) {
                $failures['timing_budget'] = $msg;
            }
        }

        // Forbidden tools (legacy `forbidden_tools` key — still useful as a
        // quick redundancy check on top of expected_tool_calls_absent).
        $forbiddenToolHits = $this->collectForbiddenToolHits($expected['forbidden_tools'] ?? [], $actualToolCalls);
        if ($forbiddenToolHits !== []) {
            $failures['forbidden_tools'] = 'forbidden tools fired: '.implode(', ', $forbiddenToolHits);
        }

        // Forbidden output phrases — captured at record time into forbidden_hits.
        $forbiddenOutputHits = is_array($run->forbidden_hits) ? array_values($run->forbidden_hits) : [];
        if ($forbiddenOutputHits !== []) {
            $failures['forbidden_outputs'] = 'forbidden phrases in response: '.implode(', ', $forbiddenOutputHits);
        }

        // Hints + fixes from heuristic patterns. These supplement the structured
        // failures with operator-readable diagnoses.
        [$hints, $fixes] = $this->buildHintsAndFixes($run, $expected, $actualToolCalls, $failures, $detectedPath);

        return [
            'passes' => $failures === [],
            'failures' => $failures,
            'detected_result_path' => $detectedPath,
            'actual_classification' => $actualClassification,
            'actual_response_mode' => $actualResponseMode,
            'actual_engine_call_level' => $actualEngineCallLevel,
            'actual_tool_calls' => $actualToolCalls,
            'actual_sse_event_types' => is_array($run->sse_event_types) ? $run->sse_event_types : [],
            'forbidden_tool_hits' => $forbiddenToolHits,
            'forbidden_output_hits' => $forbiddenOutputHits,
            'duration_ms' => (int) ($run->duration_ms ?? 0),
            'diagnosis_hints' => $hints,
            'suggested_fixes' => $fixes,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rawCalls
     * @return list<array<string, mixed>>
     */
    private function normaliseToolCalls(array $rawCalls): array
    {
        $normalised = [];
        foreach ($rawCalls as $call) {
            if (! is_array($call)) {
                continue;
            }
            $name = (string) ($call['name'] ?? $call['tool'] ?? '');
            if ($name === '') {
                continue;
            }
            $args = is_array($call['args'] ?? null) ? $call['args'] : [];
            $resultRaw = $call['result'] ?? null;
            $resultPath = $this->detectResultPathFromString(is_string($resultRaw) ? $resultRaw : '');
            $message = $this->extractMessageFromResultString(is_string($resultRaw) ? $resultRaw : '');

            $normalised[] = [
                'tool' => $name,
                'args' => $args,
                'result_path' => $resultPath,
                'result' => ['message' => $message, 'raw' => $resultRaw],
            ];
        }

        return $normalised;
    }

    private function detectResultPathFromString(string $resultStr): string
    {
        // Captured tool result strings look like:
        //   "module: protection, success: false, message: Protection profile..."
        //   "module: investment, can_proceed: false, readiness_checks: ..."
        //   "accounts_count: 0, asset_allocation: {...}, ..."
        //   "has_goals: false, summary: ..."
        if (str_contains($resultStr, 'success: false')) {
            return 'success_false';
        }
        if (str_contains($resultStr, 'can_proceed: false')) {
            return 'readiness_blocked';
        }
        if (str_contains($resultStr, 'accounts_count: 0') || str_contains($resultStr, 'has_goals: false')) {
            return 'empty_state';
        }
        if ($resultStr === '') {
            return 'success';
        }

        return 'happy';
    }

    private function extractMessageFromResultString(string $resultStr): string
    {
        if (preg_match('/message:\s*(.+?)(?=,\s+\w+:|$)/', $resultStr, $matches) === 1) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Pick the dominant path the run actually took, used for timing-budget
     * lookup. Bias toward the most specific/blocking path that fired.
     *
     * @param  array<string, mixed>  $expected
     * @param  list<array<string, mixed>>  $actualToolCalls
     */
    private function detectPath(array $expected, array $actualToolCalls): string
    {
        // KYC-blocked is YAML-driven (no analysis tools fired by design).
        if (($expected['expected_kyc_state'] ?? null) === 'blocked') {
            return 'kyc_blocked';
        }

        // Out-of-remit refusal is short-circuited (no tools at all).
        if (($expected['expected_response_mode'] ?? null) === 'out_of_remit') {
            return 'factual';
        }

        // Capture-mode (data_entry) → success.
        if (($expected['expected_response_mode'] ?? null) === 'factual'
            && ($expected['expected_engine_call_level'] ?? null) === 'factual') {
            return 'success';
        }

        // Walk the actual tool result paths — first hit wins.
        foreach ($actualToolCalls as $call) {
            $path = $call['result_path'] ?? null;
            if ($path === 'success_false' || $path === 'readiness_blocked' || $path === 'empty_state') {
                return $path;
            }
        }

        return 'happy';
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{type: string}>
     */
    private function expandEventCounts(array $counts): array
    {
        $expanded = [];
        foreach ($counts as $type => $count) {
            for ($i = 0; $i < (int) $count; $i++) {
                $expanded[] = ['type' => (string) $type];
            }
        }

        return $expanded;
    }

    /**
     * @param  list<string>  $forbidden
     * @param  list<array<string, mixed>>  $actualToolCalls
     * @return list<string>
     */
    private function collectForbiddenToolHits(array $forbidden, array $actualToolCalls): array
    {
        $names = array_values(array_filter(array_map(fn ($c) => is_array($c) ? ($c['tool'] ?? null) : null, $actualToolCalls)));

        return array_values(array_intersect($forbidden, $names));
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  list<array<string, mixed>>  $actualToolCalls
     * @param  array<string, string>  $failures
     * @return array{0: list<string>, 1: list<string>}
     */
    private function buildHintsAndFixes(
        EvalProviderRun $run,
        array $expected,
        array $actualToolCalls,
        array $failures,
        string $detectedPath,
    ): array {
        $hints = [];
        $fixes = [];

        if ($detectedPath === 'kyc_blocked' && ! isset($failures['tool_calls_absent'])) {
            $hints[] = 'KYC-blocked path: the LLM correctly avoided analysis tools given the missing universal/module data.';
        }

        if (isset($failures['classification_shape'])) {
            $hints[] = 'Classification mismatch — the user message does not produce the YAML\'s expected primary/related/modules.';
            $fixes[] = 'Either widen QuerySchemas::KEYWORD_PATTERNS for the missing phrasing, or update the YAML to match what the classifier actually produces (verify via php artisan tinker → QueryClassifier::classify).';
        }

        if (isset($failures['response_mode']) || isset($failures['engine_call_level'])) {
            $hints[] = 'Response-mode / engine-call-level diverge from AdviceFyn maps for the actual primary classification.';
            $fixes[] = 'These maps are static — the divergence comes from the actual classification differing from expected. Fix the classification_shape first.';
        }

        if (isset($failures['tool_calls'])) {
            $hints[] = 'Required tool sequence missing or mismatched. Check QuerySchemas::REQUIRED_TOOLS for the expected primary.';
            if ($run->provider === 'xai') {
                $fixes[] = 'For xAI: verify XaiToolDefinitions exposes the same tool list as AiToolDefinitions and the request includes a non-empty tools array.';
            }
            $fixes[] = 'Inspect actual_tool_calls below — if a result_path is success_false, the agent\'s secondary profile gate fired (e.g. ProtectionAgent line 72, RetirementAgent line 101).';
        }

        if (isset($failures['tool_calls_absent'])) {
            $hints[] = 'BLOCKER — a tool that should have been suppressed (KYC-blocked, write-tool, navigate_to_page) was called.';
            $fixes[] = 'AdviceFyn::WRITE_TOOLS list or the KYC-blocked prompt instruction is leaking. Verify against canonical contract.';
        }

        if (isset($failures['forbidden_tools'])) {
            $hints[] = 'BLOCKER — write tools fired in advice mode (INV-2.1.2 violation).';
            $fixes[] = 'AdviceFyn::WRITE_TOOLS list incomplete OR AdviceFyn::buildToolList leaking. Cross-check with CANON spec.';
        }

        if (isset($failures['forbidden_outputs'])) {
            $hints[] = 'Forbidden phrases present in response (Rubric-A D1 violation).';
            $fixes[] = 'Tighten the no-personalised-advice section of CoreIdentity / AdvicePromptBuilder.';
        }

        if (isset($failures['timing_budget'])) {
            $hints[] = 'Over per-provider per-path timing budget for path "'.$detectedPath.'".';
            $fixes[] = 'Either consolidate tool calls (every additional tool adds a round-trip) or widen the budget if the path legitimately needs more time.';
        }

        if (isset($failures['sse_events'])) {
            $hints[] = 'SSE structural rule failed — see message for the specific count/type that diverged.';
            $fixes[] = 'HasAiChat may have aborted before emitting expected events, or emitted forbidden events (persona_state_change, handoff). Check handleStream.';
        }

        if (isset($failures['assistant_text'])) {
            $hints[] = 'Assistant text rule failed — required substring missing, forbidden substring present, or length out of bounds.';
            $fixes[] = 'For INV-2.3.3 signposting: only `recommendation` mode appends "For regulated advice...". Factual + out_of_remit must NOT include it.';
        }

        return [$hints, $fixes];
    }

    /**
     * @param  array<string, string>  $failures
     * @param  list<string>  $fixes
     * @return array<string, mixed>
     */
    private function shellDelta(bool $passes, array $failures, array $fixes): array
    {
        return [
            'passes' => $passes,
            'failures' => $failures,
            'detected_result_path' => 'unknown',
            'actual_classification' => null,
            'actual_response_mode' => null,
            'actual_engine_call_level' => null,
            'actual_tool_calls' => [],
            'actual_sse_event_types' => [],
            'forbidden_tool_hits' => [],
            'forbidden_output_hits' => [],
            'duration_ms' => 0,
            'diagnosis_hints' => array_values($failures),
            'suggested_fixes' => $fixes,
        ];
    }
}
