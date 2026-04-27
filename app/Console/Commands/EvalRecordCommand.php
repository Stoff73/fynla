<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiConversation;
use App\Models\CriticalIllnessPolicy;
use App\Models\DCPension;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\AI\AdviceFyn;
use App\Services\Onboarding\OnboardingChatDirector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Records Rubric-B eval fixtures by running a scenario YAML against one or
 * more real providers end-to-end and writing the SSE stream to JSONL.
 *
 * Usage:
 *   php artisan eval:record advice_protection_cover                          # both providers
 *   php artisan eval:record advice_protection_cover --providers=anthropic    # one only
 *   php artisan eval:record advice_protection_cover --providers=anthropic,xai
 *   php artisan eval:record advice_protection_cover --providers=anthropic --model=claude-haiku-4-5-20251001
 *   php artisan eval:record advice_protection_cover --dry-run
 *
 * --providers  Comma-separated list (anthropic, xai). Default: both.
 *              Each provider gets a freshly seeded ephemeral user so DB
 *              state from one recording can't leak into the next.
 *
 * --model      Override the model id. Only valid with a single provider.
 *              Default: chat_model from config/services.php for each provider.
 *
 * --dry-run    Loads scenario, seeds DB, picks dispatch (advice vs onboarding),
 *              prints the plan + would-be fixture path, then ROLLS BACK the
 *              seed without invoking the provider. No tokens spent.
 *
 * --keep-data  Keep the ephemeral seeded user(s) after recording. Default
 *              is to delete them.
 *
 * Fixtures land at:
 *   tests/Feature/Fyn/Eval/fixtures/{provider}/{model}/{scenario_id}.jsonl
 *
 * After all recordings the command prints a side-by-side comparison table
 * (event count, tool call names, first content snippet, forbidden-output
 * hits, duration) so you can eyeball provider/model differences without
 * opening the JSONL files.
 */
final class EvalRecordCommand extends Command
{
    protected $signature = 'eval:record
        {scenario : Scenario id (e.g. advice_protection_cover) — must exist under tests/Feature/Fyn/Eval/scenarios/*/}
        {--providers=anthropic,xai : Comma-separated list of providers to record against}
        {--model= : Override the model id (only valid with a single provider)}
        {--dry-run : Validate setup without invoking the providers}
        {--keep-data : Keep the ephemeral seeded user(s) after recording (default: delete)}';

    protected $description = 'Record a Rubric-B eval scenario against one or more real providers.';

    private const SCENARIO_ROOT = 'tests/Feature/Fyn/Eval/scenarios';

    private const FIXTURE_ROOT = 'tests/Feature/Fyn/Eval/fixtures';

    private const SUPPORTED_PROVIDERS = ['anthropic', 'xai'];

    public function __construct(
        private readonly AdviceFyn $adviceFyn,
        private readonly OnboardingChatDirector $onboardingDirector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $scenarioId = (string) $this->argument('scenario');
        $providersOption = (string) $this->option('providers');
        $dryRun = (bool) $this->option('dry-run');
        $keepData = (bool) $this->option('keep-data');
        $modelOverride = (string) ($this->option('model') ?? '');

        $providers = array_values(array_filter(array_map(
            'trim',
            explode(',', $providersOption)
        )));

        if ($providers === []) {
            $this->error('--providers must be non-empty (e.g. anthropic, xai, or both).');

            return self::INVALID;
        }

        foreach ($providers as $p) {
            if (! in_array($p, self::SUPPORTED_PROVIDERS, true)) {
                $this->error("Unsupported provider '{$p}'. Allowed: ".implode(', ', self::SUPPORTED_PROVIDERS));

                return self::INVALID;
            }
        }

        if ($modelOverride !== '' && count($providers) > 1) {
            $this->error('--model is only valid with a single provider. Run once per provider if you need different models.');

            return self::INVALID;
        }

        // Load the scenario once — same YAML drives every provider's recording.
        $scenarioPath = $this->locateScenario($scenarioId);
        $scenario = Yaml::parseFile($scenarioPath);
        if (! is_array($scenario)) {
            $this->error("Scenario {$scenarioPath} did not parse to an array.");

            return self::FAILURE;
        }

        $turns = $scenario['input']['turns'] ?? [];
        if ($turns === []) {
            $this->error("Scenario {$scenarioId} has no input.turns.");

            return self::FAILURE;
        }

        $forbiddenOutputs = $scenario['forbidden_outputs'] ?? [];
        if (! is_array($forbiddenOutputs)) {
            $forbiddenOutputs = [];
        }

        $this->info(str_repeat('=', 70));
        $this->info("Eval recording — {$scenarioId}");
        $this->info(str_repeat('=', 70));
        $this->line("Scenario:   {$scenarioPath}");
        $this->line('Providers:  '.implode(', ', $providers));
        $this->line('Turns:      '.count($turns));
        $this->line('Dry-run:    '.($dryRun ? 'YES — no provider calls' : 'NO — will hit live providers'));
        $this->newLine();

        $exitCode = self::SUCCESS;
        $summaries = [];

        foreach ($providers as $provider) {
            $model = $this->resolveModel($provider, $modelOverride);
            $summary = $this->recordOne(
                provider: $provider,
                model: $model,
                scenarioId: $scenarioId,
                scenario: $scenario,
                turns: $turns,
                forbiddenOutputs: $forbiddenOutputs,
                dryRun: $dryRun,
                keepData: $keepData,
            );

            if ($summary === null) {
                $exitCode = self::FAILURE;

                continue;
            }

            $summaries[] = $summary;
        }

        if (! $dryRun && count($summaries) > 0) {
            $this->renderComparisonTable($scenarioId, $summaries);
        }

        return $exitCode;
    }

    /**
     * Record a single (provider, model) pass and return its summary, or null on failure.
     *
     * @param  array<string, mixed>  $scenario
     * @param  list<array<string, mixed>>  $turns
     * @param  list<string>  $forbiddenOutputs
     * @return array<string, mixed>|null
     */
    private function recordOne(
        string $provider,
        string $model,
        string $scenarioId,
        array $scenario,
        array $turns,
        array $forbiddenOutputs,
        bool $dryRun,
        bool $keepData,
    ): ?array {
        $this->newLine();
        $this->info(str_repeat('-', 70));
        $this->info(">> {$provider}  /  {$model}");
        $this->info(str_repeat('-', 70));
        $this->line('Fixture:    '.$this->fixturePath($provider, $model, $scenarioId));

        $previousProvider = Cache::get('ai_provider');
        Cache::forever('ai_provider', $provider);

        $previousModel = config("services.{$provider}.chat_model");
        config(["services.{$provider}.chat_model" => $model]);

        $startedAt = microtime(true);
        $summary = null;

        try {
            DB::beginTransaction();

            $user = $this->seedUser($scenario['seed'] ?? []);
            $conversation = $this->createConversation($user, $model);

            $this->line("Seeded:     user id={$user->id} email={$user->email}");
            $this->line("Conv:       id={$conversation->id}");

            $inOnboarding = $this->isOnboarding($user);
            $this->line('Dispatch:   '.($inOnboarding ? 'OnboardingChatDirector' : 'AdviceFyn'));

            if ($dryRun) {
                $this->newLine();
                $this->warn('Dry-run — skipping provider call. Rolling back seed.');
                DB::rollBack();

                return [
                    'provider' => $provider,
                    'model' => $model,
                    'dry_run' => true,
                ];
            }

            $this->newLine();

            $events = [];
            foreach ($turns as $turn) {
                $userMessage = $turn['user'] ?? null;
                if (! is_string($userMessage) || $userMessage === '') {
                    continue;
                }

                $this->line('> '.$userMessage);

                $generator = $inOnboarding
                    ? $this->onboardingDirector->handleUserMessage($user, $conversation, $userMessage, null)
                    : $this->adviceFyn->handle($user, $conversation, $userMessage, null);

                foreach ($generator as $event) {
                    $events[] = $event;
                    $this->writeStreamingEvent($event);
                }
            }

            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

            $fixturePath = $this->writeFixture(
                provider: $provider,
                model: $model,
                scenarioId: $scenarioId,
                events: $events,
                conversation: $conversation,
                durationMs: $durationMs,
            );

            $this->newLine();
            $this->info('Captured '.count($events)." SSE events in {$durationMs}ms.");
            $this->info("Fixture written: {$fixturePath}");

            $summary = $this->buildSummary(
                provider: $provider,
                model: $model,
                events: $events,
                forbiddenOutputs: $forbiddenOutputs,
                durationMs: $durationMs,
                fixturePath: $fixturePath,
            );

            if ($keepData) {
                DB::commit();
                $this->info("Kept seeded user id={$user->id} (--keep-data).");
            } else {
                DB::rollBack();
                $this->info('Seed rolled back (use --keep-data to persist).');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("[{$provider}] Recording failed: ".$e->getMessage());
            $this->error($e->getFile().':'.$e->getLine());
            $summary = null;
        } finally {
            if ($previousProvider === null) {
                Cache::forget('ai_provider');
            } else {
                Cache::forever('ai_provider', $previousProvider);
            }
            config(["services.{$provider}.chat_model" => $previousModel]);
        }

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  list<string>  $forbiddenOutputs
     * @return array<string, mixed>
     */
    private function buildSummary(
        string $provider,
        string $model,
        array $events,
        array $forbiddenOutputs,
        int $durationMs,
        string $fixturePath,
    ): array {
        $eventTypes = [];
        $toolCalls = [];
        $firstContent = '';

        foreach ($events as $event) {
            $type = (string) ($event['type'] ?? '');
            $eventTypes[$type] = ($eventTypes[$type] ?? 0) + 1;

            if ($type === 'tool_use' && isset($event['name'])) {
                $toolCalls[] = (string) $event['name'];
            }

            if ($type === 'content' && $firstContent === '' && isset($event['text'])) {
                $firstContent = (string) $event['text'];
            }
        }

        $assembledContent = $this->assembleContent($events);
        $forbiddenHits = $this->detectForbiddenOutputs($assembledContent, $forbiddenOutputs);

        return [
            'provider' => $provider,
            'model' => $model,
            'event_count' => count($events),
            'event_types' => $eventTypes,
            'tool_calls' => $toolCalls,
            'first_content' => $firstContent,
            'assembled_content' => $assembledContent,
            'forbidden_hits' => $forbiddenHits,
            'duration_ms' => $durationMs,
            'fixture_path' => $fixturePath,
        ];
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
     * @param  list<array<string, mixed>>  $summaries
     */
    private function renderComparisonTable(string $scenarioId, array $summaries): void
    {
        $this->newLine();
        $this->info(str_repeat('=', 70));
        $this->info("Comparison — {$scenarioId}");
        $this->info(str_repeat('=', 70));

        $headers = ['Provider/Model', 'Events', 'Tool calls', 'Forbidden hits', 'Duration', 'First content (60 chars)'];
        $rows = [];
        foreach ($summaries as $s) {
            $providerModel = $s['provider'].' / '.$s['model'];
            $events = (string) ($s['event_count'] ?? 0);
            $tools = $s['tool_calls'] === [] ? '—' : implode(', ', $s['tool_calls']);
            $forbidden = $s['forbidden_hits'] === [] ? '—' : (string) count($s['forbidden_hits']).' ('.implode('; ', $s['forbidden_hits']).')';
            $duration = ($s['duration_ms'] ?? 0).'ms';
            $firstContent = mb_strimwidth(trim((string) ($s['first_content'] ?? '')), 0, 60, '…');

            $rows[] = [$providerModel, $events, $tools, $forbidden, $duration, $firstContent];
        }

        $this->table($headers, $rows);

        // Detail block for each — useful when content diverges.
        foreach ($summaries as $s) {
            $this->newLine();
            $this->line('--- '.$s['provider'].' / '.$s['model'].' ---');
            $this->line('Fixture: '.($s['fixture_path'] ?? '?'));
            $eventTypes = [];
            foreach (($s['event_types'] ?? []) as $type => $count) {
                $eventTypes[] = "{$type}={$count}";
            }
            $this->line('Event types: '.implode(', ', $eventTypes));
            $this->line('Assembled content:');
            $this->line('  '.str_replace("\n", "\n  ", (string) ($s['assembled_content'] ?? '')));
        }
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
        $matches = glob(base_path(self::SCENARIO_ROOT)."/*/{$id}.yaml") ?: [];

        if ($matches === []) {
            throw new RuntimeException("Scenario '{$id}' not found under ".self::SCENARIO_ROOT.'/*/.');
        }

        if (count($matches) > 1) {
            throw new RuntimeException("Scenario id '{$id}' is ambiguous — found in multiple categories: ".implode(', ', $matches));
        }

        return $matches[0];
    }

    private function fixturePath(string $provider, string $model, string $scenarioId): string
    {
        return base_path(self::FIXTURE_ROOT)."/{$provider}/{$model}/{$scenarioId}.jsonl";
    }

    /**
     * @param  array<string, mixed>  $seed
     */
    private function seedUser(array $seed): User
    {
        $userAttrs = $seed['user'] ?? [];
        $email = 'eval+'.Str::random(10).'@fynla.test';

        $user = User::create(array_merge([
            'first_name' => 'Eval',
            'surname' => 'Test',
            'email' => $email,
            'password' => bcrypt('eval-recording'),
            'onboarding_completed' => true,
            'is_preview_user' => false,
        ], $userAttrs));

        // Grant the four standard consents so AiChatController's runtime
        // gate doesn't 403 mid-recording.
        foreach ([
            UserConsent::TYPE_TERMS,
            UserConsent::TYPE_PRIVACY,
            UserConsent::TYPE_DATA_PROCESSING,
            UserConsent::TYPE_AI_CHAT,
        ] as $consentType) {
            UserConsent::recordConsent($user->id, $consentType, true);
        }

        $this->seedChildEntities($user, $seed);

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $seed
     */
    private function seedChildEntities(User $user, array $seed): void
    {
        foreach ($seed as $key => $rows) {
            if ($key === 'user' || ! is_array($rows)) {
                continue;
            }

            match ($key) {
                'protection_policies' => $this->seedProtectionPolicies($user, $rows),
                'savings_accounts' => $this->seedRows($user, SavingsAccount::class, $rows),
                'investment_accounts' => $this->seedRows($user, InvestmentAccount::class, $rows),
                'dc_pensions' => $this->seedRows($user, DCPension::class, $rows),
                'properties' => $this->seedRows($user, Property::class, $rows),
                'expenditure' => $this->seedExpenditure($user, $rows),
                default => $this->warn("Unknown seed key '{$key}' — skipping. Add a branch in EvalRecordCommand::seedChildEntities."),
            };
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function seedProtectionPolicies(User $user, array $rows): void
    {
        foreach ($rows as $row) {
            $type = $row['policy_type_group'] ?? 'life';
            unset($row['policy_type_group'], $row['type']);

            $modelClass = match ($type) {
                'life' => LifeInsurancePolicy::class,
                'critical_illness' => CriticalIllnessPolicy::class,
                'income_protection' => IncomeProtectionPolicy::class,
                default => throw new RuntimeException("Unknown protection policy_type_group '{$type}'"),
            };

            $modelClass::create(array_merge([
                'user_id' => $user->id,
                'is_active' => true,
            ], $row));
        }
    }

    /**
     * @param  class-string  $modelClass
     * @param  list<array<string, mixed>>  $rows
     */
    private function seedRows(User $user, string $modelClass, array $rows): void
    {
        foreach ($rows as $row) {
            $modelClass::create(array_merge(['user_id' => $user->id], $row));
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function seedExpenditure(User $user, array $row): void
    {
        // Expenditure in scenarios is a single record, not a list.
        \App\Models\ExpenditureProfile::create(array_merge(['user_id' => $user->id], $row));
    }

    private function createConversation(User $user, string $model): AiConversation
    {
        return AiConversation::create([
            'user_id' => $user->id,
            'title' => 'Eval recording',
            'status' => 'active',
            'model_used' => $model,
            'persona_state' => ['source' => 'eval-record'],
        ]);
    }

    private function isOnboarding(User $user): bool
    {
        return $user->onboarding_completed === false
            && $user->onboarding_fyn_step !== null
            && (bool) config('onboarding.fyn_flow_enabled', true);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function writeStreamingEvent(array $event): void
    {
        $type = $event['type'] ?? '?';

        $detail = match ($type) {
            'content' => mb_substr((string) ($event['text'] ?? ''), 0, 80),
            'tool_use' => 'name='.($event['name'] ?? '?'),
            'tool_result' => 'name='.($event['name'] ?? '?'),
            'entity_created' => 'entity_type='.($event['entity_type'] ?? '?'),
            'entity_updated' => 'entity_type='.($event['entity_type'] ?? '?'),
            'entity_deleted' => 'entity_type='.($event['entity_type'] ?? '?'),
            'navigation' => 'route='.($event['route'] ?? '?'),
            'done' => '',
            default => substr((string) json_encode($event), 0, 80),
        };

        $this->line("  [{$type}] {$detail}");
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function writeFixture(
        string $provider,
        string $model,
        string $scenarioId,
        array $events,
        AiConversation $conversation,
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
                'conversation_id' => $conversation->id,
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
     * Read branch + short SHA directly from .git/HEAD without spawning git.
     *
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

        // Detached HEAD — $head is the SHA itself.
        return ['detached', substr($head, 0, 7)];
    }
}
