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
}
