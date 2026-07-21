<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Services\AI\Memory\Procedural\Procedure;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Pure reader for the onboarding workflow procedure body.
 *
 * Parses the single fenced ```yaml``` block in a `workflow`-kind procedure's
 * body into the extracted DATA-subset transition table (see Phase 4d plan §
 * extraction boundary). PHP-only fields (callable next / prompt_text, skip_if)
 * are NOT carried by the corpus — they are re-attached by OnboardingStateMachine
 * at merge time. Mirrors AiToolDefinitions::toolFromCorpus (4b).
 *
 * Returns null (never throws) on any error so OnboardingStateMachine falls back
 * to its in-code table — onboarding always has a working state machine.
 */
final class OnboardingWorkflowTable
{
    /**
     * @return array<string, array<string, mixed>>|null
     */
    public static function fromProcedure(Procedure $procedure): ?array
    {
        try {
            $yaml = self::extractYamlBlock($procedure->body);
            if ($yaml === null) {
                return null;
            }

            $parsed = Yaml::parse($yaml);
            if (! is_array($parsed) || $parsed === []) {
                return null;
            }

            $table = [];
            foreach ($parsed as $stateId => $state) {
                // Reject list-shaped YAML (numeric keys) and non-mapping states.
                if (! is_string($stateId) || ! is_array($state)) {
                    return null;
                }
                // Every state must declare a turn_type (cheap shape guard).
                if (! array_key_exists('turn_type', $state) || ! is_string($state['turn_type'])) {
                    return null;
                }
                $table[$stateId] = $state;
            }

            return $table;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Strip the leading ```yaml fence and trailing ``` fence; return inner YAML or null. */
    private static function extractYamlBlock(string $body): ?string
    {
        if (preg_match('/```yaml\s*\n(.*?)\n```/s', $body, $m) !== 1) {
            return null;
        }

        $inner = trim($m[1]);

        return $inner === '' ? null : $inner;
    }
}
