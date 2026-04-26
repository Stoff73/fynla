# CSJTODO — Fynla

*Last updated: 26 April 2026 — session 88 (BS-07 GREEN end-to-end + dashboard goals chart bug fixed in same loop, two commits pushed `285dfd5` + `4ea2d38`)*
*Previous session: 26 April 2026 — session 87 (BS-06 GREEN, BS-07 abandoned mid-walk)*

---

## Next session 89 — continue S0.16b Batch 3

**9 BS-NN scenarios remaining** in Sprint 0.16b Batch 3: **BS-10, 13, 15, 17, 18, 19, 21, 22, 23** (BS-05 stays deferred to PSP-LS / PSP-S per session 86).

**Session 89 should, in this order:**

1. Read this file top-to-bottom.
2. Read `MEMORY.md` "Top laws" — especially `feedback_loop_until_correct.md`, `critical_browser_testing_law.md`.
3. Run `./dev.sh`.
4. Run `php artisan db:seed --force` (standard session-start practice).
5. Targeted Pest sweep — confirm 486 baseline still holds.
6. Pick the next scenario from BS-10/13/15/17/18/19/21/22/23 and walk it via the canonical Quick start with Fyn flow per the docblock contract.
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

## S0.16b Batch 3 — running checklist

- [x] **BS-02** — base spouse direct-write (GREEN session 85)
- [x] **BS-04** — resume after disconnect (GREEN session 85, 7 product fixes shipped)
- [~] **BS-05** — journey map by entry source — **DEFERRED to PSP-LS / PSP-S** in `15-post-sprint-priorities-plan.md` (session 86, CSJ direction 2026-04-26).
- [x] **BS-06** — parked facts flush (GREEN session 87)
- [x] **BS-07** — dispatch flips after onboarding (GREEN session 88, dashboard goals chart bug fixed in same loop)
- [ ] **BS-10** — out-of-remit refusal
- [ ] **BS-13** — token-limit system message
- [ ] **BS-15** — hash-chain audit admin view
- [ ] **BS-17** — multi-entity persist
- [ ] **BS-18** — SSE abort keep writes
- [ ] **BS-19** — gap-fill dedup on retry
- [ ] **BS-21** — CoreIdentity tone
- [ ] **BS-22** — consent required mid-session
- [ ] **BS-23** — prompt injection sanitisation

9 scenarios remaining. BS-05 deferred.

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

Carried over from session 78:
- W1 — generic global helper function names with collision risk (`function invokeProtectedMethod(...)` in ReadCompletenessTest, `function makeUserAtState(...)` in ParkedFactsFlushTest) — both reusable-sounding names with no scenario-prefix; future tests could redeclare and trigger fatal global-namespace collision.
- W2 — INV-2.6.1 partial: `handleModuleAnalysis` still wraps via `summariseToolAnalysis` at `app/Agents/CoordinatingAgent.php:1512` — spec text additionally calls for the bypass but S0.15 plan task only scoped list-handler completeness.

Carried forward (not actively reproducing, but logged for future sessions):
- **Issue 87-B suspect** — `phpunit.xml` lacks `DB_DATABASE` override, so Pest tests' `RefreshDatabase` hits the primary `laravel` DB instead of one of the existing `laravel_test_*` databases. If seed-data wipes resume in a future session during a Pest sweep, the fix is `<env name="DB_DATABASE" value="laravel_testing"/>` in `phpunit.xml`.

## Known Issues

None active. Issue 87-A and 87-B did not reproduce in session 88.

## Deploy Status

- **All Sprint 0 work stays local** on `feature/fyn-persona-split` until S0.17 verification rollup is complete.
- **csjones.co/fynla (dev)** and **fynla.org (production)** — neither will receive Sprint 0 changes until the full Sprint 0 verification is green and CSJ opens the `feature → dev` PR. The deploy note (`April/April26Updates/deploy-session-84.md`) sits ready for that PR cycle, not as a precondition for BS-NN runs.

## Branch state

`feature/fyn-persona-split` at `4ea2d38` (session 88 tech-debt report commit). Origin in sync. Working tree clean except for untracked `.claude/ccstatusline/`, `.claude/skills/session-startOLD/`, `.claude/statusline-*.sh`, `.claude/settings.json` modifications, `.claude/skills/session-start/SKILL.md` deletion, and untracked `CSJ-CAMPAIGN-LANDING-PLAN.md` — all carried scaffold/draft, not session work.
