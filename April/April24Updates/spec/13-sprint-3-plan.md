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

# Sprint 3 — Local-First Verification + Dev Deploy

> **BRANCH: `feature/fyn-persona-split`.** Sprint 3 starts only when Sprint 2 is merged. Commits on `feature/fyn-persona-split`.
>
> **REQUIRED SUB-SKILL:** `superpowers:subagent-driven-development` or `superpowers:executing-plans`.

**Goal:** Enforce the local-first deploy gate (CSJ decision 5). Every sprint output is verified against `localhost:8000` before anything ships to `csjones.co/fynla`. Ship Sprint 0+1+2 cumulative code to dev. End state: Fyn v2 runs on `csjones.co/fynla` behind `ONBOARDING_FYN_FLOW_ENABLED=true`; Rubric-A ~24/40 🟡 Commercial-ready (just).

**Architecture:** No new code — this sprint is verification + deployment. Local test matrix + browser smoke tests + build + file upload + remote cache clear.

**Tech Stack:** Local dev (`./dev.sh`), SiteGround file manager (manual upload per CLAUDE.md), SSH remote cache clears.

**Spec reference:** INV-2.13.1 (all eval categories green), plus every invariant from §2.1-§2.12 via per-scenario browser smoke.

---

## Pre-flight

- Sprint 2 merged to `feature/fyn-persona-split`.
- Rubric-A ≥22/40.
- Rubric-B Mode 1 green; Mode 2 ≥97% last weekly run.
- Working tree clean.

---

## Task 3.1 — Seed preview personas

**Invariant:** INV-2.13.1 category 02.

**Files:**
- Modify: `database/seeders/PreviewUserSeeder.php` if any preview-user field changed under Sprint 0-2.

- [ ] **Step 1 — Reseed**
  ```
  php artisan db:seed
  ```

- [ ] **Step 2 — Verify preview data** — log in as each of the 6 personas (`young_family`, `peak_earners`, `entrepreneur`, `young_saver`, `retired_couple`, `student`) via the landing-page persona selector. Confirm each loads the dashboard without errors.

---

## Task 3.2 — Full local Browser matrix + Rubric-B Mode 1 + Mode 2

**Invariants:** every invariant with UI surface per [`03-test-strategy.md`](03-test-strategy.md).

Sprint 3 re-runs the entire Browser matrix (24 base scenarios + 14 BS-17 variants = 38 runs) against `localhost:8000` PLUS the Rubric-B eval harness (75 scenarios Mode 1). This is the local-first gate before dev deploy — nothing ships until it's all green locally.

- [ ] **Step 1 — Start local dev**
  ```
  ./dev.sh
  ```
  Verify Laravel at `http://localhost:8000` and Vite at `localhost:5173` both responding.

- [ ] **Step 2 — Seed**
  ```
  php artisan db:seed
  ```

- [ ] **Step 3 — Run full Pest suite (unit + feature + architecture)**
  ```
  ./vendor/bin/pest
  ```
  All pass.

- [ ] **Step 4 — Run Rubric-B Mode 1**
  ```
  ./vendor/bin/pest tests/Feature/Fyn/Eval/ --testsuite=Eval
  ```
  75/75 pass.

- [ ] **Step 5 — Run Browser matrix (all 38 runs)**
  ```
  ./vendor/bin/pest --testsuite=Browser --filter=BS-
  ```
  All 38 PASS. Screenshots to `docs/sprint-3-verification/BS-NN/`.

- [ ] **Step 6 — Capture transcripts** — per scenario, save the chat transcript + network-request log + DOM snapshot to `docs/sprint-3-verification/transcripts/BS-NN.md`.

- [ ] **Step 7 — Any failure → fix before proceeding.** Do NOT proceed to Task 3.4 until 100% local green.

- [ ] **Step 8 — Commit verification artefacts**
  ```
  git commit -am "docs(sprint-3): local verification — Pest + Browser matrix 38/38 green"
  ```

---

## Task 3.3 — Final Rubric-A re-score

**Invariants:** all of §2.

- [ ] **Step 1 — Walk `fyn-rubrics.md §A` dimension-by-dimension** against the running local build.
- [ ] **Step 2 — Publish delta** in `docs/sprint-3-verification/rubric-a-score.md`.
- [ ] **Step 3 — Require Rubric-A ≥22/40** (🟠 Limited beta upper or 🟡 Commercial-ready) to proceed.

---

## Task 3.4 — Build for dev target

**File(s):**
- Run: `./deploy/csjones-fynla/build.sh` (per CLAUDE.md)

- [ ] **Step 1 — Build locally**
  ```
  ./deploy/csjones-fynla/build.sh
  ```
  Expected output: `public/build/` populated with `VITE_BASE_PATH=/fynla/build/` assets.

- [ ] **Step 2 — Diff changed PHP files** vs what's currently on `csjones.co`:
  ```
  git diff --name-only $(git describe --tags --abbrev=0 2>/dev/null || echo main) -- 'app/' 'config/' 'database/migrations/' 'routes/'
  ```
  Record the list. Per CLAUDE.md memory `feedback_deploy_guide_completeness`, generate deploy guide from this diff, not from memory.

- [ ] **Step 3 — Generate deploy guide** at `/Users/CSJ/Desktop/fynla/April/April<day>Updates/sprint-3-deploy-notes.md` listing every file to upload + SSH commands to run.

---

## Task 3.5 — Upload + remote migrate + cache clear

**Remote:** `ssh.csjones.co:18765` as `u163-ptanegf9edny`.

- [ ] **Step 1 — Upload `public/build/`** via SiteGround File Manager into `~/www/csjones.co/public_html/fynla/public/build/`. Use the `cp -rn build.old/. build/` merge pattern per memory `feedback_warn_before_spa_rebuild` to preserve in-flight dynamic imports.

- [ ] **Step 2 — Upload changed PHP files** per the diff list.

- [ ] **Step 3 — Upload `.htaccess`** if routing rules changed (per Sprint 0-2).

- [ ] **Step 4 — SSH + migrate + cache clear**
  ```
  ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
  cd ~/www/csjones.co/fynla-app
  php artisan migrate --force
  php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
  ```

- [ ] **Step 5 — Smoke test `https://csjones.co/fynla`** — re-run a subset of the Browser matrix against the dev URL (not localhost). Edit `tests/Browser/TestCase.php::$rootUrl` to `https://csjones.co/fynla` in a scoped dataProvider. Target: at minimum the canonical-§0 scenarios (BS-01, 07, 09, 11, 14, 17) all green on dev.

- [ ] **Step 6 — Monitor `storage/logs/laravel.log`** for 10-15 min post-deploy.

- [ ] **Step 7 — Commit deploy notes**
  ```
  git commit -am "docs(sprint-3): dev deploy — csjones.co/fynla"
  ```

---

## Sprint 3 verification

- [ ] **Full Pest local** — all pass.
- [ ] **Rubric-B Mode 1 local** — 75/75 scenarios PASS.
- [ ] **Browser matrix local** — 38 runs all PASS; screenshots in `docs/sprint-3-verification/`.
- [ ] **`https://csjones.co/fynla`** reachable; canonical-§0 Browser subset (BS-01, 07, 09, 11, 14, 17) green on dev.
- [ ] **Rubric-A re-scored ≥22/40** locally AND on dev.
- [ ] **10-15 minute log monitoring** post-deploy — no new errors attributable to Fyn v2.

**Report-finished gate:** Sprint 3 is NOT done until local Browser matrix is green (38/38) AND dev subset (BS-01, 07, 09, 11, 14, 17) is green on `csjones.co/fynla` AND evidence committed. Per [`03-test-strategy.md §Non-negotiables`](03-test-strategy.md).

Sprint 3 complete. [`14-sprint-4-plan.md`](14-sprint-4-plan.md) covers production hardening (calendar-bound by external processes).
