# Fyn Persona Split Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

> **READ THIS FIRST:** the plan below was written before the codebase audit. Sections that conflict with real code have been corrected in the **AMENDMENTS (post-audit)** section immediately below this header. Every task body that references behaviour changed by the amendments inherits the amendment — do NOT implement the original task text where it conflicts. The amended spec at `docs/superpowers/specs/2026-04-21-fyn-persona-split-design.md` is authoritative.

---

## AMENDMENTS (post-audit, 2026-04-21)

The codebase audit (`feature-dev:code-explorer` + `feature-dev:code-architect`) identified 19 conflicts between this plan and the live code. All are resolved below. Apply these before reading any task body.

### A. Scope removed — director and prompt builder are NOT absorbed or deleted

- `OnboardingChatDirector` stays. `OnboardingPromptBuilder` stays. Both keep their current roles. The FR-M14 buffered off-script filter stays inside the director.
- **Tasks dropped:** Task 22 (move FR-M14 filter), Task 33 (port director tests), Task 34 (delete director + old prompt builder, simplify controller), the entire Phase 11 header. The `dispatchOnboarding()` method on the orchestrator is not implemented. `OnboardingTurnHandler` is not created. `OnboardingStateMachine` does NOT move namespace.
- **Task 21 simplified:** the orchestrator handles post-onboarding turns only. The `mode` parameter on `dispatch()` is removed. Onboarding mode inside the orchestrator does not exist.
- **Task 7 simplified:** `DataCapturePromptBuilder` is a dedicated post-onboarding builder. No byte-compat requirement with `OnboardingPromptBuilder`. The `originatingFocus` branch that delegated to `OnboardingPromptBuilder::buildAssetCapturePrompt()` is dropped. The builder takes a `CaptureContext` and emits the short post-onboarding capture prompt.
- **Task 9 simplified:** the persona registry has two personas. No onboarding-related entries. `data_capture.allowed_tools` covers post-onboarding write tools (same list the plan had, minus the onboarding-specific `capture_personal_details`/`capture_spouse_details`/`capture_dependants`/`capture_work_details` which stay with the director).
- **Task 18 simplified:** `AiChatController` match becomes 3-way (inOnboarding → director; splitEnabled → orchestrator; default → CoordinatingAgent). No 4-way match. No `mode: 'onboarding'` argument.

### B. Onboarding UX overhaul — still in scope, relocated to the director

Tasks 23–32 (profile-review states, spouse skip, multi-job loop, employment bubble changes, retraction, Vuex layout, ProfileReviewPanel, wide chat, skip-link rendering, memory/parking, resume) still land — but as extensions to `OnboardingChatDirector`, `OnboardingStateMachine`, and a new onboarding-only Vue component. Not as orchestrator subsystems.

- **Task 31 dropped:** `OnboardingMemoryExtractor` is removed. The parking column is the single source of memory.
- **Task 33 becomes `OnboardingFactExtractor`:** regex-based fact extraction per turn, merged into `ai_conversations.onboarding_parked_facts`. The director (not the orchestrator) runs this on every user turn and consumes it per state. History-scan behaviour (for the resume greeting and for skipping already-answered states) queries the parking column, not raw messages.
- **Task 30 onboarding view target:** `OnboardingChatDirector` lives behind the `Onboarding` Vue component mounted at `/onboarding/welcome` and related routes. New `resources/js/components/Fyn/FynOnboardingChat.vue` wraps the chat-window portion of that view. The post-onboarding `AiChatPanel.vue` is unchanged. Replace all `ChatWindow.vue` / `OnboardingFyn.vue` references in the plan accordingly.
- **Task 30 skip-link emission:** the director emits `skip_link` metadata on the spouse-state message. Click fires the action endpoint (see E), not a user message containing `__skip__`.

### C. Estate tools — use existing `LastingPowerOfAttorney` model

- **Task 10 dropped:** no new `PowerOfAttorney` model, no new migration, no new controller, no new factory, no new request validators, no new resource, no new routes. The `App\Models\Estate\LastingPowerOfAttorney` model and `lasting_powers_of_attorney` table already exist with richer schema (proper attorneys relationship via `LpaAttorney`, OPG reference, donor details, `SoftDeletes`, `Auditable`).
- **Task 12 rewritten:** only the `create_power_of_attorney` and `update_power_of_attorney` tool definitions and `CoordinatingAgent` handlers are new. Tool schema:
    - Field `lpa_type` (not `type`) with enum values `property_financial` / `health_welfare` (not `property_and_finance` / `health_and_welfare`).
    - Primary `attorney_name` creates a `LpaAttorney` record via the `attorneys()` relationship — not a flat string on the LPA row.
    - Optional `replacement_attorney_name` creates a second `LpaAttorney` with `is_replacement = true` (or whichever column name the model uses).
    - Status field matches existing enum (`draft`, `registered`, plus any other values the model defines).
- **Task 11 migration unconditional:** the conditional `Schema::hasColumn` guards in the Will migration are unnecessary. `residuary_beneficiary`, `guardian_for_minors`, `specific_gifts` do NOT exist on the `wills` table (confirmed via audit). Write the migration as a straight `$table->string('residuary_beneficiary')->nullable(); $table->string('guardian_for_minors')->nullable(); $table->text('specific_gifts')->nullable();` block. Do NOT add `executor_name` — it exists already. Tool schema targets the four fields. `will_documents.specific_gifts` is a separate column on a separate model and is not used here.

### D. Classifier fast-path — extend `QueryClassifier`, no new class

- **Tasks 15 + 16 dropped (no `FynIntentClassifier` class):** the existing `App\Services\AI\QueryClassifier` already classifies messages against `QuerySchemas::KEYWORD_PATTERNS`. Its `DATA_ENTRY` primary type already detects structural verbs.
- **Task 17 rewritten:** the orchestrator calls `QueryClassifier::classify($message)` at the top of `dispatch()`. When `classification['primary'] === QuerySchemas::DATA_ENTRY` AND `str_word_count($message) <= 40` AND `! QuerySchemas::isAdviceShaped($message)`, preselect data_capture persona. Otherwise fall through to advice and pass the classification into `AdvicePromptBuilder::build()` as today. Add a new `QuerySchemas::isAdviceShaped(string $message): bool` helper returning true if the message contains any of: `should I`, `what about`, `how much`, `am I`, `can you explain`, `why`, `recommend`, `advice`, `compare`, `projection`, `forecast`.

### E. Resume / skip / restart — new action endpoint

- **Task 32 rewritten:** add `POST /api/ai-chat/conversations/{id}/action` with body `{action: 'resume' | 'continue' | 'restart' | 'skip'}`. Create `AiChatController::action(Request $request, int $conversationId)` handler that validates the action enum and routes to the director (for onboarding conversations) or orchestrator (for post-onboarding). Actions are NOT persisted as `AiMessage` rows. Route added to `PreviewWriteInterceptor::EXCLUDED_ROUTES`.
- Frontend — replace every `__resume__`, `__continue__`, `__restart__`, `__skip__` sentinel-string send in `aiChatService.js` and `FynOnboardingChat.vue` with `postAction(conversationId, actionName)` calls that hit the new endpoint.
- On resume: director detects `onboarding_completed = false` AND `onboarding_fyn_step !== null` AND prior `AiMessage` rows exist → emits welcome-back greeting with `continue` and `restart` action bubbles.

### F. Code-correctness fixes applied throughout

Search-and-replace at implementation time — every instance of the LHS below must use the RHS.

| Wrong (in plan) | Correct |
|---|---|
| `ai_conversation_id` (in `AiMessage::where(...)`, `AiMessage::create([...])`) | `conversation_id` — this is the actual foreign key column per `database/migrations/2026_02_27_200002_create_ai_messages_table.php` and the `AiMessage` model's `$fillable` |
| `CoordinatingAgent::chatWithPromptOverride($user, $conv, $msg, $systemPrompt, $tools)` | `CoordinatingAgent::chatWithPromptOverride($user, $conv, $msg, currentRoute: null, systemPromptOverride: $systemPrompt, allowedTools: null, persistUserMessage: true, toolsListOverride: $tools)` — 8 params, tools go in `toolsListOverride`, not `allowedTools` |
| `STATE_EXPENDITURE` | `STATE_BASE_EXPENDITURE` — actual constant name in `OnboardingStateMachine` |
| `OnboardingStateMachine::getState('base_employment')` (Task 24 test) | Add a public static `getState(string $name): ?array` method on `OnboardingStateMachine` returning the state definition array, or rewrite the test to read from whatever public method already exposes the bubble list |
| `describeStep(string $step)` — references `$user` (out of scope) | `describeStep(string $step, ?User $user = null)` — pass `$user` explicitly; fallback to generic label when null |
| `extractSpouseName`: `preg_match('/.../', ucwords(mb_strtolower($text)), $m)` | `preg_match('/.../', $text, $m)` — run the regex on the original-case input; `ucwords(lowercase)` capitalises every word and breaks `[A-Z][a-z]+` |
| `max-width: 28rem` / `max-width: 56rem` in scoped CSS | Tailwind utilities on the component: `w-[525px]` for standard (matches existing `AiChatPanel.vue` width), `max-w-4xl` for wide (≈56rem). No scoped CSS with hardcoded `rem` values (violates CLAUDE.md §12). |
| `fynlaDesignGuide.md v1.4.0` | `fynlaDesignGuide.md v1.3.0` — actual version in the vault |
| `resources/js/components/AiChat/ChatWindow.vue` | `resources/js/components/Fyn/FynOnboardingChat.vue` (new) for onboarding flow. For post-onboarding chat modifications, target the existing `resources/js/components/Shared/AiChatPanel.vue` |
| `resources/js/components/AiChat/MessageBubble.vue` | No such file. Check whether `AiChatPanel.vue` renders message bubbles inline or delegates to an existing component; either way, skip-link rendering, record cards, and preview-CTA logic live in the relevant existing file. Create new component only if genuinely reusable. |
| `resources/js/views/OnboardingFyn.vue` | No such file. The Fyn onboarding flow is mounted via the `Onboarding` component referenced at `resources/js/router/index.js:371-393`. Verify the actual file path before modifying. |

### G. Prerequisite task — add factories (unconditional)

`AiConversationFactory` and `AiMessageFactory` do not exist in `database/factories/` (confirmed via grep). Every test in this plan that calls `AiConversation::factory()` or `AiMessage::factory()` will fail without them. Insert this as Task 4a (before Task 5).

**Unconditional:** `AiConversation` and `AiMessage` models do NOT currently use the `HasFactory` trait (confirmed via grep on `app/Models/AiConversation.php:13` and `app/Models/AiMessage.php:10`). Task 4a MUST add the trait; do not treat this as a conditional check.

Use the `fake()` helper, NOT `$this->faker` — per `database/CLAUDE.md` factory convention.

```php
// database/factories/AiConversationFactory.php
namespace Database\Factories;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiConversationFactory extends Factory {
    protected $model = AiConversation::class;
    public function definition(): array {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'metadata' => [],
        ];
    }
}
```

```php
// database/factories/AiMessageFactory.php
namespace Database\Factories;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiMessageFactory extends Factory {
    protected $model = AiMessage::class;
    public function definition(): array {
        return [
            'conversation_id' => AiConversation::factory(),
            'role' => 'user',
            'content' => fake()->paragraph(),
        ];
    }
}
```

**Model updates (unconditional):**
- `app/Models/AiConversation.php` — add `use Illuminate\Database\Eloquent\Factories\HasFactory;` and `use HasFactory;` to the class body.
- `app/Models/AiMessage.php` — same.

Run `php artisan db:seed` after Task 4a lands to confirm no factory regression.

### H. SSE event schema additions

The orchestrator and director must explicitly yield new SSE event types that the frontend Vuex store consumes. Declare these in the plan's Vuex integration work:

- `['type' => 'persona_state_change', 'current' => 'advice' | 'capturing']` — emitted by the orchestrator on every transition in/out of capturing state.
- `['type' => 'onboarding_layout_change', 'mode' => 'wide' | 'standard']` — emitted by the director on entry to any pause state (`standard`) or exit from a pause state (`wide`).
- `['type' => 'capture_complete', 'summary' => string, 'records_created' => array]` — emitted by the orchestrator after intercepting the `capture_complete` internal tool call.

### I. Missing integrations to add

- **Preview middleware:** add `api/ai-chat/conversations/*/action` to `EXCLUDED_ROUTES` in `app/Http/Middleware/PreviewWriteInterceptor.php`. The existing conversation routes are excluded; the new action route needs the same treatment.
- **Cache invalidation:** in `CoordinatingAgent::handleCreateWill` and `::handleCreatePowerOfAttorney` (and the `update_*` variants), call `$this->invalidateUserCache($user->id)` after creating or updating the record. Existing handler pattern — see e.g. `handleCreateSavingsAccount`.
- **LastingPowerOfAttorney observer:** the existing `Auditable` trait registers observers for audit logging. No additional observer is required for this release, but note that estate-planning analysis cached values may become stale after LPA creation. The `CoordinatingAgent::invalidateUserCache` call above handles this.

### J. Feature flag set

Final flag set (two flags only):
- `FYN_PERSONA_SPLIT` — master on/off for the post-onboarding orchestrator.
- `FYN_CLASSIFIER_FAST_PATH` — classifier kill switch within the orchestrator. Only meaningful when `FYN_PERSONA_SPLIT=true`.

No `FYN_ONBOARDING_ORCHESTRATOR` flag — the onboarding UX overhaul ships under the existing `onboarding.fyn_flow_enabled` flag as extensions to the director.

### K. Task renumbering (informational)

Dropped tasks: 10 (PowerOfAttorney infra — rewritten to zero work), 15 (classifier stub), 16 (classifier rules), 22 (filter move), 31 (memory extractor), 33 (previously numbered 33 — director cutover tests), 34 (director delete).

Final task count: approximately 28 tasks, down from 40. The original numbering is preserved in the bodies below; dropped tasks are marked with "SKIPPED — see AMENDMENTS section A/B/C/D/E above" as a header note at implementation time.

### L. Profile-review pause — route push + two-width chat (2026-04-22, session 3)

Confirmed with CSJ: there are only **two** chat widths, not three.

- **Wide:** `w-[712px]` (= 2 × 356px). Active when onboarding is running AND `onboardingLayout !== 'standard'` (path_choice / journey_choice / base_* capture / asset_capture / add_more).
- **Standard:** `w-[356px]`. Active during profile-review pauses AND all post-onboarding chat.

The PRD/spec's earlier `w-[525px]` and `max-w-4xl` (896px) literals are superseded. They were a spec artefact from the initial brainstorm, not a designed decision. **Do NOT re-introduce 525 or 896 anywhere** — treat them as anti-values.

On entering a profile-review pause the frontend also pushes the Vue Router to `/profile` (existing `UserProfile.vue` at `router/index.js:512`) so the user can cross-reference the director's summary against their real profile surface, not a stale dashboard welcome card. On confirmation the router returns to whichever route they were on before the pause.

**Task 27 amended:** the `onboarding_layout_change` SSE handler in `aiChat.js` remains unchanged (commits mode to Vuex). The new responsibility lives in AppLayout.

**Task 28 DROPPED:** `ProfileReviewPanel.vue` is no longer rendered. `UserProfile.vue` at `/profile` is the review surface (driven by the AppLayout route push) and already shows every captured field. Duplicating the summary inside the 356px chat aside was redundant. The file stays in the repo unused; `FynOnboardingChat.vue` no longer imports it.

**Task 29 amended / new Task 29b:** `AppLayout.vue` now owns three pieces of layout behaviour:

1. Aside width class — 712 wide / 356 standard (computed `asideWidthClass`).
2. Dashboard blur class — blur when wide, un-blur when standard (computed `dashboardBlurClass`).
3. Profile-review route push — watcher on `onboardingLayout`:
   - On `'standard'`: store `this.$route.fullPath` in `preProfileRoute` (component data), `$router.push('/profile')`.
   - On `'wide'`: if `preProfileRoute` is set, `$router.push(preProfileRoute)` then clear.
   - The chat itself lives in a fixed `<aside>` outside `<router-view>`, so route changes don't unmount it. Vuex `aiChat` state persists across the push.

`FynOnboardingChat.vue` — when `docked: true` (how AppLayout mounts it), returns `w-full h-full` and lets the aside own the width. The undocked path uses the same two values (712 / 356).

---

**Goal (amended):** Introduce a post-onboarding Fyn persona split (advice / data_capture) via a new `FynPersonaOrchestrator` that reuses the existing `QueryClassifier` for intent detection, add the missing Will and LPA AI tools against the existing models, and overhaul the onboarding UX inside the existing `OnboardingChatDirector` with profile-review pauses, spouse-skip, multi-job capture, conversational retraction, wide-chat layout, fact parking, and a working resume-from-where-left-off flow. Shipped behind `FYN_PERSONA_SPLIT` and `FYN_CLASSIFIER_FAST_PATH` feature flags. `OnboardingChatDirector` and `OnboardingPromptBuilder` are NOT deleted.

**Architecture (amended):** `AiChatController` adds a third branch: when `$inOnboarding` → `OnboardingChatDirector` (unchanged role, extended with new states); when `$splitEnabled` → new `FynPersonaOrchestrator` (post-onboarding only); default → `CoordinatingAgent::chat()` (today's fallback). Persona handoffs are structured tool calls (`delegate_to_capture`, `capture_complete`) intercepted by the orchestrator and stripped from SSE. Onboarding UX extends the director via new state-machine states (`profile_review_family`, `profile_review_expenditure`, `base_employment_more`), a new `OnboardingFactExtractor` service + parking JSON column, a new action endpoint, and a new `FynOnboardingChat.vue` component.

**Tech Stack:** PHP 8.2, Laravel 10, Pest 2, Vue 3, Vuex 4, Tailwind CSS, MySQL 8.

**Spec:** `docs/superpowers/specs/2026-04-21-fyn-persona-split-design.md` (commit `11d82e5`).

**Branch target:** new feature branch `feature/fyn-persona-split` off `onboardingFyn` (or off `dev` once Gate 1 lands).

**Rule reminders (from CLAUDE.md and memory):**
- No icons on Fyn chat surface. No amber / orange / primary-* / secondary-* tokens. Colors from palette only.
- No hardcoded tax values. Use `TaxConfigService` (PHP) / `taxConfig.js` (JS).
- Never use `migrate:fresh` or `migrate:refresh`. Always `php artisan db:seed` after migrations.
- Never use `npx vite build` directly; use `./deploy/csjones-fynla/build.sh` or `./deploy/fynla-org/build.sh`.
- Every `[x]` checkbox must have a real Playwright / test / command interaction. Reading a diff is not testing.

---

## File structure

### New files

```
app/Services/AI/FynPersonaOrchestrator.php                 — dispatcher (owns state transitions)
app/Services/AI/FynPersonaRegistry.php                     — config-driven persona lookup
app/Services/AI/FynPersonaInvoker.php                      — runs one persona turn end-to-end
app/Services/AI/Prompts/DataCapturePromptBuilder.php       — short capture-focused prompt (post-onboarding only)
app/Services/AI/HandoffContract.php                        — tool name constants + schema validation
app/ValueObjects/CaptureContext.php                        — immutable context passed to capture turns
app/Services/Onboarding/OnboardingFactExtractor.php        — regex-based fact extraction into ai_conversations.onboarding_parked_facts (AMENDMENTS §B)
database/migrations/2026_04_22_000001_add_persona_to_ai_messages.php
database/migrations/2026_04_22_000002_add_persona_state_to_ai_conversations.php
database/migrations/2026_04_22_000003_add_onboarding_parked_facts_to_ai_conversations.php
database/migrations/2026_04_22_000004_add_will_columns.php     — adds residuary_beneficiary, guardian_for_minors, specific_gifts to wills (AMENDMENTS §C)
database/factories/AiConversationFactory.php                — prerequisite (AMENDMENTS §G / Task 4a)
database/factories/AiMessageFactory.php                     — prerequisite (AMENDMENTS §G / Task 4a)
config/fyn.php                                              — persona split + classifier flags
config/fyn_personas.php                                     — persona registry config
tests/Unit/Services/AI/FynPersonaOrchestratorTest.php
tests/Unit/Services/AI/FynPersonaRegistryTest.php
tests/Unit/Services/AI/FynPersonaInvokerTest.php
tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php
tests/Unit/Services/Onboarding/OnboardingFactExtractorTest.php
tests/Unit/ValueObjects/CaptureContextTest.php
tests/Feature/AI/PersonaSplit/KycGateFlowTest.php
tests/Feature/AI/PersonaSplit/InlineCaptureFlowTest.php
tests/Feature/AI/PersonaSplit/CancelMidCaptureTest.php
tests/Feature/AI/PersonaSplit/CaptureTimeoutTest.php
tests/Feature/AI/PersonaSplit/ClassifierFastPathTest.php
tests/Feature/AI/PersonaSplit/PreviewModeTest.php
tests/Feature/AI/PersonaSplit/CreateWillToolTest.php
tests/Feature/AI/PersonaSplit/CreatePowerOfAttorneyToolTest.php
resources/js/components/Fyn/FynOnboardingChat.vue                    — new onboarding chat wrapper (wide/standard layouts)
resources/js/components/Onboarding/ProfileReviewPanel.vue            — read-only profile summary shown at pause points
tests/Feature/Onboarding/ProfileReviewPauseTest.php
tests/Feature/Onboarding/SpouseSkipTest.php
tests/Feature/Onboarding/MultiJobCaptureTest.php
tests/Feature/Onboarding/RetractionTest.php
tests/Feature/Onboarding/OnboardingResumeTest.php
tests/Feature/Onboarding/FactParkingTest.php
```

### Files renamed

```
app/Services/AI/SystemPromptBuilder.php → app/Services/AI/AdvicePromptBuilder.php
```

### Files modified

```
app/Agents/CoordinatingAgent.php                           — use AdvicePromptBuilder; new will/LPA handlers (against existing LastingPowerOfAttorney + LpaAttorney)
app/Traits/HasAiChat.php                                   — use AdvicePromptBuilder
app/Providers/AppServiceProvider.php                        — registry binding; rename binding
app/Services/AI/AiToolDefinitions.php                       — add handoff + will + LPA tools (LPA uses existing model per AMENDMENTS §C)
app/Services/AI/XaiToolDefinitions.php                      — add handoff + will + LPA tools
app/Services/AI/QueryClassifier.php                         — unchanged code; used by orchestrator at dispatch entry (AMENDMENTS §D)
app/Constants/QuerySchemas.php                              — add isAdviceShaped() helper (AMENDMENTS §D)
app/Http/Controllers/Api/AiChatController.php               — 3-way match branch + new action() method (AMENDMENTS §E)
app/Http/Middleware/PreviewWriteInterceptor.php             — already covers api/ai-chat/conversations/* via prefix match; verify EXCLUDED_ROUTES before migration
app/Models/AiMessage.php                                    — add HasFactory + persona fillable + cast (AMENDMENTS §G)
app/Models/AiConversation.php                               — add HasFactory + persona_state + onboarding_parked_facts fillable + casts (AMENDMENTS §G)
app/Models/Estate/Will.php                                  — fillable update for 3 new columns (AMENDMENTS §C)
routes/api.php                                              — POST /api/ai-chat/conversations/{id}/action only (NO LPA resource routes — AMENDMENTS §C)
resources/js/store/modules/aiChat.js                        — personaMode + onboardingLayout state + SSE handlers
resources/js/components/Shared/AiChatPanel.vue              — capturing pill, placeholder swap, record-card row, preview CTA (AMENDMENTS §F — replaces ChatWindow/MessageBubble targets)
resources/js/services/aiChatService.js                      — postAction() method, strip internal tool events, handle new SSE event types
app/Services/AI/Prompts/CoreIdentity.php                     — preview-mode instruction (advice persona: do not emit delegate_to_capture)
app/Services/Onboarding/OnboardingChatDirector.php           — new pause states + spouse-skip + multi-job + retraction + resume (extensions, NOT rewrite — AMENDMENTS §A)
app/Services/Onboarding/OnboardingStateMachine.php           — STATE_PROFILE_REVIEW_FAMILY, STATE_PROFILE_REVIEW_EXPENDITURE, STATE_BASE_EMPLOYMENT_MORE; rename Employed→Full-time, remove Other
config/onboarding.php                                        — bubble config changes (employment)
resources/js/layouts/AppLayout.vue                           — dashboard blur when onboarding chat is in wide mode
```

### Files deleted

None in this release. Per AMENDMENTS §A, `OnboardingChatDirector` and `OnboardingPromptBuilder` both stay. `SystemPromptBuilder` is RENAMED (not deleted) to `AdvicePromptBuilder`.

---

## Phase 1 — Foundation

### Task 1: Create `config/fyn.php` feature flags

**Files:**
- Create: `config/fyn.php`

- [ ] **Step 1: Create the config file**

```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Persona Split
    |--------------------------------------------------------------------------
    | Master switch for the persona-split architecture. When false,
    | AiChatController routes through CoordinatingAgent::chat() exactly
    | as today. When true, AiChatController routes through
    | FynPersonaOrchestrator.
    */
    'persona_split_enabled' => env('FYN_PERSONA_SPLIT', false),

    /*
    |--------------------------------------------------------------------------
    | Classifier Fast-Path
    |--------------------------------------------------------------------------
    | Separate kill switch for the rule-based classifier. Only meaningful
    | when persona_split_enabled is true. Disable to force every turn
    | through advice Fyn first.
    */
    'classifier_fast_path_enabled' => env('FYN_CLASSIFIER_FAST_PATH', true),

    /*
    |--------------------------------------------------------------------------
    | Capture Mode Guardrails
    |--------------------------------------------------------------------------
    */
    'capture_max_turns' => (int) env('FYN_CAPTURE_MAX_TURNS', 6),

    'cancel_patterns' => [
        '/^(stop|cancel|never\s*mind|forget\s*it|nah|skip)\b/i',
    ],
];
```

- [ ] **Step 2: Commit**

```bash
git add config/fyn.php
git commit -m "feat(fyn): add persona-split feature flag config"
```

---

### Task 2: Create `CaptureContext` value object

**Files:**
- Create: `app/ValueObjects/CaptureContext.php`
- Test: `tests/Unit/ValueObjects/CaptureContextTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\ValueObjects\CaptureContext;

it('constructs with required fields', function () {
    $ctx = new CaptureContext(
        reason: 'retirement advice blocked on missing pension data',
        entityTypes: ['dc_pension', 'db_pension'],
    );

    expect($ctx->reason)->toBe('retirement advice blocked on missing pension data')
        ->and($ctx->entityTypes)->toBe(['dc_pension', 'db_pension'])
        ->and($ctx->fieldsNeeded)->toBe([])
        ->and($ctx->pendingAdviceQuestion)->toBeNull()
        ->and($ctx->originatingFocus)->toBeNull();
});

it('is immutable — readonly properties', function () {
    $ctx = new CaptureContext(reason: 'x', entityTypes: ['savings_account']);

    expect(fn () => $ctx->reason = 'y')->toThrow(\Error::class);
});

it('serialises to array', function () {
    $ctx = new CaptureContext(
        reason: 'user requested inline capture',
        entityTypes: ['savings_account'],
        fieldsNeeded: ['current_balance'],
        pendingAdviceQuestion: 'What should I do about my pensions?',
        originatingFocus: 'savings',
    );

    expect($ctx->toArray())->toBe([
        'reason' => 'user requested inline capture',
        'entity_types' => ['savings_account'],
        'fields_needed' => ['current_balance'],
        'pending_advice_question' => 'What should I do about my pensions?',
        'originating_focus' => 'savings',
    ]);
});

it('hydrates from array', function () {
    $ctx = CaptureContext::fromArray([
        'reason' => 'r',
        'entity_types' => ['goal'],
        'fields_needed' => [],
        'pending_advice_question' => null,
        'originating_focus' => null,
    ]);

    expect($ctx->reason)->toBe('r')
        ->and($ctx->entityTypes)->toBe(['goal']);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/ValueObjects/CaptureContextTest.php`
Expected: FAIL with "Class CaptureContext not found".

- [ ] **Step 3: Implement `CaptureContext`**

```php
<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * Immutable context carried from advice Fyn (or the orchestrator's onboarding
 * mode) into a data-capture turn. Tells data-capture Fyn WHY it was invoked
 * and WHAT entity types to ask about.
 */
final class CaptureContext
{
    /**
     * @param  list<string>  $entityTypes
     * @param  list<string>  $fieldsNeeded
     */
    public function __construct(
        public readonly string $reason,
        public readonly array $entityTypes,
        public readonly array $fieldsNeeded = [],
        public readonly ?string $pendingAdviceQuestion = null,
        public readonly ?string $originatingFocus = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            reason: (string) ($data['reason'] ?? ''),
            entityTypes: array_values($data['entity_types'] ?? []),
            fieldsNeeded: array_values($data['fields_needed'] ?? []),
            pendingAdviceQuestion: $data['pending_advice_question'] ?? null,
            originatingFocus: $data['originating_focus'] ?? null,
        );
    }

    /**
     * @return array{reason: string, entity_types: list<string>, fields_needed: list<string>, pending_advice_question: ?string, originating_focus: ?string}
     */
    public function toArray(): array
    {
        return [
            'reason' => $this->reason,
            'entity_types' => $this->entityTypes,
            'fields_needed' => $this->fieldsNeeded,
            'pending_advice_question' => $this->pendingAdviceQuestion,
            'originating_focus' => $this->originatingFocus,
        ];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/ValueObjects/CaptureContextTest.php`
Expected: PASS — 4 tests.

- [ ] **Step 5: Commit**

```bash
git add app/ValueObjects/CaptureContext.php tests/Unit/ValueObjects/CaptureContextTest.php
git commit -m "feat(fyn): add CaptureContext value object for persona handoffs"
```

---

### Task 3: Create `HandoffContract` with tool name constants

**Files:**
- Create: `app/Services/AI/HandoffContract.php`

- [ ] **Step 1: Create the constants class**

No separate test — values are validated via the `FynPersonaRegistry` integrity test in Task 9.

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Centralises the two internal handoff tool names and the cancellation
 * regex pattern list. Constants so typos fail at parse time rather than
 * silently mis-routing at runtime.
 */
final class HandoffContract
{
    public const DELEGATE_TO_CAPTURE = 'delegate_to_capture';
    public const CAPTURE_COMPLETE = 'capture_complete';

    /**
     * @return list<string>
     */
    public static function internalToolNames(): array
    {
        return [self::DELEGATE_TO_CAPTURE, self::CAPTURE_COMPLETE];
    }

    public static function isInternalTool(string $toolName): bool
    {
        return in_array($toolName, self::internalToolNames(), true);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/AI/HandoffContract.php
git commit -m "feat(fyn): add HandoffContract constants for internal tool names"
```

---

## Phase 2 — Persistence

### Task 4: Migration + model updates for `ai_messages.persona`

**Files:**
- Create: `database/migrations/2026_04_22_000001_add_persona_to_ai_messages.php`
- Modify: `app/Models/AiMessage.php`

- [ ] **Step 1: Generate the migration**

Run: `php artisan make:migration add_persona_to_ai_messages --table=ai_messages`

Rename the generated file to `2026_04_22_000001_add_persona_to_ai_messages.php` if needed.

- [ ] **Step 2: Write the migration body**

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
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->enum('persona', ['advice', 'data_capture'])
                ->nullable()
                ->after('content')
                ->comment('Which Fyn persona produced this message. Null for pre-split rows.');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn('persona');
        });
    }
};
```

- [ ] **Step 3: Run the migration**

Run: `php artisan migrate`
Expected: `Migrating: 2026_04_22_000001_add_persona_to_ai_messages` ... `Migrated: ...`.

- [ ] **Step 4: Update `AiMessage` model**

Open `app/Models/AiMessage.php` and add `'persona'` to the `$fillable` array alphabetically. No cast needed (string enum column).

Example search for the existing fillable — it will look like:

```php
protected $fillable = [
    'ai_conversation_id',
    'role',
    'content',
    // ...
];
```

Add `'persona'`:

```php
protected $fillable = [
    'ai_conversation_id',
    'persona',
    'role',
    'content',
    // ... existing fields
];
```

- [ ] **Step 5: Reseed (project rule — always reseed after migrations)**

Run: `php artisan db:seed`
Expected: all seeders complete without errors.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_04_22_000001_add_persona_to_ai_messages.php app/Models/AiMessage.php
git commit -m "feat(fyn): add persona column to ai_messages"
```

---

### Task 5: Migration + model updates for `ai_conversations.persona_state`

**Files:**
- Create: `database/migrations/2026_04_22_000002_add_persona_state_to_ai_conversations.php`
- Modify: `app/Models/AiConversation.php`

- [ ] **Step 1: Create the migration**

Run: `php artisan make:migration add_persona_state_to_ai_conversations --table=ai_conversations`

Rename if needed.

- [ ] **Step 2: Write the migration body**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->json('persona_state')
                ->nullable()
                ->after('metadata')
                ->comment('FynPersonaOrchestrator state: current mode, pending advice question, capture context.');
        });

        // Backfill existing rows with the default state so the orchestrator
        // can read persona_state unconditionally after the flag flips on.
        DB::table('ai_conversations')
            ->whereNull('persona_state')
            ->update([
                'persona_state' => json_encode([
                    'current' => 'advice',
                    'pending_advice_question' => null,
                    'capture_context' => null,
                    'turns_in_capture' => 0,
                ]),
            ]);
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('persona_state');
        });
    }
};
```

- [ ] **Step 3: Run the migration**

Run: `php artisan migrate`
Expected: migration completes; all existing rows now have the default `persona_state`.

- [ ] **Step 4: Update `AiConversation` model**

Open `app/Models/AiConversation.php`. Add `'persona_state'` to `$fillable` and cast to `array`:

```php
protected $fillable = [
    // ... existing fields
    'persona_state',
];

protected $casts = [
    // ... existing casts
    'persona_state' => 'array',
];
```

- [ ] **Step 5: Reseed**

Run: `php artisan db:seed`
Expected: completes.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_04_22_000002_add_persona_state_to_ai_conversations.php app/Models/AiConversation.php
git commit -m "feat(fyn): add persona_state JSON column to ai_conversations"
```

---

## Phase 3 — Prompt builders

### Task 6: Rename `SystemPromptBuilder` → `AdvicePromptBuilder`

**Files:**
- Rename: `app/Services/AI/SystemPromptBuilder.php` → `app/Services/AI/AdvicePromptBuilder.php`
- Modify: every file that references `SystemPromptBuilder` (class name + use statements)
- Modify: `app/Providers/AppServiceProvider.php` if there is an explicit binding

- [ ] **Step 1: Rename the file and class**

Run: `git mv app/Services/AI/SystemPromptBuilder.php app/Services/AI/AdvicePromptBuilder.php`

Open the file. Change `class SystemPromptBuilder` to `class AdvicePromptBuilder`. Leave all methods, logic, and behaviour identical.

- [ ] **Step 2: Find all references**

Run: `grep -rn "SystemPromptBuilder" app/ tests/ config/ --include="*.php"`

Expected: a list of files (likely `app/Agents/CoordinatingAgent.php`, `app/Traits/HasAiChat.php`, possibly `app/Providers/AppServiceProvider.php`, test files).

- [ ] **Step 3: Update each reference**

For each file found in Step 2, replace `SystemPromptBuilder` with `AdvicePromptBuilder` in:
- `use App\Services\AI\SystemPromptBuilder;` → `use App\Services\AI\AdvicePromptBuilder;`
- `SystemPromptBuilder::class` → `AdvicePromptBuilder::class`
- Type hints: `SystemPromptBuilder $builder` → `AdvicePromptBuilder $builder`
- Variable names stay (`$systemPromptBuilder` → `$advicePromptBuilder` if present)

- [ ] **Step 4: Run the full test suite**

Run: `./vendor/bin/pest`
Expected: all tests pass. Any failures are import/symbol-resolution issues from missed references; fix and re-run.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor(fyn): rename SystemPromptBuilder to AdvicePromptBuilder"
```

---

### Task 7: Create `DataCapturePromptBuilder` (byte-compatible with `OnboardingPromptBuilder` for onboarding contexts)

**Files:**
- Create: `app/Services/AI/Prompts/DataCapturePromptBuilder.php`
- Test: `tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php`

- [ ] **Step 1: Write the byte-compat regression test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Prompts\DataCapturePromptBuilder;
use App\Services\Onboarding\OnboardingPromptBuilder;
use App\Services\TaxConfigService;
use App\ValueObjects\CaptureContext;
use App\Models\User;

it('produces the same output as OnboardingPromptBuilder for onboarding-focus contexts', function () {
    $taxConfig = app(TaxConfigService::class);
    $onboardingBuilder = new OnboardingPromptBuilder($taxConfig);
    $captureBuilder = new DataCapturePromptBuilder($taxConfig);

    $user = User::factory()->create(['name' => 'Test User']);

    foreach (['savings', 'investment', 'retirement', 'protection', 'estate', 'business', 'goals', 'budgeting'] as $focus) {
        $expected = $onboardingBuilder->buildAssetCapturePrompt($user, $focus);

        $ctx = new CaptureContext(
            reason: 'onboarding asset capture',
            entityTypes: OnboardingPromptBuilder::toolsForFocus($focus),
            originatingFocus: $focus,
        );
        $actual = $captureBuilder->build($user, $ctx);

        expect($actual)->toBe($expected, "Focus '$focus' output drift");
    }
});

it('produces a post-onboarding prompt when originatingFocus is null', function () {
    $builder = new DataCapturePromptBuilder(app(TaxConfigService::class));
    $user = User::factory()->create(['name' => 'Test User']);

    $ctx = new CaptureContext(
        reason: 'user requested inline capture',
        entityTypes: ['savings_account'],
    );
    $output = $builder->build($user, $ctx);

    expect($output)->toContain('<data_capture_turn>')
        ->and($output)->toContain('create_savings_account')
        ->and($output)->toContain('user requested inline capture');
});
```

- [ ] **Step 2: Run the test — expect failure**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php`
Expected: FAIL, class not found.

- [ ] **Step 3: Implement `DataCapturePromptBuilder`**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

use App\Models\User;
use App\Services\AI\Prompts\ComplianceRules;
use App\Services\AI\Prompts\CoreIdentity;
use App\Services\Onboarding\OnboardingPromptBuilder;
use App\Services\TaxConfigService;
use App\ValueObjects\CaptureContext;

/**
 * Short-form system prompt for data-capture turns. ~500 tokens vs ~1,600
 * for the advice builder. Replaces `OnboardingPromptBuilder` while remaining
 * byte-compatible with it when called with an onboarding-focus
 * `CaptureContext` — a regression guard protecting FR-M14 behaviour during
 * the onboarding migration.
 */
final class DataCapturePromptBuilder
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function build(User $user, CaptureContext $context): string
    {
        // Onboarding-focus path: delegate to the legacy builder byte-for-byte
        // so AssetCaptureOffScriptFilterTest and StateMachineWalkthroughTest
        // keep passing during the migration step.
        if ($context->originatingFocus !== null) {
            return app(OnboardingPromptBuilder::class)
                ->buildAssetCapturePrompt($user, $context->originatingFocus);
        }

        $nameParts = explode(' ', (string) $user->name);
        $firstName = $nameParts[0] !== '' ? $nameParts[0] : 'there';
        $taxYear = $this->taxConfig->getTaxYear() ?? '2025/26';

        $layers = [
            CoreIdentity::get($firstName),
            ComplianceRules::get($taxYear),
            $this->dataCaptureInstructions($context),
        ];

        return implode("\n\n", $layers);
    }

    private function dataCaptureInstructions(CaptureContext $context): string
    {
        $entityList = implode(', ', array_map(fn ($t) => "create_{$t}", $context->entityTypes));
        $fieldsHint = $context->fieldsNeeded !== []
            ? "Fields we need: ".implode(', ', $context->fieldsNeeded).".\n"
            : '';

        return <<<PROMPT
<data_capture_turn>
You are in data-capture mode. Reason: {$context->reason}.

YOUR SINGLE JOB: call the appropriate create_ tool for EACH holding the user
mentions. If the user's message describes N items, emit N tool calls in your
first response. If the user says "I don't have any", reply with one short
sentence and call no tools.

Multi-entity rule: when the user mentions multiple items in a single message,
emit one tool_use block per item in your very first response. Do not
summarise the rest in text and come back for them on the next turn.

Do NOT greet, summarise, ask follow-up questions, navigate, analyse, or
reference any financial figures beyond what the user just provided. Keep
text output to a single short confirmation sentence like
"Got it — recording those now."

Off-script guardrail (FR-M14): Your acknowledgment text MUST be EXACTLY ONE
sentence of 15 words or fewer, or empty. Do NOT ask any question — not with
a question mark, not without one. Do NOT give advice, suggestions, or
analysis. Do NOT reference figures the user did not explicitly state in
THIS message. If the user volunteered information outside the tool list
below, IGNORE it silently.

{$fieldsHint}Tools available to you in this turn:
{$entityList}

When you are done capturing, emit a `capture_complete` tool call with a
short summary and the structured list of records created. Any other tool
call will be ignored.
</data_capture_turn>
PROMPT;
    }
}
```

- [ ] **Step 4: Run the test — expect pass**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php`
Expected: PASS — 2 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/Prompts/DataCapturePromptBuilder.php tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php
git commit -m "feat(fyn): add DataCapturePromptBuilder (byte-compat with OnboardingPromptBuilder)"
```

---

## Phase 4 — Handoff tools

### Task 8: Add `delegate_to_capture` and `capture_complete` tool definitions

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php`
- Modify: `app/Services/AI/XaiToolDefinitions.php`

- [ ] **Step 1: Add handoff tools method to `AiToolDefinitions`**

Locate the last tool method in `AiToolDefinitions.php` (e.g. `profileTools()`). Add after it:

```php
/**
 * Internal handoff tools — stripped from SSE by the orchestrator before
 * reaching the frontend. Only surfaced to the relevant persona by the
 * FynPersonaInvoker; AiToolDefinitions emits the definitions so the LLM
 * knows the schema.
 */
public function handoffTools(): array
{
    return [
        [
            'name' => 'delegate_to_capture',
            'description' => 'Internal. Advice Fyn emits this when it cannot answer without data the user has not supplied, or when the user requests an inline capture. Never shown to the user.',
            'parameters' => [
                'type' => 'object',
                'required' => ['reason', 'entity_types'],
                'properties' => [
                    'reason' => [
                        'type' => 'string',
                        'description' => 'Why capture is needed (e.g. "retirement advice blocked on missing pension data").',
                    ],
                    'entity_types' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'The record types to capture (dc_pension, savings_account, property, etc.).',
                    ],
                    'fields_needed' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Optional. Specific fields required to unblock the advice answer.',
                    ],
                ],
            ],
        ],
        [
            'name' => 'capture_complete',
            'description' => 'Internal. Data-capture Fyn emits this when the capture sub-conversation is done. Never shown to the user.',
            'parameters' => [
                'type' => 'object',
                'required' => ['summary', 'records_created'],
                'properties' => [
                    'summary' => [
                        'type' => 'string',
                        'description' => 'Short user-facing summary (e.g. "Added Scottish Widows SIPP £50k").',
                    ],
                    'records_created' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'required' => ['type', 'id'],
                            'properties' => [
                                'type' => ['type' => 'string'],
                                'id' => ['type' => 'integer'],
                            ],
                        ],
                        'description' => 'Structured list of records created during the capture sub-conversation.',
                    ],
                ],
            ],
        ],
    ];
}
```

Do NOT add `handoffTools()` to the default `getTools()` output — only the invoker should surface these, filtered by persona. Task 14 wires this in.

- [ ] **Step 2: Add the same tools to `XaiToolDefinitions`**

Open `app/Services/AI/XaiToolDefinitions.php`. Locate the last tool definition. Add the xAI-shaped versions:

```php
// Inside the array returned by getTools() or equivalent builder method,
// add two entries — one for each handoff tool. XaiToolDefinitions uses
// the {name, description, parameters} shape directly, so the schema
// content from Step 1 applies as-is with the outer structure:

[
    'name' => 'delegate_to_capture',
    'description' => '... (same text as AiToolDefinitions) ...',
    'parameters' => [/* same schema */],
],
[
    'name' => 'capture_complete',
    'description' => '... (same text) ...',
    'parameters' => [/* same schema */],
],
```

Keep them in a new `handoffTools()` method to match the AiToolDefinitions pattern; only surface via the invoker.

- [ ] **Step 3: Run existing tool-related tests**

Run: `./vendor/bin/pest tests/Unit/Services/AI/ tests/Unit/Agents/`
Expected: all existing tests pass (we have not added the tools to the default list, so nothing changes for callers that use `getTools()`).

- [ ] **Step 4: Commit**

```bash
git add app/Services/AI/AiToolDefinitions.php app/Services/AI/XaiToolDefinitions.php
git commit -m "feat(fyn): add delegate_to_capture and capture_complete tool definitions"
```

---

### Task 9: Create `FynPersonaRegistry` with integrity test

**Files:**
- Create: `config/fyn_personas.php`
- Create: `app/Services/AI/FynPersonaRegistry.php`
- Test: `tests/Unit/Services/AI/FynPersonaRegistryTest.php`

- [ ] **Step 1: Create `config/fyn_personas.php`**

```php
<?php

declare(strict_types=1);

return [
    'advice' => [
        'prompt_builder' => App\Services\AI\AdvicePromptBuilder::class,
        'allowed_tools' => [
            'navigate_to_page',
            'list_goals',
            'list_life_events',
            'get_module_analysis',
            'get_recommendations',
            'get_tax_information',
            'generate_financial_plan',
            'create_what_if_scenario',
        ],
        'handoff_tools' => ['delegate_to_capture'],
    ],
    'data_capture' => [
        'prompt_builder' => App\Services\AI\Prompts\DataCapturePromptBuilder::class,
        'allowed_tools' => [
            // Financial records
            'create_savings_account',
            'create_investment_account',
            'create_holding',
            'create_pension',
            'create_protection_policy',
            'create_property',
            'create_mortgage',
            'create_asset',
            'create_liability',
            'create_estate_gift',
            'create_trust',
            'create_chattel',
            'create_business_interest',
            // Goals and life events
            'create_goal',
            'create_life_event',
            // People
            'create_family_member',
            // Profile details
            'capture_personal_details',
            'capture_spouse_details',
            'capture_dependants',
            'capture_work_details',
            'update_profile',
            // Estate documents — added in Tasks 11–12
            'create_will',
            'update_will',
            'create_power_of_attorney',
            'update_power_of_attorney',
            // Generic updates / deletes
            'update_record',
            'delete_record',
        ],
        'handoff_tools' => ['capture_complete'],
    ],
];
```

- [ ] **Step 2: Write the registry integrity test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\AiToolDefinitions;
use App\Services\AI\FynPersonaRegistry;

it('loads the registry config', function () {
    $registry = app(FynPersonaRegistry::class);

    expect($registry->personas())->toContain('advice', 'data_capture');
});

it('each persona has a valid prompt_builder class', function () {
    $registry = app(FynPersonaRegistry::class);

    foreach ($registry->personas() as $persona) {
        $class = $registry->promptBuilderClass($persona);
        expect(class_exists($class))->toBeTrue("Persona $persona points at non-existent class $class");
    }
});

it('every allowed_tool exists in AiToolDefinitions', function () {
    $registry = app(FynPersonaRegistry::class);
    $known = collect(app(AiToolDefinitions::class)->getTools())
        ->pluck('name')
        ->merge(collect(app(AiToolDefinitions::class)->handoffTools())->pluck('name'))
        ->all();

    foreach ($registry->personas() as $persona) {
        foreach ($registry->allowedTools($persona) as $tool) {
            expect(in_array($tool, $known, true))
                ->toBeTrue("Persona $persona lists unknown tool $tool");
        }
    }
});

it('every handoff_tool is a known internal tool', function () {
    $registry = app(FynPersonaRegistry::class);

    foreach ($registry->personas() as $persona) {
        foreach ($registry->handoffTools($persona) as $tool) {
            expect(\App\Services\AI\HandoffContract::isInternalTool($tool))
                ->toBeTrue("Persona $persona has non-internal handoff tool $tool");
        }
    }
});
```

- [ ] **Step 3: Run the test — expect failure**

Run: `./vendor/bin/pest tests/Unit/Services/AI/FynPersonaRegistryTest.php`
Expected: FAIL, class not found.

- [ ] **Step 4: Implement `FynPersonaRegistry`**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Config;

/**
 * Config-driven lookup for persona definitions. Each persona row has:
 *   - prompt_builder: FQCN of the class that builds that persona's system prompt
 *   - allowed_tools:  whitelist of tool names the LLM may call for this persona
 *   - handoff_tools:  tools whose emission triggers a persona transition
 *
 * Loads from config('fyn_personas'). File-based only; no runtime mutability
 * in this release (see spec open question).
 */
final class FynPersonaRegistry
{
    public const ADVICE = 'advice';
    public const DATA_CAPTURE = 'data_capture';

    /**
     * @return list<string>
     */
    public function personas(): array
    {
        return array_keys(Config::get('fyn_personas', []));
    }

    public function promptBuilderClass(string $persona): string
    {
        return (string) Config::get("fyn_personas.$persona.prompt_builder", '');
    }

    /**
     * @return list<string>
     */
    public function allowedTools(string $persona): array
    {
        return array_values(Config::get("fyn_personas.$persona.allowed_tools", []));
    }

    /**
     * @return list<string>
     */
    public function handoffTools(string $persona): array
    {
        return array_values(Config::get("fyn_personas.$persona.handoff_tools", []));
    }

    public function exists(string $persona): bool
    {
        return Config::has("fyn_personas.$persona");
    }
}
```

- [ ] **Step 5: Run the test — expect pass**

Run: `./vendor/bin/pest tests/Unit/Services/AI/FynPersonaRegistryTest.php`
Expected: PASS — 4 tests.

- [ ] **Step 6: Commit**

```bash
git add config/fyn_personas.php app/Services/AI/FynPersonaRegistry.php tests/Unit/Services/AI/FynPersonaRegistryTest.php
git commit -m "feat(fyn): add FynPersonaRegistry with integrity test"
```

Note: the integrity test for `create_will` / `create_power_of_attorney` etc. will fail until Tasks 10–12 land. Expected. They will all pass at the end of Phase 5.

---

## Phase 5 — New estate tools (Power of Attorney + Will)

### Task 10: `PowerOfAttorney` model, migration, controller, validation, routes

**Files:**
- Create: `database/migrations/2026_04_22_000003_create_power_of_attorneys_table.php`
- Create: `app/Models/Estate/PowerOfAttorney.php`
- Create: `database/factories/PowerOfAttorneyFactory.php`
- Create: `app/Http/Controllers/Api/PowerOfAttorneyController.php`
- Create: `app/Http/Requests/PowerOfAttorney/StoreRequest.php`
- Create: `app/Http/Requests/PowerOfAttorney/UpdateRequest.php`
- Create: `app/Http/Resources/PowerOfAttorneyResource.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Generate the migration**

Run: `php artisan make:migration create_power_of_attorneys_table`

Rename to `2026_04_22_000003_create_power_of_attorneys_table.php`.

- [ ] **Step 2: Write the migration**

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
        Schema::create('power_of_attorneys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['property_and_finance', 'health_and_welfare']);
            $table->string('attorney_name');
            $table->string('replacement_attorney_name')->nullable();
            $table->enum('status', ['draft', 'registered'])->default('draft');
            $table->date('registered_date')->nullable();
            $table->text('restrictions_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('power_of_attorneys');
    }
};
```

- [ ] **Step 3: Run migration and reseed**

Run: `php artisan migrate && php artisan db:seed`
Expected: migration runs; seed completes.

- [ ] **Step 4: Create the model**

```php
<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PowerOfAttorney extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'attorney_name',
        'replacement_attorney_name',
        'status',
        'registered_date',
        'restrictions_notes',
    ];

    protected $casts = [
        'registered_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): \Database\Factories\PowerOfAttorneyFactory
    {
        return \Database\Factories\PowerOfAttorneyFactory::new();
    }
}
```

- [ ] **Step 5: Create the factory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Estate\PowerOfAttorney;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PowerOfAttorneyFactory extends Factory
{
    protected $model = PowerOfAttorney::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['property_and_finance', 'health_and_welfare']),
            'attorney_name' => $this->faker->name(),
            'replacement_attorney_name' => null,
            'status' => 'draft',
            'registered_date' => null,
            'restrictions_notes' => null,
        ];
    }

    public function registered(): self
    {
        return $this->state(fn () => [
            'status' => 'registered',
            'registered_date' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
        ]);
    }
}
```

- [ ] **Step 6: Create Store + Update requests**

`app/Http/Requests/PowerOfAttorney/StoreRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\PowerOfAttorney;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:property_and_finance,health_and_welfare'],
            'attorney_name' => ['required', 'string', 'max:255'],
            'replacement_attorney_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:draft,registered'],
            'registered_date' => ['nullable', 'date'],
            'restrictions_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

`app/Http/Requests/PowerOfAttorney/UpdateRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\PowerOfAttorney;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'in:property_and_finance,health_and_welfare'],
            'attorney_name' => ['sometimes', 'string', 'max:255'],
            'replacement_attorney_name' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:draft,registered'],
            'registered_date' => ['nullable', 'date'],
            'restrictions_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 7: Create the resource**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PowerOfAttorneyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'attorney_name' => $this->attorney_name,
            'replacement_attorney_name' => $this->replacement_attorney_name,
            'status' => $this->status,
            'registered_date' => $this->registered_date?->toIso8601String(),
            'restrictions_notes' => $this->restrictions_notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 8: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PowerOfAttorney\StoreRequest;
use App\Http\Requests\PowerOfAttorney\UpdateRequest;
use App\Http\Resources\PowerOfAttorneyResource;
use App\Models\Estate\PowerOfAttorney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PowerOfAttorneyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = PowerOfAttorney::where('user_id', $request->user()->id)->get();

        return response()->json([
            'success' => true,
            'message' => 'Power of attorney records retrieved.',
            'data' => PowerOfAttorneyResource::collection($items),
        ]);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $poa = PowerOfAttorney::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status' => $request->validated('status', 'draft'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Power of attorney created.',
            'data' => new PowerOfAttorneyResource($poa),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $poa = PowerOfAttorney::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Power of attorney retrieved.',
            'data' => new PowerOfAttorneyResource($poa),
        ]);
    }

    public function update(UpdateRequest $request, int $id): JsonResponse
    {
        $poa = PowerOfAttorney::where('user_id', $request->user()->id)->findOrFail($id);
        $poa->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Power of attorney updated.',
            'data' => new PowerOfAttorneyResource($poa->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $poa = PowerOfAttorney::where('user_id', $request->user()->id)->findOrFail($id);
        $poa->delete();

        return response()->json([
            'success' => true,
            'message' => 'Power of attorney deleted.',
        ]);
    }
}
```

- [ ] **Step 9: Register routes**

Open `routes/api.php`. Inside the `auth:sanctum` group, add:

```php
Route::prefix('power-of-attorneys')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\PowerOfAttorneyController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\PowerOfAttorneyController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\Api\PowerOfAttorneyController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Api\PowerOfAttorneyController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\PowerOfAttorneyController::class, 'destroy']);
});
```

- [ ] **Step 10: Verify routes compile**

Run: `php artisan route:list --path=power-of-attorneys`
Expected: 5 routes listed with the correct HTTP verbs.

- [ ] **Step 11: Reseed**

Run: `php artisan db:seed`
Expected: completes.

- [ ] **Step 12: Commit**

```bash
git add database/migrations/2026_04_22_000003_create_power_of_attorneys_table.php \
    app/Models/Estate/PowerOfAttorney.php \
    database/factories/PowerOfAttorneyFactory.php \
    app/Http/Controllers/Api/PowerOfAttorneyController.php \
    app/Http/Requests/PowerOfAttorney/ \
    app/Http/Resources/PowerOfAttorneyResource.php \
    routes/api.php
git commit -m "feat(estate): add PowerOfAttorney model, controller, routes"
```

---

### Task 11: `create_will` / `update_will` tools + CoordinatingAgent handlers

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php`
- Modify: `app/Services/AI/XaiToolDefinitions.php`
- Modify: `app/Agents/CoordinatingAgent.php`
- Test: `tests/Feature/AI/PersonaSplit/CreateWillToolTest.php`

- [ ] **Step 1: Write the feature test**

```php
<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Estate\Will;
use App\Models\User;

it('create_will stores a Will row with the captured fields', function () {
    $user = User::factory()->create();
    $agent = app(CoordinatingAgent::class);

    $result = $agent->handleToolCall($user, 'create_will', [
        'executor_name' => 'Jane Smith',
        'residuary_beneficiary' => 'Spouse',
        'guardian_for_minors' => 'Aunt Mary',
        'specific_gifts' => 'Rolex to son',
    ]);

    expect($result['success'])->toBeTrue();

    $will = Will::where('user_id', $user->id)->first();
    expect($will)->not->toBeNull()
        ->and($will->executor_name)->toBe('Jane Smith')
        ->and($will->residuary_beneficiary)->toBe('Spouse');
});

it('update_will modifies an existing row without creating a new one', function () {
    $user = User::factory()->create();
    $will = Will::factory()->for($user)->create(['executor_name' => 'Original']);
    $agent = app(CoordinatingAgent::class);

    $agent->handleToolCall($user, 'update_will', [
        'id' => $will->id,
        'executor_name' => 'Updated',
    ]);

    expect(Will::where('user_id', $user->id)->count())->toBe(1)
        ->and($will->fresh()->executor_name)->toBe('Updated');
});
```

Note: this test assumes `CoordinatingAgent::handleToolCall` exists as a dispatch method. If it is named differently in your codebase, adjust to match — the important assertions are on the DB state, not the method name.

- [ ] **Step 2: Add `create_will` and `update_will` to `AiToolDefinitions`**

Add to the appropriate tool method (likely `additionalCreationTools()` or a new `estateDocumentTools()` method):

```php
[
    'name' => 'create_will',
    'description' => 'Record a will for the user. Captures executor, residuary beneficiary, guardian for minors, and specific gifts. For complex multi-beneficiary splits, navigate to /estate/will-builder instead.',
    'parameters' => [
        'type' => 'object',
        'required' => ['executor_name'],
        'properties' => [
            'executor_name' => ['type' => 'string', 'description' => 'Primary executor name'],
            'residuary_beneficiary' => ['type' => 'string', 'description' => 'Who inherits the residuary estate'],
            'guardian_for_minors' => ['type' => 'string', 'description' => 'Optional guardian'],
            'specific_gifts' => ['type' => 'string', 'description' => 'Optional free-text list'],
        ],
    ],
],
[
    'name' => 'update_will',
    'description' => 'Modify an existing will. Requires the will id.',
    'parameters' => [
        'type' => 'object',
        'required' => ['id'],
        'properties' => [
            'id' => ['type' => 'integer'],
            'executor_name' => ['type' => 'string'],
            'residuary_beneficiary' => ['type' => 'string'],
            'guardian_for_minors' => ['type' => 'string'],
            'specific_gifts' => ['type' => 'string'],
        ],
    ],
],
```

- [ ] **Step 3: Mirror the tools in `XaiToolDefinitions`**

Add the same two entries with the xAI-shaped structure.

- [ ] **Step 4: Add the handler methods to `CoordinatingAgent`**

Locate where existing tool handlers live (look for `handleCreateSavingsAccount`, `handleUpdateRecord`, etc.). Add:

```php
private function handleCreateWill(User $user, array $args): array
{
    $will = \App\Models\Estate\Will::create([
        'user_id' => $user->id,
        'executor_name' => $args['executor_name'],
        'residuary_beneficiary' => $args['residuary_beneficiary'] ?? null,
        'guardian_for_minors' => $args['guardian_for_minors'] ?? null,
        'specific_gifts' => $args['specific_gifts'] ?? null,
    ]);

    return $this->response(true, 'Will recorded.', ['id' => $will->id]);
}

private function handleUpdateWill(User $user, array $args): array
{
    $will = \App\Models\Estate\Will::where('user_id', $user->id)
        ->findOrFail($args['id']);
    $will->update(\Illuminate\Support\Arr::except($args, ['id']));

    return $this->response(true, 'Will updated.', ['id' => $will->id]);
}
```

Wire them into the dispatch switch:

```php
match ($toolName) {
    // ... existing cases
    'create_will' => $this->handleCreateWill($user, $args),
    'update_will' => $this->handleUpdateWill($user, $args),
    // ... rest
};
```

- [ ] **Step 5: Check `Will` model has the fillable fields**

Open `app/Models/Estate/Will.php`. Ensure `$fillable` includes `executor_name`, `residuary_beneficiary`, `guardian_for_minors`, `specific_gifts`. Add any missing fields. If the schema does not have these columns, add a migration:

Run: `php artisan make:migration add_executor_and_gifts_to_wills --table=wills`

```php
Schema::table('wills', function (Blueprint $table) {
    if (! Schema::hasColumn('wills', 'executor_name')) {
        $table->string('executor_name')->nullable()->after('user_id');
    }
    if (! Schema::hasColumn('wills', 'residuary_beneficiary')) {
        $table->string('residuary_beneficiary')->nullable();
    }
    if (! Schema::hasColumn('wills', 'guardian_for_minors')) {
        $table->string('guardian_for_minors')->nullable();
    }
    if (! Schema::hasColumn('wills', 'specific_gifts')) {
        $table->text('specific_gifts')->nullable();
    }
});
```

Run: `php artisan migrate && php artisan db:seed`.

- [ ] **Step 6: Run the feature test**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/CreateWillToolTest.php`
Expected: PASS — 2 tests.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(fyn): add create_will and update_will tools with handlers"
```

---

### Task 12: `create_power_of_attorney` / `update_power_of_attorney` tools + handlers

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php`
- Modify: `app/Services/AI/XaiToolDefinitions.php`
- Modify: `app/Agents/CoordinatingAgent.php`
- Test: `tests/Feature/AI/PersonaSplit/CreatePowerOfAttorneyToolTest.php`

- [ ] **Step 1: Write the feature test**

```php
<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Estate\PowerOfAttorney;
use App\Models\User;

it('create_power_of_attorney stores an LPA row', function () {
    $user = User::factory()->create();
    $agent = app(CoordinatingAgent::class);

    $result = $agent->handleToolCall($user, 'create_power_of_attorney', [
        'type' => 'property_and_finance',
        'attorney_name' => 'Sister Sarah',
        'status' => 'registered',
        'registered_date' => '2024-06-15',
    ]);

    expect($result['success'])->toBeTrue();

    $lpa = PowerOfAttorney::where('user_id', $user->id)->first();
    expect($lpa)->not->toBeNull()
        ->and($lpa->type)->toBe('property_and_finance')
        ->and($lpa->attorney_name)->toBe('Sister Sarah')
        ->and($lpa->status)->toBe('registered');
});

it('update_power_of_attorney modifies without creating new', function () {
    $user = User::factory()->create();
    $lpa = PowerOfAttorney::factory()->for($user)->create(['status' => 'draft']);
    $agent = app(CoordinatingAgent::class);

    $agent->handleToolCall($user, 'update_power_of_attorney', [
        'id' => $lpa->id,
        'status' => 'registered',
        'registered_date' => '2025-09-01',
    ]);

    expect(PowerOfAttorney::where('user_id', $user->id)->count())->toBe(1)
        ->and($lpa->fresh()->status)->toBe('registered');
});
```

- [ ] **Step 2: Add the tool definitions to `AiToolDefinitions` and `XaiToolDefinitions`**

```php
[
    'name' => 'create_power_of_attorney',
    'description' => 'Record a Lasting Power of Attorney for the user. LPA types: property_and_finance or health_and_welfare.',
    'parameters' => [
        'type' => 'object',
        'required' => ['type', 'attorney_name'],
        'properties' => [
            'type' => ['type' => 'string', 'enum' => ['property_and_finance', 'health_and_welfare']],
            'attorney_name' => ['type' => 'string'],
            'replacement_attorney_name' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => ['draft', 'registered']],
            'registered_date' => ['type' => 'string', 'format' => 'date'],
            'restrictions_notes' => ['type' => 'string'],
        ],
    ],
],
[
    'name' => 'update_power_of_attorney',
    'description' => 'Modify an existing Lasting Power of Attorney.',
    'parameters' => [
        'type' => 'object',
        'required' => ['id'],
        'properties' => [
            'id' => ['type' => 'integer'],
            'type' => ['type' => 'string', 'enum' => ['property_and_finance', 'health_and_welfare']],
            'attorney_name' => ['type' => 'string'],
            'replacement_attorney_name' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => ['draft', 'registered']],
            'registered_date' => ['type' => 'string', 'format' => 'date'],
            'restrictions_notes' => ['type' => 'string'],
        ],
    ],
],
```

Mirror in `XaiToolDefinitions`.

- [ ] **Step 3: Add handlers to `CoordinatingAgent`**

```php
private function handleCreatePowerOfAttorney(User $user, array $args): array
{
    $lpa = \App\Models\Estate\PowerOfAttorney::create([
        'user_id' => $user->id,
        'type' => $args['type'],
        'attorney_name' => $args['attorney_name'],
        'replacement_attorney_name' => $args['replacement_attorney_name'] ?? null,
        'status' => $args['status'] ?? 'draft',
        'registered_date' => $args['registered_date'] ?? null,
        'restrictions_notes' => $args['restrictions_notes'] ?? null,
    ]);

    return $this->response(true, 'Lasting Power of Attorney recorded.', ['id' => $lpa->id]);
}

private function handleUpdatePowerOfAttorney(User $user, array $args): array
{
    $lpa = \App\Models\Estate\PowerOfAttorney::where('user_id', $user->id)
        ->findOrFail($args['id']);
    $lpa->update(\Illuminate\Support\Arr::except($args, ['id']));

    return $this->response(true, 'Lasting Power of Attorney updated.', ['id' => $lpa->id]);
}
```

Add to the dispatch switch:

```php
'create_power_of_attorney' => $this->handleCreatePowerOfAttorney($user, $args),
'update_power_of_attorney' => $this->handleUpdatePowerOfAttorney($user, $args),
```

- [ ] **Step 4: Run the feature test**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/CreatePowerOfAttorneyToolTest.php`
Expected: PASS — 2 tests.

- [ ] **Step 5: Run the registry integrity test again**

Run: `./vendor/bin/pest tests/Unit/Services/AI/FynPersonaRegistryTest.php`
Expected: PASS — all 4 tests. Every persona tool now exists in the combined `AiToolDefinitions` inventory.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat(fyn): add create/update_power_of_attorney tools and handlers"
```

---

## Phase 6 — Orchestrator core

### Task 13: `FynPersonaInvoker` — runs one persona turn end-to-end

**Files:**
- Create: `app/Services/AI/FynPersonaInvoker.php`
- Test: `tests/Unit/Services/AI/FynPersonaInvokerTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaInvoker;
use App\Services\AI\FynPersonaRegistry;
use App\ValueObjects\CaptureContext;

it('filters the tool list to the persona allowed_tools', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->for($user)->create();

    $invoker = app(FynPersonaInvoker::class);
    $registry = app(FynPersonaRegistry::class);

    $filtered = $invoker->toolsForPersona(FynPersonaRegistry::DATA_CAPTURE);
    $names = collect($filtered)->pluck('name')->all();

    expect($names)->toContain('create_savings_account', 'capture_complete')
        ->not->toContain('navigate_to_page', 'get_module_analysis');
});

it('selects the advice prompt builder for advice persona', function () {
    $user = User::factory()->create();
    $invoker = app(FynPersonaInvoker::class);

    expect($invoker->promptBuilderFor(FynPersonaRegistry::ADVICE))
        ->toBeInstanceOf(\App\Services\AI\AdvicePromptBuilder::class);
});

it('selects the data-capture prompt builder for data_capture persona', function () {
    $invoker = app(FynPersonaInvoker::class);

    expect($invoker->promptBuilderFor(FynPersonaRegistry::DATA_CAPTURE))
        ->toBeInstanceOf(\App\Services\AI\Prompts\DataCapturePromptBuilder::class);
});
```

- [ ] **Step 2: Run test — expect failure**

Run: `./vendor/bin/pest tests/Unit/Services/AI/FynPersonaInvokerTest.php`
Expected: FAIL, class not found.

- [ ] **Step 3: Implement the invoker**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Prompts\DataCapturePromptBuilder;
use App\ValueObjects\CaptureContext;
use Illuminate\Support\Facades\Log;

/**
 * Executes a single persona turn: builds the persona-specific system prompt,
 * filters tools to the persona's allowed + handoff list, invokes the LLM via
 * CoordinatingAgent/HasAiChat, streams tokens out, and parses tool calls.
 *
 * Does NOT decide which persona to use — that's the orchestrator's job.
 */
final class FynPersonaInvoker
{
    public function __construct(
        private readonly FynPersonaRegistry $registry,
        private readonly AiToolDefinitions $toolDefinitions,
        private readonly CoordinatingAgent $coordinatingAgent,
    ) {}

    /**
     * Filter the full tool inventory down to what this persona is allowed
     * to call, plus its handoff tools.
     *
     * @return list<array<string, mixed>>
     */
    public function toolsForPersona(string $persona): array
    {
        $allowed = array_merge(
            $this->registry->allowedTools($persona),
            $this->registry->handoffTools($persona),
        );

        $all = array_merge(
            $this->toolDefinitions->getTools(),
            $this->toolDefinitions->handoffTools(),
        );

        return array_values(array_filter(
            $all,
            fn (array $tool) => in_array($tool['name'], $allowed, true),
        ));
    }

    public function promptBuilderFor(string $persona): object
    {
        $class = $this->registry->promptBuilderClass($persona);

        return app($class);
    }

    /**
     * Invoke a persona for a single turn. Returns a generator of SSE events.
     *
     * For advice persona: the builder is called with the usual arguments
     * (user, classification, kyc, currentRoute, isPreview, orchestrateAnalysis).
     * For data_capture persona: the builder is called with (user, captureContext).
     *
     * @return \Generator<array<string, mixed>>
     */
    public function invoke(
        User $user,
        AiConversation $conversation,
        string $persona,
        string $userMessage,
        ?CaptureContext $captureContext = null,
        ?string $currentRoute = null,
    ): \Generator {
        $builder = $this->promptBuilderFor($persona);
        $tools = $this->toolsForPersona($persona);

        $systemPrompt = $builder instanceof DataCapturePromptBuilder
            ? $builder->build($user, $captureContext ?? new CaptureContext(
                reason: 'data capture',
                entityTypes: [],
            ))
            : $this->coordinatingAgent->buildAdvicePrompt($user, $currentRoute);

        Log::info('[FynPersonaInvoker] invoke', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'persona' => $persona,
            'prompt_length' => strlen($systemPrompt),
            'tool_count' => count($tools),
        ]);

        // Delegate to the coordinating agent's LLM-call primitives. The
        // coordinating agent already knows how to stream chunks and parse
        // tool calls; we pass the overridden prompt + filtered tools.
        yield from $this->coordinatingAgent->chatWithPromptOverride(
            $user,
            $conversation,
            $userMessage,
            $systemPrompt,
            $tools,
        );
    }
}
```

Note: `CoordinatingAgent::chatWithPromptOverride` is referenced in `OnboardingChatDirector` today. If the exact signature differs, align to the existing method — the intent is the same: call the LLM with a custom system prompt and tool list, stream back events.

- [ ] **Step 4: Add `buildAdvicePrompt` helper to `CoordinatingAgent` if not present**

If `CoordinatingAgent` does not already expose a method that builds the advice prompt via `AdvicePromptBuilder`, add a thin wrapper:

```php
public function buildAdvicePrompt(User $user, ?string $currentRoute = null): string
{
    return app(AdvicePromptBuilder::class)->build(
        $user,
        currentRoute: $currentRoute,
        isPreview: $user->is_preview_user ?? false,
        orchestrateAnalysis: fn (int $uid) => $this->orchestrateAnalysis($uid),
    );
}
```

- [ ] **Step 5: Run the test**

Run: `./vendor/bin/pest tests/Unit/Services/AI/FynPersonaInvokerTest.php`
Expected: PASS — 3 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Services/AI/FynPersonaInvoker.php tests/Unit/Services/AI/FynPersonaInvokerTest.php app/Agents/CoordinatingAgent.php
git commit -m "feat(fyn): add FynPersonaInvoker for per-persona turn execution"
```

---

### Task 14: `FynPersonaOrchestrator` — state transitions

**Files:**
- Create: `app/Services/AI/FynPersonaOrchestrator.php`
- Test: `tests/Unit/Services/AI/FynPersonaOrchestratorTest.php`

- [ ] **Step 1: Write unit tests covering every state transition**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;
use App\Services\AI\FynPersonaRegistry;
use App\Services\AI\HandoffContract;

function mockInvokerReturning(array $events): void
{
    $invoker = Mockery::mock(\App\Services\AI\FynPersonaInvoker::class);
    $invoker->shouldReceive('invoke')
        ->andReturnUsing(function () use ($events) {
            foreach ($events as $e) {
                yield $e;
            }
        });
    app()->instance(\App\Services\AI\FynPersonaInvoker::class, $invoker);
}

it('advice → advice when no handoff emitted', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => ['current' => 'advice', 'turns_in_capture' => 0],
    ]);

    mockInvokerReturning([
        ['type' => 'text', 'content' => 'Here is my advice...'],
        ['type' => 'done'],
    ]);

    $events = iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'Advise me')
    );

    $state = $conversation->fresh()->persona_state;
    expect($state['current'])->toBe('advice');
});

it('advice → capturing via delegate_to_capture, persists pending_advice_question', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => ['current' => 'advice', 'turns_in_capture' => 0],
    ]);

    mockInvokerReturning([
        ['type' => 'text', 'content' => 'Let me note that first...'],
        ['type' => 'tool_call', 'name' => HandoffContract::DELEGATE_TO_CAPTURE, 'args' => [
            'reason' => 'need pension',
            'entity_types' => ['dc_pension'],
        ]],
        ['type' => 'done'],
    ]);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'What about my pensions?')
    );

    $state = $conversation->fresh()->persona_state;
    expect($state['current'])->toBe('capturing')
        ->and($state['pending_advice_question'])->toBe('What about my pensions?')
        ->and($state['capture_context']['entity_types'])->toBe(['dc_pension']);
});

it('capturing → advice via capture_complete with pending_advice_question triggers re-invocation', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => [
            'current' => 'capturing',
            'pending_advice_question' => 'What about my pensions?',
            'capture_context' => ['reason' => 'r', 'entity_types' => ['dc_pension']],
            'turns_in_capture' => 1,
        ],
    ]);

    mockInvokerReturning([
        ['type' => 'tool_call', 'name' => HandoffContract::CAPTURE_COMPLETE, 'args' => [
            'summary' => 'Added SIPP',
            'records_created' => [['type' => 'dc_pension', 'id' => 1]],
        ]],
        ['type' => 'done'],
    ]);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'SIPP £50k')
    );

    $state = $conversation->fresh()->persona_state;
    expect($state['current'])->toBe('advice')
        ->and($state['pending_advice_question'])->toBeNull()
        ->and($state['capture_context'])->toBeNull();
});

it('capturing → advice via cancel pattern without invoking data-capture', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => [
            'current' => 'capturing',
            'pending_advice_question' => 'what about my ISAs?',
            'capture_context' => ['reason' => 'r', 'entity_types' => ['savings_account']],
            'turns_in_capture' => 1,
        ],
    ]);

    $invoker = Mockery::mock(\App\Services\AI\FynPersonaInvoker::class);
    $invoker->shouldNotReceive('invoke')->with(Mockery::any(), Mockery::any(), 'data_capture', Mockery::any(), Mockery::any(), Mockery::any());
    $invoker->shouldReceive('invoke')
        ->andReturnUsing(function () {
            yield ['type' => 'text', 'content' => 'No problem.'];
            yield ['type' => 'done'];
        });
    app()->instance(\App\Services\AI\FynPersonaInvoker::class, $invoker);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'never mind')
    );

    expect($conversation->fresh()->persona_state['current'])->toBe('advice');
});

it('capturing → advice via turns_in_capture timeout', function () {
    $user = User::factory()->create();
    config(['fyn.capture_max_turns' => 3]);
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => [
            'current' => 'capturing',
            'pending_advice_question' => 'q',
            'capture_context' => ['reason' => 'r', 'entity_types' => ['savings_account']],
            'turns_in_capture' => 3,
        ],
    ]);

    mockInvokerReturning([
        ['type' => 'text', 'content' => 'Let me come back to what you were asking...'],
        ['type' => 'done'],
    ]);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'another thing')
    );

    expect($conversation->fresh()->persona_state['current'])->toBe('advice');
});

it('malformed delegate_to_capture is ignored and logged', function () {
    \Log::spy();
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => ['current' => 'advice', 'turns_in_capture' => 0],
    ]);

    mockInvokerReturning([
        ['type' => 'tool_call', 'name' => HandoffContract::DELEGATE_TO_CAPTURE, 'args' => [
            // 'reason' missing, 'entity_types' empty → malformed
            'entity_types' => [],
        ]],
        ['type' => 'done'],
    ]);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'advise me')
    );

    expect($conversation->fresh()->persona_state['current'])->toBe('advice');
    \Log::shouldHaveReceived('warning');
});

afterEach(function () {
    Mockery::close();
});
```

- [ ] **Step 2: Run test — expect failure**

Run: `./vendor/bin/pest tests/Unit/Services/AI/FynPersonaOrchestratorTest.php`
Expected: FAIL, class not found.

- [ ] **Step 3: Implement the orchestrator**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\ValueObjects\CaptureContext;
use Illuminate\Support\Facades\Log;

/**
 * Single dispatcher for all post-registration Fyn turns. Reads persona_state
 * from the conversation, decides which persona to invoke, intercepts handoff
 * tool calls, updates state, and loops/returns per transition rules.
 */
final class FynPersonaOrchestrator
{
    public function __construct(
        private readonly FynPersonaRegistry $registry,
        private readonly FynPersonaInvoker $invoker,
        private readonly FynIntentClassifier $classifier,
    ) {}

    /**
     * @return \Generator<array<string, mixed>>
     */
    public function dispatch(
        User $user,
        AiConversation $conversation,
        string $userMessage,
        ?string $currentRoute = null,
        string $mode = 'post_onboarding',
    ): \Generator {
        $state = $this->readState($conversation);

        // 1. Cancel-pattern check while capturing
        if ($state['current'] === 'capturing' && $this->matchesCancel($userMessage)) {
            $this->resetToAdvice($conversation);
            yield from $this->invokeAdvice($user, $conversation, $userMessage, $currentRoute);

            return;
        }

        // 2. Capture timeout
        if ($state['current'] === 'capturing'
            && ($state['turns_in_capture'] ?? 0) >= (int) config('fyn.capture_max_turns', 6)) {
            Log::warning('[FynPersonaOrchestrator] capture timeout', [
                'conversation_id' => $conversation->id,
                'turns' => $state['turns_in_capture'],
            ]);
            $this->resetToAdvice($conversation);
            yield ['type' => 'text', 'content' => "Let me come back to what you were asking — it's easier if you add those details on the page rather than here."];
            yield ['type' => 'done'];

            return;
        }

        // 3. Dispatch to correct persona
        if ($state['current'] === 'capturing') {
            yield from $this->invokeCapture($user, $conversation, $userMessage, $state);

            return;
        }

        // 4. Fast-path classifier (only when not in capturing/onboarding)
        if ($mode === 'post_onboarding'
            && (bool) config('fyn.classifier_fast_path_enabled', true)) {
            $fastPath = $this->classifier->classify($userMessage);
            if ($fastPath !== null) {
                yield from $this->fastPathCapture($user, $conversation, $userMessage, $fastPath);

                return;
            }
        }

        // 5. Default advice
        yield from $this->invokeAdvice($user, $conversation, $userMessage, $currentRoute);
    }

    private function invokeAdvice(
        User $user,
        AiConversation $conversation,
        string $userMessage,
        ?string $currentRoute,
    ): \Generator {
        $acknowledgmentText = '';
        $handoffArgs = null;

        foreach ($this->invoker->invoke($user, $conversation, FynPersonaRegistry::ADVICE, $userMessage, null, $currentRoute) as $event) {
            if ($this->isInternalToolCall($event)) {
                if (($event['name'] ?? '') === HandoffContract::DELEGATE_TO_CAPTURE) {
                    $handoffArgs = $event['args'] ?? [];
                }
                continue; // strip from SSE
            }
            if (($event['type'] ?? '') === 'text') {
                $acknowledgmentText .= $event['content'] ?? '';
            }
            yield $event;
        }

        $this->persistMessage($conversation, FynPersonaRegistry::ADVICE, $acknowledgmentText);

        if ($handoffArgs !== null) {
            if (! $this->isValidDelegate($handoffArgs)) {
                Log::warning('[FynPersonaOrchestrator] malformed delegate_to_capture', ['args' => $handoffArgs]);

                return;
            }
            $this->enterCapturing($conversation, $userMessage, $handoffArgs);
            yield from $this->invokeCapture($user, $conversation->fresh(), '', $this->readState($conversation->fresh()));
        }
    }

    private function invokeCapture(
        User $user,
        AiConversation $conversation,
        string $userMessage,
        array $state,
    ): \Generator {
        $captureContext = CaptureContext::fromArray($state['capture_context'] ?? []);
        $captureSummary = '';
        $captureComplete = null;

        foreach ($this->invoker->invoke(
            $user,
            $conversation,
            FynPersonaRegistry::DATA_CAPTURE,
            $userMessage,
            $captureContext,
        ) as $event) {
            if ($this->isInternalToolCall($event) && ($event['name'] ?? '') === HandoffContract::CAPTURE_COMPLETE) {
                $captureComplete = $event['args'] ?? [];
                continue;
            }
            if (($event['type'] ?? '') === 'text') {
                $captureSummary .= $event['content'] ?? '';
            }
            // Suppress summary when resuming an advice question — the next advice
            // turn implicitly confirms the capture.
            if ($captureComplete !== null && ! empty($state['pending_advice_question'])) {
                continue;
            }
            yield $event;
        }

        $this->persistMessage($conversation, FynPersonaRegistry::DATA_CAPTURE, $captureSummary);
        $this->incrementCaptureTurn($conversation);

        if ($captureComplete !== null) {
            $pending = $state['pending_advice_question'];
            $this->resetToAdvice($conversation);

            if ($pending !== null && $pending !== '') {
                $resumedMessage = "The user's original question was: \"$pending\". Data-capture has just recorded: {$captureComplete['summary']}. Now answer the original question using the updated records.";
                yield from $this->invokeAdvice($user, $conversation->fresh(), $resumedMessage, null);
            } else {
                yield ['type' => 'text', 'content' => $captureComplete['summary'] ?? ''];
                yield ['type' => 'done'];
            }
        }
    }

    private function fastPathCapture(
        User $user,
        AiConversation $conversation,
        string $userMessage,
        array $inferred,
    ): \Generator {
        $ctx = new CaptureContext(
            reason: 'classifier fast-path',
            entityTypes: $inferred['entity_types'],
        );
        $this->enterCapturing($conversation, $userMessage, $ctx->toArray());
        yield from $this->invokeCapture($user, $conversation->fresh(), $userMessage, $this->readState($conversation->fresh()));
    }

    private function readState(AiConversation $conversation): array
    {
        return $conversation->persona_state ?? [
            'current' => 'advice',
            'pending_advice_question' => null,
            'capture_context' => null,
            'turns_in_capture' => 0,
        ];
    }

    private function enterCapturing(AiConversation $conversation, string $originalMessage, array $handoffArgs): void
    {
        $ctx = [
            'reason' => $handoffArgs['reason'] ?? 'handoff',
            'entity_types' => $handoffArgs['entity_types'] ?? [],
            'fields_needed' => $handoffArgs['fields_needed'] ?? [],
            'originating_focus' => null,
        ];
        $conversation->update([
            'persona_state' => [
                'current' => 'capturing',
                'pending_advice_question' => $originalMessage,
                'capture_context' => $ctx,
                'turns_in_capture' => 0,
            ],
        ]);
    }

    private function incrementCaptureTurn(AiConversation $conversation): void
    {
        $state = $this->readState($conversation);
        if ($state['current'] !== 'capturing') {
            return;
        }
        $state['turns_in_capture'] = ($state['turns_in_capture'] ?? 0) + 1;
        $conversation->update(['persona_state' => $state]);
    }

    private function resetToAdvice(AiConversation $conversation): void
    {
        $conversation->update([
            'persona_state' => [
                'current' => 'advice',
                'pending_advice_question' => null,
                'capture_context' => null,
                'turns_in_capture' => 0,
            ],
        ]);
    }

    private function isInternalToolCall(array $event): bool
    {
        return ($event['type'] ?? '') === 'tool_call'
            && HandoffContract::isInternalTool($event['name'] ?? '');
    }

    private function isValidDelegate(array $args): bool
    {
        return ! empty($args['reason'])
            && ! empty($args['entity_types'])
            && is_array($args['entity_types']);
    }

    private function matchesCancel(string $message): bool
    {
        $patterns = (array) config('fyn.cancel_patterns', []);
        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    private function persistMessage(AiConversation $conversation, string $persona, string $content): void
    {
        if ($content === '') {
            return;
        }
        AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $content,
            'persona' => $persona,
        ]);
    }
}
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/pest tests/Unit/Services/AI/FynPersonaOrchestratorTest.php`
Expected: PASS — 6 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/FynPersonaOrchestrator.php tests/Unit/Services/AI/FynPersonaOrchestratorTest.php
git commit -m "feat(fyn): add FynPersonaOrchestrator with state transitions"
```

Note: the orchestrator references `FynIntentClassifier` which doesn't exist yet. The tests don't exercise the fast-path (mode is set to post_onboarding but `classify()` returns null). Create a minimal stub classifier in Task 16 to satisfy the DI wiring; a failing classifier unit test will drive the real implementation.

---

## Phase 7 — Classifier fast-path

### Task 15: Minimal `FynIntentClassifier` stub so orchestrator DI resolves

**Files:**
- Create: `app/Services/AI/FynIntentClassifier.php` (minimal; full rules in Task 16)

- [ ] **Step 1: Create the stub**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

final class FynIntentClassifier
{
    /**
     * Classify a user message. Returns null when no confident match, or
     * ['entity_types' => list<string>] when a fast-path applies.
     *
     * Full rules added in the next task.
     */
    public function classify(string $message): ?array
    {
        return null;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/AI/FynIntentClassifier.php
git commit -m "feat(fyn): stub FynIntentClassifier (full rules next task)"
```

---

### Task 16: `FynIntentClassifier` full rule set + unit tests

**Files:**
- Modify: `app/Services/AI/FynIntentClassifier.php`
- Test: `tests/Unit/Services/AI/FynIntentClassifierTest.php`

- [ ] **Step 1: Write unit tests for each rule and boundary case**

```php
<?php

declare(strict_types=1);

use App\Services\AI\FynIntentClassifier;

$cases = [
    // Confident matches
    ['add my Nationwide cash ISA — £5,000', 'savings_account'],
    ['create a goal called "Holiday Fund" for £3,000', 'goal'],
    ['record my Scottish Widows SIPP worth £50k', 'dc_pension'],
    ['update my mortgage balance to £180,000', 'mortgage'],
    ['delete my closed Halifax account', 'savings_account'],

    // Non-confident: advice-shaped
    ['Should I add an ISA?', null],
    ['What about my pensions?', null],
    ['Can you explain how SIPPs work?', null],
    ['Am I on track for retirement?', null],
    ['Why is my ISA allowance £20,000?', null],
];

it('classifies confident data-entry messages', function () use ($cases) {
    $c = app(FynIntentClassifier::class);
    foreach ($cases as [$message, $expectedType]) {
        $result = $c->classify($message);
        if ($expectedType === null) {
            expect($result)->toBeNull("Expected null for '$message'");
        } else {
            expect($result)->not->toBeNull("Expected match for '$message'")
                ->and($result['entity_types'])->toContain($expectedType);
        }
    }
});

it('rejects messages longer than 40 words even if they start with add', function () {
    $c = app(FynIntentClassifier::class);
    $long = 'add my Nationwide cash ISA ' . str_repeat('plus some other context ', 10);
    expect(str_word_count($long))->toBeGreaterThan(40);
    expect($c->classify($long))->toBeNull();
});

it('requires a verb at the start of the message', function () {
    $c = app(FynIntentClassifier::class);
    expect($c->classify('my Nationwide ISA is worth £5k'))->toBeNull();
});

it('requires at least one known entity keyword', function () {
    $c = app(FynIntentClassifier::class);
    expect($c->classify('add something to my account'))->toBeNull();
});
```

- [ ] **Step 2: Run the test — expect failure**

Run: `./vendor/bin/pest tests/Unit/Services/AI/FynIntentClassifierTest.php`
Expected: FAIL on most cases (stub always returns null, passing null cases but failing confident ones).

- [ ] **Step 3: Implement the full rule set**

Replace the stub with:

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Rule-based (no LLM) classifier for unambiguous data-entry intent. Returns
 * a fast-path hint when the message passes all four gates:
 *   1. starts with a write-shaped verb
 *   2. contains a known entity keyword
 *   3. contains no advice-shaped phrase
 *   4. is ≤ 40 words
 */
final class FynIntentClassifier
{
    private const VERB_PATTERN = '/^(add|create|record|save|log|put in|note( down)?|enter|update|change|edit|delete|remove)\b/i';

    /**
     * Map from keyword (case-insensitive substring) to entity_type.
     *
     * @var array<string, string>
     */
    private const ENTITY_KEYWORDS = [
        'isa' => 'savings_account',
        'cash isa' => 'savings_account',
        'savings account' => 'savings_account',
        'savings' => 'savings_account',
        'nationwide' => 'savings_account',
        'sipp' => 'dc_pension',
        'defined contribution' => 'dc_pension',
        'defined benefit' => 'db_pension',
        'db pension' => 'db_pension',
        'dc pension' => 'dc_pension',
        'pension' => 'dc_pension',
        'mortgage' => 'mortgage',
        'property' => 'property',
        'house' => 'property',
        'flat' => 'property',
        'goal' => 'goal',
        'target' => 'goal',
        'life event' => 'life_event',
        'event' => 'life_event',
        'trust' => 'trust',
        'will' => 'will',
        'power of attorney' => 'power_of_attorney',
        'lpa' => 'power_of_attorney',
        'investment' => 'investment_account',
        'investment account' => 'investment_account',
        'gia' => 'investment_account',
        'business' => 'business_interest',
        'chattel' => 'chattel',
        'gift' => 'estate_gift',
        'liability' => 'liability',
        'debt' => 'liability',
        'loan' => 'liability',
        'family member' => 'family_member',
        'child' => 'family_member',
        'son' => 'family_member',
        'daughter' => 'family_member',
        'spouse' => 'family_member',
        'account' => 'savings_account',
        'policy' => 'protection_policy',
        'life insurance' => 'protection_policy',
        'critical illness' => 'protection_policy',
        'income protection' => 'protection_policy',
    ];

    private const ADVICE_PHRASES = [
        'should i',
        'what about',
        'how much',
        'am i',
        'can you explain',
        'why',
        'recommend',
        'advice',
        'compare',
        'projection',
        'forecast',
        'how does',
        'what is',
        'tell me about',
    ];

    private const MAX_WORDS = 40;

    public function classify(string $message): ?array
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return null;
        }

        if (str_word_count($trimmed) > self::MAX_WORDS) {
            return null;
        }

        if (! preg_match(self::VERB_PATTERN, $trimmed)) {
            return null;
        }

        $lower = mb_strtolower($trimmed);
        foreach (self::ADVICE_PHRASES as $phrase) {
            if (str_contains($lower, $phrase)) {
                return null;
            }
        }

        $types = [];
        foreach (self::ENTITY_KEYWORDS as $keyword => $type) {
            if (str_contains($lower, $keyword)) {
                $types[] = $type;
            }
        }

        $types = array_values(array_unique($types));

        if ($types === []) {
            return null;
        }

        return ['entity_types' => $types];
    }
}
```

- [ ] **Step 4: Run the test**

Run: `./vendor/bin/pest tests/Unit/Services/AI/FynIntentClassifierTest.php`
Expected: PASS — 4 test groups with many assertions.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/FynIntentClassifier.php tests/Unit/Services/AI/FynIntentClassifierTest.php
git commit -m "feat(fyn): add rule-based FynIntentClassifier fast-path"
```

---

### Task 17: Integration test — classifier fast-path bypasses advice Fyn

**Files:**
- Test: `tests/Feature/AI/PersonaSplit/ClassifierFastPathTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;
use App\Services\AI\FynPersonaRegistry;

it('fast-paths confident data-entry messages to data_capture', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => ['current' => 'advice', 'turns_in_capture' => 0],
    ]);

    $invoker = Mockery::mock(\App\Services\AI\FynPersonaInvoker::class);
    $invokedPersonas = [];
    $invoker->shouldReceive('invoke')
        ->andReturnUsing(function ($u, $c, $persona) use (&$invokedPersonas) {
            $invokedPersonas[] = $persona;
            yield ['type' => 'tool_call', 'name' => 'capture_complete', 'args' => [
                'summary' => 'Added ISA',
                'records_created' => [['type' => 'savings_account', 'id' => 1]],
            ]];
            yield ['type' => 'done'];
        });
    app()->instance(\App\Services\AI\FynPersonaInvoker::class, $invoker);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'add my Nationwide cash ISA £5,000')
    );

    expect($invokedPersonas)->toBe([FynPersonaRegistry::DATA_CAPTURE]);
});

it('does not fast-path advice-shaped messages', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => ['current' => 'advice', 'turns_in_capture' => 0],
    ]);

    $invoker = Mockery::mock(\App\Services\AI\FynPersonaInvoker::class);
    $invokedPersonas = [];
    $invoker->shouldReceive('invoke')
        ->andReturnUsing(function ($u, $c, $persona) use (&$invokedPersonas) {
            $invokedPersonas[] = $persona;
            yield ['type' => 'text', 'content' => 'Here is my advice'];
            yield ['type' => 'done'];
        });
    app()->instance(\App\Services\AI\FynPersonaInvoker::class, $invoker);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'Should I add more to my ISA?')
    );

    expect($invokedPersonas)->toBe([FynPersonaRegistry::ADVICE]);
});

it('respects classifier_fast_path_enabled kill switch', function () {
    config(['fyn.classifier_fast_path_enabled' => false]);
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => ['current' => 'advice', 'turns_in_capture' => 0],
    ]);

    $invoker = Mockery::mock(\App\Services\AI\FynPersonaInvoker::class);
    $invokedPersonas = [];
    $invoker->shouldReceive('invoke')
        ->andReturnUsing(function ($u, $c, $persona) use (&$invokedPersonas) {
            $invokedPersonas[] = $persona;
            yield ['type' => 'text', 'content' => 'Ack'];
            yield ['type' => 'done'];
        });
    app()->instance(\App\Services\AI\FynPersonaInvoker::class, $invoker);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'add my Nationwide cash ISA £5,000')
    );

    expect($invokedPersonas[0])->toBe(FynPersonaRegistry::ADVICE);
});

afterEach(fn () => Mockery::close());
```

- [ ] **Step 2: Run the tests**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/ClassifierFastPathTest.php`
Expected: PASS — 3 tests.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/AI/PersonaSplit/ClassifierFastPathTest.php
git commit -m "test(fyn): classifier fast-path integration tests"
```

---

## Phase 8 — Controller wiring

### Task 18: Wire `AiChatController` through feature-flag match branch

**Files:**
- Modify: `app/Http/Controllers/Api/AiChatController.php`

- [ ] **Step 1: Read current controller routing branch**

Open `app/Http/Controllers/Api/AiChatController.php` around line 145–165. Confirm the existing `$inOnboarding` logic matches the spec's reference.

- [ ] **Step 2: Modify the chat() method to add the orchestrator branch**

Replace the existing ternary or if-statement that picks between `$this->onboardingDirector->handleUserMessage(...)` and `$this->coordinatingAgent->chat(...)` with:

```php
use App\Services\AI\FynPersonaOrchestrator;

// inside __construct:
public function __construct(
    private readonly CoordinatingAgent $coordinatingAgent,
    private readonly OnboardingChatDirector $onboardingDirector,
    private readonly FynPersonaOrchestrator $orchestrator,
) {}

// inside chat():
$inOnboarding = $user->onboarding_completed === false
    && $user->onboarding_fyn_step !== null
    && (bool) config('onboarding.fyn_flow_enabled', true);

$splitEnabled = (bool) config('fyn.persona_split_enabled', false);

return new StreamedResponse(function () use ($user, $conversation, $message, $currentRoute, $inOnboarding, $splitEnabled) {
    try {
        $generator = match (true) {
            $inOnboarding && $splitEnabled
                => $this->orchestrator->dispatch($user, $conversation, $message, $currentRoute, mode: 'onboarding'),
            $inOnboarding
                => $this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute),
            $splitEnabled
                => $this->orchestrator->dispatch($user, $conversation, $message, $currentRoute),
            default
                => $this->coordinatingAgent->chat($user, $conversation, $message, $currentRoute),
        };

        foreach ($generator as $event) {
            echo 'data: '.json_encode($event)."\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }
    } catch (\Exception $e) {
        Log::error('[AiChatController] Streaming error', ['user_id' => $user->id, 'error' => $e->getMessage()]);
    }
});
```

- [ ] **Step 3: Run the full AI chat test suite**

Run: `./vendor/bin/pest tests/Feature/AI/ tests/Feature/Onboarding/ tests/Unit/Services/AI/ tests/Unit/Services/Onboarding/`
Expected: all pass. Existing behaviour is preserved because `fyn.persona_split_enabled` defaults to `false`.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/AiChatController.php
git commit -m "feat(fyn): wire AiChatController through feature-flagged orchestrator branch"
```

---

### Task 19: End-to-end KYC-gate integration test

**Files:**
- Test: `tests/Feature/AI/PersonaSplit/KycGateFlowTest.php`

- [ ] **Step 1: Write the full-flow test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\DCPension;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;

it('advice → delegate → capture → advice (re-invocation) produces 3 persona-tagged messages and creates DCPension', function () {
    config(['fyn.persona_split_enabled' => true]);

    $user = User::factory()->create(['onboarding_completed' => true]);
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => ['current' => 'advice', 'turns_in_capture' => 0],
    ]);

    // Stage the invoker to emit the three scripted turns in sequence:
    // 1. advice emits ack + delegate_to_capture
    // 2. data-capture emits create_pension tool call + capture_complete
    // 3. advice (resumed) emits the final answer
    $invoker = Mockery::mock(\App\Services\AI\FynPersonaInvoker::class);
    $call = 0;
    $invoker->shouldReceive('invoke')
        ->andReturnUsing(function ($user, $conv, $persona, $msg, $ctx = null) use (&$call) {
            $call++;
            if ($call === 1) {
                yield ['type' => 'text', 'content' => 'Let me note your pension details first.'];
                yield ['type' => 'tool_call', 'name' => 'delegate_to_capture', 'args' => [
                    'reason' => 'need pension',
                    'entity_types' => ['dc_pension'],
                ]];
                yield ['type' => 'done'];
            } elseif ($call === 2) {
                // data_capture turn
                \App\Models\DCPension::create([
                    'user_id' => $user->id,
                    'scheme_name' => 'Scottish Widows SIPP',
                    'pension_type' => 'dc',
                    'current_fund_value' => 50000,
                ]);
                yield ['type' => 'tool_call', 'name' => 'capture_complete', 'args' => [
                    'summary' => 'Added Scottish Widows SIPP £50k',
                    'records_created' => [['type' => 'dc_pension', 'id' => 1]],
                ]];
                yield ['type' => 'done'];
            } else {
                yield ['type' => 'text', 'content' => 'With your SIPP on record, consolidation could save fees.'];
                yield ['type' => 'done'];
            }
        });
    app()->instance(\App\Services\AI\FynPersonaInvoker::class, $invoker);

    $orch = app(FynPersonaOrchestrator::class);

    iterator_to_array($orch->dispatch($user, $conversation, 'What should I do about my pensions?'));
    iterator_to_array($orch->dispatch($user, $conversation->fresh(), 'Scottish Widows SIPP £50k DC'));

    $messages = AiMessage::where('ai_conversation_id', $conversation->id)
        ->where('role', 'assistant')
        ->orderBy('id')
        ->get();

    expect($messages->pluck('persona')->all())->toBe(['advice', 'data_capture', 'advice']);
    expect(DCPension::where('user_id', $user->id)->count())->toBe(1);
    expect($conversation->fresh()->persona_state['current'])->toBe('advice');
    expect($conversation->fresh()->persona_state['pending_advice_question'])->toBeNull();
});

afterEach(fn () => Mockery::close());
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/KycGateFlowTest.php`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/AI/PersonaSplit/KycGateFlowTest.php
git commit -m "test(fyn): end-to-end KYC-gate flow"
```

---

### Task 20: Inline-capture, cancel, and timeout feature tests

**Files:**
- Test: `tests/Feature/AI/PersonaSplit/InlineCaptureFlowTest.php`
- Test: `tests/Feature/AI/PersonaSplit/CancelMidCaptureTest.php`
- Test: `tests/Feature/AI/PersonaSplit/CaptureTimeoutTest.php`

- [ ] **Step 1: Write `InlineCaptureFlowTest`**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;

it('mid-advice inline capture returns to advice without re-invocation', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => ['current' => 'advice', 'turns_in_capture' => 0],
    ]);

    $invoker = Mockery::mock(\App\Services\AI\FynPersonaInvoker::class);
    $invocations = 0;
    $invoker->shouldReceive('invoke')
        ->andReturnUsing(function ($u, $c, $persona) use (&$invocations) {
            $invocations++;
            if ($invocations === 1) {
                yield ['type' => 'text', 'content' => "I'll add that now."];
                yield ['type' => 'tool_call', 'name' => 'delegate_to_capture', 'args' => [
                    'reason' => 'inline',
                    'entity_types' => ['savings_account'],
                ]];
                yield ['type' => 'done'];
            } else {
                yield ['type' => 'tool_call', 'name' => 'capture_complete', 'args' => [
                    'summary' => 'Added ISA',
                    'records_created' => [['type' => 'savings_account', 'id' => 1]],
                ]];
                yield ['type' => 'done'];
            }
        });
    app()->instance(\App\Services\AI\FynPersonaInvoker::class, $invoker);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'add my Nationwide cash ISA £5,000')
    );

    // Only 2 invocations: advice (delegate) + capture (complete). No resumed advice.
    expect($invocations)->toBe(2);
    expect($conversation->fresh()->persona_state['current'])->toBe('advice');
});

afterEach(fn () => Mockery::close());
```

Wait — in the inline capture flow, the user's message starts with "add my Nationwide" which the classifier WILL fast-path. This test should bypass the classifier to test the delegate path specifically. Amend:

```php
it('mid-advice inline capture returns to advice without re-invocation', function () {
    config(['fyn.classifier_fast_path_enabled' => false]);
    // ... rest of test
});
```

- [ ] **Step 2: Write `CancelMidCaptureTest`**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;
use App\Services\AI\FynPersonaRegistry;

it('cancel pattern in capturing mode flips back to advice without data-capture invocation', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => [
            'current' => 'capturing',
            'pending_advice_question' => 'what about my ISAs?',
            'capture_context' => ['reason' => 'r', 'entity_types' => ['savings_account']],
            'turns_in_capture' => 1,
        ],
    ]);

    $invoker = Mockery::mock(\App\Services\AI\FynPersonaInvoker::class);
    $invokedPersonas = [];
    $invoker->shouldReceive('invoke')
        ->andReturnUsing(function ($u, $c, $persona) use (&$invokedPersonas) {
            $invokedPersonas[] = $persona;
            yield ['type' => 'text', 'content' => 'No problem.'];
            yield ['type' => 'done'];
        });
    app()->instance(\App\Services\AI\FynPersonaInvoker::class, $invoker);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'never mind')
    );

    expect($invokedPersonas)->toBe([FynPersonaRegistry::ADVICE]);
    expect($conversation->fresh()->persona_state['current'])->toBe('advice');
});

afterEach(fn () => Mockery::close());
```

- [ ] **Step 3: Write `CaptureTimeoutTest`**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;

it('force-flips to advice after turns_in_capture exceeds the limit', function () {
    config(['fyn.capture_max_turns' => 3]);
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => [
            'current' => 'capturing',
            'pending_advice_question' => 'q',
            'capture_context' => ['reason' => 'r', 'entity_types' => ['savings_account']],
            'turns_in_capture' => 3,
        ],
    ]);

    $invoker = Mockery::mock(\App\Services\AI\FynPersonaInvoker::class);
    $invoker->shouldNotReceive('invoke');
    app()->instance(\App\Services\AI\FynPersonaInvoker::class, $invoker);

    $events = iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'still trying')
    );

    expect($conversation->fresh()->persona_state['current'])->toBe('advice');
    expect(collect($events)->pluck('content')->filter()->implode(' '))
        ->toContain("Let me come back");
});

afterEach(fn () => Mockery::close());
```

- [ ] **Step 4: Run all three tests**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/`
Expected: PASS — all tests in the PersonaSplit directory.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/AI/PersonaSplit/InlineCaptureFlowTest.php tests/Feature/AI/PersonaSplit/CancelMidCaptureTest.php tests/Feature/AI/PersonaSplit/CaptureTimeoutTest.php
git commit -m "test(fyn): inline-capture, cancel, and timeout flows"
```

---

## Phase 9 — Onboarding migration

### Task 21: Orchestrator `onboarding` mode — state machine delegation + asset-capture dispatch

**Files:**
- Modify: `app/Services/AI/FynPersonaOrchestrator.php`
- Test: `tests/Feature/AI/PersonaSplit/OnboardingOrchestratorTest.php`

- [ ] **Step 1: Write the test first**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;
use App\Services\AI\FynPersonaRegistry;
use App\ValueObjects\CaptureContext;

it('orchestrator in onboarding mode dispatches asset_capture to data-capture persona with originating_focus', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'asset_capture',
        'onboarding_fyn_selection' => 'savings',
    ]);
    $conversation = AiConversation::factory()->for($user)->create([
        'persona_state' => ['current' => 'advice', 'turns_in_capture' => 0],
    ]);

    $invoker = Mockery::mock(\App\Services\AI\FynPersonaInvoker::class);
    $capturedContext = null;
    $invoker->shouldReceive('invoke')
        ->andReturnUsing(function ($u, $c, $persona, $msg, $ctx = null) use (&$capturedContext) {
            if ($persona === FynPersonaRegistry::DATA_CAPTURE) {
                $capturedContext = $ctx;
            }
            yield ['type' => 'text', 'content' => 'ok'];
            yield ['type' => 'done'];
        });
    app()->instance(\App\Services\AI\FynPersonaInvoker::class, $invoker);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'I have a Nationwide ISA £5k', null, 'onboarding')
    );

    expect($capturedContext)->toBeInstanceOf(CaptureContext::class)
        ->and($capturedContext->originatingFocus)->toBe('savings');
});

afterEach(fn () => Mockery::close());
```

- [ ] **Step 2: Run — expect failure**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/OnboardingOrchestratorTest.php`
Expected: FAIL (orchestrator does not yet handle onboarding mode).

- [ ] **Step 3: Add onboarding mode handling to the orchestrator**

In `FynPersonaOrchestrator::dispatch()`, before the existing state checks, insert:

```php
if ($mode === 'onboarding') {
    yield from $this->dispatchOnboarding($user, $conversation, $userMessage);
    return;
}
```

Add the method:

```php
use App\Services\Onboarding\OnboardingStateMachine;

private function dispatchOnboarding(
    User $user,
    AiConversation $conversation,
    string $userMessage,
): \Generator {
    $step = $user->onboarding_fyn_step;

    if ($step === 'asset_capture') {
        $focus = $user->onboarding_fyn_selection ?? 'savings';
        $ctx = new CaptureContext(
            reason: 'onboarding asset capture',
            entityTypes: [],
            originatingFocus: $focus,
        );

        yield from $this->invoker->invoke(
            $user,
            $conversation,
            FynPersonaRegistry::DATA_CAPTURE,
            $userMessage,
            $ctx,
        );

        return;
    }

    // Non-capture onboarding steps: fall through to the existing state
    // machine. This is a temporary bridge — Task 24 removes the director
    // and absorbs the state machine fully.
    $director = app(\App\Services\Onboarding\OnboardingChatDirector::class);
    yield from $director->handleUserMessage($user, $conversation, $userMessage, null);
}
```

- [ ] **Step 4: Run the test**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/OnboardingOrchestratorTest.php`
Expected: PASS.

- [ ] **Step 5: Run full onboarding test suite — nothing may regress**

Run: `./vendor/bin/pest tests/Feature/Onboarding/ tests/Unit/Services/Onboarding/`
Expected: all existing onboarding tests still pass (the default `mode = 'post_onboarding'` means they exercise the pre-existing code path).

- [ ] **Step 6: Commit**

```bash
git add app/Services/AI/FynPersonaOrchestrator.php tests/Feature/AI/PersonaSplit/OnboardingOrchestratorTest.php
git commit -m "feat(fyn): orchestrator onboarding mode dispatches asset_capture to data-capture persona"
```

---

### Task 22: Move FR-M14 buffered off-script filter from director to orchestrator wrapper

**Files:**
- Modify: `app/Services/AI/FynPersonaInvoker.php` (or add a new `OffScriptFilter` class invoked by the invoker)
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (remove the filter)
- Modify: `tests/Unit/Services/Onboarding/AssetCaptureOffScriptFilterTest.php` (point at new location)

- [ ] **Step 1: Extract the filter logic into a dedicated class**

Create `app/Services/AI/OffScriptFilter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Buffered sentence-level off-script filter. Accumulates content deltas for
 * a whole turn, then splits on sentence boundaries and drops any sentence
 * containing '?' OR any banned keyword when the capture focus is not
 * 'protection' or 'estate'. Originally lived in OnboardingChatDirector
 * (FR-M14). Moved here so it protects every data-capture turn, not only
 * onboarding ones.
 */
final class OffScriptFilter
{
    /** @var list<string> */
    private const BANNED_KEYWORDS = ['property', 'properties', 'mortgage', 'mortgages', 'rent', 'income', 'home', 'address', 'ownership', 'valuation'];

    /** @var list<string> */
    private const WHITELIST_FOCUS = ['protection', 'estate'];

    public function filter(string $rawText, ?string $focus): string
    {
        if (in_array((string) $focus, self::WHITELIST_FOCUS, true)) {
            return $rawText;
        }

        // Split on sentence boundary characters while keeping the delimiters.
        $sentences = preg_split('/(?<=[.!?])\s+/u', $rawText, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $kept = array_values(array_filter($sentences, function (string $sentence) {
            $lower = mb_strtolower($sentence);

            if (str_contains($sentence, '?')) {
                return false;
            }
            foreach (self::BANNED_KEYWORDS as $banned) {
                if (preg_match('/\b' . preg_quote($banned, '/') . '\b/iu', $lower)) {
                    return false;
                }
            }

            return true;
        }));

        return implode(' ', $kept);
    }
}
```

- [ ] **Step 2: Apply the filter inside `FynPersonaInvoker::invoke()` for the data-capture persona**

Modify the invoker to buffer capture-persona text events through the filter. Wrap the streamed output:

```php
use App\Services\AI\OffScriptFilter;

// In FynPersonaInvoker::__construct, add:
public function __construct(
    private readonly FynPersonaRegistry $registry,
    private readonly AiToolDefinitions $toolDefinitions,
    private readonly CoordinatingAgent $coordinatingAgent,
    private readonly OffScriptFilter $filter,
) {}

// Inside invoke(), when persona is data_capture, buffer text events:
if ($persona === FynPersonaRegistry::DATA_CAPTURE) {
    $buffer = '';
    foreach ($this->coordinatingAgent->chatWithPromptOverride(...) as $event) {
        if (($event['type'] ?? '') === 'text') {
            $buffer .= $event['content'] ?? '';
            continue; // hold text; release after filtering on 'done'
        }
        if (($event['type'] ?? '') === 'done') {
            $filtered = $this->filter->filter($buffer, $captureContext?->originatingFocus);
            if ($filtered !== '') {
                yield ['type' => 'text', 'content' => $filtered];
            }
            yield $event;
            continue;
        }
        yield $event;
    }
    return;
}

yield from $this->coordinatingAgent->chatWithPromptOverride(...);
```

- [ ] **Step 3: Remove the filter call from `OnboardingChatDirector`**

In `OnboardingChatDirector::handleAssetCaptureTurn()`, remove the buffered filter application. The director keeps invoking `CoordinatingAgent::chatWithPromptOverride()` directly during the bridge period; after Task 24 the director is deleted entirely.

- [ ] **Step 4: Update `AssetCaptureOffScriptFilterTest` to target `OffScriptFilter`**

Rewrite the test to call `OffScriptFilter::filter()` directly rather than the director internals. Preserve every existing assertion (the banned keywords, whitelist focuses, partial-sentence filtering, word-boundary edge cases).

- [ ] **Step 5: Run the onboarding + persona-split test suites**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/ tests/Feature/Onboarding/ tests/Feature/AI/PersonaSplit/`
Expected: PASS — `AssetCaptureOffScriptFilterTest` passes against the new location; everything else green.

- [ ] **Step 6: Commit**

```bash
git add app/Services/AI/OffScriptFilter.php app/Services/AI/FynPersonaInvoker.php app/Services/Onboarding/OnboardingChatDirector.php tests/Unit/Services/Onboarding/AssetCaptureOffScriptFilterTest.php
git commit -m "refactor(fyn): move FR-M14 off-script filter to orchestrator wrapper"
```

---

## Phase 10 — Onboarding UX enhancements

### Task 23: State machine — new `profile_review_family` and `profile_review_expenditure` states

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php`
- Test: `tests/Feature/AI/PersonaSplit/ProfileReviewPauseTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;

it('after base_dependants, state advances to profile_review_family, not employment', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_dependants',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    // Simulate user answering "no dependants" → state machine should now route to profile_review_family
    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'No dependants', null, 'onboarding')
    );

    expect($user->fresh()->onboarding_fyn_step)->toBe('profile_review_family');
});

it('after expenditure, state advances to profile_review_expenditure, not journey-specific capture', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'expenditure',
        'onboarding_fyn_selection' => 'retirement',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, '£1,500 rent, £300 utilities', null, 'onboarding')
    );

    expect($user->fresh()->onboarding_fyn_step)->toBe('profile_review_expenditure');
});

it('profile_review_family confirmation advances to employment', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'profile_review_family',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'Yes, that looks right', null, 'onboarding')
    );

    expect($user->fresh()->onboarding_fyn_step)->toBe('base_employment');
});

it('profile_review_expenditure confirmation advances to journey-specific asset_capture', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'profile_review_expenditure',
        'onboarding_fyn_selection' => 'retirement',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'Looks good', null, 'onboarding')
    );

    expect($user->fresh()->onboarding_fyn_step)->toBe('asset_capture');
});
```

- [ ] **Step 2: Run — expect failure**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/ProfileReviewPauseTest.php`
Expected: FAIL — states do not exist yet.

- [ ] **Step 3: Add the two new states and transitions to the state machine**

Open `app/Services/Onboarding/OnboardingStateMachine.php`. Add two new state constants:

```php
public const STATE_PROFILE_REVIEW_FAMILY = 'profile_review_family';
public const STATE_PROFILE_REVIEW_EXPENDITURE = 'profile_review_expenditure';
```

In the state definitions array (where existing states like `base_dependants`, `expenditure`, `journey_selection` live), add:

```php
self::STATE_PROFILE_REVIEW_FAMILY => [
    'prompt' => 'Is this correct? Any other family or details to add here?',
    'bubbles' => [
        ['id' => 'confirm', 'label' => "Yes, that's right"],
    ],
    'layout' => 'standard', // triggers SSE onboarding_layout_change to standard
    'on_confirm' => self::STATE_BASE_EMPLOYMENT,
    'accepts_free_text' => true, // user can say "No, my spouse DOB is wrong" → triggers retraction handling
],
self::STATE_PROFILE_REVIEW_EXPENDITURE => [
    'prompt' => 'Is this correct? Any other expenses to add, or changes to make?',
    'bubbles' => [
        ['id' => 'confirm', 'label' => "Looks good"],
    ],
    'layout' => 'standard',
    'on_confirm' => self::STATE_ASSET_CAPTURE, // journey handover happens here (Task 25)
    'accepts_free_text' => true,
],
```

Update the transitions so that:
- `base_dependants` advances to `profile_review_family` (not directly to employment)
- `expenditure` advances to `profile_review_expenditure` (not directly to journey/asset_capture)

- [ ] **Step 4: Run the test**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/ProfileReviewPauseTest.php`
Expected: PASS — 4 tests.

- [ ] **Step 5: Run the full onboarding suite to check for regressions**

Run: `./vendor/bin/pest tests/Feature/Onboarding/ tests/Unit/Services/Onboarding/`
Expected: PASS. `StateMachineWalkthroughTest` may need one fixture update — walking the happy path now hits two more states. Update fixtures (NOT assertions) to acknowledge the pause points.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Onboarding/OnboardingStateMachine.php tests/Feature/AI/PersonaSplit/ProfileReviewPauseTest.php tests/Feature/Onboarding/StateMachineWalkthroughTest.php
git commit -m "feat(fyn onboarding): profile review states after dependants and expenditure"
```

---

### Task 24: Spouse skip link, employment bubble changes, multi-job capture loop

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php`
- Modify: `config/onboarding.php` (if bubble config lives there)
- Test: `tests/Feature/AI/PersonaSplit/SpouseSkipTest.php`
- Test: `tests/Feature/AI/PersonaSplit/MultiJobCaptureTest.php`

- [ ] **Step 1: Write `SpouseSkipTest`**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;

it('skip on spouse block advances past base_spouse entirely to base_dependants', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_spouse',
        'marital_status' => 'married',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, '__skip__', null, 'onboarding')
    );

    expect($user->fresh()->onboarding_fyn_step)->toBe('base_dependants');
});

it('spouse block emits a message with a skip_link metadata flag', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_spouse',
        'marital_status' => 'married',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    $events = iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, '', null, 'onboarding')
    );

    $hasSkipLink = collect($events)->contains(fn ($e) => ($e['skip_link'] ?? false) === true);
    expect($hasSkipLink)->toBeTrue();
});
```

- [ ] **Step 2: Write `MultiJobCaptureTest`**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;

it('after first job captured, state asks for another job before advancing to expenditure', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_employment',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    // First job capture
    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'Full-time at Barclays £60k', null, 'onboarding')
    );

    expect($user->fresh()->onboarding_fyn_step)->toBe('base_employment_more');
});

it('answering "no more jobs" advances to expenditure', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_employment_more',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'No', null, 'onboarding')
    );

    expect($user->fresh()->onboarding_fyn_step)->toBe('expenditure');
});

it('employment bubbles include Full-time, exclude Other', function () {
    $config = App\Services\Onboarding\OnboardingStateMachine::getState('base_employment');

    $labels = collect($config['bubbles'] ?? [])->pluck('label')->all();
    expect($labels)->toContain('Full-time')
        ->not->toContain('Other')
        ->not->toContain('Employed');
});
```

- [ ] **Step 3: Run both tests — expect failure**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/SpouseSkipTest.php tests/Feature/AI/PersonaSplit/MultiJobCaptureTest.php`
Expected: FAIL.

- [ ] **Step 4: Add skip handling to `base_spouse`**

In `OnboardingStateMachine.php`, update the `base_spouse` state definition:

```php
self::STATE_BASE_SPOUSE => [
    'prompt' => "Tell me about your spouse — their name, date of birth, and anything relevant. You can also ",
    'skip_link' => [
        'label' => 'skip this',
        'token' => '__skip__',
        'color' => 'raspberry', // rendered as a raspberry-500 text link
    ],
    'on_skip' => self::STATE_BASE_DEPENDANTS,
    'accepts_free_text' => true,
    'on_complete' => self::STATE_BASE_DEPENDANTS,
],
```

In the orchestrator's onboarding turn handler (from Task 21), recognise the `__skip__` token — if the user's message equals that token exactly, apply the `on_skip` transition without running any capture.

- [ ] **Step 5: Update `base_employment` and add `base_employment_more`**

```php
self::STATE_BASE_EMPLOYMENT => [
    'prompt' => "What's your employment status? Tell me about your role — company, salary, and anything relevant.",
    'bubbles' => [
        ['id' => 'full_time', 'label' => 'Full-time'],
        ['id' => 'part_time', 'label' => 'Part-time'],
        ['id' => 'self_employed', 'label' => 'Self-employed'],
        ['id' => 'retired', 'label' => 'Retired'],
        ['id' => 'not_working', 'label' => 'Not working'],
        // 'Other' removed per spec
    ],
    'on_complete' => self::STATE_BASE_EMPLOYMENT_MORE,
    'accepts_free_text' => true,
],
self::STATE_BASE_EMPLOYMENT_MORE => [
    'prompt' => "Do you have any other jobs to add?",
    'bubbles' => [
        ['id' => 'yes', 'label' => 'Yes, add another'],
        ['id' => 'no', 'label' => 'No, that\'s all'],
    ],
    'on_bubble_yes' => self::STATE_BASE_EMPLOYMENT, // loop back
    'on_bubble_no' => self::STATE_EXPENDITURE,
    'accepts_free_text' => false,
],
```

Add the constant: `public const STATE_BASE_EMPLOYMENT_MORE = 'base_employment_more';`.

- [ ] **Step 6: Run both tests**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/SpouseSkipTest.php tests/Feature/AI/PersonaSplit/MultiJobCaptureTest.php`
Expected: PASS.

- [ ] **Step 7: Run full onboarding suite**

Run: `./vendor/bin/pest tests/Feature/Onboarding/ tests/Unit/Services/Onboarding/`
Expected: PASS (`StateMachineWalkthroughTest` fixtures updated to walk through the new employment_more state).

- [ ] **Step 8: Commit**

```bash
git add app/Services/Onboarding/OnboardingStateMachine.php tests/Feature/AI/PersonaSplit/SpouseSkipTest.php tests/Feature/AI/PersonaSplit/MultiJobCaptureTest.php tests/Feature/Onboarding/StateMachineWalkthroughTest.php
git commit -m "feat(fyn onboarding): spouse skip link, employment bubble updates, multi-job capture loop"
```

---

### Task 25: Prompt lifecycle — clear at pause boundaries + journey-tagged handover after expenditure review

**Files:**
- Modify: `app/Services/AI/FynPersonaOrchestrator.php`
- Test: `tests/Feature/AI/PersonaSplit/JourneyHandoverTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;
use App\Services\AI\FynPersonaRegistry;
use App\ValueObjects\CaptureContext;

it('entering profile_review_family clears the accumulated onboarding prompt context', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'profile_review_family',
    ]);
    $conversation = AiConversation::factory()->for($user)->create([
        'metadata' => ['accumulated_prompt_tokens' => 1200],
    ]);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, '', null, 'onboarding')
    );

    $meta = $conversation->fresh()->metadata ?? [];
    expect($meta['accumulated_prompt_tokens'] ?? 0)->toBe(0);
});

it('after profile_review_expenditure confirmation, CaptureContext.originatingFocus matches the journey selection', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'profile_review_expenditure',
        'onboarding_fyn_selection' => 'retirement',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    $capturedContext = null;
    $invoker = Mockery::mock(\App\Services\AI\FynPersonaInvoker::class);
    $invoker->shouldReceive('invoke')
        ->andReturnUsing(function ($u, $c, $persona, $msg, $ctx = null) use (&$capturedContext) {
            if ($persona === FynPersonaRegistry::DATA_CAPTURE) {
                $capturedContext = $ctx;
            }
            yield ['type' => 'text', 'content' => 'ok'];
            yield ['type' => 'done'];
        });
    app()->instance(\App\Services\AI\FynPersonaInvoker::class, $invoker);

    // Simulate user confirming the review, which should advance to asset_capture
    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, 'Looks good', null, 'onboarding')
    );

    // Then the next turn in asset_capture should use originating_focus = 'retirement'
    $user->refresh();
    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation->fresh(), 'I have a Scottish Widows SIPP', null, 'onboarding')
    );

    expect($capturedContext)->toBeInstanceOf(CaptureContext::class)
        ->and($capturedContext->originatingFocus)->toBe('retirement');
});

afterEach(fn () => Mockery::close());
```

- [ ] **Step 2: Run — expect failure**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/JourneyHandoverTest.php`
Expected: FAIL.

- [ ] **Step 3: Add prompt-clearing and journey handover hooks in the orchestrator**

In `FynPersonaOrchestrator`, within `dispatchOnboarding()`:

```php
private function dispatchOnboarding(
    User $user,
    AiConversation $conversation,
    string $userMessage,
    ?string $currentRoute = null,
): \Generator {
    $step = $user->onboarding_fyn_step;

    // On ENTRY to a review state: clear the accumulated prompt context to save tokens
    if (in_array($step, [
        \App\Services\Onboarding\OnboardingStateMachine::STATE_PROFILE_REVIEW_FAMILY,
        \App\Services\Onboarding\OnboardingStateMachine::STATE_PROFILE_REVIEW_EXPENDITURE,
    ], true)) {
        $this->clearPromptContext($conversation);
        yield ['type' => 'onboarding_layout_change', 'mode' => 'standard'];
    }

    if ($step === 'asset_capture') {
        $focus = $user->onboarding_fyn_selection ?? 'savings';
        $ctx = new CaptureContext(
            reason: 'onboarding asset capture',
            entityTypes: [],
            originatingFocus: $focus,
        );
        yield ['type' => 'onboarding_layout_change', 'mode' => 'wide'];
        yield from $this->invoker->invoke(
            $user, $conversation, FynPersonaRegistry::DATA_CAPTURE, $userMessage, $ctx
        );
        return;
    }

    // Default: standard bubble/state-driven handler from OnboardingTurnHandler
    yield ['type' => 'onboarding_layout_change', 'mode' => 'wide'];
    yield from app(\App\Services\AI\Onboarding\OnboardingTurnHandler::class)
        ->handle($user, $conversation, $userMessage, $currentRoute);
}

private function clearPromptContext(AiConversation $conversation): void
{
    $meta = $conversation->metadata ?? [];
    $meta['accumulated_prompt_tokens'] = 0;
    $meta['last_prompt_cleared_at'] = now()->toIso8601String();
    $conversation->update(['metadata' => $meta]);
}
```

The onboarding conversation itself (the `AiMessage` rows) is untouched — the full transcript is still stored. Only the prompt-context accumulator is reset, which the invoker's next build uses as a fresh starting point.

- [ ] **Step 4: Run the test**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/JourneyHandoverTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/FynPersonaOrchestrator.php tests/Feature/AI/PersonaSplit/JourneyHandoverTest.php
git commit -m "feat(fyn onboarding): clear prompt context at pause boundaries, journey-tagged handover"
```

---

### Task 26: Conversational retraction handler

**Files:**
- Modify: `app/Services/AI/Prompts/CoreIdentity.php` (or whichever prompt layer instructs on retraction)
- Modify: `app/Services/AI/FynPersonaOrchestrator.php`
- Test: `tests/Feature/AI/PersonaSplit/RetractionTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;

it('user saying "actually Im married" after said single updates marital_status and Fyn confirms', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'marital_status' => 'single',
        'onboarding_fyn_step' => 'base_dependants',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    $invoker = Mockery::mock(\App\Services\AI\FynPersonaInvoker::class);
    $invoker->shouldReceive('invoke')->andReturnUsing(function ($u, $c, $persona, $msg) {
        yield ['type' => 'tool_call', 'name' => 'update_profile', 'args' => [
            'marital_status' => 'married',
        ]];
        yield ['type' => 'text', 'content' => "Got it — updated from single to married."];
        yield ['type' => 'done'];
    });
    app()->instance(\App\Services\AI\FynPersonaInvoker::class, $invoker);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, "Actually I'm married", null, 'onboarding')
    );

    expect($user->fresh()->marital_status)->toBe('married');
});

afterEach(fn () => Mockery::close());
```

- [ ] **Step 2: Add the retraction instruction to the onboarding prompt layer**

In the prompt builder used during onboarding (either `DataCapturePromptBuilder` with `originatingFocus` set, or a dedicated onboarding prompt section), add:

```
<retraction_handling>
If the user contradicts or corrects a previously captured field mid-flow —
e.g. "actually I'm married", "no, make that £200 per month instead of £100",
"I meant my spouse's name is Jane, not Jenny" — recognise it as a retraction
of the earlier value. Call the appropriate update tool (update_profile,
update_record, or the relevant capture_* tool) to amend the stored value.
In your text response, confirm briefly with the before→after, e.g.
"Got it — updated from single to married." Do not apologise or dwell.
</retraction_handling>
```

- [ ] **Step 3: Run the test**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/RetractionTest.php`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Services/AI/Prompts/CoreIdentity.php tests/Feature/AI/PersonaSplit/RetractionTest.php
git commit -m "feat(fyn onboarding): conversational retraction handling"
```

---

### Task 27: Vuex `onboardingLayout` state + SSE layout events

**Files:**
- Modify: `resources/js/store/modules/aiChat.js`

- [ ] **Step 1: Add onboardingLayout state + mutation + action**

In `resources/js/store/modules/aiChat.js`:

```js
const state = {
    // ... existing state (including personaMode from Task 33)
    onboardingLayout: 'standard', // 'standard' | 'wide'
};

const mutations = {
    // ... existing mutations
    SET_ONBOARDING_LAYOUT(state, mode) {
        state.onboardingLayout = mode;
    },
};
```

Inside the SSE event handler, add:

```js
if (event.type === 'onboarding_layout_change') {
    commit('SET_ONBOARDING_LAYOUT', event.mode || 'standard');
    return;
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/store/modules/aiChat.js
git commit -m "feat(fyn onboarding): Vuex onboardingLayout state driven by SSE events"
```

---

### Task 28: `ProfileReviewPanel` component — read-only profile summary at pause points

**Files:**
- Create: `resources/js/components/Onboarding/ProfileReviewPanel.vue`
- Modify: `resources/js/views/OnboardingFyn.vue` (or wherever the onboarding layout lives) to render the panel

- [ ] **Step 1: Create the component**

```vue
<template>
    <div
        v-if="isPauseState"
        class="bg-white rounded-lg shadow-sm p-6 max-w-3xl mx-auto"
    >
        <h2 class="text-lg font-bold text-horizon-500 mb-4">Your profile so far</h2>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div v-if="user.first_name">
                <dt class="text-sm text-neutral-500">Name</dt>
                <dd class="text-horizon-500">{{ user.first_name }} {{ user.surname || '' }}</dd>
            </div>
            <div v-if="user.date_of_birth">
                <dt class="text-sm text-neutral-500">Date of birth</dt>
                <dd class="text-horizon-500">{{ formatDate(user.date_of_birth) }}</dd>
            </div>
            <div v-if="user.marital_status">
                <dt class="text-sm text-neutral-500">Marital status</dt>
                <dd class="text-horizon-500">{{ capitalise(user.marital_status) }}</dd>
            </div>
            <div v-if="spouse">
                <dt class="text-sm text-neutral-500">Spouse</dt>
                <dd class="text-horizon-500">{{ spouse.first_name }} ({{ spouse.date_of_birth ? formatDate(spouse.date_of_birth) : 'DOB not provided' }})</dd>
            </div>
            <div v-if="dependants.length">
                <dt class="text-sm text-neutral-500">Dependants</dt>
                <dd class="text-horizon-500">
                    <ul>
                        <li v-for="d in dependants" :key="d.id">{{ d.first_name }} (age {{ d.age }})</li>
                    </ul>
                </dd>
            </div>
            <div v-if="user.employment_status">
                <dt class="text-sm text-neutral-500">Employment</dt>
                <dd class="text-horizon-500">{{ capitalise(user.employment_status) }}</dd>
            </div>
            <div v-if="user.annual_employment_income">
                <dt class="text-sm text-neutral-500">Annual employment income</dt>
                <dd class="text-horizon-500">{{ formatCurrency(user.annual_employment_income) }}</dd>
            </div>
            <div v-if="user.monthly_expenditure">
                <dt class="text-sm text-neutral-500">Monthly expenditure</dt>
                <dd class="text-horizon-500">{{ formatCurrency(user.monthly_expenditure) }}/mo</dd>
            </div>
        </dl>

        <p class="mt-6 text-sm text-neutral-500">
            If anything's wrong, just tell Fyn in the chat — e.g. "actually my DOB is 12 March 1985".
        </p>
    </div>
</template>

<script>
import { mapState } from 'vuex';
import currencyMixin from '@/mixins/currencyMixin';
import { formatDateLong } from '@/utils/dateFormatter';

export default {
    name: 'ProfileReviewPanel',
    mixins: [currencyMixin],
    props: {
        user: { type: Object, required: true },
        spouse: { type: Object, default: null },
        dependants: { type: Array, default: () => [] },
    },
    computed: {
        ...mapState('aiChat', ['onboardingLayout']),
        isPauseState() {
            return this.onboardingLayout === 'standard';
        },
    },
    methods: {
        capitalise(s) {
            return s ? s.charAt(0).toUpperCase() + s.slice(1).replaceAll('_', ' ') : '';
        },
        formatDate(d) {
            return formatDateLong(d);
        },
    },
};
</script>
```

- [ ] **Step 2: Render the panel inside the onboarding view**

In whichever view owns the onboarding layout (e.g. `resources/js/views/OnboardingFyn.vue`), import and render:

```vue
<template>
    <div class="onboarding-container" :class="containerClass">
        <ProfileReviewPanel
            v-if="onboardingLayout === 'standard'"
            :user="user"
            :spouse="spouse"
            :dependants="dependants"
        />
        <FynChatWindow :wide="onboardingLayout === 'wide'" />
    </div>
</template>

<script>
import { mapState } from 'vuex';
import ProfileReviewPanel from '@/components/Onboarding/ProfileReviewPanel.vue';
import FynChatWindow from '@/components/AiChat/ChatWindow.vue';

export default {
    components: { ProfileReviewPanel, FynChatWindow },
    computed: {
        ...mapState('aiChat', ['onboardingLayout']),
        containerClass() {
            return this.onboardingLayout === 'wide' ? 'onboarding-wide' : 'onboarding-standard';
        },
        // ... user / spouse / dependants from API
    },
};
</script>
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Onboarding/ProfileReviewPanel.vue resources/js/views/OnboardingFyn.vue
git commit -m "feat(fyn onboarding): ProfileReviewPanel component at pause points"
```

---

### Task 29: Wide-chat layout + dashboard blur during onboarding capture blocks

**Files:**
- Modify: `resources/js/components/AiChat/ChatWindow.vue`
- Modify: `resources/js/layouts/AppLayout.vue`
- Modify: `resources/js/store/modules/aiChat.js` (small addition only if the `onboardingLayout` state from Task 27 isn't yet consumed)

- [ ] **Step 1: Add the wide-mode prop and CSS to `ChatWindow.vue`**

```vue
<template>
    <div :class="['fyn-chat-window', wide ? 'fyn-chat-wide' : 'fyn-chat-standard']">
        <!-- existing chat contents -->
    </div>
</template>

<script>
export default {
    props: {
        wide: { type: Boolean, default: false },
    },
    // ... existing options
};
</script>

<style scoped>
.fyn-chat-window {
    transition: max-width 0.3s ease-in-out;
}
.fyn-chat-standard {
    max-width: 28rem; /* existing standard width */
}
.fyn-chat-wide {
    max-width: 56rem; /* 2× standard */
}
</style>
```

- [ ] **Step 2: Drive `wide` from `onboardingLayout`**

In the parent that renders `ChatWindow` (`OnboardingFyn.vue` or similar), bind `:wide="onboardingLayout === 'wide'"`.

- [ ] **Step 3: Add dashboard blur when chat is wide**

Open `resources/js/layouts/AppLayout.vue`. Add a class binding on the main content container:

```vue
<template>
    <div :class="['app-layout', dashboardBlurred && 'dashboard-blurred']">
        <!-- navigation, content -->
    </div>
</template>

<script>
import { mapState } from 'vuex';

export default {
    computed: {
        ...mapState('aiChat', ['onboardingLayout']),
        dashboardBlurred() {
            return this.$route.name === 'onboarding-fyn' && this.onboardingLayout === 'wide';
        },
    },
};
</script>

<style scoped>
.app-layout.dashboard-blurred :deep(.dashboard-content) {
    filter: blur(4px);
    pointer-events: none;
    user-select: none;
    transition: filter 0.3s ease-in-out;
}
</style>
```

- [ ] **Step 4: Manual browser test**

Start dev server: `./dev.sh`
Open incognito, register a new test user with `?from=fyn`, walk the flow:

- [ ] On first load of onboarding → chat is **wide**, dashboard behind is **blurred**.
- [ ] Answer base_personal → still wide.
- [ ] Answer base_spouse → still wide. Skip link visible (verified in Task 30).
- [ ] Answer base_dependants → chat **shrinks to standard**, dashboard **un-blurs**, ProfileReviewPanel visible.
- [ ] Confirm → chat **doubles back to wide**, dashboard blurs.
- [ ] Answer employment + another-job + expenditure → chat shrinks again at `profile_review_expenditure`.
- [ ] Confirm → chat wides, journey asset_capture starts.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/AiChat/ChatWindow.vue resources/js/layouts/AppLayout.vue
git commit -m "feat(fyn onboarding): wide chat + dashboard blur during capture, standard at pauses"
```

---

### Task 30: `MessageBubble` skip-link rendering + retraction acknowledgment style

**Files:**
- Modify: `resources/js/components/AiChat/MessageBubble.vue`

- [ ] **Step 1: Accept skip-link metadata prop**

In the existing props:

```js
props: {
    message: { type: Object, required: true },
    recordsCreated: { type: Array, default: () => [] }, // from Task 35
    previewCta: { type: Boolean, default: false },      // from Task 35
    skipLink: { type: Object, default: null },          // new — { label, token, color }
},
```

- [ ] **Step 2: Render the inline raspberry-coloured link**

Inside the template, after the message text:

```vue
<template v-if="skipLink">
    <button
        type="button"
        class="inline text-raspberry-500 hover:text-raspberry-600 underline"
        @click="handleSkip"
    >
        {{ skipLink.label }}
    </button>
</template>
```

- [ ] **Step 3: Emit the skip token on click**

```js
methods: {
    handleSkip() {
        this.$emit('skip', this.skipLink.token);
    },
},
```

In the parent (`ChatWindow.vue`), listen for the `skip` event and push the token as a new user message to the conversation, which the orchestrator interprets as a skip in the backend (Task 24 handles the `__skip__` token).

- [ ] **Step 4: Wire the parent**

In `ChatWindow.vue` where messages are rendered:

```vue
<MessageBubble
    v-for="msg in messages"
    :key="msg.id"
    :message="msg"
    :skip-link="msg.skip_link || null"
    @skip="handleSkipClicked"
/>
```

With the handler:

```js
methods: {
    handleSkipClicked(token) {
        this.$store.dispatch('aiChat/sendMessage', { message: token });
    },
},
```

- [ ] **Step 5: Manual browser test (within Task 29's full walkthrough)**

- [ ] On the `base_spouse` prompt, a raspberry link reading "skip this" is visible inline.
- [ ] Clicking it advances to `base_dependants` without recording any spouse data.
- [ ] Retraction: mid-flow type "actually I'm married" — Fyn replies "Got it — updated from single to married." and the backing `users.marital_status` is updated. Verified via DB check.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/AiChat/MessageBubble.vue resources/js/components/AiChat/ChatWindow.vue
git commit -m "feat(fyn onboarding): skip-link rendering in MessageBubble"
```

---

### Task 31: Onboarding memory — Fyn reads past conversation before asking any question

**Files:**
- Create: `app/Services/Onboarding/OnboardingMemoryExtractor.php`
- Modify: `app/Services/AI/Onboarding/OnboardingTurnHandler.php`
- Modify: `app/Services/AI/Prompts/DataCapturePromptBuilder.php`
- Test: `tests/Feature/AI/PersonaSplit/OnboardingMemoryTest.php`

Why this task exists: the state machine advances based on captured fields on the `users` row, but the user can volunteer information *before* the state that captures it. Example: in their very first free-text message, a user says *"I'm 40, married to Jane, two kids — Sam 8 and Eli 6."* Today, Fyn still asks "What's your marital status?", then "Tell me about your spouse", then "Do you have dependants?" — re-asking everything the user already said. This task lets Fyn check the full conversation history and skip or confirm fields that have already been volunteered.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;

it('skips base_spouse when the user already described their spouse in an earlier message', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_personal',
        'marital_status' => 'married',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    // Earlier in the conversation the user said everything up-front.
    AiMessage::create([
        'ai_conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => "I'm 40, married to Jane who is 38, two kids — Sam 8 and Eli 6.",
    ]);

    // Simulate the orchestrator advancing from base_personal → base_spouse
    $user->update(['onboarding_fyn_step' => 'base_spouse']);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, '', null, 'onboarding')
    );

    // Memory extractor should have matched spouse info in the earlier message and auto-advanced.
    expect($user->fresh()->onboarding_fyn_step)->toBe('base_dependants');
});

it('when a field is captured from memory, Fyn emits a confirmation rather than a fresh question', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_dependants',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    AiMessage::create([
        'ai_conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => "Two kids — Sam 8 and Eli 6.",
    ]);

    $events = iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, '', null, 'onboarding')
    );

    $combinedText = collect($events)->filter(fn ($e) => ($e['type'] ?? '') === 'text')->pluck('content')->implode(' ');
    expect($combinedText)->toContain('Sam')->and($combinedText)->toContain('Eli');
});
```

- [ ] **Step 2: Run the test — expect failure**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/OnboardingMemoryTest.php`
Expected: FAIL.

- [ ] **Step 3: Create `OnboardingMemoryExtractor`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\AiConversation;
use App\Models\AiMessage;

/**
 * Extracts already-volunteered facts from a conversation's message history.
 * Used by the onboarding turn handler to skip or pre-fill states when the
 * user has already given the answer in an earlier turn.
 *
 * Heuristic-based — no LLM call. False negatives fall through to asking
 * normally. False positives are bounded: we only pre-fill when the signal
 * is unambiguous (explicit phrase match).
 */
final class OnboardingMemoryExtractor
{
    /**
     * Analyse the conversation and return a map of captured hints.
     *
     * @return array{
     *   marital_status: ?string,
     *   spouse_mentioned: bool,
     *   spouse_name: ?string,
     *   dependants_mentioned: bool,
     *   dependants_count: ?int,
     *   employment_mentioned: bool,
     *   expenditure_mentioned: bool,
     * }
     */
    public function extract(AiConversation $conversation): array
    {
        $userMessages = AiMessage::where('ai_conversation_id', $conversation->id)
            ->where('role', 'user')
            ->orderBy('id')
            ->pluck('content')
            ->all();

        $all = mb_strtolower(implode(' ', $userMessages));

        return [
            'marital_status' => $this->matchMarital($all),
            'spouse_mentioned' => $this->mentionsSpouse($all),
            'spouse_name' => $this->extractSpouseName($all),
            'dependants_mentioned' => $this->mentionsDependants($all),
            'dependants_count' => $this->extractDependantCount($all),
            'employment_mentioned' => $this->mentionsEmployment($all),
            'expenditure_mentioned' => $this->mentionsExpenditure($all),
        ];
    }

    private function matchMarital(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'married') || str_contains($text, 'my wife') || str_contains($text, 'my husband') || str_contains($text, 'my spouse') => 'married',
            str_contains($text, 'civil partner') => 'civil_partnership',
            str_contains($text, "i'm single") || str_contains($text, 'i am single') => 'single',
            str_contains($text, 'divorced') => 'divorced',
            str_contains($text, 'widowed') => 'widowed',
            default => null,
        };
    }

    private function mentionsSpouse(string $text): bool
    {
        foreach (['my wife', 'my husband', 'my spouse', 'my partner', 'married to'] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function extractSpouseName(string $text): ?string
    {
        // Match "married to <Name>" or "my wife <Name>" or "my husband <Name>"
        if (preg_match('/(?:married to|my wife|my husband|my spouse|my partner)\s+([A-Z][a-z]+)/u', ucwords($text), $m)) {
            return $m[1];
        }

        return null;
    }

    private function mentionsDependants(string $text): bool
    {
        foreach (['my kid', 'my kids', 'my son', 'my daughter', 'my child', 'my children', 'dependant', 'dependent'] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function extractDependantCount(string $text): ?int
    {
        // "two kids", "three children", "1 child", "2 dependants"
        $wordMap = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5];
        if (preg_match('/(\d+|one|two|three|four|five)\s+(kid|kids|child|children|dependant|dependants|dependent|dependents)/u', $text, $m)) {
            return is_numeric($m[1]) ? (int) $m[1] : ($wordMap[mb_strtolower($m[1])] ?? null);
        }

        return null;
    }

    private function mentionsEmployment(string $text): bool
    {
        foreach (['work at', 'work for', 'i work', 'my job', 'self-employed', 'full-time', 'part-time', 'unemployed', 'retired', 'salary of'] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function mentionsExpenditure(string $text): bool
    {
        return (bool) preg_match('/£\s?\d+[\d,]*\s*(per month|a month|\/mo|\/month|monthly)/i', $text);
    }
}
```

- [ ] **Step 4: Wire the extractor into `OnboardingTurnHandler`**

At the top of `OnboardingTurnHandler::handle()`, before the state logic:

```php
use App\Services\Onboarding\OnboardingMemoryExtractor;

// inside handle():
$memory = app(OnboardingMemoryExtractor::class)->extract($conversation);

// Then, per state, consult $memory before emitting the question:

// base_spouse state: skip if spouse already mentioned
if ($user->onboarding_fyn_step === OnboardingStateMachine::STATE_BASE_SPOUSE
    && $memory['spouse_mentioned']
    && $user->spouse_id !== null /* already linked */) {
    $user->update(['onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_DEPENDANTS]);
    yield ['type' => 'text', 'content' => "I've got your spouse details from earlier — let's move on."];
    yield ['type' => 'done'];
    return;
}

// base_dependants state: pre-fill count if mentioned
if ($user->onboarding_fyn_step === OnboardingStateMachine::STATE_BASE_DEPENDANTS
    && $memory['dependants_mentioned']
    && $memory['dependants_count'] !== null) {
    // Emit a confirmation-style prompt instead of a fresh question
    $confirmPrompt = "You mentioned {$memory['dependants_count']} children earlier — could you give me their names and dates of birth?";
    yield ['type' => 'text', 'content' => $confirmPrompt];
    // Still advance on their response; state stays until they answer
    return;
}

// base_employment state: similar — if employment already described, confirm + ask for salary only
// (follow the same pattern; full implementation mirrors the above)
```

- [ ] **Step 5: Add the memory block to `DataCapturePromptBuilder` for onboarding turns**

Inside `build()` when `originatingFocus !== null`, inject a memory summary into the prompt:

```php
use App\Services\Onboarding\OnboardingMemoryExtractor;

// Inside build(), after the legacy delegation check, before emitting layers:
$memory = app(OnboardingMemoryExtractor::class)->extract($conversation ?? /* need to plumb this through */);

$memoryBlock = '';
if ($memory['spouse_mentioned'] || $memory['dependants_mentioned'] || $memory['employment_mentioned']) {
    $items = [];
    if ($memory['spouse_mentioned']) $items[] = "spouse (name: {$memory['spouse_name']})";
    if ($memory['dependants_mentioned']) $items[] = "{$memory['dependants_count']} children";
    if ($memory['employment_mentioned']) $items[] = "employment";
    $memoryBlock = "\n\n<conversation_memory>\nThe user has ALREADY told you about: " . implode(', ', $items) . ". Do NOT ask about these again — reference what you know and only ask for the missing specifics.\n</conversation_memory>";
}

// Append $memoryBlock to the prompt layers.
```

The `build()` signature will need a `?AiConversation $conversation` parameter — update its callers in `FynPersonaInvoker` and the onboarding dispatch accordingly.

- [ ] **Step 6: Run the test**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/OnboardingMemoryTest.php`
Expected: PASS.

- [ ] **Step 7: Run the full onboarding suite**

Run: `./vendor/bin/pest tests/Feature/Onboarding/ tests/Unit/Services/Onboarding/ tests/Feature/AI/PersonaSplit/`
Expected: PASS. `StateMachineWalkthroughTest` may need fixture updates — the happy-path fixtures don't mention spouses/dependants early, so the walk should be unchanged.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Onboarding/OnboardingMemoryExtractor.php app/Services/AI/Onboarding/OnboardingTurnHandler.php app/Services/AI/Prompts/DataCapturePromptBuilder.php tests/Feature/AI/PersonaSplit/OnboardingMemoryTest.php
git commit -m "feat(fyn onboarding): memory extractor — skip re-asking facts the user already volunteered"
```

---

### Task 32: Resume-from-where-left-off — fix the broken "welcome back" flow

**Files:**
- Investigate: `app/Http/Controllers/Api/AiChatController.php` (method `startOnboarding` or equivalent)
- Investigate: `resources/js/views/OnboardingFyn.vue` (on-mount logic)
- Modify: one or both of the above
- Test: `tests/Feature/AI/PersonaSplit/OnboardingResumeTest.php`

Why this task exists: when the user logs out mid-onboarding and logs back in, the intended behaviour is for Fyn to greet them with *"Welcome back, {firstName}. Last time we were on [stage], you'd just [summary]. Want to continue from where we left off, or start over?"*. The user has confirmed this was **planned and was claimed as done, but is not currently working**. This task investigates the gap, fixes the code, and adds a regression test so it can't silently break again.

- [ ] **Step 1: Investigate the current state**

Run: `grep -rn "onboarding_fyn_step\|resumeOnboarding\|startOnboarding\|continueOnboarding" app/Http/Controllers/ app/Services/Onboarding/ resources/js/views/OnboardingFyn.vue 2>/dev/null`

Read the identified files. Confirm whether a resume path exists but is bypassed, or whether it was never wired up. Capture findings inline (do not write to separate files — notes go in this step).

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;

it('returning user with incomplete onboarding sees a welcome-back greeting', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_employment',
        'first_name' => 'Chris',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();
    AiMessage::create([
        'ai_conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Got it — DOB noted as 12 March 1985.',
    ]);

    // Simulate the user landing on /onboarding/fyn after logging back in.
    // The resume endpoint (or the orchestrator's onboarding-mode dispatch on empty user message)
    // must emit a resume greeting with two bubbles.
    $events = iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, '', null, 'onboarding')
    );

    $greetingText = collect($events)
        ->filter(fn ($e) => ($e['type'] ?? '') === 'text')
        ->pluck('content')
        ->implode(' ');

    expect($greetingText)
        ->toContain('Welcome back')
        ->and($greetingText)->toContain('Chris');

    $bubbles = collect($events)
        ->flatMap(fn ($e) => $e['bubbles'] ?? [])
        ->pluck('id')
        ->all();

    expect($bubbles)->toContain('continue')->toContain('restart');
});

it('choosing "continue" resumes from the stored onboarding_fyn_step', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_employment',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    // User clicks Continue
    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, '__continue__', null, 'onboarding')
    );

    // Step should be unchanged — the NEXT dispatch proceeds with the normal flow
    expect($user->fresh()->onboarding_fyn_step)->toBe('base_employment');
});

it('choosing "restart" clears state and begins again', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_employment',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();
    AiMessage::factory()->count(5)->for($conversation, 'conversation')->create();

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, '__restart__', null, 'onboarding')
    );

    expect($user->fresh()->onboarding_fyn_step)->toBe('path_choice')
        ->and(AiMessage::where('ai_conversation_id', $conversation->id)->count())->toBe(0);
});
```

- [ ] **Step 3: Run — expect failure**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/OnboardingResumeTest.php`
Expected: FAIL.

- [ ] **Step 4: Add the resume detection + greeting to the orchestrator**

Inside `FynPersonaOrchestrator::dispatchOnboarding()`, at the very top (before the review-state checks), add:

```php
use App\Services\Onboarding\OnboardingStateMachine;

// Detect resume scenario: user has a saved step AND this is a fresh page-load turn
// (either empty user message, or the special __resume__ bootstrap token).
$isResumeBootstrap = $userMessage === '' || $userMessage === '__resume__';
$hasPriorMessages = \App\Models\AiMessage::where('ai_conversation_id', $conversation->id)->exists();
$hasSavedStep = $user->onboarding_fyn_step !== null
    && $user->onboarding_fyn_step !== OnboardingStateMachine::STATE_PATH_CHOICE;

if ($isResumeBootstrap && $hasPriorMessages && $hasSavedStep) {
    yield from $this->emitResumeGreeting($user, $conversation);
    return;
}

// Handle resume bubble choices
if ($userMessage === '__continue__') {
    // Just advance through normal dispatch with the same saved step — fall through.
    $userMessage = ''; // re-enter the flow
}
if ($userMessage === '__restart__') {
    \App\Models\AiMessage::where('ai_conversation_id', $conversation->id)->delete();
    $user->update(['onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE]);
    yield ['type' => 'text', 'content' => "No problem — let's start fresh."];
    yield ['type' => 'done'];
    return;
}
```

Add the helper:

```php
private function emitResumeGreeting(User $user, AiConversation $conversation): \Generator
{
    $firstName = $user->first_name ?: explode(' ', (string) $user->name)[0];
    $stepLabel = $this->describeStep($user->onboarding_fyn_step);
    $lastAssistant = \App\Models\AiMessage::where('ai_conversation_id', $conversation->id)
        ->where('role', 'assistant')
        ->latest('id')
        ->value('content');
    $tail = $lastAssistant ? mb_substr($lastAssistant, 0, 120) : '';

    $greeting = "Welcome back, {$firstName}. Last time we were on {$stepLabel}"
        . ($tail ? " — I'd just said: \"{$tail}\"" : '')
        . '. Want to continue from where we left off, or start over?';

    yield ['type' => 'text', 'content' => $greeting];
    yield [
        'type' => 'bubbles',
        'bubbles' => [
            ['id' => 'continue', 'label' => 'Continue', 'token' => '__continue__'],
            ['id' => 'restart', 'label' => 'Start over', 'token' => '__restart__'],
        ],
    ];
    yield ['type' => 'done'];
}

private function describeStep(string $step): string
{
    return match ($step) {
        'path_choice' => 'choosing your path',
        'base_personal' => 'your personal details',
        'base_spouse' => 'your spouse',
        'base_dependants' => 'your dependants',
        'profile_review_family' => 'reviewing your family profile',
        'base_employment' => 'your employment',
        'base_employment_more' => 'adding more jobs',
        'expenditure' => 'your expenses',
        'profile_review_expenditure' => 'reviewing your expenses',
        'asset_capture' => "your {$this->userFocusLabel($user ?? null)}",
        'add_more' => 'adding more details',
        default => "the {$step} step",
    };
}
```

- [ ] **Step 5: Frontend — trigger a resume dispatch on mount**

Open `resources/js/views/OnboardingFyn.vue`. In the `mounted()` / `onMounted()` hook, add logic to fetch the existing conversation and, if the user has a saved step, dispatch the resume bootstrap:

```js
async mounted() {
    const userResp = await this.$store.dispatch('auth/fetchUser');
    if (userResp.onboarding_completed === false && userResp.onboarding_fyn_step) {
        // Resume scenario
        await this.$store.dispatch('aiChat/loadExistingOnboardingConversation');
        await this.$store.dispatch('aiChat/sendMessage', { message: '__resume__' });
    } else {
        // Fresh start
        await this.$store.dispatch('aiChat/startOnboarding');
    }
},
```

Add the Vuex action `loadExistingOnboardingConversation` in `aiChat.js` that fetches the user's most recent `AiConversation` with onboarding context.

- [ ] **Step 6: Run the test**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/OnboardingResumeTest.php`
Expected: PASS.

- [ ] **Step 7: Manual browser test**

Run `./dev.sh`, open incognito. Register a new user with `?from=fyn`, walk partway through onboarding (to `base_employment`). Close the tab without finishing. Log in again. Visit `/onboarding/fyn`.

- [ ] Fyn greets with "Welcome back, {firstName}. Last time we were on your employment..."
- [ ] Two bubbles visible: "Continue" and "Start over".
- [ ] Click "Continue" → state machine resumes at `base_employment`, next message advances normally.
- [ ] Log out again, log back in, click "Start over" → `onboarding_fyn_step` resets to `path_choice`, conversation cleared, fresh path-choice prompt appears.

- [ ] **Step 8: Commit**

```bash
git add app/Services/AI/FynPersonaOrchestrator.php resources/js/views/OnboardingFyn.vue resources/js/store/modules/aiChat.js tests/Feature/AI/PersonaSplit/OnboardingResumeTest.php
git commit -m "fix(fyn onboarding): resume-from-where-left-off flow (welcome back + continue/restart)"
```

---

### Task 33: Onboarding fact parking — extract ALL info from every user message, ask only for the gaps

**Files:**
- Create: `database/migrations/2026_04_22_000004_add_onboarding_parked_facts_to_ai_conversations.php`
- Modify: `app/Models/AiConversation.php`
- Modify: `app/Services/Onboarding/OnboardingMemoryExtractor.php` (extend with `extractStructuredFacts`)
- Modify: `app/Services/AI/FynPersonaOrchestrator.php` (extract-and-park hook at turn start)
- Modify: `app/Services/AI/Onboarding/OnboardingTurnHandler.php` (consume parking per state, ask targeted follow-ups)
- Test: `tests/Feature/AI/PersonaSplit/OnboardingFactParkingTest.php`

Why this task exists: today, when the user answers a question and volunteers extra information in the same breath, the extra info is silently dropped. Example: Fyn asks "What's your marital status?", user says *"Married to Angela, 45"* — Fyn captures the marital status and loses the spouse info, then asks the user again about their spouse a turn later. This task makes every turn capture *everything* the user volunteers, store it in a parked-facts column, and use it to collapse subsequent states to targeted follow-ups that ask **only for the missing fields**.

Distinct from Task 31 (memory): Task 31 reads past messages to decide whether to skip a state. This task stores *structured facts* extracted from EVERY user message into a dedicated JSON column, so subsequent states can consume partial records directly and emit questions like *"Thanks for letting me know about Angela — could I get her email address so I can set up a linked account for her?"* rather than *"Tell me about your spouse."*

- [ ] **Step 1: Create the migration**

Run: `php artisan make:migration add_onboarding_parked_facts_to_ai_conversations --table=ai_conversations`

Rename the generated file to `2026_04_22_000004_add_onboarding_parked_facts_to_ai_conversations.php`.

- [ ] **Step 2: Write the migration body**

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
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->json('onboarding_parked_facts')
                ->nullable()
                ->after('persona_state')
                ->comment('Structured facts extracted from any user message during onboarding. Subsequent states consume these to collapse their prompts to gap-filling follow-ups.');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('onboarding_parked_facts');
        });
    }
};
```

- [ ] **Step 3: Run migration and reseed**

Run: `php artisan migrate && php artisan db:seed`
Expected: migration completes; seed completes.

- [ ] **Step 4: Update `AiConversation` model**

Open `app/Models/AiConversation.php`. Add `onboarding_parked_facts` to `$fillable` and cast to `array`:

```php
protected $fillable = [
    // ... existing fields
    'onboarding_parked_facts',
];

protected $casts = [
    // ... existing casts
    'onboarding_parked_facts' => 'array',
];
```

- [ ] **Step 5: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;
use App\Services\Onboarding\OnboardingMemoryExtractor;

it('extracts structured facts from a user message and stores them in onboarding_parked_facts', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_personal',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch(
            $user,
            $conversation,
            "I'm 40, married to Angela, 45.",
            null,
            'onboarding'
        )
    );

    $parked = $conversation->fresh()->onboarding_parked_facts;

    expect($parked['personal']['marital_status'] ?? null)->toBe('married')
        ->and($parked['spouse']['first_name'] ?? null)->toBe('Angela')
        ->and($parked['spouse']['age_hint'] ?? null)->toBe(45);
});

it('at base_spouse, Fyn asks only for email when name and age are already parked', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_spouse',
        'marital_status' => 'married',
    ]);
    $conversation = AiConversation::factory()->for($user)->create([
        'onboarding_parked_facts' => [
            'spouse' => ['first_name' => 'Angela', 'age_hint' => 45],
        ],
    ]);

    $events = iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, '', null, 'onboarding')
    );

    $text = collect($events)->filter(fn ($e) => ($e['type'] ?? '') === 'text')->pluck('content')->implode(' ');

    expect($text)->toContain('Angela')
        ->and($text)->toContain('email');
});

it('when all required spouse fields are parked, the state advances without asking', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_spouse',
        'marital_status' => 'married',
    ]);
    $conversation = AiConversation::factory()->for($user)->create([
        'onboarding_parked_facts' => [
            'spouse' => [
                'first_name' => 'Angela',
                'age_hint' => 45,
                'email' => 'angela@example.com',
            ],
        ],
    ]);

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, '', null, 'onboarding')
    );

    expect($user->fresh()->onboarding_fyn_step)->toBe('base_dependants');
});

it('parks employment hints (company, salary) volunteered before the employment state', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_personal',
    ]);
    $conversation = AiConversation::factory()->for($user)->create();

    iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch(
            $user,
            $conversation,
            "I'm 40 and I work at Barclays full-time on £85k.",
            null,
            'onboarding'
        )
    );

    $parked = $conversation->fresh()->onboarding_parked_facts;

    expect($parked['employment']['company_hint'] ?? null)->toBe('Barclays')
        ->and($parked['employment']['status_hint'] ?? null)->toBe('full_time')
        ->and($parked['employment']['salary_hint'] ?? null)->toBe(85000);
});

it('parks dependant count so base_dependants asks only for names/DOBs', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_dependants',
    ]);
    $conversation = AiConversation::factory()->for($user)->create([
        'onboarding_parked_facts' => [
            'dependants' => ['count_hint' => 2],
        ],
    ]);

    $events = iterator_to_array(
        app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, '', null, 'onboarding')
    );

    $text = collect($events)->filter(fn ($e) => ($e['type'] ?? '') === 'text')->pluck('content')->implode(' ');

    expect($text)->toContain('2')->and($text)->toContain('names');
});
```

- [ ] **Step 6: Run the test — expect failure**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/OnboardingFactParkingTest.php`
Expected: FAIL on every case.

- [ ] **Step 7: Extend `OnboardingMemoryExtractor` with `extractStructuredFacts()`**

Add to the existing extractor class:

```php
/**
 * Extract a structured fact payload from a SINGLE user message. Returns
 * per-entity buckets suitable for merging into
 * ai_conversations.onboarding_parked_facts.
 *
 * @return array{
 *   personal: array<string, mixed>,
 *   spouse: array<string, mixed>,
 *   dependants: array<string, mixed>,
 *   employment: array<string, mixed>,
 *   expenditure: array<string, mixed>,
 * }
 */
public function extractStructuredFacts(string $message): array
{
    $lower = mb_strtolower($message);
    $cased = $message; // for proper-noun extraction

    $facts = [
        'personal' => [],
        'spouse' => [],
        'dependants' => [],
        'employment' => [],
        'expenditure' => [],
    ];

    // Marital status
    $marital = $this->matchMarital($lower);
    if ($marital !== null) {
        $facts['personal']['marital_status'] = $marital;
    }

    // Spouse name + age
    if (preg_match('/(?:married to|my wife|my husband|my spouse|my partner)\s+([A-Z][a-z]+)(?:,?\s+(?:who is\s+)?(\d{1,3}))?/u', $cased, $m)) {
        $facts['spouse']['first_name'] = $m[1];
        if (isset($m[2]) && $m[2] !== '') {
            $facts['spouse']['age_hint'] = (int) $m[2];
        }
    }

    // Spouse email
    if (preg_match('/([a-z0-9._+-]+@[a-z0-9.-]+\.[a-z]{2,})/i', $message, $m)) {
        // naive — if there's only one email in the message, assume it's the spouse's
        $facts['spouse']['email'] = $m[1];
    }

    // Dependant count
    $count = $this->extractDependantCount($lower);
    if ($count !== null) {
        $facts['dependants']['count_hint'] = $count;
    }

    // Dependant names + ages — "Sam 8 and Eli 6", "a son called Sam aged 8"
    if (preg_match_all('/([A-Z][a-z]+)\s+(?:aged\s+)?(\d{1,2})(?!\d)/u', $cased, $mm, PREG_SET_ORDER)) {
        $names = [];
        foreach ($mm as $pair) {
            $names[] = ['name' => $pair[1], 'age_hint' => (int) $pair[2]];
        }
        if ($names !== []) {
            $facts['dependants']['people_hint'] = $names;
        }
    }

    // Employment
    if (preg_match('/(?:work(?:ing)? (?:at|for))\s+([A-Z][A-Za-z&\' ]+)/u', $cased, $m)) {
        $facts['employment']['company_hint'] = trim($m[1]);
    }
    if (str_contains($lower, 'full-time') || str_contains($lower, 'full time')) {
        $facts['employment']['status_hint'] = 'full_time';
    } elseif (str_contains($lower, 'part-time') || str_contains($lower, 'part time')) {
        $facts['employment']['status_hint'] = 'part_time';
    } elseif (str_contains($lower, 'self-employed') || str_contains($lower, 'self employed')) {
        $facts['employment']['status_hint'] = 'self_employed';
    } elseif (str_contains($lower, 'retired')) {
        $facts['employment']['status_hint'] = 'retired';
    }
    if (preg_match('/£\s?(\d{1,3}(?:,\d{3})*|\d+)\s*(k|K|,000)?/', $message, $m)) {
        $raw = (int) str_replace(',', '', $m[1]);
        $salary = (isset($m[2]) && $m[2] !== '' && strtolower($m[2]) === 'k') ? $raw * 1000 : $raw;
        if ($salary >= 1000) {
            // Heuristic: context words to distinguish salary from expenditure
            if (preg_match('/\b(salary|pay|earn|income|on £)/i', $message)) {
                $facts['employment']['salary_hint'] = $salary;
            } elseif (preg_match('/\b(rent|bills|expenses|spend|monthly|per month|\/mo|\/month)/i', $message)) {
                $facts['expenditure']['monthly_hint'] = $salary;
            }
        }
    }

    return $facts;
}

/**
 * Deep-merge newly extracted facts into an existing parked payload.
 * Newer values take precedence per key; arrays (e.g. people_hint) are
 * concatenated and deduplicated.
 */
public function mergeIntoPark(array $existing, array $fresh): array
{
    $merged = $existing;

    foreach ($fresh as $entity => $fields) {
        $merged[$entity] = $merged[$entity] ?? [];
        foreach ($fields as $key => $value) {
            if ($key === 'people_hint' && is_array($value)) {
                $existingPeople = $merged[$entity]['people_hint'] ?? [];
                $combined = array_merge($existingPeople, $value);
                // Dedupe by name
                $seen = [];
                $deduped = [];
                foreach ($combined as $person) {
                    $name = $person['name'] ?? null;
                    if ($name !== null && ! isset($seen[$name])) {
                        $seen[$name] = true;
                        $deduped[] = $person;
                    }
                }
                $merged[$entity]['people_hint'] = $deduped;
            } else {
                $merged[$entity][$key] = $value;
            }
        }
    }

    return $merged;
}
```

- [ ] **Step 8: Add the extract-and-park hook to the orchestrator**

At the top of `FynPersonaOrchestrator::dispatchOnboarding()`, before any state-specific logic:

```php
// Extract-and-park on every turn. Handles the "user volunteers extra info"
// case — whatever they say is added to parked_facts, which subsequent
// states consume to shrink their prompts to gap-filling follow-ups.
if ($userMessage !== '' && $userMessage !== '__resume__' && $userMessage !== '__continue__' && $userMessage !== '__restart__' && $userMessage !== '__skip__') {
    $extractor = app(\App\Services\Onboarding\OnboardingMemoryExtractor::class);
    $fresh = $extractor->extractStructuredFacts($userMessage);
    $existing = $conversation->onboarding_parked_facts ?? [];
    $merged = $extractor->mergeIntoPark($existing, $fresh);
    if ($merged !== $existing) {
        $conversation->update(['onboarding_parked_facts' => $merged]);
        $conversation->refresh();
    }
}
```

- [ ] **Step 9: Update state handlers to consume parked facts**

In `OnboardingTurnHandler::handle()`, add per-state parking consultation. Example for `base_spouse`:

```php
if ($user->onboarding_fyn_step === OnboardingStateMachine::STATE_BASE_SPOUSE) {
    $parked = $conversation->onboarding_parked_facts['spouse'] ?? [];
    $required = ['first_name', 'email']; // minimum to create a linked account
    $missing = array_values(array_diff($required, array_keys($parked)));

    // If everything's parked, apply and advance silently.
    if ($missing === []) {
        // Apply parked → create/link spouse record using $parked values
        app(\App\Services\Onboarding\SpouseLinkingService::class)
            ->createFromParkedFacts($user, $parked);
        $user->update(['onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_DEPENDANTS]);
        yield ['type' => 'text', 'content' => "Got your spouse details on file. Let's move on."];
        yield ['type' => 'done'];
        return;
    }

    // If some fields are parked, emit a targeted follow-up.
    if ($parked !== []) {
        $knownName = $parked['first_name'] ?? 'your spouse';
        $ageNote = isset($parked['age_hint']) ? " (age {$parked['age_hint']})" : '';
        $askList = implode(', ', array_map(fn ($f) => str_replace('_', ' ', $f), $missing));
        yield ['type' => 'text', 'content' => "Thanks for letting me know about {$knownName}{$ageNote}. Could I get their {$askList}? That'll let me set up a linked account for them."];
        yield ['type' => 'done'];
        return;
    }

    // Nothing parked — fall through to the normal question.
    // (existing behaviour)
}
```

Apply the same pattern to `base_dependants`, `base_employment`, and `expenditure`:

- `base_dependants`: if `count_hint` + `people_hint` are parked, ask only for missing DOBs per named dependant, or skip-and-create if everything's in.
- `base_employment`: if `company_hint` + `status_hint` + `salary_hint` are parked, create the employment record and advance to `base_employment_more`.
- `expenditure`: if `monthly_hint` is parked, confirm ("I noted £X monthly — is that right?") instead of asking afresh.

Keep the per-state diffs short and explicit; do NOT introduce a generic "parking consumer" abstraction in this task (YAGNI — only four states need this).

- [ ] **Step 10: Add `SpouseLinkingService::createFromParkedFacts()` helper**

In `app/Services/Onboarding/SpouseLinkingService.php`, add:

```php
public function createFromParkedFacts(User $user, array $parked): void
{
    if (empty($parked['first_name']) || empty($parked['email'])) {
        return; // caller should have validated — but be defensive
    }

    $spouse = User::firstOrCreate(
        ['email' => $parked['email']],
        [
            'name' => $parked['first_name'],
            'first_name' => $parked['first_name'],
            'is_preview_user' => false,
            // password is set when the spouse accepts the linked-account invite
        ]
    );

    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);

    // DOB: if age_hint is the only thing we have, approximate DOB to 1 Jan of the inferred birth year
    if (isset($parked['age_hint']) && $spouse->date_of_birth === null) {
        $year = (int) now()->format('Y') - (int) $parked['age_hint'];
        $spouse->update(['date_of_birth' => "{$year}-01-01"]);
    }
}
```

- [ ] **Step 11: Run the tests**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/OnboardingFactParkingTest.php`
Expected: PASS — 5 tests.

- [ ] **Step 12: Run the full onboarding + persona-split suites**

Run: `./vendor/bin/pest tests/Feature/Onboarding/ tests/Unit/Services/Onboarding/ tests/Feature/AI/PersonaSplit/`
Expected: PASS. `StateMachineWalkthroughTest` fixtures may need minor updates if the happy path accidentally triggers parking; check and adjust fixtures (not assertions).

- [ ] **Step 13: Commit**

```bash
git add database/migrations/2026_04_22_000004_add_onboarding_parked_facts_to_ai_conversations.php \
    app/Models/AiConversation.php \
    app/Services/Onboarding/OnboardingMemoryExtractor.php \
    app/Services/AI/FynPersonaOrchestrator.php \
    app/Services/AI/Onboarding/OnboardingTurnHandler.php \
    app/Services/Onboarding/SpouseLinkingService.php \
    tests/Feature/AI/PersonaSplit/OnboardingFactParkingTest.php
git commit -m "feat(fyn onboarding): fact parking + targeted gap-filling follow-ups"
```

---

## Phase 11 — Director cutover

### Task 34: Port director-specific tests to orchestrator-based equivalents

**Files:**
- Modify: `tests/Unit/Services/Onboarding/SpouseCollisionTest.php`
- Modify: `tests/Unit/Services/Onboarding/OnboardingChatDirectorFixesTest.php`

- [ ] **Step 1: For each test, replace `OnboardingChatDirector` invocation with `FynPersonaOrchestrator` in onboarding mode**

Open `tests/Unit/Services/Onboarding/SpouseCollisionTest.php`. Find every instance that calls `$director->handleUserMessage(...)` or injects `OnboardingChatDirector`. Replace with:

```php
app(FynPersonaOrchestrator::class)->dispatch($user, $conversation, $message, null, 'onboarding');
```

Repeat for `OnboardingChatDirectorFixesTest.php`. Do NOT change any assertion — the behavioural contract is identical; only the entry point moves.

- [ ] **Step 2: Run the ported tests**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/SpouseCollisionTest.php tests/Unit/Services/Onboarding/OnboardingChatDirectorFixesTest.php`
Expected: PASS. Any failure indicates behavioural drift between director and orchestrator that must be reconciled before proceeding.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Services/Onboarding/SpouseCollisionTest.php tests/Unit/Services/Onboarding/OnboardingChatDirectorFixesTest.php
git commit -m "test(fyn): port director tests to orchestrator onboarding mode"
```

---

### Task 35: Delete director and old prompt builder; simplify controller

**Files:**
- Delete: `app/Services/Onboarding/OnboardingChatDirector.php`
- Delete: `app/Services/Onboarding/OnboardingPromptBuilder.php`
- Modify: `app/Http/Controllers/Api/AiChatController.php`
- Modify: `app/Services/AI/FynPersonaOrchestrator.php` (remove the bridge call to the director)
- Modify: `app/Services/AI/Prompts/DataCapturePromptBuilder.php` (inline the old builder's `buildAssetCapturePrompt` logic since the class is gone)

- [ ] **Step 1: Inline `OnboardingPromptBuilder`'s byte-for-byte output into `DataCapturePromptBuilder`**

Open `app/Services/Onboarding/OnboardingPromptBuilder.php` and copy the `buildAssetCapturePrompt`, `assetCaptureInstructions`, `focusLabel`, and `toolsForFocus` methods. Paste them into `DataCapturePromptBuilder` as private methods (prefixed `legacyOnboarding...`). Update the `build()` method:

```php
if ($context->originatingFocus !== null) {
    return $this->legacyOnboardingPrompt($user, $context->originatingFocus);
}
```

Delete the delegation to `app(OnboardingPromptBuilder::class)`.

- [ ] **Step 2: Run the byte-compat regression test**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php`
Expected: PASS — the byte-for-byte test still passes against the inlined logic.

- [ ] **Step 3: Update `FynPersonaOrchestrator::dispatchOnboarding()` — non-capture states**

Non-capture onboarding states (`intro`, `base_personal`, `base_spouse`, `base_dependants`, `expenditure`, `journey_selection`, `add_more`, `done`) used to go through `OnboardingChatDirector::handleUserMessage`. That code path must survive the director deletion.

Move the non-capture handling logic (bubble matching, DOB parsing, state advancement) out of `OnboardingChatDirector` and into the orchestrator. Factor into a helper like `OnboardingTurnHandler` to keep the orchestrator focused:

Create `app/Services/AI/Onboarding/OnboardingTurnHandler.php` (new namespace as per spec):

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Onboarding;

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;

/**
 * Handles non-capture onboarding turns: bubble matching, DOB parsing,
 * base_personal / base_spouse / base_dependants / expenditure / journey
 * selection / add_more / done. Absorbed from OnboardingChatDirector
 * (which is now deleted).
 */
final class OnboardingTurnHandler
{
    /**
     * @return \Generator<array<string, mixed>>
     */
    public function handle(
        User $user,
        AiConversation $conversation,
        string $userMessage,
        ?string $currentRoute,
    ): \Generator {
        // Paste the body of OnboardingChatDirector::handleUserMessage()
        // here, minus the asset_capture branch (now owned by the orchestrator).
        // ... (full implementation copied from the director)
    }
}
```

Update `FynPersonaOrchestrator::dispatchOnboarding()`:

```php
private function dispatchOnboarding(
    User $user,
    AiConversation $conversation,
    string $userMessage,
    ?string $currentRoute,
): \Generator {
    if ($user->onboarding_fyn_step === 'asset_capture') {
        // ... unchanged asset_capture handling
        return;
    }

    yield from app(\App\Services\AI\Onboarding\OnboardingTurnHandler::class)
        ->handle($user, $conversation, $userMessage, $currentRoute);
}
```

- [ ] **Step 4: Move `OnboardingStateMachine` namespace**

Run: `git mv app/Services/Onboarding/OnboardingStateMachine.php app/Services/AI/Onboarding/OnboardingStateMachine.php`

Open the moved file, change the namespace:

```php
namespace App\Services\AI\Onboarding;
```

Update every `use` statement across the codebase:

Run: `grep -rln "App\\\\Services\\\\Onboarding\\\\OnboardingStateMachine" app/ tests/`

For each file listed, change the FQCN in the `use` statement to `App\Services\AI\Onboarding\OnboardingStateMachine`.

- [ ] **Step 5: Delete the director and prompt builder**

Run: `git rm app/Services/Onboarding/OnboardingChatDirector.php app/Services/Onboarding/OnboardingPromptBuilder.php`

- [ ] **Step 6: Simplify `AiChatController`**

Remove `OnboardingChatDirector` from the constructor. Replace the four-way match with:

```php
$generator = (bool) config('fyn.persona_split_enabled', false)
    ? $this->orchestrator->dispatch(
        $user,
        $conversation,
        $message,
        $currentRoute,
        mode: $this->resolveMode($user),
    )
    : $this->coordinatingAgent->chat($user, $conversation, $message, $currentRoute);
```

Add the helper:

```php
private function resolveMode(User $user): string
{
    $inOnboarding = $user->onboarding_completed === false
        && $user->onboarding_fyn_step !== null
        && (bool) config('onboarding.fyn_flow_enabled', true);

    return $inOnboarding ? 'onboarding' : 'post_onboarding';
}
```

- [ ] **Step 7: Run the full test suite**

Run: `./vendor/bin/pest`
Expected: ALL tests pass. The migration is complete; every former director responsibility is now owned by the orchestrator.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor(fyn): delete OnboardingChatDirector/OnboardingPromptBuilder, absorb into orchestrator"
```

---

## Phase 12 — Chat UI

### Task 36: Vuex `personaMode` state + SSE event handling

**Files:**
- Modify: `resources/js/store/modules/aiChat.js`

- [ ] **Step 1: Add `personaMode` state**

Open `resources/js/store/modules/aiChat.js`. In the state section, add:

```js
const state = {
    // ... existing state
    personaMode: 'advice', // 'advice' | 'capturing' | 'onboarding'
};
```

Add mutations:

```js
const mutations = {
    // ... existing mutations
    SET_PERSONA_MODE(state, mode) {
        state.personaMode = mode;
    },
};
```

Add an action dispatched when an SSE event arrives indicating a persona transition. Modify the existing SSE event handler to recognise `persona_state_change` events:

```js
// Inside the existing streamReply / handleSseEvent logic:
if (event.type === 'persona_state_change') {
    commit('SET_PERSONA_MODE', event.current || 'advice');
    return;
}
```

Add the action exposing it to components:

```js
const actions = {
    // ... existing actions
    updatePersonaMode({ commit }, mode) {
        commit('SET_PERSONA_MODE', mode);
    },
};
```

- [ ] **Step 2: Emit `persona_state_change` events from the orchestrator**

In `FynPersonaOrchestrator`, whenever `persona_state.current` changes, yield a new event type before the state transition proceeds:

```php
private function emitStateChange(AiConversation $conversation): \Generator
{
    yield [
        'type' => 'persona_state_change',
        'current' => $conversation->persona_state['current'] ?? 'advice',
    ];
}
```

Call `yield from $this->emitStateChange($conversation);` inside `enterCapturing()` and `resetToAdvice()` wrappers (you'll need to restructure those to yield rather than just update). Alternatively, emit the event after each transition from within `dispatch()`:

```php
// After persona_state is updated inside dispatch(), yield:
yield ['type' => 'persona_state_change', 'current' => $conversation->fresh()->persona_state['current']];
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/store/modules/aiChat.js app/Services/AI/FynPersonaOrchestrator.php
git commit -m "feat(fyn): Vuex personaMode state + SSE persona_state_change events"
```

---

### Task 37: `ChatWindow.vue` placeholder swap + capturing pill

**Files:**
- Modify: `resources/js/components/AiChat/ChatWindow.vue`

- [ ] **Step 1: Add computed for placeholder text**

In the `<script>` section of `ChatWindow.vue`, add:

```js
computed: {
    ...mapState('aiChat', ['personaMode']),
    chatPlaceholder() {
        return this.personaMode === 'capturing'
            ? 'Tell Fyn the details…'
            : 'Ask Fyn anything…';
    },
    showCapturingPill() {
        return this.personaMode === 'capturing';
    },
},
```

- [ ] **Step 2: Bind the placeholder to the input and add the pill above**

In the template, find the chat input. Change `:placeholder="..."` to `:placeholder="chatPlaceholder"`.

Above the input, add:

```vue
<div
    v-if="showCapturingPill"
    class="inline-flex items-center px-3 py-1 mb-2 text-sm text-horizon-500 bg-savannah-100 rounded-full"
>
    Updating your records
</div>
```

No icon, no spinner — per CLAUDE.md §14.

- [ ] **Step 3: Manual browser test**

Start dev server: `./dev.sh`
Open incognito → http://localhost:8000 → login as `john@example.com` / `password` (fetch verification code from DB per CLAUDE.md).

With `FYN_PERSONA_SPLIT=true`, send a message like "add my Nationwide cash ISA £5,000". Verify:
- [ ] Pill appears reading "Updating your records" above the input.
- [ ] Placeholder changes to "Tell Fyn the details…".
- [ ] After `capture_complete`, pill disappears; placeholder returns to "Ask Fyn anything…".

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/AiChat/ChatWindow.vue
git commit -m "feat(fyn): chat window placeholder swap and capturing pill"
```

---

### Task 38: `MessageBubble.vue` record-card row + preview-mode CTA

**Files:**
- Modify: `resources/js/components/AiChat/MessageBubble.vue`

- [ ] **Step 1: Accept new props**

```js
props: {
    message: { type: Object, required: true },
    recordsCreated: { type: Array, default: () => [] }, // new
    previewCta: { type: Boolean, default: false },      // new
},
```

- [ ] **Step 2: Render record cards when recordsCreated is non-empty**

Add to the template, after the existing message content:

```vue
<div v-if="recordsCreated.length" class="mt-2 space-y-2">
    <div
        v-for="record in recordsCreated"
        :key="`${record.type}-${record.id}`"
        class="card-sm flex items-center justify-between"
    >
        <div class="text-sm text-horizon-500">
            {{ record.display_name || `${record.type} #${record.id}` }}
        </div>
        <router-link
            :to="routeForRecord(record)"
            class="text-sm text-raspberry-500 hover:text-raspberry-600"
        >
            View
        </router-link>
    </div>
</div>
```

Add helper:

```js
methods: {
    routeForRecord(record) {
        const map = {
            savings_account: '/net-worth/cash',
            investment_account: '/net-worth/investments',
            dc_pension: '/net-worth/retirement',
            db_pension: '/net-worth/retirement',
            property: '/net-worth/property',
            mortgage: '/net-worth/property',
            life_insurance: '/protection',
            critical_illness: '/protection',
            income_protection: '/protection',
            goal: '/goals',
            life_event: '/goals',
            trust: '/trusts',
            will: '/estate/will-builder',
            power_of_attorney: '/estate/power-of-attorney',
            family_member: '/valuable-info?section=family',
            chattel: '/net-worth/chattels',
            business_interest: '/net-worth/business',
            liability: '/net-worth/liabilities',
        };
        return map[record.type] || '/dashboard';
    },
},
```

- [ ] **Step 3: Add preview-mode CTA**

Add after the record-card row:

```vue
<div v-if="previewCta" class="mt-3">
    <router-link
        to="/register"
        class="inline-block px-4 py-2 bg-raspberry-500 text-white rounded-md hover:bg-raspberry-600"
    >
        Sign up
    </router-link>
</div>
```

- [ ] **Step 4: Manual browser test**

Confirm:
- [ ] A `capture_complete` with `records_created: [{type: 'savings_account', id: N}]` renders a card with "View" linking to `/net-worth/cash`.
- [ ] Preview user trying to capture sees the "Sign up" CTA.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/AiChat/MessageBubble.vue
git commit -m "feat(fyn): message bubble record cards and preview CTA"
```

---

## Phase 13 — Preview mode

### Task 39: Advice prompt instructs preview-user Fyn not to emit `delegate_to_capture`

**Files:**
- Modify: `app/Services/AI/Prompts/CoreIdentity.php` (or whichever prompt layer includes preview context)
- Test: `tests/Feature/AI/PersonaSplit/PreviewModeTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;

it('preview user advice prompt contains the delegate-suppression instruction', function () {
    $user = User::factory()->create(['is_preview_user' => true]);
    $prompt = app(AdvicePromptBuilder::class)->build(
        $user,
        isPreview: true,
    );

    expect($prompt)->toContain('preview mode')
        ->and($prompt)->toContain("won't save");
});

it('non-preview user prompt does not contain the suppression instruction', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $prompt = app(AdvicePromptBuilder::class)->build($user, isPreview: false);

    expect($prompt)->not->toContain("I can't save data in preview");
});
```

- [ ] **Step 2: Find the preview-mode block in the prompt layers**

Open `app/Services/AI/Prompts/CoreIdentity.php` or `ComplianceRules.php` or `FcaProcessInstructions.php` — whichever currently receives the `isPreview` flag. Add (or modify) the preview paragraph:

```php
if ($isPreview) {
    $sections[] = <<<PROMPT
<preview_mode>
You are in preview mode. The user has NOT signed up and their data is
ephemeral. Do NOT emit `delegate_to_capture`. If the user asks you to add,
update, or delete a record, respond with:

"I can't save data in preview mode — but if you sign up, I'll capture this
straight away."

The frontend will render a sign-up button below your message.
</preview_mode>
PROMPT;
}
```

- [ ] **Step 3: Run the test**

Run: `./vendor/bin/pest tests/Feature/AI/PersonaSplit/PreviewModeTest.php`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Services/AI/Prompts/CoreIdentity.php tests/Feature/AI/PersonaSplit/PreviewModeTest.php
git commit -m "feat(fyn): advice prompt suppresses delegate_to_capture in preview mode"
```

---

## Phase 14 — Rollout

### Task 40: Deploy guide + vault sync

**Files:**
- Create: `April/April21Updates/deploy-fyn-persona-split.md`
- Create: `/Users/CSJ/Desktop/fynlaBrain/April/April21Updates/deploy-fyn-persona-split.md` (mirror)

- [ ] **Step 1: Generate the file list from git**

Run: `git diff --name-only $(git merge-base HEAD onboardingFyn)..HEAD`
Capture the output.

- [ ] **Step 2: Write the deploy guide**

Template for `April/April21Updates/deploy-fyn-persona-split.md`:

```markdown
# Fyn Persona Split — Deploy Guide (dev first)

**Target:** csjones.co/fynla (dev)
**Branch:** feature/fyn-persona-split → onboardingFyn → dev
**Flags at rollout:**
- `FYN_PERSONA_SPLIT=false` (ship dark first)
- `FYN_CLASSIFIER_FAST_PATH=true` (only meaningful when the above is true)

## Files changed

[paste the full output of `git diff --name-only` here — do NOT list from memory per feedback_deploy_guide_completeness.md]

## Pre-deploy checks (local)

- [ ] `./vendor/bin/pest` — full test suite green
- [ ] `php artisan migrate:status` — no pending migrations on local
- [ ] `./deploy/csjones-fynla/build.sh` — Vite build succeeds, record asset hashes
- [ ] Smoke test the app at `http://localhost:8000` with `FYN_PERSONA_SPLIT=false` — verify nothing regressed

## Upload

1. Upload `public/build/` directory to `~/www/csjones.co/fynla-app/public/build/`
2. Upload every changed PHP file to the matching path under `~/www/csjones.co/fynla-app/`
3. Upload changed Vue files to `~/www/csjones.co/fynla-app/resources/js/`
4. Upload `config/fyn.php`, `config/fyn_personas.php`
5. Upload the three new migration files in `database/migrations/`
6. Upload the new routes in `routes/api.php`

## SSH and finalise

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
php artisan migrate --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
php artisan db:seed --force
```

## Dark verification (flag off)

- [ ] Visit `https://csjones.co/fynla`. Login works. Existing chat behaves identically to pre-deploy.
- [ ] Onboarding flow (new user `?from=fyn`) still runs FR-M9..FR-M15 green.
- [ ] No new errors in `storage/logs/laravel.log` after 15 minutes.

## Flip the flag

SSH, edit `.env` to add `FYN_PERSONA_SPLIT=true`, then:

```bash
php artisan config:cache
```

## Smoke tests (flag on)

All tests require Playwright CLICK/FILL/SUBMIT per `critical_browser_testing_law`:

- [ ] **T1 (KYC gate)** — new user with no pensions asks "What about my pensions?". Fyn emits delegate + ack, asks for pension details, captures SIPP, returns with advice answer referencing the captured SIPP. DB shows `ai_messages` with personas `[advice, data_capture, advice]`.
- [ ] **T2 (inline capture)** — logged-in user with data sends "add my Nationwide cash ISA £5,000". Orchestrator fast-paths straight to data-capture. `savings_accounts` row created. `ai_conversations.persona_state.current` returns to `advice`.
- [ ] **T3 (cancel mid-capture)** — trigger a capture, then send "never mind". Orchestrator flips back without creating any record.
- [ ] **T4 (timeout)** — simulate (manually) with `FYN_CAPTURE_MAX_TURNS=2` and stall in capture. Fyn surfaces the timeout message.
- [ ] **T5 (onboarding untouched)** — fresh registration flow, walk FR-M9..FR-M15. All pass.
- [ ] **T6 (will)** — "add my will, executor Jane Smith, spouse as residuary beneficiary". `wills` row created.
- [ ] **T7 (LPA)** — "record my LPA for property and finance, attorney Sarah, registered 2024-06-15". `power_of_attorneys` row created.
- [ ] **T8 (classifier kill switch)** — set `FYN_CLASSIFIER_FAST_PATH=false`, re-run T2. Confirms advice Fyn is invoked first before delegating.
- [ ] **T9 (preview mode)** — preview persona sends "add my ISA". Advice Fyn declines and frontend shows Sign up CTA. No DB write.
- [ ] **T10 (wide-chat onboarding layout)** — fresh registration → during capture blocks (personal, spouse, dependants), chat is wide and dashboard behind is blurred. Layout switches to standard at `profile_review_family` and `profile_review_expenditure`, ProfileReviewPanel visible with the captured data. Back to wide after confirmation.
- [ ] **T11 (spouse skip link)** — user marks marital status as married → spouse prompt shows inline raspberry "skip this" link. Click advances to dependants without writing spouse data.
- [ ] **T12 (profile review pause — dependants)** — after dependants answered, chat shrinks to standard, profile panel shows name/DOB/marital/spouse/dependants. Saying "Yes, that looks right" advances to employment; saying "No, my DOB is 12 March 1985" triggers retraction and `users.date_of_birth` updates.
- [ ] **T13 (multi-job capture)** — at employment, complete one job. Fyn asks "Any other jobs to add?". Answer yes → loops back. Answer no → advances to expenditure.
- [ ] **T14 (employment bubble sanity)** — employment bubble list in UI contains "Full-time" and does NOT contain "Other" or "Employed".
- [ ] **T15 (profile review pause — expenditure)** — after expenses answered, chat shrinks, profile shows full picture (personal + family + employment + expenses). Confirmation advances to journey-specific asset_capture (retirement users hit pension-focused prompts, protection users hit protection-focused, etc.).
- [ ] **T16 (retraction)** — at any mid-flow point, type "Actually I'm married" after initially saying single. Fyn emits `update_profile`, `users.marital_status` flips to `married`, and Fyn replies "Got it — updated from single to married." DB confirms.
- [ ] **T17 (prompt clear at pauses)** — inspect `ai_conversations.metadata.accumulated_prompt_tokens` immediately after entering each review state — should be 0, confirming the token lifecycle reset.
- [ ] **T18 (journey handover)** — retirement-journey user finishing expenditure review sees asset_capture turn invoked with `CaptureContext.originating_focus = 'retirement'` (verify via log or DB-recorded `persona_state.capture_context`).
- [ ] **T19 (conversation memory)** — in the very first onboarding turn, the user says *"I'm 40, married to Jane, two kids — Sam 8 and Eli 6"*. Fyn captures personal details, then at `base_spouse` skips the spouse question (auto-advances after noting "I've got your spouse details from earlier"), and at `base_dependants` asks for NAMES/DOBs rather than "do you have dependants?".
- [ ] **T20 (resume — welcome back)** — register a new user, walk onboarding to `base_employment`, log out. Log back in, visit `/onboarding/fyn`. Fyn greets: *"Welcome back, {firstName}. Last time we were on your employment..."*, shows Continue and Start over bubbles. Clicking Continue picks up at `base_employment`; clicking Start over resets to `path_choice` and clears prior messages.
- [ ] **T21 (fact parking — Angela scenario)** — at `base_personal`, user types *"I'm 40, married to Angela, 45"*. `ai_conversations.onboarding_parked_facts` shows `personal.marital_status = married` and `spouse.first_name = Angela, spouse.age_hint = 45`. At the next turn (`base_spouse`), Fyn emits *"Thanks for letting me know about Angela (age 45). Could I get their email? That'll let me set up a linked account for them."* — NOT a full "tell me about your spouse" question. After the user provides the email, the spouse User is created via `SpouseLinkingService::createFromParkedFacts()` with name, DOB (approx from age), and email all populated.
- [ ] **T22 (fact parking — employment pre-volunteered)** — user says *"I'm 40 and I work at Barclays full-time on £85k"* during `base_personal`. Parking shows `employment.company_hint = Barclays, status_hint = full_time, salary_hint = 85000`. At `base_employment`, Fyn either (a) advances silently after creating the employment record, or (b) asks only for the gap (e.g. end date if salary context needs refinement). No re-asking "Where do you work?".

## Rollback

If any smoke test fails critically:

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
# edit .env: FYN_PERSONA_SPLIT=false
php artisan config:cache
```

The columns `ai_messages.persona` and `ai_conversations.persona_state` remain, but no new code writes them while the flag is off. Existing data is untouched.
```

- [ ] **Step 3: Mirror to the vault**

```bash
cp April/April21Updates/deploy-fyn-persona-split.md /Users/CSJ/Desktop/fynlaBrain/April/April21Updates/deploy-fyn-persona-split.md
```

- [ ] **Step 4: Commit the deploy guide**

```bash
git add April/April21Updates/deploy-fyn-persona-split.md
git commit -m "docs(deploy): Fyn persona split dev deploy guide"
```

- [ ] **Step 5: Invoke the vault-sync skill**

At the end of this release, run `/vault-sync` to update the fynlaBrain vault Index, Home, and Git History entries for April 21.

---

## Self-review notes

**Spec coverage check:**
- Problem / Goals / Non-goals → addressed by Phases 1–14 collectively; non-goals explicitly excluded.
- Decisions (mode fork, routing, tool ownership, handoff, UX) → Phases 3 (builders), 4 (handoff tools), 6 (orchestrator), 7 (classifier), 10 (onboarding UX), 12 (chat UI).
- Architecture diagram → Phase 6 (orchestrator + invoker), Phase 9 (onboarding mode).
- Components (new/renamed/migrated/unchanged) → Phases 1–11 map 1:1.
- Handoff contract → Task 8 (definitions), Task 14 (interception).
- Persistence (two migrations) → Phase 2.
- Error handling (malformed, timeout, cancel, rollback) → Task 14 + Task 20.
- Onboarding migration (3-step) → Tasks 21, 22, 34, 35.
- Onboarding UX enhancements (wide chat, pauses, skip, retract, multi-job, journey handover, memory, resume, fact parking) → Phase 10 (Tasks 23–33).
- Classifier fast-path → Tasks 15–17.
- Chat UI → Tasks 36–38 (composed on top of Phase 10's onboarding-specific UI).
- Preview mode → Task 39.
- Testing (unit + feature + classifier + onboarding + UI + estate tools + regression) → covered across every phase; full suite run at the end of Task 35.
- Feature flag + rollout → Task 1 (flag), Task 40 (deploy guide).
- New tools required (Will, LPA) → Phase 5 (Tasks 10–12).

**Placeholder scan:** no TBD / TODO / "similar to" / "handle appropriately" left in plan. Every code step has full code.

**Type consistency:** `CaptureContext` constructor / `fromArray` / `toArray` shape is consistent across Tasks 2, 7, 14, 21, 25. `FynPersonaRegistry` const names (`ADVICE`, `DATA_CAPTURE`) used consistently. `HandoffContract::DELEGATE_TO_CAPTURE` / `CAPTURE_COMPLETE` used consistently. State machine constants (`STATE_PROFILE_REVIEW_FAMILY`, `STATE_PROFILE_REVIEW_EXPENDITURE`, `STATE_BASE_EMPLOYMENT_MORE`) introduced in Task 23–24 and consumed in Tasks 25, 31–32.

**Ordering note:** Tasks 27 (Vuex `onboardingLayout`) and 33 (Vuex `personaMode`) both touch `resources/js/store/modules/aiChat.js`. Do 27 first (earlier in plan order); Task 33 then adds `personaMode` alongside the existing `onboardingLayout` rather than rewriting. Same pattern for Tasks 29 + 34 (ChatWindow) and Tasks 30 + 35 (MessageBubble) — Phase 10 establishes the file, Phase 12 layers on.

**Known gap:** Task 13 references `CoordinatingAgent::chatWithPromptOverride()` and `CoordinatingAgent::buildAdvicePrompt()`. The former is mentioned in `OnboardingChatDirector` today — confirm the exact signature when implementing; the helper in Task 13 Step 4 may need to adjust parameters to match. The latter is added in Task 13 Step 4 if it doesn't already exist.
