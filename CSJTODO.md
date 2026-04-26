# CSJTODO — Fynla

*Last updated: 26 April 2026 — session 86 (stale-test fix → 486 baseline held; BS-05 deferred to post-sprint workstream; new `15-post-sprint-priorities-plan.md` queued)*
*Previous session: 26 April 2026 — session 85 (BS-02 + BS-04 GREEN, 7 product fixes shipped)*

---

## Needs design + planning before implementation

### Resume-onboarding-after-pause UX (uncovered by BS-04, 2026-04-26)

When a user clicks **Something else** on the welcome-back greeting, onboarding pauses cleanly: `onboarding_fyn_step` is nulled, `onboarding_fyn_context.paused_at_step` records where they were, and Fyn hands them to AdviceFyn for free-text. The data layer is fine. **The product gap**: there is no UI affordance to bring them back. Once paused, the user can't resume onboarding without re-registering.

**Surface choice (CSJ direction 2026-04-26): the chat window is NOT the right place.** Putting a "Continue Onboarding" bubble back into the chat would defeat the point of the handoff — the user just paused onboarding *to get the chat*. The resume affordance needs to live somewhere persistent and ambient.

Candidate surfaces (need design call):
- Dashboard banner / hero card ("You started onboarding — pick up where you left off")
- Global header strip (alongside the trial-countdown banner)
- Outstanding-actions list / profile-completeness widget
- Notification-style toast on next dashboard mount

The data + backend wiring is already in place: read `onboarding_fyn_context.paused_at_step`, restore `onboarding_fyn_step`, re-fire `postAction('resume')` from whatever surface the user clicks. Implementation is small once the surface is chosen.

**Action**: needs a design pass + plan entry before implementation. Not blocking BS-04 GREEN. Flag for the next planning round.

---

## Next session 87 — continue S0.16b Batch 3 — 11 scenarios remaining (BS-05 deferred)

Session 86 closed with the targeted Pest sweep at the post-session-85 baseline (486 passing) and BS-05 explicitly deferred to the post-sprint Lifestyle Landing Pages workstream per CSJ direction 2026-04-26. Two stale Pest assertions were updated to match session 85's intentional `restart` → `something_else` greeting bubble rename (`OnboardingResumeTest.php:54` and `ResumeAfterDisconnectTest.php:84`). A new top-level plan file `April/April24Updates/plan/15-post-sprint-priorities-plan.md` was created (mirrored to vault) capturing both the lifestyle and campaign landing-pages workstreams as the next priority once Sprints 0-4 are GREEN; one-line forward references were added to the tail of every sprint plan (10/11/12/13/14). Session 86 closed without a new commit yet — the BS-05 stub docblock + plan files remain unstaged so CSJ can review before the session-86 wrap commit.

**Session 87 should:**

1. Read this file top-to-bottom (especially the "Needs design + planning" section, the session 86 narrative below, and the BS-NN Batch 3 checklist with BS-05 now deferred).
2. Read `April/April24Updates/plan/15-post-sprint-priorities-plan.md` once so the post-sprint context is loaded; the Sprint 0 S0.15 entry now carries a deferral note pointing at PSP-LS, and BS-05's stub docblock has the same pointer.
3. Run `./dev.sh` to start the local dev stack.
4. **Targeted Pest sweep** to verify no regressions from session 86's stale-assertion fixes (the rename was assertion-only — no production code changed):
   ```
   ./vendor/bin/pest tests/Feature/Auth tests/Feature/AI tests/Feature/Fyn tests/Feature/Onboarding tests/Architecture
   ```
   Baseline: 486 passing. If anything fails, fix in the same loop before continuing.
5. **Continue S0.16b Batch 3** with **BS-06 (parked facts flush)** next. **11 scenarios remaining** (BS-06, 07, 10, 13, 15, 17, 18, 19, 21, 22, 23 — BS-05 is now deferred, not next). All run via the canonical Quick start with Fyn real-user pattern — **no factory seeds, ever, no SQL fixtures, no manual consent grants, no manual trial starts** (session 85 reinforced this — when BS-04 stub said "backdate `ai_messages.created_at`", CSJ correctly directed to drive sign-out + sign-in instead, which uncovered the 7 real bugs). Update each stub docblock with a delivery note as you go.

All Sprint 0 work stays on `feature/fyn-persona-split` locally until S0.17 verification rollup is complete. The deploy note (`April/April26Updates/deploy-session-84.md`) sits ready for the eventual `feature → dev` PR after Sprint 0 is 100% green; it is NOT a precondition for any BS-NN run.

**Lifestyle + campaign landing pages — queued post-Sprints 0-4 GREEN.** See `April/April24Updates/plan/15-post-sprint-priorities-plan.md`. Campaign side has an existing 617-line draft at repo root (`CSJ-CAMPAIGN-LANDING-PLAN.md`, untracked, last updated 2026-04-25) that becomes the canonical campaign plan when PSP-C kicks off. Lifestyle side will need a parallel plan written at PSP-LS kickoff. Architectural extensibility (config-driven entry-source map, additive `journey_map`, optional first-state argument on `OnboardingChatDirector::emitFirstTurn`) is already in place from Sprint 0 work — no current-sprint code change needed to keep this open for future entry sources.

**Read these before starting:**

- This file top-to-bottom (handover).
- `April/April24Updates/plan/15-post-sprint-priorities-plan.md` (NEW session 86 — also vault-mirrored) — post-Sprint-0-4 lifestyle + campaign workstream queue. Read once so the BS-05 deferral context is loaded.
- `tests/Browser/scenarios/BS-05-journey-map-by-entry-source.php` — session 86 delivery note explaining the user-visible-flow deferral.
- `tests/Browser/scenarios/BS-04-resume-after-disconnect.php` — full session 85 GREEN delivery note with all 7 product fixes itemised, plus the "Outstanding follow-up" pointing at the resume-from-pause UX gap.
- `tests/Browser/scenarios/BS-02-base-spouse-direct-write.php` — session 85 GREEN delivery note with three stub-script amendments.
- `tests/Browser/scenarios/BS-01-onboarding-path-choice-to-done.php` — session 84 delivery note: read this so session 87 doesn't repeat the factory-seed mistake.
- `April/April26Updates/deploy-session-84.md` — deploy steps for S0.5.z (still pending the eventual `feature → dev` PR; not blocking BS-NN runs).
- `April/April24Updates/plan/10-sprint-0-plan.md` (gitignored — vault mirror at `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/plan/10-sprint-0-plan.md`) — Sprint 0 plan with all S0.5.* sub-tasks; S0.15 entry now carries the BS-05 deferral note.
- `MEMORY.md` "Top laws" — especially `feedback_loop_until_correct.md`, `critical_browser_testing_law.md`, `feedback_never_touch_env_or_db.md`.

---

## Session 86 — stale-test fix, BS-05 deferred, post-sprint plan queued

### Completed this session

- [x] **Session-bootstrap operational checks** — git clean (untracked `CSJ-CAMPAIGN-LANDING-PLAN.md` carried), branch `feature/fyn-persona-split` up-to-date with origin, no conflict markers, no pending migrations, DB seeded, dev server up. No worktrees.

- [x] **Targeted Pest sweep first pass — 2 failures.** Both stale-assertion regressions from session 85's intentional `restart` → `something_else` rename of the welcome-back greeting bubble (CSJ direction during session 85 BS-04 work). The product code is correct; only the test assertions were behind:
  - `tests/Feature/Onboarding/OnboardingResumeTest.php:54` asserted `bubbleIds → ['continue', 'restart']`. Updated to `['continue', 'something_else']` plus matching docblock + test-name update.
  - `tests/Feature/Onboarding/ResumeAfterDisconnectTest.php:84` asserted `['id' => 'restart', 'label' => 'Start over']`. Updated to `['id' => 'something_else', 'label' => 'Something else']` plus matching docblock + test-name update.

- [x] **Targeted Pest sweep re-run — 486 passing across `tests/Feature/Auth tests/Feature/AI tests/Feature/Fyn tests/Feature/Onboarding tests/Architecture` (1605 assertions, 0 failures).** Exact match to the session 84 baseline. No other regressions.

- [x] **BS-05 deferred per CSJ direction (2026-04-26).** Driving BS-05 via the canonical real-user flow surfaced that the 4 entry-source CTAs the stub script assumes (Starting Out / Building Foundations / Protecting What Matters / Planning Your Future → `/register?from={journey_id}`) do not exist in the product. The current landing page has only `/register?from=fyn`. The existing `/stage/*` SEO pages route to `/register?stage={career_stage}` which feeds the legacy Onboarding flow, not the journey-map flow. Frontend plumbing to forward `from` for journey-map keys is also missing (Register.vue at lines 341-352 only special-cases `from === 'fyn'`; `aiChatService.startOnboardingStream` sends body `'{}'` with no `from` field). Backend half of INV-2.2.5 is complete and Pest-verified end-to-end by `EntrySourceJourneyMapTest` (S0.15.2). CSJ direction: BS-05 user-visible flow is part of the Lifestyle Landing Pages workstream, paired with the parallel Campaign Landing Pages workstream — both built together, both queued after Sprints 0-4 GREEN.

- [x] **Created `April/April24Updates/plan/15-post-sprint-priorities-plan.md`** (106 lines, mirrored to fynlaBrain vault). Captures: gate (sprints 0-4 verification rollups all GREEN), architectural extensibility brief (config-driven entry-source map applies to current sprint work — no Sprint 0-4 code change needed but no tightening either), PSP-LS (Lifestyle Landing Pages — 4-5 pages routing through `/register?from={journey_id}`, frontend plumbing list, director-side personalised welcome), PSP-C (Campaign Landing Pages — references existing 617-line `CSJ-CAMPAIGN-LANDING-PLAN.md` draft), PSP-S (shared plumbing both workstreams need). Aligned to existing plan-file style with task entries / acceptance / out-of-scope.

- [x] **Annotated Sprint 0 plan S0.15 entry** with the BS-05 user-visible-flow deferral note pointing at PSP-LS.

- [x] **Added "Post-sprint priorities" forward references** at the tail of every sprint plan (`10-sprint-0`, `11-sprint-1`, `12-sprint-2`, `13-sprint-3`, `14-sprint-4`) pointing at `15-post-sprint-priorities-plan.md`. Sprint 4's footer was rewritten to say "Next priority" (with the gate condition) instead of the original "Subsequent iterations get their own separate plans".

- [x] **Vault mirror sync** — copied 5 updated sprint plan files + the new `15-post-sprint-priorities-plan.md` to `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/plan/`. Vault mirror line counts now match local (106 / 568 / 218 / 291 / 157 / 240 = 1580 total).

- [x] **BS-05 stub docblock updated** with full delivery note explaining the deferral, the missing frontend plumbing, the backend Pest-sibling coverage, and the pointer to PSP-LS / PSP-S of the new plan. `markPendingInteractiveRun('BS-05')` retained.

### Outstanding for session 87

See "Next session 87" header at the top of this file. Summary: targeted Pest sweep at 486 baseline, then **BS-06 (parked facts flush)** — not BS-05. Batch 3 is now 11 scenarios remaining.

---

## Session 85 — BS-02 + BS-04 GREEN, 7 product fixes shipped (commits `b3e18e2`, `9b1c644`)

### Completed this session

- [x] **BS-02 (base spouse direct-write) GREEN end-to-end via canonical Quick start with Fyn flow.** User #356 (Marcus Holloway) → User #357 (Angela, aslater@gmail.com) bidirectionally linked, family_members #31/#32 created, audit chain rows confirm `capture_spouse_details` direct-write. /profile Family tab shows Angela with Spouse + Account Linked badges, DOB 12/01/1976, Age 50. 9 screenshots in `docs/sprint-0-verification/BS-02/`. Three stub-script amendments captured in delivery note (avatar dropdown route, Family card UI does not surface email, no /settings Connected accounts tab — all carry forward to S0.17 spec amendment list).

- [x] **BS-04 (resume after disconnect) GREEN end-to-end via canonical real-user flow.** Drove the test the way a user would: register → walk to base_dependants_detail → sign out → sign back in. Uncovered 7 real product bugs that were preventing the resume flow from ever reaching the user. All 7 fixed in the same loop per CLAUDE.md Rule #15:

  1. **`UserResource` missing `onboarding_fyn_step`/`path`/`selection`** — `AiChatPanel.vue` mid-onboarding check at line 1168 always returned false because the field was undefined in the auth/user payload. Frontend never dispatched `startOnboardingConversation` and never hit the resume path. Fixed: added the three fields to `app/Http/Resources/UserResource.php`.

  2. **`startOnboardingConversation` reloaded full history into the active chat** instead of dispatching the resume action. The result was a wall of past messages with no welcome-back greeting. Fixed: `aiChat.js` now commits `SET_CURRENT_CONVERSATION` + dispatches `postAction('resume')` so the active chat surface is reserved for the welcome-back turn (history lives in the sidebar).

  3. **Welcome-back bubble label "Start over" → "Something else"** (CSJ direction). Restart was a heavy hammer for someone who just wanted to ask a different question. Greeting wording updated to "...continue from where we left off, or is there something else I can help with?".

  4. **New `something_else` action handler** in `OnboardingChatDirector` — pauses onboarding (stores current step into `onboarding_fyn_context.paused_at_step`, nulls `onboarding_fyn_step`) and emits "Of course — what can I help you with?". Path + selection preserved for future resume. Action also added to `AiChatController` validation regex + `aiChat.js` validActions + `aiChatService.js` JSDoc union.

  5. **Quick-reply bubble click handler called `sendMessage(label)` for ALL bubbles** — including action bubbles. Clicking "Continue" persisted a user message "Continue", which `OnboardingDirector::handleUserMessage` treated as a state response (duplicate assistant turn) AND `HasAiChat`'s first-message-title hook overwrote the conversation title from "Onboarding" to "Continue", breaking `getOnboardingStatus`'s title filter on next sign-in. Fixed: `AiChatPanel.vue handleQuickReplySelect` differentiates action bubbles (`msg.metadata.action_bubbles === true` → `postAction(bubble.id)`) from regular bubbles (still `sendMessage(label)`).

  6. **`AiChatController::sendMessage` dispatch only checked `onboarding_completed`**, not `onboarding_fyn_step`. After "Something else" pause (step nulled), the next user message still routed to `OnboardingChatDirector` which silently no-op'd. Fixed: dispatch now also requires `onboarding_fyn_step !== null`, matching the `/action` endpoint check. Paused users now route to AdviceFyn on free-text.

  7. **`handleCaptureDependants` saved `first_name='Mia'` but did not set the legacy `name` column** (which has a "Unknown" default). Family tab UI surfaced `name`, so cards rendered "Unknown" instead of "Mia"/"Owen". Fixed: handler now sets both `first_name` AND `name` to the resolved value.

  14 screenshots in `docs/sprint-0-verification/BS-04/` covering the full RED → GREEN journey including each fix verification. BS-04 stub docblock updated with all 7 fixes itemised.

- [x] **Resume-onboarding-after-pause UX gap noted** at the top of this file under "Needs design + planning before implementation". CSJ explicitly directed: chat window is NOT the right surface. Needs design call on dashboard banner / global header / outstanding-actions list / etc. Data + backend wiring already in place — implementation is small once surface is chosen.

- [x] **Tech debt audit** — 5 doc-staleness items found (4 docblocks missing `something_else` in their action enum + 1 stale "Continue / Start over" reference + 1 duplicate screenshot). All fixed inline as part of the same change.

- [x] **Two commits pushed to origin**:
  - `b3e18e2` `feat(fyn): resume-after-disconnect end-to-end + Something Else handoff (BS-02 + BS-04 GREEN)` — 32 files, +376 / −110.
  - `9b1c644` `docs: session 85 end — CSJTODO + tech debt report (BS-02 + BS-04 GREEN)` — 2 files, +133 / −68.

- [x] **Vault sync** — Apr26.md updated (8 → 11 commits, session 85 narrative appended), Apr2026 Commits.md updated (620 → 623), Home.md updated (2,814 → 2,817 commits, April 620 → 623), April Index.md session 85 entry added + April26Updates section reference updated.

### Outstanding for session 86

See "Next session 86" header at the top of this file. Summary: targeted Pest sweep, then BS-05.

---

## Session 84 (10:06 onwards) — BS-01 GREEN via real-user flow + S0.5.z

### Completed this session

- [x] **Session-bootstrap Pest sweep** — 418 passing across `tests/Feature/AI`, `tests/Feature/Fyn`, `tests/Unit/Services/AI`, `tests/Architecture`. No regressions from session 83's AdviceFyn / handleInlineCapture / CoordinatingAgent changes.
- [x] **First BS-01 attempt (factory user) — abandoned.** Drove BS-01 GREEN via `User::factory()->create()` + manual consent + manual trial seeding. Got the test technically GREEN but surfaced a list of "stub gaps" that were mostly wrong, caused by diverging from the actual user journey rather than real product issues. CSJ pushed back hard on the seeding approach. Retracted the attempt; deleted the polluted User #222 + screenshots; restarted via the canonical UI flow.
- [x] **S0.5.z — Registration → onboarding consent gap (commit `085bfe7`).** Real product bug surfaced by driving BS-01 through the canonical Quick start with Fyn flow: `AuthController::verifyCode` created the user and started the trial but never recorded any GDPR consents. Dashboard's auto-onboarding immediately POSTs `/api/ai-chat/onboarding/start`, which gates on `TYPE_AI_CHAT` consent (`AiChatController:257`) — returned 403; frontend silently fell back to a blank conversation; onboarding never started for any newly-registered user. Form footer "By creating an account, you agree to our Terms of Service and Privacy Policy" was a UX promise the backend wasn't honouring. **Fix**: imported `App\Models\UserConsent` + `App\Services\GDPR\ConsentService`, injected `ConsentService` as a `private readonly` constructor dep, and called `$this->consentService->recordConsents($user, [terms => true, privacy => true, data_processing => true, ai_chat => true])` immediately after `startTrial`. Form footer makes terms+privacy explicit; data_processing is the lawful basis under which the app operates and is implicit at sign-up; ai_chat is implicit when the user enters via the Fyn CTA. INV-2.10.3 still applies — withdrawal continues to flow through `UserConsent::withdraw` and the runtime consent gate on every chat turn (existing `ConsentRuntimeCheckTest` still green).
- [x] **BS-01 GREEN end-to-end via the canonical real-user flow.** Landing page → "Quick start with Fyn" CTA (`/register?from=fyn`) → registration form fill → MFA → `/dashboard?openFyn=journey&newUser=1` (auto-onboarding fires) → walked every grouped-extract state. Final state: User #54 (Laury Marks, bs01-real@example.com), `onboarding_completed=true`, `onboarding_fyn_step=null`, dob=1985-01-12, marital=married, spouse_id=55 (Angela linked bidirectionally as User #55), employer=ACME Ltd, occupation=Engineer, annual_employment_income=75000, monthly_expenditure=2500, 1 LifeInsurancePolicy (Aviva £300k level_term), all 4 GDPR consents granted, trial active. 13 screenshots in `docs/sprint-0-verification/BS-01/` (01-landing, 02-register-form, 03-path-choice, 04-journey-choice, 05-base-personal, 06-base-spouse, 07-base-dependants, 08-profile-review-a, 09-base-employment, 10-base-work, 11-base-expenditure, 12-add-more, 13-completed).
- [x] **BS-01 stub delivery note** updated honestly — three real stub-script amendments captured (5 journey bubbles not 4; terminal label "I'm done" not "Finish for now"; final URL is journey's terminal module not /dashboard). Three wrong "gap" claims explicitly retracted with file:line evidence so next session doesn't repeat the factory-seed mistake (`Register.vue:346`, `OnboardingStateMachine:483-512` + `UserFactory:36`, `CoordinatingAgent.php:1220-1228`).
- [x] **Targeted Pest sweep post-fix**: 486 passing across `tests/Feature/Auth`, `tests/Feature/AI`, `tests/Feature/Fyn`, `tests/Feature/Onboarding`, `tests/Architecture` (1605 assertions, 0 regressions from the AuthController change).
- [x] **Plan amendment** — added S0.5.z entry to `April/April24Updates/plan/10-sprint-0-plan.md` (gitignored — local working notes; vault sync handles the mirror).
- [x] **Single commit `085bfe7` pushed** to origin: `feat(auth): record GDPR consents at registration verifyCode (S0.5.z)`. 15 files / +103 / -1 (1 PHP code change + 13 BS-01 screenshots + stub delivery note).

### NOT Done — Outstanding for next session

#### S0.16b Batch 3 — 11 remaining scenarios (BS-02 + BS-04 GREEN session 85; BS-05 deferred session 86)

- [x] **BS-02** — base spouse direct-write (GREEN session 85)
- [x] **BS-04** — resume after disconnect (GREEN session 85, 7 product fixes shipped — see below)
- [~] **BS-05** — journey map by entry source — **DEFERRED to PSP-LS / PSP-S in `15-post-sprint-priorities-plan.md`** (session 86, CSJ direction 2026-04-26). Backend half complete + Pest-sibling green; user-visible flow ships with the Lifestyle Landing Pages workstream after Sprints 0-4 GREEN.
- [ ] **BS-06** — parked facts flush
- [ ] **BS-07** — dispatch flips after onboarding
- [ ] **BS-10** — out-of-remit refusal
- [ ] **BS-13** — token-limit system message
- [ ] **BS-15** — hash-chain audit admin view
- [ ] **BS-17** — multi-entity persist
- [ ] **BS-18** — SSE abort keep writes
- [ ] **BS-19** — gap-fill dedup on retry
- [ ] **BS-21** — CoreIdentity tone
- [ ] **BS-22** — consent required mid-session
- [ ] **BS-23** — prompt injection sanitisation

**Pattern for ALL of these (do not deviate):**

1. Sign out + clear session storage in browser.
2. Navigate to landing page → "Quick start with Fyn" CTA → fresh registration with a unique email.
3. Verify MFA via the pending registration's `verification_code` from DB.
4. Land on dashboard with auto-opened onboarding chat.
5. Drive the scenario via real keystrokes / clicks per the BS-NN stub script.
6. Verify DB state + DOM state + SSE events per the stub's assertions.
7. Capture screenshots into `docs/sprint-0-verification/BS-NN/`.
8. Update the stub docblock with a delivery note (date + GREEN/RED + any stub-amendments).

**No `User::factory()` seeds. No manual consent grants. No manual trial starts. No factory shortcuts of any kind.** The first attempt at BS-01 surfaced ghost gaps that don't exist in production. The only reliable seed is "register a fresh user via `/register?from=fyn`".

#### Spec-amendment list (carry forward to S0.17 verification)

- [ ] BS-01 stub script: journey-choice has 5 bubbles not 4 (Starting Out / Building Foundations / Protecting What Matters / Planning Your Future / Enjoying Your Wealth per `OnboardingStateMachine.php:96-126`).
- [ ] BS-01 stub script: terminal bubble label is `I'm done` not `Finish for now`.
- [ ] BS-01 stub script: final assertion should be "any authenticated route rendered with onboarding_completed=true" — Fyn auto-routes to the journey's terminal module (`/protection` for Protecting What Matters), not `/dashboard`.
- [ ] BS-16 stub seed expects `Invoice::factory(...)->state('paid')` but `invoices.status` ENUM is `draft|issued|void` — either widen the enum or update the stub. (Carried from session 83.)
- [ ] BS-16 stub seeds only `Subscription` + `Invoice` rows but `PaymentController::billingHistory` reads `$subscription->payments()`. Either widen the controller query or update the stub seed to include matching Payment rows. (Carried from session 83.)

#### WriteIntentClassifier extension (BS-17 prep, carried from session 83)

- [ ] Current scope: `protection_policy` has full duplicate-check logic (provider × sum_assured ±1%). Other entity types in `RecordDuplicateChecker::alreadyExists` return `false` — the classifier still routes to `handleInlineCapture` but doesn't suppress duplicates. Before BS-17 (multi-entity persist), extend duplicate-check logic for: `savings_account` (provider × account_type × current_balance ±1%), `investment_account` (provider × current_value ±1%), `pension` (provider × current_value ±1%), `property` (address fuzzy match), `goal` (goal_name).

### Context for next session

**Branch:** `feature/fyn-persona-split` at `085bfe7`. Working tree clean. Single commit pushed to origin this session (the consent fix + BS-01 evidence).

**Critical architectural state — registration → onboarding handshake (post-S0.5.z):**

```
Landing page → Quick start with Fyn CTA (/register?from=fyn)
  → register form submit → POST /api/auth/register
  → PendingRegistration created → verification email
  → MFA code entered → POST /api/auth/verify-code
  → AuthController::verifyCode:
      → User::create
      → TrialService::startTrial($user, 'standard', 'yearly')
      → ConsentService::recordConsents($user, [terms, privacy, data_processing, ai_chat])  ← S0.5.z added
  → Register.vue:346 router.push to Dashboard with {openFyn: 'journey', newUser: '1'}
  → Dashboard.vue:2157 sees openFyn=journey
  → dispatch('aiChat/startOnboardingConversation')
  → POST /api/ai-chat/onboarding/start
  → AiChatController::startOnboarding:
      → consent gate (line 257) — passes because S0.5.z granted ai_chat
      → SSE stream opens with first onboarding turn (path_choice state)
  → User walks the bubble-driven onboarding flow
  → onboarding_completed=true, journey's terminal module rendered
```

**S0.5.z covers all four implicit consents at registration. INV-2.10.3 still applies** — the runtime consent gate runs on every chat turn and re-checks. Withdrawal via /settings GDPR UI still works (existing `UserConsent::withdraw` path). All existing consent tests still green.

### Reference paths

- Sprint 0 plan (with S0.5.z entry): `April/April24Updates/plan/10-sprint-0-plan.md` (vault: `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/plan/`).
- BS-NN stubs: `tests/Browser/scenarios/BS-{NN}-{slug}.php`.
- Screenshot drop targets: `docs/sprint-0-verification/BS-NN/` (per scenario).
- Deploy notes: `April/April26Updates/deploy-session-84.md` (vault mirror in `April/April26Updates/`).

---

## Outstanding — Tech Debt Deferred

Carried over from session 78:
- W1 — generic global helper function names with collision risk (`function invokeProtectedMethod(...)` in ReadCompletenessTest, `function makeUserAtState(...)` in ParkedFactsFlushTest) — both reusable-sounding names with no scenario-prefix; future tests could redeclare and trigger fatal global-namespace collision.
- W2 — INV-2.6.1 partial: `handleModuleAnalysis` still wraps via `summariseToolAnalysis` at `app/Agents/CoordinatingAgent.php:1512` — spec text additionally calls for the bypass but S0.15 plan task only scoped list-handler completeness.

## Known Issues

- None blocking BS-NN work. All Sprint 0 work runs locally against `./dev.sh`.

## Deploy Status

- **All Sprint 0 work stays local** on `feature/fyn-persona-split` until S0.17 verification rollup is complete.
- **csjones.co/fynla (dev)** and **fynla.org (production)** — neither will receive Sprint 0 changes until the full Sprint 0 verification is green and CSJ opens the `feature → dev` PR. The deploy note (`April/April26Updates/deploy-session-84.md`) sits ready for that PR cycle, not as a precondition for BS-NN runs.
