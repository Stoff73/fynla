# Fyn v2 — Canonical Two-Fyn Contract

> **BRANCH: `feature/fyn-persona-split`.** All implementation builds on this branch.

This statement is the source of truth for every doc, spec, plan, PRD, and task list in this workstream. It appears verbatim at the top of every artefact.

---

**FYN HAS TWO STATES.**

**ONBOARDING FYN** takes a user through the onboarding flow using bubbles for the user to choose the path, and guides them through the flow they choose. It accepts multi-line information and **SAVES AND WRITES** it to the database so user information is persisted. It has memory: any additional information already entered is not asked about again, but is resurfaced to the user at the right time to give a view of intelligence. If a user leaves at any point in the conversation, the next time they log in Onboarding Fyn picks up from where they left off (example only, not the whole scope: *"Good afternoon CSJ — last time we were busy entering your family details, you told me about X. Do you want to continue from where we left off?"* Yes / No bubble). Journeys are mapped according to what the user wants and where they enter onboarding from. Onboarding Fyn also receives handovers from Advice Fyn for any outstanding information needed to produce guidance. **Onboarding Fyn is the ONLY state that enters or edits information.**

**ADVICE FYN** takes a user request, fetches the user's information, and answers that request using the recommendation engine, the risk module, and every other module or system in the app as needed. Examples only, not the whole scope:

- *"Where's my invoice?"* → Advice Fyn checks subscription status and navigates to the subscription page, confirming the subscription.
- *"Should I contribute more to my ISA?"* → Advice Fyn uses the recommendation engine to surface the guidance the engine produces and navigates to the portfolio page.

Advice Fyn covers tax optimisation (income tax, asset splitting between spouses, etc.), and all other guidance across every module as per the financial planning remit, classification system, recommendation engine, and all the investment, retirement, protection, estate engines and modules. **The ONLY thing Advice Fyn does NOT do is enter or edit information** — that is Onboarding Fyn's job.

**THE USER NEVER SEES THE HANDOFF, OR FEELS THE SWITCH**, between the two states.

---

## What this means for code

- One dispatch decision in `AiChatController::sendMessage`: onboarding or advice, based on `users.onboarding_completed`.
- Onboarding Fyn = the existing `OnboardingChatDirector` (promoted) with a new `handleInlineCapture` entry point for post-onboarding captures.
- Advice Fyn = a new `AdviceFyn` class wrapping the advice-side prompt + chat loop + read-only tool list.
- No `FynPersonaOrchestrator`, no `FynPersonaInvoker`, no `FynPersonaRegistry`, no `DataCapturePromptBuilder`.
- `HandoffContract` constants and `CaptureContext` VO are kept.
- Zero SSE events visible to the frontend that distinguish the two states. No `persona_state_change` event. No capturing pill. Input placeholder invariant.

## What this means for the user

- Onboarding feels like a friendly guided flow with clickable choices and open-text questions.
- Advice feels like a conversational assistant that knows their situation, answers with real data + engine-generated guidance, and navigates them to the right module page.
- When Advice Fyn needs more information to answer something, the request for that information arrives as a natural continuation of the conversation — no "switching to capture mode" preamble, no sudden bubbles.
- Resuming on a new device / session / after a disconnect picks up exactly where the user left off.

## What this means for evaluation

- `01-invariants.md` breaks this contract into ~35 falsifiable invariants. Each invariant has a specific test.
- `fyn-rubrics.md §B` contains 75 golden conversations that exercise the contract end-to-end.
- Scenario category `09-canonical-behaviour` (10 scenarios) is the core canonical-contract test set. Any regression in that category blocks merge.

---

*Source of truth. Do not paraphrase when copying into other docs — paste verbatim.*

---

# Sprint 1 — Eval Harness + Memory Model + `<known_facts>`

> **BRANCH: `feature/fyn-persona-split`.** Sprint 1 starts only when Sprint 0 is merged. All Sprint 1 commits go on `feature/fyn-persona-split` (or feature branches `feature/csj/sprint1-<subtask>`).
>
> **REQUIRED SUB-SKILL:** Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans`.

**Goal:** Ship the eval harness (Rubric B Mode 1 + first 30 scenarios) + memory model (3 DB stores + 1 conversation index + `<known_facts>` prompt injection + `search_conversation_index` tool) + advice-response SSE shape + `AdviceResponsePanel.vue`. End state: Rubric-A 17-18/40 🟠 Limited beta, Rubric-B Mode 1 passing 100% on seeded scenarios, dev-deploy gate unlocked for Sprint 3.

**Architecture:** Eval harness in `tests/Feature/Fyn/Eval/` with `EvalRunner`, `MockedProviderClient`, YAML scenario format, per-tool scorecard, hard-fail floors. Memory model: add `summary`, `topics`, `entities_mentioned`, `intents_stated` JSON columns to `ai_conversations` + `ConversationSummariserJob` + `MemoryRetrieverService` + `<known_facts>` block in every prompt builder + `search_conversation_index` tool exposed to Advice Fyn. Advice response: new `advice_response` SSE event emitted by `AdviceFyn` in recommendation-mode turns, rendered by new `AdviceResponsePanel.vue`.

**Tech Stack:** Laravel 10, Pest 3, Symfony YAML, Vue 3 + Vuex, Tailwind.

**Spec reference:** INV-2.2.3, INV-2.3.1, INV-2.3.2, INV-2.3.5, INV-2.3.6, INV-2.11.1, INV-2.11.2, INV-2.11.3, INV-2.13.1, INV-2.13.2, INV-2.13.3, INV-2.13.4 in [`01-invariants.md`](01-invariants.md).

---

## Pre-flight

- Sprint 0 merged to `feature/fyn-persona-split`.
- `./vendor/bin/pest` green.
- Rubric-A re-scored ≥13/40.

---

## Task 1.1 — Eval harness scaffold

**Invariants:** INV-2.13.1, INV-2.13.2, INV-2.13.3.

**Files:**
- Create: `tests/Feature/Fyn/Eval/EvalRunner.php`
- Create: `tests/Feature/Fyn/Eval/MockedProviderClient.php`
- Create: `tests/Feature/Fyn/Eval/AssertionHelpers.php`
- Create: `tests/Feature/Fyn/Eval/EvalReport.php`
- Create: `config/fyn_eval.php`
- Create: `tests/Feature/Fyn/Eval/scenarios/` (9 subdirectories)
- Create: `tests/Architecture/EvalScenarioCountTest.php`
- Create: `tests/Architecture/EvalFloorIntegrityTest.php`

- [ ] **Step 1 — `config/fyn_eval.php`**

```php
<?php

declare(strict_types=1);

return [
    'recall_floor' => [
        'default' => 95,
        'protection' => 95,
        'savings' => 95,
        'retirement' => 95,
        'investment' => 95,
        'mortgage' => 95,
    ],
    'precision_floor' => [
        'default' => 95,
    ],
    'hard_fail_floors' => [
        'entity_validity' => 100,
        'value_accuracy' => 100,
        'cross_entity_consistency' => 100,
        'fabrication_rate' => 0,
    ],
    'scenario_minima' => [
        '01-query-types' => 22,
        '02-preview-personas' => 6,
        '03-multi-entity' => 10,
        '04-handoffs' => 5,
        '05-cancel-timeout' => 3,
        '06-prompt-injection' => 10,
        '07-regulatory' => 5,
        '08-provider-parity' => 4,
        '09-canonical-behaviour' => 10,
    ],
];
```

- [ ] **Step 2 — `EvalRunner`** — Pest dataset provider + per-scenario runner. Signature:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Fyn\Eval;

use Symfony\Component\Yaml\Yaml;

final class EvalRunner
{
    /** @return iterable<string, array{0: string, 1: array}> */
    public static function scenarios(?string $category = null): iterable
    {
        $base = __DIR__.'/scenarios';
        $pattern = $category ? "{$base}/{$category}/*.yaml" : "{$base}/*/*.yaml";
        foreach (glob($pattern) as $path) {
            $id = basename($path, '.yaml');
            yield $id => [$id, Yaml::parseFile($path)];
        }
    }

    public static function run(string $id, array $scenario, string $mode = 'mocked'): EvalResult
    {
        // Load fixtures, mock the provider client (mocked mode) or hit real
        // providers (real mode), execute the scenario's turns, collect SSE
        // events + DB diffs + tool calls, run AssertionHelpers::* checks,
        // return EvalResult with per-metric pass/fail.
    }
}
```

- [ ] **Step 3 — `MockedProviderClient`** — replays recorded JSONL fixtures under `tests/Feature/Fyn/Eval/fixtures/anthropic/` and `fixtures/xai/`.

- [ ] **Step 4 — `AssertionHelpers`** methods:
  - `assertToolCallsMatch($expected, $actual)` — name + arg-shape subset.
  - `assertSseEventSequence($expected, $actual)` — types + fields + order.
  - `assertDbWrites($expected, $user)` — before/after DB state.
  - `assertForbiddenOutputsAbsent($text, $patterns)`.
  - `assertInterpretiveTextMapsToEngineSource($text, $engineOutput)` (INV-2.3.2).

- [ ] **Step 5 — `EvalReport`** — writes `storage/eval-scorecards/YYYY-MM-DD-{mode}.md` per-tool scorecard.

- [ ] **Step 6 — Scenario scaffolding** — create 9 directories with a `README.md` each explaining the category scope (copy from `fyn-rubrics.md §B`).

- [ ] **Step 7 — `EvalScenarioCountTest`**

```php
<?php

declare(strict_types=1);

it('scenario directory count meets category minima', function (): void {
    $minima = config('fyn_eval.scenario_minima');
    foreach ($minima as $category => $minCount) {
        $pattern = base_path("tests/Feature/Fyn/Eval/scenarios/{$category}/*.yaml");
        $files = glob($pattern);
        expect(count($files))->toBeGreaterThanOrEqual($minCount,
            "Category {$category} has ".count($files)." scenarios, needs {$minCount}");
    }
});
```

- [ ] **Step 8 — Commit**
  ```
  git commit -am "feat(eval): harness scaffold + scenario category directories"
  ```

---

## Task 1.2 — First 10 scenarios (query types + multi-entity)

**Invariant:** INV-2.13.1.

**Files:**
- Create: `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_protection_cover.yaml` + 5 more
- Create: `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/protection_2x_known_providers.yaml` + 3 more
- Create: fixture files per scenario under `tests/Feature/Fyn/Eval/fixtures/{anthropic,xai}/`

- [ ] **Step 1 — Write scenario YAML** — example `advice_protection_cover.yaml`:

```yaml
id: advice_protection_cover
category: 01-query-types
description: User asks "am I covered enough for protection?" — expect recommendation-mode response citing engine output.
input:
  turns:
    - user: "Am I covered enough for protection?"
seed:
  user:
    first_name: Test
    surname: User
    marital_status: married
  protection_policies:
    - { provider: Aviva, type: life, sum_assured: 100000, monthly_premium: 25 }
expected_classifications:
  - advice_protection
expected_tool_calls:
  - tool: get_module_analysis
    args: { module: protection }
  - tool: get_recommendations
expected_sse_events:
  - type: content
  - type: advice_response
  - type: done
expected_advice_response:
  signposting_suffix_present: true
  has_recommendations: true
forbidden_outputs:
  - "I think you should"
  - "I'd recommend"
  - "In my opinion"
timing_budget_ms: 5000
tags:
  - regression-band-0
  - recommendation-mode
  - protection
```

- [ ] **Step 2 — Record fixtures** — run the scenario against real Anthropic + real xAI once; capture the SSE JSONL stream into `fixtures/{provider}/advice_protection_cover.jsonl`.

- [ ] **Step 3 — Author remaining 5 query-type scenarios**: `advice_savings_emergency`, `advice_investment_isa`, `advice_retirement_contribution`, `advice_estate_iht`, `advice_goals_affordability`.

- [ ] **Step 4 — Author 4 multi-entity scenarios**: `protection_2x_known_providers`, `protection_2x_unknown_providers`, `savings_3x_mixed`, `pensions_2x_schemes`.

- [ ] **Step 5 — Run `./vendor/bin/pest tests/Feature/Fyn/Eval/ --testsuite=Eval` with mocked provider**. All 10 PASS.

- [ ] **Step 6 — Commit**
  ```
  git commit -am "feat(eval): first 10 scenarios — 6 query types + 4 multi-entity"
  ```

---

## Task 1.3 — Conversation index schema

**Invariant:** INV-2.11.2.

**Files:**
- Create migration: `database/migrations/2026_05_02_000001_add_conversation_index_columns.php`
- Modify: `app/Models/AiConversation.php` (fillable + casts)
- Create job: `app/Jobs/ConversationSummariserJob.php`
- Create: `tests/Feature/AI/ConversationIndexPopulationTest.php`

- [ ] **Step 1 — Migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $t): void {
            $t->text('summary')->nullable()->after('onboarding_parked_facts');
            $t->json('topics')->nullable()->after('summary');
            $t->json('entities_mentioned')->nullable()->after('topics');
            $t->json('intents_stated')->nullable()->after('entities_mentioned');
            $t->timestamp('summarised_at')->nullable()->after('intents_stated');
            $t->index('summarised_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $t): void {
            $t->dropColumn(['summary', 'topics', 'entities_mentioned', 'intents_stated', 'summarised_at']);
        });
    }
};
```

- [ ] **Step 2 — Update `AiConversation` model**
  ```php
  protected $fillable = [..., 'summary', 'topics', 'entities_mentioned', 'intents_stated', 'summarised_at'];
  protected $casts = [
      // existing casts ...
      'topics' => 'array',
      'entities_mentioned' => 'array',
      'intents_stated' => 'array',
      'summarised_at' => 'datetime',
  ];
  ```

- [ ] **Step 3 — `ConversationSummariserJob`**

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class ConversationSummariserJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public readonly int $conversationId) {}

    public function handle(): void
    {
        $conversation = AiConversation::with('messages')->find($this->conversationId);
        if (! $conversation) return;

        // Call the cheapest provider with a structured-output prompt.
        // Expected JSON: {summary, topics, entities_mentioned, intents_stated}
        $result = app(\App\Services\AI\ConversationSummariser::class)->summarise($conversation);

        $conversation->update([
            'summary' => $result['summary'] ?? null,
            'topics' => $result['topics'] ?? [],
            'entities_mentioned' => $result['entities_mentioned'] ?? [],
            'intents_stated' => $result['intents_stated'] ?? [],
            'summarised_at' => now(),
        ]);
    }
}
```

- [ ] **Step 4 — `ConversationSummariser` service** — prompts the cheapest model for structured-output JSON.

- [ ] **Step 5 — Dispatch triggers:**
  - `OnboardingChatDirector` on `STATE_DONE` transition: `ConversationSummariserJob::dispatch($conversation->id)`.
  - Scheduled task: find `ai_conversations` with `last_message_at > 30 min ago` AND `summarised_at IS NULL OR summarised_at < last_message_at` and dispatch.

- [ ] **Step 6 — `ConversationIndexPopulationTest`** asserts a closed conversation has non-empty index fields post-job.

- [ ] **Step 7 — Commit**
  ```
  git commit -am "feat(memory): conversation index schema + summariser job"
  ```

---

## Task 1.4 — `MemoryRetrieverService` + `<known_facts>` block

**Invariants:** INV-2.2.3, INV-2.11.1.

**Files:**
- Create: `app/Services/AI/MemoryRetrieverService.php`
- Modify: `app/Services/AI/AdvicePromptBuilder.php` (inject known-facts block)
- Modify: `app/Services/Onboarding/OnboardingPromptBuilder.php` (inject known-facts block in grouped-extract + asset-capture prompts)
- Create: `tests/Unit/Services/AI/MemoryRetrieverServiceTest.php`
- Create: `tests/Unit/Services/Onboarding/KnownFactsBlockTest.php`
- Create scenario: `tests/Feature/Fyn/Eval/scenarios/09-canonical-behaviour/memory-no-repeat-ask.yaml`

- [ ] **Step 1 — `MemoryRetrieverService`** — retrieval order DB → parked → current → index:

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\User;

final class MemoryRetrieverService
{
    /**
     * @param  list<string>  $fieldsNeeded
     * @return array<string, mixed>
     */
    public function retrieve(User $user, ?AiConversation $conversation, array $fieldsNeeded): array
    {
        $facts = $this->fromAuthoritativeDb($user);

        if ($conversation) {
            $facts = array_merge($facts, $this->fromParkedFacts($conversation));
            $facts = array_merge($facts, $this->fromCurrentConversation($conversation));
        }

        // Index query ONLY for fields still not populated.
        $missing = array_diff($fieldsNeeded, array_keys($facts));
        if ($missing !== [] && $conversation) {
            $facts = array_merge($facts, $this->fromConversationIndex($user, $missing));
        }

        return $facts;
    }

    private function fromAuthoritativeDb(User $user): array { /* reads users.*, family_members, linked module tables */ }
    private function fromParkedFacts(AiConversation $c): array { /* reads onboarding_parked_facts JSON */ }
    private function fromCurrentConversation(AiConversation $c): array { /* derives from ai_messages */ }
    private function fromConversationIndex(User $user, array $missing): array { /* queries ai_conversations index columns */ }
}
```

- [ ] **Step 2 — Inject `<known_facts>` in `OnboardingPromptBuilder`**

```php
// In every grouped-extract + asset-capture prompt build:
$facts = app(\App\Services\AI\MemoryRetrieverService::class)
    ->retrieve($user, $conversation, $this->fieldsNeededForState($state));

$block = "<known_facts>\n";
foreach ($facts as $key => $value) {
    $block .= "- {$key}: ".json_encode($value)."\n";
}
$block .= "</known_facts>\n\nDo not ask the user for any field above.\n";

$prompt = $block.$existingPrompt;
```

- [ ] **Step 3 — Same injection in `AdvicePromptBuilder`** for recommendation-mode + factual-mode prompts.

- [ ] **Step 4 — `MemoryRetrieverServiceTest`** — parameterised over each layer; assert fall-through.

- [ ] **Step 5 — `KnownFactsBlockTest`** — seed user with every onboarding field; build the grouped-extract prompt for `base_spouse`; assert every relevant field appears in the block.

- [ ] **Step 6 — Eval scenario `09-03 memory-no-repeat-ask`** — seed `marital_status = 'married'`; start onboarding at `base_spouse`; assert Fyn never emits a prompt asking own marital status.

- [ ] **Step 7 — Commit**
  ```
  git commit -am "feat(memory): MemoryRetrieverService + <known_facts> prompt injection"
  ```

---

## Task 1.5 — `search_conversation_index` tool

**Invariant:** INV-2.11.3.

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php` (+1 tool)
- Modify: `app/Services/AI/XaiToolDefinitions.php` (+1 tool)
- Modify: `app/Agents/CoordinatingAgent.php` (+handler)
- Modify: `app/Services/AI/AdviceFyn.php` (add `search_conversation_index` to allowed tools — it's not in the write list, so it's permitted by default; just ensure it's registered)
- Create: `tests/Feature/AI/SearchConversationIndexTest.php`
- Create scenario: `09-canonical-behaviour/cross-conversation-surface.yaml`

- [ ] **Step 1 — Register tool on both providers**
  ```php
  // AiToolDefinitions addition:
  [
      'name' => 'search_conversation_index',
      'description' => "Search prior conversations for relevant topics or entity references. Use only when the current conversation does not contain the needed fact. Returns matching conversation summaries.",
      'parameters' => [
          'type' => 'object',
          'properties' => [
              'topic_keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
              'entity_types' => ['type' => 'array', 'items' => ['type' => 'string']],
          ],
          'required' => [],
          'additionalProperties' => false,
      ],
  ],
  ```

- [ ] **Step 2 — Handler**

```php
private function handleSearchConversationIndex(array $input, User $user): array
{
    $topics = $input['topic_keywords'] ?? [];
    $entityTypes = $input['entity_types'] ?? [];

    $query = \App\Models\AiConversation::forUser($user->id)
        ->whereNotNull('summary');

    if ($topics !== []) {
        $query->where(function ($q) use ($topics) {
            foreach ($topics as $topic) {
                $q->orWhereJsonContains('topics', $topic);
            }
        });
    }

    if ($entityTypes !== []) {
        $query->where(function ($q) use ($entityTypes) {
            foreach ($entityTypes as $type) {
                $q->orWhereJsonContains('entities_mentioned', ['type' => $type]);
            }
        });
    }

    return $query->orderByDesc('last_message_at')
        ->limit(10)
        ->get(['id', 'summary', 'topics', 'entities_mentioned', 'intents_stated', 'last_message_at'])
        ->toArray();
}
```

- [ ] **Step 3 — `SearchConversationIndexTest`** — seed 3 conversations with different topics; search by keyword; assert correct subset returned.

- [ ] **Step 4 — Eval scenario `09-10 cross-conversation-surface`**.

- [ ] **Step 5 — Commit**
  ```
  git commit -am "feat(memory): search_conversation_index tool"
  ```

---

## Task 1.6 — `advice_response` SSE event + `AdviceResponsePanel.vue`

**Invariant:** INV-2.3.5.

**Files:**
- Modify: `app/Services/AI/AdviceFyn.php` (emit `advice_response` at recommendation-mode turn end)
- Create: `resources/js/components/Shared/AdviceResponsePanel.vue`
- Modify: `resources/js/store/modules/aiChat.js` (handle `advice_response`)
- Modify: `resources/js/components/Shared/AiChatPanel.vue` (render `advice_response` messages via new component)
- Create: `tests/Feature/Fyn/AdviceResponseSseShapeTest.php`

- [ ] **Step 1 — JSON schema** — document the payload shape (see INV-2.3.5 in `01-invariants.md`).

- [ ] **Step 2 — Emit the event in `AdviceFyn::handle` for recommendation-mode turns**:

```php
// After the tool loop completes for recommendation-mode turns:
$payload = [
    'type' => 'advice_response',
    'headline' => $this->composeHeadline($engineOutput),
    'key_figures' => $this->extractKeyFigures($engineOutput),
    'breakdowns' => $this->extractBreakdowns($engineOutput),
    'recommendations' => $this->mapRecommendations($engineOutput['ranked_recommendations']),
    'next_steps' => $this->extractNextSteps($engineOutput),
    'signposting' => 'For regulated advice personal to your circumstances, speak to a qualified financial adviser.',
];
yield $payload;
```

Helper methods map `orchestrateAnalysis` output → the payload shape. Pure functions, unit-testable.

- [ ] **Step 3 — `AdviceResponsePanel.vue`** — single-file component rendering the payload:

```vue
<template>
  <div class="advice-response-panel">
    <h2 class="text-lg font-bold text-horizon-500">{{ response.headline }}</h2>
    <div v-if="response.key_figures.length" class="grid grid-cols-2 gap-3 mt-3">
      <div v-for="fig in response.key_figures" :key="fig.label"
           class="p-3 border border-light-gray rounded-lg bg-savannah-100">
        <p class="text-xs text-neutral-500">{{ fig.label }}</p>
        <p class="text-xl font-bold text-horizon-500">{{ formatValue(fig) }}</p>
      </div>
    </div>
    <div v-for="breakdown in response.breakdowns" :key="breakdown.title" class="mt-4">
      <h3 class="text-sm font-bold text-horizon-500">{{ breakdown.title }}</h3>
      <div class="mt-2 space-y-1">
        <div v-for="row in breakdown.rows" :key="row.label" class="flex justify-between text-sm">
          <span class="text-neutral-500">{{ row.label }}</span>
          <span class="text-horizon-500 font-semibold">{{ row.value }}</span>
        </div>
      </div>
    </div>
    <div v-if="response.recommendations.length" class="mt-5">
      <h3 class="text-sm font-bold text-horizon-500 mb-2">Recommendations</h3>
      <ul class="space-y-2">
        <li v-for="rec in response.recommendations" :key="rec.id"
            class="p-3 border border-light-gray rounded-lg">
          <div class="flex items-start justify-between gap-3">
            <p class="text-sm text-horizon-500">{{ rec.text }}</p>
            <span :class="priorityBadgeClass(rec.priority)">{{ rec.priority }}</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1">Timeline: {{ rec.timeline }}</p>
        </li>
      </ul>
    </div>
    <div v-if="response.next_steps.length" class="mt-4 flex flex-wrap gap-2">
      <button v-for="step in response.next_steps" :key="step.label"
              class="px-3 py-1.5 text-xs border border-raspberry-500 text-raspberry-500 rounded-lg"
              @click="$emit('navigate', step.route)">
        {{ step.label }}
      </button>
    </div>
    <p class="mt-5 text-xs text-neutral-500 italic">{{ response.signposting }}</p>
  </div>
</template>

<script>
export default {
  name: 'AdviceResponsePanel',
  props: {
    response: { type: Object, required: true },
  },
  emits: ['navigate'],
  methods: {
    formatValue(fig) {
      if (fig.unit === 'gbp') return '£' + Number(fig.value).toLocaleString('en-GB');
      if (fig.unit === 'percent') return fig.value + '%';
      if (fig.unit === 'years') return fig.value + ' years';
      return fig.value;
    },
    priorityBadgeClass(priority) {
      const classes = {
        critical: 'px-2 py-0.5 text-xs rounded bg-raspberry-100 text-raspberry-700',
        high: 'px-2 py-0.5 text-xs rounded bg-violet-100 text-violet-700',
        medium: 'px-2 py-0.5 text-xs rounded bg-savannah-200 text-horizon-500',
        low: 'px-2 py-0.5 text-xs rounded bg-neutral-100 text-neutral-500',
      };
      return classes[priority] || classes.medium;
    },
  },
};
</script>
```

- [ ] **Step 4 — Vuex handler** in `resources/js/store/modules/aiChat.js`:
  ```js
  case 'advice_response':
      commit('ADD_MESSAGE', {
          id: 'advice_' + Date.now(),
          role: 'advice_response',
          content: event.headline,
          metadata: event,
          created_at: new Date().toISOString(),
      });
      break;
  ```

- [ ] **Step 5 — `AiChatPanel.vue` render** — add a branch for `msg.role === 'advice_response'` that renders `<AdviceResponsePanel :response="msg.metadata" @navigate="handleNavigate" />`.

- [ ] **Step 6 — `AdviceResponseSseShapeTest`** — run a recommendation-mode scenario; extract the `advice_response` event; validate against the JSON schema.

- [ ] **Step 7 — Commit**
  ```
  git commit -am "feat(fyn): advice_response SSE event + AdviceResponsePanel.vue"
  ```

---

## Task 1.7 — Expand eval to 30 scenarios

**Invariant:** INV-2.13.1.

- [ ] **Step 1 — Author remaining 16 query-type scenarios** (total 22 for category 01).
- [ ] **Step 2 — Author remaining 6 multi-entity scenarios** (total 10 for category 03).
- [ ] **Step 3 — Author 5 handoff round-trip scenarios** (category 04).
- [ ] **Step 4 — Author 3 cancel/timeout scenarios** (category 05).
- [ ] **Step 5 — Author 2 prompt-injection scenarios** as starters (category 06 — full set in Sprint 2).
- [ ] **Step 6 — Record fixtures** for each against real Anthropic + xAI.
- [ ] **Step 7 — Mode 1 green at 30 scenarios**.
- [ ] **Step 8 — Commit per category**.

---

## Task 1.8 — Advice Fyn response-mode classifier

**Invariant:** INV-2.3.1, INV-2.3.6.

**Files:**
- Modify: `app/Services/AI/AdviceFyn.php` (add `classifyResponseMode` + `engineCallLevel`)
- Create: `tests/Unit/Services/AI/AdviceFynResponseModeTest.php`, `tests/Unit/Services/AI/AdviceFynEngineCallLevelTest.php`

- [ ] **Step 1 — Parameterised failing tests** over every `QuerySchemas` constant.

- [ ] **Step 2 — Implement `AdviceFyn::classifyResponseMode(string $queryType): 'factual'|'recommendation'|'out_of_remit'`** via a static map.

- [ ] **Step 3 — Implement `AdviceFyn::engineCallLevel(string $queryType): 'holistic'|'module'|'factual'`**.

- [ ] **Step 4 — Wire into `handle`** — before falling through to `chatWithPromptOverride`, determine mode + level; if factual, bypass `orchestrateAnalysis` and call the module service directly.

- [ ] **Step 5 — Tests + commit**.

---

## Task 1.9 — Sprint 1 Playwright matrix (incremental)

**Invariants:** INV-2.2.3, INV-2.3.1 (both modes), INV-2.3.5, INV-2.11.1, INV-2.11.3 — per [`03-test-strategy.md`](03-test-strategy.md) per-sprint index. Sprint 1 adds **4 new scenarios** on top of the 20 Sprint 0 scenarios (total matrix: 24).

**Files:**
- Create: `tests/Browser/scenarios/BS-03-known-facts-no-repeat-ask.php`
- Create: `tests/Browser/scenarios/BS-08-advice-factual-net-worth.php`
- Create: `tests/Browser/scenarios/BS-09-advice-recommendation-isa.php`
- Create: `tests/Browser/scenarios/BS-24-cross-conversation-surface.php`

- [ ] **Step 1 — Author the 4 new scenarios** per specs in [`03-test-strategy.md §BS-03/08/09/24`](03-test-strategy.md). Each starts from `http://localhost:8000`, logs in via `Login::as` or `Login::asPreviewPersona`, clicks through the UI, asserts SSE + DOM.

- [ ] **Step 2 — Regression-run Sprint 0 matrix** — ensure Sprint 1 changes didn't break BS-01 through BS-23.

- [ ] **Step 3 — Run full matrix**
  ```
  ./dev.sh &
  php artisan db:seed
  ./vendor/bin/pest --testsuite=Browser --filter=BS-
  ```
  Expected: 24/24 pass.

- [ ] **Step 4 — Screenshots** for BS-03, 08, 09, 24 → `docs/sprint-1-verification/BS-NN/`.

- [ ] **Step 5 — Commit**
  ```
  git commit -am "test(browser): Sprint 1 Playwright matrix (4 new, 24 total)"
  ```

---

## Sprint 1 verification

- [ ] **Full Pest:** `./vendor/bin/pest` — all pass.
- [ ] **Eval Mode 1:** `./vendor/bin/pest tests/Feature/Fyn/Eval/ --testsuite=Eval` — 30/30 PASS.
- [ ] **Browser matrix (24 scenarios):** `./vendor/bin/pest --testsuite=Browser --filter=BS-` — 24/24 pass.
- [ ] **Rubric-A re-score:** target 17-18/40 🟠 Limited beta — gate for Sprint 3 dev deploy.
- [ ] **Merge branch:** PR to `feature/fyn-persona-split`; merge on green.

**Report-finished gate:** Sprint 1 is NOT done until the 24-scenario Browser matrix (4 new + 20 regression) is green AND evidence committed. Per [`03-test-strategy.md §Non-negotiables`](03-test-strategy.md).

Sprint 1 complete. [`12-sprint-2-plan.md`](12-sprint-2-plan.md) next.
