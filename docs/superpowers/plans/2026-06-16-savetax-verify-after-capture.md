# SaveTax Verify-After-Capture + /m Income/Expenditure — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** After each SaveTax campaign onboarding section's capture, run a verify loop (anything-else? → navigate to the section's screen + minimise + nudge → is-this-correct? → edit), and build the two `/m` screens (Income, Expenditure) that two of those sections need as destinations.

**Architecture:** Three *generic* verify states added to `OnboardingStateMachine` — `campaign_verify_more`, `campaign_verify_navigate`, `campaign_verify_edit` — parameterised by the section being verified, which is stamped into `users.onboarding_fyn_context['verify_section']` when the flow is entered. The seven section-end `next` edges (the per-section advice states + giving + expenditure) re-route into the verify flow instead of straight to `nextCampaignSection()`. The verify-navigate state is a `bubbles` state that *also* emits a `navigation` SSE event (a small director extension) so the chat minimises and routes while the "is this correct?" bubbles wait for the user to reopen. Two net-new `/m` Vue screens mirror the existing module-screen pattern.

**Tech Stack:** Laravel 10 (PHP 8.2), Pest, the onboarding state machine + corpus-merged transition table, the `/m` mobile SPA (`resources/mobile/`, isolated Vite build), Sanctum.

**Spec:** `docs/superpowers/specs/2026-06-16-savetax-verify-after-capture-design.md`. Read it first.

**Hard constraints (read before touching the state machine):**
- The transition table is `OnboardingStateMachine::inCodeStates()` **merged with** the corpus YAML `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`. **Every new state must exist in BOTH** or `tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php` (asserts the corpus state-id set == the in-code set, data deep-equal) fails. Callable fields (`next`, callable `prompt_text`, callable `navigate_to`, `skip_if`) are code-only and listed in that test's never-from-corpus exemption set; the corpus entry carries the DATA + a `next` stub (`next: { branch: <method> }`).
- After ANY `resources/mobile/*` change you must rebuild the mobile bundle before browser-testing (no HMR for `/m`). Local: `bash scripts/build-mobile.sh` (local base) — see `reference_mobile_phone_entry_responsive`. csjones verify is the Rule #14 gate.
- Never `migrate:fresh`; `php artisan db:seed` after any migration. (This plan adds no migration.)

---

## File Structure

**Backend (state machine + director):**
- Modify `app/Services/Onboarding/OnboardingStateMachine.php` — add the 3 verify states to `inCodeStates()`, the `campaignVerifyConfig()` map, the `enterCampaignVerify()` helper, and the dynamic `prompt_text`/`navigate_to`/`next` resolver methods; re-route the 7 section-end edges.
- Modify `app/Services/Onboarding/OnboardingChatDirector.php` — `emitTurnForState()` emits a `navigation` event for a `bubbles` state carrying a resolved `navigate_to`.
- Modify `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` — corpus entries for the 3 verify states.

**Frontend (`/m`):**
- Create `resources/mobile/views/Income.vue` — income screen (user + spouse).
- Create `resources/mobile/views/Expenditure.vue` — expenditure screen.
- Modify `resources/mobile/router.js` — `/income`, `/expenditure` routes.
- Modify `resources/mobile/components/MobileChrome.vue` + `resources/mobile/views/Dashboard.vue` — a "Cash Management" drawer-nav group (Income, Expenditure).
- Modify `resources/mobile/views/Dashboard.vue` — confirm the Gate-2 bubble persists across the minimise (verify; tweak only if dropped).

**Tests:**
- `tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php` (new) — state transitions.
- `tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php` (existing) — must stay green.

---

## Task 1: Verify config map (section → route + capture-entry state)

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php` (add a static method near `campaignSections()`, ~line 166)
- Test: `tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php` (create)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\Onboarding\OnboardingStateMachine;

it('maps every navigable campaign section to a route and capture-entry state', function (): void {
    $config = OnboardingStateMachine::campaignVerifyConfig();

    // Charity verifies inline (no route); the rest navigate.
    expect($config['income']['route'])->toBe('/income')
        ->and($config['income']['entry'])->toBe(OnboardingStateMachine::STATE_BASE_EMPLOYMENT)
        ->and($config['savings']['route'])->toBe('/savings')
        ->and($config['investments']['route'])->toBe('/investment')
        ->and($config['pensions']['route'])->toBe('/retirement')
        ->and($config['spouse']['route'])->toBe('/income')
        ->and($config['expenditure']['route'])->toBe('/expenditure')
        ->and($config['giving']['route'])->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php --filter="maps every navigable"`
Expected: FAIL — `Call to undefined method ...::campaignVerifyConfig()`

- [ ] **Step 3: Implement `campaignVerifyConfig()`**

Add to `OnboardingStateMachine.php` immediately after `campaignSections()`:

```php
    /**
     * SaveTax verify sub-flow config: for each campaign section, the /m screen to
     * navigate to for the "is this correct?" confirm (null = inline confirm, no
     * navigation — used for charitable giving) and the section's capture-entry
     * state to loop back to on "anything else to add?". The single source of truth
     * for the per-section verify behaviour; the three generic verify states read
     * the current section from users.onboarding_fyn_context['verify_section'].
     *
     * @return array<string, array{route:?string, entry:string}>
     */
    public static function campaignVerifyConfig(): array
    {
        return [
            'income' => ['route' => '/income', 'entry' => self::STATE_BASE_EMPLOYMENT],
            'savings' => ['route' => '/savings', 'entry' => self::STATE_CAMPAIGN_ISA_HOLDINGS],
            'investments' => ['route' => '/investment', 'entry' => self::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS],
            'pensions' => ['route' => '/retirement', 'entry' => self::STATE_CAMPAIGN_DOB],
            'giving' => ['route' => null, 'entry' => self::STATE_CAMPAIGN_CHARITABLE_GIVING],
            'spouse' => ['route' => '/income', 'entry' => self::STATE_CAMPAIGN_SPOUSE_WORK],
            'expenditure' => ['route' => '/expenditure', 'entry' => self::STATE_BASE_EXPENDITURE],
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php --filter="maps every navigable"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Onboarding/OnboardingStateMachine.php tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php
git commit -m "feat(savetax-verify): campaignVerifyConfig section→route+entry map"
```

---

## Task 2: `enterCampaignVerify()` helper + verify-flow resolver methods

These are the code-only callables the three generic states use. `enterCampaignVerify` stamps the section into context and returns the first verify state. `verifyPromptMore`/`verifyPromptNavigate` build the section-aware prompts. `verifyNavigateRoute` returns the route (or null). `nextFromVerifyMore`/`nextFromVerifyNavigate` are the branch callables.

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php`
- Test: `tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('stamps the verify section into context and enters campaign_verify_more', function (): void {
    $user = \App\Models\User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_context' => [],
    ]);

    $next = OnboardingStateMachine::enterCampaignVerify($user, 'savings');

    expect($next)->toBe('campaign_verify_more')
        ->and($user->fresh()->onboarding_fyn_context['verify_section'])->toBe('savings');
});

it('routes verify_more yes back to the section entry and no to navigate', function (): void {
    $user = \App\Models\User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_context' => ['verify_section' => 'savings'],
    ]);

    expect(OnboardingStateMachine::nextFromVerifyMore('yes', $user))
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS)
        ->and(OnboardingStateMachine::nextFromVerifyMore('no', $user))
        ->toBe('campaign_verify_navigate');
});

it('routes verify_navigate no to edit and yes to the next section', function (): void {
    $user = \App\Models\User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_context' => ['verify_section' => 'giving'],
        'marital_status' => 'single',
    ]);

    expect(OnboardingStateMachine::nextFromVerifyNavigate('no', $user))
        ->toBe('campaign_verify_edit');
    // 'giving' → next section is 'spouse' (skipped: single) → 'expenditure' entry.
    expect(OnboardingStateMachine::nextFromVerifyNavigate('yes', $user))
        ->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php`
Expected: FAIL — undefined methods `enterCampaignVerify` / `nextFromVerifyMore` / `nextFromVerifyNavigate`.

- [ ] **Step 3: Implement the helpers**

Add to `OnboardingStateMachine.php` (near the other `nextFrom*` campaign helpers):

```php
    /** Stamp the section into context and enter the verify sub-flow. */
    public static function enterCampaignVerify(User $user, string $section): string
    {
        $context = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
        $context['verify_section'] = $section;
        $user->onboarding_fyn_context = $context;
        $user->save();

        return 'campaign_verify_more';
    }

    /** The section currently being verified (from context). */
    private static function verifySection(User $user): string
    {
        return (string) (($user->onboarding_fyn_context['verify_section'] ?? '') ?: '');
    }

    /** verify_more: "yes" loops back to the section's capture entry; "no" → navigate. */
    public static function nextFromVerifyMore(string $answer, User $user): string
    {
        if (self::normaliseYesNo($answer) === 'yes') {
            $section = self::verifySection($user);

            return self::campaignVerifyConfig()[$section]['entry'] ?? self::STATE_CAMPAIGN_SYNTHESIS;
        }

        return 'campaign_verify_navigate';
    }

    /** verify_navigate: "no" → edit; "yes" → advance past the verified section. */
    public static function nextFromVerifyNavigate(string $answer, User $user): string
    {
        if (self::normaliseYesNo($answer) === 'no') {
            return 'campaign_verify_edit';
        }

        return self::nextCampaignSection(self::verifySection($user), $user->refresh());
    }

    /** Resolved navigate_to for the verify_navigate state (null = inline confirm). */
    public static function verifyNavigateRoute(User $user): ?string
    {
        $section = self::verifySection($user);

        return self::campaignVerifyConfig()[$section]['route'] ?? null;
    }

    /** Prompt for verify_more, section-aware. */
    public static function verifyPromptMore(array $state, User $user): string
    {
        $label = self::sectionLabel(self::verifySection($user));

        return "Anything else to add to your {$label}?";
    }

    /** Prompt for verify_navigate, section-aware (navigation vs inline confirm). */
    public static function verifyPromptNavigate(array $state, User $user): string
    {
        if (self::verifyNavigateRoute($user) === null) {
            // Inline confirm (charitable giving): no screen.
            return "I've recorded that. Does it look right?";
        }

        return "I've added that — taking you to the screen now. Is this information correct?";
    }

    /** Human label for a campaign section, for verify prompts. */
    private static function sectionLabel(string $section): string
    {
        return [
            'income' => 'income', 'savings' => 'savings', 'investments' => 'investments',
            'pensions' => 'pensions', 'giving' => 'charitable giving', 'spouse' => 'spouse details',
            'expenditure' => 'expenditure',
        ][$section] ?? 'details';
    }
```

> `normaliseYesNo()` already exists for the yes/no bubble states (used by `nextFromEmploymentMore` etc.). If it does not, add a private `normaliseYesNo(string $a): string` returning `'yes'`/`'no'` from the bubble id / free text. Verify by grep before adding: `grep -n "function normaliseYesNo" app/Services/Onboarding/OnboardingStateMachine.php`.

- [ ] **Step 4: Run to verify pass**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php`
Expected: PASS (all cases in this file so far).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Onboarding/OnboardingStateMachine.php tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php
git commit -m "feat(savetax-verify): enterCampaignVerify + verify-flow resolvers"
```

---

## Task 3: The three generic verify states in `inCodeStates()`

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php` (`inCodeStates()` return array — add to the campaign block near `STATE_CAMPAIGN_SYNTHESIS`, ~line 602)
- Test: `tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('defines the three generic verify states with the right turn types', function (): void {
    $m = new ReflectionMethod(OnboardingStateMachine::class, 'inCodeStates');
    $m->setAccessible(true);
    $states = $m->invoke(null);

    expect($states)->toHaveKeys(['campaign_verify_more', 'campaign_verify_navigate', 'campaign_verify_edit'])
        ->and($states['campaign_verify_more']['turn_type'])->toBe('bubbles')
        ->and($states['campaign_verify_navigate']['turn_type'])->toBe('bubbles')
        ->and($states['campaign_verify_edit']['turn_type'])->toBe('delegated')
        ->and($states['campaign_verify_navigate']['navigate_to'])->toBeInstanceOf(Closure::class);
});
```
(Add `use ReflectionMethod;` is unnecessary in Pest closures — fully-qualify `\ReflectionMethod`.)

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php --filter="three generic verify states"`
Expected: FAIL — keys missing.

- [ ] **Step 3: Add the three states**

Insert into the `inCodeStates()` return array, right after the `STATE_CAMPAIGN_SYNTHESIS` entry:

```php
            // ── SaveTax verify sub-flow (generic; section in context) ──────
            // Entered via enterCampaignVerify() which stamps verify_section.
            'campaign_verify_more' => [
                'turn_type' => 'bubbles',
                'prompt_text' => self::class.'::verifyPromptMore',
                'bubbles' => [
                    ['id' => 'yes', 'label' => 'Yes, add more'],
                    ['id' => 'no', 'label' => "No, that's everything"],
                ],
                'capture_field' => null,
                'next' => self::class.'::nextFromVerifyMore',
            ],
            // Bubbles state that ALSO emits a navigation event when navigate_to
            // resolves to a route (director extension): the chat minimises + routes,
            // and the "is this correct?" bubbles wait for the user to reopen.
            // navigate_to is a code-only closure (null for charitable giving =
            // inline confirm, no navigation).
            'campaign_verify_navigate' => [
                'turn_type' => 'bubbles',
                'prompt_text' => self::class.'::verifyPromptNavigate',
                'navigate_to' => fn (User $user): ?string => self::verifyNavigateRoute($user),
                'bubbles' => [
                    ['id' => 'yes', 'label' => "Yes, that's right"],
                    ['id' => 'no', 'label' => 'No, change something'],
                ],
                'capture_field' => null,
                'next' => self::class.'::nextFromVerifyNavigate',
            ],
            'campaign_verify_edit' => [
                'turn_type' => 'delegated',
                'prompt_text' => 'No problem — what needs changing?',
                'capture_field' => null,
                // After the edit is applied, re-show the screen + re-ask "correct?".
                'next' => 'campaign_verify_navigate',
            ],
```

- [ ] **Step 4: Run to verify pass**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php --filter="three generic verify states"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Onboarding/OnboardingStateMachine.php tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php
git commit -m "feat(savetax-verify): three generic verify states"
```

---

## Task 4: Re-route the seven section-end edges into the verify flow

The verify flow is currently unreachable. Re-point each section's "I'm done with this section" `next` from `nextCampaignSection(<section>)` to `enterCampaignVerify($user, '<section>')`.

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php`
- Test: `tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('routes each section-end into the verify flow instead of straight to the next section', function (): void {
    $m = new \ReflectionMethod(OnboardingStateMachine::class, 'inCodeStates');
    $m->setAccessible(true);
    $states = $m->invoke(null);
    $user = \App\Models\User::factory()->create([
        'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_context' => [],
        'marital_status' => 'married',
    ]);

    foreach ([
        OnboardingStateMachine::STATE_CAMPAIGN_ADVICE_INCOME,
        OnboardingStateMachine::STATE_CAMPAIGN_ADVICE_SAVINGS,
        OnboardingStateMachine::STATE_CAMPAIGN_ADVICE_INVESTMENTS,
        OnboardingStateMachine::STATE_CAMPAIGN_ADVICE_PENSIONS,
        OnboardingStateMachine::STATE_CAMPAIGN_ADVICE_SPOUSE,
        OnboardingStateMachine::STATE_CAMPAIGN_CHARITABLE_GIVING,
    ] as $stateId) {
        $next = $states[$stateId]['next'];
        $resolved = is_callable($next) ? $next('', $user) : $next;
        expect($resolved)->toBe('campaign_verify_more', "state {$stateId} should enter verify");
    }
});
```

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php --filter="routes each section-end"`
Expected: FAIL — current `next` resolves to a `STATE_CAMPAIGN_*` entry, not `campaign_verify_more`.

- [ ] **Step 3: Re-route the edges**

In `inCodeStates()`, change these `next` values:

- `STATE_CAMPAIGN_ADVICE_INCOME` (~line 563): `'next' => fn (string $answer, User $user): string => self::enterCampaignVerify($user, 'income'),`
- `STATE_CAMPAIGN_ADVICE_SAVINGS` (~line 569): `... self::enterCampaignVerify($user, 'savings'),`
- `STATE_CAMPAIGN_ADVICE_INVESTMENTS` (~line 575): `... self::enterCampaignVerify($user, 'investments'),`
- `STATE_CAMPAIGN_ADVICE_PENSIONS` (~line 581): `... self::enterCampaignVerify($user, 'pensions'),`
- `STATE_CAMPAIGN_ADVICE_SPOUSE` (~line 592): `... self::enterCampaignVerify($user, 'spouse'),`
- `STATE_CAMPAIGN_CHARITABLE_GIVING` (~line 502): `'next' => fn (string $answer, User $user): string => self::enterCampaignVerify($user, 'giving'),`
- `STATE_BASE_EXPENDITURE` (~line 382, the `campaign` arm of the existing ternary): change `self::nextCampaignSection('expenditure', $user)` to `self::enterCampaignVerify($user, 'expenditure')`. Keep the non-campaign arm (`STATE_PROFILE_REVIEW_EXPENDITURE`) unchanged.

> Note: the verify-navigate "yes" branch (`nextFromVerifyNavigate`) calls `nextCampaignSection(section)` — so advancement still happens, just after the verify gate.

- [ ] **Step 4: Run to verify pass**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php`
Expected: PASS (whole file).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Onboarding/OnboardingStateMachine.php tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php
git commit -m "feat(savetax-verify): route section-ends through the verify flow"
```

---

## Task 5: Corpus entries for the three verify states (golden-master parity)

**Files:**
- Modify: `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`
- Test: `tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php` (existing)

- [ ] **Step 1: Run the parity test to confirm it now fails**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php`
Expected: FAIL — the in-code set now has 3 states the corpus lacks.

- [ ] **Step 2: Add the corpus entries**

Append to the campaign block of `fyn-onboarding.v1.md` (mirror the YAML shape of `campaign_charitable_giving` at line 206; the `next` is a stub — the real callable stays in code; `navigate_to`/callable `prompt_text` are code-only and are NOT written here):

```yaml
campaign_verify_more:
  turn_type: bubbles
  prompt_text: 'Anything else to add?'
  bubbles:
    - { id: yes, label: 'Yes, add more' }
    - { id: no, label: "No, that's everything" }
  capture_field: null
  next: { branch: nextFromVerifyMore }

campaign_verify_navigate:
  turn_type: bubbles
  prompt_text: 'Is this information correct?'
  bubbles:
    - { id: yes, label: "Yes, that's right" }
    - { id: no, label: 'No, change something' }
  capture_field: null
  next: { branch: nextFromVerifyNavigate }

campaign_verify_edit:
  turn_type: delegated
  prompt_text: 'No problem — what needs changing?'
  capture_field: null
  next: { state: campaign_verify_navigate }
```

> The parity test deep-compares the DATA subset (turn_type, bubbles, capture_field, state-id set + order) and re-attaches callable `next`/`prompt_text`/`navigate_to` from code. The corpus `prompt_text` here is the literal-string fallback; the dynamic `self::class.'::verifyPromptMore'` callable in code wins at merge time (callable `prompt_text` is in the never-from-corpus set). If the test reports a `prompt_text` mismatch, add `prompt_text` for these three states to the test's exemption list (the same way other callable-prompt states are handled) — verify how `STATE_BASE_PERSONAL` (callable prompt) is treated and follow it.

- [ ] **Step 3: Reindex + run the corpus + parity tests**

Run:
```
php artisan fyn:pointers:reindex
./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php
```
Expected: PASS (state-id sets equal; data deep-equal).

- [ ] **Step 4: Commit**

```bash
git add fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php
git commit -m "feat(savetax-verify): corpus entries for the verify states (parity)"
```

---

## Task 6: Director — emit `navigation` for a bubbles state carrying `navigate_to`

A `bubbles` state with a resolved `navigate_to` route should emit a `navigation` SSE event (so the chat minimises + routes) **in addition to** its `quick_replies` (so the "is this correct?" bubbles wait for reopen). For charitable giving, `navigate_to` resolves to `null` → no navigation (inline confirm).

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (`emitTurnForState`, the `bubbles` branch, ~line 616–639)
- Test: `tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php`

- [ ] **Step 1: Write the failing test** (drives the SSE stream for the verify_navigate state and asserts a navigation event is present for a navigable section, absent for giving)

```php
it('emits a navigation event for a navigable verify section and none for giving', function (): void {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $director = app(\App\Services\Onboarding\OnboardingChatDirector::class);

    foreach ([['savings', true], ['giving', false]] as [$section, $expectNav]) {
        $user = \App\Models\User::factory()->create([
            'onboarding_fyn_path' => 'campaign', 'onboarding_completed' => false,
            'onboarding_fyn_step' => 'campaign_verify_navigate',
            'onboarding_fyn_context' => ['verify_section' => $section],
        ]);
        $conversation = \App\Models\AiConversation::factory()->create(['user_id' => $user->id]);

        $events = iterator_to_array($director->emitTurnForStateForTest($user, $conversation, 'campaign_verify_navigate'));
        $types = array_column($events, 'type');

        expect(in_array('navigation', $types, true))->toBe($expectNav, "section {$section}");
        expect($types)->toContain('quick_replies');
    }
});
```
> `emitTurnForState` is private; add a thin test seam `public function emitTurnForStateForTest(...)` that forwards to it, OR test through the public `sendMessage` entry that resumes at `onboarding_fyn_step`. Prefer the public path if a seam feels intrusive — check how existing onboarding director tests drive turns (`grep -rn "emitTurnForState\|->sendMessage" tests/`).

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php --filter="emits a navigation event"`
Expected: FAIL — no navigation event emitted.

- [ ] **Step 3: Emit the navigation event in the bubbles branch**

In `emitTurnForState`, inside `if ($turnType === 'bubbles') { ... }`, after the `quick_replies` yield + `saveMessage` (after line ~639), add:

```php
            // Verify-navigate: a bubbles state can carry a navigate_to (closure or
            // string). When it resolves to a route, emit a navigation event so the
            // /m chat minimises + routes to the section's screen while these bubbles
            // wait for the user to reopen. Null route = inline confirm (no nav).
            $navigateTo = $state['navigate_to'] ?? null;
            $route = is_callable($navigateTo) ? $navigateTo($user) : $navigateTo;
            if (is_string($route) && $route !== '') {
                yield [
                    'type' => 'navigation',
                    'route_path' => $route,
                    'description' => $stateId,
                ];
            }
```

> Add `navigate_to` to the golden-master test's never-from-corpus exemption set if not already there (it's a code-only callable), so Task 5's parity test stays green. Verify: `grep -n "navigate_to" tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php` — `STATE_CAMPAIGN_TERMINAL` already carries a (string) `navigate_to`; confirm how it's handled and match.

- [ ] **Step 4: Run to verify pass**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Onboarding/OnboardingChatDirector.php tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php
git commit -m "feat(savetax-verify): emit navigation on a bubbles state with navigate_to"
```

---

## Task 7: New `/m` Income screen

Mirror `resources/mobile/views/TaxStrategy.vue` (MobileChrome wrapper, `apiGet`, `m-card`/`m-hero` classes, local `formatCurrency`, CSS-variable tokens, no scores, no decorative icons, Rule #9 acronyms). Fetch `GET /api/user/profile` and render the user's income breakdown plus the spouse's when a spouse is linked.

**Files:**
- Create: `resources/mobile/views/Income.vue`

- [ ] **Step 1: Confirm the profile endpoint shape**

Run: `php artisan tinker --execute="\$u=\App\Models\User::where('annual_employment_income','>',0)->first(); echo json_encode(app(\App\Services\UserProfileService::class)->getCompleteProfile(\$u), JSON_PRETTY_PRINT);" 2>&1 | head -60`
Confirm the keys for `annual_employment_income`, `annual_self_employment_income`, `annual_rental_income`, `annual_dividend_income`, `annual_other_income`, and how the spouse is represented (`spouse` block / `spouse_id`). Adjust the computed getters in Step 2 to the real key paths.

- [ ] **Step 2: Create `Income.vue`**

```vue
<template>
  <MobileChrome title="Income" subtitle="What you (and your spouse) earn each year" :loading="loading" loading-label="your income">
    <div v-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>
    <template v-else>
      <div class="m-card m-hero">
        <p class="m-sub m-label">Your total annual income</p>
        <p class="m-metric">{{ fmt(userTotal) }}</p>
      </div>
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Your income</p>
        <div v-for="row in userRows" :key="row.key" class="inc-row">
          <span class="inc-row__label">{{ row.label }}</span>
          <span class="inc-row__amt">{{ fmt(row.amount) }}</span>
        </div>
      </div>
      <div v-if="hasSpouse" class="m-card">
        <p class="m-section-label" style="margin-top:0">Your spouse's income</p>
        <div v-for="row in spouseRows" :key="row.key" class="inc-row">
          <span class="inc-row__label">{{ row.label }}</span>
          <span class="inc-row__amt">{{ fmt(row.amount) }}</span>
        </div>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../store.js';
import { apiGet } from '../api.js';
import MobileChrome from '../components/MobileChrome.vue';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

const ROWS = [
  { key: 'annual_employment_income', label: 'Employment' },
  { key: 'annual_self_employment_income', label: 'Self-employment' },
  { key: 'annual_rental_income', label: 'Rental' },
  { key: 'annual_dividend_income', label: 'Dividends' },
  { key: 'annual_other_income', label: 'Other' },
];

export default {
  name: 'MobileIncome',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', profile: null }),
  computed: {
    // Adjust these paths to the real getCompleteProfile() shape (Step 1).
    income() { return this.profile?.income || this.profile || {}; },
    spouse() { return this.profile?.spouse?.income || this.profile?.spouse || null; },
    hasSpouse() { return !!this.spouse && Object.keys(this.spouse).length > 0; },
    userRows() { return ROWS.map(r => ({ ...r, amount: Number(this.income[r.key]) || 0 })).filter(r => r.amount > 0); },
    spouseRows() { return ROWS.map(r => ({ ...r, amount: Number((this.spouse || {})[r.key]) || 0 })).filter(r => r.amount > 0); },
    userTotal() { return this.userRows.reduce((s, r) => s + r.amount, 0); },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    async load() {
      this.loading = true; this.error = ''; this.profile = null;
      try {
        const { ok, data } = await apiGet('/api/user/profile', store.token);
        if (ok) this.profile = data?.data || data || {};
        else this.error = data?.message || 'We could not load your income.';
      } catch (e) { this.error = 'Network error. Please try again.'; }
      finally { this.loading = false; }
    },
  },
};
</script>

<style scoped>
.inc-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--horizon-100); }
.inc-row:last-of-type { border-bottom: 0; }
.inc-row__label { font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.inc-row__amt { font-size: 14px; color: var(--neutral-600); white-space: nowrap; }
</style>
```

- [ ] **Step 3: Register the route + rebuild** (route in Task 9). For now, smoke-load via tinker token after Task 9. Commit.

```bash
git add resources/mobile/views/Income.vue
git commit -m "feat(savetax-verify): /m income screen"
```

---

## Task 8: New `/m` Expenditure screen

**Files:**
- Create: `resources/mobile/views/Expenditure.vue`

- [ ] **Step 1: Create `Expenditure.vue`** (same pattern; `monthly_expenditure` + `annual_expenditure` from the profile)

```vue
<template>
  <MobileChrome title="Expenditure" subtitle="What you spend each month" :loading="loading" loading-label="your expenditure">
    <div v-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>
    <template v-else>
      <div class="m-card m-hero">
        <p class="m-sub m-label">Monthly expenditure</p>
        <p class="m-metric">{{ fmt(monthly) }}</p>
        <p class="m-hero-sub">{{ fmt(annual) }} a year</p>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../store.js';
import { apiGet } from '../api.js';
import MobileChrome from '../components/MobileChrome.vue';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

export default {
  name: 'MobileExpenditure',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', profile: null }),
  computed: {
    monthly() { return Number(this.profile?.monthly_expenditure ?? this.profile?.expenditure?.monthly_expenditure) || 0; },
    annual() { return Number(this.profile?.annual_expenditure ?? this.profile?.expenditure?.annual_expenditure) || (this.monthly * 12); },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    async load() {
      this.loading = true; this.error = ''; this.profile = null;
      try {
        const { ok, data } = await apiGet('/api/user/profile', store.token);
        if (ok) this.profile = data?.data || data || {};
        else this.error = data?.message || 'We could not load your expenditure.';
      } catch (e) { this.error = 'Network error. Please try again.'; }
      finally { this.loading = false; }
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/mobile/views/Expenditure.vue
git commit -m "feat(savetax-verify): /m expenditure screen"
```

---

## Task 9: Routes + Cash Management drawer nav

**Files:**
- Modify: `resources/mobile/router.js`
- Modify: `resources/mobile/components/MobileChrome.vue`
- Modify: `resources/mobile/views/Dashboard.vue`

- [ ] **Step 1: Add the routes**

In `resources/mobile/router.js`, add imports after `TaxStrategy`:
```js
import Income from './views/Income.vue';
import Expenditure from './views/Expenditure.vue';
```
And routes after `tax-strategy`:
```js
    { path: '/income', name: 'm-income', component: Income, meta: { auth: true } },
    { path: '/expenditure', name: 'm-expenditure', component: Expenditure, meta: { auth: true } },
```

- [ ] **Step 2: Add the Cash Management nav group (both files)**

In BOTH `MobileChrome.vue` (`navSections`, ~line 211) and `Dashboard.vue` (`navSections`, ~line 494), add a "Cash Management" group **before** the "Finances" group, and a `NAV_ICON.income` + `NAV_ICON.expenditure` (reuse an existing wallet/coins-style glyph in the same `<svg stroke-width="2" 20x20>` format as the other nav icons — the drawer nav is the approved icon surface). Group:
```js
        { group: 'Cash Management', links: [
          { slug: 'income', label: 'Income', icon: NAV_ICON.income, route: '/income' },
          { slug: 'expenditure', label: 'Expenditure', icon: NAV_ICON.expenditure, route: '/expenditure' },
        ] },
```
Icons (add to both `NAV_ICON` objects):
```js
  income: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
  expenditure: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
```

- [ ] **Step 3: Build + smoke**

Run: `bash scripts/build-mobile.sh` (local base). Then in a browser at `/m/app/income` and `/m/app/expenditure` (inject a Sanctum token into `localStorage['m_scaffold_token']` per `reference_mobile_campaign_onboarding_and_fyn_streaming`), confirm both screens render the seeded values.

- [ ] **Step 4: Commit**

```bash
git add resources/mobile/router.js resources/mobile/components/MobileChrome.vue resources/mobile/views/Dashboard.vue
git commit -m "feat(savetax-verify): /m income/expenditure routes + Cash Management nav"
```

---

## Task 10: Frontend — confirm the Gate-2 bubble survives the minimise

The verify-navigate turn emits `quick_replies` (Gate 2) **and** a `navigation` event. `handleOnboardingNavigation` runs `closeFyn()` at stream end. Confirm the just-streamed bubbles message is already persisted to `messages` before the close, so reopening shows "Is this correct? [Yes/No]".

**Files:**
- Inspect/modify: `resources/mobile/views/Dashboard.vue` (the SSE cursor handling, ~line 903–1090, and `handleOnboardingNavigation` ~line 1110)

- [ ] **Step 1: Trace the cursor** — confirm `quick_replies` are pushed onto `cursor.reply.bubbles` and the reply is in `messages` before `if (cursor.navigation) this.handleOnboardingNavigation(...)` fires. If the navigation path clears or skips the bubble (e.g. the reply is dropped when `cursor.navigation` is set), adjust so the bubble message persists. The existing terminal nav (`→/tax-strategy`) does NOT carry bubbles, so this combination is new — verify with a live walk in Task 11.

- [ ] **Step 2: If a tweak is needed**, make the minimal change so a navigation turn still commits its bubble message. Add a regression note in the commit. If no tweak is needed, record that the trace confirmed correct behaviour. Commit only if changed.

```bash
git add resources/mobile/views/Dashboard.vue
git commit -m "fix(savetax-verify): keep Gate-2 bubble across the minimise"
```

---

## Task 11: Full suite + browser E2E on `/m` (Rule #14)

- [ ] **Step 1: Backend suite green**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/`
Expected: PASS (the new `CampaignVerifyFlowTest` + the golden-master/table tests + existing onboarding tests).

- [ ] **Step 2: Full suite**

Run: `./vendor/bin/pest`
Expected: green (prior baseline + the new cases). Fix any regression (e.g. an onboarding director test that counted campaign turns).

- [ ] **Step 3: Deploy to csjones + browser E2E** (per `reference_savetax_campaign_e2e_test_pattern`)

Push the branch, deploy to csjones (`git checkout` the branch + build + upload `public/m-build` + `db:seed --force` + `fyn:pointers:reindex` + cache clears), then on `https://csjones.co/fynla/m` (phone viewport), walk the SaveTax campaign end-to-end:
1. For a section with a screen (e.g. savings): after capture, "Anything else to add?" appears → No → chat minimises + routes to `/savings` + the nudge bar shows → reopen → "Is this correct?" → No → "What needs changing?" → edit → re-shows `/savings` → Yes → next section.
2. Confirm the new Income (incl. spouse) + Expenditure screens render the captured values and appear in the Cash Management drawer group.
3. Confirm charitable giving verifies **inline** (no navigation).
4. Confirm the campaign still ends at `/tax-strategy`.

Record evidence (screenshots, the `ai_messages` transcript). **No completion report until the full loop is green on `/m`.**

- [ ] **Step 4: tech-debt-session + PR**

Run the `tech-debt-session` skill over the changed files, then open the `dev` PR.

---

## Self-Review

**Spec coverage:** §2 flow → Tasks 2–6 (Gate 1/2, navigate, edit). §3 mapping → Task 1 config. §4 architecture (3 generic states + context) → Tasks 1–4. §4.2 director navigation → Task 6. §4.3 frontend → Tasks 9–10. §5 two screens → Tasks 7–9. §5.1 nav → Task 9. §6 edit (existing tools) → reached via `campaign_verify_edit` delegated turn (Task 3) + the already-whitelisted `update_record`/`update_profile` (no new tools). §7 testing → Tasks 1–6, 11. §8 success criteria → Task 11 E2E. ✓

**Placeholder scan:** Two grounded "verify-then-match-the-pattern" steps remain by necessity — Task 5 Step 2 (corpus `prompt_text` exemption, follow `STATE_BASE_PERSONAL`), Task 6 Step 1 (test seam vs public path) and Step 3 (`navigate_to` exemption, follow `STATE_CAMPAIGN_TERMINAL`), and Task 7 Step 1 / Task 10 (confirm live shapes). These are verification steps with a named existing precedent to copy, not open-ended TODOs. ✓

**Type/name consistency:** `campaignVerifyConfig` (Task 1), `enterCampaignVerify` / `nextFromVerifyMore` / `nextFromVerifyNavigate` / `verifyNavigateRoute` / `verifyPromptMore` / `verifyPromptNavigate` (Task 2), states `campaign_verify_more` / `campaign_verify_navigate` / `campaign_verify_edit` (Tasks 3–5), context key `verify_section` — consistent across tasks. ✓

---

## STATUS (2026-06-16) — backend + screens DONE; Task 10 reframed

**Done, committed on `savetax-verify-capture`, green (318 onboarding tests + income_summary feature test):**
- Tasks 1–6 (backend verify sub-flow: config, helpers, 3 generic verify states, edge re-routing, corpus parity, director navigation-on-bubbles). Commits `209bda6`→`f1751b3`. Also fixed two test gaps the plan missed: the golden-master `navigate_to` closure assertion (`toBeInstanceOf(Closure)` not `->toBe`, since `inCodeStates()` regenerates the `fn`), and `CampaignSectionFlowTest`/`OnboardingStateMachineTest` which encoded the old advice→next-section edges (now advice→`campaign_verify_more`).
- Tasks 7–9 (the two `/m` screens + Cash Management nav) + a net-new additive `income_summary` block on `UserProfileService::getCompleteProfile` (user + spouse per-source), with a feature test. Commit `72276df`.

### Task 10 (REVISED) — Option B: resume onboarding in the dock (CSJ decision 2026-06-16)

**Architecture finding that forced this:** the `/m` onboarding *campaign* chat (the `messages`/`cursor` state, the SSE handling, the bubble rendering) lives **inside `Dashboard.vue`**. The app uses a plain `<router-view/>` (no `<keep-alive>`), so navigating to `/income` etc. **unmounts `Dashboard.vue`** and destroys the chat + the waiting Gate-2 bubble. Module screens render `MobileChrome`'s dock, which starts a **fresh advice** chat (`initFyn` → "What would you like to look at?"), not the onboarding conversation. So the spec's "minimise → navigate → reopen chat → Gate-2 waiting" cannot work as-is.

**Chosen fix (Option B):** make `MobileChrome`'s Fyn dock **onboarding-aware** — when opened while onboarding is in progress (`store.user.onboarding_completed === false` and an active onboarding conversation exists), it **resumes the persisted onboarding conversation** (already in `ai_messages`) instead of starting advice, rendering the latest turn = the Gate-2 prompt + yes/no bubbles, and drives the bubble click → next onboarding turn.

**Implementation steps:**
1. **Extract the onboarding chat client into a shared unit** (composable `resources/mobile/composables/useOnboardingChat.js` or a mixin) that both `Dashboard.vue` and `MobileChrome.vue` use: the SSE `handleFynEvent` (`onboarding_advance` / `quick_replies` / `navigation`), `chooseBubble`, the `send`/stream loop, and the resume path. DRY — don't duplicate `Dashboard.vue`'s logic into the dock.
2. **Dock resume-on-open:** in `MobileChrome.openFyn`/`initFyn`, if onboarding is active, resume the onboarding conversation (load its messages incl. bubbles, or call the existing `resume` action) rather than pushing the advice greeting. The dock must render onboarding bubbles + handle clicks via the shared unit.
3. **Widen `Dashboard.vue::handleOnboardingNavigation`** (currently `if (routePath !== '/tax-strategy') return;`) to a whitelist of the verify destinations: `['/tax-strategy','/income','/expenditure','/savings','/investment','/retirement']` (ignore unknown desktop-only routes). Apply the same in the shared unit so the dock can re-navigate on a Gate-2-"no" edit re-show. (NOTE: the Gate-2 bubble already survives a navigation turn in `send()` — it has text+bubbles so it isn't dropped at lines ~1090-1095; only `handleOnboardingNavigation`'s guard blocks non-tax routes.)
4. **Confirm the dock is present on the destination screens** (it is — every `MobileChrome`-wrapped screen has it).

### Task 11 — full suite + `/m` E2E (unchanged)
Full `./vendor/bin/pest`; deploy the branch to csjones; browser-walk the SaveTax campaign on `/m` confirming the full verify loop per section (Gate 1 → navigate to the right screen → reopen dock → Gate-2 resumes → edit path), the new Income/Expenditure screens + Cash Management nav, giving inline; tech-debt-session; open the dev PR. Per `reference_savetax_campaign_e2e_test_pattern`.
