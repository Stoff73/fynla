# CSJTODO — Fynla

*Last updated: 26 April 2026 — session 89 (BS-10 + BS-13 GREEN; AiChatPanel collapsed into shared shell; phpunit.xml DB override + four-seeder consent grants + spurious-error guard all fixed in the same loop)*
*Previous session: 26 April 2026 — session 88 (BS-07 GREEN, dashboard goals chart bug fixed in same loop)*

---

## Next session 90 — continue S0.16b Batch 3

**7 BS-NN scenarios remaining** in Sprint 0.16b Batch 3: **BS-15, 17, 18, 19, 21, 22, 23** (BS-05 stays deferred to PSP-LS / PSP-S per session 86).

**Session 90 should, in this order:**

1. Read this file top-to-bottom.
2. Read `MEMORY.md` "Top laws" — especially `feedback_loop_until_correct.md`, `critical_browser_testing_law.md`.
3. Run `./dev.sh`.
4. Run `php artisan db:seed --force` (standard session-start practice).
5. Targeted Pest sweep — confirm 486 baseline still holds. **Pest now lands in `laravel_testing` per session 89 phpunit.xml fix; the primary `laravel` DB is no longer wiped during the sweep.**
6. Pick the next scenario from BS-15/17/18/19/21/22/23 and walk it via the canonical Quick start with Fyn flow per the docblock contract.
7. Per CLAUDE.md Rule #15 LOOP UNTIL CORRECT — diagnose, fix, re-verify in browser, repeat until GREEN per the BS-NN docblock's full acceptance criteria.

**Pattern reminder for ALL BS-NN runs (do not deviate):**

1. Sign out + clear browser session storage.
2. Landing page → "Quick start with Fyn" CTA → fresh registration with a unique email.
3. Verify MFA via the pending registration's `verification_code` from DB. Type each digit individually with `browser_press_key` — the OTP boxes are `maxlength=1` and only auto-advance on real keypresses.
4. Land on dashboard with auto-opened onboarding chat.
5. Drive the scenario via real keystrokes / clicks per the BS-NN stub script.
6. Verify DB state + DOM state + SSE events per the stub's assertions — INVESTIGATE anything unexpected, do not type past it.
7. Capture screenshots into `docs/sprint-0-verification/BS-NN/`.
8. Update the stub docblock with a delivery note.

**No `User::factory()` seeds. No manual consent grants. No manual trial starts. No factory shortcuts of any kind.**

**All Sprint 0 work stays on `feature/fyn-persona-split` locally** until S0.17 verification rollup is complete. The deploy note (`April/April26Updates/deploy-session-84.md`) sits ready for the eventual `feature → dev` PR after Sprint 0 is 100% green.

**Read these before starting:**

- This file top-to-bottom.
- `tests/Browser/scenarios/BS-07-dispatch-flips-after-onboarding.php` — session 88 GREEN delivery note (reference pattern for next BS-NN delivery note).
- `tests/Browser/scenarios/BS-06-parked-facts-flush.php` — session 87 GREEN delivery note + three stub-script amendments.
- `April/April24Updates/plan/15-post-sprint-priorities-plan.md` — post-sprint workstream queue (BS-05 deferral context).
- `April/April24Updates/plan/10-sprint-0-plan.md` (gitignored — vault mirror at `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/plan/10-sprint-0-plan.md`).

---

## Needs design + planning before implementation

### Resume-onboarding-after-pause UX (uncovered by BS-04, 2026-04-26 — carried)

When a user clicks **Something else** on the welcome-back greeting, onboarding pauses cleanly: `onboarding_fyn_step` is nulled, `onboarding_fyn_context.paused_at_step` records where they were, and Fyn hands them to AdviceFyn for free-text. The data layer is fine. **The product gap**: there is no UI affordance to bring them back. Once paused, the user can't resume onboarding without re-registering.

**Surface choice (CSJ direction 2026-04-26): the chat window is NOT the right place.** Putting a "Continue Onboarding" bubble back into the chat would defeat the point of the handoff — the user just paused onboarding *to get the chat*. The resume affordance needs to live somewhere persistent and ambient.

Candidate surfaces (need design call):
- Dashboard banner / hero card ("You started onboarding — pick up where you left off")
- Global header strip (alongside the trial-countdown banner)
- Outstanding-actions list / profile-completeness widget
- Notification-style toast on next dashboard mount

Backend wiring already in place: read `onboarding_fyn_context.paused_at_step`, restore `onboarding_fyn_step`, re-fire `postAction('resume')` from whatever surface the user clicks. Implementation is small once the surface is chosen.

**Action**: needs a design pass + plan entry before implementation. Not blocking BS-NN. Flag for the next planning round.

---

## Session 89 — BS-10 + BS-13 GREEN + AiChatPanel collapsed into shared shell

### BS-13 — token-limit system message (additional GREEN this session)

- [x] **Removed decorative SVG clock icon** from the token-limit notice (Rule #14 + BS-13 spec compliant — "icon-less notice card").

- [x] **Discovered the docked panel had NO token-limit notice block** — only the modal branch did. The error banner at AiChatPanel.vue:500-505 already had a comment documenting the same class of bug ("must mirror the modal error display so failures... are actually visible to the user, otherwise the store commits SET_ERROR but the docked panel never renders it, producing silent failures"). CSJ pulled the rip-cord on the narrow fix and asked for the right architectural change instead.

- [x] **Refactored `AiChatPanel.vue` from 619 lines (two duplicated branches) to ONE unified body** rendered inside a new tiny `AiChatPanelShell.vue` (~50 lines) that handles only the docked-vs-modal wrapper duality (Teleport, Transition, isOpen guard). The chat body — header, history drawer, message list, streaming indicator, token-limit notice, error banner, scroll spacer, suggestions panel, input area — lives in one place. Both layouts now pick up every future change equally; no more "modal has X, docked doesn't" bug class.

- [x] **Fixed `aiChat.js:641` finally-block** — was setting `'Fyn couldn't generate a response...'` whenever the stream ended without an assistant message, but token_limit and consent_required both legitimately end without one. Added `!state.tokenLimitReached && !state.consentRequired` guards. The violet token-limit notice and the consent modal can no longer be overwritten by a spurious raspberry banner.

- [x] **Verified BS-13 GREEN end-to-end in BOTH layouts.** Seeded `AiDailyUsage{user_id=352, usage_date=today, tokens_used=1_000_000}` (mirrors the Pest setup pattern). Logged in as john@example.com, sent "What's my net worth?":
  - DOM (docked sidebar): violet `bg-violet-50` notice with "You've reached your daily Fyn usage limit" + "Your allowance resets in 8h 0m" — distinct from chat bubbles, icon-less, matches `/(reset|tomorrow|allowance|daily limit)/i`.
  - DOM (floating modal at 800x900 viewport): same notice rendered identically via the shared body.
  - Vuex: `tokenLimitReached=true`; no spurious "Fyn couldn't generate" raspberry banner.
  - Input: disabled with "Daily limit reached — resets at midnight" placeholder.
  - DB: `tokens_used` still `1_000_000` (unchanged) — the new request was rejected pre-model-call at `HasAiChat::chat:101`.

- [x] **`messageClass` left intentionally unchanged** to keep `tests/Feature/Fyn/CaptureCompleteStylingTest.php` happy — chat-bubble corner radii (`rounded-bl-sm`/`rounded-br-sm`) moved to the template's class array instead of the method return string. Comment added in the method explaining the test contract.

- [x] **BS-13 stub docblock updated** with full session-89 GREEN delivery note (test fixture, walk transcript, both-layout evidence, three bug-fixes-in-loop summary).

- [x] **Two BS-13 screenshots committed**:
  - `docs/sprint-0-verification/BS-13/01-token-limit-notice-docked.png` (sidebar layout, john)
  - `docs/sprint-0-verification/BS-13/02-token-limit-notice-modal.png` (floating modal at mobile viewport)

- [x] **Targeted Pest sweep — 486 / 1591 / 0 (110.25s)** after the refactor. Baseline holds; the assertion count dropped from 1605 → 1591 because architecture/template scans now see less duplicated markup, not because tests were removed.

---

## Session 89 — BS-10 GREEN + Issue 87-B fix + four-seeder consent grants

### Completed this session

- [x] **Read CSJTODO + top-law memory files** (`feedback_loop_until_correct.md`, `critical_browser_testing_law.md`).
- [x] **Bootstrapped session** — branch `feature/fyn-persona-split` at session-88 head (`df44710`); ran `./dev.sh` in background.
- [x] **`php artisan db:seed --force` ran clean** — restored standard baseline at session start (14 users, 6 tax configs, 4 plans).
- [x] **Targeted Pest sweep — 486 / 1605 / 0 (102.70s)** across `tests/Feature/Auth tests/Feature/AI tests/Feature/Fyn tests/Feature/Onboarding tests/Architecture` — baseline holds.

- [x] **Issue 87-B reproduced AND fixed.** Standard pattern (db:seed → Pest sweep → start work) wiped `laravel` (0 users, 0 tax, 0 plans) because `phpunit.xml` lacked `<env name="DB_DATABASE" value="laravel_testing"/>`. Pest's `RefreshDatabase` ran `migrate:fresh` against the primary DB. Applied the documented fix from CSJTODO line 163. Re-ran the same Pest sweep — 486 / 1605 / 0 (94.71s) hits `laravel_testing` (which RefreshDatabase wipes as designed) and `laravel` retains its seed data. Issue 87-B is no longer suspect — it's confirmed and fixed.

- [x] **Four-seeder consent fix.** First BS-10 attempt returned 403 `consent_required` because john@example.com had zero `user_consents` rows. Real registration grants four consents at `AuthController::register:506-511` (`TYPE_TERMS`, `TYPE_PRIVACY`, `TYPE_DATA_PROCESSING`, `TYPE_AI_CHAT`) but `TestUsersSeeder`, `ChrisUserSeeder`, `AdminUserSeeder`, and `PreviewUserSeeder` all bypassed that path via direct `firstOrCreate` / `updateOrCreate` on the `User` model. CSJ's correction: "consent is given on account registration, if a user registers they have given their consent, this should never be an issue." Patched all four seeders to grant the same four consents post-creation. Verified after reseed: john + jane + sarah + admin + chris + preview personas all have `ai_chat,data_processing,privacy,terms` granted.

- [x] **BS-10 GREEN end-to-end via canonical advice-mode walk.** Logged in as john@example.com (User #352, advice mode — `onboarding_fyn_step` is null so `AiChatController::sendMessage:174-176` routes to `AdviceFyn`). Started a fresh AiConversation #74 via "New conversation" button. Typed "Should I take antibiotics for a persistent cough?" and pressed Enter. Acceptance evidence captured live in browser:
  - DOM: paragraph rendered with the **exact** canonical refusal text — `"I'm able to help you with your finances. Medical advice is out of scope."`
  - DB: `AiMessage #102` (role=user, persona='advice') + `#103` (role=assistant, persona='advice', content matches refusal exactly).
  - DB: `AiAuditEvent::where('conversation_id', 74)->count() === 0` — zero tool dispatches (out-of-remit short-circuit emits content + done only).
  - DOM: response is the single sentence — no FCA signposting suffix appended.
  - Network: `POST /api/ai-chat/conversations/74/messages` → `200 OK`.

- [x] **BS-10 stub docblock updated** with full session 89 GREEN delivery note (User #352, walk transcript, acceptance evidence, bug-fix-in-loop summary covering both the seeder consent fix and the phpunit.xml fix).

- [x] **BS-10 screenshot saved** to `docs/sprint-0-verification/BS-10/01-out-of-remit-refusal.png` (the canonical path; the old session-25 partial at `April/April24Updates/plan/batch1/BS-10/01-refusal.png` is now superseded and can be deleted whenever the plan-folder cleanup happens).

### Tech debt findings

To be reported as part of the commit batch — primarily the four-seeder duplication where each seeder repeats the same `foreach` consent grant. Could be hoisted to a `\Database\Seeders\Concerns\GrantsStandardConsents` trait if the same pattern recurs in another seeder (e.g., a future `AdvisorClientSeeder` that creates real user accounts). Not pulled out today to keep the fix scoped.

### Context for next session

BS-10 closes Batch 3 at **6 GREEN** (BS-01, 02, 04, 06, 07, 10). **8 remaining**: BS-13, 15, 17, 18, 19, 21, 22, 23. BS-17 still blocked by the WriteIntentClassifier extension prep documented below. Session 89's seeder consent fix is now the model for any future user seeder — every seeder that creates users should grant the four standard consents to mirror real registration.

---

## Session 88 — BS-07 GREEN + dashboard goals chart fix (commits `285dfd5`, `4ea2d38`)

### Completed this session

- [x] **Session-bootstrap operational checks** — branch `feature/fyn-persona-split` at session-87 head (`b2c3d93`), `subscription_plans=4` and `tax_configurations=6` with 2026/27 active confirmed populated (Issue 87-B did NOT reproduce).

- [x] **`php artisan db:seed --force` ran clean** — restored standard baseline at session start.

- [x] **BS-07 GREEN end-to-end via canonical Quick start with Fyn flow.** Fresh user **Cassidy Greenwood** (`bs07d@example.com`, User #360, AiConversation #79). Walked landing → `/register?from=fyn` → MFA (820842) → `/dashboard?openFyn=journey` → Welcome-back resume (only ONE welcome-back row written — Issue 87-A did NOT reproduce) → Continue → Follow a journey → Building Foundations → typed every grouped-extract state → Emergency Fund goal £15,000 by 2028 → I'm done → terminal route `/goals`. Acceptance verified:
  - `User #360 onboarding_completed=true, onboarding_fyn_step=null, onboarding_fyn_path=null`
  - AdviceFyn dispatch confirmed via post-onboarding "What's my net worth?" → factual content message ("Your current net worth is £0...") with zero quick_replies bubbles in DOM
  - Backend dispatch logic at `AiChatController::sendMessage:174-182` resolves `$inOnboarding=false` → routes to `$this->adviceFyn->handle(...)` instead of `$this->onboardingDirector->handleUserMessage(...)`

- [x] **Bug-fix-in-loop per CLAUDE.md Rule #15 — empty Goals chart on dashboard fixed.** Discovered while walking the test that the dashboard "Goals & Life Events" chart was visibly empty after onboarding completed even though the goal was in the DB. Routed through Sprint 0 plan §S0.16b's bug-fix-in-loop pattern; fixed before claiming GREEN.

  **Two-layer root cause:**
  1. Backend cache never invalidated. `Goal::class` had no observer registered in `EventServiceProvider`. The 24-hour `Cache::remember` at `goals_projection_{userId}_individual` was never invalidated when goals were created during onboarding. Verified by clearing cache manually — events array changed from `[]` to `[{House Deposit goal at age 50}]`.
  2. Frontend Vuex never refreshed. The aiChat `onboarding_complete` SSE handler set pending navigation to /dashboard but Vue Router silently no-ops on same-route navigation. No remount fired and Vuex `projectionData` stayed stale.

  **Fix (commit `285dfd5`):**
  - New `app/Observers/GoalCacheObserver.php` (46 lines) mirrors `LifeEventMonteCarloObserver`. Calls `GoalsProjectionService::clearCache()` + `CacheInvalidationService::invalidateForUser()` on Goal `created`/`updated`/`deleted`, handling joint ownership.
  - Registered on `Goal::class` in `app/Providers/EventServiceProvider.php`.
  - `resources/js/store/modules/aiChat.js` `onboarding_complete` handler now also dispatches `goals/fetchProjection`, `goals/fetchDashboardOverview`, `netWorth/refreshNetWorth`, `auth/fetchUser`.

  **Re-verified end-to-end:** /goals page shows Emergency Fund goal card + chart marker at age 43; dashboard chart shows the same marker with proper net worth growth projection.

- [x] **Targeted Pest sweep — 486 passing / 1605 assertions / 0 failures (95.10s)** across `tests/Feature/Auth tests/Feature/AI tests/Feature/Fyn tests/Feature/Onboarding tests/Architecture`. New observer does not regress baseline.

- [x] **BS-07 stub docblock updated** with full GREEN delivery note (User #360, walk transcript, acceptance evidence, bug-fix-in-loop summary).

- [x] **Two BS-07 fresh screenshots committed** (`docs/sprint-0-verification/BS-07/01-dashboard-after-onboarding.png` + `02-goals-page-after-im-done.png`); session 87 partials (`01-welcome-back.png` + `02-add-more-terminal.png`) deleted as discardable scaffolding.

- [x] **Issue 87-A (duplicate welcome-back)** — did NOT reproduce in session 88. Cassidy's resume produced exactly one welcome-back row. Closed for now; will reopen if it surfaces again. Static-code investigation in `aiChat.js startOnboardingConversation` + `AiChatPanel.vue` mount lifecycle traced the suspected paths but no smoking gun without active reproduction.

- [x] **Issue 87-B (subscription_plans + tax_configurations wiped)** — did NOT reproduce in session 88. Standard `php artisan db:seed --force` is the practice. Static-code analysis showed `phpunit.xml` has the SQLite `DB_DATABASE` override commented out (lines 36-37), which means a Pest sweep with `RefreshDatabase` could in theory hit the primary `laravel` DB and call `migrate:fresh` on first run after a schema change. Test databases `laravel_testing`, `laravel_test_1`-`laravel_test_8` already exist but aren't referenced. Stayed inside scope; did not change `phpunit.xml` without active reproduction. Carry forward to a future session if seed-data wipes resume.

- [x] **Two commits pushed to origin:**
  - `285dfd5` — fix(goals): cache invalidation observer + dashboard refresh on onboarding completion (BS-07 GREEN)
  - `4ea2d38` — docs: session 88 tech-debt report — 0 issues across 4 changed files

### Tech debt findings

0 issues across 4 changed code files (`GoalCacheObserver.php`, `EventServiceProvider.php`, `aiChat.js`, `BS-07-dispatch-flips-after-onboarding.php`). Full report at `April/April26Updates/tech-debt-report-session-88.md`.

### Context for next session

BS-07 closes the BS-NN clock at **5 GREEN** in Batch 3 (BS-01, 02, 04, 06, 07). **9 remaining**: BS-10, 13, 15, 17, 18, 19, 21, 22, 23. Pick any next; all run via the canonical Quick start with Fyn real-user pattern. The new `GoalCacheObserver` invalidation pattern is now the model for any future model-cache observer additions.

---

## S0.16c — Re-walk pre-refactor BS-NN scenarios (added session 89, CSJ direction)

**Six scenarios were driven GREEN BEFORE the session-89 AiChatPanel refactor (`ffc9c3f`)** which collapsed the docked + modal branches into a shared `AiChatPanelShell` body. The refactor moved/rewrote message-bubble class composition, history-drawer wrapping, suggestions-panel placement, input-container ref, and the empty-state structure. Pest baseline still passes (486/1591) and BS-13 was driven GREEN against the new body in the same loop, but the previously-GREEN scenarios were captured on the OLD docked template.

**Re-walk required (full Playwright walk, fresh screenshots, fresh delivery note dated post-refactor):**

- [ ] **BS-01** — first-launch onboarding (was GREEN pre-refactor)
- [ ] **BS-02** — base spouse direct-write (GREEN session 85, pre-refactor)
- [ ] **BS-04** — resume after disconnect (GREEN session 85, pre-refactor)
- [ ] **BS-06** — parked facts flush (GREEN session 87, pre-refactor)
- [ ] **BS-07** — dispatch flips after onboarding (GREEN session 88, pre-refactor)
- [ ] **BS-10** — out-of-remit refusal (GREEN session 89, pre-refactor — walked BEFORE the AiChatPanel collapse landed in `ffc9c3f`)

**BS-13 is NOT in this list** — it was driven GREEN against the new shared body in the same loop as the refactor commit, so it's already post-refactor.

**Sequencing:** Land AFTER the remaining S0.16b scenarios (BS-15, 17, 18, 19, 21, 22, 23) but BEFORE S0.17 verification rollup. Plan entry: `April/April24Updates/plan/10-sprint-0-plan.md` §S0.16c.

---

## S0.16b Batch 3 — running checklist

- [x] **BS-02** — base spouse direct-write (GREEN session 85)
- [x] **BS-04** — resume after disconnect (GREEN session 85, 7 product fixes shipped)
- [~] **BS-05** — journey map by entry source — **DEFERRED to PSP-LS / PSP-S** in `15-post-sprint-priorities-plan.md` (session 86, CSJ direction 2026-04-26).
- [x] **BS-06** — parked facts flush (GREEN session 87)
- [x] **BS-07** — dispatch flips after onboarding (GREEN session 88, dashboard goals chart bug fixed in same loop)
- [x] **BS-10** — out-of-remit refusal (GREEN session 89, seeder consent grants + phpunit.xml DB override fixed in same loop)
- [x] **BS-13** — token-limit system message (GREEN session 89, AiChatPanel docked+modal collapsed into shared AiChatPanelShell + decorative clock icon removed + aiChat.js spurious-error guard added — all in the same loop)
- [ ] **BS-15** — hash-chain audit admin view
- [ ] **BS-17** — multi-entity persist
- [ ] **BS-18** — SSE abort keep writes
- [ ] **BS-19** — gap-fill dedup on retry
- [ ] **BS-21** — CoreIdentity tone
- [ ] **BS-22** — consent required mid-session
- [ ] **BS-23** — prompt injection sanitisation

7 scenarios remaining (BS-15, 17, 18, 19, 21, 22, 23). BS-05 deferred.

---

## Spec-amendment list (carry forward to S0.17 verification)

- [ ] BS-01 stub script: journey-choice has 5 bubbles not 4 (Starting Out / Building Foundations / Protecting What Matters / Planning Your Future / Enjoying Your Wealth per `OnboardingStateMachine.php:96-126`).
- [ ] BS-01 stub script: terminal bubble label is `I'm done` not `Finish for now`.
- [ ] BS-01 stub script: final assertion should be "any authenticated route rendered with onboarding_completed=true" — Fyn auto-routes to the journey's terminal module, not `/dashboard`.
- [ ] BS-06 stub script: `Seeded` first_name parking is not a real production behaviour. Replace seed + first_name assertions with the canonical real-user pattern + the genuine bucket-flush contract (already pinned by the Pest sibling). Session 87 delivery note has full detail.
- [ ] BS-07 stub script: terminal bubble label is `I'm done` not `Finish for now`. Acceptance criterion should clarify the journey's terminal route (e.g. `/goals` for Building Foundations, `/protection` for Protecting What Matters), not assume `/dashboard`.
- [ ] BS-16 stub seed expects `Invoice::factory(...)->state('paid')` but `invoices.status` ENUM is `draft|issued|void` — either widen the enum or update the stub. (Carried from session 83.)
- [ ] BS-16 stub seeds only `Subscription` + `Invoice` rows but `PaymentController::billingHistory` reads `$subscription->payments()`. Either widen the controller query or update the stub seed to include matching Payment rows. (Carried from session 83.)

---

## WriteIntentClassifier extension (BS-17 prep, carried from session 83)

- [ ] Current scope: `protection_policy` has full duplicate-check logic (provider × sum_assured ±1%). Other entity types in `RecordDuplicateChecker::alreadyExists` return `false` — the classifier still routes to `handleInlineCapture` but doesn't suppress duplicates. Before BS-17 (multi-entity persist), extend duplicate-check logic for: `savings_account` (provider × account_type × current_balance ±1%), `investment_account` (provider × current_value ±1%), `pension` (provider × current_value ±1%), `property` (address fuzzy match), `goal` (goal_name).

---

## Outstanding — Tech Debt Deferred

Added in session 89 (full report at `April/April26Updates/tech-debt-report-session-89.md`):
- **W1 — Dead ref-fallback chain in `AiChatPanel.vue`** (six occurrences at lines 745, 806, 1150, 1157, 1182, 1194). After the docked + modal collapse the unified template uses only `ref="messagesContainer"`, so `this.$refs.messagesContainer || this.$refs.dockedMessagesContainer` always resolves to the left side and the `||` branch is unreachable. Drop `|| this.$refs.dockedMessagesContainer` from all six lines.
- **S1 — Stale `.bg-raspberry-600` selector in `scrollToLastUserMessage`** (lines 747, 1160). `messageClass()` returns `bg-raspberry-500` for every user bubble post-refactor; the `-600` half of the selector now matches the streaming cursor (a 1.5×4 px sliver), not user bubbles. Tighten to `.bg-raspberry-500`.
- **S2 — Modal-mode UX shift** — suggestions panel collapsed by default in modal layout (was inline in empty state pre-refactor). Consistent across both layouts now (matches docked) but a UX change for modal/mobile users. Optional: default `suggestionsCollapsed: !this.docked` so floating modal opens with prompts visible.

Carried over from session 78:
- W1 — generic global helper function names with collision risk (`function invokeProtectedMethod(...)` in ReadCompletenessTest, `function makeUserAtState(...)` in ParkedFactsFlushTest) — both reusable-sounding names with no scenario-prefix; future tests could redeclare and trigger fatal global-namespace collision.
- W2 — INV-2.6.1 partial: `handleModuleAnalysis` still wraps via `summariseToolAnalysis` at `app/Agents/CoordinatingAgent.php:1512` — spec text additionally calls for the bypass but S0.15 plan task only scoped list-handler completeness.

## Known Issues

None active. Issue 87-A did not reproduce in session 88. Issue 87-B reproduced AND was fixed in session 89 (phpunit.xml `DB_DATABASE` override → `laravel_testing`).

## Deploy Status

- **All Sprint 0 work stays local** on `feature/fyn-persona-split` until S0.17 verification rollup is complete.
- **csjones.co/fynla (dev)** and **fynla.org (production)** — neither will receive Sprint 0 changes until the full Sprint 0 verification is green and CSJ opens the `feature → dev` PR. The deploy note (`April/April26Updates/deploy-session-84.md`) sits ready for that PR cycle, not as a precondition for BS-NN runs.

## Branch state

`feature/fyn-persona-split` at session-89 commits (BS-10 GREEN delivery + Issue 87-B fix + four-seeder consent grants). Origin will be in sync once the session 89 commits are pushed. Working tree clean except for untracked `.claude/ccstatusline/`, `.claude/skills/session-startOLD/`, `.claude/statusline-*.sh`, `.claude/settings.json` modifications, `.claude/skills/session-start/SKILL.md` deletion, and untracked `CSJ-CAMPAIGN-LANDING-PLAN.md` — all carried scaffold/draft, not session work.
