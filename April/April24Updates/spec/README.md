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

# Fyn v2 Spec — Index

> **BRANCH MANDATE: Every file in this directory describes work to be built on branch `feature/fyn-persona-split`.** Do NOT start from `main`, `dev`, or any other branch. The branch already contains ~7,000 LOC of partially-aligned persona-split machinery that the spec rebuilds / collapses / extends. Working off any other base will produce rework or outright conflict.
>
> Current branch state at spec time (24 April 2026): `origin/feature/fyn-persona-split` is **68 commits ahead of `origin/main`** and **179 commits behind `origin/main`**. Sprint 0 Task 0.1 rebases the branch before any other work starts.

---

## How this directory is organised

The spec was originally one monolithic document (`../fyn-v2-spec.md`). It's been split here into focused files so each sprint plan can be executed independently by a fresh agent context, and each invariant group can be read without the whole narrative.

### Read-only reference (the "what is true and what must become true")

| File | Purpose |
|---|---|
| [`00-canonical.md`](00-canonical.md) | The two-Fyn canonical statement — source of truth, rendered verbatim at the top of every doc, spec, plan, PRD, and task list in this workstream. |
| [`01-invariants.md`](01-invariants.md) | The spec proper — 13 invariant groups, ~35 falsifiable invariants, each with `Property / Falsifiability test / Acceptance criterion`. This is what a reviewer uses to decide "does this build honour the spec?" |
| [`02-current-system.md`](02-current-system.md) | Code-grounded description of what's on `feature/fyn-persona-split` today, anchored to file:line. Summary of `audit-evidence.md`. |
| [`03-test-strategy.md`](03-test-strategy.md) | **Dual-layer test strategy.** Pest (unit / feature / architecture) + Playwright BS-NN browser scenarios (24 specs, click-through from `http://localhost:8000`, no fabricated URLs). Per-invariant mapping table + per-sprint scenario index + harness setup + non-negotiable "report-finished" gates. Every sprint plan references this file. |

### Sprint plans (TDD task decomposition — the "how")

Each plan produces working, testable software on its own and can be executed by a fresh subagent with no knowledge of the other sprints.

| File | Produces | Estimated engineering effort |
|---|---|---|
| [`10-sprint-0-plan.md`](10-sprint-0-plan.md) | Two-Fyn collapse + direct-write conversions + reliability + audit chain + CoreIdentity + billing tools + consent. Ships Rubric-A ~13-15/40. | 3-4 weeks |
| [`11-sprint-1-plan.md`](11-sprint-1-plan.md) | Eval harness (Rubric B Mode 1) + memory model (3 stores + 1 index) + `<known_facts>` prompt block + first 30 scenarios. Ships Rubric-A 17-18/40 🟠 Limited beta. | 1-2 weeks |
| [`12-sprint-2-plan.md`](12-sprint-2-plan.md) | Batch-shaped capture tools for all 18+ entity types; retire regex extractor. Ships full multi-entity coverage. | 2-3 weeks |
| [`13-sprint-3-plan.md`](13-sprint-3-plan.md) | Local-first verification gate + dev deploy to `csjones.co/fynla`. | 1 week |
| [`14-sprint-4-plan.md`](14-sprint-4-plan.md) | Production hardening (external legal, DPIA, Privacy Policy rewrite, provider failover, Sentry). Calendar-bound by external processes. | 4-8 weeks calendar |

---

## Source documents (inputs to this spec)

Located in the parent folder `April24Updates/` and in the vault mirror. All three contain the canonical §0 at the top; all three have file:line citations for every implementation claim.

| Source | What it is |
|---|---|
| [`../audit-evidence.md`](../audit-evidence.md) | Code-grounded ground truth with file:line anchors. §1–§22 + addenda. The primary source for every claim in `02-current-system.md`. |
| [`../audit-synthesis.md`](../audit-synthesis.md) | Consolidated verdict across 5 reviewers + CSJ decisions. §1–§10. The primary source for invariant priorities in `01-invariants.md`. |
| [`../fyn-rubrics.md`](../fyn-rubrics.md) | Rubric A (enterprise assessment, 10 dims × 5 levels = /40) + Rubric B (eval harness, 75 golden conversations). Drives acceptance criteria and PR gates. |

Supporting docs in the same folder:
- `../docs-three-pass-review.md` — the review that shaped v2 of the audit docs.
- `../code-vs-review-report.md` — earlier comparison of branch code to morning-doc claims.
- `../CSJTODO.md` — session 69 handover with CSJ decisions inline.

---

## Decision register (all resolved at spec time)

These are the decisions that shaped the spec. They are closed. If execution surfaces a reason to re-open any of them, the spec is amended first, then implementation follows.

| Decision | Answer | Source |
|---|---|---|
| Tool semantics | Direct-write everywhere (Q1=a) | CSJ 24 April |
| Provider parity | Reach parity, 40/36 catalogue (Q2=a) | CSJ 24 April |
| Recommendation engine | Existing `orchestrateAnalysis` pipeline — reused, not replaced | CSJ |
| Advice response shape | New `advice_response` SSE event + `AdviceResponsePanel.vue` | CSJ 24 April |
| SSE abort | Keep partial writes; instrument + monitor | CSJ 24 April |
| Document extraction | UI-only CTA flow; not an Advice Fyn tool | CSJ 24 April |
| Entry-source mapping | Config-driven + extensible; 4 initial mappings | CSJ 24 April |
| FCA signposting | *"For regulated advice personal to your circumstances, speak to a qualified financial adviser."* | CSJ 24 April |
| Out-of-remit copy | *"I'm able to help you with your finances. {context} is out of scope."* | CSJ 24 April |
| Memory model | 3 stores + 1 index; retrieval order DB → parked → current → index | CSJ 24 April |
| FCA posture | Guidance-only, no targeted-support pursuit | audit-synthesis §8 |
| Persona count | Two Fyns, no Orchestrator class | audit-synthesis §8 |
| Multi-entity thresholds | 95% baseline recall/precision; 100% hard-fail floors | audit-synthesis §8 |
| Python sidecar | Delete | audit-synthesis §8 |
| Deploy gate | Local-first unambiguous | audit-synthesis §8 |
| Rubrics | Build both | audit-synthesis §8 |

---

## Execution workflow

1. Read [`00-canonical.md`](00-canonical.md) — 30 seconds.
2. Read [`01-invariants.md`](01-invariants.md) — 15 minutes. This is the spec. Every line is load-bearing.
3. Read [`02-current-system.md`](02-current-system.md) — 10 minutes. Tells you what's on the branch today.
4. For the sprint you're about to execute, read the corresponding plan file. Each sprint plan is a TDD decomposition with exact file paths, complete code, and shell commands.
5. Execute via `superpowers:subagent-driven-development` (recommended) — fresh subagent per task + two-stage review — OR `superpowers:executing-plans` (inline, faster, less isolation).
6. Verify the sprint's invariants pass per the `Acceptance criterion` lines in `01-invariants.md`. Publish Rubric-A delta at sprint end per [`fyn-rubrics.md §A`](../fyn-rubrics.md).

---

## Non-negotiables before any PR lands

- Canonical §0 must appear at the top of any new doc produced by this workstream (spec amendments, PRDs, task lists, handover notes).
- Every implementation claim about the current branch must carry an inline file:line citation.
- No change that regresses a Rubric-A level or a Rubric-B metric merges without explicit risk acceptance.
- No change that violates canonical §0 (visible handoff, repeat-ask, Advice Fyn writing to DB, missing resume) merges regardless of other scores.
- Branch: every commit goes on `feature/fyn-persona-split` or a feature branch off it (`feature/csj/<subtask>`). Never directly on `main` or `dev`.

---

## Verification summary

**Two layers required for every sprint** — Pest AND Playwright BS-NN scenarios. See [`03-test-strategy.md`](03-test-strategy.md) for the click-through discipline.

Post-Sprint-0:
- `./vendor/bin/pest` — all pass.
- `./vendor/bin/pest --testsuite=Architecture` — all pass.
- `./vendor/bin/pest --testsuite=Browser --filter=BS-` — **20 scenarios PASS**; screenshots in `docs/sprint-0-verification/`.
- `php artisan ai:audit:verify-chain` — `{chain_valid: true, tip_hash: ..., row_count: N}`.
- Rubric-A re-score → 13-15/40.

Post-Sprint-1:
- `./vendor/bin/pest tests/Feature/Fyn/Eval/` — 100% on Mode 1.
- Weekly Mode 2 cron — ≥97%.
- Browser matrix: **24 scenarios PASS** (4 new + 20 regression).
- Rubric-A re-score → 17-18/40 🟠 Limited beta — gate for Sprint 3 dev deploy.

Post-Sprint-2:
- Browser matrix: **38 runs PASS** (24 base + 14 BS-17 batch-tool variants).
- Rubric-B Mode 1: 75/75; Mode 2: ≥97%.

Post-Sprint-3:
- Full matrix on local AND canonical subset (BS-01, 07, 09, 11, 14, 17) on `csjones.co/fynla` — PASS on both.

Post-Sprint-4:
- Browser matrix on `https://fynla.org`: **39 runs PASS** (38 base + BS-25 failover).

**No sprint reports "done" without its required Browser-matrix evidence committed.** Per [`03-test-strategy.md §Non-negotiables`](03-test-strategy.md).

Full procedure in [`01-invariants.md §verification`](01-invariants.md) and each sprint plan's terminal section.

---

*Prepared 24 April 2026. Spec source of truth for Fyn v2. Branch: `feature/fyn-persona-split`. Mirror this folder to `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/spec/` on approval.*
