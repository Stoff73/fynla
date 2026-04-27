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
 * Records Rubric-B eval fixtures by running a scenario YAML against a real
 * provider end-to-end and writing the SSE stream to JSONL.
 *
 * Usage:
 *   php artisan eval:record advice_protection_cover
 *   php artisan eval:record advice_protection_cover --provider=xai
 *   php artisan eval:record advice_protection_cover --provider=anthropic --model=claude-haiku-4-5-20251001
 *   php artisan eval:record advice_protection_cover --dry-run
 *
 * --dry-run    Loads scenario, seeds DB, picks dispatch (advice vs onboarding),
 *              prints the plan + would-be fixture path, then ROLLS BACK the
 *              seed without invoking the provider. No tokens spent.
 *
 * --keep-data  Keep the seeded user + cascaded rows after recording. Default
 *              is to delete them (the user is recorded ephemeral, not real).
 *
 * Fixtures land at:
 *   tests/Feature/Fyn/Eval/fixtures/{provider}/{model}/{scenario_id}.jsonl
 */
final class EvalRecordCommand extends Command
{
    protected $signature = 'eval:record
        {scenario : Scenario id (e.g. advice_protection_cover) — must exist under tests/Feature/Fyn/Eval/scenarios/*/}
        {--provider=anthropic : Provider to record against (anthropic|xai)}
        {--model= : Override the model id (defaults to chat_model from config/services.php)}
        {--dry-run : Validate setup without invoking the provider}
        {--keep-data : Keep the ephemeral seeded user after recording (default: delete)}';

    protected $description = 'Record a Rubric-B eval scenario against a real provider.';

    private const SCENARIO_ROOT = 'tests/Feature/Fyn/Eval/scenarios';

    private const FIXTURE_ROOT = 'tests/Feature/Fyn/Eval/fixtures';

    public function __construct(
        private readonly AdviceFyn $adviceFyn,
        private readonly OnboardingChatDirector $onboardingDirector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $scenarioId = (string) $this->argument('scenario');
        $provider = (string) $this->option('provider');
        $dryRun = (bool) $this->option('dry-run');
        $keepData = (bool) $this->option('keep-data');

        if (! in_array($provider, ['anthropic', 'xai'], true)) {
            $this->error("--provider must be 'anthropic' or 'xai', got '{$provider}'.");

            return self::INVALID;
        }

        $model = $this->resolveModel($provider, (string) ($this->option('model') ?? ''));

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

        $this->info(str_repeat('=', 70));
        $this->info("Eval recording — {$scenarioId}");
        $this->info(str_repeat('=', 70));
        $this->line("Provider:   {$provider}");
        $this->line("Model:      {$model}");
        $this->line("Scenario:   {$scenarioPath}");
        $this->line('Turns:      '.count($turns));
        $this->line('Fixture:    '.$this->fixturePath($provider, $model, $scenarioId));
        $this->line('Dry-run:    '.($dryRun ? 'YES — no provider call' : 'NO — will hit live provider'));
        $this->newLine();

        // Force the provider for this recording so the chat machinery
        // routes through the correct client + tool catalogue.
        $previousProvider = Cache::get('ai_provider');
        Cache::forever('ai_provider', $provider);

        // Override the chat model for this provider for the duration of the
        // recording. config() is request-scoped so this only affects the
        // current process.
        $previousModel = config("services.{$provider}.chat_model");
        config(["services.{$provider}.chat_model" => $model]);

        $exitCode = self::SUCCESS;

        try {
            DB::beginTransaction();

            $user = $this->seedUser($scenario['seed'] ?? []);
            $conversation = $this->createConversation($user, $model);

            $this->info("Seeded user id={$user->id} email={$user->email}");
            $this->info("Conversation id={$conversation->id}");

            $inOnboarding = $this->isOnboarding($user);
            $this->line('Dispatch:   '.($inOnboarding ? 'OnboardingChatDirector' : 'AdviceFyn'));
            $this->newLine();

            if ($dryRun) {
                $this->warn('Dry-run — skipping provider call. Rolling back seed.');
                DB::rollBack();

                return self::SUCCESS;
            }

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

            $this->newLine();
            $this->info('Captured '.count($events).' SSE events.');

            $fixturePath = $this->writeFixture(
                provider: $provider,
                model: $model,
                scenarioId: $scenarioId,
                events: $events,
                conversation: $conversation,
            );

            $this->info("Fixture written: {$fixturePath}");

            if ($keepData) {
                DB::commit();
                $this->info("Kept seeded user id={$user->id} (--keep-data).");
            } else {
                DB::rollBack();
                $this->info('Seed rolled back (use --keep-data to persist).');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Recording failed: '.$e->getMessage());
            $this->error($e->getFile().':'.$e->getLine());
            $exitCode = self::FAILURE;
        } finally {
            // Restore previous provider + model so we don't leak across runs.
            if ($previousProvider === null) {
                Cache::forget('ai_provider');
            } else {
                Cache::forever('ai_provider', $previousProvider);
            }
            config(["services.{$provider}.chat_model" => $previousModel]);
        }

        return $exitCode;
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
