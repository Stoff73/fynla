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

# Progress Log

## Session 2026-04-24

### Opening state
- User invoked `/planning-with-files` with argument: write plan-slice plans for every spec file using identical template (Objective / Spec reference / Files affected / Acceptance test / Out of scope).
- `April/April24Updates/plan/` was empty.
- `April/April24Updates/spec/` contained 10 files.

### Actions
1. Listed spec/ and plan/ directories.
2. Read all 10 spec files end-to-end.
3. Read `spec/README.md` to confirm workstream org; `spec/00-canonical.md` for the contract.
4. Created `task_plan.md`, `findings.md`, `progress.md` for my own tracking.
5. Captured code anchors (file:line from `spec/02-current-system.md`) in `findings.md`.
6. Wrote 10 plan files in `plan/`:
   - `00-canonical-plan.md` — 7 slices enforcing the canonical contract.
   - `01-invariants-plan.md` — 35 slices, one per INV, covering all 13 invariant groups + verification.
   - `02-current-system-plan.md` — 13 slices turning the branch-state description into obligations.
   - `03-test-strategy-plan.md` — 30 slices covering harness + 24 Playwright scenarios + BS-25 + Rubric-B + discipline gates.
   - `10-sprint-0-plan.md` — 17 slices, one per task 0.1–0.16 + verification.
   - `11-sprint-1-plan.md` — 10 slices covering Tasks 1.1–1.9 + verification.
   - `12-sprint-2-plan.md` — 20 slices for Tasks 2.1–2.19 + verification.
   - `13-sprint-3-plan.md` — 6 slices for Tasks 3.1–3.5 + verification gate.
   - `14-sprint-4-plan.md` — 14 slices covering Track A (5 external) + Track B (7 code) + release gate + verification.
   - `README-plan.md` — 7 slices enforcing spec-index accuracy + non-negotiables.

### Confirmed per plan-slice template
Every plan uses the identical template the user specified:
- **Objective** (one sentence)
- **Spec reference** (property / INV / task)
- **Files affected** (file:line citations from current branch)
- **Acceptance test** (command / test / observation)
- **Out of scope** (what not to touch)

### Confirmed references
Every plan's preamble cites:
- Canonical contract `spec/00-canonical.md`
- Branch `feature/fyn-persona-split`
- Source spec file
- `audit-evidence.md`
- `audit-synthesis.md`
- `fyn-rubrics.md`

### Next
Deliverables complete. Awaiting user review.
