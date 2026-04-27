<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\CriticalIllnessPolicy;
use App\Models\DCPension;
use App\Models\EvalProviderRun;
use App\Models\EvalRecordingSession;
use App\Models\ExpenditureProfile;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Records Rubric-B eval fixtures by running a scenario YAML against one or
 * more real providers end-to-end. Persists a forensic record (start state,
 * conversation, tool calls, writes made, end state) to eval_recording_sessions
 * + eval_provider_runs, alongside the raw JSONL fixture for replay.
 *
 * Both providers run against THE SAME shared eval user so deltas are clean.
 * Between providers the user is restored to the start_state_snapshot — any
 * writes the model made (handoff create/update/delete) are reverted, but
 * captured forever in eval_provider_runs.db_writes_made.
 *
 * Usage:
 *   php artisan eval:record advice_protection_cover
 *   php artisan eval:record advice_protection_cover --providers=anthropic
 *   php artisan eval:record advice_protection_cover --providers=anthropic --model=claude-haiku-4-5-20251001
 *   php artisan eval:record advice_protection_cover --dry-run
 *
 * Fixtures land at:
 *   tests/Feature/Fyn/Eval/fixtures/{provider}/{model}/{scenario_id}.jsonl
 */
final class EvalRecordCommand extends Command
{
    protected $signature = 'eval:record
        {scenario : Scenario id (e.g. advice_protection_cover)}
        {--providers=anthropic,xai : Comma-separated providers (anthropic|xai)}
        {--model= : Override the model id (only valid with a single provider)}
        {--dry-run : Validate setup without invoking the providers}';

    protected $description = 'Record a Rubric-B eval scenario against one or more real providers, persisting a forensic record.';

    private const SCENARIO_ROOT = 'tests/Feature/Fyn/Eval/scenarios';

    private const FIXTURE_ROOT = 'tests/Feature/Fyn/Eval/fixtures';

    private const SUPPORTED_PROVIDERS = ['anthropic', 'xai'];

    /**
     * Tables snapshotted for state isolation between provider runs. Each
     * key is a scenario.seed YAML key; the value is the Eloquent model.
     */
    private const SNAPSHOT_TABLES = [
        'life_insurance_policies' => LifeInsurancePolicy::class,
        'critical_illness_policies' => CriticalIllnessPolicy::class,
        'income_protection_policies' => IncomeProtectionPolicy::class,
        'savings_accounts' => SavingsAccount::class,
        'investment_accounts' => InvestmentAccount::class,
        'dc_pensions' => DCPension::class,
        'properties' => Property::class,
    ];

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
        $scenarioYaml = (string) file_get_contents($scenarioPath);
        $scenario = Yaml::parse($scenarioYaml);
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

        // Seed once and PERSIST. The eval user is durable — every recording
        // forever-onwards uses the same user_id structure.
        $user = $this->seedUser($scenario['seed'] ?? []);
        $startStateSnapshot = $this->snapshotState($user);

        [$branch, $sha] = $this->resolveGitInfo();

        $session = EvalRecordingSession::create([
            'scenario_id' => $scenarioId,
            'scenario_path' => $scenarioPath,
            'scenario_yaml' => $scenarioYaml,
            'eval_user_id' => $user->id,
            'start_state_snapshot' => $startStateSnapshot,
            'fynla_branch' => $branch,
            'fynla_sha' => $sha,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $this->info("Session #{$session->id} — eval user id={$user->id} (is_eval_user=true)");
        $this->info('Dispatch:    '.($this->isOnboarding($user) ? 'OnboardingChatDirector' : 'AdviceFyn'));
        $this->newLine();

        $exitCode = self::SUCCESS;
        $summaries = [];

        foreach ($providers as $provider) {
            $model = $this->resolveModel($provider, $modelOverride);
            $run = $this->recordOne(
                session: $session,
                user: $user,
                provider: $provider,
                model: $model,
                scenarioId: $scenarioId,
                turns: $turns,
                forbiddenOutputs: $forbiddenOutputs,
                startStateSnapshot: $startStateSnapshot,
                dryRun: $dryRun,
            );

            if ($run === null) {
                $exitCode = self::FAILURE;

                continue;
            }

            $summaries[] = $run;

            // Restore the user back to start state before next provider
            // runs — so provider 2 sees byte-identical data to provider 1.
            // Tool-call writes are now permanently captured in
            // $run->db_writes_made (committed).
            $this->restoreToSnapshot($user, $startStateSnapshot);
            $user->refresh();
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
        $this->line("View later: php artisan eval:show --session={$session->id}");

        return $exitCode;
    }

    /**
     * @param  list<array<string, mixed>>  $turns
     * @param  list<string>  $forbiddenOutputs
     * @param  array<string, mixed>  $startStateSnapshot
     */
    private function recordOne(
        EvalRecordingSession $session,
        User $user,
        string $provider,
        string $model,
        string $scenarioId,
        array $turns,
        array $forbiddenOutputs,
        array $startStateSnapshot,
        bool $dryRun,
    ): ?EvalProviderRun {
        $this->newLine();
        $this->info(str_repeat('-', 70));
        $this->info(">> {$provider}  /  {$model}");
        $this->info(str_repeat('-', 70));
        $this->line('Fixture:    '.$this->fixturePath($provider, $model, $scenarioId));

        $previousProvider = Cache::get('ai_provider');
        Cache::forever('ai_provider', $provider);

        $previousModel = config("services.{$provider}.chat_model");
        config(["services.{$provider}.chat_model" => $model]);

        $startedAt = now();
        $startMicrotime = microtime(true);
        $run = null;

        try {
            $conversation = $this->createConversation($user, $model);
            $this->line("Conv:       id={$conversation->id} (kept; tool-call writes are restored after capture)");

            if ($dryRun) {
                $this->warn('Dry-run — skipping provider call.');
                // Still create a placeholder run with empty data so the
                // forensic record exists for inspection.
                $run = EvalProviderRun::create([
                    'eval_recording_session_id' => $session->id,
                    'provider' => $provider,
                    'model' => $model,
                    'conversation_id' => $conversation->id,
                    'user_message' => '(dry-run)',
                    'assistant_text' => null,
                    'tool_calls' => [],
                    'sse_event_count' => 0,
                    'sse_event_types' => [],
                    'forbidden_hits' => [],
                    'db_writes_made' => [],
                    'end_state_snapshot' => $startStateSnapshot,
                    'fixture_path' => null,
                    'duration_ms' => 0,
                    'started_at' => $startedAt,
                    'completed_at' => now(),
                ]);

                return $run;
            }

            $this->newLine();
            $inOnboarding = $this->isOnboarding($user);

            $events = [];
            $userMessages = [];
            foreach ($turns as $turn) {
                $userMessage = $turn['user'] ?? null;
                if (! is_string($userMessage) || $userMessage === '') {
                    continue;
                }

                $userMessages[] = $userMessage;
                $this->line('> '.$userMessage);

                $generator = $inOnboarding
                    ? $this->onboardingDirector->handleUserMessage($user, $conversation, $userMessage, null)
                    : $this->adviceFyn->handle($user, $conversation, $userMessage, null);

                foreach ($generator as $event) {
                    $events[] = $event;
                    $this->writeStreamingEvent($event);
                }
            }

            $durationMs = (int) ((microtime(true) - $startMicrotime) * 1000);

            $endStateSnapshot = $this->snapshotState($user->fresh());
            $writesMade = $this->diffSnapshots($startStateSnapshot, $endStateSnapshot);
            $assembledContent = $this->assembleContent($events);

            $fixturePath = $this->writeFixture(
                provider: $provider,
                model: $model,
                scenarioId: $scenarioId,
                events: $events,
                conversation: $conversation,
                durationMs: $durationMs,
            );

            $run = EvalProviderRun::create([
                'eval_recording_session_id' => $session->id,
                'provider' => $provider,
                'model' => $model,
                'conversation_id' => $conversation->id,
                'user_message' => implode("\n---\n", $userMessages),
                'assistant_text' => $assembledContent,
                'tool_calls' => $this->extractToolCalls($conversation->id),
                'sse_event_count' => count($events),
                'sse_event_types' => $this->countEventTypes($events),
                'forbidden_hits' => $this->detectForbiddenOutputs($assembledContent, $forbiddenOutputs),
                'db_writes_made' => $writesMade,
                'end_state_snapshot' => $endStateSnapshot,
                'fixture_path' => $fixturePath,
                'duration_ms' => $durationMs,
                'started_at' => $startedAt,
                'completed_at' => now(),
            ]);

            $this->newLine();
            $this->info('Captured '.count($events)." SSE events in {$durationMs}ms.");
            $this->info("Run #{$run->id} persisted; fixture: {$fixturePath}");

            $writeCount = count($writesMade['created'] ?? []) + count($writesMade['updated'] ?? []) + count($writesMade['deleted'] ?? []);
            if ($writeCount > 0) {
                $this->line("DB writes:  {$writeCount} (will be restored before next provider).");
            } else {
                $this->line('DB writes:  none.');
            }
        } catch (\Throwable $e) {
            $this->error("[{$provider}] Recording failed: ".$e->getMessage());
            $this->error($e->getFile().':'.$e->getLine());
            $run = null;
        } finally {
            if ($previousProvider === null) {
                Cache::forget('ai_provider');
            } else {
                Cache::forever('ai_provider', $previousProvider);
            }
            config(["services.{$provider}.chat_model" => $previousModel]);
        }

        return $run;
    }

    /**
     * Snapshot the user + every child entity row to a JSON-serialisable array.
     * The snapshot is the source of truth for "what data was Fyn working from".
     *
     * @return array<string, mixed>
     */
    private function snapshotState(User $user): array
    {
        $snapshot = [
            'user' => $user->only([
                'id', 'first_name', 'surname', 'email', 'date_of_birth',
                'marital_status', 'annual_income', 'onboarding_completed',
                'onboarding_fyn_step', 'is_preview_user', 'is_eval_user',
            ]),
        ];

        foreach (self::SNAPSHOT_TABLES as $key => $modelClass) {
            $snapshot[$key] = $modelClass::where('user_id', $user->id)
                ->withTrashed()
                ->get()
                ->map(fn ($m) => $m->getAttributes())
                ->all();
        }

        $snapshot['expenditure_profile'] = ExpenditureProfile::where('user_id', $user->id)
            ->first()
            ?->getAttributes();

        return $snapshot;
    }

    /**
     * Diff two snapshots into created/updated/deleted lists.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{created: list<array<string, mixed>>, updated: list<array<string, mixed>>, deleted: list<array<string, mixed>>}
     */
    private function diffSnapshots(array $before, array $after): array
    {
        $writes = ['created' => [], 'updated' => [], 'deleted' => []];

        // User-level field diff
        $beforeUser = $before['user'] ?? [];
        $afterUser = $after['user'] ?? [];
        $changedFields = [];
        foreach ($afterUser as $field => $newValue) {
            $oldValue = $beforeUser[$field] ?? null;
            if ($oldValue !== $newValue) {
                $changedFields[$field] = ['from' => $oldValue, 'to' => $newValue];
            }
        }
        if ($changedFields !== []) {
            $writes['updated'][] = ['table' => 'users', 'id' => $beforeUser['id'] ?? null, 'fields' => $changedFields];
        }

        // Child-table row diff (keyed by id)
        foreach (array_keys(self::SNAPSHOT_TABLES) as $key) {
            $beforeById = $this->keyById($before[$key] ?? []);
            $afterById = $this->keyById($after[$key] ?? []);

            foreach ($afterById as $id => $row) {
                if (! isset($beforeById[$id])) {
                    $writes['created'][] = ['table' => $key, 'id' => $id, 'data' => $row];
                }
            }

            foreach ($beforeById as $id => $row) {
                if (! isset($afterById[$id])) {
                    $writes['deleted'][] = ['table' => $key, 'id' => $id, 'data' => $row];
                }
            }

            foreach ($afterById as $id => $afterRow) {
                if (! isset($beforeById[$id])) {
                    continue;
                }
                $beforeRow = $beforeById[$id];
                $changed = $this->diffRows($beforeRow, $afterRow);
                if ($changed !== []) {
                    $writes['updated'][] = ['table' => $key, 'id' => $id, 'fields' => $changed];
                }
            }
        }

        // expenditure_profile (single row)
        $beforeExp = $before['expenditure_profile'] ?? null;
        $afterExp = $after['expenditure_profile'] ?? null;
        if ($beforeExp === null && $afterExp !== null) {
            $writes['created'][] = ['table' => 'expenditure_profile', 'id' => $afterExp['id'] ?? null, 'data' => $afterExp];
        } elseif ($beforeExp !== null && $afterExp === null) {
            $writes['deleted'][] = ['table' => 'expenditure_profile', 'id' => $beforeExp['id'] ?? null, 'data' => $beforeExp];
        } elseif ($beforeExp !== null && $afterExp !== null) {
            $changed = $this->diffRows($beforeExp, $afterExp);
            if ($changed !== []) {
                $writes['updated'][] = ['table' => 'expenditure_profile', 'id' => $beforeExp['id'] ?? null, 'fields' => $changed];
            }
        }

        return $writes;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int|string, array<string, mixed>>
     */
    private function keyById(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            if ($id !== null) {
                $out[$id] = $row;
            }
        }

        return $out;
    }

    /**
     * Field-level diff of two row arrays, ignoring volatile fields.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diffRows(array $before, array $after): array
    {
        $changed = [];
        $ignoreKeys = ['updated_at', 'created_at'];

        foreach ($after as $field => $newValue) {
            if (in_array($field, $ignoreKeys, true)) {
                continue;
            }
            $oldValue = $before[$field] ?? null;
            if ($oldValue !== $newValue) {
                $changed[$field] = ['from' => $oldValue, 'to' => $newValue];
            }
        }

        return $changed;
    }

    /**
     * Restore the user's child entities to match the snapshot. New rows
     * are hard-deleted, deleted rows re-inserted, updated rows reverted.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreToSnapshot(User $user, array $snapshot): void
    {
        DB::transaction(function () use ($user, $snapshot): void {
            // Restore user fields (skip id/timestamps/email — email was set
            // on seed and must remain unique).
            $userFields = $snapshot['user'] ?? [];
            unset($userFields['id'], $userFields['email']);
            if ($userFields !== []) {
                $user->update($userFields);
            }

            foreach (self::SNAPSHOT_TABLES as $key => $modelClass) {
                $rows = $snapshot[$key] ?? [];
                $snapshotIds = array_filter(array_column($rows, 'id'));

                // Hard-delete any current row for this user not in snapshot.
                $query = $modelClass::where('user_id', $user->id);
                if ($snapshotIds !== []) {
                    $query->whereNotIn('id', $snapshotIds);
                }
                if (method_exists($modelClass, 'forceDelete')) {
                    $query->forceDelete();
                } else {
                    $query->delete();
                }

                foreach ($rows as $row) {
                    $id = $row['id'] ?? null;
                    if ($id === null) {
                        continue;
                    }
                    $current = $modelClass::withTrashed()->find($id);
                    if ($current === null) {
                        // Re-insert with the original id.
                        $modelClass::query()->insert($row);
                    } else {
                        if (method_exists($current, 'trashed') && $current->trashed()) {
                            $current->restore();
                        }
                        $current->forceFill($row)->save();
                    }
                }
            }

            // expenditure_profile — single row
            $exp = $snapshot['expenditure_profile'] ?? null;
            if ($exp === null) {
                ExpenditureProfile::where('user_id', $user->id)->delete();
            } else {
                ExpenditureProfile::updateOrCreate(
                    ['id' => $exp['id'] ?? 0],
                    $exp
                );
            }
        });
    }

    /**
     * Tool calls with full args + result_summary live on the assistant
     * AiMessage's metadata, written by HasAiChat::handleStream after the
     * tool loop closes. The SSE stream itself only carries name + status,
     * not the structured input — so we read from the message of record.
     *
     * @return list<array{name: string, args: array<string, mixed>|string, result: mixed}>
     */
    private function extractToolCalls(int $conversationId): array
    {
        $message = AiMessage::where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->latest('id')
            ->first();

        if ($message === null) {
            return [];
        }

        $rawCalls = $message->metadata['tool_calls'] ?? null;
        if (! is_array($rawCalls)) {
            return [];
        }

        $calls = [];
        foreach ($rawCalls as $tc) {
            if (! is_array($tc)) {
                continue;
            }
            $calls[] = [
                'name' => (string) ($tc['tool'] ?? $tc['name'] ?? 'unknown'),
                'args' => $tc['input'] ?? $tc['args'] ?? [],
                'result' => $tc['result_summary'] ?? $tc['result'] ?? null,
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

        $headers = ['Provider/Model', 'Events', 'Tool calls', 'DB writes', 'Forbidden', 'Duration', 'First content (60c)'];
        $rows = [];
        foreach ($runs as $r) {
            $providerModel = $r->provider.' / '.$r->model;
            $events = (string) $r->sse_event_count;
            $toolNames = array_column($r->tool_calls ?? [], 'name');
            $tools = $toolNames === [] ? '—' : implode(', ', $toolNames);
            $writesArr = $r->db_writes_made ?? [];
            $writeCount = count($writesArr['created'] ?? []) + count($writesArr['updated'] ?? []) + count($writesArr['deleted'] ?? []);
            $writes = $writeCount === 0 ? '—' : (string) $writeCount;
            $forbidden = ($r->forbidden_hits ?? []) === [] ? '—' : (string) count($r->forbidden_hits);
            $duration = ($r->duration_ms ?? 0).'ms';
            $firstContent = mb_strimwidth(trim((string) ($r->assistant_text ?? '')), 0, 60, '…');

            $rows[] = [$providerModel, $events, $tools, $writes, $forbidden, $duration, $firstContent];
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
        $matches = glob(base_path(self::SCENARIO_ROOT)."/*/{$id}.yaml") ?: [];

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
     * @param  array<string, mixed>  $seed
     */
    private function seedUser(array $seed): User
    {
        $userAttrs = $seed['user'] ?? [];
        $email = 'eval+'.Str::random(10).'@fynla.test';

        // Schema validation — fail loudly on any seed.user key that is not
        // an actual users-table column. Without this guard, mass-assignment
        // via User::create silently inserts (or, on stricter SQL modes,
        // throws a column-not-found at INSERT time) and recordings end up
        // with the wrong data shape. See April27Updates/fixEvalTask.md
        // Task 3 — `annual_income: 50000` in two YAMLs was being lost
        // because the schema uses seven `annual_*_income` columns instead.
        if (! is_array($userAttrs)) {
            throw new RuntimeException('Scenario seed.user must be an array.');
        }

        $tableColumns = \Illuminate\Support\Facades\Schema::getColumnListing((new User)->getTable());
        $unknown = array_diff(array_keys($userAttrs), $tableColumns);
        if ($unknown !== []) {
            throw new RuntimeException(
                "Scenario seed.user contains keys that are not columns on `users`: ".
                implode(', ', $unknown).
                ". Either fix the YAML (e.g. `annual_income` → `annual_employment_income`) ".
                'or add the column via a migration first.'
            );
        }

        $user = User::create(array_merge([
            'first_name' => 'Eval',
            'surname' => 'Test',
            'email' => $email,
            'password' => bcrypt('eval-recording'),
            'onboarding_completed' => true,
            'is_preview_user' => false,
            'is_eval_user' => true,
        ], $userAttrs, ['is_eval_user' => true]));

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

            $modelClass::create(array_merge(['user_id' => $user->id, 'is_active' => true], $row));
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
        ExpenditureProfile::create(array_merge(['user_id' => $user->id], $row));
    }

    private function createConversation(User $user, string $model): AiConversation
    {
        // message_count is explicitly set to 0 so the in-memory model
        // matches the DB default. HasAiChat::chat() reads
        // $conversation->message_count with strict types — leaving it
        // unset on the in-memory object yields null and trips the
        // classifyComplexity(int $conversationDepth) signature. The DB
        // column already defaults to 0 (non-null), this just keeps the
        // PHP-side state consistent without a round-trip refresh().
        return AiConversation::create([
            'user_id' => $user->id,
            'title' => 'Eval recording',
            'status' => 'active',
            'model_used' => $model,
            'message_count' => 0,
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
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
