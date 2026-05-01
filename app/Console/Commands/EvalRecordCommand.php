<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EvalProviderRun;
use App\Models\EvalRecordingSession;
use App\Models\User;
use App\Services\Eval\EvalHttpDriver;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Records eval fixtures by driving a JSON scenario end-to-end against the
 * local Laravel server via {@see EvalHttpDriver} — the same HTTP surface a
 * real browser session uses: login → create conversation → SSE message →
 * logout (4 HTTP calls). The engine/gate trace is handed off in-process
 * via the file cache, not a separate HTTP fetch. The driver owns
 * provider switching and snapshot-based DB write detection; per the
 * canonical contract (§0.1), the driver does NOT reset the persona —
 * any post-capture reset for mutating scenarios runs here in the caller
 * AFTER EvalProviderRun rows are persisted.
 *
 * The command's job is the orchestration shell:
 *   1. Load + validate the scenario JSON.
 *   2. Look up the persona's preview user (the durable seed).
 *   3. Persist an EvalRecordingSession row with persona + http_log.
 *   4. For each provider, call EvalHttpDriver::run and persist an
 *      EvalProviderRun with assistant_text, tool_calls, engine_trace,
 *      db_writes, and the JSONL fixture path.
 *   5. Render a comparison table.
 *
 * Usage:
 *   php artisan eval:record mitchell_advice_protection_cover
 *   php artisan eval:record mitchell_advice_protection_cover --providers=anthropic
 *   php artisan eval:record mitchell_advice_protection_cover --providers=anthropic --model=claude-haiku-4-5-20251001
 *   php artisan eval:record mitchell_advice_protection_cover --dry-run
 *
 * Fixtures land at:
 *   tests/Feature/Fyn/Eval/fixtures/{provider}/{model}/{scenario_id}.jsonl
 */
final class EvalRecordCommand extends Command
{
    protected $signature = 'eval:record
        {scenario : Scenario id (e.g. mitchell_advice_protection_cover)}
        {--providers=anthropic,xai : Comma-separated providers (anthropic|xai)}
        {--model= : Override the model id (only valid with a single provider)}
        {--dry-run : Validate setup without invoking the providers}';

    protected $description = 'Record an eval scenario via the HTTP loop, persisting a forensic record + JSONL fixture per provider.';

    private const SCENARIO_ROOT = 'tests/Feature/Fyn/Eval/scenarios';

    private const FIXTURE_ROOT = 'tests/Feature/Fyn/Eval/fixtures';

    private const SUPPORTED_PROVIDERS = ['anthropic', 'xai'];

    public function handle(): int
    {
        $scenarioId = (string) $this->argument('scenario');
        $providersOption = (string) $this->option('providers');
        $dryRun = (bool) $this->option('dry-run');
        $modelOverride = (string) ($this->option('model') ?? '');

        $providers = array_values(array_filter(array_map('trim', explode(',', $providersOption))));
        if ($providers === []) {
            $this->error('--providers must be non-empty.');

            return self::INVALID;
        }

        foreach ($providers as $p) {
            if (! in_array($p, self::SUPPORTED_PROVIDERS, true)) {
                $this->error("Unsupported provider '{$p}'. Allowed: ".implode(', ', self::SUPPORTED_PROVIDERS));

                return self::INVALID;
            }
        }

        if ($modelOverride !== '' && count($providers) > 1) {
            $this->error('--model is only valid with a single provider.');

            return self::INVALID;
        }

        $scenarioPath = $this->locateScenario($scenarioId);
        $scenarioJson = (string) file_get_contents($scenarioPath);
        $scenario = json_decode($scenarioJson, true);
        if (! is_array($scenario)) {
            $this->error("Scenario {$scenarioPath} did not parse to an array.");

            return self::FAILURE;
        }
        $scenario['id'] = $scenario['id'] ?? $scenarioId;

        $turns = $scenario['input']['turns'] ?? [];
        if (! is_array($turns) || $turns === []) {
            $this->error("Scenario {$scenarioId} has no input.turns.");

            return self::FAILURE;
        }

        $persona = $scenario['persona'] ?? null;
        if (! is_string($persona) || $persona === '') {
            $this->error("Scenario {$scenarioId} has no persona.");

            return self::FAILURE;
        }

        $personaUser = User::where('is_preview_user', true)
            ->where('preview_persona_id', $persona)
            ->first();
        if ($personaUser === null) {
            $this->error("Persona '{$persona}' has no seeded preview user. Run: php artisan db:seed --class=PreviewUserSeeder --force");

            return self::FAILURE;
        }

        $forbiddenOutputs = $scenario['expected_assistant_text']['must_not_contain_substrings'] ?? [];
        if (! is_array($forbiddenOutputs)) {
            $forbiddenOutputs = [];
        }

        $this->info(str_repeat('=', 70));
        $this->info("Eval recording — {$scenarioId}");
        $this->info(str_repeat('=', 70));
        $this->line("Scenario:   {$scenarioPath}");
        $this->line("Persona:    {$persona} (user id={$personaUser->id})");
        $this->line('Providers:  '.implode(', ', $providers));
        $this->line('Turns:      '.count($turns));
        $this->line('Dry-run:    '.($dryRun ? 'YES — no provider calls' : 'NO — will hit live providers via HTTP'));
        $this->newLine();

        [$branch, $sha] = $this->resolveGitInfo();

        $session = EvalRecordingSession::create([
            'scenario_id' => $scenarioId,
            'scenario_path' => $scenarioPath,
            'scenario_yaml' => $scenarioJson,
            'preview_user_id' => $personaUser->id,
            'persona' => $persona,
            'start_state_snapshot' => [],
            'http_log' => [],
            'fynla_branch' => $branch,
            'fynla_sha' => $sha,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $this->info("Session #{$session->id} — persona={$persona} (user id={$personaUser->id})");
        $this->newLine();

        $exitCode = self::SUCCESS;
        $summaries = [];

        foreach ($providers as $provider) {
            $model = $this->resolveModel($provider, $modelOverride);
            $run = $this->recordOne(
                session: $session,
                scenario: $scenario,
                provider: $provider,
                model: $model,
                forbiddenOutputs: $forbiddenOutputs,
                dryRun: $dryRun,
            );

            if ($run === null) {
                $exitCode = self::FAILURE;

                continue;
            }

            $summaries[] = $run;
        }

        $session->update([
            'status' => $exitCode === self::SUCCESS ? 'completed' : 'failed',
            'completed_at' => now(),
        ]);

        if (! $dryRun && count($summaries) > 0) {
            $this->renderComparisonTable($scenarioId, $session, $summaries);
        }

        $this->newLine();
        $this->info("Session #{$session->id} {$session->status}.");

        return $exitCode;
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  list<string>  $forbiddenOutputs
     */
    private function recordOne(
        EvalRecordingSession $session,
        array $scenario,
        string $provider,
        string $model,
        array $forbiddenOutputs,
        bool $dryRun,
    ): ?EvalProviderRun {
        $this->newLine();
        $this->info(str_repeat('-', 70));
        $this->info(">> {$provider} / {$model}");
        $this->info(str_repeat('-', 70));
        $this->line('Fixture:    '.$this->fixturePath($provider, $model, (string) $scenario['id']));

        if ($dryRun) {
            $this->warn('Dry-run — skipping HTTP loop.');

            return EvalProviderRun::create([
                'eval_recording_session_id' => $session->id,
                'provider' => $provider,
                'model' => $model,
                'conversation_id' => 0,
                'user_message' => '(dry-run)',
                'assistant_text' => null,
                'tool_calls' => [],
                'sse_event_count' => 0,
                'sse_event_types' => [],
                'forbidden_hits' => [],
                'db_writes_made' => [],
                'engine_trace' => [],
                'end_state_snapshot' => [],
                'fixture_path' => null,
                'duration_ms' => 0,
                'started_at' => now(),
                'completed_at' => now(),
            ]);
        }

        $startedAt = now();
        $startMicrotime = microtime(true);

        try {
            $result = app(EvalHttpDriver::class)->run(
                scenario: $scenario,
                provider: $provider,
                model: $model,
            );

            $durationMs = (int) ((microtime(true) - $startMicrotime) * 1000);
            $events = $result['events'];
            $assembledContent = $this->assembleContent($events);

            $userMessages = [];
            foreach ($scenario['input']['turns'] ?? [] as $turn) {
                if (is_array($turn) && isset($turn['user']) && is_string($turn['user'])) {
                    $userMessages[] = $turn['user'];
                }
            }

            $fixturePath = $this->writeFixture(
                provider: $provider,
                model: $model,
                scenarioId: (string) $scenario['id'],
                events: $events,
                conversationId: (int) ($result['conversation_id'] ?? 0),
                durationMs: $durationMs,
            );

            $session->update([
                'http_log' => array_merge(is_array($session->http_log) ? $session->http_log : [], $result['http_log']),
            ]);

            $run = EvalProviderRun::create([
                'eval_recording_session_id' => $session->id,
                'provider' => $provider,
                'model' => $model,
                'conversation_id' => (int) ($result['conversation_id'] ?? 0),
                'user_message' => implode("\n---\n", $userMessages),
                'assistant_text' => $assembledContent,
                'tool_calls' => $this->extractToolCallsFromEvents($events),
                'sse_event_count' => count($events),
                'sse_event_types' => $this->countEventTypes($events),
                'forbidden_hits' => $this->detectForbiddenOutputs($assembledContent, $forbiddenOutputs),
                'db_writes_made' => $result['db_writes'] ?? [],
                'engine_trace' => $result['engine_trace'] ?? [],
                'end_state_snapshot' => $result['end_state'] ?? [],
                'fixture_path' => $fixturePath,
                'duration_ms' => $durationMs,
                'started_at' => $startedAt,
                'completed_at' => now(),
            ]);

            $this->newLine();
            $this->info('Captured '.count($events)." SSE events in {$durationMs}ms.");
            $this->info("Run #{$run->id} persisted; fixture: {$fixturePath}");

            $writes = $result['db_writes'] ?? [];
            if (! empty($writes)) {
                $this->line('DB writes:  '.count($writes).' diff entries.');
            } else {
                $this->line('DB writes:  none.');
            }

            // Canonical 0.1 — reset persona ONLY when an eval has actually
            // changed data on the persona record, AFTER EvalProviderRun
            // is persisted so the forensic chain is captured first.
            $this->resetPersonaIfMutating($writes, (string) ($scenario['persona'] ?? ''));

            return $run;
        } catch (\Throwable $e) {
            $this->error("[{$provider}] Recording failed: ".$e->getMessage());
            $this->error($e->getFile().':'.$e->getLine());

            return null;
        }
    }

    /**
     * Canonical 0.1 reset orchestration: invokes `preview:reset {persona}`
     * if and only if the captured `$writes` diff is non-empty AND a
     * persona is supplied. Caller is responsible for calling this AFTER
     * `EvalProviderRun::create()` so the forensic chain is persisted
     * before any cleanup runs.
     *
     * Public so the unit test can exercise it directly without driving
     * the whole `recordOne` pipeline.
     *
     * @param  array<string, mixed>  $writes
     */
    public function resetPersonaIfMutating(array $writes, string $persona): void
    {
        if (empty($writes) || $persona === '') {
            return;
        }

        $this->info('Mutating recording detected '.count($writes)." diff entries — resetting persona '{$persona}'.");
        \Illuminate\Support\Facades\Artisan::call('preview:reset', ['persona' => $persona]);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function extractToolCallsFromEvents(array $events): array
    {
        $calls = [];
        foreach ($events as $event) {
            if (($event['type'] ?? null) !== 'tool_use') {
                continue;
            }
            $calls[] = [
                'name' => (string) ($event['tool'] ?? 'unknown'),
                'args' => is_array($event['input'] ?? null) ? $event['input'] : [],
                'result' => null,
            ];
        }

        return $calls;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array<string, int>
     */
    private function countEventTypes(array $events): array
    {
        $counts = [];
        foreach ($events as $event) {
            $type = (string) ($event['type'] ?? '?');
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function assembleContent(array $events): string
    {
        $buffer = '';
        foreach ($events as $event) {
            if (($event['type'] ?? null) === 'content' && isset($event['text'])) {
                $buffer .= (string) $event['text'];
            }
        }

        return $buffer;
    }

    /**
     * @param  list<string>  $patterns
     * @return list<string>
     */
    private function detectForbiddenOutputs(string $text, array $patterns): array
    {
        $hits = [];
        foreach ($patterns as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }
            if (stripos($text, $pattern) !== false) {
                $hits[] = $pattern;
            }
        }

        return $hits;
    }

    /**
     * @param  list<EvalProviderRun>  $runs
     */
    private function renderComparisonTable(string $scenarioId, EvalRecordingSession $session, array $runs): void
    {
        $this->newLine();
        $this->info(str_repeat('=', 70));
        $this->info("Comparison — {$scenarioId} (session #{$session->id})");
        $this->info(str_repeat('=', 70));

        $headers = ['Provider/Model', 'Events', 'Tool calls', 'Trace', 'Forbidden', 'Duration', 'First content (60c)'];
        $rows = [];
        foreach ($runs as $r) {
            $providerModel = $r->provider.' / '.$r->model;
            $events = (string) $r->sse_event_count;
            $toolNames = array_column($r->tool_calls ?? [], 'name');
            $tools = $toolNames === [] ? '—' : implode(', ', $toolNames);
            $traceCount = is_array($r->engine_trace) ? count($r->engine_trace) : 0;
            $trace = $traceCount === 0 ? '—' : (string) $traceCount;
            $forbidden = ($r->forbidden_hits ?? []) === [] ? '—' : (string) count($r->forbidden_hits);
            $duration = ($r->duration_ms ?? 0).'ms';
            $firstContent = mb_strimwidth(trim((string) ($r->assistant_text ?? '')), 0, 60, '…');

            $rows[] = [$providerModel, $events, $tools, $trace, $forbidden, $duration, $firstContent];
        }

        $this->table($headers, $rows);
    }

    private function resolveModel(string $provider, string $override): string
    {
        if ($override !== '') {
            return $override;
        }

        return (string) config("services.{$provider}.chat_model");
    }

    private function locateScenario(string $id): string
    {
        $matches = glob(base_path(self::SCENARIO_ROOT)."/*/{$id}.json") ?: [];

        if ($matches === []) {
            throw new RuntimeException("Scenario '{$id}' not found under ".self::SCENARIO_ROOT.'/*/.');
        }

        if (count($matches) > 1) {
            throw new RuntimeException("Scenario id '{$id}' ambiguous — found in: ".implode(', ', $matches));
        }

        return $matches[0];
    }

    private function fixturePath(string $provider, string $model, string $scenarioId): string
    {
        return base_path(self::FIXTURE_ROOT)."/{$provider}/{$model}/{$scenarioId}.jsonl";
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function writeFixture(
        string $provider,
        string $model,
        string $scenarioId,
        array $events,
        int $conversationId,
        int $durationMs,
    ): string {
        $path = $this->fixturePath($provider, $model, $scenarioId);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        [$branch, $sha] = $this->resolveGitInfo();

        $meta = [
            '__meta' => [
                'provider' => $provider,
                'model' => $model,
                'scenario_id' => $scenarioId,
                'recorded_at' => now()->toIso8601String(),
                'fynla_branch' => $branch,
                'fynla_sha' => $sha,
                'conversation_id' => $conversationId,
                'event_count' => count($events),
                'duration_ms' => $durationMs,
            ],
        ];

        $lines = [json_encode($meta, JSON_UNESCAPED_SLASHES)];
        foreach ($events as $event) {
            $lines[] = json_encode($event, JSON_UNESCAPED_SLASHES);
        }

        file_put_contents($path, implode("\n", $lines)."\n");

        return $path;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveGitInfo(): array
    {
        $headPath = base_path('.git/HEAD');
        if (! is_file($headPath)) {
            return ['unknown', 'unknown'];
        }

        $head = trim((string) file_get_contents($headPath));

        if (str_starts_with($head, 'ref: ')) {
            $ref = substr($head, 5);
            $branch = str_starts_with($ref, 'refs/heads/') ? substr($ref, strlen('refs/heads/')) : $ref;
            $refPath = base_path('.git/'.$ref);
            $sha = is_file($refPath) ? substr(trim((string) file_get_contents($refPath)), 0, 7) : 'unknown';

            return [$branch, $sha];
        }

        return ['detached', substr($head, 0, 7)];
    }
}
