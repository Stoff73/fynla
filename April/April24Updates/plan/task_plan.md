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

# Task Plan: Generate Sprint Plans for Fyn v2 Spec

## Goal
Produce one plan file per spec file in `/Users/CSJ/Desktop/fynla/April/April24Updates/spec/`, saved to `/Users/CSJ/Desktop/fynla/April/April24Updates/plan/`.

Each plan uses the canonical plan-slice template:
- **Objective** (one sentence)
- **Spec reference** (which property this satisfies)
- **Files affected** (cited from current code on `feature/fyn-persona-split`)
- **Acceptance test** (how we'll know it worked)
- **Out of scope** (what not to touch)

Plans MUST reference:
- The source spec file
- `/Users/CSJ/Desktop/fynla/April/April24Updates/audit-evidence.md`
- `/Users/CSJ/Desktop/fynla/April/April24Updates/audit-synthesis.md`
- `/Users/CSJ/Desktop/fynla/April/April24Updates/fyn-rubrics.md`

Branch: `feature/fyn-persona-split` (68 commits ahead of main, 179 behind — Sprint 0 Task 0.1 rebases first).

## Spec files to plan (10 total)

| # | Spec file | Plan output |
|---|-----------|-------------|
| 1 | `00-canonical.md` | `00-canonical-plan.md` |
| 2 | `01-invariants.md` | `01-invariants-plan.md` |
| 3 | `02-current-system.md` | `02-current-system-plan.md` |
| 4 | `03-test-strategy.md` | `03-test-strategy-plan.md` |
| 5 | `10-sprint-0-plan.md` | `10-sprint-0-plan.md` |
| 6 | `11-sprint-1-plan.md` | `11-sprint-1-plan.md` |
| 7 | `12-sprint-2-plan.md` | `12-sprint-2-plan.md` |
| 8 | `13-sprint-3-plan.md` | `13-sprint-3-plan.md` |
| 9 | `14-sprint-4-plan.md` | `14-sprint-4-plan.md` |
| 10 | `README.md` | `README-plan.md` |

## Phases

### Phase 1 — Read all spec files [complete]
### Phase 2 — Read source reference documents [complete]
### Phase 3 — Capture code citations in findings.md [complete]
### Phase 4 — Write `00-canonical-plan.md` [complete]
### Phase 5 — Write `01-invariants-plan.md` [complete]
### Phase 6 — Write `02-current-system-plan.md` [complete]
### Phase 7 — Write `03-test-strategy-plan.md` [complete]
### Phase 8 — Write `10-sprint-0-plan.md` [complete]
### Phase 9 — Write `11-sprint-1-plan.md` [complete]
### Phase 10 — Write `12-sprint-2-plan.md` [complete]
### Phase 11 — Write `13-sprint-3-plan.md` [complete]
### Phase 12 — Write `14-sprint-4-plan.md` [complete]
### Phase 13 — Write `README-plan.md` [complete]
### Phase 14 — Final verification [complete]
All 10 plan files delivered with identical plan-slice template (Objective / Spec reference / Files affected with code citations / Acceptance test / Out of scope); every plan preambles with canonical contract + branch + the four reference docs.

## Decisions
- Template is identical across all 10 plan files (user requirement).
- For sprint plans (10-14), each sprint plan file contains multiple task-slices inside. The plan-slice template applies PER TASK, producing a nested structure: the plan file is an ordered list of plan-slices, one per task in the sprint.
- For reference spec files (00, 01, 02, 03, README), the plan-slice applies at the document level since the "property" IS the full document.
