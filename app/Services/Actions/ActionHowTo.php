<?php

declare(strict_types=1);

namespace App\Services\Actions;

/**
 * An action's how-to as branches (CSJ 2026-09-28): the user's own records pick
 * which steps they see, and their own figures fill them. The ONE home for the
 * template grammar — the seeder parses with it, the card renders with it.
 *
 *   when has_workplace_pension:
 *   1. Ask {workplace_pension} to take {contribution_per_month_left} a month more.
 *   when band is higher or additional and has_personal_pension:
 *   2. Claim the other {extra_relief} on your Self Assessment return.
 *   always:
 *   3. Pay it in by {tax_year_end}.
 *
 * A condition is clauses joined by "and": `fact`, `not fact`,
 * `fact is a or b`, or `fact is not a or b`. A step whose placeholder has no
 * value is dropped, so a user never sees a brace or a blank.
 */
final class ActionHowTo
{
    /**
     * @return array<string, array{status: string, steps: list<array{when: string|null, text: string}>}>
     */
    public static function parse(string $markdown): array
    {
        $entries = [];
        $key = null;
        $when = null;
        foreach (preg_split('/\R/', $markdown) as $line) {
            if (preg_match('/^## ([a-z0-9_]+)\s*$/', $line, $m)) {
                $key = $m[1];
                $when = null;
                $entries[$key] = ['status' => 'draft', 'steps' => []];
            } elseif ($key === null) {
                continue;
            } elseif (preg_match('/^status:\s*(draft|approved)\s*$/', $line, $m)) {
                $entries[$key]['status'] = $m[1];
            } elseif (preg_match('/^always:\s*$/', $line)) {
                $when = null;
            } elseif (preg_match('/^when (.+):\s*$/', $line, $m)) {
                $when = trim($m[1]);
            } elseif (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
                $entries[$key]['steps'][] = ['when' => $when, 'text' => trim($m[1])];
            }
        }

        return $entries;
    }

    /**
     * @param  list<array{when?: string|null, text: string}|string>  $steps  stored steps (a bare string is unconditional)
     * @param  array<string, mixed>  $facts  raw values, for conditions
     * @param  array<string, string>  $text  display values, for placeholders
     * @return list<string>
     */
    public static function render(array $steps, array $facts, array $text): array
    {
        $out = [];
        foreach ($steps as $step) {
            $step = is_string($step) ? ['when' => null, 'text' => $step] : $step;
            if (! self::holds($step['when'] ?? null, $facts)) {
                continue;
            }
            $missing = false;
            $filled = preg_replace_callback('/\{([a-z0-9_]+)\}/', function ($m) use ($text, &$missing) {
                if (! isset($text[$m[1]]) || $text[$m[1]] === '') {
                    $missing = true;

                    return '';
                }

                return $text[$m[1]];
            }, (string) $step['text']);
            if (! $missing) {
                $out[] = $filled;
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $facts */
    public static function holds(?string $when, array $facts): bool
    {
        if ($when === null || $when === '') {
            return true;
        }
        foreach (preg_split('/\s+and\s+/', $when) as $clause) {
            $clause = trim($clause);
            if (preg_match('/^([a-z0-9_]+) is (not )?(.+)$/', $clause, $m)) {
                $options = array_map('trim', preg_split('/\s+or\s+/', $m[3]));
                if (in_array(self::scalar($facts[$m[1]] ?? ''), $options, true) === ($m[2] !== '')) {
                    return false;
                }
            } elseif (preg_match('/^not ([a-z0-9_]+)$/', $clause, $m)) {
                if (self::truthy($facts[$m[1]] ?? null)) {
                    return false;
                }
            } elseif (! self::truthy($facts[$clause] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** 1.0 compares as "1", so a count reads as it is written. */
    private static function scalar(mixed $v): string
    {
        return is_float($v) && floor($v) === $v ? (string) (int) $v : (string) $v;
    }

    private static function truthy(mixed $v): bool
    {
        return is_numeric($v) ? (float) $v > 0 : ! empty($v);
    }
}
