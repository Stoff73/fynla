# Fyn-Driven Onboarding Flow — Implementation Plan

**Branch:** `onboardingFyn` (off `dev`)
**Target env:** `csjones.co/fynla` (dev), then `fynla.org` (prod)
**Status:** Amended 20 April 2026 — implementation landed through commit `88018a5`. See `fynOnboardFix.md §20` for the delta register of what shipped vs what's still in scope for this release.

**⚠️ Icon rule (added 15 April 2026 session 54):** The Fyn chat window is a BANNED surface per `CLAUDE.md §14` and the top-of-file rule in `fynlaDesignGuide.md v1.4.0`. Every mention of `icon?`, `icon:`, or any emoji/glyph on a bubble, chat message, or quick reply in this document is RETRACTED. The `FynQuickReplies.vue` component takes bubbles of `{id, label}` only. No icon prop. No icon render. (The functional-only exception for the collapsed side nav does NOT apply — this plan is entirely chat-surface work.)

---

## Context

**Problem.** Fynla has a hidden "Quick start with Fyn" CTA on the landing page (`LandingPage.vue:156-162`, commented out). It was taken offline on 9 April 2026 because the downstream flow breaks: `dc_pensions.current_value` is missing on production, Fyn's `update_profile` tool sends `employment_status = 'full_time'` which fails validation, the coordinating agent runs full financial analysis against empty user data and hallucinates figures, and — most importantly — the multi-entity extraction is broken (user says "I have a £34k ISA and a £3458 Lloyds account", Fyn confirms both but only one gets captured).

**Intended outcome.** A working Fyn-driven onboarding experience for new users who click the CTA. The flow: landing page → register → dashboard with Fyn chat auto-open → Fyn greets by name → user picks Journey or Focus from clickable speech bubbles → Fyn collects base KYC data conversationally → Fyn collects focus-specific existing assets (handling multi-entity messages correctly) → Fyn asks if they want to cover anything else → finish on the focus's module page (single focus) or dashboard (multi-focus).

**Why this shape.** The existing `/onboarding` wizard at `OnboardingView.vue` works and is live — we are NOT touching it. This is a parallel path, specifically for users who click the hidden CTA. The existing `Register.vue:318-352` routing plumbing to `Dashboard.vue` with `openFyn=journey&newUser=1` is already in place and works — we only fix what's broken downstream.

**Scope discipline.** No journey code is deleted. The clean "journeys vs focuses" split is a **UI relabel in the Fyn chat only**. `JourneyFieldResolver::JOURNEY_FIELDS` continues to own all 8 journey field definitions as shared truth for both paths.

---

## Implementation status (20 April 2026)

Phases 0–5 of this plan are complete on the `onboardingFyn` branch. Commit `88018a5` landed four bug fixes surfaced by the 16 April end-to-end test. The 20 April audit (`April/April20Updates/fynComprehensiveCheck.md`) identified 13 more items (F1–F13) that recur the same bug patterns elsewhere in Fyn's touch surface, plus 3 deferred items from the original analysis — all locked into the scope of this release.

| Phase | Status |
|---|---|
| Phase 0 — Pre-flight | Complete |
| Phase 1 — Bug fixes (multi-entity, dc_pensions.current_value, employment_status validation, empty-data analysis skip) | Complete |
| Phase 2 — FIELD_DEFINITIONS extensions, savings journey, BASE_DATA_STEPS | Complete |
| Phase 3 — FynQuickReplies.vue, SSE event plumbing (FynConfirmationCard.vue descoped in spec) | Complete |
| Phase 4 — OnboardingChatDirector, OnboardingStateMachine, update_user_base_data | Complete (shipped with 14-state canonical shape, not 16) |
| Phase 5 — CTA un-hide, smoke test, dev deploy | Complete; deployed to csjones.co/fynla |
| Phase 6 — Production rollout | **Pending** — gated on resolution of P0 items in the 20 April amendment |
| Post-implementation bug fixes (commit `88018a5`) | Complete |
| F1–F13 + G1 + G2 + C1 follow-up work | **In scope for this release** — see `fynOnboardFix.md §20` |

---

## Decisions (approved during brainstorming)

| # | Decision | Choice |
|---|----------|--------|
| 1 | Scope of rewrite | **Parallel path.** Re-enable hidden CTA; existing wizard untouched. |
| 2 | Journeys vs Focuses overlap | **Clean split — UI only.** Journeys menu in Fyn chat: `budgeting, estate, family, business, goals`. Focuses menu: `savings, investment, retirement, protection`. Underlying `JOURNEY_FIELDS` shared. |
| 3 | Base data collection | **In-chat with inline confirmation cards.** Fyn writes to `users` table directly after user confirms via a compact card in the chat. No navigation for base data. Option C (end-review page) considered as a potential addition. |
| 4 | Multi-entity bug strategy | **Phase 1 of plan.** Fix before building the new chat onboarding layer. |
| 5 | Single vs multi focus selection | **Hybrid.** User picks one focus/journey first. At end, Fyn offers "Anything else?" bubbles to add more. |
| 6 | Completion depth | **Blocking + asset capture.** Fyn stops when all blocking readiness checks pass AND existing assets in the chosen focus module are captured (or user explicitly declines). Warning items deferred to dashboard alerts. |
| 7 | Exit / resume mid-flow | **Full state resume.** On next login, Fyn auto-opens with "Welcome back — we were talking about your savings. Ready to continue?" Reuses `onboarding_progress` + `ai_conversations` + `ai_messages` tables. |
| 8 | Chat copy source | **Extend `FIELD_DEFINITIONS` with `fyn_prompt` keys.** Form labels stay unchanged; Fyn uses new `fyn_prompt` copy. Single source of truth, separate copy. |
| 9 | Dashboard visibility | **Fully visible with real-time fill.** Modules populate as Fyn captures data. Fyn chat is a docked panel. Reuses existing `journeyBlurActive` mechanism but with blur at lower intensity. |
| 10 | Side bugs in Phase 1 | **All three `fynQuickStartBugs.md` items.** Multi-entity bug + `dc_pensions.current_value` prod migration + `employment_status` validation + empty-data check in `buildSystemPrompt()`. |
| 11 | Quick reply UI component | **New `FynQuickReplies.vue`.** Purpose-built component rendered as inline SSE message type `quick_replies`. Separate from `SuggestedPrompts.vue`. |
| 12 | End state after "no more focuses" | **Conditional.** Single focus → navigate to that module page. Multiple focuses → navigate to dashboard. Both: final Fyn message, chat minimises, `onboarding_completed_at = now()`. |

---

## Architecture

### Data flow

```
[Landing page CTA]                (LandingPage.vue:156-162, unhide)
      ↓
[Register with ?from=fyn]         (Register.vue:341-346, unchanged)
      ↓
[Dashboard + openFyn=journey&newUser=1]
      ↓
[FynChatPanel opens on mount]     (new: wire onboarding mode trigger)
      ↓
[Backend: OnboardingChatDirector  (new service; wraps HasAiChat)
   - Loads/creates onboarding state
   - Builds new-user system prompt (skip full analysis)
   - Emits quick_replies events for speech bubbles
   - Runs JourneyFieldResolver.getStepsForJourney(...) for chosen focus
   - Uses fyn_prompt copy from FIELD_DEFINITIONS
   - On asset mentions, emits fill_form events (existing mechanism)
   - Writes base data via direct tool calls (new tools)
]
      ↓
[SSE events back to frontend]
      ↓
[FynQuickReplies.vue or MobileFynChat.vue renderer]
      ↓
[User picks a bubble / types / confirms]
      ↓
[State persisted on every turn to onboarding_progress + ai_messages]
      ↓
[On completion: users.onboarding_completed = true, navigate per Q12]
```

### New backend components

| File | Purpose |
|------|---------|
| `app/Services/Onboarding/OnboardingChatDirector.php` | Orchestrates the Fyn-driven onboarding flow. Decides which questions to ask next based on current onboarding state. Assembles quick-reply options. Writes to `onboarding_progress` and `users` on each confirmed turn. |
| `app/Services/Onboarding/OnboardingStateMachine.php` | Pure state logic: `greeting → path_choice → (journey|focus)_selection → base_data_dob → base_data_spouse → base_data_dependants → base_data_employment → base_data_income → base_data_expenditure → focus_assets → add_more → done`. Reads from `onboarding_progress.step_name`. |
| `app/Services/AI/Prompts/NewUserContext.php` | A new system-prompt layer that REPLACES `FinancialContext` + `ExistingRecords` + `DataCompleteness` for users with zero data. Tells Claude "this user just registered, no data yet, do NOT reference specific figures, keep it generic." Addresses `fynQuickStartBugs.md` issue 2. |
| `app/Services/AI/AiToolDefinitions.php` (extend) | Add `update_user_base_data` tool for direct writes of DOB, marital_status, employment_status, income, expenditure etc. Keeps base-data writes out of the form-fill path. |

### New frontend components

| File | Purpose |
|------|---------|
| `resources/js/components/Fyn/FynQuickReplies.vue` | Inline clickable speech bubbles rendered within the chat stream. Supports hierarchical menus, icons, free-text fallback. Emits `select` with payload `{ id, label, value }`. |
| `resources/js/components/Fyn/FynConfirmationCard.vue` | Compact inline card for base-data confirmation ("Got it: DOB 12 Jan 1985, married, 2 kids. Correct?") with Yes / Edit buttons. Used only for base-data steps (no navigation). |
| `resources/js/store/modules/fynOnboarding.js` | New Vuex module tracking current onboarding step, captured-but-unconfirmed data, and resume state. Persists via `/api/onboarding/progress`. |

### Modified files

| File | Change |
|------|--------|
| `resources/js/views/Public/LandingPage.vue:156-162` | Un-comment the CTA. |
| `resources/js/views/Dashboard.vue:2154-2162` | On `openFyn=journey` query, dispatch `fynOnboarding/start` to bootstrap the onboarding chat state (not just set `journeyBlurActive`). |
| `resources/js/store/modules/aiChat.js:320-397` | Handle new SSE event types: `quick_replies`, `confirmation_card`, `onboarding_complete`. |
| `resources/js/store/modules/aiFormFill.js:81-117` | **Bug fix for multi-entity.** Investigate and fix queue handling. See Phase 1 details. |
| `resources/js/mobile/views/MobileFynChat.vue` (and desktop equivalent) | Render the three new message types via new components. |
| `app/Services/Onboarding/JourneyFieldResolver.php:87-283` | Extend `FIELD_DEFINITIONS` entries with `fyn_prompt` (per-field conversational copy). Add a new `JOURNEY_FIELDS['savings']` entry. |
| `app/Http/Controllers/Api/AiChatController.php:133-186` | Delegate to `OnboardingChatDirector` when user is in onboarding mode (based on `users.onboarding_completed = false`); otherwise fall through to existing `CoordinatingAgent`. |
| `app/Services/AI/SystemPromptBuilder.php` | For new users (zero data), substitute `NewUserContext` layer for the financial/analysis layers. Addresses `fynQuickStartBugs.md` issue 2. |
| `app/Http/Requests/UpdateIncomeOccupationRequest.php:29` | Add `full_time` to employment_status validation rule. |
| `database/migrations/.../add_current_value_to_dc_pensions.php` | Already exists locally — needs running on prod. No code change. |

---

## Phases

### Phase 0 — Pre-flight (no code changes)

Objectives: establish a feedback loop and confirm assumptions with runtime evidence.

1. Verify the multi-entity bug reproduces on `dev` by manually sending the exact message through the existing Fyn chat and observing:
   - Browser DevTools Network tab: count `fill_form` SSE events in the stream. If two → bug is frontend (`aiFormFill` queue). If one → bug is Claude's tool emission or backend.
   - Laravel log: enable temporary `Log::debug()` in `HasAiChat::chat()` around the tool execution loop (`HasAiChat.php:347-425`) to log `count($toolUseBlocks)` and each `$toolResult`.
2. Run a one-time manual test of `dc_pensions.current_value` on the dev DB to confirm whether the column is already present on `csjones.co/fynla` (likely yes, since dev DB was fresh-seeded when the env was stood up).
3. Run the existing `/onboarding` wizard in a browser as a clean user to confirm it works end-to-end (baseline we're not breaking).

### Phase 1 — Bug fixes (must complete before building new onboarding)

**1a. Multi-entity bug hunt and fix.**

Primary suspect based on code review: `aiFormFill.js:81-117` queue handling. When two `fill_form` events arrive in quick succession:
- First dispatches `startFill` → sets `pendingFill` for ISA → form component mounts and acknowledges → state transitions to `filling: true` → eventually form saves and calls `completeFill`.
- Second dispatches `startFill` → sees `pendingFill || filling` truthy → `ENQUEUE_FILL` → returns.
- `completeFill` must call `processNextInQueue` to drain Lloyds. **If this call path is broken, or if navigation unmounts the form before dequeue fires, the second entity is lost.**

Investigation steps:
1. Add `logger.debug()` calls at every state transition in `aiFormFill.js` (`startFill`, `enqueue`, `completeFill`, `processNextInQueue`, `acknowledgeFormReady`).
2. Reproduce the bug with DevTools open. Capture the log sequence.
3. Cross-check against the assistant message rendering — if the frontend sees two `fill_form` SSE events but only one reaches `startFill`, the bug is in `aiChat.js:320-397` event dispatcher.
4. Cross-check against backend Laravel log from Phase 0 — if the backend emits two events and the frontend only receives one, the bug is in SSE transport or `aiChatService.sendMessageStream()`.
5. If backend emits one event only, the bug is in `HasAiChat.php` tool execution, OR Claude itself only emits one `tool_use` block (most likely due to `FcaProcessInstructions.php` "call the tool immediately in your very first response" biasing Claude toward single-call emission).

Likely fixes (apply whichever applies):
- **Queue drainage bug** (frontend): ensure `completeFill` reliably calls `processNextInQueue` after any exit path (success, failure, cancel, timeout). Add integration test.
- **Navigation-unmount race** (frontend): before navigating away from a filled form, ensure the fill state is committed synchronously, and that `beforeRouteLeave` doesn't `commit('CLEAR')` before dequeue runs.
- **Claude single-emission bias** (backend): update `app/Services/AI/Prompts/FcaProcessInstructions.php:63-111` to explicitly instruct "if the user mentions multiple financial products in a single message, call the appropriate tool for **each one** in your first response. Do not call only one tool and summarise the rest in text." Add a concrete example in the system prompt.

Verification: send "I have a £34k ISA in a stocks & shares ISA and a Lloyds current account with £3458" in the chat. Confirm two `fill_form` events, two forms open sequentially, two records saved.

**1b. `dc_pensions.current_value` on production.** Run the pending migration on production via SSH. Pure ops, no code change. (Reference: `fynQuickStartBugs.md:13-17`.)

**1c. `employment_status` validation rule.** Add `'full_time'` to the `in:` rule in `UpdateIncomeOccupationRequest.php:29`. One-line change. Add an architecture test to ensure backend validation rules match the DB enum values.

**1d. Empty-data analysis skip.** In `SystemPromptBuilder.php`, detect new-user state (no income, no assets, no pensions) and skip the financial context + existing records + data completeness layers. Substitute a minimal `NewUserContext.php` layer telling Claude "this user just registered, do NOT reference specific financial figures, keep guidance generic until data is provided." (Reference: `fynQuickStartBugs.md:29-46`, fix option C.)

**Phase 1 exit criteria:** All four bugs verified fixed in a browser test on `csjones.co/fynla`. The existing `openFyn=journey&newUser=1` flow reaches the dashboard without the currently-broken CTA being un-hidden yet.

### Phase 2 — Content layer extensions

**2a. Extend `FIELD_DEFINITIONS` with `fyn_prompt` copy.** For each field in `JourneyFieldResolver::FIELD_DEFINITIONS:87-283`, add a new `fyn_prompt` key with conversational copy. Examples:

```php
'date_of_birth' => [
    'label' => 'Your date of birth',
    'fyn_prompt' => "First — when were you born? I'll use it to work out your tax bands, retirement timeline, and protection needs.",
    'why' => [...existing...],
    // ...
],
'marital_status' => [
    'label' => 'Your marital status',
    'fyn_prompt' => "Are you married, in a civil partnership, single, or something else? Married couples get significant tax advantages I can factor in.",
    // ...
],
```

**2b. Add `savings` entry to `JOURNEY_FIELDS`.** New entry in the constant:

```php
'savings' => [
    'personal' => ['date_of_birth', 'annual_employment_income', 'monthly_expenditure'],
    'financial' => ['savings_accounts'],
],
```

Also add `'savings'` to `JourneyStateService::JOURNEYS` so state tracking works consistently. Update `DEFAULT_STEP_COUNTS`.

**2c. Define the base data step sequence.** A new constant `BASE_DATA_STEPS` in `JourneyFieldResolver` listing the base data fields in canonical order: `date_of_birth, marital_status, family_members, employment_status, occupation_or_retirement_date, income_sources, expenditure`. This is what Fyn walks through before the focus-specific steps for EVERY path.

### Phase 3 — Quick reply UI + backend event plumbing

**3a. New `FynQuickReplies.vue`.** Single-file Vue component. Props: `bubbles: Array<{id, label, icon?, value}>`, `multiSelect?: boolean` (default false). Emits `select` with payload `{id, value}`. Visuals per `fynlaDesignGuide.md` v1.3.0 (raspberry CTAs, Segoe UI 900 for labels, spring for selected state). Must support hierarchical reveal (clicking a parent collapses it and reveals children).

**3b. New `FynConfirmationCard.vue`.** Single-file Vue component. Props: `summary: String`, `details: Array<{label, value}>`, `onConfirm`, `onEdit`. Compact card with raspberry "Yes, that's right" button and violet "Edit" button.

**3c. New SSE event types.** Extend `HasAiChat::chat()` generator to emit:
- `type: 'quick_replies'` — `{bubbles: [...], parentMessageId?: number, multiSelect?: boolean}`
- `type: 'confirmation_card'` — `{summary, details, pendingWriteId}`
- `type: 'onboarding_complete'` — `{nextRoute, summary}`

Add handlers in `aiChat.js:320-397` for each. Each yields a new pseudo-message in the message list with `role: 'assistant'` and a new `message_type` field.

**3d. Extend `MobileFynChat.vue` (and desktop equivalent) message renderer.** Add `v-if` branches for the new `message_type` values that render `<FynQuickReplies>` / `<FynConfirmationCard>` components. Route clicks back through `sendMessage` with a special `metadata: { quickReplyId: ... }` payload so the backend can interpret the choice.

### Phase 4 — Onboarding chat director (backend state machine)

**4a. `OnboardingChatDirector.php`.** New service class. Dependencies: `OnboardingStateMachine`, `JourneyFieldResolver`, `JourneyStateService`, `OnboardingProgressRepository`, `HasAiChat` (via a wrapping `CoordinatingAgent`-like class).

Responsibilities:
1. On `chat($user, $conversation, $message)`, read `users.onboarding_completed`. If true, pass through to `CoordinatingAgent` (normal Fyn). If false, enter onboarding mode.
2. Read the current `onboarding_progress` for `focus_area = active`. Determine current step via `OnboardingStateMachine::getNextStep(...)`.
3. Based on step, build a tight system prompt (using `NewUserContext` instead of full financial analysis) and instruct Claude to:
   - Ask the `fyn_prompt` question for the current field
   - Emit a `quick_replies` event with the canonical options (Yes/No, enum values, etc.)
   - When a value is captured, emit `confirmation_card` for base-data fields OR `fill_form` for asset fields
   - When the user confirms, call the appropriate tool (`update_user_base_data` for base data, existing `create_*` tools for assets)
4. After each tool call, persist progress: `onboarding_progress.step_data` (the captured value), advance the step, emit the next `quick_replies` or question.
5. At completion of base data → start the focus-specific sub-flow using `JourneyFieldResolver::getStepsForJourney($focus)` (skipping any fields already captured in base data).
6. At completion of asset capture → emit `quick_replies` for "Anything else? Savings / Investment / Retirement / Protection / No thanks".
7. On "No thanks": set `users.onboarding_completed = true`, `onboarding_completed_at = now()`, count total focuses captured, emit `onboarding_complete` with `nextRoute` per Q12 conditional.

**4b. `OnboardingStateMachine.php`.** Pure state logic. States:

```
greeting
  → path_choice         (emit quick_replies: Journeys / Focuses)
  → journey_selection   (if journeys; emit 5 journey bubbles)
  → focus_selection     (if focuses; emit 4 focus bubbles)
  → base_data_dob
  → base_data_marital
  → base_data_family
  → base_data_employment
  → base_data_income
  → base_data_expenditure
  → focus_assets        (loops per existing asset the user volunteers)
  → add_more            (emit quick_replies: add another focus / done)
  → done
```

Skip any base-data step whose value is already set on `users`. (Resume-safe.)

**4c. New `update_user_base_data` tool.** Direct-write tool in `AiToolDefinitions.php`. Parameters: `date_of_birth, marital_status, employment_status, occupation, retirement_date, annual_employment_income, annual_self_employment_income, annual_rental_income, annual_dividend_income, annual_interest_income, annual_other_income, monthly_expenditure`. All nullable. Handler in a new `OnboardingAgent::handleUpdateUserBaseData()` that writes to the `users` table directly and returns `{action: 'confirmation_card', fields_updated: [...]}`. This is different from `fill_form` — no navigation, just in-chat confirmation.

**4d. Resume support.** On Fyn chat open, if `users.onboarding_completed = false` AND `onboarding_progress` has at least one in-progress row, emit an initial `content` event: "Welcome back — we were talking about your [focus name]. Ready to pick up where we left off?" plus `quick_replies` of "Yes, let's continue / Start over / Skip for now". "Start over" wipes `onboarding_progress` and begins from `path_choice`. "Skip for now" minimises the chat.

### Phase 5 — Un-hide CTA + smoke test + dev deploy

1. Un-comment `LandingPage.vue:156-162`.
2. Run `./dev.sh`, register a fresh local user via `?from=fyn`, walk the full flow in a browser.
3. Write feature tests covering the state machine transitions.
4. Build for dev: `./deploy/csjones-fynla/build.sh`.
5. Upload to csjones.co/fynla per existing deploy process.
6. Run migrations + cache clears on dev server.
7. Smoke test on `https://csjones.co/fynla`.
8. If clean, open PR `onboardingFyn → dev`.

### Phase 6 — Production rollout (gated on user approval)

Only after dev is stable:
1. Open PR `dev → main`.
2. Build for prod: `./deploy/fynla-org/build.sh`.
3. Upload + migrations + cache clear on `fynla.org`.
4. Monitor logs for 30 minutes.
5. Production smoke test.

---

## File index (new or modified)

### New backend
- `app/Services/Onboarding/OnboardingChatDirector.php`
- `app/Services/Onboarding/OnboardingStateMachine.php`
- `app/Services/AI/Prompts/NewUserContext.php`
- `app/Agents/OnboardingAgent.php` (or fold into CoordinatingAgent — decide during implementation)

### Modified backend
- `app/Services/Onboarding/JourneyFieldResolver.php` — extend `FIELD_DEFINITIONS` + add `'savings'`
- `app/Services/Onboarding/JourneyStateService.php` — add `'savings'` to `JOURNEYS` + step count
- `app/Services/AI/SystemPromptBuilder.php` — substitute `NewUserContext` for new users
- `app/Services/AI/AiToolDefinitions.php` — add `update_user_base_data` tool
- `app/Services/AI/Prompts/FcaProcessInstructions.php` — add multi-entity explicit example
- `app/Traits/HasAiChat.php` — new SSE event types (quick_replies, confirmation_card, onboarding_complete)
- `app/Http/Controllers/Api/AiChatController.php` — delegate to `OnboardingChatDirector` when user not onboarded
- `app/Http/Requests/UpdateIncomeOccupationRequest.php` — add `full_time` to `in:` rule

### New frontend
- `resources/js/components/Fyn/FynQuickReplies.vue`
- `resources/js/components/Fyn/FynConfirmationCard.vue`
- `resources/js/store/modules/fynOnboarding.js`

### Modified frontend
- `resources/js/views/Public/LandingPage.vue:156-162` — un-comment CTA
- `resources/js/views/Dashboard.vue:2143-2162` — dispatch `fynOnboarding/start` on `openFyn=journey`
- `resources/js/store/modules/aiChat.js:320-397` — handle new SSE event types
- `resources/js/store/modules/aiFormFill.js:81-117` — multi-entity bug fix
- `resources/js/mobile/views/MobileFynChat.vue` — render new message types
- Desktop Fyn chat panel component (path to confirm during implementation) — same renderer updates

### Ops (no code)
- Run pending `dc_pensions.current_value` migration on production

---

## Verification plan

**Phase 1 bugs (run locally first, then on csjones.co/fynla):**
1. Register a fresh user, click the onboarding CTA, reach the dashboard.
2. Type: "I have a £34,000 stocks & shares ISA with Vanguard and a Lloyds current account with £3,458."
3. Verify: two `fill_form` SSE events in DevTools Network panel. Two forms open sequentially. Both records saved. Backend log shows two tool executions.
4. Type: "I earn £75,000 as a software engineer." Verify: Fyn does NOT respond with hallucinated figures about existing wealth or specific module analysis, because `NewUserContext` replaces the analysis layer.
5. On production: query `dc_pensions` schema and confirm `current_value` column exists after migration.

**Phase 4 state machine (run locally via Playwright MCP):**
6. Fresh register → click CTA → greeted by name.
7. Click "Focuses" bubble → see 4 focus bubbles.
8. Click "Savings" → Fyn asks DOB with a date input.
9. Enter DOB → confirmation card appears with Yes/Edit.
10. Yes → Fyn asks marital status with bubble options.
11. Walk through every base-data step, filling every field.
12. Reach asset capture → say "I have three ISAs at Vanguard, Hargreaves, and AJ Bell worth £10k, £15k, £25k".
13. Verify three `fill_form` events, three savings accounts created.
14. After asset capture → "Anything else?" bubbles appear.
15. Click "No thanks" → single-focus path → navigates to `/savings` module page.
16. Reload → verify `users.onboarding_completed = true`, no resume prompt.

**Resume flow:**
17. Fresh register, click CTA, answer DOB and marital, close the tab mid-flow.
18. Log in again → Fyn chat auto-opens with "Welcome back" + "Continue / Start over / Skip".
19. Click "Continue" → Fyn resumes at `base_data_family` (next unfilled step).

**Multi-focus end state:**
20. Register fresh, pick Savings, complete it, click "Add Investment" at the end, complete it, click "No thanks".
21. Verify navigation goes to the dashboard (multi-focus), NOT a module page.

**Regression check on existing wizard:**
22. Register a fresh user WITHOUT clicking the CTA → lands on the existing `/onboarding` wizard → complete the wizard → reach dashboard.
23. Verify existing wizard flow still works end-to-end for budgeting and protection journeys.

---

## Out of scope (explicitly)

- Not touching the existing `/onboarding` wizard beyond the shared `JourneyFieldResolver` extensions.
- Not touching the 8 existing journey flows beyond the new `fyn_prompt` copy.
- Not touching spouse data duplication (`users.spouse_id` vs `family_members.linked_user_id`) — known tech debt, deferred.
- Not writing the Fyn chat content/copy beyond the field-level `fyn_prompt` keys. Greeting lines, transition phrases, and celebration messages are content tasks done during implementation with UX writing input.
- Not adding a new `/onboarding/review` page (Q3 option C) in the first pass. Hold as a future enhancement if users struggle with the in-chat confirmation cards.
- Not building new journey content (all 8 journeys already work at baseline).
- Not changing the existing preview persona flow.
- No version bumps (per project rule).

---

## Open questions to resolve during implementation

1. **Desktop Fyn chat panel path** — I know `resources/js/mobile/views/MobileFynChat.vue` exists but haven't verified the desktop equivalent's exact path. Check `resources/js/components/Shared/` and `resources/js/components/Fyn/` during Phase 3.
2. **Existing `JourneyStateService::JOURNEYS` vs `users.onboarding_focus_area`** — the `onboarding_focus_area` column was changed to `VARCHAR(50)` so any string works, but `JourneyStateService::JOURNEYS` is a hardcoded PHP constant. Adding `'savings'` means touching both. Verify nothing else depends on the hardcoded list.
3. **Quick reply click → next Fyn turn** — how does the frontend represent a bubble click to the backend? Options: (a) `sendMessage` with the bubble label as text, (b) a new `/api/ai-chat/.../quick-reply` endpoint that takes a bubble ID, (c) `sendMessage` with `metadata: {quickReplyId}`. Decide in Phase 3. Favoured: option (c) to keep it on the same endpoint.
4. **`fynOnboarding` Vuex store vs. reusing `aiChat`** — depending on how much state is onboarding-specific, it may be cleaner to keep onboarding state inside the existing `aiChat` store under a nested key (`aiChat.onboarding`) rather than a new store. Decide during Phase 4.
5. **Mobile app implications** — the iOS Capacitor app uses the same Vue codebase. Phase 5 smoke test must include a mobile build (`./deploy/mobile/build-ios.sh`) and an iOS device check.
6. **Resolved 20 April**: Preview user isolation for `/onboarding/start` requires `EXCLUDED_ROUTES` update in `PreviewWriteInterceptor` (previously not specified — added to spec §4.1 and §20.3 C1).
7. **Resolved 20 April**: Skip rules on `base_personal` now use hybrid behaviour (both-or-neither skip + pre-filled prompt if one field is already captured). See spec §5.3.
8. **Resolved 20 April**: F5 Trust CLT orphan fix moves to a `Trust` model observer, not a `DB::transaction` wrap (the trust itself is `fill_form`, saved client-side — transaction wrap wouldn't help).

---

## Success criteria

- CTA is visible and clickable on the landing page.
- A brand-new user can go CTA → register → Fyn chat → pick Savings focus → answer all base-data questions via inline bubbles and confirmation cards → tell Fyn about two different savings accounts in one message → see both saved → see Savings module populated → land on `/savings`.
- A user who closes the tab mid-flow can return and resume.
- A user can add a second focus at the end without restarting.
- The existing `/onboarding` wizard for non-Fyn registrations is unchanged and still works.
- No regressions in the existing Fyn chat for authenticated users who are already onboarded.
- All four `fynQuickStartBugs.md` issues are resolved and verified.
