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

# Sprint 0 — Two-Fyn Collapse + Reliability + Audit Chain

> **BRANCH: `feature/fyn-persona-split`.** All Sprint 0 commits go on this branch (or feature branches off it named `feature/csj/sprint0-<subtask>`). Never commit directly to `main` or `dev`.
>
> **REQUIRED SUB-SKILL:** Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the two-Fyn architectural collapse + reliability floor + hash-chain audit + compliance floor on `feature/fyn-persona-split`. End state: Rubric-A 13-15/40, Rubric-B Mode-1 green on Sprint 0 scenarios, ready for Sprint 1 eval-harness expansion and Sprint 3 dev deploy.

**Architecture:** Delete `FynPersonaOrchestrator` / `Invoker` / `Registry` / `DataCapturePromptBuilder`. Introduce `AdviceFyn` class (read-only tool list, engine-routed). Extend `OnboardingChatDirector` with `handleInlineCapture` (direct-write, conversational prompts, gap-fill rewired from the invoker). Remove visible-handoff UI. Convert 17 fill_form handlers to direct-write. Add billing/subscription tools (3). Tighten `update_record` with per-entity allowlist + strict schema. Two-phase `delete_record` confirmation. Runtime `ConsentService::hasConsent` check. Reliability invariants land in one bundled task: SSE abort detection, atomic token budget, idempotency middleware, provider-swap lock, gap-fill dedup, `generateTitle` sanitation. Hash-chain audit migration with HMAC signing. CoreIdentity rewrite to guidance-only framing. Out-of-remit canonical refusal.

**Tech Stack:** Laravel 10 + PHP 8.2 + Pest 3 + Vue 3 + Vuex + Tailwind. LLM providers: Anthropic + xAI. Stream transport: SSE. Tests: Pest `./vendor/bin/pest`.

**Spec reference:** Every task maps to one or more invariants in [`01-invariants.md`](01-invariants.md) (INV-2.X.Y). Every claim about current code is grounded in [`02-current-system.md`](02-current-system.md).

---

## Pre-flight

Confirm before starting:

- Branch: `git branch --show-current` → `feature/fyn-persona-split` (or a feature branch off it).
- Working tree clean.
- Pest baseline: `./vendor/bin/pest` → 2,448 passing + 1 known flake.

If any fails, stop and reconcile.

---

## Task 0.1 — Rebase onto `main`

**Invariant:** INV-2.1.1, INV-2.1.3, plus every reliability invariant that depends on `main` having latest middleware/guardrails.

**Files:**
- Entire branch. 179-commit drift against `origin/main`.

- [ ] **Step 1 — Snapshot**
  ```
  git fetch origin
  git rev-list --count origin/feature/fyn-persona-split..origin/main
  git status
  ```
  Expected: count = 179; clean status.

- [ ] **Step 2 — Create rebase branch**
  ```
  git checkout feature/fyn-persona-split
  git checkout -b feature/csj/sprint0-rebase
  ```

- [ ] **Step 3 — Rebase**
  ```
  git rebase origin/main
  ```
  Conflicts expected in `resources/js/layouts/AppLayout.vue`, `app/Agents/CoordinatingAgent.php`, `routes/api.php`, `routes/web.php`, `app/Traits/HasAiChat.php`, `app/Services/AI/AdvicePromptBuilder.php`, `app/Services/AI/AiToolDefinitions.php`, `app/Services/AI/XaiToolDefinitions.php`, `app/Services/AI/Prompts/ComplianceRules.php`, `app/Services/AI/Prompts/FcaProcessInstructions.php`, `app/Services/AI/StructuredResponseValidator.php`, `app/Http/Controllers/Api/AiChatController.php`, `app/Http/Controllers/Api/AdminController.php`, `resources/js/router/index.js`, `resources/js/store/modules/aiChat.js`, `resources/js/components/Shared/AiChatPanel.vue`.

- [ ] **Step 4 — Resolve per hotspot.** Prefer branch version for persona-split machinery (will be deleted in 0.3 anyway). Prefer main version for reliability/bugfix improvements.

- [ ] **Step 5 — Pest after rebase**
  ```
  ./vendor/bin/pest
  ```
  Expected: 2,448 passing + 1 flake.

- [ ] **Step 6 — Push rebase branch**
  ```
  git push origin feature/csj/sprint0-rebase --force-with-lease
  ```

- [ ] **Step 7 — Open PR `feature/csj/sprint0-rebase` → `feature/fyn-persona-split`; merge on green.**

---

## Task 0.2 — Delete stale OpenAI config + Python sidecar

**Invariant:** cleanup; flagged by audit (see `audit-evidence.md §16`).

**Files:**
- Modify: `config/services.php` (remove lines 34-38)
- Delete: `scripts/fynla_agent/`, `scripts/run_agent.py`, `scripts/requirements.txt`
- Delete: `app/Http/Controllers/Api/AgentInternalController.php`, `app/Http/Middleware/AgentTokenAuth.php`
- Modify: `routes/api.php` (remove `/api/internal/agent/*` block at lines 1193-1199)
- Modify: `app/Http/Kernel.php` (remove `agent.token` middleware at line 81)
- Modify: `.env.example` (remove `AGENT_INTERNAL_TOKEN`, `OPENAI_*`)

- [ ] **Step 1 — Confirm with CSJ no external caller** (already confirmed in `audit-synthesis.md §8` CSJ decision 4).

- [ ] **Step 2 — Remove OpenAI block** — delete the `'openai' => [ ... ]` entry from `config/services.php`.

- [ ] **Step 3 — Delete files**
  ```
  git rm -r scripts/fynla_agent scripts/run_agent.py scripts/requirements.txt
  git rm app/Http/Controllers/Api/AgentInternalController.php app/Http/Middleware/AgentTokenAuth.php
  ```

- [ ] **Step 4 — Remove route block** in `routes/api.php`.

- [ ] **Step 5 — Remove middleware registration** at `app/Http/Kernel.php:81`.

- [ ] **Step 6 — Remove env entries** from `.env.example`.

- [ ] **Step 7 — Verify cleanup** — architecture test at `tests/Architecture/NoStaleReferencesTest.php`:

```php
<?php

declare(strict_types=1);

it('no references remain for deleted Agent sidecar', function (): void {
    $patterns = ['AgentInternalController', 'AgentTokenAuth', 'AGENT_INTERNAL_TOKEN', 'OPENAI_CHAT_MODEL'];
    $roots = [app_path(), config_path(), base_path('routes')];
    $hits = [];
    foreach ($roots as $root) {
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iter as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            foreach ($patterns as $pat) {
                if (str_contains($content, $pat)) {
                    $hits[] = "{$file->getPathname()}: {$pat}";
                }
            }
        }
    }
    expect($hits)->toBeEmpty();
});
```

- [ ] **Step 8 — Pest**
  ```
  ./vendor/bin/pest
  ```

- [ ] **Step 9 — Commit**
  ```
  git commit -am "chore: remove stale OpenAI config + dead Python sidecar"
  ```

---

## Task 0.3 — Two-Fyn collapse (architecture)

**Invariants:** INV-2.1.1, INV-2.1.2, INV-2.4.1, INV-2.4.2, INV-2.4.5.

**Files:**
- **Create:** `app/Services/AI/AdviceFyn.php`
- **Create:** `app/Services/AI/HandoffPayloadValidator.php`
- **Modify:** `app/Services/Onboarding/OnboardingChatDirector.php` (add `handleInlineCapture` + ported `emitGapFillFromCaptureContext` / `runExtractorForFocus` / `inferFocusesFromEntityTypes` helpers)
- **Modify:** `app/Http/Controllers/Api/AiChatController.php` (two-way dispatch; remove `wrapWithMultiEntityGapFill` + `runControllerGapFill` + `focusFromEntityType`)
- **Delete:** `app/Services/AI/FynPersonaOrchestrator.php`, `app/Services/AI/FynPersonaInvoker.php`, `app/Services/AI/FynPersonaRegistry.php`, `app/Services/AI/Prompts/DataCapturePromptBuilder.php`, `config/fyn_personas.php`, `config/fyn.php` (orphan after orchestrator deletion)
- **Modify:** `app/Services/AI/AdvicePromptBuilder.php` — delete the `<persona_split_handoff>` prompt layer (AdviceFyn has no `delegate_to_capture` in its tool list, so the layer lies to the LLM)
- **Modify (comment scrub):** `app/Services/AI/HandoffContract.php`, `app/Services/AI/AiToolDefinitions.php`, `app/Services/AI/XaiToolDefinitions.php`, `app/Traits/HasAiChat.php`, `app/Agents/CoordinatingAgent.php`, `app/Constants/QuerySchemas.php` — docblock references to deleted class names removed
- **Modify:** `app/Providers/AppServiceProvider.php` (remove orchestrator bindings; bind `AdviceFyn`)
- **Create migration:** `database/migrations/2026_04_25_000001_clear_stale_persona_state.php`
- **Delete tests:** `tests/Feature/AI/PersonaSplit/{CancelMidCapture,CaptureTimeout,ClassifierFastPath,PreviewMode,KycGateFlow}Test.php`, `tests/Unit/Services/AI/{FynPersonaInvoker,FynPersonaOrchestrator,FynPersonaRegistry}Test.php`, `tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php`, `tests/Unit/Services/AI/AdvicePromptBuilderPersonaSplitTest.php`
- **Port tests (rename):** `tests/Feature/AI/PersonaSplit/{CreateWillTool,CreatePowerOfAttorneyTool}Test.php` → `tests/Feature/Fyn/` (plain `git mv`)
- **Port tests (rewrite):** `tests/Feature/AI/PersonaSplit/InlineCaptureFlowTest.php` → `tests/Feature/Fyn/InlineCaptureFlowTest.php` — original mocked deleted classes; new body pins `handleInlineCapture` behaviour (event stripping + handoff invisibility) via a `CoordinatingAgent::chatWithPromptOverride` mock
- **Create tests:** `tests/Feature/Fyn/DispatchRoutingTest.php`, `tests/Feature/Fyn/AdviceFynToolListTest.php`, `tests/Feature/Fyn/HandoffInvisibilityTest.php`, `tests/Feature/Fyn/HandoffPayloadValidationTest.php`, `tests/Architecture/PersonaMachineryAbsentTest.php` (the architecture test's skip list excludes itself and `DispatchRoutingTest.php`, whose negative assertions legitimately contain the deleted class names as string literals)

- [ ] **Step 1 — Write failing architecture test: persona machinery absent**

```php
<?php

declare(strict_types=1);

it('no class references remain for deleted persona machinery', function (): void {
    $patterns = [
        'FynPersonaOrchestrator',
        'FynPersonaInvoker',
        'FynPersonaRegistry',
        'DataCapturePromptBuilder',
    ];
    $roots = [app_path(), config_path(), base_path('tests')];
    $hits = [];
    foreach ($roots as $root) {
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iter as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            if (str_ends_with($file->getPathname(), 'PersonaMachineryAbsentTest.php')) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            foreach ($patterns as $pat) {
                if (str_contains($content, $pat)) {
                    $hits[] = "{$file->getPathname()}: {$pat}";
                }
            }
        }
    }
    expect($hits)->toBeEmpty();
});
```

- [ ] **Step 2 — Write failing test: dispatch routing**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('AiChatController::sendMessage dispatches only to OnboardingChatDirector or AdviceFyn', function (): void {
    $source = File::get(base_path('app/Http/Controllers/Api/AiChatController.php'));
    preg_match('/public function sendMessage.*?\n    \}/s', $source, $matches);
    $methodBody = $matches[0];

    expect($methodBody)->toContain('OnboardingChatDirector');
    expect($methodBody)->toContain('AdviceFyn');
    expect($methodBody)->not->toContain('FynPersonaOrchestrator');
    expect($methodBody)->not->toContain('FynPersonaInvoker');
});
```

- [ ] **Step 3 — Write failing test: AdviceFyn read-only tool list**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\AdviceFyn;

$writeTools = [
    'create_savings_account', 'create_investment_account', 'create_holding',
    'create_pension', 'create_property', 'create_mortgage',
    'create_protection_policy', 'create_asset', 'create_liability',
    'create_estate_gift', 'create_chattel', 'create_business_interest',
    'create_trust', 'create_family_member', 'create_will', 'update_will',
    'create_power_of_attorney', 'update_power_of_attorney',
    'update_record', 'delete_record', 'update_profile', 'set_expenditure',
    'capture_personal_details', 'capture_spouse_details',
    'capture_dependants', 'capture_work_details',
];

it('AdviceFyn tool list excludes every DB-mutating tool on Anthropic', function () use ($writeTools): void {
    cache()->forever('ai_provider', 'anthropic');
    $user = User::factory()->create();
    $tools = app(AdviceFyn::class)->buildToolList($user);
    expect(array_intersect($tools, $writeTools))->toBeEmpty();
});

it('AdviceFyn tool list excludes every DB-mutating tool on xAI', function () use ($writeTools): void {
    cache()->forever('ai_provider', 'xai');
    $user = User::factory()->create();
    $tools = app(AdviceFyn::class)->buildToolList($user);
    expect(array_intersect($tools, $writeTools))->toBeEmpty();
});

it('AdviceFyn tool list includes create_what_if_scenario (analytics exception)', function (): void {
    cache()->forever('ai_provider', 'anthropic');
    $user = User::factory()->create();
    $tools = app(AdviceFyn::class)->buildToolList($user);
    expect($tools)->toContain('create_what_if_scenario');
});
```

- [ ] **Step 4 — Write failing test: handoff invisibility**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;

it('zero persona_state_change SSE events emitted during handoff', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true]);
    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    $response = $this->actingAs($user)
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", [
            'message' => 'I want advice — oh actually add Aviva life £300k',
        ]);

    $raw = $response->getContent();
    $events = collect(explode("\n\n", $raw))
        ->filter(fn ($c) => str_starts_with(trim($c), 'data:'))
        ->map(fn ($c) => json_decode(preg_replace('/^data:\s*/', '', trim($c)), true))
        ->filter();

    expect($events->pluck('type')->filter(fn ($t) => $t === 'persona_state_change'))->toBeEmpty();
});
```

- [ ] **Step 5 — Verify tests fail**
  ```
  ./vendor/bin/pest tests/Feature/Fyn/ tests/Architecture/PersonaMachineryAbsentTest.php -v
  ```
  Expected: FAIL — `AdviceFyn` class does not exist.

- [ ] **Step 6 — Implement `AdviceFyn`**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class AdviceFyn
{
    private const WRITE_TOOLS = [
        'create_savings_account', 'create_investment_account', 'create_holding',
        'create_pension', 'create_property', 'create_mortgage',
        'create_protection_policy', 'create_asset', 'create_liability',
        'create_estate_gift', 'create_chattel', 'create_business_interest',
        'create_trust', 'create_family_member', 'create_will', 'update_will',
        'create_power_of_attorney', 'update_power_of_attorney',
        'update_record', 'delete_record', 'update_profile', 'set_expenditure',
        'capture_personal_details', 'capture_spouse_details',
        'capture_dependants', 'capture_work_details',
    ];

    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
        private readonly AiToolDefinitions $toolDefinitions,
        private readonly XaiToolDefinitions $xaiToolDefinitions,
    ) {}

    public function handle(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute = null,
    ): \Generator {
        $allowedTools = $this->buildToolList($user);

        return $this->coordinatingAgent->chatWithPromptOverride(
            user: $user,
            conversation: $conversation,
            message: $message,
            currentRoute: $currentRoute,
            systemPromptOverride: null,
            allowedTools: $allowedTools,
            persistUserMessage: true,
            toolsListOverride: null,
            personaOverride: 'advice',
        );
    }

    /** @return list<string> */
    public function buildToolList(User $user): array
    {
        $provider = Cache::get('ai_provider', config('services.ai_provider', 'anthropic'));
        $definitions = $provider === 'xai' ? $this->xaiToolDefinitions : $this->toolDefinitions;
        $allTools = $definitions->getTools($user->is_preview_user);

        $names = array_filter(array_map(
            fn (array $t) => $t['name'] ?? ($t['function']['name'] ?? null),
            $allTools,
        ));

        return array_values(array_diff($names, self::WRITE_TOOLS));
    }
}
```

- [ ] **Step 7 — Implement `HandoffPayloadValidator`**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

final class HandoffPayloadValidator
{
    /** @param  array<string, mixed>  $payload */
    public static function validateDelegateToCapture(array $payload): ?string
    {
        if (! isset($payload['reason']) || ! is_string($payload['reason'])) {
            return 'missing_or_invalid_reason';
        }
        if (! isset($payload['entity_types']) || ! is_array($payload['entity_types'])) {
            return 'missing_or_invalid_entity_types';
        }
        foreach ($payload['entity_types'] as $type) {
            if (! is_string($type)) {
                return 'entity_types_must_be_strings';
            }
        }
        return null;
    }

    /** @param  array<string, mixed>  $payload */
    public static function validateCaptureComplete(array $payload): ?string
    {
        if (! isset($payload['summary']) || ! is_string($payload['summary'])) {
            return 'missing_or_invalid_summary';
        }
        if (! isset($payload['records_created']) || ! is_array($payload['records_created'])) {
            return 'missing_or_invalid_records_created';
        }
        return null;
    }
}
```

- [ ] **Step 8 — Implement `OnboardingChatDirector::handleInlineCapture`**

Append to `app/Services/Onboarding/OnboardingChatDirector.php`:

```php
public function handleInlineCapture(
    User $user,
    AiConversation $conversation,
    string $message,
    \App\ValueObjects\CaptureContext $context,
    ?string $currentRoute = null,
): \Generator {
    $allowedTools = $this->captureToolSet($context);

    $generator = $this->coordinatingAgent->chatWithPromptOverride(
        user: $user,
        conversation: $conversation,
        message: $message,
        currentRoute: $currentRoute,
        systemPromptOverride: null,
        allowedTools: $allowedTools,
        persistUserMessage: true,
        toolsListOverride: null,
        personaOverride: 'onboarding_inline',
    );

    foreach ($generator as $event) {
        // Strip any layout / bubble events to preserve handoff invisibility.
        if (in_array($event['type'] ?? '', ['onboarding_layout_change', 'quick_replies'], true)) {
            continue;
        }
        yield $event;
    }

    // Gap-fill from CaptureContext (extractor covers 4 focuses).
    yield from $this->emitGapFillFromCaptureContext($user, $conversation, $context, $message);
}

/** @return list<string> */
private function captureToolSet(\App\ValueObjects\CaptureContext $context): array
{
    return [
        'create_savings_account', 'create_investment_account', 'create_holding',
        'create_pension', 'create_property', 'create_mortgage',
        'create_protection_policy', 'create_family_member',
        'create_goal', 'create_life_event', 'create_trust',
        'create_will', 'update_will',
        'create_power_of_attorney', 'update_power_of_attorney',
        'create_asset', 'create_liability', 'create_estate_gift',
        'create_chattel', 'create_business_interest',
        'update_record', 'update_profile', 'set_expenditure',
    ];
}

private function emitGapFillFromCaptureContext(
    User $user,
    AiConversation $conversation,
    \App\ValueObjects\CaptureContext $context,
    string $message,
): \Generator {
    // Port of FynPersonaInvoker::emitGapFillFromCaptureContext (lines 251-300
    // on the to-be-deleted invoker). Copy the body verbatim before deletion.
    // Uses AssetCaptureEntityExtractor for protection/savings/retirement/investment.
    $focuses = $this->inferFocusesFromEntityTypes($context->entityTypes);
    foreach ($focuses as $focus) {
        yield from $this->runExtractorForFocus($user, $focus, $message);
    }
}
```

Copy `inferFocusesFromEntityTypes` and `runExtractorForFocus` method bodies verbatim from the current `FynPersonaInvoker` before deleting it in Step 10.

- [ ] **Step 9 — Rewrite `AiChatController::sendMessage` dispatch**

Replace the 3-way dispatch block with:

```php
// Early returns for system-level messages (token limit, consent, preview).
// (existing early-return code retained)

$inOnboarding = $user->onboarding_completed === false
    && (bool) config('onboarding.fyn_flow_enabled', true);

return new StreamedResponse(function () use ($user, $conversation, $message, $currentRoute, $inOnboarding) {
    try {
        $generator = $inOnboarding
            ? $this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute)
            : $this->adviceFyn->handle($user, $conversation, $message, $currentRoute);

        foreach ($generator as $event) {
            echo 'data: '.json_encode($event)."\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }
    } catch (\Exception $e) {
        \Log::error('[AiChatController] Streaming error', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'in_onboarding' => $inOnboarding,
            'error' => $e->getMessage(),
        ]);
        echo 'data: '.json_encode(['type' => 'error', 'message' => 'An unexpected error occurred.'])."\n\n";
    }
}, 200, [/* SSE headers */]);
```

Inject `AdviceFyn` into the constructor:

```php
public function __construct(
    private readonly CoordinatingAgent $coordinatingAgent,
    private readonly OnboardingChatDirector $onboardingDirector,
    private readonly AdviceFyn $adviceFyn,
    private readonly AssetCaptureEntityExtractor $entityExtractor,
) {}
```

Remove the `FynPersonaOrchestrator` dependency and the `wrapWithMultiEntityGapFill` wrapper.

- [ ] **Step 10 — Delete persona-split files**
  ```
  git rm app/Services/AI/FynPersonaOrchestrator.php
  git rm app/Services/AI/FynPersonaInvoker.php
  git rm app/Services/AI/FynPersonaRegistry.php
  git rm app/Services/AI/Prompts/DataCapturePromptBuilder.php
  git rm config/fyn_personas.php
  ```

- [ ] **Step 11 — Update service bindings** in `app/Providers/AppServiceProvider.php` — remove orchestrator references; add `$this->app->singleton(\App\Services\AI\AdviceFyn::class);`.

- [ ] **Step 12 — Migration: clear stale `persona_state`**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_conversations')->update(['persona_state' => null]);
    }

    public function down(): void {}
};
```

- [ ] **Step 13 — Delete stale tests**
  ```
  git rm tests/Feature/AI/PersonaSplit/CancelMidCaptureTest.php
  git rm tests/Feature/AI/PersonaSplit/CaptureTimeoutTest.php
  git rm tests/Feature/AI/PersonaSplit/ClassifierFastPathTest.php
  git rm tests/Feature/AI/PersonaSplit/PreviewModeTest.php
  git rm tests/Feature/AI/PersonaSplit/KycGateFlowTest.php
  git rm tests/Unit/Services/AI/FynPersonaInvokerTest.php
  git rm tests/Unit/Services/AI/FynPersonaOrchestratorTest.php
  git rm tests/Unit/Services/AI/FynPersonaRegistryTest.php
  git rm tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php
  git rm tests/Unit/Services/AI/AdvicePromptBuilderPersonaSplitTest.php
  ```

- [ ] **Step 14 — Port kept tests**
  ```
  git mv tests/Feature/AI/PersonaSplit/CreateWillToolTest.php tests/Feature/Fyn/CreateWillToolTest.php
  git mv tests/Feature/AI/PersonaSplit/CreatePowerOfAttorneyToolTest.php tests/Feature/Fyn/CreatePowerOfAttorneyToolTest.php
  git mv tests/Feature/AI/PersonaSplit/InlineCaptureFlowTest.php tests/Feature/Fyn/InlineCaptureFlowTest.php
  ```
  Fix namespaces and class imports in each ported file.

- [ ] **Step 15 — Run migration + Pest**
  ```
  php artisan migrate
  ./vendor/bin/pest
  ```
  Expected: new Fyn tests PASS; no regressions.

- [ ] **Step 16 — Commit**
  ```
  git commit -am "feat(fyn): two-Fyn collapse — AdviceFyn + handleInlineCapture + delete orchestrator stack"
  ```

---

## Task 0.4 — Remove visible-handoff UI

**Invariant:** INV-2.4.1.

**Files:**
- Modify: `resources/js/store/modules/aiChat.js` (remove `persona_state_change` case + `personaMode` state/getter/mutation)
- Modify: `resources/js/components/Shared/AiChatPanel.vue` (remove capturing-pill render + conditional placeholder)

- [ ] **Step 1 — Remove handler** at `resources/js/store/modules/aiChat.js` — delete the `case 'persona_state_change':` block (currently lines 511-516).

- [ ] **Step 2 — Remove `personaMode`** from `state`, `getters`, `mutations`.

- [ ] **Step 3 — Remove capturing pill + placeholder swap** in `resources/js/components/Shared/AiChatPanel.vue`:
  - Delete the `<div v-if="personaMode === 'capturing'">` pill block.
  - Replace `:placeholder="personaMode === 'capturing' ? 'Capturing...' : 'How can I help?'"` with `placeholder="How can I help?"`.

- [ ] **Step 4 — Dev-server smoke test**
  ```
  ./dev.sh
  ```
  Log in (incognito), drive an advice → capture → advice turn. Verify: no pill, no placeholder change.

- [ ] **Step 5 — Re-run invisibility test**
  ```
  ./vendor/bin/pest tests/Feature/Fyn/HandoffInvisibilityTest.php -v
  ```

- [ ] **Step 6 — Commit**
  ```
  git commit -am "feat(fyn): remove visible-handoff UI — persona_state_change + capturing pill"
  ```

---

## Task 0.5 — Convert 17 fill_form handlers to direct-write

**Invariants:** INV-2.5.1, INV-2.5.2, INV-2.5.5.

One sub-task per handler. Pattern repeats — full detail for `handleCreateSavingsAccount` (Task 0.5.a); remaining sub-tasks follow the same pattern substituting the module's service + FormRequest.

### Task 0.5.a — `create_savings_account`

**Files:**
- Modify: `app/Agents/CoordinatingAgent.php::handleCreateSavingsAccount` (current line ~1557)
- Test: `tests/Feature/AI/DirectWrite/CreateSavingsAccountTest.php`

- [ ] **Step 1 — Failing tests**

```php
<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\SavingsAccount;
use App\Models\User;

it('create_savings_account persists a SavingsAccount row directly', function (): void {
    $user = User::factory()->create();

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Test Cash ISA',
        'provider' => 'Aviva',
        'account_type' => 'cash_isa',
        'balance' => 5000,
        'interest_rate' => 4.5,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('savings_account');
    expect($result['entity_id'])->toBeInt();
    expect(SavingsAccount::find($result['entity_id']))
        ->not->toBeNull()
        ->balance->toEqual('5000.00')
        ->provider->toBe('Aviva');
});

it('create_savings_account returns validation_failed on invalid input', function (): void {
    $user = User::factory()->create();
    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'provider' => 'Aviva',
    ], $user);

    expect($result)->toHaveKey('error');
    expect($result['error'])->toBe('validation_failed');
});
```

- [ ] **Step 2 — Verify fail**
  ```
  ./vendor/bin/pest tests/Feature/AI/DirectWrite/CreateSavingsAccountTest.php
  ```

- [ ] **Step 3 — Rewrite handler** at `app/Agents/CoordinatingAgent.php::handleCreateSavingsAccount`:

```php
private function handleCreateSavingsAccount(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return ['blocked' => true, 'reason' => 'preview_mode'];
    }

    $rules = (new \App\Http\Requests\StoreSavingsAccountRequest)->rules();
    $validator = validator($input, $rules);
    if ($validator->fails()) {
        return [
            'error' => 'validation_failed',
            'errors' => $validator->errors()->toArray(),
        ];
    }

    return \DB::transaction(function () use ($user, $validator) {
        $account = \App\Models\SavingsAccount::create([
            'user_id' => $user->id,
            ...$validator->validated(),
        ]);

        return [
            'success' => true,
            'entity_type' => 'savings_account',
            'entity_id' => $account->id,
            'persisted_fields' => $account->only(array_keys($validator->validated())),
        ];
    });
}
```

- [ ] **Step 4 — Run tests**. Expected: PASS.

- [ ] **Step 5 — Commit**
  ```
  git commit -am "feat(fyn): direct-write handleCreateSavingsAccount"
  ```

### Task 0.5.b–0.5.p — remaining handlers

Same pattern. Each sub-task: failing test → direct-write handler using the target service/model/FormRequest → Pest PASS → commit.

| Sub-task | Handler | Line | Target model | FormRequest |
|---|---|---|---|---|
| 0.5.b | `handleCreateInvestmentAccount` | 1614 | `InvestmentAccount` | `StoreInvestmentAccountRequest` |
| 0.5.c | `handleCreateHolding` | 1750 | `Holding` | `StoreHoldingRequest` |
| 0.5.d | `handleCreatePension` | 1817 | `DCPension` / `DBPension` | `StoreDCPensionRequest` / `StoreDBPensionRequest` |
| 0.5.e | `handleCreateProperty` | 1895 | `Property` | `StorePropertyRequest` |
| 0.5.f | `handleCreateMortgage` | 2026 | `Mortgage` | `StoreMortgageRequest` |
| 0.5.g | `handleCreateProtectionPolicy` | 2073 | via `PolicyCRUDTrait` → life / CI / IP models | per-type requests |
| 0.5.h | `handleCreateEstateAsset` | 2140 | `App\Models\Estate\Asset` | `StoreEstateAssetRequest` |
| 0.5.i | `handleCreateEstateLiability` | 2173 | `App\Models\Estate\Liability` | `StoreEstateLiabilityRequest` |
| 0.5.j | `handleCreateEstateGift` | 2213 | `App\Models\Estate\Gift` | `StoreEstateGiftRequest` |
| 0.5.k | `handleCreateFamilyMember` | 2770 | `SpouseLinkingService::linkOrCreateSpouse` (spouse branch) or `FamilyMember::create` | `StoreFamilyMemberRequest` |
| 0.5.l | `handleCreateTrust` | 2869 | `App\Models\Estate\Trust` | `StoreTrustRequest` |
| 0.5.m | `handleCreateBusinessInterest` | 2931 | `BusinessInterest` | `StoreBusinessInterestRequest` |
| 0.5.n | `handleCreateChattel` | 2986 | `Chattel` | `StoreChattelRequest` |
| 0.5.o | `handleCreateGoal` | 1474 | `Goal` | `StoreGoalRequest` |
| 0.5.p | `handleCreateLifeEvent` | 1518 | `LifeEvent` | `StoreLifeEventRequest` |

- [ ] **For each 0.5.b–0.5.p:** five-step cycle + commit.

### Task 0.5.q — coverage + observer firing

- [ ] **Step 1 — Write coverage test** `tests/Feature/AI/DirectWriteCoverageTest.php`:

```php
<?php

declare(strict_types=1);

it('exactly one fill_form return site remains — create_what_if_scenario', function (): void {
    $source = file_get_contents(base_path('app/Agents/CoordinatingAgent.php'));
    preg_match_all("/'action' => 'fill_form'/", $source, $m);
    expect(count($m[0]))->toBe(1);

    preg_match('/function handleCreateWhatIfScenario.*?\n    \}/s', $source, $body);
    expect($body[0])->toContain("'action' => 'fill_form'");
});
```

- [ ] **Step 2 — Observer-fire tests** at `tests/Feature/AI/DirectWriteObserverFireTest.php` — one test per handler spying on the expected observer. Skeleton:

```php
it('create_savings_account fires SavingsAccountRiskObserver + NetWorthCacheObserver', function (): void {
    $user = \App\Models\User::factory()->create();
    \Illuminate\Support\Facades\Event::fake();
    app(\App\Agents\CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'A', 'provider' => 'B', 'account_type' => 'cash_isa',
        'balance' => 100, 'interest_rate' => 1.0,
    ], $user);
    \Illuminate\Support\Facades\Event::assertDispatched(\Illuminate\Database\Eloquent\Events\Created::class);
});
```

- [ ] **Step 3 — Run both tests**. PASS.

- [ ] **Step 4 — Commit**.

---

## Task 0.6 — Billing / subscription tools

**Invariant:** INV-2.7.2.

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php` (add `billingTools()` method, merge into `getTools`)
- Modify: `app/Services/AI/XaiToolDefinitions.php` (add wrapped billing tools)
- Modify: `app/Agents/CoordinatingAgent.php::executeTool` dispatch (3 new handlers)
- Test: `tests/Feature/AI/BillingToolsTest.php`, `tests/Architecture/ToolCatalogueParityTest.php`

- [ ] **Step 1 — Failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\{User, Subscription, Invoice, SubscriptionPlan};

it('get_subscription_status returns current subscription shape', function (): void {
    $plan = SubscriptionPlan::factory()->create(['name' => 'Standard', 'tier' => 'standard']);
    $user = User::factory()->has(
        Subscription::factory()->active()->state(['subscription_plan_id' => $plan->id])
    )->create();

    $result = app(\App\Agents\CoordinatingAgent::class)
        ->executeTool('get_subscription_status', [], $user);

    expect($result)->toHaveKeys([
        'status', 'plan_name', 'trial_ends_at', 'current_period_end',
        'next_charge_amount', 'is_cancelled',
    ]);
});

it('list_invoices returns invoice shape', function (): void {
    $user = User::factory()->has(Invoice::factory()->count(3))->create();
    $result = app(\App\Agents\CoordinatingAgent::class)
        ->executeTool('list_invoices', [], $user);
    expect($result)->toHaveCount(3);
    expect($result[0])->toHaveKeys(['invoice_id', 'issued_at', 'amount', 'status', 'pdf_url']);
});

it('get_current_plan returns plan shape', function (): void {
    $user = User::factory()->has(Subscription::factory()->active())->create();
    $result = app(\App\Agents\CoordinatingAgent::class)
        ->executeTool('get_current_plan', [], $user);
    expect($result)->toHaveKeys(['plan_name', 'tier', 'price_gbp', 'features']);
});
```

```php
<?php

declare(strict_types=1);

use App\Services\AI\{AiToolDefinitions, XaiToolDefinitions};

it('Anthropic and xAI tool catalogues match exactly', function (): void {
    $a = collect(app(AiToolDefinitions::class)->getTools(false))
        ->pluck('name')->sort()->values()->all();
    $x = collect(app(XaiToolDefinitions::class)->getTools(false))
        ->map(fn ($t) => $t['function']['name'])
        ->sort()->values()->all();
    expect($a)->toEqual($x);
});
```

- [ ] **Step 2 — Register tools** on Anthropic: `billingTools()` method returning 3 tool definitions (zero-parameter objects). Merge into `getTools`.

- [ ] **Step 3 — xAI equivalents** via `wrapTool` with `strict: true`.

- [ ] **Step 4 — Implement handlers** in `CoordinatingAgent`:

```php
private function handleGetSubscriptionStatus(User $user): array
{
    $sub = $user->activeSubscription();
    if (! $sub) {
        return ['status' => 'none'];
    }
    return [
        'status' => $sub->status,
        'plan_name' => $sub->plan->name,
        'trial_ends_at' => $sub->trial_ends_at?->toIso8601String(),
        'current_period_end' => $sub->current_period_end->toIso8601String(),
        'next_charge_amount' => (float) $sub->plan->price_gbp,
        'is_cancelled' => $sub->cancelled_at !== null,
    ];
}

private function handleListInvoices(User $user): array
{
    return $user->invoices()->latest()->get()
        ->map(fn ($i) => [
            'invoice_id' => $i->id,
            'issued_at' => $i->issued_at->toIso8601String(),
            'amount' => (float) $i->amount,
            'status' => $i->status,
            'pdf_url' => $i->pdf_url,
        ])->toArray();
}

private function handleGetCurrentPlan(User $user): array
{
    $sub = $user->activeSubscription();
    if (! $sub) {
        return ['plan_name' => 'none', 'tier' => 'none', 'price_gbp' => 0.0, 'features' => []];
    }
    return [
        'plan_name' => $sub->plan->name,
        'tier' => $sub->plan->tier,
        'price_gbp' => (float) $sub->plan->price_gbp,
        'features' => $sub->plan->features ?? [],
    ];
}
```

Wire into the `executeTool` dispatch switch.

- [ ] **Step 5 — Pest**. PASS both providers.

- [ ] **Step 6 — Commit**
  ```
  git commit -am "feat(fyn): billing tools + provider parity (40/40 catalogue)"
  ```

---

## Task 0.7 — `update_record` allowlist + strict schema

**Invariant:** INV-2.7.3.

**Files:**
- Create: `app/Constants/UpdateRecordAllowlist.php`
- Modify: `app/Agents/CoordinatingAgent.php::handleUpdateRecord` (around line 3134)
- Modify: `app/Services/AI/AiToolDefinitions.php` (replace `update_record` schema with `oneOf`)
- Modify: `app/Services/AI/XaiToolDefinitions.php` (wrap with `strict: true`)
- Test: `tests/Unit/Constants/UpdateRecordAllowlistTest.php`, `tests/Feature/AI/UpdateRecordSecurityTest.php`

- [ ] **Step 1 — Create allowlist**

```php
<?php

declare(strict_types=1);

namespace App\Constants;

final class UpdateRecordAllowlist
{
    public const MAP = [
        'savings_account' => ['account_name', 'provider', 'balance', 'interest_rate', 'account_type'],
        'investment_account' => ['account_name', 'provider', 'total_value', 'account_type'],
        'holding' => ['security_name', 'ticker', 'allocation_percent', 'current_price', 'ocf_percent'],
        'dc_pension' => ['provider', 'pot_value', 'monthly_contribution', 'employer_contribution'],
        'db_pension' => ['scheme_name', 'annual_pension_amount', 'normal_retirement_age'],
        'property' => ['property_name', 'current_value', 'property_type'],
        'mortgage' => ['outstanding_balance', 'interest_rate', 'term_years', 'monthly_payment'],
        'life_insurance_policy' => ['provider', 'sum_assured', 'monthly_premium', 'end_date'],
        'critical_illness_policy' => ['provider', 'sum_assured', 'monthly_premium', 'end_date'],
        'income_protection_policy' => ['provider', 'monthly_benefit', 'monthly_premium', 'deferred_period'],
        'trust' => ['trust_name', 'trust_type', 'value'],
        'estate_asset' => ['asset_name', 'asset_type', 'value'],
        'estate_liability' => ['liability_name', 'liability_type', 'outstanding_amount'],
        'estate_gift' => ['gift_description', 'value', 'gift_date', 'recipient_name'],
        'chattel' => ['chattel_name', 'value', 'category'],
        'business_interest' => ['business_name', 'ownership_percentage', 'value'],
        'family_member' => ['first_name', 'surname', 'date_of_birth', 'annual_income'],
        'goal' => ['goal_name', 'target_amount', 'target_date', 'priority', 'status'],
        'life_event' => ['event_name', 'event_type', 'expected_date', 'amount', 'certainty'],
    ];

    /** @return list<string> */
    public static function allowedFields(string $entityType): array
    {
        return self::MAP[$entityType] ?? [];
    }
}
```

- [ ] **Step 2 — Failing test**

```php
<?php

declare(strict_types=1);

use App\Constants\UpdateRecordAllowlist;

it('rejects Trust.settlor', function (): void {
    expect(UpdateRecordAllowlist::allowedFields('trust'))->not->toContain('settlor');
});

it('rejects Mortgage.start_date', function (): void {
    expect(UpdateRecordAllowlist::allowedFields('mortgage'))->not->toContain('start_date');
});

it('rejects FamilyMember.relationship', function (): void {
    expect(UpdateRecordAllowlist::allowedFields('family_member'))->not->toContain('relationship');
});
```

- [ ] **Step 3 — Rewrite handler**

```php
private function handleUpdateRecord(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return ['blocked' => true, 'reason' => 'preview_mode'];
    }

    $entityType = $input['entity_type'] ?? '';
    $allowed = \App\Constants\UpdateRecordAllowlist::allowedFields($entityType);
    if (empty($allowed)) {
        return ['error' => 'unsupported_entity_type', 'entity_type' => $entityType];
    }

    $fields = $input['fields'] ?? [];
    $disallowed = array_diff_key($fields, array_flip($allowed));
    if (! empty($disallowed)) {
        return [
            'error' => 'fields_not_allowed',
            'entity_type' => $entityType,
            'disallowed_fields' => array_keys($disallowed),
        ];
    }

    // Dispatch to Model::update per entity_type within DB::transaction.
    // (Existing model-lookup + update logic, now with allowlisted fields.)
}
```

- [ ] **Step 4 — oneOf schema** in `AiToolDefinitions`:

```php
private function updateRecordSchema(): array
{
    $oneOf = [];
    foreach (\App\Constants\UpdateRecordAllowlist::MAP as $entityType => $allowedFields) {
        $properties = [];
        foreach ($allowedFields as $field) {
            $properties[$field] = ['type' => ['string', 'number', 'null']];
        }
        $oneOf[] = [
            'type' => 'object',
            'properties' => [
                'entity_type' => ['const' => $entityType],
                'entity_id' => ['type' => 'integer'],
                'fields' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['entity_type', 'entity_id', 'fields'],
            'additionalProperties' => false,
        ];
    }
    return ['oneOf' => $oneOf];
}
```

- [ ] **Step 5 — xAI strict wrap**.

- [ ] **Step 6 — Feature test** per entity type — attempt each forbidden field; assert `fields_not_allowed`.

- [ ] **Step 7 — Pest + commit**.

---

## Task 0.8 — `delete_record` two-phase confirmation

**Invariant:** D5 Level 3 sub-criterion.

**Files:**
- Modify: `app/Agents/CoordinatingAgent.php::handleDeleteRecord`
- Test: `tests/Feature/AI/DeleteRecordConfirmationTest.php`

- [ ] **Step 1 — Failing test** — first call returns `{requires_confirmation: true, confirmation_token}`; repeat with token deletes.

- [ ] **Step 2 — Rewrite handler**

```php
private function handleDeleteRecord(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return ['blocked' => true];
    }

    $token = hash('sha256',
        $user->id . '|' . $input['entity_type'] . '|' . $input['entity_id']
        . '|' . now()->format('Y-m-d')
    );

    if (($input['confirmation_token'] ?? '') !== $token) {
        return [
            'requires_confirmation' => true,
            'confirmation_token' => $token,
            'entity_type' => $input['entity_type'],
            'entity_id' => $input['entity_id'],
            'preview_message' => "This will delete {$input['entity_type']} #{$input['entity_id']}. Confirm?",
        ];
    }

    // Proceed with deletion within DB::transaction.
}
```

- [ ] **Step 3 — Pest + commit**.

---

## Task 0.9 — Consent runtime check

**Invariant:** INV-2.10.3.

**Files:**
- Modify: `app/Http/Controllers/Api/AiChatController.php::sendMessage` + `startOnboarding`
- Modify: `app/Services/GDPR/ConsentService.php` (add `TYPE_AI_CHAT` constant if absent)
- Create migration: `database/migrations/2026_04_25_000002_add_ai_chat_consent_types.php`
- Modify: `resources/js/store/modules/aiChat.js` (new `consent_required` SSE handler)
- Test: `tests/Feature/AI/ConsentRuntimeCheckTest.php`

- [ ] **Step 1 — Failing test**: user without `ai_chat` consent → 403 `{error: 'consent_required', required: 'ai_chat'}`.

- [ ] **Step 2 — Migration** to widen `user_consents.type` (either varchar or enum ADD VALUE).

- [ ] **Step 3 — Add `TYPE_AI_CHAT`** to `ConsentService`.

- [ ] **Step 4 — Guard in controller**

```php
if (! app(\App\Services\GDPR\ConsentService::class)->hasConsent($user, 'ai_chat')) {
    return response()->json([
        'error' => 'consent_required',
        'required' => 'ai_chat',
    ], 403);
}
```

- [ ] **Step 5 — Frontend handler** in `aiChat.js` — new `case 'consent_required':` dispatches consent-modal open.

- [ ] **Step 6 — Test + commit**.

---

## Task 0.10 — User-prompt-field sanitisation + structural separation

**Invariant:** INV-2.10.4.

**Files:**
- Create: `app/Services/AI/Prompts/UserContentSanitiser.php`
- Modify: `app/Services/AI/AdvicePromptBuilder.php`
- Modify: `app/Services/Onboarding/OnboardingPromptBuilder.php`
- Test: `tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php`

- [ ] **Step 1 — Create sanitiser**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

final class UserContentSanitiser
{
    public static function clean(string $value): string
    {
        return preg_replace("/[^A-Za-z0-9\s'.,\-]/u", '', $value) ?? '';
    }

    public static function wrap(string $value): string
    {
        return '<user_provided>'.self::clean($value).'</user_provided>';
    }
}
```

- [ ] **Step 2 — Failing injection tests** against known attack strings.

- [ ] **Step 3 — Wrap every user-controlled interpolation** in both prompt builders — replace `"Hello {$user->first_name}"` patterns with `"Hello ".UserContentSanitiser::wrap($user->first_name)`.

- [ ] **Step 4 — Test + commit**.

---

## Task 0.11 — Reliability bundle

**Invariants:** INV-2.9.1 through INV-2.9.6.

**Files:**
- Migration: `database/migrations/2026_04_25_000003_create_ai_daily_usage_table.php`
- Migration: `database/migrations/2026_04_25_000004_create_ai_request_idempotency_table.php`
- Migration: `database/migrations/2026_04_25_000005_create_ai_abort_events_table.php`
- Model: `app/Models/AiDailyUsage.php`, `app/Models/AiRequestIdempotency.php`, `app/Models/AiAbortEvent.php`
- Middleware: `app/Http/Middleware/IdempotencyKeyMiddleware.php`
- Job: `app/Jobs/AiIdempotencyCleanupJob.php`
- Modify: `app/Traits/HasAiGuardrails.php` (atomic `consume`, `getAiProviderForLoop`)
- Modify: `app/Traits/HasAiChat.php` (abort polling; `generateTitle` sanitation; `summariseToolResult` preserves entity_id)
- Modify: `app/Http/Controllers/Api/AdminController.php` (versioned provider cache)
- Modify: `routes/api.php` (attach idempotency middleware)
- Modify: `app/Services/Onboarding/AssetCaptureEntityExtractor.php` (DB dedup in `findMissing`)
- Modify: `app/Http/Kernel.php`, `app/Console/Kernel.php`

Each sub-step is a TDD cycle per pattern above. Commit per sub-step.

- [ ] **Step 1 — Atomic token budget**: migration + model + `HasAiGuardrails::consume` rewrite with `DB::transaction` + `SELECT ... FOR UPDATE`; backfill job reads today's `ai_messages` into `ai_daily_usage`.

- [ ] **Step 2 — SSE abort detection + `ai_abort_events`**: `connection_aborted()` polling in `HasAiChat::chat`; insert row on detection; do NOT roll back writes (per INV-2.9.2).

- [ ] **Step 3 — Idempotency middleware + table + cleanup job**.

- [ ] **Step 4 — Provider-swap lock**: `HasAiGuardrails::getAiProviderForLoop()` captures once per chat call; versioned cache key.

- [ ] **Step 5 — Gap-fill DB dedup** in `AssetCaptureEntityExtractor::findMissing`.

- [ ] **Step 6 — `generateTitle` sanitation**: `strip_tags` + `mb_substr` clamp at `HasAiChat.php:704`.

- [ ] **Step 7 — `summariseToolResult`** preserves `entity_id` + `entity_type` at `HasAiChat.php:749`.

- [ ] **Step 8 — Pest** with new concurrency tests.

- [ ] **Step 9 — Commit per sub-step**.

---

## Task 0.12 — Hash-chain audit migration

**Invariants:** INV-2.10.2, INV-2.5.4.

**Files:**
- Migration: `database/migrations/2026_04_25_000006_create_ai_audit_events_table.php`
- Model: `app/Models/AiAuditEvent.php`
- Service: `app/Services/AI/AuditChainService.php`
- Command: `app/Console/Commands/AiAuditVerifyChainCommand.php`
- Job: `app/Jobs/AiAuditRetentionJob.php`
- Modify: `app/Agents/CoordinatingAgent.php::executeTool` — replace `[AI-AUDIT]` file log with chain-append calls
- Modify: `resources/js/components/Admin/AiAudit.vue` (chain-view tab)
- Modify: `app/Console/Kernel.php` — schedule retention + weekly integrity check
- Tests: `tests/Feature/Audit/HashChainTest.php`, `HmacSigningTest.php`, `ChainTamperDetectionTest.php`, `RetentionPseudonymisationTest.php`

- [ ] **Step 1 — Migration** per schema in `01-invariants.md` INV-2.10.2.

- [ ] **Step 2 — `AuditChainService::append`**

```php
public function append(array $event): \App\Models\AiAuditEvent
{
    return \DB::transaction(function () use ($event) {
        $prev = \App\Models\AiAuditEvent::lockForUpdate()->latest('id')->first();
        $prevHash = $prev?->row_hash ?? str_repeat('0', 64);
        $signedAt = now();
        $serialised = json_encode($event, JSON_THROW_ON_ERROR);
        $rowHash = hash('sha256', $prevHash . $serialised . $signedAt->toIso8601String());
        $signature = hash_hmac('sha256', $rowHash, config('app.ai_audit_hmac_key'));
        return \App\Models\AiAuditEvent::create([
            ...$event,
            'prev_hash' => $prevHash,
            'row_hash' => $rowHash,
            'signed_at' => $signedAt,
            'signature' => $signature,
        ]);
    });
}

public function verifyChain(): array
{
    $iterator = \App\Models\AiAuditEvent::orderBy('id')->cursor();
    $prevHash = str_repeat('0', 64);
    $count = 0;
    foreach ($iterator as $row) {
        $serialised = json_encode($row->only([
            'user_id', 'conversation_id', 'tool_name', 'operation', 'status',
            'input_summary', 'result_summary', 'entity_type', 'entity_id',
        ]), JSON_THROW_ON_ERROR);
        $expected = hash('sha256', $prevHash . $serialised . $row->signed_at->toIso8601String());
        if ($expected !== $row->row_hash) {
            return ['chain_valid' => false, 'broken_at' => $row->id, 'row_count' => $count];
        }
        $prevHash = $row->row_hash;
        $count++;
    }
    return ['chain_valid' => true, 'tip_hash' => $prevHash, 'row_count' => $count];
}
```

- [ ] **Step 3 — Artisan command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AI\AuditChainService;
use Illuminate\Console\Command;

final class AiAuditVerifyChainCommand extends Command
{
    protected $signature = 'ai:audit:verify-chain';

    public function handle(AuditChainService $svc): int
    {
        $this->line(json_encode($svc->verifyChain()));
        return 0;
    }
}
```

- [ ] **Step 4 — Replace `[AI-AUDIT]` call sites** in `CoordinatingAgent::executeTool`:
  - At entry: `AuditChainService::append(['status' => 'dispatched', 'tool_name' => ..., 'input_summary' => ..., 'operation' => ...])`.
  - After successful write: `append(['status' => 'persisted', 'entity_id' => ..., 'entity_type' => ...])`.
  - In catch: `append(['status' => 'failed', 'result_summary' => ['error' => ...]])`.

- [ ] **Step 5 — Retention job** with hash-preserving pseudonymisation (swap PII fields for deterministic tokens; `row_hash` remains valid because the hash is over the original serialised content, which is what the retention job mutates — actually, this requires care: if the job mutates existing rows, the chain breaks. Correct pattern is soft-pseudonymisation in a separate view/export, not in the source rows. Document this trade-off in the job's class docblock.)

- [ ] **Step 6 — `AiAudit.vue` chain-view tab** reads `ai_audit_events`, shows list + chain-status banner.

- [ ] **Step 7 — Tests**:
  - `HashChainTest` — append 100 events, verify chain.
  - `HmacSigningTest` — sign / verify.
  - `ChainTamperDetectionTest` — manually mutate a row's `input_summary`; `verifyChain` detects break at that row.
  - `RetentionPseudonymisationTest` — pseudonymisation path preserves read-only chain view.

- [ ] **Step 8 — Commit**.

---

## Task 0.13 — CoreIdentity rewrite + FCA signposting

**Invariants:** INV-2.10.1, INV-2.3.3.

**Files:**
- Modify: `app/Services/AI/Prompts/CoreIdentity.php`
- Modify: `app/Services/AI/AdvicePromptBuilder.php`
- Test: `tests/Architecture/CoreIdentityFramingTest.php`, `tests/Feature/Fyn/FcaSignpostingTest.php`

- [ ] **Step 1 — Failing architecture test**

```php
<?php

declare(strict_types=1);

it('CoreIdentity does not frame Fyn as a qualified financial planner', function (): void {
    $src = file_get_contents(base_path('app/Services/AI/Prompts/CoreIdentity.php'));
    expect(strtolower($src))->not->toContain('you think like a qualified financial planner');
    expect(strtolower($src))->not->toContain('authorised adviser');
    expect(strtolower($src))->not->toContain('regulated adviser');
});
```

- [ ] **Step 2 — Rewrite `CoreIdentity::get`**

```php
public static function get(string $firstName): string
{
    return <<<PROMPT
You are Fyn, a UK personal-finance guidance tool inside the Fynla app. You help {$firstName} understand their finances, explore options, and surface the outputs of Fynla's financial-planning engines.

You do NOT give personalised regulated financial advice — {$firstName} must consult a qualified financial adviser for that.

Tone: clear, plain-English, calm. Never patronising. Never alarmist. British spelling. Currency in £. Always signpost regulated advice when the user's query asks "what should I do?".
PROMPT;
}
```

- [ ] **Step 3 — Signposting suffix** in `AdvicePromptBuilder` for recommendation-mode only:

```text
End your response with the exact signposting string:
"For regulated advice personal to your circumstances, speak to a qualified financial adviser."
Do NOT include this string on factual-mode responses.
```

- [ ] **Step 4 — Feature tests** for presence on recommendation-mode + absence on factual-mode.

- [ ] **Step 5 — Commit**
  ```
  git commit -am "feat(fyn): CoreIdentity guidance-only framing + FCA signposting on recommendation mode"
  ```

---

## Task 0.14 — Out-of-remit response

**Invariant:** INV-2.3.4.

**Files:**
- Modify: `app/Services/AI/AdviceFyn.php` (early-return on out-of-remit classification)
- Modify: `app/Constants/QuerySchemas.php` (add `OUT_OF_REMIT` constant if absent)
- Modify: `app/Services/AI/QueryClassifier.php` (classify non-financial topics as `out_of_remit`)
- Test: `tests/Feature/Fyn/OutOfRemitTest.php`

- [ ] **Step 1 — Failing tests** — medical / legal / emotional / general-knowledge inputs → exact string `"I'm able to help you with your finances. {context} is out of scope."`.

- [ ] **Step 2 — `QuerySchemas::OUT_OF_REMIT = 'out_of_remit'`**.

- [ ] **Step 3 — Extend `QueryClassifier`** to return `out_of_remit` with `detected_topic` for non-financial inputs.

- [ ] **Step 4 — `AdviceFyn::handle` early-return**

```php
public function handle(User $user, AiConversation $conversation, string $message, ?string $currentRoute = null): \Generator
{
    $classification = app(\App\Services\AI\QueryClassifier::class)->classify($message, $currentRoute);

    if ($classification['primary'] === \App\Constants\QuerySchemas::OUT_OF_REMIT) {
        $context = $classification['detected_topic'] ?? 'general queries';
        $text = "I'm able to help you with your finances. {$context} is out of scope.";

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $text,
            'persona' => 'advice',
        ]);

        yield ['type' => 'content', 'text' => $text];
        yield ['type' => 'done'];
        return;
    }

    // Normal dispatch.
    $allowedTools = $this->buildToolList($user);
    yield from $this->coordinatingAgent->chatWithPromptOverride(
        user: $user,
        conversation: $conversation,
        message: $message,
        currentRoute: $currentRoute,
        systemPromptOverride: null,
        allowedTools: $allowedTools,
        persistUserMessage: true,
        toolsListOverride: null,
        personaOverride: 'advice',
    );
}
```

- [ ] **Step 5 — Parameterised test** over 4 topic categories.

- [ ] **Step 6 — Commit**
  ```
  git commit -am "feat(fyn): out-of-remit canonical refusal"
  ```

---

## Task 0.15 — Coverage-gap tests for remaining invariants

These are small invariants from `01-invariants.md` that don't need their own large task but must have tests before Sprint 0 closes. Each is a single Pest test; commit them in one change.

**Invariants:** INV-2.2.4, INV-2.2.5, INV-2.2.6, INV-2.4.3, INV-2.6.1, INV-2.6.2, INV-2.7.4.

**Files:**
- Create: `tests/Feature/Onboarding/ResumeAfterDisconnectTest.php`
- Create: `tests/Feature/Onboarding/EntrySourceJourneyMapTest.php`
- Create: `tests/Feature/Onboarding/ParkedFactsFlushTest.php`
- Create: `tests/Feature/Fyn/CaptureCompleteStylingTest.php`
- Create: `tests/Feature/AI/ReadCompletenessTest.php`
- Create: `tests/Feature/AI/GetRecommendationsCompletenessTest.php`
- Create: `tests/Architecture/PreviewModeToolCatalogueTest.php`
- Modify (tiny): `config/onboarding.php` — add `journey_map` array
- Modify (tiny): `app/Http/Controllers/Api/AiChatController.php::startOnboarding` — read `journey_map` when `request->from` set

- [ ] **Step 1 — INV-2.2.4 resume test** — use the existing `OnboardingChatDirector::resumeSummary` method (lines 394-406 on the branch); assert it returns the expected label per `STATE_*` constant; assert the `startOnboarding` emits a `quick_replies` turn with the label + Yes/No bubble when reconnect > 5 min.

- [ ] **Step 2 — INV-2.2.5 journey map test + config** — add `'journey_map' => ['budgeting' => 'budgeting', 'goals' => 'goals', 'protection' => 'protection', 'retirement' => 'retirement']` to `config/onboarding.php`; in `startOnboarding`, if `$request->from` is set AND in the map, set `onboarding_fyn_step = STATE_JOURNEY_SELECTION` with that journey pre-selected; assert the 4 mappings + unknown fallback.

- [ ] **Step 3 — INV-2.2.6 parked-facts flush test** — seed a conversation with `onboarding_parked_facts = {first_name: 'X'}`; run the base_personal commit; assert `first_name` key removed.

- [ ] **Step 4 — INV-2.4.3 capture_complete styling test** — render an `capture_complete` message; assert the DOM element's CSS class set equals that of a normal assistant `content` bubble (no "capture" badge class).

- [ ] **Step 5 — INV-2.6.1 read-completeness test** — seed 50+ records; call `handleListRecords` for each entity type; assert `count($result) === DB count`.

- [ ] **Step 6 — INV-2.6.2 get_recommendations completeness test** — seed a user with analysis data; call `handleRecommendations`; assert every field (`priority_score`, `timeline`, `category`, `impact`, `rationale`, `personalised_context`) is present on every recommendation.

- [ ] **Step 7 — INV-2.7.4 preview parity test** — `AiToolDefinitions::getTools(true)` and `XaiToolDefinitions::getTools(true)` return identical sets; intersection contains zero write tools.

- [ ] **Step 8 — Pest + commit**
  ```
  git commit -am "test(fyn): coverage for remaining invariants (INV-2.2.4/5/6, 2.4.3, 2.6.1/2, 2.7.4)"
  ```

---

## Task 0.16 — Browser test harness + Sprint 0 Playwright matrix

**Invariants:** every Sprint 0 invariant that has observable UI — see [`03-test-strategy.md`](03-test-strategy.md) per-sprint scenario index. Sprint 0 requires BS-01, 02, 04, 05, 06, 07, 10, 11, 12, 13, 14, 15, 16, 17 (4-focus), 18, 19, 20, 21, 22, 23 — **20 scenarios**.

**Files:**
- Create: `tests/Browser/TestCase.php` (Pest base wrapping the Playwright MCP)
- Create: `tests/Browser/Helpers/Login.php`
- Create: `tests/Browser/Helpers/AssertSseEvents.php`
- Create: `tests/Browser/README.md`
- Create: `tests/Browser/scenarios/BS-01-onboarding-path-choice-to-done.php` through `BS-23-prompt-injection.php` (20 files)
- Create per-scenario screenshot output directory: `docs/sprint-0-verification/BS-NN/`

- [ ] **Step 1 — Harness skeleton**
  ```php
  // tests/Browser/TestCase.php
  <?php
  declare(strict_types=1);
  namespace Tests\Browser;

  use Tests\TestCase as BaseTestCase;

  abstract class TestCase extends BaseTestCase
  {
      protected string $rootUrl = 'http://localhost:8000';

      protected function setUp(): void
      {
          parent::setUp();
          // Assume `./dev.sh` is running in another terminal.
          // Assume `php artisan db:seed` was run pre-suite.
      }

      protected function browserHealthcheck(): void
      {
          // Ping http://localhost:8000; if not responding, skip the suite with a clear message
          // to start ./dev.sh.
      }
  }
  ```

- [ ] **Step 2 — Login helper** — centralises the click-through pattern so every scenario begins identically:

```php
// tests/Browser/Helpers/Login.php
<?php
declare(strict_types=1);
namespace Tests\Browser\Helpers;

use App\Models\EmailVerificationCode;
use App\Models\User;

final class Login
{
    public static function as(string $email, string $password): void
    {
        // 1. browser_navigate http://localhost:8000
        // 2. browser_snapshot -> click 'Sign in'
        // 3. browser_fill_form email, password -> Enter
        // 4. If MFA prompt: fetch latest code from DB, browser_type, Enter
        //    (per root CLAUDE.md 'Authentication for Testing')
        // 5. browser_wait_for 'Dashboard' or first Onboarding Fyn turn
    }

    public static function asFactoryUser(User $user, string $password): void
    {
        self::as($user->email, $password);
    }

    public static function asPreviewPersona(string $persona): void
    {
        // browser_navigate http://localhost:8000
        // browser_click persona CTA on landing page
        // wait for dashboard
    }
}
```

- [ ] **Step 3 — SSE capture helper**

```php
// tests/Browser/Helpers/AssertSseEvents.php
<?php
declare(strict_types=1);
namespace Tests\Browser\Helpers;

final class AssertSseEvents
{
    /** @return list<array<string, mixed>> */
    public static function fromNetworkRequests(array $requests): array
    {
        // Filter requests to /api/ai-chat/conversations/*/messages
        // Parse text/event-stream bodies; return decoded event list.
    }

    public static function assertNoEventType(array $events, string $type): void
    {
        foreach ($events as $event) {
            if (($event['type'] ?? null) === $type) {
                throw new \AssertionError("Unexpected SSE event '{$type}' emitted");
            }
        }
    }

    public static function assertEventTypeCount(array $events, string $type, int $expected): void
    {
        $count = count(array_filter($events, fn ($e) => ($e['type'] ?? null) === $type));
        if ($count !== $expected) {
            throw new \AssertionError("Expected {$expected} '{$type}' events, got {$count}");
        }
    }
}
```

- [ ] **Step 4 — Write the 20 scenario files** — one Pest test file per BS-NN. Each scenario follows the spec in [`03-test-strategy.md`](03-test-strategy.md). Example for BS-11:

```php
<?php
// tests/Browser/scenarios/BS-11-handoff-invisibility.php
declare(strict_types=1);

use App\Models\User;
use Tests\Browser\Helpers\{Login, AssertSseEvents};

it('BS-11 handoff invisibility — no persona_state_change, no inline quick_replies, no pill', function (): void {
    // Seed
    $this->seed(\Database\Seeders\PreviewUserSeeder::class);

    // Step 1-2: log in as young_family persona
    Login::asPreviewPersona('young_family');

    // Step 3: capture rest-state snapshot + placeholder text via browser_evaluate

    // Step 4: submit the advice + inline capture message
    //   Use browser_type + browser_press_key Enter.

    // Step 5: wait for 'done' SSE event

    // Step 6: capture network requests; extract SSE events
    $events = AssertSseEvents::fromNetworkRequests(/* requests */);

    // Assert no persona_state_change
    AssertSseEvents::assertNoEventType($events, 'persona_state_change');

    // Assert no quick_replies during capture sub-turn
    // (between first 'content' event time and 'done' time)
    AssertSseEvents::assertNoEventType(/* filtered capture-phase events */, 'quick_replies');

    // Step 7: post-turn DOM snapshot; assert placeholder unchanged vs step 3; assert no pill element

    // Step 8: navigate /protection via UI click; verify Aviva policy row rendered
});
```

The remaining 19 scenarios follow the same structure; full specs are in [`03-test-strategy.md`](03-test-strategy.md).

- [ ] **Step 5 — Screenshots** — each scenario calls `browser_take_screenshot` at key assertion points; save to `docs/sprint-0-verification/BS-NN/<step>.png`.

- [ ] **Step 6 — Run the matrix**
  ```
  ./dev.sh &                               # Laravel + Vite in another terminal
  php artisan db:seed                      # seed test data
  ./vendor/bin/pest --testsuite=Browser --filter=BS-
  ```
  Expected: all 20 scenarios PASS.

- [ ] **Step 7 — Commit**
  ```
  git commit -am "test(browser): Sprint 0 Playwright matrix (20 scenarios)"
  ```

---

## Sprint 0 verification

- [ ] **Full Pest:** `./vendor/bin/pest` — all pass.
- [ ] **Architecture tests:** `./vendor/bin/pest --testsuite=Architecture` — all pass.
- [ ] **Audit chain:** `php artisan ai:audit:verify-chain` — `{chain_valid: true, ...}`.
- [ ] **Browser matrix (20 Sprint 0 scenarios):** `./vendor/bin/pest --testsuite=Browser --filter=BS-` — 20/20 pass. Screenshots in `docs/sprint-0-verification/`.
- [ ] **Rubric-A re-score** per `fyn-rubrics.md §A`. Target: 13-15/40.
- [ ] **Merge branch:** open PR → `feature/fyn-persona-split`; merge on green.

**Report-finished gate:** Sprint 0 is NOT done until the 20-scenario Browser matrix is green AND screenshot evidence is committed AND Rubric-A is re-scored. Per `03-test-strategy.md §Non-negotiables when reporting "testing complete"` — do not report done otherwise.

Sprint 0 complete. [`11-sprint-1-plan.md`](11-sprint-1-plan.md) next.
