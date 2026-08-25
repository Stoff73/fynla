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

# Sprint 4 — Production Hardening

> **BRANCH: `feature/fyn-persona-split`.** Production deploy requires `feature/fyn-persona-split` → `dev` → `main` per `CLAUDE.md` branch workflow. Sprint 4 tasks span both code commits and external-calendar items (legal, DPIA).
>
> **REQUIRED SUB-SKILL:** `superpowers:subagent-driven-development` or `superpowers:executing-plans` for the code tasks. External tasks are CSJ-calendar-bound.

**Goal:** Pass through the external-legal / compliance / ops hardening gate so Fyn v2 can ship to `fynla.org` (production). End state: Rubric-A ~28-30/40 🟡 Commercial-ready (solid). Privacy Policy factually consistent with code. Provider failover + Sentry error reporting in place. DPIA on file.

**Architecture:** Code deltas are comparatively small: provider failover logic in `HasAiChat`, Sentry integration, per-provider timeout parity. The bulk of Sprint 4 is external work (legal opinion, DPIA drafting, DPA verification, UK IDTA, TRA) which is calendar-bound by third parties and does NOT require branch commits.

**Tech Stack:** Laravel 10 + Sentry PHP SDK. External: commercial legal, external DPO, Anthropic + xAI commercial contacts.

**Spec reference:** INV-2.9.2 (instrumented monitoring confirms keep-policy), INV-2.10.2 (chain retention job in production), §4 out-of-scope items for MVP that land here.

---

## Pre-flight

- Sprint 3 deployed to `csjones.co/fynla`.
- Dev build running for at least 2 weeks with log monitoring.
- No canonical-violation incidents in dev logs.

---

## Track A — External / calendar-bound (parallel to Track B)

These run in parallel with code work. Each is CSJ-owned; this plan tracks them but does not prescribe TDD cycles.

- [ ] **A.1 — External legal opinion on guidance-only posture** (4-8 weeks calendar). Commission from retained counsel. Deliverable: signed opinion referenced in `docs/fca-position.md`. Locks Rubric-A D1 level 3.

- [ ] **A.2 — DPIA draft** (2-4 weeks calendar). Covers Fyn AI + document extraction. Deliverable: `docs/dpia-fyn-v2.md` signed off. Locks Rubric-A D2 level 2.

- [ ] **A.3 — Article 28 DPA verification** with Anthropic + xAI commercial contacts. Deliverable: `docs/dpas/anthropic.md`, `docs/dpas/xai.md`. Locks Rubric-A D7 level 2.

- [ ] **A.4 — UK IDTA + Transfer Risk Assessment** for Anthropic + xAI (both US-based). Deliverable: signed IDTA + TRA on file.

- [ ] **A.5 — Privacy Policy rewrite** — commercial decision: (i) disclose Anthropic (chat) + xAI (chat) + Meta Pixel + AWIN + Plausible honestly, OR (ii) remove Meta Pixel / AWIN / Plausible to match current policy wording. Update `resources/js/views/Public/PrivacyPolicyPage.vue` either way. Locks Rubric-A D2 level 2 + closes canonical-§7 contradiction.

---

## Track B — Code tasks

### Task 4.1 — Provider failover

**Invariant:** Rubric-A D7 level 3 sub-criterion.

**Files:**
- Modify: `app/Traits/HasAiChat.php` (retry-with-fallback on provider 5xx)
- Create: `tests/Feature/AI/ProviderFailoverTest.php`

- [ ] **Step 1 — Failing test**: simulate Anthropic 5xx; expect fallback to xAI on same turn; assert `ai_provider` cache not toggled globally.

- [ ] **Step 2 — Implement** — wrap the provider call in try/catch; on 5xx, retry once with the other provider; record the failover in `ai_audit_events` as operation `classify` + `status = 'failover'`.

- [ ] **Step 3 — Test + commit**.

### Task 4.2 — Per-provider timeout parity

**Invariant:** reliability floor.

**Files:**
- Modify: `app/Traits/HasAiChat.php` Anthropic call path (explicit 120s timeout matching xAI)

- [ ] **Step 1 — Failing test** — mocks Anthropic to delay > 120s; assert request aborts at 120s matching xAI behaviour.

- [ ] **Step 2 — Pass explicit timeout** to the Anthropic SDK call.

- [ ] **Step 3 — Commit**.

### Task 4.3 — Sentry error reporting

**Files:**
- `composer require sentry/sentry-laravel`
- Modify: `config/sentry.php`, `.env.example`, `app/Exceptions/Handler.php`

- [ ] **Step 1 — Install + configure Sentry**. Ensure PII is scrubbed before send.

- [ ] **Step 2 — Verify error capture** via a test endpoint that throws.

- [ ] **Step 3 — Commit**.

### Task 4.4 — Org-level token cap

**Invariant:** Rubric-A D7 level 2.

**Files:**
- Modify: `app/Traits/HasAiGuardrails.php` — new `enforceOrgCap` called alongside per-user cap.
- Modify: `config/services.php` — `ai.monthly_org_cap_gbp`, `ai.daily_extraction_cap_per_user`.

- [ ] **Step 1 — Failing test**: synthetic org-level usage spike triggers cap.

- [ ] **Step 2 — Implement** aggregate `ai_daily_usage` sum across all users, compare to monthly cap; return 503 `{error: 'org_capacity_exceeded'}` when exceeded.

- [ ] **Step 3 — Commit**.

### Task 4.5 — Chain-retention production cron

**Invariant:** INV-2.10.2 — retention with pseudonymisation.

- [ ] **Step 1 — Verify `AiAuditRetentionJob`** (created in Sprint 0.12) runs weekly in `app/Console/Kernel.php`.

- [ ] **Step 2 — Add monitoring** — Sentry alert if the job fails or if `ai:audit:verify-chain` returns `chain_valid: false`.

### Task 4.6 — Meta Pixel / AWIN / Plausible reconciliation

**Invariant:** canonical §7 contradiction resolution.

Two forks depending on A.5 outcome:

**Fork i — keep trackers, rewrite policy to disclose:**
- [ ] Update `resources/js/views/Public/PrivacyPolicyPage.vue` §7 to list all processors.
- [ ] Add PECR-compliant consent banner for Meta Pixel cookies.

**Fork ii — remove trackers to match current policy:**
- [ ] Delete Meta Pixel init from `resources/views/app.blade.php:80-89`.
- [ ] Delete AWIN files per `audit-evidence.md §14`.
- [ ] Delete Plausible script at `app.blade.php:71-73`.

Commit whichever fork is chosen.

---

## Dev → main release gate (pre-production)

Once Track A is complete AND Track B code is merged to `feature/fyn-persona-split`:

- [ ] **Step 1 — PR `feature/fyn-persona-split` → `dev`**. Require green CI + CSJ approval.
- [ ] **Step 2 — Deploy to `csjones.co/fynla`**. Soak 2 weeks.
- [ ] **Step 3 — PR `dev` → `main`**. Only CSJ opens this.
- [ ] **Step 4 — Build production** via `./deploy/fynla-org/build.sh`.
- [ ] **Step 5 — Upload to `~/www/fynla.org/public_html/`**. SSH + `php artisan migrate --force` + cache clears.
- [ ] **Step 6 — Smoke test `https://fynla.org`**. Monitor `storage/logs/laravel.log` for 10-15 min.

---

## Task 4.7 — Sprint 4 Playwright matrix additions

**Invariants:** provider failover (Task 4.1 in this sprint), org-cap rendering (Task 4.4), privacy policy accuracy (A.5). Sprint 4 adds **BS-25** (provider failover) and re-runs the full 38-run matrix against production after deploy.

**Files:**
- Create: `tests/Browser/scenarios/BS-25-provider-failover.php`

- [ ] **Step 1 — Author BS-25** — log in, open chat, induce an Anthropic 5xx via admin-forced circuit-break, submit a message, verify xAI takes over on the same turn, verify the response renders, verify `ai_audit_events` contains a `failover` row (Pest side).

- [ ] **Step 2 — Re-run full matrix against `https://fynla.org`** (post-production-deploy) — 38 runs + BS-25 = 39 runs. Edit `tests/Browser/TestCase.php::$rootUrl` to production URL in a scoped dataProvider.

- [ ] **Step 3 — Screenshots** → `docs/sprint-4-verification/production-matrix/BS-NN/`.

- [ ] **Step 4 — Commit**
  ```
  git commit -am "test(browser): Sprint 4 — BS-25 failover + production matrix verification"
  ```

---

## Sprint 4 verification

- [ ] **Track A** — all 5 external artefacts on file.
- [ ] **Track B** — all 6 code tasks merged.
- [ ] **Pest full suite** — all pass.
- [ ] **Browser matrix on production** — 39 runs PASS on `https://fynla.org`.
- [ ] **Rubric-A re-score** — target 28-30/40 🟡 Commercial-ready (solid).
- [ ] **Production deploy** — `https://fynla.org` serving Fyn v2 to all users.
- [ ] **48-hour production soak** — no new Sentry alerts attributable to Fyn v2; `ai:audit:verify-chain` green.

**Report-finished gate:** Sprint 4 is NOT done (and Fyn v2 is NOT live) until production Browser matrix is green (39/39) AND 48-hour soak clean AND production `ai:audit:verify-chain` green. Per [`03-test-strategy.md §Non-negotiables`](03-test-strategy.md).

Sprint 4 complete. Fyn v2 is live in production. Subsequent iterations (D1 level 4, D4 level 4, cross-conversation memory Level 4, etc.) are out of scope for this spec and get their own separate plans.

---

*End of spec. Canonical is still [`00-canonical.md`](00-canonical.md). If any of this drifts from canonical during implementation, stop and amend the spec before writing more code.*
