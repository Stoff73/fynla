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

# Plan — `README.md` (spec index)

> **Canonical contract:** [`../spec/00-canonical.md`](../spec/00-canonical.md).
> **Branch:** all implementation commits on `feature/fyn-persona-split`. The branch is 68 commits ahead of / 179 behind `origin/main` at spec time.
> **Sources:**
> - Source spec: [`../spec/README.md`](../spec/README.md)
> - Audit evidence: [`../audit-evidence.md`](../audit-evidence.md)
> - Audit synthesis: [`../audit-synthesis.md`](../audit-synthesis.md)
> - Rubrics: [`../fyn-rubrics.md`](../fyn-rubrics.md)

`spec/README.md` is the workstream index, not a deliverable in its own right. Its role is to keep contributors oriented — what files exist, what decisions are closed, how to run the workflow, what "done" means for each sprint. This plan captures the obligations that keep the index true as the spec evolves.

---

### RDM-01 — Keep the file catalogue accurate

- **Objective:** When any file is added, removed, or renamed under `April/April24Updates/spec/` or `April/April24Updates/plan/`, update `spec/README.md` tables so the index stays synchronised.
- **Spec reference:** `spec/README.md` §How-this-directory-is-organised (lines 9-33) and §Source-documents (lines 36-46).
- **Files affected:**
  - `April/April24Updates/spec/README.md` — tables for "Read-only reference" and "Sprint plans".
  - `April/April24Updates/plan/README-plan.md` (this file) if the list grows beyond the 10 plan files currently enumerated in `task_plan.md`.
  - `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/spec/` mirror per memory `reference_month_updates_folders.md` when approved for vault sync.
- **Acceptance test:** `ls April/April24Updates/spec/*.md | diff - <(awk '/\| \[`/ {print $2}' April/April24Updates/spec/README.md | tr -d '[]`')` returns no differences. A dry-run lint step at PR time.
- **Out of scope:** Mirroring to the vault before CSJ approval (per memory `feedback_deploy_guides_both_locations.md` — deploy guides go in both, spec snapshot waits for approval). Changing file names to match the table (it's the table that follows the filenames, not vice versa).

---

### RDM-02 — Preserve the decision register

- **Objective:** The 16 decisions in `spec/README.md §Decision-register` (lines 57-75) are closed at spec time. Any reopen must amend the spec *first* — the README table is updated synchronously with the invariants, sprint plans, and canonical file.
- **Spec reference:** `spec/README.md §Decision-register`; all decisions cross-referenced in `../audit-synthesis.md §8` and the canonical file.
- **Files affected:**
  - `April/April24Updates/spec/README.md` — decision register table.
  - `April/April24Updates/audit-synthesis.md §8` — CSJ decisions listed inline.
  - `April/April24Updates/spec/00-canonical.md` — if a decision change modifies the contract.
  - Any sprint plan containing a task whose behaviour depended on the prior decision.
- **Acceptance test:**
  - Walk the table; each row's "Source" column has a current citation. A manual audit in PR review.
  - If a decision is reopened: open issue + amend spec + update the README table + cross-reference the new source date — all in the same PR.
- **Out of scope:** Retroactively rewriting history (decisions made at spec time are stamped "CSJ 24 April"; subsequent overrides carry their own date).

---

### RDM-03 — Honour the non-negotiables-before-PR list

- **Objective:** Before any PR on this workstream lands, enforce the five non-negotiables in `spec/README.md §Non-negotiables-before-any-PR-lands` (lines 89-95): canonical §0 at doc top; file:line citations on every implementation claim; no Rubric-A/B regressions without explicit risk acceptance; no canonical §0 violation; commits on `feature/fyn-persona-split` (or feature branches off it).
- **Spec reference:** `spec/README.md` lines 89-95; `../fyn-rubrics.md §A` + `§B`; CLAUDE.md branch-workflow rule `feature → dev → main`.
- **Files affected:**
  - `.github/CODEOWNERS` — `@Stoff73` required reviewer (already on branch per CLAUDE.md "Branch workflow").
  - PR template (if present) or PR description — checklist mirroring these five items.
  - Sprint PRs — the verification section of each sprint plan (e.g., `spec/10-sprint-0-plan.md §Sprint-0-verification`).
- **Acceptance test:** Every PR in the workstream has: (a) source file contains canonical §0 or a link to it; (b) every file:line citation grep-verifies on the branch; (c) Rubric A + B re-scored in the PR body or linked verification doc; (d) Browser-matrix evidence committed in `docs/sprint-<n>-verification/`; (e) source branch matches the `feature/fyn-persona-split` or `feature/csj/*` pattern.
- **Out of scope:** Adding CI enforcement scripts (PR-time manual checklist is sufficient for Fyn v2 per scope discipline). Merging directly to `main` or `dev` (blocked by GitHub protection per memory `feedback_main_via_dev_only.md`).

---

### RDM-04 — Per-sprint Browser-matrix gate

- **Objective:** Enforce the per-sprint Browser matrix requirements in `spec/README.md §Verification-summary` (lines 100-126) and `spec/03-test-strategy.md §Per-sprint-scenario-index` — Sprint 0: 20 scenarios; Sprint 1: 24; Sprint 2: 38; Sprint 3: 44; Sprint 4: 39. Nothing merges until the matrix is green with screenshot evidence.
- **Spec reference:** `spec/README.md` lines 100-126 (Sprint-by-sprint Browser-matrix tables); `spec/03-test-strategy.md` lines 600-607 (per-sprint scenario index) + `§Non-negotiables-when-reporting-"testing complete"` (lines 633-643).
- **Files affected:**
  - `tests/Browser/scenarios/BS-NN-*.php` — 25 scenario files plus 14 BS-17 variants.
  - `docs/sprint-<n>-verification/BS-NN/` — screenshot directories populated by each scenario.
  - Per-sprint PR bodies — link to the verification directories.
- **Acceptance test:**
  - `./vendor/bin/pest --testsuite=Browser --filter=BS-` run after `./dev.sh` + `php artisan db:seed` → the sprint's required scenario count all PASS.
  - `ls docs/sprint-<n>-verification/BS-*/ | wc -l` ≥ number of scenarios required for that sprint.
  - Per CLAUDE.md CRITICAL browser-testing rules + memory `critical_browser_testing_law.md`: "Browser tested" means CLICKED, FILLED, SUBMITTED in Playwright — not diff review, not snapshot-without-interaction.
- **Out of scope:** Declaring "done" on Pest alone (explicitly forbidden per `spec/03-test-strategy.md §Non-negotiables`). Skipping login through the UI (must click Sign in → fill creds → MFA-from-DB on local or ask-user on production).

---

### RDM-05 — Execution-workflow adherence

- **Objective:** Every implementation session follows the 6-step execution workflow in `spec/README.md §Execution-workflow` (lines 78-86) — read canonical, read invariants, read current-system, read target sprint plan, execute via subagent-driven-development or executing-plans, verify per invariants + rubrics.
- **Spec reference:** `spec/README.md` lines 78-86; Claude superpowers skills `superpowers:subagent-driven-development`, `superpowers:executing-plans`, `superpowers:verification-before-completion`.
- **Files affected:**
  - Plans in this folder (inputs to the subagent flow).
  - `docs/sprint-<n>-verification/*` (outputs).
  - Session transcripts / handovers in `April/April<DD>Updates/` (recommendation).
- **Acceptance test:**
  - Each sprint's starting commit hash is recorded in the sprint's verification doc so the `feature/fyn-persona-split` trajectory is auditable.
  - Every completed task has a corresponding commit with message matching the sprint plan's commit format (e.g., `feat(fyn): ...`, `test(browser): ...`).
  - When spawning an agent to execute a task: the prompt includes the relevant vault-context per CLAUDE.md "Sub-Agent Vault Context (MANDATORY)".
- **Out of scope:** Deviating from subagent-driven-development without explicit user approval (CSJ has told the model to prefer inline execution for small tasks per memory `feedback_subagent_accountability.md`, but sprint-level work uses the skill).

---

### RDM-06 — Vault mirror synchronisation

- **Objective:** Once a spec file is approved and stable, mirror it to `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/spec/` so the Obsidian vault carries the same source of truth.
- **Spec reference:** `spec/README.md` last line ("*Mirror this folder to `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/spec/` on approval.*"); memory `reference_month_updates_folders.md` (month-updates folders live in both repo AND vault); `reference_fynlabrain_vault.md` (vault format: YAML frontmatter + wikilinks).
- **Files affected:**
  - `/Users/CSJ/Desktop/fynla/April/April24Updates/spec/*.md` — source.
  - `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/spec/*.md` — target.
  - Vault MOC: `/Users/CSJ/Desktop/fynlaBrain/April/April.md` + `Home.md` — link through to the new spec directory.
- **Acceptance test:** `diff -r April/April24Updates/spec /Users/CSJ/Desktop/fynlaBrain/April/April24Updates/spec` is empty after approval. `vault-sync` skill run completes without flagging missing wikilinks.
- **Out of scope:** Mirroring in-progress / draft files (only approved). Rewriting spec files into the vault's YAML-frontmatter format — plain Markdown passthrough is sufficient per `reference_fynlabrain_vault.md`.

---

### RDM-07 — Rubric-A trajectory publication

- **Objective:** The expected Rubric-A trajectory in `spec/README.md` lines 100-126 (and `spec/01-invariants.md §verification`) is the gate for each sprint merge — publish a delta at the end of each sprint; any regression blocks merge.
- **Spec reference:** `spec/README.md §Verification-summary`; `spec/01-invariants.md §verification`; `fyn-rubrics.md §A`.
- **Files affected:**
  - `docs/sprint-<n>-verification/rubric-a-score.md` — one per sprint.
  - PR descriptions — link + summary.
- **Acceptance test:**
  - Sprint 0: 13-15/40 published.
  - Sprint 1: 17-18/40.
  - Sprint 2: ~22/40.
  - Sprint 3: ~24/40 AND local + dev subset green.
  - Sprint 4: 28-30/40 AND production matrix green.
  - Any dimension regresses → PR blocked until documented risk acceptance in PR description.
- **Out of scope:** Inventing a new rubric. Publishing a score without evidence (e.g., checklist of tests, link to screenshot directories, or reviewer sign-off).

---

*End of plan for `README.md`. The README is the orientation surface — when it's stale, contributors get lost.*
