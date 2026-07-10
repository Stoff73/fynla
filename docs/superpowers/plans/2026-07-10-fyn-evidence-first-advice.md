# Fyn Evidence-First Advice Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the existing CoALA-based Fyn runtime into a launch-safe, evidence-first personal-finance guidance system with one Advice Case, one turn preparation, one evidence snapshot, complexity-gated planning, typed user-controllable memory and mechanical policy enforcement.

**Architecture:** Preserve the single Fyn surface, deterministic write-intent bypasses, read-only Advice Fyn, `delegate_to_capture`, GroundGate, pointer registry, versioned corpora and signed episodic blobs. Add an explicit guidance operating policy, progressively populated `AdviceCase`, request-scoped turn/evidence holders, a deterministic direct/planned router, SQL relationship memory replacing per-user Markdown, and a response policy gate. Planner and learning work leave the hot path unless an evaluated complex-turn route explicitly requires them.

**Tech Stack:** PHP 8.3, Laravel 10 initially then Laravel 13, MySQL 8, Vue 3, Vuex, Vite, Pest, Vitest, Playwright, xAI/Anthropic provider adapters, Laravel database queues.

## Global Constraints

- Approved design: `docs/superpowers/specs/2026-07-10-fyn-evidence-first-advice-design.md`.
- Master release programme: `docs/superpowers/plans/2026-07-10-online-readiness-programme.md`, Tasks 22A-22J.
- Launch operating mode is `guidance`; targeted support and regulated advice remain mechanically disabled.
- Advice Fyn remains read-only. Every memory/data mutation from chat uses `delegate_to_capture` and remains GroundGate-protected.
- Canonical financial data is fetched live from models, `TaxConfigService` and deterministic engines; never freeze balances, allowances, recommendations or plan figures into semantic memory.
- One `ai_advice_logs` row is the structured Advice Case. The signed episode blob remains the forensic transcript. Do not create a third advice-log table.
- The SQL/signed-blob episode system becomes canonical. Retire the agent-written Markdown episode summaries and per-user Markdown semantic store only after an idempotent migration/reconciliation proves parity.
- `FYN_LEARNING_ENABLED=false` remains the launch default. No task enables learning without Task 22J's explicit gate.
- Every user-facing change is complete on desktop web and `/m`; no new decorative icons; use existing layout/design-system patterns.
- Every task follows test-driven development and ends with focused green evidence plus a bounded commit.
- Every migration is additive and reversible. Never use `migrate:fresh`, `migrate:refresh`, `db:wipe`, `route:cache` or `artisan optimize`.
- Run `php artisan db:seed` after local migration activity.
- Live verification uses the shared chat endpoint and normal browser interactions; no direct state injection or DOM-script clicks.

## File map

### Policy and turn preparation

- `app/Services/AI/Policy/AdviceOperatingMode.php` — operating-mode enum.
- `app/Services/AI/Policy/AdvicePolicy.php` — immutable policy value object.
- `app/Services/AI/Policy/AdvicePolicyResolver.php` — fail-closed policy selection.
- `app/Services/AI/Fyn/FynTurnPreparation.php` — immutable classification/KYC/policy result.
- `app/Services/AI/Fyn/FynTurnPreparationHolder.php` — request-scoped lifecycle holder.
- `app/Services/AI/Fyn/FynTurnPreparer.php` — the single classifier/KYC entry point.

### Advice Case and evidence

- `app/Services/AI/Advice/AdviceCase.php` — progressively populated turn record.
- `app/Services/AI/Advice/AdviceCaseHolder.php` — request-scoped case holder.
- `app/Services/AI/Advice/AdviceCaseRecorder.php` — persists through `AiAdviceLog`.
- `app/Services/AI/Fyn/FynEvidenceSnapshot.php` — immutable prompt/provenance snapshot.
- `app/Services/AI/Fyn/FynEvidenceAssembler.php` — one retrieval/assembly operation.
- `app/Services/AI/Fyn/FynEvidenceHolder.php` — shared planner/reasoner snapshot.

### Planning and policy enforcement

- `app/Services/AI/Loop/AdviceRoute.php` — `direct|planned` enum.
- `app/Services/AI/Loop/AdviceComplexityRouter.php` — deterministic routing.
- `app/Services/AI/Policy/AdviceResponseGate.php` — allow/sanitise/regenerate/block decision.
- `app/Services/AI/Policy/AdviceResponseDecision.php` — immutable result.
- `app/Jobs/AI/ProcessFynLearning.php` — queued post-turn proposal work.

### Canonical relationship memory

- `app/Models/UserMemoryFact.php` — typed user relationship memory.
- `app/Services/AI/Memory/MemoryTrust.php` — trust-state enum.
- `app/Services/AI/Memory/UserMemoryRepository.php` — persistence, supersession and retrieval.
- `app/Services/AI/Memory/UserMemoryMigrationService.php` — legacy Markdown import/reconcile.
- `app/Console/Commands/FynMemoryMigrate.php` — dry-run/execute migration command.
- `app/Http/Controllers/Api/Settings/FynMemoryController.php` — user-scoped list/confirm/correct/delete.
- `resources/js/views/Settings/FynMemorySettings.vue` and `resources/mobile/views/FynMemorySettings.vue` — parity surfaces.

---

### Task 22A: Make the launch regulatory operating mode explicit and fail closed

**Files:**
- Create: `app/Services/AI/Policy/AdviceOperatingMode.php`
- Create: `app/Services/AI/Policy/AdvicePolicy.php`
- Create: `app/Services/AI/Policy/AdvicePolicyResolver.php`
- Modify: `config/fyn.php`
- Modify: `.env.example`
- Modify: `app/Services/AI/Fyn/FynContextAssembler.php`
- Modify: `app/Services/AI/Fyn/FynSystemPrompt.php`
- Create: `tests/Unit/Services/AI/Policy/AdvicePolicyResolverTest.php`
- Modify: `tests/Unit/Services/AI/Fyn/FynContextAssemblerTest.php`
- Modify: `docs/superpowers/specs/fyn-system-prompt.snapshot.txt`
- Modify: affected fixtures under `tests/fixtures/PromptOverlay/`

**Interfaces:**
- `AdvicePolicyResolver::resolve(): AdvicePolicy`
- `AdvicePolicy::promptBlock(): string`
- Unknown, disabled or unapproved configuration produces the guidance policy and logs a configuration violation without exposing internal detail to the user.

- [ ] **Step 1: Write the failing policy-resolution tests**

```php
it('launches in guidance mode', function (): void {
    config(['fyn.advice_policy.mode' => 'guidance']);

    $policy = app(AdvicePolicyResolver::class)->resolve();

    expect($policy->mode)->toBe(AdviceOperatingMode::Guidance)
        ->and($policy->allowsPersonalRecommendation)->toBeFalse()
        ->and($policy->allowsNamedProducts)->toBeFalse()
        ->and($policy->requiresSuitabilityRecord)->toBeFalse();
});

it('fails closed when a regulated mode is not enabled', function (): void {
    config([
        'fyn.advice_policy.mode' => 'regulated_advice',
        'fyn.advice_policy.enabled_modes' => ['guidance'],
    ]);

    expect(app(AdvicePolicyResolver::class)->resolve()->mode)
        ->toBe(AdviceOperatingMode::Guidance);
});
```

- [ ] **Step 2: Run the tests and observe missing classes**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Policy/AdvicePolicyResolverTest.php`

Expected: FAIL because the policy contract does not exist.

- [ ] **Step 3: Add the enum and immutable policy**

```php
enum AdviceOperatingMode: string
{
    case Guidance = 'guidance';
    case TargetedSupport = 'targeted_support';
    case RegulatedAdvice = 'regulated_advice';
}

final readonly class AdvicePolicy
{
    public function __construct(
        public AdviceOperatingMode $mode,
        public string $version,
        public bool $allowsPersonalRecommendation,
        public bool $allowsNamedProducts,
        public bool $requiresSuitabilityRecord,
        public bool $requiresHumanReview,
    ) {}
}
```

The launch resolver returns `guidance@1`. It reads `enabled_modes` and an explicit `approved_release_modes` list; both must contain a non-guidance mode before selection. Do not put permissions or legal assertions in `.env` alone.

- [ ] **Step 4: Add fail-closed configuration**

```php
'advice_policy' => [
    'mode' => env('FYN_ADVICE_MODE', 'guidance'),
    'enabled_modes' => ['guidance'],
    'approved_release_modes' => ['guidance'],
    'version' => 'guidance@1',
],
```

Document `FYN_ADVICE_MODE=guidance` in `.env.example` and deployment templates without introducing a production toggle for disabled modes.

- [ ] **Step 5: Inject one dynamic policy block and remove prompt contradiction**

`FynContextAssembler` adds `<advice_policy>` from `AdvicePolicy::promptBlock()`. Update the static prompt so it identifies Fyn as a personal-finance guidance tool and delegates mode-specific permissions to that block. Replace the conflicting `RECOMMEND ACTIONS` instruction with `EXPLAIN ENGINE-GROUNDED OPTIONS AND NEXT STEPS`; preserve exact user figures and actionable explanation without claiming personal suitability.

- [ ] **Step 6: Regenerate prompt snapshots and run policy/prompt tests**

Run:

```bash
./vendor/bin/pest tests/Unit/Services/AI/Policy/AdvicePolicyResolverTest.php \
  tests/Unit/Services/AI/Fyn/FynContextAssemblerTest.php \
  tests/Unit/Services/AI/Fyn/FynSystemPromptTest.php
```

Expected: PASS; the assembled guidance prompt contains one coherent operating policy and no instruction to give a personal recommendation.

- [ ] **Step 7: Commit**

```bash
git add app/Services/AI/Policy app/Services/AI/Fyn config/fyn.php .env.example \
  tests/Unit/Services/AI tests/fixtures
git commit -m "feat: make Fyn guidance policy explicit"
```

---

### Task 22B: Compute classification, KYC and policy once per advice turn

**Files:**
- Create: `app/Services/AI/Fyn/FynTurnPreparation.php`
- Create: `app/Services/AI/Fyn/FynTurnPreparationHolder.php`
- Create: `app/Services/AI/Fyn/FynTurnPreparer.php`
- Modify: `app/Services/AI/AdviceFyn.php`
- Modify: `app/Traits/HasAiChat.php:145-197`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `tests/Unit/Services/AI/Fyn/FynTurnPreparerTest.php`
- Create: `tests/Feature/Fyn/SingleTurnPreparationTest.php`

**Interfaces:**
- `FynTurnPreparer::prepare(User, AiConversation, string, ?string): FynTurnPreparation`
- `FynTurnPreparationHolder::set/get/reset`
- Onboarding and legacy non-AdviceFyn callers retain a guarded fallback preparation path.

- [ ] **Step 1: Write the one-call regression test**

Use spies for `QueryClassifier` and `KycGateChecker`, execute one advice stream and assert each is called once. Add a generator-abandonment test asserting the holder is empty after `Generator::throw()`/early close.

```php
expect($classifier)->toHaveReceived('classify')->once();
expect($kyc)->toHaveReceived('check')->once();
expect(app(FynTurnPreparationHolder::class)->get())->toBeNull();
```

- [ ] **Step 2: Run and observe duplicate classification**

Run: `./vendor/bin/pest tests/Feature/Fyn/SingleTurnPreparationTest.php`

Expected: FAIL because `AdviceFyn` and `HasAiChat` both classify the turn.

- [ ] **Step 3: Add the immutable preparation shape**

```php
final readonly class FynTurnPreparation
{
    public function __construct(
        public array $classification,
        public ?array $kycResult,
        public AdvicePolicy $policy,
        public string $currentRouteLabel,
        public CarbonImmutable $preparedAt,
    ) {}
}
```

The preparer performs the existing query classification, Task 8 required-data KYC and policy resolution. It does not call financial engines or mutate state.

- [ ] **Step 4: Bind the holder as request scoped**

Add `FynTurnPreparationHolder::class` with the same scoped lifecycle pattern used for `SemanticSnapshotHolder`. `set()` rejects a second different preparation in one turn; `reset()` clears it.

- [ ] **Step 5: Make AdviceFyn the owner of preparation**

At the beginning of `AdviceFyn::handle()`, call the preparer once and use its classification for every deterministic bypass. Set the holder immediately before entering `FynLoop`; reset it in `finally` around `yield from`.

In `HasAiChat`, read the holder first. Only call the preparer when no preparation exists, which covers onboarding/legacy paths. Remove its direct `QueryClassifier`/`KycGateChecker` calls.

- [ ] **Step 6: Run focused and Fyn tests**

Run:

```bash
./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynTurnPreparerTest.php \
  tests/Feature/Fyn/SingleTurnPreparationTest.php \
  tests/Feature/Fyn tests/Feature/AI
```

Expected: PASS; advice classification and KYC execute once, and holders clear on every exit.

- [ ] **Step 7: Commit**

```bash
git add app/Services/AI/Fyn app/Services/AI/AdviceFyn.php app/Traits/HasAiChat.php \
  app/Providers/AppServiceProvider.php tests/Unit/Services/AI/Fyn \
  tests/Feature/Fyn/SingleTurnPreparationTest.php
git commit -m "refactor: prepare each Fyn turn once"
```

---

### Task 22C: Add the canonical structured Advice Case

**Files:**
- Create: `database/migrations/2026_07_10_120000_extend_ai_advice_logs_for_advice_cases.php`
- Modify: `app/Models/AiAdviceLog.php`
- Create: `app/Services/AI/Advice/AdviceCase.php`
- Create: `app/Services/AI/Advice/AdviceCaseHolder.php`
- Create: `app/Services/AI/Advice/AdviceCaseRecorder.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Services/AI/AdviceFyn.php`
- Modify: `app/Traits/HasAiChat.php:900-965`
- Modify: `app/Services/AI/Memory/Episodic/EpisodeBlobData.php`
- Create: `tests/Feature/AI/AdviceCasePersistenceTest.php`
- Modify: `tests/Feature/AI/EpisodePersistenceTest.php`

**Interfaces:**
- `AdviceCase::begin(User, AiConversation, string, FynTurnPreparation): self`
- Fluent mutation methods return a new immutable case or use a single holder-owned mutable builder; no static/global state.
- `AdviceCaseRecorder::complete(AiMessage, AdviceCase): AiAdviceLog`
- `ai_advice_logs.message_id` is unique where non-null, preventing duplicate cases for one answer.

- [ ] **Step 1: Write the failing Advice Case persistence test**

Execute one scripted retirement answer with a tax tool. Assert one row linked to the assistant message and signed episode:

```php
expect(AiAdviceLog::where('message_id', $assistant->id)->count())->toBe(1);

$case = AiAdviceLog::where('message_id', $assistant->id)->firstOrFail();
expect($case->operating_mode)->toBe('guidance')
    ->and($case->policy_version)->toBe('guidance@1')
    ->and($case->status)->toBe('completed')
    ->and($case->facts_used)->toBeArray()
    ->and($case->source_versions)->toBeArray();
```

- [ ] **Step 2: Run and observe the incomplete legacy log**

Run: `./vendor/bin/pest tests/Feature/AI/AdviceCasePersistenceTest.php`

Expected: FAIL because `AiAdviceLog` contains only the legacy classification/snapshot fields.

- [ ] **Step 3: Add the structured fields**

Before adding the unique index, query `ai_advice_logs` for non-null duplicate `message_id` values. The migration aborts with a clear exception if duplicates exist; reconciliation is a separate reviewed data task and must not silently discard an audit record. The migration then adds nullable/indexable scalars plus JSON:

```php
$table->string('operating_mode', 32)->default('guidance')->index();
$table->string('policy_version', 64)->nullable();
$table->string('advice_route', 16)->nullable()->index();
$table->string('status', 16)->default('completed')->index();
$table->json('facts_used')->nullable();
$table->json('memories_used')->nullable();
$table->json('engine_outputs')->nullable();
$table->json('assumptions')->nullable();
$table->json('missing_data')->nullable();
$table->json('policy_decisions')->nullable();
$table->json('source_versions')->nullable();
$table->json('planner_trace')->nullable();
$table->unique('message_id');
```

Preserve the legacy columns during launch; the recorder fills both until admin/report consumers migrate. Do not duplicate full assembled prompts or raw tool-result bodies into the Advice Case.

- [ ] **Step 4: Create the case/holder/recorder**

Begin the case after turn preparation. Add facts, memories, engine outputs, tool calls and policy decisions through named methods. The recorder runs after the assistant message exists and uses `updateOrCreate(['message_id' => ...])` so retries are idempotent.

- [ ] **Step 5: Link the signed episode without duplicating truth**

Add `advice_case_id` to the episode frontmatter data, populated from the persisted log. If the episode currently writes before the log, reverse the two guarded operations or create the log shell before blob persistence and complete it afterward. A failure marks the case `failed`; it must not create two rows.

- [ ] **Step 6: Migrate, seed and run persistence tests**

Run:

```bash
php artisan migrate
php artisan db:seed
./vendor/bin/pest tests/Feature/AI/AdviceCasePersistenceTest.php \
  tests/Feature/AI/EpisodePersistenceTest.php
```

Expected: PASS; one answer has one Advice Case and one signed episode reference.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/AiAdviceLog.php app/Services/AI/Advice \
  app/Services/AI/AdviceFyn.php app/Traits/HasAiChat.php app/Providers/AppServiceProvider.php \
  app/Services/AI/Memory/Episodic/EpisodeBlobData.php tests/Feature/AI
git commit -m "feat: persist structured Fyn advice cases"
```

---

### CHECKPOINT 1 — Operating policy, preparation and Advice Case

Before Task 22D:

1. Deploy Tasks 22A-22C to csjones on the feature branch.
2. Ask a factual and a recommendation-mode question on desktop and `/m`.
3. Verify one preparation and one Advice Case per answer.
4. Verify `guidance` fail-closed behaviour under a deliberately invalid staging configuration.
5. Confirm no write tool appears or executes on Advice Fyn.
6. Attach redacted SQL/episode evidence and proceed only when the checkpoint is green.

---

### Task 22D: Assemble one evidence snapshot for planner and reasoner

**Files:**
- Create: `app/Services/AI/Fyn/FynEvidenceSnapshot.php`
- Create: `app/Services/AI/Fyn/FynEvidenceHolder.php`
- Create: `app/Services/AI/Fyn/FynEvidenceAssembler.php`
- Modify: `app/Services/AI/Fyn/FynContextAssembler.php`
- Modify: `app/Services/AI/Loop/FynLoop.php`
- Modify: `app/Traits/HasAiChat.php:1084-1133`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `tests/Unit/Services/AI/Fyn/FynEvidenceAssemblerTest.php`
- Create: `tests/Feature/Fyn/SingleEvidenceSnapshotTest.php`

**Interfaces:**
- `FynEvidenceAssembler::assemble(FynTurnContext, ?callable): FynEvidenceSnapshot`
- `FynEvidenceSnapshot::promptBlock(): string`
- `FynEvidenceSnapshot::plannerContext(): string`
- `FynEvidenceSnapshot::provenance(): array`

- [ ] **Step 1: Write the retrieval-once and relevance tests**

Seed five recent irrelevant episodes and one older relevant retirement episode. Spy on procedure, semantic, episodic and pointer retrieval. Assert one call per source and that planner/reasoner receive the same relevant episode identifiers.

- [ ] **Step 2: Run and observe divergent recall**

Run: `./vendor/bin/pest tests/Feature/Fyn/SingleEvidenceSnapshotTest.php`

Expected: FAIL because the planner calls query-ranked `recallContext($user, $query)` while the assembler separately calls recent-only `recallContext($user)`.

- [ ] **Step 3: Add the immutable snapshot**

The snapshot contains rendered prompt blocks plus structured lists:

```php
final readonly class FynEvidenceSnapshot
{
    public function __construct(
        public string $promptBlock,
        public array $facts,
        public array $memories,
        public array $episodes,
        public array $procedures,
        public array $liveSources,
        public array $sourceVersions,
    ) {}
}
```

Each entry contains an internal identifier, source/trust, effective/as-of time and a prompt-safe rendered value. The prompt never exposes internal IDs.

- [ ] **Step 4: Move retrieval from `FynContextAssembler` into `FynEvidenceAssembler`**

Reuse the existing selector, live pointer dispatcher, semantic retriever, procedural contribution/version collectors and sized-analysis closure. Pass the current user message to every relevance operation. `FynContextAssembler::build()` becomes a compatibility facade returning `snapshot->promptBlock()`.

- [ ] **Step 5: Share through a scoped holder**

The first advice caller assembles and sets the snapshot. `FynLoop` uses `plannerContext()`; `HasAiChat::injectUnifiedTurnContext` uses `promptBlock()`. Both record the same provenance into the Advice Case. Reset in `finally` on completion, abort and exception.

- [ ] **Step 6: Run focused and context golden-master tests**

Run:

```bash
./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynEvidenceAssemblerTest.php \
  tests/Feature/Fyn/SingleEvidenceSnapshotTest.php \
  tests/Unit/Services/AI/Fyn/FynContextAssemblerTest.php \
  tests/Feature/AI/PromptOverlayGoldenMasterTest.php
```

Expected: PASS; one source snapshot is shared and only relevant episodes/procedures are injected.

- [ ] **Step 7: Commit**

```bash
git add app/Services/AI/Fyn app/Services/AI/Loop/FynLoop.php app/Traits/HasAiChat.php \
  app/Providers/AppServiceProvider.php tests/Unit/Services/AI/Fyn \
  tests/Feature/Fyn/SingleEvidenceSnapshotTest.php tests/fixtures/PromptOverlay
git commit -m "refactor: share one Fyn evidence snapshot"
```

---

### Task 22E: Gate the planner by deterministic complexity and move learning off the hot path

**Files:**
- Create: `app/Services/AI/Loop/AdviceRoute.php`
- Create: `app/Services/AI/Loop/AdviceComplexityRouter.php`
- Modify: `app/Services/AI/Loop/FynLoop.php`
- Modify: `config/fyn.php`
- Create: `app/Jobs/AI/ProcessFynLearning.php`
- Modify: `app/Services/AI/ConversationSummariser.php`
- Modify: `app/Services/AI/Cost/AiCostAttributionService.php`
- Create: `tests/Unit/Services/AI/Loop/AdviceComplexityRouterTest.php`
- Create: `tests/Feature/Fyn/PlannerRoutingTest.php`
- Create: `tests/Feature/Fyn/Learning/QueuedLearningTest.php`

**Interfaces:**
- `AdviceComplexityRouter::route(FynTurnPreparation, FynEvidenceSnapshot, AiConversation): AdviceRoute`
- Config `fyn.planner.mode = off|shadow|active`, launch default `shadow`.
- Direct turns call `reason()` with no planner call.
- Shadow turns may queue/record a planner comparison but never delay or control the live answer.

- [ ] **Step 1: Write the route matrix tests**

Cover factual, single-module, missing-data, write intent, follow-up, holistic and explicit cross-module comparison cases. The first five are direct; the latter two are planned-eligible.

- [ ] **Step 2: Prove a normal turn currently calls the planner**

Run: `./vendor/bin/pest tests/Feature/Fyn/PlannerRoutingTest.php`

Expected: FAIL because `FynLoop::run()` calls `Planner::plan()` before every non-bypassed advice answer.

- [ ] **Step 3: Implement the deterministic router**

Use Task 8's classification/required-data contract and explicit module count. Do not call an LLM to determine complexity. Record the route and signals in the Advice Case and `AgentDecision` trace.

- [ ] **Step 4: Split direct and planned loop paths**

```php
if ($route === AdviceRoute::Direct) {
    yield ['type' => 'thinking'];
    yield from $this->reason(...);
    return;
}
```

`planned + active` uses the existing bounded planner. `planned + shadow` serves the direct answer and dispatches a redacted evaluation job after persistence. `off` always serves direct. Planner failure always degrades to direct reasoning.

- [ ] **Step 5: Queue summarisation and learning proposals**

After the assistant message and Advice Case persist, dispatch `ProcessFynLearning` only when `FYN_LEARNING_ENABLED=true`. The job receives IDs, reloads canonical records, checks erasure/deletion state, and stages proposals; it does not receive serialized full user models or plaintext secrets. Conversation summarisation leaves the response path under the same queue discipline.

- [ ] **Step 6: Run route, queue and cost tests**

Run:

```bash
./vendor/bin/pest tests/Unit/Services/AI/Loop/AdviceComplexityRouterTest.php \
  tests/Feature/Fyn/PlannerRoutingTest.php \
  tests/Feature/Fyn/Learning/QueuedLearningTest.php
```

Expected: PASS; ordinary turns have `planner_calls=0`, eligible shadow turns record comparison telemetry without delaying response, and disabled learning queues nothing.

- [ ] **Step 7: Commit**

```bash
git add app/Services/AI/Loop app/Jobs/AI app/Services/AI/ConversationSummariser.php \
  app/Services/AI/Cost config/fyn.php tests/Unit/Services/AI/Loop \
  tests/Feature/Fyn/PlannerRoutingTest.php tests/Feature/Fyn/Learning
git commit -m "perf: gate Fyn planning by turn complexity"
```

---

### CHECKPOINT 2 — Evidence reuse and planner efficiency

1. Deploy Tasks 22D-22E to csjones.
2. Run a single-module retirement question and verify zero planner calls.
3. Run a holistic cross-module comparison and verify shadow telemetry uses the same evidence snapshot.
4. Compare time-to-first-content, total duration, tokens and cost against pre-change evidence.
5. Verify desktop and `/m` quote the same engine figures.
6. Verify a write intent still performs one hidden handoff and one database write.
7. Loop until all checks are green.

---

### Task 22F: Make the existing SQL/signed-blob episodic subsystem canonical

**Files:**
- Modify: `app/Services/AI/Memory/FynMemoryStore.php`
- Modify: `app/Services/AI/Memory/Episodic/EpisodeRetriever.php`
- Create: `app/Services/AI/Memory/Episodic/EpisodeRecallService.php`
- Modify: `app/Services/AI/Fyn/FynEvidenceAssembler.php`
- Modify: `app/Services/AI/Loop/FynLoop.php`
- Modify: `config/fyn.php`
- Modify: `app/Services/Account/RetentionPurgeService.php`
- Modify: `app/Console/Commands/FynUserErase.php`
- Create: `tests/Feature/Fyn/Memory/CanonicalEpisodeRecallTest.php`
- Modify: `tests/Unit/Services/AI/FynMemoryStoreTest.php`
- Modify: episode retention/erasure tests.

**Interfaces:**
- `EpisodeRecallService::recall(int $userId, string $query, int $limit=5): Collection`
- Runtime recall uses `ai_messages` structured fields and lazy-loads signed blobs only for the selected candidates.
- `FynMemoryStore` retains procedural/rubric responsibilities temporarily but exposes no runtime per-user episode write/recall after cutover.

- [ ] **Step 1: Write canonical recall tests**

Seed assistant episodes with structured summary/observation metadata and signed blobs. Add legacy Markdown duplicates. Assert recall returns each episode once from SQL, relevance-ranks with current query and performs no full directory scan.

- [ ] **Step 2: Run and observe Markdown runtime dependence**

Run: `./vendor/bin/pest tests/Feature/Fyn/Memory/CanonicalEpisodeRecallTest.php`

Expected: FAIL because planner/reasoner recall still uses `FynMemoryStore` file glob/parse.

- [ ] **Step 3: Implement SQL-first candidate selection**

Extend `EpisodeRetriever` to select user-owned assistant messages with summary/observation, timestamps, source versions and blob reference. Rank a bounded recent candidate set with the existing sparse scorer; lazy-load only selected blobs when detail is required. Keep query counts bounded and tested.

- [ ] **Step 4: Stop writing planner-authored Markdown summaries**

Remove `FynLoop::recordEpisode()` calls to `FynMemoryStore::writeEpisode`. Learning proposals reference the persisted assistant message/episode instead. Retain a deprecated read-only adapter only for the migration command, not runtime recall.

- [ ] **Step 5: Reconcile retention and erasure**

Ensure purge, cold archive, export and `fyn:user:erase` operate on SQL/signed blobs and also remove any legacy `fyn-memory/episodic/episodes/{user}` tree. Add orphan reconciliation assertions.

- [ ] **Step 6: Run memory, retention and performance tests**

Run:

```bash
./vendor/bin/pest tests/Feature/Fyn/Memory/CanonicalEpisodeRecallTest.php \
  tests/Feature/AI/EpisodePersistenceTest.php \
  tests/Unit/Services/Account/RetentionPurgeServiceTest.php \
  tests/Feature/Console/FynUserEraseTest.php
```

Expected: PASS; runtime recall has one canonical result per episode and no full Markdown scan.

- [ ] **Step 7: Commit**

```bash
git add app/Services/AI/Memory app/Services/AI/Fyn/FynEvidenceAssembler.php \
  app/Services/AI/Loop/FynLoop.php app/Services/Account/RetentionPurgeService.php \
  app/Console/Commands/FynUserErase.php config/fyn.php tests
git commit -m "refactor: consolidate Fyn episodic memory"
```

---

### Task 22G: Replace per-user Markdown semantic memory with typed SQL relationship memory

**Files:**
- Create: `database/migrations/2026_07_10_130000_create_user_memory_facts_table.php`
- Create: `app/Models/UserMemoryFact.php`
- Create: `app/Services/AI/Memory/MemoryTrust.php`
- Create: `app/Services/AI/Memory/UserMemoryRepository.php`
- Create: `app/Services/AI/Memory/UserMemoryMigrationService.php`
- Create: `app/Console/Commands/FynMemoryMigrate.php`
- Modify: `app/Services/AI/Memory/SemanticRetriever.php`
- Modify: `app/Services/AI/Learning/SemanticFactPromoter.php`
- Modify: `app/Services/AI/Memory/UserSemanticStore.php` to a migration-only legacy adapter.
- Modify: `app/Console/Commands/FynUserErase.php`
- Create: `tests/Feature/Fyn/Memory/UserMemoryRepositoryTest.php`
- Create: `tests/Feature/Console/FynMemoryMigrateTest.php`
- Modify: semantic fact promotion/retrieval tests.

**Interfaces:**
- `UserMemoryRepository::relevantFor(User, string, int $limit=5): Collection`
- `confirm`, `correct`, `forget` are user-scoped and transactional.
- A correction creates a new row and sets the previous row to `superseded`; it never silently overwrites provenance.

- [ ] **Step 1: Write schema and trust-invariant tests**

The table includes:

```php
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('fact_key', 160);
$table->string('category', 64);
$table->json('value');
$table->text('display_text');
$table->string('trust_state', 32)->index();
$table->string('source_type', 32);
$table->foreignId('source_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
$table->unsignedBigInteger('source_message_id')->nullable();
$table->dateTime('valid_from')->nullable();
$table->dateTime('valid_to')->nullable();
$table->dateTime('confirmed_at')->nullable();
$table->foreignId('superseded_by_id')->nullable()->constrained('user_memory_facts')->nullOnDelete();
$table->string('status', 24)->default('active')->index();
$table->dateTime('last_used_at')->nullable();
$table->timestamps();
$table->index(['user_id', 'fact_key', 'status']);
```

Enforce one active row per user/fact key inside `UserMemoryRepository` with `DB::transaction()` plus `lockForUpdate()` over the matching rows. The supporting composite index keeps that lock/query bounded while allowing more than one historical superseded version.

- [ ] **Step 2: Run and observe missing canonical store**

Run: `./vendor/bin/pest tests/Feature/Fyn/Memory/UserMemoryRepositoryTest.php`

Expected: FAIL because approved facts are Markdown files without typed trust/provenance/supersession.

- [ ] **Step 3: Implement trust and repository rules**

`MemoryTrust` values are `user_confirmed`, `user_stated_unverified`, and `inference_proposed`. Canonical financial state and historical episodes are not relationship-memory rows. Retrieval returns only active, effective facts; proposed inference never enters prompts. Canonical live data always wins a conflict.

- [ ] **Step 4: Make semantic promotion write SQL**

Admin approval promotes a proposal to `user_stated_unverified` unless the proposal contains recorded direct user confirmation. It never writes a live financial figure or global corpus. Enforce `FYN_LEARNING_ENABLED` at promotion as well as proposal creation.

- [ ] **Step 5: Add the idempotent migration command**

```text
php artisan fyn:memory:migrate                 # dry run
php artisan fyn:memory:migrate --user=123      # scoped dry run
php artisan fyn:memory:migrate --force         # write
php artisan fyn:memory:migrate --force --verify
```

The command parses legacy per-user Markdown facts, derives stable keys, writes/upserts SQL rows with legacy-file provenance, compares counts/hashes and reports conflicts. It never deletes files. File deletion happens only after a separately recorded production verification.

- [ ] **Step 6: Switch retrieval and erasure to SQL**

`SemanticRetriever::retrieveForUser()` consumes `UserMemoryRepository`; global semantic retrieval remains unchanged. `FynUserErase` deletes SQL rows and legacy directories. Export/retention tasks consume the new relation.

- [ ] **Step 7: Migrate locally, seed and run tests**

Run:

```bash
php artisan migrate
php artisan db:seed
php artisan fyn:memory:migrate --force --verify
./vendor/bin/pest tests/Feature/Fyn/Memory tests/Feature/Fyn/Learning \
  tests/Feature/Console/FynMemoryMigrateTest.php tests/Feature/Console/FynUserEraseTest.php
```

Expected: PASS; SQL and legacy counts reconcile, only active trusted facts are recalled, and erasure removes both forms.

- [ ] **Step 8: Commit**

```bash
git add database/migrations app/Models/UserMemoryFact.php app/Services/AI/Memory \
  app/Services/AI/Learning/SemanticFactPromoter.php app/Console/Commands \
  tests/Feature/Fyn tests/Feature/Console
git commit -m "feat: add typed Fyn relationship memory"
```

---

### Task 22H: Give users memory visibility, correction and deletion on desktop and `/m`

**Files:**
- Read before UI changes: `fynlaDesignGuide.md`
- Create: `app/Http/Controllers/Api/Settings/FynMemoryController.php`
- Create: `app/Http/Requests/ConfirmFynMemoryRequest.php`
- Create: `app/Http/Requests/CorrectFynMemoryRequest.php`
- Modify: `routes/api.php`
- Create: `resources/js/services/fynMemoryService.js`
- Create: `resources/js/views/Settings/FynMemorySettings.vue`
- Modify: `resources/js/router/index.js`
- Modify: `resources/js/views/Settings.vue`
- Modify: `resources/js/components/Settings/SettingsTabBar.vue`
- Create: `resources/mobile/views/FynMemorySettings.vue`
- Modify: `resources/mobile/api.js`
- Modify: `resources/mobile/router.js`
- Modify: `resources/mobile/components/MobileChrome.vue`
- Modify: `app/Services/AI/AdviceFyn.php`, `WriteIntentClassifier.php`, `OnboardingChatDirector.php`, tool schemas/allowlists for memory correction handoff.
- Create: `tests/Feature/Api/Settings/FynMemoryControllerTest.php`
- Create: `tests/frontend/settings/fynMemorySettings.test.js`
- Create: `tests/Browser/acceptance/fyn-memory-control.yaml`

**Interfaces:**
- `GET /api/settings/fyn-memory`
- `PATCH /api/settings/fyn-memory/{memory}/confirm`
- `POST /api/settings/fyn-memory/{memory}/correct`
- `DELETE /api/settings/fyn-memory/{memory}`
- Every query is scoped to `request()->user()`; cross-user IDs return 404.

- [ ] **Step 1: Write API ownership/preview tests**

Cover list, confirm, correction/supersession, delete, cross-user 404, proposed-inference exclusion, preview-write interception and response resources that expose no conversation/message internal IDs.

- [ ] **Step 2: Run and observe missing endpoints**

Run: `./vendor/bin/pest tests/Feature/Api/Settings/FynMemoryControllerTest.php`

Expected: FAIL with 404/missing controller.

- [ ] **Step 3: Implement the user-scoped API**

Return `id`, category label, display text, trust label, effective date, confirmation date and last-used date. Correction accepts one plain-text replacement plus optional effective date, creates a superseding row transactionally and returns both current and replaced status. Delete uses the repository and writes an auditable user action.

- [ ] **Step 4: Build desktop and `/m` parity surfaces**

Desktop uses `AppLayout` and existing Settings patterns. `/m` uses `MobileLayout`. Both show plain sections for Confirmed, Needs confirmation and Past/corrected memories. Use no new icons or scores. Include empty/loading/error states and clear copy explaining that live account values come from financial records, not this memory list.

- [ ] **Step 5: Route chat corrections through capture**

Classify "forget that", "that is no longer true", "change what you remember about..." as write intents. Add narrowly scoped `confirm_memory_fact`, `correct_memory_fact` and `forget_memory_fact` write surfaces to `AdviceFyn::WRITE_TOOLS`, capture tool schemas and `OnboardingChatDirector` handlers. Advice mode can only call `delegate_to_capture`; GroundGate rejects direct memory writes.

- [ ] **Step 6: Add frontend and browser tests**

The browser manifest signs in, opens memory settings on desktop and `/m`, confirms one fact, corrects one fact, deletes one fact, verifies the list, then asks Fyn to forget a seeded fact and verifies the invisible handoff and database state.

- [ ] **Step 7: Run focused verification**

Run:

```bash
./vendor/bin/pest tests/Feature/Api/Settings/FynMemoryControllerTest.php \
  tests/Feature/Fyn tests/Feature/AI
npm run test:frontend -- tests/frontend/settings/fynMemorySettings.test.js
node scripts/quality/validate-acceptance.mjs tests/Browser/acceptance/fyn-memory-control.yaml
```

Expected: PASS on both surfaces; direct advice writes remain impossible.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/Settings app/Http/Requests routes/api.php \
  app/Services/AI resources/js resources/mobile tests/Feature/Api/Settings \
  tests/frontend/settings tests/Browser/acceptance/fyn-memory-control.yaml
git commit -m "feat: let users control Fyn memory"
```

---

### CHECKPOINT 3 — Typed memory and user control

1. Run `fyn:memory:migrate --force --verify` against a staging copy and retain redacted reconciliation evidence.
2. Exercise confirmed, unverified, corrected, superseded and deleted facts on desktop and `/m`.
3. Verify relevant recall uses the current fact and stale/superseded facts never enter the prompt.
4. Verify chat correction/forgetting uses the capture handoff and one auditable write.
5. Run export, self-service deletion and `fyn:user:erase`; verify no SQL row, signed blob or legacy file orphan remains outside the approved retention contract.
6. Do not delete production legacy files yet; record the later cleanup decision in the release manifest.

---

### Task 22I: Add mechanical guidance-response policy enforcement

**Files:**
- Create: `app/Services/AI/Policy/AdviceResponseAction.php`
- Create: `app/Services/AI/Policy/AdviceResponseDecision.php`
- Create: `app/Services/AI/Policy/AdviceResponseGate.php`
- Modify: `app/Services/AI/StructuredResponseValidator.php`
- Modify: `app/Traits/HasAiChat.php` before stream finalisation/persistence.
- Modify: `app/Models/AiAdviceLog.php`
- Modify: `app/Http/Controllers/Api/Admin/AdviceViolationController.php`
- Modify: `resources/js/views/Admin/AdviceViolations.vue`
- Modify: existing assistant-message `metadata.validation_violations`; do not add a second violations table.
- Create: `tests/Unit/Services/AI/Policy/AdviceResponseGateTest.php`
- Create: `tests/Feature/Fyn/GuidancePolicyEnforcementTest.php`
- Extend: `tests/Feature/Fyn/Eval/scenarios/07-regulatory/` and `09-canonical-behaviour/`.

**Interfaces:**
- `AdviceResponseGate::evaluate(AdviceCase, string): AdviceResponseDecision`
- Actions: `allow`, `sanitise`, `regenerate`, `block`.
- The gate runs on complete candidate text before persistence; streaming uses the existing safe buffering boundary for advice turns where regeneration/blocking may be required.

- [ ] **Step 1: Write the policy matrix tests**

Cover:

- unsupported number absent from Advice Case -> block;
- fabricated save -> block;
- disabled operating mode/personal suitability claim -> block;
- missing mandatory adviser signpost -> deterministic append/sanitise;
- banned financial-quality score -> sanitise/block per Task 22 contract;
- named provider/product -> report-only at launch unless explicitly presented as suitable;
- risk/tax caveat absent -> regenerate or deterministic append under the policy matrix;
- clean engine-grounded option explanation -> allow.

- [ ] **Step 2: Run and observe prompt-only behaviour**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Policy/AdviceResponseGateTest.php`

Expected: FAIL because current regulatory controls are prompt/log-only.

- [ ] **Step 3: Implement deterministic high-confidence checks**

Use Advice Case evidence to extract normalized currency/percentage/date claims and ensure each exists in facts, engine outputs, tax provenance or an explicitly labelled assumption. Reuse Task 9 repetition and Task 22 acronym/icon/score sanitisation. Never attempt free-form legal interpretation in the gate.

- [ ] **Step 4: Add one bounded regeneration**

For `regenerate`, send machine-readable violations and the same evidence snapshot to one tools-disabled correction pass. Re-evaluate once. A second failure returns approved deterministic blocked copy and records both attempts. It never loops indefinitely.

- [ ] **Step 5: Persist policy evidence**

Write action, rule IDs, sanitized fields, regeneration count and final disposition to `ai_advice_logs.policy_decisions` and the canonical violations queue. Logs contain IDs/hashes, not raw personal financial context.

- [ ] **Step 6: Run policy, eval and Fyn suites**

Run:

```bash
./vendor/bin/pest tests/Unit/Services/AI/Policy/AdviceResponseGateTest.php \
  tests/Feature/Fyn/GuidancePolicyEnforcementTest.php \
  tests/Feature/Fyn/Eval tests/Feature/Fyn tests/Feature/AI
```

Expected: PASS; high-confidence violations cannot persist or stream as successful advice.

- [ ] **Step 7: Commit**

```bash
git add app/Services/AI/Policy app/Services/AI/StructuredResponseValidator.php \
  app/Traits/HasAiChat.php app/Models tests/Unit/Services/AI/Policy \
  tests/Feature/Fyn
git commit -m "feat: enforce Fyn guidance policy mechanically"
```

---

### Task 22J: Add evaluation, telemetry and controlled activation gates

**Files:**
- Modify: `app/Services/AI/Cost/AiCostAttributionService.php`
- Create/modify: telemetry migration/model used by Fyn turn metrics; reuse existing cost/decision tables where sufficient.
- Create: `app/Services/AI/Evaluation/PlannerShadowEvaluator.php`
- Create: `tests/Feature/Fyn/Eval/scenarios/10-memory/`.
- Extend: regulatory, provider-parity, canonical-behaviour and repetition scenarios.
- Create: `tests/Feature/Fyn/Eval/AdviceArchitectureEvalTest.php`
- Create: `tests/Browser/acceptance/fyn-evidence-first-gauntlet.yaml`
- Create: `docs/online-readiness/fyn-advice-architecture-go-no-go.md`
- Modify: `docs/online-readiness/coverage-matrix.md`, `audit-ledger.yaml`, release manifest.

**Interfaces:**
- Metrics: time to first content, total duration, model calls, tokens/cost, direct/planned route, planner shadow outcome, evidence counts, policy action and user-memory corrections.
- No raw user message, prompt or tool result is duplicated into telemetry.

- [ ] **Step 1: Write metric and eval-count contract tests**

Assert every completed Advice Case has timing/route/cost/policy fields and every required scenario category is non-empty. Add fixtures for relevant/stale/conflicting/corrected/deleted memory, simple/direct and complex/planned turns, unsupported figures, write safety and surface parity.

- [ ] **Step 2: Implement shadow comparison without user-path impact**

For eligible turns, compare planner-proposed action with the served direct trajectory asynchronously. Grade grounded facts, required tools, missing-data handling, policy outcome and task completion; never compare byte-identical prose.

- [ ] **Step 3: Define the activation decision record**

`fyn-advice-architecture-go-no-go.md` records:

- launch operating mode and policy version;
- planner mode (`off`, `shadow`, or approved `active` subset);
- time/cost budgets and observed percentiles;
- numerical consistency and memory relevance results;
- policy violation/regeneration results;
- migration/reconciliation/erasure evidence;
- explicit `FYN_LEARNING_ENABLED` decision (default remains false);
- CSJ sign-off and rollback values.

- [ ] **Step 4: Run automated architecture evaluation**

Run:

```bash
./vendor/bin/pest tests/Feature/Fyn/Eval/AdviceArchitectureEvalTest.php \
  tests/Feature/Fyn/Eval tests/Feature/Fyn tests/Feature/AI tests/Unit/Services/AI
npm run test:frontend
node scripts/quality/validate-acceptance.mjs tests/Browser/acceptance/fyn-evidence-first-gauntlet.yaml
```

Expected: PASS; no empty evaluation categories; direct-turn planner calls are zero; every high-confidence policy scenario is mechanically controlled.

- [ ] **Step 5: Run the live-provider browser gauntlet on immutable staging**

Run the manifest on desktop and `/m` for factual, module advice, holistic comparison, missing data, write capture, memory correction, tax provenance, investment warning, unsupported product and disconnection/resume. Verify Advice Case, signed episode, audit chain, policy decision and UI output for each. Loop until green.

- [ ] **Step 6: Record go/no-go and retain fail-closed defaults**

Unless CSJ explicitly approves otherwise, release with:

```dotenv
FYN_ADVICE_MODE=guidance
FYN_PLANNER_MODE=shadow
FYN_LEARNING_ENABLED=false
```

Do not activate regulated modes or learning as a convenience during deployment.

- [ ] **Step 7: Commit**

```bash
git add app/Services/AI tests/Feature/Fyn/Eval tests/Browser/acceptance \
  docs/online-readiness
git commit -m "test: gate Fyn evidence-first launch"
```

---

### FINAL CHECKPOINT — Pre-launch Fyn architecture acceptance

Before the master programme proceeds to the framework upgrade and whole-product gauntlet:

1. Run the complete PHP, frontend, lint and automated browser lanes.
2. Run the evidence-first manifest on immutable csjones staging on desktop and `/m`.
3. Verify every substantive response has one Advice Case and one linked signed episode.
4. Verify simple/module turns make no planner call; shadow work never delays the response.
5. Verify all canonical figures match Fyn, desktop and `/m`.
6. Verify memory visibility/correction/deletion, export, purge and erasure.
7. Verify GroundGate and catalogue strip still block every advice write surface.
8. Verify policy blocks unsupported figures/fabricated saves/disabled modes and records evidence.
9. Verify fail-closed deployment values and rollback by configuration.
10. Obtain CSJ go/no-go in `fyn-advice-architecture-go-no-go.md`.

Any red result routes through the plan's diagnose -> fix -> full-browser-reverify loop. The master Task 23 may not start until this checkpoint is green.

## Self-review mapping

| Design requirement | Implementation task |
|---|---|
| Explicit launch guidance perimeter | 22A |
| One classification/KYC/policy preparation | 22B |
| One structured Advice Case | 22C |
| One shared evidence snapshot | 22D |
| Complexity-gated planning and async learning | 22E |
| Canonical SQL/signed-blob episodic memory | 22F |
| Typed relationship memory and migration | 22G |
| Desktop/`/m` user correction and chat handoff | 22H |
| Mechanical response policy | 22I |
| Evals, telemetry and activation gate | 22J |

No task enables regulated advice, autonomous learning, dense retrieval or multiple conversational agents. Those remain outside launch scope.
