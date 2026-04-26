# CSJTODO — Fynla

*Last updated: 26 April 2026 — session 84 (BS-01 GREEN via real-user flow + S0.5.z registration consent fix)*
*Previous session: 26 April 2026 — session 83 (Sprint 0.16b Batch 2 fully GREEN + LLM-independence shift)*

---

## Next session: continue S0.16b Batch 3 — 14 scenarios remaining

Session 84 closed with **BS-01 onboarding path-choice-to-done GREEN end-to-end via the canonical Quick start with Fyn real-user flow** and **S0.5.z** shipped — a real registration-flow gap that was silently locking every newly-registered user out of onboarding. CSJ pushed back hard on the first attempt's seeding approach and the redundant questions that came with it, and instructed: drive the test as a real user would, from the CTA through to completion, clicking and filling actual forms, creating records the way the product creates them. Reset the approach. The fix lives in `AuthController::verifyCode` and runs immediately after `TrialService::startTrial`. The dev environment (`csjones.co/fynla`) needs the AuthController fix uploaded BEFORE further BS-NN runs there — see the deploy note.

**The next session should:**

1. Read this file top-to-bottom.
2. Run `./dev.sh` to start the local dev stack.
3. Run targeted Pest sweep to verify no regressions from session 84's AuthController change:
   ```
   ./vendor/bin/pest tests/Feature/Auth tests/Feature/AI tests/Feature/Fyn tests/Feature/Onboarding tests/Architecture
   ```
   (Session 84 baseline: 486 passing.)
4. Continue S0.16b Batch 3 with **BS-02 (base spouse direct-write)** next. All 14 remaining scenarios run via the same canonical Quick start with Fyn real-user pattern — no factory seeds, ever. Update each stub docblock with a delivery note as you go.

All Sprint 0 work stays on `feature/fyn-persona-split` locally until S0.17 verification rollup is complete. The deploy note (`April/April26Updates/deploy-session-84.md`) sits there for the eventual `feature → dev` PR after Sprint 0 is 100% green; it is NOT a precondition for any BS-NN run.

**Read these before starting:**

- This file top-to-bottom (handover).
- `April/April26Updates/deploy-session-84.md` — deploy steps for S0.5.z to csjones.co/fynla (and later fynla.org).
- `tests/Browser/scenarios/BS-01-onboarding-path-choice-to-done.php` — full session 84 delivery note explaining the wrong "stub gaps" the first attempt produced (factory-user shortcuts) vs the three real stub-script amendments uncovered via the canonical real-user flow. Read this so the next session doesn't repeat the factory-seed mistake.
- `April/April24Updates/plan/10-sprint-0-plan.md` §S0.5.z (gitignored, vault has the mirror) — registration consent fix details + verification steps.
- `MEMORY.md` "Top laws" — `feedback_loop_until_correct.md`, `critical_browser_testing_law.md`.

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

#### S0.16b Batch 3 — 14 remaining scenarios

- [ ] **BS-02** — base spouse direct-write
- [ ] **BS-04** — resume after disconnect
- [ ] **BS-05** — journey map by entry source
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
