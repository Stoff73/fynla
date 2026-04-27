<?php

declare(strict_types=1);

namespace Tests\Feature\Fyn\Eval;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Static helpers used by EvalRunner to evaluate per-metric pass/fail.
 *
 * Each helper returns [bool $passed, string $detail] so EvalRunner can
 * compose an EvalResult without knowing the specifics of each assertion.
 */
final class AssertionHelpers
{
    /**
     * Assert the actual tool calls match the expected list (name + arg-shape
     * subset, in order). Argument matching is a recursive subset: every key
     * present in $expectedArgs must equal the same key in $actualArgs.
     *
     * @param  list<array{tool: string, args?: array<string, mixed>}>  $expected
     * @param  list<array{tool: string, args?: array<string, mixed>}>  $actual
     * @return array{0: bool, 1: string}
     */
    public static function assertToolCallsMatch(array $expected, array $actual): array
    {
        if (count($expected) !== count($actual)) {
            return [false, 'expected '.count($expected).' tool calls, got '.count($actual)];
        }

        foreach ($expected as $i => $expectedCall) {
            $actualCall = $actual[$i] ?? null;
            if ($actualCall === null) {
                return [false, "missing tool call at index {$i}"];
            }

            if (($expectedCall['tool'] ?? null) !== ($actualCall['tool'] ?? null)) {
                return [false, "tool[{$i}] expected '{$expectedCall['tool']}', got '".($actualCall['tool'] ?? 'null')."'"];
            }

            $expectedArgs = $expectedCall['args'] ?? [];
            $actualArgs = $actualCall['args'] ?? [];
            if (! self::isSubsetMatch($expectedArgs, $actualArgs)) {
                return [false, "tool[{$i}] arg subset mismatch: expected ".json_encode($expectedArgs).' missing/divergent in '.json_encode($actualArgs)];
            }
        }

        return [true, ''];
    }

    /**
     * Assert the SSE event sequence matches expected types + fields + order.
     * Each expected event is a partial structure; every key present must
     * appear with the same value in the actual event.
     *
     * @param  list<array<string, mixed>>  $expected
     * @param  list<array<string, mixed>>  $actual
     * @return array{0: bool, 1: string}
     */
    public static function assertSseEventSequence(array $expected, array $actual): array
    {
        if (count($expected) !== count($actual)) {
            return [false, 'expected '.count($expected).' SSE events, got '.count($actual)];
        }

        foreach ($expected as $i => $expectedEvent) {
            $actualEvent = $actual[$i] ?? [];
            if (! self::isSubsetMatch($expectedEvent, $actualEvent)) {
                return [false, "SSE[{$i}] subset mismatch: expected ".json_encode($expectedEvent).' missing/divergent in '.json_encode($actualEvent)];
            }
        }

        return [true, ''];
    }

    /**
     * Assert the DB writes after the scenario produced expected rows. Each
     * key is a table name; each value is the expected count of rows scoped
     * to the user.
     *
     * @param  array<string, int>  $expected  table => expected_count_for_user
     * @return array{0: bool, 1: string}
     */
    public static function assertDbWrites(array $expected, User $user): array
    {
        foreach ($expected as $table => $count) {
            $actual = DB::table($table)->where('user_id', $user->id)->count();
            if ($actual !== $count) {
                return [false, "table {$table} expected {$count} rows for user {$user->id}, got {$actual}"];
            }
        }

        return [true, ''];
    }

    /**
     * Assert none of the forbidden patterns appear in the assistant text.
     *
     * @param  list<string>  $patterns  case-insensitive substrings or /regex/i
     * @return array{0: bool, 1: string}
     */
    public static function assertForbiddenOutputsAbsent(string $text, array $patterns): array
    {
        foreach ($patterns as $pattern) {
            $isRegex = str_starts_with($pattern, '/') && (substr_count($pattern, '/') >= 2);

            $hit = $isRegex
                ? (bool) preg_match($pattern, $text)
                : (stripos($text, $pattern) !== false);

            if ($hit) {
                return [false, "forbidden output present: '{$pattern}'"];
            }
        }

        return [true, ''];
    }

    /**
     * Assert every interpretive sentence in the response cites a value that
     * appears in the engine output payload (INV-2.3.2). The check is
     * heuristic: every £-figure or percentage in $text must also appear
     * somewhere in the JSON-encoded $engineOutput.
     *
     * @param  array<string, mixed>  $engineOutput
     * @return array{0: bool, 1: string}
     */
    public static function assertInterpretiveTextMapsToEngineSource(string $text, array $engineOutput): array
    {
        $haystack = json_encode($engineOutput) ?: '';

        // £ figures with optional thousands separators and decimals.
        if (preg_match_all('/£\s?([0-9][0-9,]*(?:\.[0-9]+)?)/u', $text, $m)) {
            foreach ($m[1] as $figure) {
                $normalised = str_replace(',', '', $figure);
                if (! str_contains($haystack, $normalised) && ! str_contains($haystack, $figure)) {
                    return [false, "£{$figure} in text not found in engine output"];
                }
            }
        }

        // Percentage figures (e.g. 4.5%, 25 %).
        if (preg_match_all('/([0-9]+(?:\.[0-9]+)?)\s?%/u', $text, $m)) {
            foreach ($m[1] as $pct) {
                if (! str_contains($haystack, $pct)) {
                    return [false, "{$pct}% in text not found in engine output"];
                }
            }
        }

        return [true, ''];
    }

    /**
     * Recursive subset-equality. Every key present in $expected must exist
     * in $actual with the same value (subset for nested arrays).
     *
     * @param  array<int|string, mixed>  $expected
     * @param  array<int|string, mixed>  $actual
     */
    private static function isSubsetMatch(array $expected, array $actual): bool
    {
        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $actual)) {
                return false;
            }

            if (is_array($value)) {
                if (! is_array($actual[$key]) || ! self::isSubsetMatch($value, $actual[$key])) {
                    return false;
                }
            } elseif ($actual[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    // ─── New asserters (S1.2.l YAML shape) ─────────────────────────
    //
    // The 10 rewritten scenarios in 01-query-types/ + 03-multi-entity/
    // moved to a multi-label classifier shape, response-mode + engine-
    // call-level + KYC-state assertions, per-tool result_path enums,
    // structural SSE assertions, and a per-provider per-path timing
    // budget. Helpers below correspond 1:1 to those YAML keys. The full
    // S1.7.a extension (per_turn, state_transition, parked_facts,
    // handoff_path, fragment-inheritance) is a separate task and lands
    // when the 48 NEW canonical/state-machine/handoff/resume YAMLs do.

    public const VALID_RESPONSE_MODES = ['recommendation', 'factual', 'out_of_remit'];

    public const VALID_ENGINE_CALL_LEVELS = ['holistic', 'module', 'factual'];

    public const VALID_KYC_STATES = ['passed', 'bypass', 'blocked'];

    public const VALID_RESULT_PATHS = ['success_false', 'readiness_blocked', 'empty_state', 'happy', 'success'];

    /**
     * Assert classification multi-label shape: primary (strict), related
     * (set equality), modules (set equality).
     *
     * @param  array{primary: string, related: list<string>, modules: list<string>}  $expected
     * @param  array{primary?: string, related?: list<string>, modules?: list<string>}  $actual
     * @return array{0: bool, 1: string}
     */
    public static function assertClassificationShape(array $expected, array $actual): array
    {
        $expectedPrimary = $expected['primary'] ?? null;
        $actualPrimary = $actual['primary'] ?? null;
        if ($expectedPrimary !== $actualPrimary) {
            return [false, "classification primary expected '{$expectedPrimary}', got '".($actualPrimary ?? 'null')."'"];
        }

        [$relatedOk, $relatedDetail] = self::compareSet('related', $expected['related'] ?? [], $actual['related'] ?? []);
        if (! $relatedOk) {
            return [false, $relatedDetail];
        }

        [$modulesOk, $modulesDetail] = self::compareSet('modules', $expected['modules'] ?? [], $actual['modules'] ?? []);
        if (! $modulesOk) {
            return [false, $modulesDetail];
        }

        return [true, ''];
    }

    /**
     * Assert response mode matches AdviceFyn::classifyResponseMode output.
     *
     * @return array{0: bool, 1: string}
     */
    public static function assertResponseModeMatches(string $expected, string $actual): array
    {
        if (! in_array($expected, self::VALID_RESPONSE_MODES, true)) {
            return [false, "expected response_mode '{$expected}' is not a valid mode (".implode(', ', self::VALID_RESPONSE_MODES).')'];
        }

        if ($expected !== $actual) {
            return [false, "response_mode expected '{$expected}', got '{$actual}'"];
        }

        return [true, ''];
    }

    /**
     * Assert engine call level matches AdviceFyn::engineCallLevel output.
     *
     * @return array{0: bool, 1: string}
     */
    public static function assertEngineCallLevelMatches(string $expected, string $actual): array
    {
        if (! in_array($expected, self::VALID_ENGINE_CALL_LEVELS, true)) {
            return [false, "expected engine_call_level '{$expected}' is not a valid level (".implode(', ', self::VALID_ENGINE_CALL_LEVELS).')'];
        }

        if ($expected !== $actual) {
            return [false, "engine_call_level expected '{$expected}', got '{$actual}'"];
        }

        return [true, ''];
    }

    /**
     * Assert KYC state matches the KycGateChecker::check return shape.
     *
     * For state=passed: actual.passed must be true and missing must be empty.
     * For state=bypass: actual.passed must be true and prompt_text must be empty.
     * For state=blocked: actual.passed must be false and every label in
     * expectedMissing must appear (substring) in actual.missing.
     *
     * @param  list<array{label: string, route?: string}>  $expectedMissing
     * @param  array{passed: bool, missing: list<string>, prompt_text?: string}  $actualKyc
     * @return array{0: bool, 1: string}
     */
    public static function assertKycState(string $expectedState, array $expectedMissing, array $actualKyc): array
    {
        if (! in_array($expectedState, self::VALID_KYC_STATES, true)) {
            return [false, "expected kyc_state '{$expectedState}' is not a valid state (".implode(', ', self::VALID_KYC_STATES).')'];
        }

        $passed = $actualKyc['passed'] ?? null;
        $missing = $actualKyc['missing'] ?? [];
        $promptText = $actualKyc['prompt_text'] ?? '';

        switch ($expectedState) {
            case 'passed':
                if ($passed !== true) {
                    return [false, 'kyc_state passed expected actual.passed=true, got '.var_export($passed, true)];
                }
                if (! empty($missing)) {
                    return [false, "kyc_state passed expected empty missing, got ".json_encode($missing)];
                }

                return [true, ''];

            case 'bypass':
                if ($passed !== true) {
                    return [false, 'kyc_state bypass expected actual.passed=true, got '.var_export($passed, true)];
                }
                if ($promptText !== '') {
                    return [false, "kyc_state bypass expected empty prompt_text, got ".substr($promptText, 0, 80).'…'];
                }

                return [true, ''];

            case 'blocked':
                if ($passed !== false) {
                    return [false, 'kyc_state blocked expected actual.passed=false, got '.var_export($passed, true)];
                }
                foreach ($expectedMissing as $expected) {
                    $needle = $expected['label'] ?? '';
                    if ($needle === '') {
                        continue;
                    }
                    $hit = false;
                    foreach ($missing as $actualLabel) {
                        if (stripos($actualLabel, $needle) !== false) {
                            $hit = true;
                            break;
                        }
                    }
                    if (! $hit) {
                        return [false, "kyc_state blocked: expected missing '{$needle}' not found in actual.missing ".json_encode($missing)];
                    }
                }

                return [true, ''];
        }

        return [true, '']; // unreachable
    }

    /**
     * Assert tool calls against the new rule-based shape. Each expected
     * entry has:
     *   - tool: string (required)
     *   - args: array<string,mixed> (optional, recursive subset match)
     *   - required: bool (true = must appear, false = conditional)
     *   - result_path: enum (optional, asserted against actual.result_path
     *     when present in $actualToolCalls[i].result_path)
     *   - result_message_contains: string (optional substring asserted
     *     against actual.result.message)
     *
     * No order assertion. Required tools must each match at least one
     * actual call. Conditional tools are documentation-only — never fail.
     *
     * @param  list<array<string, mixed>>  $expected
     * @param  list<array<string, mixed>>  $actual
     * @return array{0: bool, 1: string}
     */
    public static function assertToolCallsMatchRules(array $expected, array $actual): array
    {
        foreach ($expected as $i => $rule) {
            $required = (bool) ($rule['required'] ?? false);
            if (! $required) {
                continue; // conditional — never fail on absence
            }

            $tool = $rule['tool'] ?? null;
            $expectedArgs = $rule['args'] ?? [];
            $expectedPath = $rule['result_path'] ?? null;
            $expectedMessageSubstring = $rule['result_message_contains'] ?? null;

            if ($expectedPath !== null && ! in_array($expectedPath, self::VALID_RESULT_PATHS, true)) {
                return [false, "tool[{$i}] '{$tool}' has invalid result_path '{$expectedPath}' (allowed: ".implode(', ', self::VALID_RESULT_PATHS).')'];
            }

            $found = false;
            foreach ($actual as $call) {
                if (($call['tool'] ?? null) !== $tool) {
                    continue;
                }
                $actualArgs = $call['args'] ?? [];
                if (! self::isSubsetMatch($expectedArgs, $actualArgs)) {
                    continue;
                }
                if ($expectedPath !== null && ($call['result_path'] ?? null) !== $expectedPath) {
                    continue;
                }
                if ($expectedMessageSubstring !== null) {
                    $message = $call['result']['message'] ?? '';
                    if (stripos($message, $expectedMessageSubstring) === false) {
                        continue;
                    }
                }
                $found = true;
                break;
            }

            if (! $found) {
                $detail = "tool '{$tool}'";
                if (! empty($expectedArgs)) {
                    $detail .= ' args='.json_encode($expectedArgs);
                }
                if ($expectedPath !== null) {
                    $detail .= " result_path={$expectedPath}";
                }
                if ($expectedMessageSubstring !== null) {
                    $detail .= " result.message contains '{$expectedMessageSubstring}'";
                }

                return [false, "required {$detail} not found in actual tool calls"];
            }
        }

        return [true, ''];
    }

    /**
     * Assert none of the listed tools appear in actual tool calls.
     *
     * @param  list<string>  $forbiddenTools
     * @param  list<array<string, mixed>>  $actual
     * @return array{0: bool, 1: string}
     */
    public static function assertToolCallsAbsent(array $forbiddenTools, array $actual): array
    {
        foreach ($forbiddenTools as $tool) {
            foreach ($actual as $call) {
                if (($call['tool'] ?? null) === $tool) {
                    return [false, "forbidden tool '{$tool}' was called"];
                }
            }
        }

        return [true, ''];
    }

    /**
     * Assert SSE event stream against the structural rule block.
     *
     * Rules:
     *   - must_contain_types: list<string>           — every type must appear ≥1×
     *   - must_emit_exactly_once: list<string>       — each type must appear exactly 1×
     *   - must_emit_exactly: array<string,int>       — type → exact count
     *   - must_not_emit: list<string>                — none of these types may appear
     *   - content_event_minimum: int                 — `content` events ≥ N
     *   - tool_use_count_min: int                    — tool_use events ≥ N
     *   - tool_use_count_max: int                    — tool_use events ≤ N
     *
     * @param  array<string, mixed>  $rules
     * @param  list<array{type: string, ...}>  $actualEvents
     * @return array{0: bool, 1: string}
     */
    public static function assertSseStructural(array $rules, array $actualEvents): array
    {
        $counts = [];
        foreach ($actualEvents as $event) {
            $type = $event['type'] ?? null;
            if ($type === null) {
                continue;
            }
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        foreach ($rules['must_contain_types'] ?? [] as $type) {
            if (($counts[$type] ?? 0) < 1) {
                return [false, "SSE missing type '{$type}'"];
            }
        }

        foreach ($rules['must_emit_exactly_once'] ?? [] as $type) {
            if (($counts[$type] ?? 0) !== 1) {
                return [false, "SSE type '{$type}' expected exactly 1, got ".($counts[$type] ?? 0)];
            }
        }

        foreach ($rules['must_emit_exactly'] ?? [] as $type => $expectedCount) {
            if (($counts[$type] ?? 0) !== $expectedCount) {
                return [false, "SSE type '{$type}' expected exactly {$expectedCount}, got ".($counts[$type] ?? 0)];
            }
        }

        foreach ($rules['must_not_emit'] ?? [] as $type) {
            if (($counts[$type] ?? 0) > 0) {
                return [false, "SSE forbidden type '{$type}' appeared ".$counts[$type].' time(s)'];
            }
        }

        if (isset($rules['content_event_minimum'])) {
            $min = (int) $rules['content_event_minimum'];
            if (($counts['content'] ?? 0) < $min) {
                return [false, "SSE content events expected ≥{$min}, got ".($counts['content'] ?? 0)];
            }
        }

        if (isset($rules['tool_use_count_min'])) {
            $min = (int) $rules['tool_use_count_min'];
            if (($counts['tool_use'] ?? 0) < $min) {
                return [false, "SSE tool_use events expected ≥{$min}, got ".($counts['tool_use'] ?? 0)];
            }
        }

        if (isset($rules['tool_use_count_max'])) {
            $max = (int) $rules['tool_use_count_max'];
            if (($counts['tool_use'] ?? 0) > $max) {
                return [false, "SSE tool_use events expected ≤{$max}, got ".$counts['tool_use']];
            }
        }

        return [true, ''];
    }

    /**
     * Assert assistant text against the rule block:
     *   - must_contain_substrings: list<string>          — all must appear
     *   - must_contain_at_least_one_of: list<list<string>> — each inner list must have ≥1 hit
     *   - must_not_contain_substrings: list<string>      — none may appear
     *   - exact_match: string                            — strict equality (canonical refusal)
     *   - minimum_length_chars: int
     *   - maximum_length_chars: int
     *
     * @param  array<string, mixed>  $rules
     * @return array{0: bool, 1: string}
     */
    public static function assertAssistantTextRules(array $rules, string $actualText): array
    {
        if (isset($rules['exact_match'])) {
            if ($actualText !== $rules['exact_match']) {
                return [false, "assistant_text exact_match failed (expected length ".strlen($rules['exact_match']).", got ".strlen($actualText).')'];
            }
        }

        foreach ($rules['must_contain_substrings'] ?? [] as $needle) {
            if (stripos($actualText, $needle) === false) {
                return [false, "assistant_text missing required substring '{$needle}'"];
            }
        }

        foreach ($rules['must_contain_at_least_one_of'] ?? [] as $groupIndex => $group) {
            if (! is_array($group)) {
                return [false, "assistant_text must_contain_at_least_one_of[{$groupIndex}] must be a list of substrings"];
            }
            $hit = false;
            foreach ($group as $needle) {
                if (stripos($actualText, (string) $needle) !== false) {
                    $hit = true;
                    break;
                }
            }
            if (! $hit) {
                return [false, "assistant_text none of group ".json_encode($group)." matched"];
            }
        }

        foreach ($rules['must_not_contain_substrings'] ?? [] as $needle) {
            if (stripos($actualText, $needle) !== false) {
                return [false, "assistant_text contains forbidden substring '{$needle}'"];
            }
        }

        if (isset($rules['minimum_length_chars'])) {
            $min = (int) $rules['minimum_length_chars'];
            if (strlen($actualText) < $min) {
                return [false, "assistant_text length ".strlen($actualText)." below minimum {$min}"];
            }
        }

        if (isset($rules['maximum_length_chars'])) {
            $max = (int) $rules['maximum_length_chars'];
            if (strlen($actualText) > $max) {
                return [false, "assistant_text length ".strlen($actualText)." above maximum {$max}"];
            }
        }

        return [true, ''];
    }

    /**
     * Assert actual duration falls within the per-provider per-path budget.
     *
     * Budget shape: { anthropic: { path: ms, ... }, xai: { path: ms, ... } }
     *
     * @param  array<string, array<string, int>>  $budget
     * @return array{0: bool, 1: string}
     */
    public static function assertTimingBudgetWithinPath(array $budget, string $provider, string $path, float $actualMs): array
    {
        $providerBudget = $budget[$provider] ?? null;
        if (! is_array($providerBudget)) {
            return [false, "timing_budget_ms missing provider '{$provider}'"];
        }

        $pathBudget = $providerBudget[$path] ?? null;
        if ($pathBudget === null) {
            return [false, "timing_budget_ms.{$provider} missing path '{$path}'"];
        }

        if ($actualMs > (float) $pathBudget) {
            return [false, "{$provider}/{$path} actual {$actualMs}ms exceeds budget {$pathBudget}ms"];
        }

        return [true, ''];
    }

    /**
     * Reject deprecated YAML keys with a clear migration message. Returns
     * [true, ''] when no deprecated keys are present.
     *
     * Deprecated keys (S1.2.l):
     *   - expected_advice_response       → removed (S1.6.a removed the SSE event)
     *   - expected_classifications: [..] → expected_classification_shape: {primary, related, modules}
     *   - timing_budget_ms: <int>        → { anthropic: {path: ms}, xai: {path: ms} }
     *   - expected_sse_events as list    → structural rules block
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: bool, 1: string}
     */
    public static function assertNoDeprecatedKeys(array $payload): array
    {
        if (array_key_exists('expected_advice_response', $payload)) {
            return [false, "deprecated key 'expected_advice_response' (S1.6.a removed the SSE event — use expected_assistant_text.must_contain_substrings for INV-2.3.3 signposting instead)"];
        }

        if (array_key_exists('expected_classifications', $payload)) {
            return [false, "deprecated key 'expected_classifications' — use expected_classification_shape: {primary, related, modules}"];
        }

        if (isset($payload['timing_budget_ms']) && (is_int($payload['timing_budget_ms']) || is_string($payload['timing_budget_ms']))) {
            return [false, "deprecated shape: timing_budget_ms must be a per-provider per-path map { anthropic: {path: ms}, xai: {path: ms} }"];
        }

        if (isset($payload['expected_sse_events']) && is_array($payload['expected_sse_events']) && array_is_list($payload['expected_sse_events'])) {
            return [false, "deprecated shape: expected_sse_events must be a structural rules block (must_contain_types, must_emit_exactly_once, etc.), not a list of events"];
        }

        return [true, ''];
    }

    /**
     * @param  list<string>  $expected
     * @param  list<string>  $actual
     * @return array{0: bool, 1: string}
     */
    private static function compareSet(string $label, array $expected, array $actual): array
    {
        $expectedSet = array_values(array_unique($expected));
        $actualSet = array_values(array_unique($actual));
        sort($expectedSet);
        sort($actualSet);

        if ($expectedSet !== $actualSet) {
            $missing = array_values(array_diff($expectedSet, $actualSet));
            $extra = array_values(array_diff($actualSet, $expectedSet));
            $detail = "{$label} set mismatch";
            if ($missing !== []) {
                $detail .= '; missing='.json_encode($missing);
            }
            if ($extra !== []) {
                $detail .= '; extra='.json_encode($extra);
            }

            return [false, $detail];
        }

        return [true, ''];
    }
}
