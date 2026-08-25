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

# Plan — `13-sprint-3-plan.md` (Sprint 3: local-first verification + dev deploy)

> **Canonical contract:** [`../spec/00-canonical.md`](../spec/00-canonical.md).
> **Branch:** all implementation commits on `feature/fyn-persona-split`. Sprint 3 starts only after Sprint 2 merged.
> **Sources:**
> - Source spec: [`../spec/13-sprint-3-plan.md`](../spec/13-sprint-3-plan.md)
> - Audit evidence: [`../audit-evidence.md`](../audit-evidence.md)
> - Audit synthesis: [`../audit-synthesis.md`](../audit-synthesis.md)
> - Rubrics: [`../fyn-rubrics.md`](../fyn-rubrics.md)

**Goal (per source spec):** Enforce the local-first deploy gate (CSJ decision 5 in `../audit-synthesis.md §8`). Every sprint output is verified against `localhost:8000` before anything ships to `csjones.co/fynla`. Ship Sprint 0 + 1 + 2 cumulative code to dev. End state Rubric-A ~24/40 🟡 Commercial-ready (just).

**Pre-flight gate:** Sprint 2 merged; Rubric-A ≥22/40; Rubric-B Mode 1 green; Mode 2 ≥97% last weekly; working tree clean.

**No new code** in Sprint 3. Every slice is verification or deployment.

---

### S3.1 — Seed preview personas on local

- **Objective:** Run `php artisan db:seed` (per memory `#1 CRITICAL RULE — NEVER SKIP`) and manually verify each of the 6 preview personas loads the dashboard error-free.
- **Spec reference:** Source spec Task 3.1 + `spec/01-invariants.md` INV-2.13.1 category 02 (preview-personas).
- **Files affected:**
  - If any preview-user field changed under Sprint 0-2: MODIFY `database/seeders/PreviewUserSeeder.php` to keep the 6 personas (`young_family`, `peak_earners`, `entrepreneur`, `young_saver`, `retired_couple`, `student`) in sync.
- **Acceptance test:** Each of the 6 preview personas — selected via landing-page persona selector — loads the dashboard without errors; landing-page click-through per `spec/03-test-strategy.md` click-through discipline.
- **Out of scope:** Creating a 7th persona. Changing existing preview data shapes beyond what Sprint 0-2 code requires.

---

### S3.2 — Full local Browser matrix + Rubric-B Mode 1 + Mode 2

- **Objective:** Run the full local verification gate — Pest full suite + Rubric-B Mode 1 (75 scenarios) + Browser matrix (38 runs = 24 base + 14 BS-17 variants) + transcripts — against `localhost:8000` before any deploy.
- **Spec reference:** Source spec Task 3.2 + `spec/03-test-strategy.md` + `spec/01-invariants.md §verification` "Post Sprint 3".
- **Files affected:**
  - Running processes:
    - `./dev.sh` (Laravel + Vite) in a separate terminal.
    - `php artisan db:seed`.
    - `./vendor/bin/pest` (full suite).
    - `./vendor/bin/pest tests/Feature/Fyn/Eval/ --testsuite=Eval` (Mode 1, 75 scenarios).
    - `./vendor/bin/pest --testsuite=Browser --filter=BS-` (38 runs).
  - OUTPUTS: `docs/sprint-3-verification/BS-NN/` (screenshots) + `docs/sprint-3-verification/transcripts/BS-NN.md` (chat transcript + network-request log + DOM snapshot per scenario).
  - COMMIT: `docs(sprint-3): local verification — Pest + Browser matrix 38/38 green`.
- **Acceptance test:** Pest 100%; Mode 1 75/75 PASS; Browser 38/38 PASS. Any failure blocks proceeding to Task S3.4 until fixed. Per `spec/03-test-strategy.md §Non-negotiables` — nothing reports "done" without this rollup.
- **Out of scope:** Mode 2 real-provider run (weekly cron continues separately). Testing on dev URL (S3.5). Skipping any scenario because it's "flaky" — the strategy forbids this.

---

### S3.3 — Final Rubric-A re-score (local)

- **Objective:** Walk each of D1-D10 per `fyn-rubrics.md §A` against the running local build; publish delta to `docs/sprint-3-verification/rubric-a-score.md`; require ≥22/40 to proceed to dev deploy.
- **Spec reference:** Source spec Task 3.3 + `fyn-rubrics.md §A`.
- **Files affected:** `docs/sprint-3-verification/rubric-a-score.md` — new.
- **Acceptance test:** Rubric-A ≥22/40 (🟠 Limited-beta upper or 🟡 Commercial-ready entry). No dimension regresses from Sprint 2 baseline.
- **Out of scope:** Changing the rubric weights. Publishing a score without evidence (walk each dimension; cite tests/scenarios).

---

### S3.4 — Build for dev target

- **Objective:** Produce a dev build with the correct `VITE_BASE_PATH=/fynla/build/` + `VITE_ROUTER_BASE=/fynla/` + `VITE_API_BASE_URL=https://csjones.co/fynla` + `VITE_REVOLUT_SANDBOX=true`; generate a deploy guide from `git diff`, not memory.
- **Spec reference:** Source spec Task 3.4 + CLAUDE.md "Deploying to dev" + memory `feedback_deploy_guide_completeness.md`, `feedback_never_raw_vite_build.md`.
- **Files affected:**
  - RUN: `./deploy/csjones-fynla/build.sh` (MANDATORY — never `npx vite build` per memory).
  - EXPECTED OUTPUT: `public/build/` populated with `/fynla/build/` asset paths.
  - RUN: `git diff --name-only <last-dev-deploy-tag> -- 'app/' 'config/' 'database/migrations/' 'routes/'` → record as deploy-file list.
  - CREATE: `April/April<DD>Updates/sprint-3-deploy-notes.md` listing every file to upload + SSH commands (per memory `feedback_deploy_guides_both_locations.md` also mirror to vault).
- **Acceptance test:** `ls public/build/` contains assets with paths starting `/fynla/build/`; deploy-notes doc generated from actual diff, not from memory.
- **Out of scope:** Building for production (`./deploy/fynla-org/build.sh` is Sprint 4). Mixing targets — per CLAUDE.md, wrong combo = blank page / 404 loop.

---

### S3.5 — Upload + remote migrate + cache clear + dev smoke

- **Objective:** Upload `public/build/` + changed PHP files + `.htaccess` (if routing changed) to `~/www/csjones.co/public_html/fynla/public/` via SiteGround File Manager; SSH + `php artisan migrate --force` + cache clears; smoke-test canonical-§0 Browser subset (BS-01, 07, 09, 11, 14, 17) on `https://csjones.co/fynla`; monitor `storage/logs/laravel.log` for 10-15 min.
- **Spec reference:** Source spec Task 3.5 + CLAUDE.md "Deploying to dev" + memory `reference_csjones_sibling_dir.md`, `reference_csjones_ssh_access.md`, `feedback_warn_before_spa_rebuild.md`.
- **Files affected:**
  - UPLOAD target: `~/www/csjones.co/public_html/fynla/public/build/` (use `cp -rn build.old/. build/` merge pattern per memory `feedback_warn_before_spa_rebuild.md` to preserve in-flight dynamic imports).
  - UPLOAD: PHP files per S3.4 diff list; changed `.htaccess` if routing rules changed.
  - SSH: `ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co`; `cd ~/www/csjones.co/fynla-app` (sibling-dir pattern per memory `reference_csjones_sibling_dir.md`, NOT `public_html/fynla`).
  - SSH commands: `php artisan migrate --force && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize`.
  - BROWSER SCENARIOS (scoped): BS-01, BS-07, BS-09, BS-11, BS-14, BS-17 — edit `tests/Browser/TestCase.php::$rootUrl` to `https://csjones.co/fynla` in a scoped dataProvider or environment flag; run subset; screenshot evidence to `docs/sprint-3-verification/dev/BS-NN/`.
  - COMMIT: `docs(sprint-3): dev deploy — csjones.co/fynla`.
- **Acceptance test:**
  - `https://csjones.co/fynla` reachable; login works via production-flow (ask CSJ for MFA code per CLAUDE.md "Authentication for Testing").
  - Canonical-§0 subset (BS-01, 07, 09, 11, 14, 17) → 6/6 PASS on dev URL.
  - `storage/logs/laravel.log` shows no new errors attributable to Fyn v2 over 10-15 min soak.
- **Out of scope:** Production deploy (Sprint 4). Running the full 38-scenario matrix against dev URL (canonical subset suffices per spec). Modifying `.env` on the remote server (per memory `feedback_never_touch_env_or_db.md`).

---

### S3.6 — Sprint 3 verification rollup + gate

- **Objective:** Publish Sprint 3 verification showing local 38/38 + dev subset 6/6 + Rubric-A ≥22/40 (locally AND on dev) + 10-15 minute log-monitoring clean; this is the gate for Sprint 4.
- **Spec reference:** Source spec §Sprint-3-verification + `spec/03-test-strategy.md §Non-negotiables` (report-finished gate).
- **Files affected:**
  - `docs/sprint-3-verification/rubric-a-score.md` (local + dev).
  - `docs/sprint-3-verification/rollup.md` — summary of Pest + Mode 1 + Browser matrix + log check.
  - PR body on merge of `feature/fyn-persona-split` → itself (if a consolidation PR is used) linking to the verification.
- **Acceptance test:** All four gates green. Per memory `critical_browser_testing_law.md` + `feedback_never_claim_verified.md` — no "verified" claim until evidence is committed.
- **Out of scope:** Merging to `main` (Sprint 4 final gate per CLAUDE.md branch workflow). Closing out external tracks (Sprint 4 Track A).

---

*End of plan for Sprint 3. Sprint 4 follows — production hardening (external-calendar-bound) + production deploy to `fynla.org`.*

**Post-sprint priorities:** see `15-post-sprint-priorities-plan.md` for the lifestyle + campaign landing-pages workstream, queued after Sprints 0-4 hit GREEN.
