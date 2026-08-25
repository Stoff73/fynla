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

# Plan — `14-sprint-4-plan.md` (Sprint 4: production hardening + production deploy)

> **Canonical contract:** [`../spec/00-canonical.md`](../spec/00-canonical.md).
> **Branch:** production path is `feature/fyn-persona-split` → `dev` → `main` per CLAUDE.md branch workflow. Sprint 4 ships to `https://fynla.org` only through the dev gate.
> **Sources:**
> - Source spec: [`../spec/14-sprint-4-plan.md`](../spec/14-sprint-4-plan.md)
> - Audit evidence: [`../audit-evidence.md`](../audit-evidence.md)
> - Audit synthesis: [`../audit-synthesis.md`](../audit-synthesis.md)
> - Rubrics: [`../fyn-rubrics.md`](../fyn-rubrics.md)

**Goal (per source spec):** Pass the external-legal / compliance / ops hardening gate so Fyn v2 can ship to `fynla.org`. End state Rubric-A ~28-30/40 🟡 Commercial-ready (solid). Privacy policy factually consistent; provider failover + Sentry in place; DPIA on file.

**Pre-flight gate:** Sprint 3 deployed to `csjones.co/fynla`; ≥2 weeks log-monitoring soak; no canonical-violation incidents in dev logs.

Sprint 4 has two tracks:
- **Track A (External, calendar-bound).** CSJ-owned; 4-8 weeks calendar. Not TDD cycles.
- **Track B (Code).** 6 code tasks, subagent-driven-development pattern.

---

## Track A — External / calendar-bound

### S4.A1 — External legal opinion on guidance-only posture

- **Objective:** Commission retained counsel for a signed opinion on the guidance-only framing (CSJ decision 1); deliverable referenced in `docs/fca-position.md`; locks Rubric-A D1 level 3.
- **Spec reference:** Source spec Track A.1 + `audit-synthesis.md §8` CSJ decision 1 + INV-2.10.1 + `fyn-rubrics.md §A` D1.
- **Files affected:**
  - NEW: `docs/fca-position.md` containing the signed opinion's summary + PDF link.
  - `resources/js/views/Public/PrivacyPolicyPage.vue` — may require edits in light of counsel's feedback (coordinated with A.5).
- **Acceptance test:** Signed opinion PDF on file; `docs/fca-position.md` references it. D1 re-scores to level 3 on `rubric-a-score.md`.
- **Out of scope:** Pursuing FCA targeted-support authorisation (explicit CSJ decision: not pursued). Changing `CoreIdentity.php` language (already guidance-only from Sprint 0).

### S4.A2 — DPIA draft

- **Objective:** Draft + sign off `docs/dpia-fyn-v2.md` covering Fyn AI + document extraction; 2-4 weeks calendar; locks Rubric-A D2 level 2.
- **Spec reference:** Source spec Track A.2 + `fyn-rubrics.md §A` D2.
- **Files affected:** NEW `docs/dpia-fyn-v2.md`.
- **Acceptance test:** DPIA signed off by external DPO; referenced in privacy documentation.
- **Out of scope:** Vendor sub-processor audits beyond Anthropic + xAI (those go in A.3).

### S4.A3 — Article 28 DPA verification with Anthropic + xAI

- **Objective:** Verify Article 28 DPAs with Anthropic + xAI commercial contacts; file signed copies at `docs/dpas/anthropic.md`, `docs/dpas/xai.md`; locks Rubric-A D7 level 2.
- **Spec reference:** Source spec Track A.3 + `fyn-rubrics.md §A` D7.
- **Files affected:** NEW `docs/dpas/anthropic.md`, `docs/dpas/xai.md`.
- **Acceptance test:** Both DPAs signed + referenced in privacy documentation.
- **Out of scope:** Negotiating new commercial terms (use existing DPA templates from each provider).

### S4.A4 — UK IDTA + Transfer Risk Assessment

- **Objective:** Sign UK International Data Transfer Agreement + Transfer Risk Assessment for both Anthropic + xAI (US-based processors); file on disk.
- **Spec reference:** Source spec Track A.4.
- **Files affected:** NEW `docs/transfers/anthropic-idta.pdf`, `docs/transfers/xai-idta.pdf`, `docs/transfers/tra-2026.md`.
- **Acceptance test:** Both IDTAs signed; TRA documents current transfer posture + risk mitigations.
- **Out of scope:** EU-based model alternatives (no requirement in spec).

### S4.A5 — Privacy Policy rewrite

- **Objective:** Resolve the §5/§7 contradictions in `PrivacyPolicyPage.vue` per `spec/02-current-system.md §9`. Two forks: (i) keep Meta Pixel / AWIN / Plausible + Anthropic / xAI and honestly disclose; (ii) remove trackers to match current wording. Locks Rubric-A D2 level 2 + closes canonical §7.
- **Spec reference:** Source spec Track A.5 + `spec/02-current-system.md §9` + Rubric-A D2.
- **Files affected:**
  - MODIFY `resources/js/views/Public/PrivacyPolicyPage.vue` — §5 line 111 (health data), §7 line 124 (Anthropic scope), §7 line 132 (third-party analytics).
  - Fork (i): also keeps `resources/views/app.blade.php:80-89` (Meta Pixel), `resources/js/utils/awinTracking.js`, `app.blade.php:71-73` (Plausible). Add PECR-compliant consent banner per audit `§14`.
  - Fork (ii): DELETE Meta Pixel init at `app.blade.php:80-89`, delete AWIN files per `audit-evidence.md §14`, delete Plausible script at `app.blade.php:71-73`.
- **Acceptance test:** Privacy Policy is factually consistent with running code. A spot audit comparing policy sections to actual third-party requests shows no contradictions.
- **Out of scope:** Pursuing analytics alternatives (Plausible-only, etc.) beyond what the commercial decision allows.

---

## Track B — Code tasks

### S4.B1 — Provider failover (Task 4.1)

- **Objective:** Wrap provider calls in try/catch; on 5xx from primary, retry once with the other provider on the same turn; record failover as `ai_audit_events` row with `operation='classify', status='failover'`; do NOT toggle the global `ai_provider` cache.
- **Spec reference:** Source spec Task 4.1 + Rubric-A D7 level 3 sub-criterion.
- **Files affected:**
  - MODIFY `app/Traits/HasAiChat.php` — new retry wrapper around the provider call at lines 287-305 (Anthropic) + corresponding xAI path.
  - MODIFY `app/Services/AI/AuditChainService.php::append` — accepts `operation='classify'` with extra metadata (`from_provider`, `to_provider`).
  - CREATE `tests/Feature/AI/ProviderFailoverTest.php` — mock Anthropic 5xx; assert xAI takes over; assert `ai_provider` cache not toggled; assert `ai_audit_events` has `failover` row.
- **Acceptance test:** Pest failover test green; no global provider change per audit row inspection.
- **Out of scope:** Multi-failover chains (single retry is the spec). Circuit-breaker persistence across requests.

### S4.B2 — Per-provider timeout parity (Task 4.2)

- **Objective:** Set explicit 120s timeout on the Anthropic SDK call at `app/Traits/HasAiChat.php:287-305` to match `XaiClient.php:64`.
- **Spec reference:** Source spec Task 4.2 + reliability floor.
- **Files affected:**
  - MODIFY `app/Traits/HasAiChat.php:287-305` — pass explicit timeout to Anthropic SDK (via HTTP client options).
  - CREATE `tests/Feature/AI/TimeoutParityTest.php` — mock delay > 120s; assert request aborts at 120s on both providers.
- **Acceptance test:** Both providers abort at matching 120s; test green.
- **Out of scope:** Tuning timeout lower than 120s. Per-endpoint differentiation.

### S4.B3 — Sentry error reporting (Task 4.3)

- **Objective:** Install Sentry Laravel SDK; wire into `app/Exceptions/Handler.php`; scrub PII before send.
- **Spec reference:** Source spec Task 4.3.
- **Files affected:**
  - RUN: `composer require sentry/sentry-laravel`.
  - CREATE `config/sentry.php`.
  - MODIFY `.env.example` — add `SENTRY_LARAVEL_DSN`.
  - MODIFY `app/Exceptions/Handler.php` — wire Sentry reporter; PII scrubbing callback.
  - Smoke endpoint or test: throwing endpoint reports to Sentry.
- **Acceptance test:** A known error reaches Sentry dashboard; PII (email addresses, DOB, account balances) stripped from payloads.
- **Out of scope:** Frontend Sentry integration (separate decision).

### S4.B4 — Org-level token cap (Task 4.4)

- **Objective:** New `HasAiGuardrails::enforceOrgCap` called alongside per-user cap; sums `ai_daily_usage` across users; returns 503 `{error: 'org_capacity_exceeded'}` when monthly cap hit.
- **Spec reference:** Source spec Task 4.4 + Rubric-A D7 level 2.
- **Files affected:**
  - MODIFY `app/Traits/HasAiGuardrails.php` — add `enforceOrgCap`.
  - MODIFY `config/services.php` — add `ai.monthly_org_cap_gbp`, `ai.daily_extraction_cap_per_user`.
  - CREATE `tests/Feature/AI/OrgCapTest.php` — synthetic org-level usage spike triggers cap.
- **Acceptance test:** When seeded usage spike > cap → 503 returned; within-cap → 200.
- **Out of scope:** Multi-tenant billing (single-org assumption). Graceful degradation below cap.

### S4.B5 — Chain-retention production cron (Task 4.5)

- **Objective:** Ensure `AiAuditRetentionJob` (created Sprint 0 Task 0.12) runs weekly on production; Sentry alert on job failure or `ai:audit:verify-chain` returning `chain_valid: false`.
- **Spec reference:** Source spec Task 4.5 + INV-2.10.2.
- **Files affected:**
  - MODIFY `app/Console/Kernel.php` — confirm weekly schedule for `AiAuditRetentionJob`.
  - MODIFY `app/Console/Kernel.php` — schedule weekly `ai:audit:verify-chain` with Sentry alert on non-green output.
- **Acceptance test:** Weekly scheduled runs visible in `php artisan schedule:list`; synthetic chain-break triggers Sentry alert.
- **Out of scope:** Chain backup/restore procedures (separate operational doc).

### S4.B6 — Meta Pixel / AWIN / Plausible reconciliation (Task 4.6)

- **Objective:** Execute Fork (i) keep + disclose OR Fork (ii) remove, per the A.5 commercial decision. Commit whichever fork is chosen.
- **Spec reference:** Source spec Task 4.6 (two forks) + `spec/02-current-system.md §9` (privacy contradictions).
- **Files affected:**
  - Fork (i): MODIFY `PrivacyPolicyPage.vue §7` to list all processors; add PECR cookie banner (reuse existing consent modal infra); keep `resources/views/app.blade.php:71-73, 80-89` + `resources/js/utils/awinTracking.js`.
  - Fork (ii): DELETE `app.blade.php:80-89` (Meta Pixel), delete `resources/js/utils/awinTracking.js` + 4 other AWIN files per `audit-evidence.md §14`, delete `app.blade.php:71-73` (Plausible).
- **Acceptance test:** Network-requests snapshot on a post-login page: Fork (i) shows expected disclosed requests + PECR consent modal; Fork (ii) shows no third-party tracker requests.
- **Out of scope:** Introducing new trackers not already on the branch.

---

## Dev → main release gate (pre-production)

### S4.R1 — Release PR pipeline

- **Objective:** Once Track A signed + Track B merged to `feature/fyn-persona-split`, ship through the full release gate: PR `feature/fyn-persona-split` → `dev` (green CI + CSJ approval); deploy to `csjones.co/fynla`; 2-week soak; PR `dev` → `main`; build production; upload; SSH migrate; smoke; monitor.
- **Spec reference:** Source spec §Dev→main-release-gate + CLAUDE.md branch-workflow.
- **Files affected:**
  - `./deploy/fynla-org/build.sh` — production build script (per CLAUDE.md, NEVER raw `npx vite build`).
  - Upload target: `~/www/fynla.org/public_html/` (production).
  - Remote SSH: `ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org`; `cd ~/www/fynla.org/public_html` (production uses STANDARD layout per memory `reference_csjones_sibling_dir.md`, NOT sibling-dir).
  - Commands: `php artisan migrate --force && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize`.
- **Acceptance test:** `https://fynla.org` serving Fyn v2; smoke flows succeed; `storage/logs/laravel.log` no new errors for 10-15 min.
- **Out of scope:** Force-pushing to `main` (per memory `feedback_main_via_dev_only.md`, absolutely not). Skipping the 2-week dev soak.

---

### S4.B7 — Sprint 4 Playwright matrix additions (Task 4.7)

- **Objective:** Add BS-25 (provider failover) as a new Playwright scenario; re-run the full 38-run matrix against `https://fynla.org` post-deploy = 39 runs.
- **Spec reference:** Source spec Task 4.7 + `spec/03-test-strategy.md §BS-25`.
- **Files affected:**
  - CREATE `tests/Browser/scenarios/BS-25-provider-failover.php` — log in, open chat, induce Anthropic 5xx via admin-forced circuit-break, submit message, verify xAI takes over same turn, verify response renders, verify `ai_audit_events` failover row.
  - RUN production matrix: scope `TestCase::$rootUrl` to `https://fynla.org` in a dataProvider; execute 39 runs.
  - Screenshots in `docs/sprint-4-verification/production-matrix/BS-NN/`.
  - COMMIT: `test(browser): Sprint 4 — BS-25 failover + production matrix verification`.
- **Acceptance test:** 39/39 PASS on production.
- **Out of scope:** Chaos-engineering multi-failover sequences.

---

### S4.V1 — Sprint 4 verification rollup + production soak

- **Objective:** Publish Sprint 4 verification: Track A complete (5 artefacts), Track B merged (6 code tasks), Pest full green, production matrix 39/39, Rubric-A 28-30/40 🟡 Commercial-ready solid, production deploy live, 48-hour soak clean (no Sentry alerts attributable to Fyn v2; `ai:audit:verify-chain` green on production).
- **Spec reference:** Source spec §Sprint-4-verification + `spec/01-invariants.md §verification` "Post Sprint 4" + `spec/03-test-strategy.md §Non-negotiables`.
- **Files affected:**
  - `docs/sprint-4-verification/rubric-a-score.md` (final).
  - `docs/sprint-4-verification/production-soak-log.md` — 48-hour monitoring summary.
  - PR body linking to all evidence.
- **Acceptance test:**
  - Track A: 5 signed artefacts (legal opinion, DPIA, 2 DPAs, IDTA/TRA).
  - Track B: 6 merged code tasks.
  - Production matrix 39/39 green.
  - 48-hour soak: no new Sentry alerts attributable to Fyn v2; production `ai:audit:verify-chain` green.
  - Rubric-A ≥28/40.
- **Out of scope:** Iterations beyond D1 level 4, D4 level 4, cross-conversation memory Level 4 (out of spec — separate plans).

---

*End of plan for Sprint 4. Fyn v2 live on `https://fynla.org`.*

**Next priority:** the lifestyle + campaign landing-pages workstream is queued in `15-post-sprint-priorities-plan.md`. It starts only once all five sprint verification rollups (S0.17 / S1.V1 / S2.V1 / S3.V1 / S4.V1) are GREEN. Subsequent iterations beyond that get their own separate plans.
