# PRD — Fyn-driven onboarding flow

**Project:** Fyn-driven onboarding flow
**Owner:** CSJ
**Status:** Draft
**Date:** 20 April 2026
**Spec:** `/Users/CSJ/Desktop/fynla/April/April15Updates/fynOnboardFix.md` (amended 20 April)
**Plan:** `/Users/CSJ/Desktop/fynla/April/April15Updates/fynOnboarding.md` (amended 20 April)
**Codebase audit:** Completed 20 April — see Risks & Dependencies for residual concerns

---

## 1. Context & Why

### Problem

The Fyn chat UX for new users has been hidden in production since 9 April 2026 because the underlying flow was broken. When a newly-registered user clicked "Quick start with Fyn" on the landing page, Fyn would: (a) only capture one of two holdings mentioned in a single message, (b) send invalid `employment_status = 'full_time'` against the backend's missing-enum validation, (c) run a full financial analysis against an empty user record and hallucinate figures, and (d) fail in ways that produced a dead-end without any resumable state.

A parallel onboarding replacement — a backend-authoritative state machine with a `FynQuickReplies.vue` bubble UI — was specced in `fynOnboardFix.md` (15 April), built on the `onboardingFyn` branch, and deployed to `csjones.co/fynla`. A 16 April end-to-end test (transcript at `April/April20Updates/fynChat.md`) surfaced four new bugs in the replacement flow: an add_more "Savings → family" loop, stacked LLM + retry messages on grouped extraction, an all-or-nothing work-capture handler, and a data-destination mismatch where onboarding wrote `users.monthly_expenditure` while the dashboard read `ExpenditureProfile.total_monthly_expenditure`. All four were fixed today (20 April, commit `88018a5`).

A follow-up audit (`fynComprehensiveCheck.md`) surfaced 13 more items (F1–F13) where the same bug patterns recur elsewhere in Fyn's touch surface — post-onboarding `handleSetExpenditure` still has the same `ExpenditureProfile` gap, the spouse-email collision in `SpouseLinkingService` silently loops the user, the family asset_capture turn goes off-script with property/mortgage questions, `handleUpdateRecord` lets the LLM update any fillable field including `Trust.settlor` and `Mortgage.start_date`, etc. Plus three deferred items from the original analysis. This PRD locks all of them into the scope of this release so the reconciliation is done in one pass rather than via serial follow-up.

### Business case

The hidden "Quick start with Fyn" CTA is the primary onboarding differentiator for the acquisition funnel — it's what separates Fynla's conversational onboarding from every other UK financial-planning app's form wizard. Keeping it offline costs new-user conversion every day. The 15 April rewrite is 95 % done; the remaining 5 % is the C1 preview bug, the 13 F-items, and a feature-test backfill that were discovered only after the system was exercised end-to-end.

This PRD's purpose is not "ship a new feature" but "reconcile what's actually live with what was specced, close the outstanding quality gaps, then re-enable the CTA on production with confidence". Every item below is either already shipped (documented here for the record) or a specific fix on the path to the production release of the CTA.

### Strategic fit

Touches the **Coordination** module (Fyn chat is cross-cutting) and the onboarding entry point for every subsequent module the user engages with. Indirectly unlocks **Savings / Investment / Retirement / Protection / Estate / Goals / Budgeting / Family / Business** — the 9 asset-capture focuses the state machine hands off to. Unblocks the production rollout of PR-equivalent work already on `dev` (PR #220 tech-debt) because the `onboardingFyn` branch is the gating release item — see `April/April20Updates/handover-2026-04-20-session-1.md` for the current branch queue.

### Status against live code

Because the plan has been implemented through commit `88018a5` and the PRD is a reconciliation, the following breakdown matters more than a traditional forward-looking "what we'll build":

| Bucket | Items | Count |
|---|---|---|
| **Shipped on onboardingFyn** (verified live on csjones.co/fynla) | 4 users table columns, 3 API endpoints, 4 new services (`OnboardingChatDirector`, `OnboardingStateMachine`, `OnboardingValueInterpreter`, `OnboardingPromptBuilder`), 3 new frontend pieces (`FynQuickReplies.vue`, `aiChat.js` SSE handlers, landing-page CTA un-hide), kill switch, `NewUserContext` → `EmptyDataGuard` swap, full 14-state machine (with intentional collapse from spec's 16 to 14 via `grouped_extract` turn type), 4 post-launch bug fixes in `88018a5` | Complete |
| **Spec deviations now canonical** | 14-state collapse, `grouped_extract`/`terminal` turn types, `{id, label, description?}` bubble shape, `$guarded` not `$fillable`, `conversation_created` SSE event | Documented in amended spec §20.2 |
| **P0 fixes — in scope for this release** | C1 (preview isolation), G2 (hybrid skip), G1 (feature tests), F1 (post-onboarding expenditure sync), F2 (spouse collision), F3 (family off-script), F5 (trust CLT orphan) | 7 items |
| **P1 fixes — in scope, next iteration within the release** | F4 (update whitelist), F6 (personal+spouse partial capture), cleanup duplicate helper, selective content filter | 4 items |
| **P2 fixes — in scope if time permits** | F7–F13 (prompt surface, expenditure fallback, dup-name checks, spouse sync, route allow-list, estate partial-payload) | 7 items |

Production rollout of the CTA re-enable is gated on every P0 item landing and being green on browser verification. P1/P2 can ship as incremental PRs against `dev` after the initial `onboardingFyn → dev → main` cut.

---

## 2. Target Persona

**Primary: `young_family` (James & Emily Carter)** — the Carter persona is a newly-registered user with a mortgage, early workplace pensions, and a young dependant. They're the clearest fit for the "Protecting and growing" journey + family-focus-then-savings flow that the 16 April test walked. The Savings-family loop bug hit them hardest — a new family-journey user is disproportionately likely to click add_more → Savings, which was broken until today.

**Secondary: `young_saver` (John Morgan)** — the persona that picks the Savings focus directly. Slightly smaller hit because they don't traverse the add_more loop, but they do hit `capture_work_details` (the bug fixed in 88018a5 §3) and post-onboarding `handleSetExpenditure` (F1 — still open).

**Tertiary: `entrepreneur` (Alex Chen)** — Ltd Co director, self-employed, hits the business asset_capture focus and `capture_work_details` with the self-employment income branch. The F5 Trust CLT orphan risk will bite this persona if they add a trust with an initial value during or after onboarding.

Preview personas (`peak_earners`, `retired_couple`, `student`) are not primary for THIS release — they're used for pre-existing-data demonstration and their onboarding runs are skipped via `skip_if` rules since they already have data seeded. The preview-user 403 path (C1) specifically protects them from accidental state writes.

---

## 3. Success Metrics (KPIs)

All metrics measurable with existing telemetry. No new analytics infrastructure required.

| Metric | Baseline | Target | Measurement |
|---|---|---|---|
| Successful `POST /ai-chat/onboarding/start` events per new registration (CTA path) | N/A (CTA hidden) | ≥ 0.9 per new user via `?from=fyn` | `ai_conversations` rows with `title='Onboarding'`, joined to `users.created_at`, filtered on users with `registration_source='fyn_cta'`. Weekly. |
| Onboarding completion rate (users reaching `STATE_DONE`) | unknown — reporting gap | ≥ 70 % of users who reach `path_choice` | `onboarding_progress` rows where `step_name='done' AND completed=true`, over users with any `onboarding_progress` rows. Weekly. |
| Median time from `path_choice` to `done` | unknown | ≤ 8 minutes (happy path), ≤ 15 minutes (spouse + asset capture) | `onboarding_progress.completed_at` first/last delta per user. Weekly. |
| `handleCaptureWorkDetails` retry rate (partial-capture loops per user) | pre-88018a5: ≥ 2 per user on base_work | ≤ 1.2 average per user | Count of `base_work` entries in `onboarding_progress` before advance, per user. 30-day rolling. |
| Post-onboarding expenditure visible on dashboard after Fyn says "captured" | 0 % (F1 bug) | 100 % after F1 ships | Manual audit first, then `ExpenditureProfile` row count vs `handleSetExpenditure` call count. Weekly. |
| Off-script asset_capture questions per 100 turns (e.g. property/mortgage questions on family focus) | 1 in 1 on 16 Apr test | 0 in 100 after F3 ships | Manual transcript review of the first 100 asset_capture turns after F3 deploy; regex audit of assistant messages for `\b(property|mortgage|rent)\b` in family/savings/investment/retirement/protection focuses. One-off audit. |
| Preview user can trigger onboarding flow | Previously broken (middleware fake-success) | 403 from `/start` as designed | Feature test against C1. Pest suite. |
| Orphaned CLT rows created when trust form is cancelled | ≥ 1 per cancelled trust form (F5) | 0 after F5 ships | `SELECT COUNT(*) FROM gifts WHERE gift_type='clt' AND NOT EXISTS (SELECT 1 FROM trusts WHERE trust_name = gifts.recipient AND user_id = gifts.user_id)`. Daily for 2 weeks post-deploy. |

Mobile (iOS Capacitor) onboarding is explicitly deferred per spec §19 supplementary decision 4 — no mobile metrics in this release.

---

## 4. User Stories & Scenarios

### User stories

- As a **`young_family`** user registering via the landing-page CTA, I want Fyn to open automatically on my dashboard and greet me by name, so I don't have to guess where to start.
- As a **`young_family`** user who picked "Protecting and growing", I want to tell Fyn about my spouse and finish the family section, then pick Savings to tell Fyn about my savings accounts, so I can capture both in one uninterrupted session — without Fyn asking me about family a second time when I clearly said "Savings".
- As a **`young_saver`** user, I want to type "Dentsu" into Fyn's base_work question without losing it when I then say "Chief Marketing Officer, £50,000", so I don't waste three turns re-entering what I already told Fyn.
- As any user, I want Fyn to tell me when my expense information is saved — and then actually SEE it on my dashboard — rather than being told "captured" and discovering a blank expenditure card.
- As an **`entrepreneur`** user who adds a trust and then cancels the trust form, I want there to be NO orphaned CLT gift recorded, so my IHT calculation doesn't over-count a settlement I never made.
- As a `peak_earners` **preview user** browsing the preview persona, I want Fyn's onboarding CTA to not trigger a state-machine write on my seeded persona record — it should refuse cleanly with a sensible message.
- As a user who registers a spouse whose email already belongs to another Fynla household, I want Fyn to tell me clearly — not loop me with "I need a first name, date of birth, and email address for your partner" three times until I give up.

### Key scenarios

**Scenario 1 — Protecting-and-growing → family → Savings (the 16 Apr happy path, now green after 88018a5 + F1 + F2 + F3):**

1. User registers via `/register?from=fyn`, verifies OTP, lands on `/dashboard?openFyn=journey&newUser=1`.
2. Fyn chat opens automatically. Greeting message: *"Hi Fyntest, I'm Fyn — welcome to Fynla. I'll help you set up your financial plan. To start, do you want to follow a life-stage journey or pick a single module focus?"* with two bubbles.
3. User clicks **Follow a journey**. Fyn shows 5 journey bubbles (each with `description` text per spec §5.2 canonical shape).
4. User clicks **Protecting and growing**. Fyn asks DOB + marital status in one turn (`base_personal` grouped_extract). User types "19/02/82, Married" → Fyn advances silently to `base_spouse`.
5. User types Laura's details → Fyn creates linked spouse account, advances to `base_dependants`.
6. User clicks **No** → Fyn advances to `base_employment`. User clicks **Employed** → `base_work`.
7. User types "Dentsu" → `handleCaptureWorkDetails` saves `employer=Dentsu`, returns `missing: [occupation, annual_income]`. Fyn emits targeted retry: *"Thanks — I still need your role or position and your gross annual income in GBP. Could you share those?"*
8. User types "Chief Marketing Officer, £50000" → all three work fields now populated → advance to `base_expenditure`.
9. User types "£4000" → `users.monthly_expenditure` AND `ExpenditureProfile.total_monthly_expenditure` both set to 4000 → advance to `asset_capture` with `selection=protection`.
10. Fyn emits the protection intro ("Let's look at your existing protection cover…"), user lists policies or says "I don't have any" → advance to `add_more`. Bubbles: Savings, Investment, Retirement, I'm done (protection stripped because visited).
11. User clicks **Savings** → `persistCapture` writes `onboarding_fyn_selection=savings`, appends to `visited_focuses` → `asset_capture` re-fires with savings intro.
12. User captures savings, returns to `add_more`, picks **I'm done** → `done` state → navigation to `/net-worth/cash`.

> **Session 4 (20 April 2026) correction.** The `Protecting and growing` journey originally mapped to `selection=family`. That caused a third family prompt (asset_capture asking about parents / adult children) after `base_spouse` and `base_dependants`, violating the "one contiguous section per topic" rule — family → employment → expenditure → family again. Journey bubble ids were remapped: `Protecting and growing → protection`, `Planning your future → retirement`. The `family` asset_capture focus was removed entirely. Household is fully captured by `base_spouse` + `base_dependants`; extended family (parents, adult children) is captured via module pages after onboarding, not mid-flow.

**Scenario 2 — Spouse email already in use (F2):**

1. User on `base_spouse`, types "Laura, 23/02/87, laura@oldhousehold.co.uk".
2. `SpouseLinkingService::linkOrCreateSpouse` discovers that email is already bound to another household.
3. Throws new `SpouseCollisionException` (F2 — new exception type).
4. `handleCaptureSpouseDetails` catches the collision-specific exception, returns a distinct error receipt WITHOUT `onboarding_capture: true`.
5. Director `emitTerminalError` (new method — F2) emits: *"That email's already registered with another Fynla household. Want to use a different address for your partner, or ask them to link their own account?"*
6. User types a fresh email → the flow resumes cleanly at `base_spouse`.
7. State does NOT silently advance or loop on the generic "I need a first name, date of birth, and email" retry.

**Scenario 3 — Preview user hits onboarding CTA (C1):**

1. Preview user (persona-selector flow) clicks "Quick start with Fyn" on the landing page.
2. Routed to registration (they're already authenticated as a preview persona, so actually clicking the CTA from within the app would target `/onboarding/start`).
3. `POST /api/ai-chat/onboarding/start` is hit. With `api/ai-chat/onboarding` now in `PreviewWriteInterceptor::EXCLUDED_ROUTES`, the middleware passes the request through to the controller.
4. `AiChatController::startOnboarding` checks `$user->is_preview_user === true` and returns JSON `{success: false, reason: 'preview_mode'}` with 403.
5. Frontend catches the 403 and falls back to normal chat open — persona's seeded data is displayed.
6. No state-machine write happens; seeded persona data is untouched.

**Scenario 4 — Post-onboarding user says "my rent is £1,500" (F1 — the recurrence of bug §4 in a different layer):**

1. User completed onboarding 3 days ago. They visit the chat and type "My rent is £1500 and utilities are £300."
2. Fyn in normal chat mode calls `handleSetExpenditure` with the two numeric fields.
3. Handler writes `users.rent=1500`, `users.utilities=300`, `users.monthly_expenditure=1800`, `users.annual_expenditure=21600`.
4. **Pre-F1**: dashboard expenditure widget reads `ExpenditureProfile.total_monthly_expenditure` which is still null → Fyn says "captured" but dashboard shows nothing.
5. **Post-F1**: handler also calls `ExpenditureProfile::updateOrCreate([...], ['total_monthly_expenditure' => 1800])` → dashboard and Fyn agree.

**Scenario 5 — Entrepreneur adds a trust then cancels (F5):**

1. User in normal chat: "I want to add a discretionary trust called Chen Family Trust, initial value £250,000."
2. Fyn calls `handleCreateTrust`. **Pre-F5**: tool immediately writes a `clt` `Gift` row (line 2624), then returns `fill_form` to open the Trust form with the initial value.
3. User cancels the trust form on the frontend.
4. **Pre-F5**: `gifts` table now has an orphaned CLT referencing a trust that was never saved. Next IHT calculation double-counts.
5. **Post-F5**: `handleCreateTrust` returns `fill_form` with no side effect. When the user saves the form, the `Trust` model fires a `created` event. A new `Trust` observer (F5) listens and creates the CLT `Gift` only if the trust actually exists. Cancellation → no orphan.

---

## 5. Functional Requirements

Prioritised using MoSCoW. Items already shipped are marked **[SHIPPED]** for reconciliation clarity.

### Must-have

- **FR-M1:** [SHIPPED] Backend-authoritative state machine with 14 canonical states (`OnboardingStateMachine::states()`), LLM involvement scoped to grouped_extract and asset_capture turns only. _Touches: `app/Services/Onboarding/OnboardingStateMachine.php`, `OnboardingChatDirector.php`, `OnboardingValueInterpreter.php`, `OnboardingPromptBuilder.php`._
- **FR-M2:** [SHIPPED] Three API endpoints: `POST /api/ai-chat/onboarding/start`, `GET /api/ai-chat/onboarding/status`, modified `POST /api/ai-chat/conversations/{id}/messages` that delegates to the director. _Touches: `app/Http/Controllers/Api/AiChatController.php`, `routes/api.php`._
- **FR-M3:** [SHIPPED] Database schema: `users.onboarding_fyn_step`, `onboarding_fyn_path`, `onboarding_fyn_selection`, `onboarding_fyn_context` columns via migration `2026_04_15_090000`. Plus `civil_partnership` value added to `marital_status` enum via migration `2026_04_15_091500`. _Touches: `database/migrations/*`._
- **FR-M4:** [SHIPPED] `FynQuickReplies.vue` component accepting `{id, label, description?}` bubbles. No icon prop, no icon render. _Touches: `resources/js/components/Fyn/FynQuickReplies.vue`._
- **FR-M5:** [SHIPPED] `AiChatPanel.vue` + `aiChat.js` + `aiChatService.js` frontend wiring for `startOnboardingConversation` and the new SSE event types. Legacy `options:` block and `SET_PENDING_JOURNEY_PROMPT` removed. _Touches: `resources/js/components/Shared/AiChatPanel.vue`, `resources/js/store/modules/aiChat.js`, `resources/js/services/aiChatService.js`._
- **FR-M6:** [SHIPPED] Kill switch `config('onboarding.fyn_flow_enabled')` gating both `/start`, `/status`, and the `/messages` delegation. Default on. _Touches: `config/onboarding.php`, `AiChatController.php`._
- **FR-M7:** [SHIPPED] Landing page CTA un-hidden at `LandingPage.vue:156`. _Touches: `resources/js/views/Public/LandingPage.vue`._
- **FR-M8:** [SHIPPED] Four bug fixes from commit `88018a5`: add_more selection persistence, LLM content event swallow on grouped_extract turns, `handleCaptureWorkDetails` partial-capture with targeted retry, `base_expenditure` ExpenditureProfile sync. _Touches: `OnboardingChatDirector.php`, `CoordinatingAgent.php::handleCaptureWorkDetails`._
- **FR-M9:** **C1 — Preview user isolation fix.** Add `'api/ai-chat/onboarding'` to `PreviewWriteInterceptor::EXCLUDED_ROUTES` so the controller-level 403 check actually runs. _Touches: `app/Http/Middleware/PreviewWriteInterceptor.php`._
- **FR-M10:** **G2 — Hybrid `base_personal` skip rule.** If DOB is already set and marital is not (or vice versa), the grouped_extract prompt adapts to ask only for the missing field, pre-confirming the already-captured one. _Touches: `OnboardingStateMachine.php::resolvePromptText` and related skip logic._
- **FR-M11:** **G1 — Feature tests.** Integration tests for `POST /ai-chat/onboarding/start` (200 for fresh users, 409 for already-completed, 403 for preview, 503 for kill-switch off), state-machine walkthrough (`path_choice` → `done`), multi-entity asset_capture. _Touches: `tests/Feature/Onboarding/` (new files)._
- **FR-M12:** **F1 — Post-onboarding expenditure sync.** `CoordinatingAgent::handleSetExpenditure` must `ExpenditureProfile::updateOrCreate([...], ['total_monthly_expenditure' => $total])` alongside the existing `users.*` write. _Touches: `app/Agents/CoordinatingAgent.php:2748`._
- **FR-M13:** **F2 — Spouse-email collision distinct path.** New `App\Exceptions\SpouseCollisionException extends RuntimeException`. `SpouseLinkingService::linkOrCreateSpouse` throws it (instead of `InvalidArgumentException`) when the target email belongs to another household. `handleCaptureSpouseDetails` catches it separately and returns `['error' => true, 'error_type' => 'spouse_collision', 'message' => '...']`. `OnboardingChatDirector::handleGroupedExtractTurn` inspects `error_type` and calls a new `emitTerminalError` method that renders: *"That email's already registered with another Fynla household. Want to use a different address for your partner, or ask them to link their own account?"* State stays on `base_spouse` so the user's next message is interpreted as a retry. _Touches: `app/Services/Onboarding/SpouseLinkingService.php`, `CoordinatingAgent.php:883`, `OnboardingChatDirector.php`, new `app/Exceptions/SpouseCollisionException.php`._
- **FR-M14:** **F3 — Family asset_capture off-script prevention.** Tighten `OnboardingPromptBuilder::assetCaptureInstructions` with an explicit "Do NOT ask about property, mortgages, or anything outside the tool list shown above." Add a selective content-event filter in `OnboardingChatDirector::handleAssetCaptureTurn`: swallow content events that (a) contain a question mark OR (b) arrive in a turn where zero tool calls were made. Preserve single-sentence confirmations like "Got it — recording those now." `tool_choice` stays `'auto'` so the "I don't have any" path continues to work. _Touches: `OnboardingPromptBuilder.php`, `OnboardingChatDirector.php::handleAssetCaptureTurn`._
- **FR-M15:** **F5 — Trust CLT orphan prevention.** Move CLT auto-creation out of `handleCreateTrust` (lines 2617–2638). Create a new `Trust` model observer that listens on the `created` event and writes a corresponding `Estate\Gift` row with `gift_type='clt'`. `handleCreateTrust` continues to return `fill_form`, but no longer writes the CLT directly. If the user cancels the trust form, no trust is saved and no CLT exists. _Touches: `app/Agents/CoordinatingAgent.php:2617-2638` (remove the direct CLT write), new `app/Observers/TrustObserver.php`, `app/Providers/AppServiceProvider.php` or model `booted()` hook to register the observer._

### Should-have

- **FR-S1:** **F4 — `handleUpdateRecord` per-entity field allowlist.** Replace the `getFillable()` boundary (line 2838) with a `private const ALLOWED_UPDATE_FIELDS` array on `CoordinatingAgent` keyed by the 12 entity types in `resolveModel()`. Intersect with `getFillable()`. Explicitly omit `settlor` from trust, `start_date`/`term_years` from mortgage, `relationship` from family_member. _Touches: `app/Agents/CoordinatingAgent.php:2802-2858`._
- **FR-S2:** **F6 — `handleCapturePersonalDetails` + `handleCaptureSpouseDetails` partial-capture.** Apply the `handleCaptureWorkDetails` template — save non-empty fields, respect already-populated `users` state, compute `missing`, return `onboarding_capture: true` with `details.missing`. The director's `composePartialRetryText` helper already has friendly-map entries for both tools (`capture_personal_details` and `capture_spouse_details`). _Touches: `CoordinatingAgent.php:787`, `CoordinatingAgent.php:883`._
- **FR-S3:** **Cleanup — duplicate helper.** Extract `educationStatusForAge` to `OnboardingValueInterpreter::educationStatusForAge` (public static). Remove the duplicate from `CoordinatingAgent.php:1075` and `OnboardingChatDirector.php:582`. _Touches: those three files._
- **FR-S4:** **Cleanup — selective content-event filter.** Same as FR-M14's asset_capture swallow logic — ensure it's correctly scoped (question-mark detection + zero-tool-call detection, not unconditional) to avoid killing legitimate confirmations. Tracked separately because its implementation is a refinement of F3, not a new change. _Touches: `OnboardingChatDirector.php::handleAssetCaptureTurn`._

### Nice-to-have

- **FR-N1:** **F7 — Surface `employer` + `occupation` in SystemPromptBuilder user profile.** After the employment status line in `buildUserProfile`, append `"- Employer: {$user->employer}"` and `"- Role: {$user->occupation}"` when set. Removes post-onboarding hedging. _Touches: `app/Services/AI/SystemPromptBuilder.php:180-279`._
- **FR-N2:** **F8 — ExpenditureProfile fallback in calculateTotalExpenditure.** After the `users.monthly_expenditure` and `users.annual_expenditure` checks, fall back to `ExpenditureProfile.total_monthly_expenditure`. Mirrors the `KycGateChecker::checkUniversalRequirements` order. _Touches: `SystemPromptBuilder.php:963-974`._
- **FR-N3:** **F9 — Duplicate-name checks on 7 create handlers.** Add `checkForDuplicate` calls to `handleCreateTrust`, `handleCreateFamilyMember`, `handleCreateBusinessInterest`, `handleCreateEstateAsset`, `handleCreateEstateLiability`, `handleCreateEstateGift`, `handleCreateChattel` following the `handleCreateSavingsAccount:1531` template. _Touches: 7 handlers in `CoordinatingAgent.php`._
- **FR-N4:** **F10 — `handleUpdateProfile` spouse-linked-user sync.** When the user updates personal details via Fyn, detect `household_id` and mirror relevant non-identity fields (address, phone) to the linked spouse user record. Marital status changes sync to both. _Touches: `CoordinatingAgent.php:2946`._
- **FR-N5:** **F11 — `handleSetExpenditure` spouse sync for household budget.** Same household mirror pattern as F10, applied to expenditure. _Touches: `CoordinatingAgent.php:2748`._
- **FR-N6:** **F12 — Add missing routes to navigate_to_page allow-list.** `/estate/inheritance-tax`, `/settings/privacy`, `/risk-profile/levels`, `/risk-profile/factor/:factor`, `/planning/what-if/:id`, `/actions/:planType/:actionId`, `/plans/goal/:goalId`. _Touches: `app/Services/AI/AiToolDefinitions.php:60`._
- **FR-N7:** **F13 — `handleCreateEstateAsset` + `handleCreateEstateGift` partial-payload tolerance.** Apply the work-capture template: save non-empty fields, return `missing[]`, let the director emit a targeted follow-up. _Touches: `CoordinatingAgent.php:2097`, `CoordinatingAgent.php:2170`._

---

## 6. User Flow & UX/Design

### Flow

```
Landing page
    └─ "Quick start with Fyn" CTA
         ↓
Register?from=fyn
    └─ email, password, verification code
         ↓
Dashboard?openFyn=journey&newUser=1
    └─ Dashboard.vue:2158 dispatches aiChat/startOnboardingConversation
         ↓
POST /api/ai-chat/onboarding/start
    ├─ preview user? → 403 + JSON {reason:'preview_mode'}  [FR-M9 fix]
    ├─ already completed? → 409 + JSON {reason:'already_completed'}
    ├─ already mid-flow? → stream {type:'resume', conversation_id:N}
    └─ fresh → create AiConversation, set onboarding_fyn_step='path_choice', stream emitFirstTurn
         ↓
Turn 1: path_choice bubbles (Follow a journey / Pick a focus)
         ↓                              ↓
Turn 2: journey_selection (5 bubbles)  Turn 2: focus_selection (4 bubbles)
         └──────────────┬───────────────┘
                        ↓
Turn 3: base_personal (grouped_extract: DOB + marital)
    ├─ both fields extracted → save → advance
    └─ partial → emitPartialRetry with missing field(s)
                        ↓
          [married|civil]  [single|divorced|widowed]
                 ↓                    ↓
         base_spouse              base_dependants
            ↓                     (Yes → base_dependants_detail, No → base_employment)
         [normal case]            (handles spouse collision via SpouseCollisionException — FR-M13)
            ↓
         base_dependants → base_employment
            ↓
         base_employment (bubbles: 6 options)
            ├─ employed/self_employed/part_time → base_work (grouped_extract: employer+occupation+income)
            ├─ retired → base_retirement_date
            └─ unemployed/other → base_expenditure
            ↓
         base_expenditure (free_text, £X parsed)
            ├─ writes users.monthly_expenditure
            └─ writes ExpenditureProfile.total_monthly_expenditure (bug §4 fix from 88018a5)
            ↓
         asset_capture (delegated LLM turn, focus-filtered create_* tools)
            ├─ FR-M14: selective content-event filter (swallow off-script questions)
            └─ multi-entity messages produce multiple tool calls
            ↓
         add_more bubbles (strips visited focuses, appends "I'm done")
            ├─ pick a new focus → persistCapture updates onboarding_fyn_selection + visited_focuses
            │                     (bug §1 fix from 88018a5)
            │                     → loops to asset_capture
            └─ "I'm done" → done
            ↓
         done (terminal, clears onboarding_fyn_* columns, sets onboarding_completed=true)
            └─ navigate to /net-worth/cash (savings), /net-worth/investments (investment),
               /net-worth/retirement (retirement), /protection, /estate, /goals,
               /dashboard (multi-focus), etc.
```

Post-onboarding: normal Fyn chat via `CoordinatingAgent::chat()` for any subsequent message. The delegation condition in `AiChatController` is `onboarding_completed=false && onboarding_fyn_step !== null && config('onboarding.fyn_flow_enabled')`.

### UX/Design notes

- **Design system:** `fynlaDesignGuide.md v1.4.0` at `/Users/CSJ/Desktop/fynlaBrain/Design/fynlaDesignGuide.md`. Raspberry CTAs, horizon text, Segoe UI font stack, weights 900 (display) / 700 (h2–h5).
- **Icon surface:** Fyn chat is a **BANNED** icon surface per design guide v1.4.0. No emoji, glyphs, or SVG icons anywhere in chat messages, quick replies, confirmation cards, or streaming indicators. Spec §top and the states-file header comment both enforce this. `FynQuickReplies.vue` takes `{id, label, description?}` only.
- **Reusable components:** `FynQuickReplies.vue` (built in Phase 3, canonical). Existing `AiChatPanel.vue` chrome (reused). Existing SSE streaming primitives in `aiChat.js` (reused for `onboarding_advance`, `onboarding_complete`, `conversation_created`, `onboarding_field_captured` events).
- **New components (if any):** none in this release. Spec §17 explicitly rules out `FynConfirmationCard.vue` — confirmation UX is handled via the grouped_extract advance-silently pattern ("Got it — advancing to…" is implicit in the next turn's prompt).
- **Responsive behaviour:** desktop docked chat panel (right side), mobile chat opens over the dashboard on narrow widths. Standard responsive — no special treatment. Journey-blur on desktop only (≥ 1024 px).
- **iOS Capacitor:** explicitly out of scope per spec §19 supplementary decision 4. `MobileFynChat.vue` is untouched; iOS users see the normal Fyn chat (no onboarding auto-start). A follow-up task will wire mobile — tracked outside this PRD.
- **Accessibility:** `FynQuickReplies.vue` uses `<button>` elements — keyboard nav and screen-reader support are native. Focus moves to the input textarea after each turn completes (existing `AiChatPanel.vue` behaviour).
- **Reference artefacts:**
  - 16 April test transcript: `/Users/CSJ/Desktop/fynla/April/April20Updates/fynChat.md`
  - Root-cause analysis: `/Users/CSJ/Desktop/fynla/April/April20Updates/fynChatAnalysis.md`
  - Comprehensive audit: `/Users/CSJ/Desktop/fynla/April/April20Updates/fynComprehensiveCheck.md`
  - Vault mirror of this PRD (if requested): `/Users/CSJ/Desktop/fynlaBrain/April/April20Updates/PRD-fyn-driven-onboarding.md`

---

## 7. Out of Scope

- **Legacy `/onboarding` wizard** at `OnboardingView.vue`. Untouched. A separate acquisition path for non-CTA users.
- **Existing authenticated Fyn chat** for users with `onboarding_completed=true`. Untouched except for F1/F7/F8 (which refine the post-onboarding layer but don't replace it).
- **`FcaProcessInstructions`** as a system-prompt layer. Stays, but is skipped during onboarding via `OnboardingPromptBuilder`. No content changes.
- **Mobile (iOS Capacitor) onboarding.** `MobileFynChat.vue` does not auto-start onboarding. Mobile users can still onboard via the web experience if they open the app's WebView directly; the CTA flow is web-only for this release.
- **Version bumps.** No version string changes anywhere — per project rule (`feedback_never_version_bump`).
- **New Vue components** beyond `FynQuickReplies.vue`. No `FynConfirmationCard.vue`.
- **Frontend state machine.** The backend owns all state.
- **Refactoring `CoordinatingAgent`'s tool handlers** — capture_* and create_* handlers stay in place. Moving them into a new `OnboardingAgent` class is a future architectural consideration, not in this release.
- **`handleCreateTrust` → full transaction wrap.** The fix is specifically the CLT observer move (F5), not a transaction on the tool handler.
- **Production rollout of the CTA.** Stays gated behind dev stability. The PRD authorises dev deploy + CTA un-hide on production only after the P0 items pass browser verification.
- **`dc_pensions.current_value` production migration.** Separate ops task.
- **Awin affiliate integration.** Separate work on `dev`, parallel timeline.
- **Admin Insights CMS.** Already shipped on main; not part of this PRD.

---

## 8. Risks & Dependencies

### Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| F5 Trust observer fires on `created` but the gift creation fails (e.g. DB error) → trust exists without CLT | Low | Medium | Observer catches `\Throwable` and logs; no transaction wrap needed (the CLT being missing is recoverable, and the alternative was an orphan CLT which is worse). Add a scheduled job to reconcile missing CLTs against trusts with `initial_value > 0`. |
| F2 `SpouseCollisionException` renamed type breaks an existing caller of `SpouseLinkingService` | Low | Low | Grep `app/` for `linkOrCreateSpouse` before merging. Only caller today is `handleCaptureSpouseDetails` (already in scope). |
| C1 `PreviewWriteInterceptor` `EXCLUDED_ROUTES` addition accidentally excludes real writes that SHOULD be blocked for preview users | Low | Medium | The pattern `str_starts_with($currentPath, $excludedRoute.'/')` with `'api/ai-chat/onboarding'` only matches the two new endpoints. Verified against the route list. Feature test in FR-M11. |
| Hybrid skip rule on `base_personal` (G2) produces awkward prompt copy ("Got it — I already have you as born {date}") that sounds robotic to some users | Medium | Low | Default copy in spec §5.3 is the baseline; UX writer can refine during implementation. Metric 6 ("off-script per 100 turns") is unrelated but will catch weirdness. |
| F3 prompt tightening causes the LLM to refuse legitimate confirmations ("Got it — recording those now.") because the filter suppresses content events too aggressively | Medium | Low | Spec §10.3 is explicit: selective filter (swallow if contains `?` OR zero tool calls), not unconditional swallow. Playwright test in FR-M11 covers the confirmation-message case. |
| F4 allowlist missing an entity type a developer adds later → silent block on legitimate LLM-driven edit | Medium | Low | Default to the existing `getFillable()` fallback when the entity type isn't in the allowlist. Add a deprecation log line. |
| Merge-back to `dev` collides with PR #220 (tech-debt) changes in `CoordinatingAgent.php` and `AiToolDefinitions.php` | Medium | Medium | Cross-reference file diff before merge per `feedback_merge_branch_conflicts`. Onboarding branch was forked pre-tech-debt; expect real conflicts in the decimal:2 cast area and the AI tool definitions refactor. |
| Production rollout hits an edge case not covered by preview-persona testing (real users have data shapes preview personas don't) | Medium | Medium | Roll out dev → main behind the kill switch OFF initially. Flip the flag on for 10 % of new registrations, monitor metrics for 48 hours, then full on. |
| iOS Capacitor users click the CTA, get routed to the web flow, and break because `MobileFynChat.vue` handling is different | Low | Low | CTA is web-only this release. Mobile app users see the existing onboarding path. Test by loading `fynla.org` on iOS Safari in-app browser. |

### Technical dependencies

- **`TaxConfigService`** — indirectly via `SystemPromptBuilder` and `EmptyDataGuard`. The onboarding flow itself touches no tax rates.
- **`ExpenditureProfile`** model — F1 and F8 both depend on this. Schema is stable.
- **`SpouseLinkingService`** — F2 changes its exception type.
- **`PreviewWriteInterceptor`** — C1 modifies `EXCLUDED_ROUTES`.
- **`CoordinatingAgent`** — many handlers touched (F1, F2, F3, F4, F5, F6, F9).
- **`HasAiChat` trait** — F3 asset_capture filter lives in the director's delegation; trait unchanged.
- **Anthropic + Grok/xAI** — `AI_PROVIDER` config switches between them; onboarding grouped_extract works against both per spec §11.2. F3 fix must be tested on Grok (active on dev) AND Claude (active elsewhere).
- **`AiToolDefinitions`** — F12 adds 6–7 routes to the `navigate_to_page` allow-list.

### Sequencing dependencies

- FR-M1–M8 already shipped; no sequencing.
- **FR-M9 (C1 preview)** must land before any preview-persona QA of the onboarding flow, otherwise preview test data will get corrupted.
- **FR-M10 (G2 hybrid skip)** is independent of M9 but should land before QA starts so the skip-path case is testable.
- **FR-M11 (G1 feature tests)** is a dependency of the production gate. The CI suite must include the new tests before `dev → main`.
- **FR-M12 (F1 expenditure sync)** should land alongside FR-N1/FR-N2 (F7/F8 SystemPromptBuilder changes) if possible, so the prompt/dashboard/Fyn all agree in one deploy.
- **FR-M13 (F2 spouse collision)** is independent but blocks preview-persona testing with shared seed data.
- **FR-M14 (F3 off-script)** requires browser testing on Grok specifically; schedule after a fresh csjones.co/fynla deploy.
- **FR-M15 (F5 trust observer)** must land with a migration-free observer registration — check `AppServiceProvider` vs model `booted()` for registration pattern consistency.
- **P1 items (FR-S1–S4)** can ship in a follow-up PR after the P0 set is green.
- **P2 items (FR-N1–N7)** can ship as small incremental PRs.
- **Merge to `dev`**: follows PR-style `feature/csj/*` → `dev` per project workflow. Cross-check with the 77-commits-behind-dev gap (onboardingFyn currently) — `feedback_merge_branch_conflicts`.
- **Production**: separate `dev → main` PR only after dev stability for ≥ 48 hours.

### Residual concerns from codebase audit

- **Spec §3.3 "$fillable" instruction was wrong** — spec now corrected to say `$guarded`. Implementation is correct. No residual risk.
- **Mobile onboarding descoped** — acceptable for this release; a follow-up PRD will spec the mobile port.
- **Asset_capture delegated turn content-event filter is nuanced** — FR-M14's implementation needs careful Playwright coverage. Flagged in risks above.
- **No regression test for the 16 April bug class** beyond unit tests — the `OnboardingChatDirectorFixesTest.php` covers the fixes but doesn't re-run the exact 16 April transcript as a browser test. FR-M11 should include that sequence.

---

## 9. Document History

| Date | Change | By |
|---|---|---|
| 20 April 2026 | Initial draft — reconciliation of 15 April spec/plan against live code on `onboardingFyn` branch through commit `88018a5`, plus F1–F13 follow-up work locked into scope | prd-writer skill (session 1, 20 April) |
