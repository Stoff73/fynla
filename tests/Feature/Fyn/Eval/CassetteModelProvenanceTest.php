<?php

declare(strict_types=1);

/**
 * Fail-loud guard: recorded Mode-1 cassettes must live under the model
 * directory matching the CURRENTLY configured chat model for their
 * provider.
 *
 * MockedProviderClient::defaultFixturePath() and EvalRecordCommand both
 * resolve fixtures at fixtures/{provider}/{model}/{scenario}.jsonl where
 * {model} = config("services.{provider}.chat_model"). When the configured
 * model changes (e.g. the xAI cutover grok-4-1-fast-reasoning → grok-4.3),
 * the old cassette directory is silently orphaned: the replay layer looks
 * under the new model dir, finds nothing, and Mode-1 evals become
 * fixture-missing without any signal.
 *
 * This test makes that state impossible to ship silently. It is EXPECTED
 * to be RED whenever a provider's cassettes are stranded under a stale
 * model directory, and only goes GREEN once they have been re-recorded
 * (php artisan eval:record --providers=<provider>) under the configured
 * model and the stale directory removed. Per the project rule that a
 * failing eval surfaces a real engineering issue, do NOT skip or silence
 * this — fix the provenance.
 */

use Tests\Feature\Fyn\Eval\MockedProviderClient;

it('keeps every recorded cassette under the configured model directory for its provider', function (): void {
    // CSJ standing decision (23 May 2026): cassette re-recording is deferred
    // until after the full Fyn refactor lands. Skip until then so this stops
    // surfacing in every Pest run. Re-enable by deleting this skip + running
    // `php artisan eval:record --providers=xai` to re-record under the
    // currently configured model. The provenance check itself is unchanged
    // and will run again once the skip is removed.
    test()->markTestSkipped('Deferred until post-refactor cassette re-record (CSJ 2026-05-23).');

    $fixturesRoot = base_path('tests/Feature/Fyn/Eval/fixtures');

    if (! is_dir($fixturesRoot)) {
        // No fixtures recorded yet — nothing can be orphaned.
        expect(true)->toBeTrue();

        return;
    }

    // Same provider whitelist MockedProviderClient enforces.
    $providers = ['anthropic', 'xai'];

    $violations = [];

    foreach ($providers as $provider) {
        $providerDir = "{$fixturesRoot}/{$provider}";
        if (! is_dir($providerDir)) {
            continue;
        }

        $configuredModel = (string) config("services.{$provider}.chat_model");

        foreach (glob("{$providerDir}/*", GLOB_ONLYDIR) ?: [] as $modelDir) {
            $cassettes = glob("{$modelDir}/*.jsonl") ?: [];
            if ($cassettes === []) {
                continue;
            }

            $modelName = basename($modelDir);
            if ($modelName !== $configuredModel) {
                $violations[] = sprintf(
                    "%s: %d cassette(s) stranded under '%s/%s' but the configured ".
                    "%s.chat_model is '%s'. Re-record with `php artisan eval:record ".
                    "--providers=%s` and remove the stale '%s' directory.",
                    $provider,
                    count($cassettes),
                    $provider,
                    $modelName,
                    $provider,
                    $configuredModel,
                    $provider,
                    $modelName,
                );
            }
        }
    }

    expect($violations)->toBe(
        [],
        'Orphaned eval cassettes detected (Mode-1 evals would be silently '.
        "fixture-missing for these providers):\n  - ".implode("\n  - ", $violations)
    );
});

it('confirms MockedProviderClient resolves the configured model path', function (): void {
    // Pins the contract this guard relies on: the replay path is keyed on
    // the configured chat model. If this resolution changes, the guard
    // above must change with it.
    $path = MockedProviderClient::defaultFixturePath('xai', 'grok-4.3', 'some_scenario');

    expect($path)->toContain('fixtures/xai/grok-4.3/some_scenario.jsonl');
});
