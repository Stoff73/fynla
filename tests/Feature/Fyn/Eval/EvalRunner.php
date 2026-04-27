<?php

declare(strict_types=1);

namespace Tests\Feature\Fyn\Eval;

use Symfony\Component\Yaml\Yaml;

/**
 * Pest dataset provider + per-scenario runner for Rubric-B Mode 1 evals.
 *
 * Scenarios live at tests/Feature/Fyn/Eval/scenarios/{NN-category}/*.yaml
 * and are loaded into Pest via {@see scenarios()}.
 *
 * S1.1 ships the scaffold. The {@see run()} entry point throws a clear
 * "scaffold-only" error until Tasks 1.2+ wire seed → mocked-provider →
 * SSE-capture → assertion plumbing per the spec.
 */
final class EvalRunner
{
    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function scenarios(?string $category = null): iterable
    {
        $base = __DIR__.'/scenarios';
        $pattern = $category !== null
            ? "{$base}/{$category}/*.yaml"
            : "{$base}/*/*.yaml";

        $matches = glob($pattern) ?: [];

        foreach ($matches as $path) {
            $id = basename($path, '.yaml');
            $payload = Yaml::parseFile($path);

            if (! is_array($payload)) {
                throw new \RuntimeException("Scenario {$path} did not parse to an array.");
            }

            yield $id => [$id, $payload];
        }
    }

    /**
     * Run a single scenario and return its EvalResult.
     *
     * Wired in Task 1.2 onwards. At S1.1 the runner exists so the
     * `--testsuite=Eval` boots cleanly with zero scenarios — calling run()
     * directly is intentionally an error so partially-implemented scenarios
     * don't silently green.
     *
     * @param  array<string, mixed>  $scenario
     */
    public static function run(string $id, array $scenario, string $mode = 'mocked'): EvalResult
    {
        if (! in_array($mode, ['mocked', 'real'], true)) {
            throw new \InvalidArgumentException("Unknown eval mode '{$mode}'. Expected 'mocked' or 'real'.");
        }

        throw new \RuntimeException(
            "EvalRunner::run is scaffold-only at Sprint 1 Task 1.1. ".
            "Per-scenario execution lands in Task 1.2 (seed + fixtures + ".
            "MockedProviderClient wiring). Scenario id: '{$id}'."
        );
    }
}
