---
type: handover
mode: context-clear
date: 2026-04-22
session: 1
branch: feature/fyn-persona-split
previous_session: 2026-04-21 session 2 (PRD)
---

# Context Clear Handover — 2026-04-22, Session 1

## Immediate state

Just finished the full Fyn persona-split implementation (all 14 phases, 15 commits on `feature/fyn-persona-split`) plus complete end-to-end browser testing of both the six post-onboarding persona scenarios AND the entire Fyn-driven onboarding flow through every state including every Phase-10/11 edge case. Branch pushed to origin. User flagged one behaviour — multi-entity protection capture only persisted ONE of two policies — and promoted it to the TOP priority for next session. That fix is to be planned and executed before any other work.

## The thread

1. Started from yesterday's PRD + amended plan. Reviewed briefly, then implemented all 14 phases in phase order with commits and tests at every step.
2. User asked for the five deferred visual/plumbing items (tool drift, AiChatPanel chrome, onboarding view swap, parking hydration, live browser tests). All five delivered in `a4e32c8`.
3. User then asked specifically for the full Fyn-driven onboarding flow via the "Quick start with Fyn" CTA end-to-end in the browser with every edge case recorded. Ran it: Path A (journey → Protecting and growing) to terminal state, Path B (focus → Savings), fact parking, parking hydration short-circuit, both profile-review pauses, multi-job loop, spouse skip link, restart/resume/continue/skip action endpoints, employment bubble rename/remove. All recorded verbatim in `April/April22Updates/browser-test-onboarding-full-flow.md` (still in the prior `April21Updates/` folder actually — needs a look — see below).
4. During the protection-module asset_capture test, Fyn only persisted Aviva life insurance from a message that mentioned both Aviva life insurance £300k AND Vitality critical illness £100k. User promoted the multi-entity fix to TOP priority for the next session and explicitly named every module it must cover.

## Files touched (uncommitted before this handover commit)

- `CSJTODO.md` — rewrote the top section to flag the multi-entity fix as the mandatory first task for next session, with a plan-of-attack stub naming every module that must be verified (protection, investment, retirement, savings, estate, expenditure, income, employment, family members, goals/life events).

Everything else from this session is already committed and pushed. 15 commits on `feature/fyn-persona-split`:

```
a4e32c8 feat(fyn): complete remaining Phase 13/14 items — tool drift, chat UI chrome, onboarding mount swap, parking hydration
4e105b8 test(fyn): Phase 14 FynPersonaOrchestrator state-transition coverage
876ddd5 feat(fyn): Phase 13 frontend — Vuex, SSE handlers, action endpoint, ProfileReviewPanel, FynOnboardingChat, dashboard blur
9948bbe feat(fyn): Phase 12 retraction + Phase 13 preview-mode advice instruction
774cf45 feat(fyn): Phase 11 OnboardingFactExtractor — regex-based parking-only capture
10e77be feat(fyn): Phase 10 director extensions — profile review, multi-job, spouse skip, employment bubbles
bed82dc feat(fyn): Phase 9 controller routing + Phase 12 action endpoint
55167e0 feat(fyn): Phase 6/7/8 — invoker, orchestrator, classifier fast-path
f1787ea feat(fyn): Phase 5 persona registry — config + class + integrity tests
9d61226 feat(fyn): Phase 4 tools — handoff + Will + LPA
fa565d2 feat(fyn): Phase 3 prompt builders — rename + DataCapturePromptBuilder
82c9fdd feat(fyn): Phase 2 persistence — persona, persona_state, parked facts, will columns
d47773b feat(fyn): add HandoffContract constants for internal tool names
7aec391 feat(fyn): add CaptureContext value object
e715d98 feat(fyn): add persona-split feature flag config
```

Browser test artefacts + finding docs in `April/April21Updates/` (not April22) — dated when the work actually ran:
- `browser-test-conversations.md` — 6 persona-split scenarios
- `browser-test-onboarding-full-flow.md` — every onboarding state + edge case
- `findings-implementation.md` — bugs found and fixed during implementation

## What the next Claude needs to know

1. **TOP PRIORITY — multi-entity capture across ALL modules.** See the new "TOP PRIORITY FOR NEXT SESSION" block at the top of `CSJTODO.md`. Don't start any other implementation work until this is planned and verified. The prior FR-M14 fix covered assets + liabilities only; protection/retirement/investment/etc. still drop entities when the user mentions multiple in one message. Plan-of-attack stub is in CSJTODO. Start by inventorying every `create_*`/`update_*` tool and its current description, then audit each against a new multi-entity rule, THEN update prompts in both `OnboardingPromptBuilder::assetCaptureInstructions` AND `DataCapturePromptBuilder::captureInstructions`.

2. **`feature/fyn-persona-split` is pushed but NOT merged.** When the multi-entity fix is done, merge back: `feature/fyn-persona-split → onboardingFyn`, then Gate 1 deploy `onboardingFyn → csjones.co/fynla`. No expected conflicts — the feature branch is purely additive over `onboardingFyn` HEAD.

3. **`FYN_PERSONA_SPLIT` flag is OFF by default.** Dev server (localhost:8000) is currently running with `FYN_PERSONA_SPLIT=true` as an env override (PID in `/tmp/fynla-server.pid`). If still running when the next session starts, it's safe to kill and restart with the flag off unless continuing persona-split testing.

4. **DB has test data from this session's browser runs.** `john@example.com` (id=18) was reset and driven through onboarding multiple times; spouse `angela@example.com` (id=22) was created; Aviva life insurance £300k persisted; LPA id=5 persisted; savings account Nationwide £5000 from the first persona-split run. Don't assume seed-fresh state.

5. **Known LLM-compliance quirks** (documented in `CSJTODO.md` under "Known LLM-compliance issues"): data-capture prompt soft on format, advice prompt prefers navigate over delegate, LPA `status=registered` extraction failed once, will capture currently routed through advice not data_capture, DOB slashed-date parse is Carbon-default American m/d/y. Lower priority than the multi-entity fix. 

6. **3 pre-existing Pest flakes** (`AutoRiskCalculatorTest`, `InvestmentModuleTest > Risk Profile`, `WillBuilderApiTest > pre-populate`). Order-dependent. Not regressions from this branch — all three pass in isolation. Don't chase them this session.

## Pick up from here

1. Read `CSJTODO.md` top-to-bottom (it was fully rewritten with the multi-entity priority and the 10-module list).
2. Run `./vendor/bin/pest tests/Feature/Onboarding/ tests/Unit/Services/AI/ tests/Unit/Services/Onboarding/` to reconfirm the persona-split suite is green after the overnight gap. Should be 306 passing.
3. Start the multi-entity fix plan. Use `feature-dev:code-explorer` or the Agent tool to inventory every tool definition and their descriptions. Build the module map before writing any prompt changes.
4. When the plan is clear, present it to the user for sign-off BEFORE touching code. Prior experience: doing large multi-file changes without alignment causes churn.

**Do NOT** start implementation until the user has reviewed the multi-entity plan.
