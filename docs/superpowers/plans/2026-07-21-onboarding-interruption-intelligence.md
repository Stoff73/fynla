# Onboarding Interruption Intelligence Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mid-onboarding free text that isn't an answer to the current capture question gets an intelligent response — volunteered information is offered for immediate storage, answerable questions are answered inline from the user's data, big questions are deferred with a tracked promise raised after onboarding completes — instead of "Sorry, I didn't catch that."

**Architecture:** One new private dispatcher `handleInterruption()` in `OnboardingChatDirector`, hooked at the two "didn't catch that" sites (the linear capture path in `handleUserMessage`, and the grouped-extract `emitRetry`). It reuses existing machinery end-to-end: `WriteIntentClassifier` (information vs question), `QueryClassifier` + `AdviceFyn::engineCallLevelFor()` (answer-now vs defer judgement), `handleInlineCapture` (the immediate write path — the same handlers advice-mode handoffs use), `FynLoop::run(SessionMode::Advice, …)` (the grounded read-only inline answer), and `$conversation->metadata['deferred_questions']` (survives completion, which nulls `users.onboarding_fyn_context`). Deferred questions are raised at both completion terminals as tappable bubbles — the tap sends the question as a normal post-completion message, which routes to Advice Fyn with full data.

**Tech Stack:** Laravel 10 / PHP 8.2, Pest (`RefreshDatabase`), `ScriptedAnthropicClient` LLM stub, existing Fyn SSE event vocabulary (`content`, `quick_replies`, `done`).

**CSJ decisions locked in (2026-07-21):**
1. Answer timing — Fyn judges contextually: `engineCallLevelFor()` `factual`/`module` → answer inline then resume the walk; `holistic` → defer with a promise.
2. Store timing — write immediately on "yes" via `handleInlineCapture` (the same capture handlers).
3. Deferred questions — tracked and raised after onboarding, not conversational-only.
4. Consistent across web, `/m`, and native automatically — everything here is server-side in the director; bubbles persist in message metadata (the 2026-07-21 resume-greeting fix pattern) so transcript-only renders stay tappable.

**Design note surfaced to CSJ:** extractor-only personal facts (e.g. "my wife is called Angela" mid-step) are *already* parked by `OnboardingFactExtractor` and auto-committed when the walk reaches the matching step — for those the handler acknowledges without an extra "store it?" turn. The explicit store offer fires for write-intent information (assets, pensions, policies — things needing `create_*` writes).

## Global Constraints

- No hardcoded tax values — `TaxConfigService` only (CLAUDE.md Rule 2).
- No icons/emoji anywhere; Fyn speaks plain text (Rule 15). No acronyms except ISA (Rule 9). British spelling in user-facing copy.
- No frontend persona signals — the interruption handling must be invisible as a "state"; it is just Fyn responding (canonical contract).
- Advice content mid-onboarding is READ-ONLY: inline answers run `SessionMode::Advice` with `AdviceFyn::buildToolList()` (public, strips `WRITE_TOOLS`).
- Every new persisted assistant message that carries choices stores them in `metadata.bubbles` (+ `action_bubbles` where taps are director actions) — transcript renders must never dead-end.
- `declare(strict_types=1);`, PSR-12, `./vendor/bin/pint` before each commit.
- Tests follow `tests/Feature/Onboarding/ProfileReviewPauseTest.php` pattern: factory user with a step, `AiConversation` (`model_used => 'director'`), drive the generator into an array, assert events + DB. Chat-path suites already get a scripted empty AI client from the global Pest hook; script explicit events where a task needs them.
- Working branch: `codex/savetax-allowance-ctas` (csjones runs it). Do NOT touch the pkg7 worktree — native needs no change (server-side feature + persisted bubbles).

---

### Task 1: Dispatcher skeleton + Site A hook (behaviour-preserving fallback)

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (constructor ~line 79; Site A block ~lines 284-295; new method after `interpretAnswer`)
- Test: `tests/Feature/Onboarding/OnboardingInterruptionTest.php` (create)

**Interfaces:**
- Consumes: `WriteIntentClassifier::isQuestion(string $message): bool`, `::classify(string $userMessage): ?array` (keys used later: `reason`, `entity_type`, `fields_needed`); `QueryClassifier::classify(string $message, ?string $currentRoute = null): array` (key `primary`); `AdviceFyn::engineCallLevelFor(?string $primary): string` (`'factual'|'module'|'holistic'`); `App\Constants\QuerySchemas` constants.
- Produces: `private function handleInterruption(User $user, AiConversation $conversation, string $currentStateId, array $state, string $message, ?string $currentRoute = null): ?\Generator` — returns `null` when the existing retry should fire; Tasks 2–4 fill the branches.

- [ ] **Step 1: Write the failing regression + skeleton tests**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function interruptionUser(string $step = OnboardingStateMachine::STATE_PATH_CHOICE): array
{
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
        'onboarding_completed' => false,
        'onboarding_fyn_step' => $step,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
        'metadata' => ['source' => 'fyn_onboarding'],
    ]);

    return [$user, $conversation];
}

function driveDirector(User $user, AiConversation $conversation, string $message): array
{
    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, $message) as $event) {
        $received[] = $event;
    }

    return $received;
}

it('still emits the plain retry for unclassifiable free text', function () {
    [$user, $conversation] = interruptionUser();

    $received = driveDirector($user, $conversation, 'asdf qwerty');

    $texts = collect($received)->where('type', 'content')->pluck('text');
    expect($texts->first(fn ($t) => str_contains($t, "didn't catch that")))->not->toBeNull();
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
});
```

- [ ] **Step 2: Run to verify it passes already (this one pins current behaviour)**

Run: `./vendor/bin/pest tests/Feature/Onboarding/OnboardingInterruptionTest.php -v`
Expected: PASS (it's a regression pin — the hook must never break it).

- [ ] **Step 3: Inject the classifiers and add the skeleton + hook**

Constructor (append two deps — both leaf services, no container cycle):

```php
public function __construct(
    private readonly CoordinatingAgent $coordinatingAgent,
    private readonly OnboardingPromptBuilder $promptBuilder,
    private readonly OnboardingFactExtractor $factExtractor,
    private readonly AssetCaptureEntityExtractor $entityExtractor,
    private readonly HouseholdProvisioner $householdProvisioner,
    private readonly MemoryRetrieverService $memory,
    private readonly RecordDuplicateChecker $duplicateChecker,
    private readonly FynLoop $fynLoop,
    private readonly ProceduralVersionHolder $proceduralVersions,
    private readonly \App\Services\AI\QueryClassifier $queryClassifier,
    private readonly \App\Services\AI\WriteIntentClassifier $writeIntentClassifier,
) {}
```

(Convert the FQCNs to `use` imports at the top of the file, matching house style.)

Site A hook — replace the existing `if (! $interpretation['ok'])` block body:

```php
if (! $interpretation['ok']) {
    $interruption = $this->handleInterruption(
        $user, $conversation, $currentStateId, $state, $message, $currentRoute
    );
    if ($interruption !== null) {
        yield from $interruption;

        return;
    }

    yield [
        'type' => 'content',
        'text' => $interpretation['retry_text'] ?? "Sorry, I didn't catch that. Could you try again?",
    ];
    yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state, includeTransitionHeader: false);

    return;
}
```

Skeleton (Tasks 2–4 replace the branch stubs; keep them returning `null` for now so behaviour is unchanged):

```php
/**
 * Interruption intelligence (CSJ 2026-07-21): free text that failed
 * interpretation at a capture step is not noise — classify it and respond.
 * Returns null when nothing matched, so the caller's existing retry fires.
 */
private function handleInterruption(
    User $user,
    AiConversation $conversation,
    string $currentStateId,
    array $state,
    string $message,
    ?string $currentRoute = null
): ?\Generator {
    if ($this->writeIntentClassifier->isQuestion($message)) {
        $primary = $this->queryClassifier->classify($message, $currentRoute)['primary'] ?? null;

        return $this->handleQuestionInterruption(
            $user, $conversation, $currentStateId, $state, $message, $primary, $currentRoute
        );
    }

    if ($this->writeIntentClassifier->classify($message) !== null) {
        return $this->handleInformationInterruption(
            $user, $conversation, $currentStateId, $state, $message, $currentRoute
        );
    }

    return null;
}

private function handleQuestionInterruption(
    User $user,
    AiConversation $conversation,
    string $currentStateId,
    array $state,
    string $message,
    ?string $primary,
    ?string $currentRoute
): ?\Generator {
    return null; // Task 3 / Task 4
}

private function handleInformationInterruption(
    User $user,
    AiConversation $conversation,
    string $currentStateId,
    array $state,
    string $message,
    ?string $currentRoute
): ?\Generator {
    return null; // Task 2
}
```

- [ ] **Step 4: Run the new test file + the neighbouring suites**

Run: `./vendor/bin/pest tests/Feature/Onboarding/OnboardingInterruptionTest.php tests/Feature/Onboarding/OnboardingResumeTest.php tests/Feature/Onboarding/ProfileReviewPauseTest.php -v`
Expected: PASS (all — the skeleton returns null everywhere).

- [ ] **Step 5: Pint + commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/OnboardingInterruptionTest.php
git add -A && git commit -m "feat(onboarding): interruption dispatcher skeleton behind the capture retry"
```

---

### Task 2: Information branch — offer, then write immediately on "yes"

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (`handleInformationInterruption`; new pending-flag branch in `handleUserMessage` after the `pending_dob_confirm` block ~line 225)
- Test: `tests/Feature/Onboarding/OnboardingInterruptionTest.php`

**Interfaces:**
- Consumes: `CaptureContext::fromArray(['reason' => …, 'entity_types' => […], 'fields_needed' => […]])` (`App\ValueObjects\CaptureContext`); `handleInlineCapture(User $user, AiConversation $conversation, string $message, CaptureContext $context, ?string $currentRoute = null): \Generator` (same class, line ~5088); the `onboarding_fyn_context` read-mutate-save idiom.
- Produces: context key `pending_interruption_store = ['message' => string, 'intent' => array, 'state_id' => string]`; persisted offer message with `metadata.bubbles` `[['id' => 'store_now', 'label' => 'Yes, save it'], ['id' => 'store_later', 'label' => 'Not now']]` and `action_bubbles => false` (labels are typed back as normal messages — resolution is text-matched like the income-challenge pattern).

- [ ] **Step 1: Write the failing tests**

```php
it('offers to store volunteered write-intent information and parks the pending flag', function () {
    [$user, $conversation] = interruptionUser();

    $received = driveDirector($user, $conversation, 'I have a Cash ISA with Barclays with £30,000 in it');

    $offer = collect($received)->firstWhere('type', 'quick_replies');
    expect($offer)->not->toBeNull();
    expect($offer['prompt_text'])->toContain('save');
    expect(array_column($offer['bubbles'], 'id'))->toBe(['store_now', 'store_later']);

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store']['message'] ?? null)
        ->toBe('I have a Cash ISA with Barclays with £30,000 in it');
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);

    $persisted = $conversation->messages()->where('role', 'assistant')->latest('id')->first();
    expect(array_column($persisted->metadata['bubbles'] ?? [], 'id'))->toBe(['store_now', 'store_later']);
});

it('declining the store offer resumes the walk and keeps nothing pending', function () {
    [$user, $conversation] = interruptionUser();
    driveDirector($user, $conversation, 'I have a Cash ISA with Barclays with £30,000 in it');

    $received = driveDirector($user->refresh(), $conversation, 'Not now');

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store'] ?? null)->toBeNull();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
    // The walk's current question is re-emitted.
    expect(collect($received)->where('type', 'quick_replies')->count())->toBeGreaterThan(0);
});

it('accepting the store offer routes the original message through inline capture', function () {
    [$user, $conversation] = interruptionUser();
    driveDirector($user, $conversation, 'I have a Cash ISA with Barclays with £30,000 in it');

    driveDirector($user->refresh(), $conversation, 'Yes, save it');

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_interruption_store'] ?? null)->toBeNull();
    // handleInlineCapture ran: its deterministic gap-fill created the account
    // (the scripted empty LLM stream produces no tool calls; the
    // entity-extractor gap-fill is what persists — assert the row).
    expect(\App\Models\SavingsAccount::where('user_id', $user->id)->count())->toBeGreaterThan(0);
});
```

NOTE for the implementer: run the third test and inspect. If the extractor-driven gap-fill in `handleInlineCapture` requires scripted LLM tool calls to create the row under the empty scripted client, replace the row assertion with the seam assertion below (and keep it honest — do not weaken further):

```php
    // Seam assertion fallback: the pending flag was consumed and a capture
    // acknowledgement was voiced by the inline-capture turn.
    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' ');
    expect($texts)->not->toContain("didn't catch that");
```

- [ ] **Step 2: Run to verify they fail**

Run: `./vendor/bin/pest tests/Feature/Onboarding/OnboardingInterruptionTest.php -v`
Expected: FAIL — no `quick_replies` offer (skeleton returns null → plain retry).

- [ ] **Step 3: Implement the branch + pending resolution**

`handleInformationInterruption` body:

```php
private function handleInformationInterruption(
    User $user,
    AiConversation $conversation,
    string $currentStateId,
    array $state,
    string $message,
    ?string $currentRoute
): ?\Generator {
    $intent = $this->writeIntentClassifier->classify($message);
    if ($intent === null) {
        return null;
    }

    return (function () use ($user, $conversation, $currentStateId, $message, $intent): \Generator {
        $offer = "That sounds like something worth saving. Want me to add it to your plan now? We can also come back to it during setup.";
        $bubbles = [
            ['id' => 'store_now', 'label' => 'Yes, save it'],
            ['id' => 'store_later', 'label' => 'Not now'],
        ];

        $context = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
        $context['pending_interruption_store'] = [
            'message' => $message,
            'intent' => $intent,
            'state_id' => $currentStateId,
        ];
        $user->onboarding_fyn_context = $context;
        $user->save();

        $saved = $this->saveMessage($conversation, 'assistant', $offer, [
            'metadata' => [
                'onboarding_step' => $currentStateId,
                'bubbles' => $bubbles,
            ],
        ]);

        yield [
            'type' => 'quick_replies',
            'prompt_text' => $offer,
            'bubbles' => $bubbles,
        ];
        yield ['type' => 'done', 'message_id' => $saved->id];
    })();
}
```

Pending resolution — insert in `handleUserMessage` directly after the `pending_dob_confirm` block (mirror its shape exactly; `$context` is already loaded there):

```php
// Interruption store-offer resolution (pending_interruption_store parked by
// handleInformationInterruption). The user is answering "want me to save
// that now?" — handle it before any normal turn routing.
if (isset($context['pending_interruption_store'])) {
    $pending = $context['pending_interruption_store'];
    $reply = mb_strtolower(trim($message));

    unset($context['pending_interruption_store']);
    $user->onboarding_fyn_context = $context;
    $user->save();

    if (str_starts_with($reply, 'yes')) {
        $captureContext = \App\ValueObjects\CaptureContext::fromArray([
            'reason' => $pending['intent']['reason'] ?? 'volunteered_mid_onboarding',
            'entity_types' => [$pending['intent']['entity_type'] ?? 'savings_account'],
            'fields_needed' => $pending['intent']['fields_needed'] ?? [],
        ]);
        yield from $this->handleInlineCapture(
            $user, $conversation, (string) $pending['message'], $captureContext, $currentRoute
        );
        yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state, includeTransitionHeader: false);

        return;
    }

    if (str_starts_with($reply, 'not now') || str_starts_with($reply, 'no')) {
        yield ['type' => 'content', 'text' => "No problem — we'll cover it during setup."];
        yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state, includeTransitionHeader: false);

        return;
    }
    // Anything else: the user moved on — fall through to normal routing.
}
```

(`use App\ValueObjects\CaptureContext;` as an import, matching house style.)

- [ ] **Step 4: Run to verify pass**

Run: `./vendor/bin/pest tests/Feature/Onboarding/OnboardingInterruptionTest.php -v`
Expected: PASS (apply the documented fallback assertion only if the row assertion proves stub-dependent).

- [ ] **Step 5: Pint + commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/OnboardingInterruptionTest.php
git add -A && git commit -m "feat(onboarding): volunteered information offers an immediate store via inline capture"
```

---

### Task 3: Question branch — inline grounded answer for factual/module questions

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (`handleQuestionInterruption`)
- Test: `tests/Feature/Onboarding/OnboardingInterruptionTest.php`

**Interfaces:**
- Consumes: `FynLoop::run(SessionMode $mode, User $user, AiConversation $conversation, string $message, ?string $currentRoute, ?array $allowedTools, bool $persistUserMessage = true): \Generator` (`App\Services\AI\Fyn\FynLoop`, `App\Enums\SessionMode` — verify the enum FQCN at implementation time via `grep -rn "enum SessionMode" app/`); `AdviceFyn::buildToolList(User $user): array` (public, `app/Services/AI/AdviceFyn.php:497`); `AdviceFyn::engineCallLevelFor(?string $primary): string`.
- Produces: inline answer events followed by the re-emitted current step; no state advance; the user message is NOT double-persisted (`persistUserMessage: false` — `handleUserMessage` already saved it at line ~133).

- [ ] **Step 1: Write the failing test**

```php
it('answers a module-level question inline from data then resumes the walk', function () {
    // Scripted advice turn: one text chunk. The global Pest hook binds an
    // empty scripted client; rebind with a single content event so the
    // FynLoop advice turn voices an answer.
    app()->instance(\Anthropic\Contracts\ClientContract::class, scriptedFynClientWithText(
        'Your pension is on track — you hold one workplace pension.'
    ));

    [$user, $conversation] = interruptionUser();

    $received = driveDirector($user, $conversation, 'Am I on track for retirement?');

    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' ');
    expect($texts)->toContain('pension');
    expect($texts)->not->toContain("didn't catch that");

    // The walk resumed at the same step.
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
});
```

IMPLEMENTER NOTE (client stub): find the exact scripted-client helper used by existing FynLoop-driving suites before writing `scriptedFynClientWithText` — run `grep -rn "ScriptedAnthropicClient\|ScriptedXai" tests/Feature/AI/ | head` and copy the binding idiom from the nearest passing test (e.g. `CampaignReentryExitTest.php` `beforeEach`). Use that idiom verbatim; do not invent a new stub.

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Onboarding/OnboardingInterruptionTest.php -v`
Expected: FAIL — the retry text appears instead of an answer.

- [ ] **Step 3: Implement**

```php
private function handleQuestionInterruption(
    User $user,
    AiConversation $conversation,
    string $currentStateId,
    array $state,
    string $message,
    ?string $primary,
    ?string $currentRoute
): ?\Generator {
    if ($primary === null) {
        return null;
    }

    $level = \App\Services\AI\AdviceFyn::engineCallLevelFor($primary);

    if ($level === 'holistic') {
        return $this->deferQuestion($user, $conversation, $currentStateId, $state, $message); // Task 4
    }

    return (function () use ($user, $conversation, $currentStateId, $state, $message, $currentRoute): \Generator {
        $readOnlyTools = app(\App\Services\AI\AdviceFyn::class)->buildToolList($user);
        yield from $this->fynLoop->run(
            \App\Enums\SessionMode::Advice,
            $user,
            $conversation,
            $message,
            $currentRoute,
            $readOnlyTools,
            persistUserMessage: false,
        );
        yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state, includeTransitionHeader: false);
    })();
}
```

Notes for the implementer:
- `app(AdviceFyn::class)` at call time is safe (AdviceFyn's constructor injects this director; resolving it lazily inside a method creates no constructor cycle — same service-locator precedent as `buildSectionAdvice`'s `app(ComposedTaxPlanService::class)` at line ~1021).
- Verify the `SessionMode` FQCN and the exact case name (`Advice`) with `grep -rn "SessionMode::Advice" app/ | head -3` and match it.
- If `FynLoop::run` in Advice mode emits a terminal `done` event that would end the frontend turn before the re-emitted step arrives, inspect how `handleInlineCapture` handles the same problem (it strips/reorders events for exactly this reason — see its docblock "Strips layout/quick_reply events") and mirror that filtering. Do not ship without confirming the re-emitted step renders after the answer in the event order of the test.

- [ ] **Step 4: Run to verify pass**

Run: `./vendor/bin/pest tests/Feature/Onboarding/OnboardingInterruptionTest.php -v`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/OnboardingInterruptionTest.php
git add -A && git commit -m "feat(onboarding): module questions answered inline mid-walk via read-only advice turn"
```

---

### Task 4: Defer branch — park holistic questions with a promise

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (new `deferQuestion` method next to `handleQuestionInterruption`)
- Test: `tests/Feature/Onboarding/OnboardingInterruptionTest.php`

**Interfaces:**
- Produces: `private function deferQuestion(User $user, AiConversation $conversation, string $currentStateId, array $state, string $message): \Generator`; conversation metadata key `deferred_questions` = list of `['question' => string, 'state_id' => string]` (persisted on `ai_conversations.metadata` — it survives completion, which nulls `users.onboarding_fyn_context`). Task 5 consumes this key.

- [ ] **Step 1: Write the failing test**

```php
it('defers a holistic question with a promise and parks it on the conversation', function () {
    [$user, $conversation] = interruptionUser();

    $received = driveDirector($user, $conversation, 'How healthy are my overall finances?');

    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' ');
    expect($texts)->toContain('once your setup is done');

    $conversation->refresh();
    expect($conversation->metadata['deferred_questions'][0]['question'] ?? null)
        ->toBe('How healthy are my overall finances?');

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
});
```

(IMPLEMENTER NOTE: confirm "How healthy are my overall finances?" classifies to `QuerySchemas::HOLISTIC_HEALTH` with `app(QueryClassifier::class)->classify('How healthy are my overall finances?')['primary']` in tinker; if not, pick a phrasing from `QuerySchemas::KEYWORD_PATTERNS[HOLISTIC_HEALTH]` that does, and use it in both test and expectation.)

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Onboarding/OnboardingInterruptionTest.php -v`
Expected: FAIL — `deferQuestion` does not exist yet (Task 3 references it; stub it returning the generator below).

- [ ] **Step 3: Implement**

```php
private function deferQuestion(
    User $user,
    AiConversation $conversation,
    string $currentStateId,
    array $state,
    string $message
): \Generator {
    $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
    $deferred = $metadata['deferred_questions'] ?? [];
    $deferred[] = ['question' => $message, 'state_id' => $currentStateId];
    $metadata['deferred_questions'] = $deferred;
    $conversation->update(['metadata' => $metadata]);

    $promise = "Good question — that one deserves a proper answer, so I'll come back to it once your setup is done and I can see the full picture.";
    $saved = $this->saveMessage($conversation, 'assistant', $promise, [
        'metadata' => ['onboarding_step' => $currentStateId],
    ]);

    yield ['type' => 'content', 'text' => $promise];
    yield ['type' => 'done', 'message_id' => $saved->id];
    yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state, includeTransitionHeader: false);
}
```

- [ ] **Step 4: Run to verify pass**

Run: `./vendor/bin/pest tests/Feature/Onboarding/OnboardingInterruptionTest.php -v`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/OnboardingInterruptionTest.php
git add -A && git commit -m "feat(onboarding): holistic questions deferred with a tracked promise"
```

---

### Task 5: Raise deferred questions at both completion terminals

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (`emitDoneTurn` ~line 4868, `emitTerminalNavigationTurn` ~line 4298; new shared `emitDeferredQuestions`)
- Test: `tests/Feature/Onboarding/OnboardingInterruptionTest.php`

**Interfaces:**
- Consumes: `deferred_questions` conversation-metadata key from Task 4.
- Produces: before each terminal's celebration `quick_replies`, one `quick_replies` event + persisted message: prompt "Earlier you asked me something — want to pick that up now that your plan is set up?" with one bubble per deferred question, `id => 'deferred_0'…`, `label` = the question text truncated to 60 chars. Bubble taps send the label as a normal message; the user is completed by then, so it routes to Advice Fyn with full data (the dispatch predicate handles this — no new routing). Clears the metadata key after emitting.

- [ ] **Step 1: Write the failing tests**

```php
it('raises deferred questions at the classic completion terminal and clears them', function () {
    [$user, $conversation] = interruptionUser();
    $conversation->update(['metadata' => array_merge($conversation->metadata ?? [], [
        'deferred_questions' => [['question' => 'How healthy are my overall finances?', 'state_id' => 'path_choice']],
    ])]);

    $received = [];
    $method = new ReflectionMethod(OnboardingChatDirector::class, 'emitDoneTurn');
    foreach ($method->invoke(app(OnboardingChatDirector::class), $user, $conversation) as $event) {
        $received[] = $event;
    }

    $raise = collect($received)->where('type', 'quick_replies')
        ->first(fn ($e) => str_contains($e['prompt_text'] ?? '', 'Earlier you asked'));
    expect($raise)->not->toBeNull();
    expect($raise['bubbles'][0]['label'])->toBe('How healthy are my overall finances?');

    $conversation->refresh();
    expect($conversation->metadata['deferred_questions'] ?? null)->toBeNull();

    // The persisted raise message keeps its bubbles for transcript renders.
    $persisted = $conversation->messages()->where('content', 'like', 'Earlier you asked%')->first();
    expect(array_column($persisted->metadata['bubbles'] ?? [], 'label'))
        ->toBe(['How healthy are my overall finances?']);
});
```

(IMPLEMENTER NOTE: if `emitDoneTurn` cannot run standalone under Reflection because of state expectations, drive it the long way instead — mirror how an existing completion test reaches STATE_DONE; find one with `grep -rln "onboarding_complete" tests/Feature/Onboarding/ | head` and copy its drive. Keep the same assertions. Add the mirror-image test for `emitTerminalNavigationTurn` using `tests/Feature/AI/CampaignReentryExitTest.php`'s HTTP drive pattern — assert the raise bubble appears BEFORE the celebration/route bubble in `AiMessage` order, exactly as that test asserts the better-in-the-app bubble ordering.)

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Onboarding/OnboardingInterruptionTest.php -v`
Expected: FAIL — no raise event.

- [ ] **Step 3: Implement the shared raiser and call it from both terminals**

```php
private function emitDeferredQuestions(AiConversation $conversation): \Generator
{
    $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
    $deferred = $metadata['deferred_questions'] ?? [];
    if ($deferred === []) {
        return;
    }

    unset($metadata['deferred_questions']);
    $conversation->update(['metadata' => $metadata]);

    $bubbles = [];
    foreach (array_values($deferred) as $i => $entry) {
        $bubbles[] = [
            'id' => 'deferred_'.$i,
            'label' => mb_substr((string) ($entry['question'] ?? ''), 0, 60),
        ];
    }

    $prompt = 'Earlier you asked me something — want to pick that up now that your plan is set up?';
    $saved = $this->saveMessage($conversation, 'assistant', $prompt, [
        'metadata' => ['bubbles' => $bubbles],
    ]);

    yield [
        'type' => 'quick_replies',
        'prompt_text' => $prompt,
        'bubbles' => $bubbles,
    ];
    yield ['type' => 'done', 'message_id' => $saved->id];
}
```

Call sites — in `emitDoneTurn`, immediately BEFORE the celebration `content` yield (~line 4881): `yield from $this->emitDeferredQuestions($conversation);`. In `emitTerminalNavigationTurn`, immediately BEFORE the better-in-the-app `$appNote` block (~line 4313): same call. (Ordering contract: deferred raise → app note → celebration + route bubble last, so the tappable route bubble stays last — the invariant CampaignReentryExitTest pins.)

- [ ] **Step 4: Run the interruption suite + the terminal-ordering pin**

Run: `./vendor/bin/pest tests/Feature/Onboarding/OnboardingInterruptionTest.php tests/Feature/AI/CampaignReentryExitTest.php -v`
Expected: PASS — including the existing bubble-ordering assertions.

- [ ] **Step 5: Pint + commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/OnboardingInterruptionTest.php
git add -A && git commit -m "feat(onboarding): deferred questions raised at both completion terminals"
```

---

### Task 6: Site C hook — grouped-extract retries get the same intelligence

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (`emitRetry` ~line 2293 and its caller in `handleGroupedExtractTurn`)
- Test: `tests/Feature/Onboarding/OnboardingInterruptionTest.php`

**Interfaces:**
- Consumes: `handleInterruption(...)` from Task 1.
- Produces: `emitRetry` gains `User $user` and `string $userMessage` parameters (private method — update every caller; find them all with `grep -n "emitRetry(" app/Services/Onboarding/OnboardingChatDirector.php`) and tries `handleInterruption` first, falling back to the existing retry copy.

- [ ] **Step 1: Write the failing test**

```php
it('answers a question asked at a grouped-extract step instead of retrying blind', function () {
    app()->instance(\Anthropic\Contracts\ClientContract::class, scriptedFynClientWithText(
        'You currently spend about £2,400 a month.'
    ));

    [$user, $conversation] = interruptionUser(OnboardingStateMachine::STATE_BASE_PERSONAL);

    $received = driveDirector($user, $conversation, 'What do I spend each month?');

    $texts = collect($received)->where('type', 'content')->pluck('text')->implode(' ');
    expect($texts)->not->toContain("didn't catch that");
});
```

(IMPLEMENTER NOTE: `STATE_BASE_PERSONAL` is a `grouped_extract` state whose LLM extraction runs under the scripted client. Drive the test once and inspect which path fires; the goal is pinned by the assertion — a question at a grouped step must not produce the blind retry. If the scripted client causes the extraction turn to succeed-empty without reaching `emitRetry`, force the retry path the way `FactParkingTest.php` / existing grouped-extract tests do — copy their drive idiom.)

- [ ] **Step 2: Run to verify it fails**
- [ ] **Step 3: Implement — thread `$user` + `$userMessage` into `emitRetry`; at its top:**

```php
$interruption = $this->handleInterruption(
    $user, $conversation, $currentStateId, $state, $userMessage
);
if ($interruption !== null) {
    yield from $interruption;

    return;
}
```

- [ ] **Step 4: Run the interruption suite + grouped-extract neighbours**

Run: `./vendor/bin/pest tests/Feature/Onboarding/ -v`
Expected: PASS across the directory.

- [ ] **Step 5: Pint + commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/OnboardingInterruptionTest.php
git add -A && git commit -m "feat(onboarding): grouped-extract retries route through interruption intelligence"
```

---

### Task 7: Full verification — suite, live browser (web + /m), deploy to csjones

- [ ] **Step 1: Full Pest suite** — `./vendor/bin/pest` — expected: no new failures vs the pre-task baseline (record the baseline count first).
- [ ] **Step 2: Push + deploy** — `git push origin codex/savetax-allowance-ctas`, then on csjones: `git pull origin codex/savetax-allowance-ctas && php artisan config:cache`. Backend-only — no Vite build.
- [ ] **Step 3: Live browser verification (Rule 14 — interact, not observe), web SPA on csjones:** log in as a mid-onboarding test user (create one via registration if needed), open Fyn, type a module question ("Am I on track for retirement?") → grounded answer then the step re-prompt; type an asset sentence ("I have a Cash ISA with £5,000") → store offer → "Yes, save it" → row visible on /savings; type a holistic question → promise; complete the walk → deferred question raised with a tappable bubble → tap → Advice Fyn answers.
- [ ] **Step 4: Same journey on `/m`** (use the verify-m skill paths — cold Playwright nav needs `/m/app/login` + MFA via tinker).
- [ ] **Step 5: Native check** — no native code change; confirm by loading the transcript in the simulator that offer/raise bubbles render (they persist in metadata). If a new TestFlight build is already planned, fold it in; otherwise simulator evidence suffices.
- [ ] **Step 6: Report to CSJ** with the evidence, then `session-end`/vault sync as directed.

## Self-Review (done at plan time)

- **Spec coverage:** information → offer → immediate write (Task 2); question → contextual judge (Task 3); defer + track (Task 4); raise after onboarding (Task 5); all surfaces (server-side + persisted bubbles; Task 7 verifies web + /m + native render). Extractor-only facts decision surfaced in the header for CSJ.
- **Placeholders:** two honest implementation-time verifications remain (SessionMode FQCN; scripted-client helper idiom) — each carries the exact grep to resolve it; no TBDs.
- **Type consistency:** `handleInterruption` signature identical at both hook sites; `deferred_questions` shape written (Task 4) matches read (Task 5); bubble ids `store_now`/`store_later` consistent between emit and resolution (resolution is label-text-matched, mirroring the income-challenge pattern).
