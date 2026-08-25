# Fyn — Integrated Plan

**Date:** 24 April 2026
**Status:** Research + synthesis document. Precursor to rewritten spec / plan / PRD.
**Target branches:** `feature/fyn-persona-split` (primary vehicle, supersedes `onboardingFyn` / PR #214)
**Scope:** Reconcile (a) current Fyn on `main`, (b) the verdict's 26 improvement gaps, (c) the persona-split + onboarding UX overhaul in flight, (d) the multi-entity capture fix, (e) the 13-test browser matrix, (f) everything specced in the four PRDs / specs / plans. Produce one honest, ordered roadmap plus a dependency index so small changes don't compound into breaking bugs.
**Tone:** Direct. Every claim has a file:line or vault ref. Nothing sugar-coated.

**Companion docs:**
- [`fyn-system-map.md`](./fyn-system-map.md) — snapshot of Fyn on `main` as of 24 April
- [`verdictFyn.md`](./verdictFyn.md) — Anthropic + xAI rubric rating, 26 gaps, 4 sprints
- `/Users/CSJ/Desktop/fynlaBrain/April/April21Updates/PRD-fyn-persona-split.md` — current PRD
- `/Users/CSJ/Desktop/fynlaBrain/April/April21Updates/spec-fyn-persona-split-design.md` — current spec
- `/Users/CSJ/Desktop/fynlaBrain/April/April21Updates/plan-fyn-persona-split.md` — current plan (6,066 lines)
- `/Users/CSJ/Desktop/fynlaBrain/April/April20Updates/PRD-fyn-driven-onboarding.md` — prior PRD (superseded)
- `/Users/CSJ/Desktop/fynlaBrain/April/April22Updates/plan-multi-entity-capture.md` — multi-entity fix plan
- `/Users/CSJ/Desktop/fynlaBrain/April/April22Updates/REPORT-2-implementation-status.md` — 22 April implementation audit
- `/Users/CSJ/Desktop/fynlaBrain/April/April23Updates/handover-2026-04-23-session-1.md` — most recent handover

---

## 0. Executive summary

### Where Fyn actually is right now

- **`main`** — Fyn on production/dev runs the single-prompt `CoordinatingAgent::chat` path described in `fyn-system-map.md`. None of the onboarding state machine, persona split, multi-entity fix, or new estate tools are on `main`. Landing-page "Quick start with Fyn" CTA was unhidden 23 April (commit `97edb5d`) but routes to the known-buggy pre-persona flow — so the CTA is live but lands in quicksand.
- **`onboardingFyn`** — 41 commits ahead of main. Has the state machine, director, grouped-extract handlers, FR-M9..FR-M15 shipped, 6/7 smoke tests PASS. PR #214 is OPEN but not deployed anywhere. **Should be closed as superseded.**
- **`feature/fyn-persona-split`** — 68 commits ahead of main, 72 behind. Has everything on `onboardingFyn` plus the 14 persona-split phases plus the B-1..B-9 remediation plus the multi-entity gap-fill (now verified working end-to-end for the "Aviva life + Vitality critical illness" case). 2,448 tests passing. Working tree clean at `355aa97`. **Not deployed anywhere.** This is the vehicle for everything downstream.

### The shortest honest gap summary

- **12 Must FRs have code but 3 are known-broken** at the behavioural level, even though the code is present (spec says "done", live browser doesn't agree)
- **16 of 20 planned Feature tests are missing** — the 14 persona-split scenario tests don't exist, plus 2 onboarding tests
- **No evaluation harness** (verdict G1) — every prompt/model/tool change is shipped blind
- **No evaluator-optimiser loop** (verdict G2) — validator flags violations but doesn't regenerate
- **Three dispatch paths with duplicated gap-fill logic** — W1 in the 23 April tech-debt report, extracted `MultiEntityGapFiller` service deferred
- **Architectural debt in `AppLayout`** — mounted inside every view, so routes trigger remount. FR-M21's "aside outside router-view" assumption is wrong; current fix is a mount-time scroll rescue
- **Director is 1985 lines** (W2 in tech-debt) — beyond the 4× split-candidate threshold. Needs `OnboardingCaptureAckBuilder` + `OnboardingGapFiller` extraction
- **`main` has moved 72 commits** since persona-split diverged — merge conflicts likely in `AppLayout.vue`, `CoordinatingAgent.php`, `router/index.js`

### Top 5 things to get right before anything else

1. **Rebase `feature/fyn-persona-split` onto the current `main`** before any further feature work. 72 commits of drift guarantees conflicts. Doing this first means subsequent testing is against realistic conditions, not a 2-week-old main.
2. **Complete the 13-test browser matrix locally** (Tests 2-13 are pending; Test 1 is partial). Every test has to pass with verbatim transcript + DB evidence before any merge to `onboardingFyn` or `dev`. No completion reports without evidence per `critical_browser_testing_law`.
3. **Back-fill the 16 missing Feature tests** (14 persona-split scenarios + 2 onboarding scenarios from the plan). Writing these gives us an automated guard against the 9 B-X bugs, makes the eval harness from verdict G1 tractable, and means future changes don't regress silently.
4. **Close the remaining B-X bugs** (B-2 household link, B-3 family-member age, B-7 LPA status, B-8 advice-bypass-delegate, B-9 data-capture prompt enforcement). B-1 is fixed; the rest are scoped and ready.
5. **Land verdict Sprint 1 at the same time** — temperature drop, Anthropic cache metrics, reasoning-tokens tracking, sanitise-before-validate, first-name sanitisation. These are 10-minute fixes each but give immediate quality + observability wins.

If you do 1–5 and then ship persona-split to dev, we are where we should have been on 21 April. Everything beyond (evaluator-optimiser loop, extracted services, AppLayout refactor, mobile parity) stacks on top.

---

## 1. Current state on `main` — recap

The system map covers this in full (`fyn-system-map.md`). Condensed reminder:

- **Request flow**: `AiChatController::sendMessage` → `CoordinatingAgent::chat` (via `HasAiChat` trait) → 10-layer system prompt → xAI `grok-4-1-fast-reasoning` OR Anthropic `claude-haiku-4-5` → tool loop (max 5/turn) → SSE events back to Vuex store
- **29 tools** across read / analysis / tax / planning / data-creation / modification / profile categories
- **Classification**: `QueryClassifier` regex → 22 types → drives per-query prompt layers + KYC gate + required tools
- **Persistence**: `ai_conversations`, `ai_messages` (with `system_prompt` snapshot), `ai_advice_logs`
- **No onboarding flow, no persona split, no multi-entity fix, no new estate tools.** These are all on the feature branches.
- **Landing CTA** is unhidden on dev (23 April, `97edb5d`) but hits the default `CoordinatingAgent::chat` path, which has the 4 bug patterns the April 20 bug analysis catalogued as well as the 13 F-items that recur elsewhere.

**Critical gotcha for integration:** `main` has moved 72 commits since persona-split diverged. Lifecycle rate-limit fix, session 67 investment analyse-500 fix, session 66 pension projection fix, session 67 UI bundle, session 68 dev→main release + hotfix, intervention/image v3 downgrade (PHP 8.2 compatibility). None of these are on persona-split. A merge will conflict.

---

## 2. Verdict recommendations — carried forward

The 26 gaps from `verdictFyn.md` (plus 4 refactors) distil to 8 load-bearing ones. Carried forward verbatim (numbering preserved for cross-ref):

| # | Gap | Status today | Priority for integrated plan |
|---|---|---|---|
| **G1** | **No evaluation harness** | Still missing on `main`; **partially addressed** on persona-split via new unit tests (classifier, validator, fact-extractor, orchestrator) but no end-to-end eval | **Critical — Sprint 1** |
| **G2** | **No evaluator-optimiser loop** | Validator logs violations but doesn't regenerate — same on all branches | **High — Sprint 1** |
| **G3** | ~~Stale model choice~~ | **Withdrawn 24 April** — `grok-4-1-fast-reasoning` is a deliberate unit-economics choice; dead `advanced_chat_model` branch noted for future cleanup | — |
| **G4** | **Conversation history tool-call fold-in as `[Context: ...]` text** | Same on all branches — breaks xAI cache, leaks, caused April 16 bugs | **High — Sprint 2** |
| **G5** | **Regex-only classifier, no LLM fallback** | Same on all branches; persona-split promotes the classifier to orchestrator level but keeps it regex-only | **High — Sprint 3** |
| **G6** | **Temperature 0.7 (xAI), default ~1.0 (Anthropic)** | Same on all branches | **High — Sprint 1 (10-min fix)** |
| **G7** | **Parallel tool execution** | Same on all branches — tools run serially via `foreach` | **Medium — Sprint 4** |
| **G8** | **Tool descriptions missing examples + boundaries** | Partial on persona-split — multi-entity affordance added, old "DO NOT call other tools" removed | **Medium — ongoing** |
| **G9** | **No structured output for recommendations** | Same on all branches | **Medium — Sprint 3** |
| G10 | **Anthropic cache tokens not persisted** | Same on all branches | Low — Sprint 1 (1-hour fix) |
| G11 | **`reasoning_tokens` not tracked on xAI** | Same on all branches | Low — Sprint 1 (30-min fix) |
| G12 | **No reasoning-summary streaming** | Same on all branches | Medium — Sprint 4 |
| G13 | **`MAX_TOOL_CALLS_PER_TURN=5` too low for holistic** | Same on all branches | Low — Sprint 4 |
| G14 | **20-turn history window too small** | Same on all branches | Medium — Sprint 4 |
| G15 | **Sanitise-before-validate order** | Same on all branches | Low — Sprint 1 (10-min fix) |
| G16 | **KYC dedup via substring matching** | Same on all branches | Low — Sprint 3 |
| G17 | **Model IDs duplicated in 5 places** | Same on all branches | Nit |
| G18 | **`ai_chat_enabled` column unused** | Same on all branches | Nit — delete |
| G19 | **Raw user text as conversation title** | Same on all branches | Nit |
| G20 | **Admin UI missing for `AiAuditController`** | Same on all branches | Backlog |
| G21 | **`get_module_analysis(holistic)` edge case** | Same on all branches | Low |
| G22 | **No SSE retry** | Same on all branches | Low |
| G23 | **`StaticFynChat` drifts from real panel** | Same on all branches | Nit |
| G24 | **Fyn-branded cards aren't actually Fyn** | Same on all branches | Nit |
| G25 | **No user feedback signal (thumbs up/down)** | Same on all branches | Medium — Sprint 4 |
| G26 | **Preview-user 100k tokens/day is arbitrary** | Same on all branches | Nit |

**Plus from verdict §8**: first-name prompt injection vulnerability (security, carried forward).

**Plus from verdict §4.2** (Layer 2 prompt): banned-acronym list is 1,200+ characters of prompt tax — could move to post-hoc validator replace instead.

---

## 3. In-flight work inventory

### 3.1 `onboardingFyn` branch (PR #214)

Contains commits up to `ae78731` (docs(session) for 21 April). **Superseded by `feature/fyn-persona-split`** which has everything here plus more.

**What it delivers:**
- `OnboardingChatDirector` + `OnboardingStateMachine` + `OnboardingPromptBuilder` + `OnboardingValueInterpreter`
- `JourneyStateService`, `SpouseLinkingService`
- `TrustObserver` for CLT orphan prevention (FR-M15)
- AiChatController routing `$inOnboarding` branch (2-way match, persona-split adds 3-way)
- Frontend: `FynOnboardingChat.vue`, Vuex `aiChat.js` store extensions, Journey Vue components
- Database: `users.onboarding_fyn_*` columns, state-machine constants
- Smoke test report: 6/7 PASS, FR-M14 off-script filter had one regression caught

**Why close this PR:** persona-split has all of this + newer commits on top. Merging `onboardingFyn` first then `fyn-persona-split` would be two merges when one suffices. The memory note `project_pr214_with_persona_split.md` already recommends the merged approach.

### 3.2 `feature/fyn-persona-split` branch

27 additional commits on top of `onboardingFyn`. Implements the full April 21 persona-split PRD plus the April 22 multi-entity plan plus the B-1..B-9 remediation.

**New PHP services:**
- `App\Services\AI\FynPersonaOrchestrator` — post-onboarding dispatcher, ~415 LOC
- `App\Services\AI\FynPersonaInvoker` — per-persona LLM invocation wrapper, ~518 LOC
- `App\Services\AI\FynPersonaRegistry` — config-driven persona lookup
- `App\Services\AI\HandoffContract` — constants for `delegate_to_capture` / `capture_complete` tool names
- `App\Services\AI\Prompts\DataCapturePromptBuilder` — short capture-focused prompt, ~500 tokens
- `App\Services\Onboarding\OnboardingFactExtractor` — regex-based fact parking
- `App\Services\Onboarding\HouseholdProvisioner` — spouse household linking (B-2 subject)
- `App\ValueObjects\CaptureContext` — immutable handoff payload

**Renames:**
- `App\Services\AI\SystemPromptBuilder` → `App\Services\AI\AdvicePromptBuilder` (50+ references updated)

**Database migrations:**
- `2026_04_21_*_add_persona_state_to_ai_conversations.php` — JSON column
- `2026_04_21_*_add_onboarding_parked_facts_to_ai_conversations.php` — JSON column
- `2026_04_21_*_add_persona_to_ai_messages.php` — enum column
- `2026_04_21_*_add_will_columns.php` — `residuary_beneficiary`, `guardian_for_minors`, `specific_gifts`

**New tools (registered in both `AiToolDefinitions` and `XaiToolDefinitions`):**
- `delegate_to_capture` — internal handoff, never shown to user
- `capture_complete` — internal handoff, never shown to user
- `create_will` / `update_will`
- `create_power_of_attorney` / `update_power_of_attorney`

**Frontend:**
- Vuex `aiChat.js` — `personaMode` + `onboardingLayout` state, `postAction` method, SSE handlers for new event types
- `AppLayout.vue` — wide/standard chat-aside widths, dashboard blur, profile-review route push
- `AiChatPanel.vue` — capturing pill, preview CTA, record cards, placeholder swap
- `FynOnboardingChat.vue` — extracted from `AiChatPanel` for onboarding mode

**Feature flags:**
- `FYN_PERSONA_SPLIT` (default false) — gates orchestrator
- `FYN_CLASSIFIER_FAST_PATH` (default true) — gates classifier fast-path inside orchestrator
- `FYN_CAPTURE_MAX_TURNS` (default 6) — capture-mode timeout

### 3.3 Spec / plan / PRD set (vault)

| Doc | Lines | Status |
|---|---|---|
| PRD-fyn-persona-split (April 21, amended April 22) | 410 | **Authoritative** — current release spec |
| spec-fyn-persona-split-design (April 21, amended April 22) | 595 | **Authoritative** — technical design |
| plan-fyn-persona-split (April 21, amendments A–L) | 6,066 | **Authoritative** — task breakdown, but **largely implemented** — needs audit against current state |
| PRD-fyn-driven-onboarding (April 20) | 343 | **Superseded** by April 21 PRD |
| fynOnboardFix (April 15, amended April 20) | 768 | Legacy spec — superseded |
| fynOnboarding (April 15, amended April 20) | 387 | Legacy plan — superseded |
| plan-multi-entity-capture (April 22) | — | **Authoritative** for the multi-entity work — Phases A/B/C shipped, D (browser matrix) outstanding |
| REPORT-2-implementation-status (April 22) | — | **Honest implementation audit** — what's actually done vs specced |

### 3.4 Test coverage state

From REPORT-2 plus 23 April handover:

**Unit tests — 7 of 6 planned present** (session 4 added one extra):
- ✅ `FynPersonaOrchestratorTest` (315 lines)
- ✅ `FynPersonaRegistryTest` (114 lines)
- ✅ `FynPersonaInvokerTest` (256 lines) — was missing in REPORT-2, added in session 4
- ✅ `DataCapturePromptBuilderTest` (105 lines) — was missing in REPORT-2, added in session 4
- ✅ `OnboardingFactExtractorTest` (169 lines)
- ✅ `CaptureContextTest` (105 lines)
- ✅ `AdvicePromptBuilderPersonaSplitTest` (75 lines)
- ✅ `AssetCaptureEntityExtractorTest` (334 lines)
- ✅ `AssetCaptureOffScriptFilterTest` (353 lines)
- ✅ `HouseholdProvisionerTest` (74 lines)
- ✅ `OnboardingChatDirectorFixesTest` (232 lines)
- ✅ `OnboardingStateMachineTest` (460 lines — was 433 before session 4 additions)
- ✅ `OnboardingValueInterpreterTest` (219 lines)
- ✅ `SpouseCollisionTest` (152 lines)

**Feature tests — only 3 of 14 planned present** (still 11 missing):
- ✅ `AssetCaptureMultiEntityTest` (119 lines) — added session 4 (B-1 gap-fill verification)
- ✅ `StartOnboardingEndpointTest` (104 lines)
- ✅ `StateMachineWalkthroughTest` (198 lines)
- ❌ `PersonaSplit/KycGateFlowTest` — missing
- ❌ `PersonaSplit/InlineCaptureFlowTest` — missing
- ❌ `PersonaSplit/CancelMidCaptureTest` — missing
- ❌ `PersonaSplit/CaptureTimeoutTest` — missing
- ❌ `PersonaSplit/ClassifierFastPathTest` — missing
- ❌ `PersonaSplit/PreviewModeTest` — missing
- ❌ `PersonaSplit/CreateWillToolTest` — missing
- ❌ `PersonaSplit/CreatePowerOfAttorneyToolTest` — missing
- ❌ `Onboarding/ProfileReviewPauseTest` — missing
- ❌ `Onboarding/SpouseSkipTest` — missing
- ❌ `Onboarding/MultiJobCaptureTest` — missing
- ❌ `Onboarding/RetractionTest` — missing
- ❌ `Onboarding/OnboardingResumeTest` — missing
- ❌ `Onboarding/FactParkingTest` — missing

**Browser tests:**
- 21 April: Full Path A walkthrough + edge cases completed (`browser-test-onboarding-full-flow.md`)
- 22 April: Multi-entity matrix — Row 1 (protection) done but confirmed failure pre-session-4 fix
- 23 April: Test 1 partial (through `base_employment`); Tests 2-13 pending

**Pest total:** 2,448 tests passing, 1 pre-existing flake (`AutoRiskCalculatorTest` enum truncation) + 1 related flake (`InvestmentModuleTest > Risk Profile Management`).

---

## 4. Three-lens delta analysis

### 4.1 Implementation lens — what's specced vs built

Authoritative source: REPORT-2 (22 April) plus session 4 additions.

| FR | Specced | Built | Verified in browser | Gaps |
|---|---|---|---|---|
| FR-M1 (3-way route) | ✅ | ✅ at `AiChatController.php:156-168` | ✅ | — |
| FR-M2 (orchestrator + state + timeout) | ✅ | ✅ at `FynPersonaOrchestrator.php` | ✅ browser-test-conversations.md | — |
| FR-M3 (invoker) | ✅ | ✅ at `FynPersonaInvoker.php` | ✅ | — |
| FR-M4 (SystemPromptBuilder→AdvicePromptBuilder rename) | ✅ | ✅ | ✅ | — |
| FR-M5 (DataCapturePromptBuilder + CaptureContext) | ✅ | ✅ | ✅ | — |
| FR-M6 (FynPersonaRegistry + config) | ✅ | ✅ | ✅ | — |
| FR-M7 (handoff tools) | ✅ | ✅ | ✅ | — |
| FR-M8 (classifier fast-path) | ✅ | ✅ at `QuerySchemas.php:703` | ✅ browser-test Scenario 2 & 3 | — |
| FR-M9 (create_will + schema) | ✅ | ✅ | ⚠️ limited browser testing | LPA status drop (B-7) |
| FR-M10 (create_power_of_attorney) | ✅ | ✅ | ⚠️ Scenario 6 only | LPA status drop (B-7) |
| FR-M11 (ai_messages.persona) | ✅ | ✅ | ✅ | — |
| FR-M12 (persona_state + onboarding_parked_facts JSON) | ✅ | ✅ | ✅ | — |
| FR-M13 (AiConversation + AiMessage factories) | ✅ | ✅ | ✅ | — |
| FR-M14 (profile review pauses — family + expenditure) | ✅ | ⚠️ state-machine OK, UX partial | ⚠️ sessions 2 & 3 worked; first cycle has mitigations | **B-6 first-pause return nav fallback; AppLayout remount underlying cause** |
| FR-M15 (employment bubbles + multi-job loop) | ✅ | ✅ | ✅ | — |
| FR-M16 (spouse skip link + action endpoint) | ✅ | ✅ | ✅ | — |
| FR-M17 (conversational retraction) | ✅ | ✅ prompt layer | ❌ **never browser-tested end-to-end** | — |
| FR-M18 (POST /action + middleware exclusion) | ✅ | ✅ | ✅ all 4 action types | — |
| FR-M19 (OnboardingFactExtractor + parking merge) | ✅ | ✅ | ✅ | — |
| FR-M20 (resume-from-where-left-off) | ✅ | ✅ | ✅ | — |
| FR-M21 (wide chat + dashboard blur + route push) | ✅ | ⚠️ session 3 amendments | ⚠️ works with mitigations | **AppLayout remount architectural issue, papered over by mount-time scroll rescue** |
| FR-M22 (ProfileReviewPanel) | ✅ then **DROPPED 22 April** | dropped | N/A | — |
| FR-M23 (Vuex personaMode + onboardingLayout) | ✅ | ✅ | ✅ | — |
| FR-M24 (capturing pill + record cards + preview CTA) | ✅ | ✅ | ✅ | — |
| FR-M25 (preview-mode advice prompt) | ✅ | ✅ | ✅ Scenario 4 | — |
| FR-M26 (feature flags) | ✅ | ✅ | ✅ | — |
| FR-S1 (observability logs) | ✅ | ✅ | ✅ | — |
| FR-S2 (weekly drift audit) | ✅ | ❌ **NOT BUILT** | N/A | **Missing — backlog it or drop** |
| FR-S3 (rollback mechanics) | ✅ | ✅ | ⚠️ untested | — |

**Net score: 26/26 Must-FRs have code; 3 have known behavioural bugs (M14, M17 untested, M21 architectural mitigation), 2 of 3 Should-FRs done.**

### 4.2 Usefulness lens — does this solve the stated problem?

**Caveat (added 24 April after enterprise-verdict reloop)**: "usefulness" here is measured against the PRD's feature goals, not against commercial-regulated readiness. The persona-split + onboarding work genuinely solves the PRD's user-feature problems, but per `enterprise-verdict.md` the system is still **C- against enterprise bar** with 10 Critical gaps. Feature-usefulness ≠ commercial-readiness.

The PRD's five concrete problems (§1) were:

**P1 — Behaviour quality during data entry is brittle.**
- Diagnosis: correct. Advice-prompt bias toward single-tool-per-turn breaks multi-entity capture.
- Fix: persona split + short capture prompt + multi-entity affordance in tool descriptions + gap-fill service.
- Outcome: **Working** per 22 April session 4 end-to-end test — "Aviva life £300k + Vitality critical illness £100k" both persist. Browser-verified on Fyn Quick Chat path.
- Residual: LPA status compliance (B-7), advice-bypass-delegate pattern (B-8), data-capture prompt soft on format (B-9). These are LLM-compliance issues, not architectural.

**P2 — Tokens are wasted.**
- Diagnosis: correct. Advice prompt is ~1,600 tokens; capture only needs ~500.
- Fix: DataCapturePromptBuilder ~500 tokens.
- Outcome: **Partially verified** — prompt length difference is real and visible in logs. No production measurement yet (not deployed), so the PRD's "≤600 token data-capture turns in 7 days on prod" KPI is N/A.

**P3 — Architecture does not scale.**
- Diagnosis: correct. Adding a third persona would have meant duplicating SystemPromptBuilder.
- Fix: `FynPersonaRegistry` config-driven.
- Outcome: **Works on paper** — adding a third persona is now one config entry + one prompt builder class. No live third persona built yet, so the claim isn't stress-tested. The integrity test asserts registry coherence.

**P4 — Fyn silently ignores extra information.**
- Diagnosis: correct. Single-question state machine handlers didn't consult a shared memory.
- Fix: `OnboardingFactExtractor` + `ai_conversations.onboarding_parked_facts` + per-state hydration paths.
- Outcome: **Working** — browser-test (21 April) verified "Angela, Sam 8, Eli 6" all parked; hydration short-circuit avoided a whole LLM call. Fact parking is a genuine UX win.

**P5 — Resume from where left off is broken.**
- Diagnosis: correct. Welcome-back flow was claimed done but never fired.
- Fix: `POST /api/ai-chat/conversations/{id}/action` with `resume` / `continue` / `restart` / `skip`.
- Outcome: **Working** — all four action types verified in 21 April browser test.

**Usefulness verdict:** The PRD's stated problems are genuinely solved on the branch. Where it matters (multi-entity, fact parking, resume), live browser tests agree with the spec. The failure modes that remain (B-7/B-8/B-9 LLM compliance, B-2/B-3 data fidelity, FR-M21 architectural cost, FR-S2 drift audit) are worth fixing but don't undermine the core usefulness claim.

### 4.3 Eval lens — can we measure if this is working?

**This is where the branch is weakest**, and it aligns with verdict G1 / G2.

| Dimension | Current state | Gap |
|---|---|---|
| Unit tests | ✅ Strong — 14 new unit test files, 2,448 total passing | — |
| Feature tests | ❌ Weak — 11 of 14 planned files missing | **16 missing scenarios** |
| Browser matrix | ⚠️ Partial — Test 1 partial, Tests 2–13 pending, matrix is hand-run not automated | **Automation needed** (Playwright MCP sequence per row) |
| Production metrics | ❌ None — nothing deployed, no instrumentation target | **Need** Plausible / structured logs per the PRD KPIs |
| LLM compliance regression | ❌ None — no mocked-LLM harness that could catch "Fyn started hallucinating acronyms" | **Critical for ongoing model / prompt changes** — ties to verdict G1 |
| Cost / latency tracking | ⚠️ xAI cached_tokens logged; Anthropic cache metrics ignored; no reasoning_tokens capture; no p95 latency SLI | **Verdict G10 + G11 + dashboard** |
| Quality signals (thumbs up/down) | ❌ Not in product | **Verdict G25** |

**The concrete gap:** after this work ships to prod, there is no way to answer "has Fyn gotten better or worse?" beyond raw support tickets. The PRD's 7 KPIs (token-usage, off-script complaints, drop-off rate, ask-count per block, resume invocation rate, orchestrator success rate, LPA creation rate) all require instrumentation that isn't in the branch. At least 4 of them need log-aggregation queries which aren't set up.

**Before any deploy to dev** the following should be locked in:
1. Log-aggregation structure for the 7 PRD KPIs (structured logs in `FynPersonaInvoker::invoke`, `FynPersonaOrchestrator::dispatch`, `handleResumeAction`)
2. Feature tests for the 11 missing persona-split / onboarding scenarios
3. Mocked-LLM harness ("if model says X, does the system behave correctly") — verdict G1 eval minimum viable
4. Structured eval run on a seed of 30–50 golden conversations against the current prompt

---

## 5. Issues, omissions, mistakes

### 5.1 The multi-entity issue — detailed trace

The user asked for detail here. Full archaeology of the bug.

**Symptom (16 April test, pre-fix):**
> User: *"I have Aviva life insurance £300,000 and Vitality critical illness £100,000"*
> Fyn (text): acknowledges both
> DB: 1 `LifeInsurancePolicy` row, 0 `CriticalIllnessPolicy` rows

**Root cause investigation (22 April findings-multi-entity.md):**

Two competing prompt signals:

| Source | Phrase (verbatim) | LLM interpretation |
|---|---|---|
| `OnboardingPromptBuilder.php:102` (prompt) | *"when the user mentions multiple holdings/records in a single message, emit one tool_use block per record"* | "emit multiple" |
| `XaiToolDefinitions.php` line 284, 298, 323, 437, 457–458, 504, 623, 663, 676, 798, 848–849, 873, 889 (15 tools, tool descriptions) | *"Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn."* | "don't call this tool twice either" |
| `XaiToolDefinitions.php:848-849` (`create_family_member`) | *"For multiple children, call this tool ONCE per child in separate turns."* | directly contradicts |
| `FcaProcessInstructions::getDataCreationGuidance` (advice path only) | *"you MUST call the appropriate tool"* (singular) | single-emission on the fallback path |

**LLM behaviour:** xAI `grok-4-1-fast-reasoning` trusts the tool metadata more than the system prompt when signals conflict. So even with the multi-entity instruction in the prompt, the LLM emitted one `create_protection_policy` per turn.

**Fix path (Phases A/B/C of April 22 plan):**
- **Phase A** — removed `"Do NOT call any other creation tools in the same turn"` from 15 xAI tools + 1 Anthropic tool; replaced `create_family_member` "separate turns" guidance with multi-entity affordance; narrowed `create_property` exclusion to navigate + analysis tools only; added `"You MAY call this tool multiple times in the same turn when the user mentions multiple items of this type"` to 34 tools
- **Phase B** — promoted multi-entity rule to TOP of `OnboardingPromptBuilder::assetCaptureInstructions` and `DataCapturePromptBuilder::captureInstructions`; added inline examples per focus
- **Phase C** — softened `FcaProcessInstructions` single-emission bias to "tool(s)" and added multi-entity example

**Result of Phases A/B/C alone (22 April session 3 re-test):** **STILL FAILING.** Phase A/B/C prompt changes are necessary but not sufficient. xAI-Grok continued emitting one tool call even with the prompt cleaned up.

**Actual fix (session 4, commit `37b6a4b` — 22 April end-of-day):**

A deterministic post-LLM fallback was added, documented as the "gap-fill" mechanism. This looks at Fyn's acknowledgment text — which, per the logs, consistently mentions both policies even when only one tool call fires — and emits the missing tool call(s) deterministically.

Commit message: *"feat(fyn): deterministic multi-entity gap-fill (B-1) + household provisioner (B-2)"*

Key file changes per commit:
- New logic inside the director / invoker / orchestrator — three dispatch paths now all apply the gap-fill. **This duplication is tech-debt W1** in the 23 April tech-debt report.
- New `tests/Feature/Onboarding/AssetCaptureMultiEntityTest.php` (119 lines) as a regression guard.

**Verified live (23 April handover):** "B-1 multi-entity gap-fill is now deployed across all three chat dispatch paths and verified live end-to-end in Fyn Quick Chat (Aviva life £300,000 + Vitality critical illness £100,000 → both policies persisted)."

**Status 24 April:**
- ✅ Fix is in place on `feature/fyn-persona-split`
- ✅ End-to-end verified
- ⚠️ Not yet tested for other modules in Test 2-13 (which are pending) — the April 22 browser-test matrix Rows 2-14 never ran to completion
- ⚠️ **Duplicated logic across 3 paths** — W1 tech debt says "Extract a `MultiEntityGapFiller` service once 3-path pattern is stable"
- ⚠️ **Fragile in principle** — relies on Fyn's acknowledgment text being regex-parseable. If the LLM phrases things differently (e.g. "I've recorded those for you" instead of "Aviva life £300,000 and Vitality critical illness £100,000"), the gap-fill won't fire. Needs evaluation coverage (verdict G1) to detect drift.

**What this means for the integrated plan:**
1. Multi-entity is solved *architecturally* but with a mechanism that has coverage risk — the eval harness (G1) is now MORE important, because we need to detect acknowledgment-regex drift.
2. The duplicated gap-fill logic should be extracted to `App\Services\AI\MultiEntityGapFiller` before additional features touch the three dispatch paths. Every future change doubles or triples the edit surface until that extraction happens.
3. Tests 2-13 of the matrix MUST be completed to verify coverage — each module's entity-type names, label phrasing, and record-creation signals differ, and the regex-based fallback may miss some.

### 5.2 Other known bugs (B-2 through B-9)

| Bug | Severity | Status 24 April | Priority |
|---|---|---|---|
| **B-1 multi-entity capture** | P0 | **FIXED** (session 4) — gap-fill via all 3 paths | — |
| **B-2 spouse household_id NULL** | P1 | Open. HouseholdProvisioner service added but linking on spouse capture not verified | High — data fidelity |
| **B-3 family_member.age NULL** | P1 | Open. DOB saved, age column not computed | Medium |
| **B-4 children DOB → YYYY-01-01** | P2 | Open. "Sam aged 8" → dob `2018-01-01`. Acceptable if user confirms at profile-review | Low |
| **B-5 chat scroll rewinds on pause transition** | P1→Mitigated | Was blocking; session 3 commit `55a13f8` added mount-time rescue | Watch for regression |
| **B-6 first-pause return nav fallback** | P2 | Mitigated via `/dashboard` fallback. Root cause = AppLayout remount. Not properly fixed | Medium |
| **B-7 LPA status=registered dropped by LLM** | P2 | Open. User says "registered", handler saves default `draft`. Tool description tightening needed | Low |
| **B-8 advice bypasses delegate_to_capture** | P2 | Open. Advice Fyn prefers navigate_to_page + fill_form over handoff — sidesteps the whole split mechanism | Medium |
| **B-9 data-capture prompt soft on format** | P2 | Open. Multi-paragraph text where one-sentence ack specified. Either prompt tightening or post-stream filter | Medium |

**Plus from REPORT-2 Part E**: 5 of these 9 still open at end of 22 April, 4 mitigated or fixed in session 4 (B-1 definitively, B-5/B-6 mitigations).

### 5.3 Test coverage gaps

Already captured in §3.4. Summary: **11 Feature tests missing** covering:
- 8 persona-split scenarios (KYC gate, inline capture, cancel, timeout, classifier fast-path, preview mode, create_will, create_power_of_attorney)
- 6 onboarding UX scenarios (profile review pause, spouse skip, multi-job capture, retraction, resume, fact parking)

Plus **0 of 14 multi-entity matrix rows** have feature-test coverage (`tests/Feature/Fyn/MultiEntity/` directory doesn't exist). This matters because the gap-fill is regex-based against acknowledgment text — the most vulnerable layer to model-behaviour drift.

### 5.4 Architectural debt

From 23 April tech-debt-report:

| Item | Severity | Effort | Notes |
|---|---|---|---|
| **W1** — Gap-fill loop logic duplicated across `AiChatController`, `FynPersonaInvoker`, `OnboardingChatDirector` | Warning | 1–2 days | Extract `App\Services\AI\MultiEntityGapFiller`. Three tests should pass identically against the extracted service. |
| **W2** — `OnboardingChatDirector` is 1,985 lines (~4× split-candidate threshold) | Warning | 2–3 days | Candidates: `OnboardingCaptureAckBuilder` + `OnboardingGapFiller` extraction (can merge with W1). |
| **W3** — 5 unused-symbol diagnostics in `OnboardingChatDirector.php` | Warning | <1 hour | Cosmetic cleanup |
| **W4** — **AppLayout remounts on every route change** | Warning — architectural | 1–2 days | The entire `FR-M21` mechanism rests on an assumption that's wrong. Papered over with mount-time scroll rescue (B-5 mitigation) and route-push fallback (B-6 mitigation). **Proper fix: hoist `AppLayout` above `<router-view>`** so the docked `<aside>` + `AiChatPanel` persist across route changes. |

Plus from verdict: `CoordinatingAgent.php` at 2,635 lines (R1 refactor candidate), `HasAiChat::chat()` at ~470 lines (R2), nested tool schemas (R3). These persist through persona-split.

### 5.5 Specced-but-dropped / amended

- **FR-M22 (ProfileReviewPanel.vue)** — specced, built, then dropped 22 April session 3. The `/profile` route + `UserProfile.vue` serves as the review surface. File retained in repo but unused.
- **Chat widths** — spec went through three iterations: `w-[525px]` + `max-w-4xl` (original); `w-[525px]` + `max-w-[896px]` (amended); **712 wide / 356 standard** (current, session 3 normalisation). All earlier literals are anti-values per the PRD amendment log.
- **`OnboardingMemoryExtractor` class** — planned as separate class, dropped. The parking JSON column IS the memory, no second service.
- **`FynIntentClassifier` class** — planned new class, dropped. Reuse `QueryClassifier` promoted to orchestrator level instead.
- **New `PowerOfAttorney` model** — planned, dropped. Existing `App\Models\Estate\LastingPowerOfAttorney` + `LpaAttorney` relationship is the target.

All of these are **good** amendments — they removed complexity. Listed here for traceability.

### 5.6 Spec-vs-implementation deviations

From REPORT-2 Part A:

1. **LPA `status` enum** — plan included `revoked` value; migration shipped without it. LPA status supports `draft`/`registered` only. **Non-blocking but spec/DB drift**.
2. **FR-M21 `aside` assumption** — spec line 480 said *"the chat itself lives in a fixed `<aside>` outside `<router-view>`, so the route change doesn't unmount it and Vuex `aiChat` state persists intact."* Reality: `AppLayout` is imported inside each view, so it DOES unmount. The fix is the mount-time scroll rescue + route-push fallback. **Architectural deviation, mitigated but not fixed.**

---

## 6. Integrated target end-state

The mental model of what "Fyn, fully shipped" looks like after the integrated plan lands.

### 6.1 Entry points (every way a user reaches Fyn)

| # | Entry point | Path | Target persona/director | Notes |
|---|---|---|---|---|
| 1 | Landing page "Quick start with Fyn" CTA | `/register?from=fyn` → `/onboarding/welcome` → onboarding | Director | Currently live on dev (unhidden 23 April), routes to known-buggy flow |
| 2 | Dashboard onboarding deep-link | `/dashboard?openFyn=journey` | Director | Triggers `startOnboardingConversation` |
| 3 | Docked chat panel (desktop) | auto-opens on authenticated routes, lg:+ only | Orchestrator (if flag on) OR CoordinatingAgent | Mount inside AppLayout |
| 4 | Floating chat button (mobile / collapsed desktop) | bottom-right, teleported to body | Orchestrator (if flag on) OR CoordinatingAgent | `AiChatButton.vue` |
| 5 | Mobile iOS tab | `/m/fyn` | Orchestrator OR CoordinatingAgent | `MobileFynChat.vue` |
| 6 | Suggested-prompt tap | from empty state or mobile `SuggestedPrompts` | Router decides | Pre-fills input |
| 7 | Client-side navigation shortcut | `/utils/chatNavigationRouter.js` zero-LLM match | N/A — handled in Vuex | ~15 trigger phrases, 40 route targets |
| 8 | Action endpoint | `POST /api/ai-chat/conversations/{id}/action` | Director | `resume` / `continue` / `restart` / `skip` |
| 9 | Onboarding start endpoint | `POST /api/ai-chat/onboarding/start` | Director | Creates fresh onboarding conversation |
| 10 | Daily insight API (mobile) | `GET /api/v1/mobile/insights/daily` | None — deterministic | Uses `CoordinatingAgent::analyze`, rotates 6 canned strings |
| 11 | Learn Hub deep-link | `prefillPrompt` action in Vuex | Orchestrator | Fills input from article |
| 12 | Journey "Get started with Fyn" | `?openFyn=journey` URL query | Director | Shows journey bubbles |

**Static FynChat** on public pages (`/`, `/features`, etc.) is read-only — no backend — so not an entry point proper.

### 6.2 Tools — final catalogue (combined)

**29 original** + **4 new** = **33 tools**, plus **2 internal handoff tools** = **35 total registered** but **33 user-exposed**.

#### Read / analysis (8 — unchanged from `main`)
- `navigate_to_page`
- `list_records`
- `list_goals`
- `list_life_events`
- `get_module_analysis`
- `get_recommendations`
- `get_tax_information`
- `generate_financial_plan`

#### Meta (1 — unchanged)
- `create_what_if_scenario`

#### Data creation — existing (13 — unchanged list, descriptions cleaned per multi-entity Phase A)
- `create_goal`
- `create_life_event`
- `create_savings_account`
- `create_investment_account`
- `create_holding`
- `create_pension`
- `create_property`
- `create_mortgage`
- `create_protection_policy`
- `create_asset`
- `create_liability`
- `create_estate_gift`
- `create_family_member`

#### Data creation — persona-split additions (2 new)
- `create_trust` (existed; on data-capture persona)
- `create_business_interest` (existed)
- `create_chattel` (existed)
- **`create_will`** — NEW (FR-M9)
- **`create_power_of_attorney`** — NEW (FR-M10, against existing `LastingPowerOfAttorney` model)

#### Onboarding capture (4 — grouped_extract handlers)
- `capture_personal_details`
- `capture_spouse_details`
- `capture_dependants`
- `capture_work_details`

#### Modification (3 — unchanged)
- `set_expenditure`
- `update_record`
- `delete_record`
- `update_profile`

#### Estate updates (2 new)
- **`update_will`** — NEW
- **`update_power_of_attorney`** — NEW

#### Internal handoff (2 new, never shown to user)
- **`delegate_to_capture`** — advice → data-capture
- **`capture_complete`** — data-capture → advice

**Persona tool allocation** (from `config/fyn_personas.php`):

- **advice persona** → 9 read/analysis tools + `delegate_to_capture` (10 total)
- **data_capture persona** → all 13 create + 4 capture + 3 modification + 2 estate update + `capture_complete` (23 total)
- **onboarding director (pre-onboarding flow)** → focus-filtered `create_*` tools + `update_profile` + `update_record` per focus

### 6.3 Interactions — the full journey map

**Flow 1 — Fresh user onboarding**

```
/register?from=fyn → /onboarding/welcome → director starts
  ├── STATE_PATH_CHOICE (wide chat + blurred dashboard)
  ├── STATE_JOURNEY_SELECTION or STATE_FOCUS_SELECTION
  ├── STATE_BASE_PERSONAL (fact extractor populates parking)
  ├── STATE_BASE_SPOUSE (skip link available; parking may short-circuit to gap-fill only)
  ├── STATE_BASE_DEPENDANTS → STATE_BASE_DEPENDANTS_DETAIL
  ├── STATE_PROFILE_REVIEW_FAMILY [PAUSE — /profile surface, 356 chat]
  ├── STATE_BASE_EMPLOYMENT (Full-time / Self-employed / Part-time / Retired / Not working)
  ├── STATE_BASE_WORK (grouped_extract; partial capture OK)
  ├── STATE_BASE_EMPLOYMENT_MORE (multi-job loop: Yes → back / No → forward)
  ├── STATE_BASE_EXPENDITURE
  ├── STATE_PROFILE_REVIEW_EXPENDITURE [PAUSE — /profile surface]
  ├── STATE_ASSET_CAPTURE (journey-focused; gap-fill active)
  ├── STATE_ADD_MORE (filtered bubbles)
  └── STATE_DONE → navigation to module landing
```

**Flow 2 — Post-onboarding advice with handoff (KYC-gated)**

```
POST /api/ai-chat/conversations/{id}/messages
  └── AiChatController → FynPersonaOrchestrator::dispatch
      ├── loadState → current:advice
      ├── QueryClassifier fast-path → not DATA_ENTRY
      ├── invoke advice persona
      │   ├── AdvicePromptBuilder (10-layer, 1,600 tokens)
      │   ├── allowed tools: 9 read + delegate_to_capture
      │   └── LLM emits text "Let me note your pension details first" + delegate_to_capture
      ├── persist assistant message (persona='advice')
      ├── strip handoff from SSE
      ├── set persona_state.current='capturing', pending_advice_question=<original>
      ├── invoke data_capture persona with CaptureContext
      │   ├── DataCapturePromptBuilder (~500 tokens)
      │   ├── allowed tools: 23 create/update + capture_complete
      │   └── LLM asks for pension details OR captures from context
      ├── user responds "Scottish Widows SIPP £50k DC"
      ├── data_capture invokes create_pension → DCPension row
      ├── LLM emits capture_complete
      ├── strip handoff from SSE
      ├── reset persona_state.current='advice'
      ├── re-invoke advice persona with original question primed as system suffix
      └── advice answers with updated <financial_context> + <existing_records>
```

**Flow 3 — Post-onboarding inline capture (no advice blocked)**

```
user mid-advice: "Oh, add my Nationwide ISA £5,000"
  → orchestrator classifier fast-path → DATA_ENTRY + word_count<=40 + no advice shape → preselects data_capture
  → invoke data_capture directly (bypass advice)
  → create_savings_account → ISA row persisted
  → capture_complete (no pending_advice_question → no re-invocation)
  → orchestrator returns to advice state, awaits next user turn
```

**Flow 4 — Multi-entity capture with gap-fill**

```
user: "Aviva life £300k and Vitality critical illness £100k"
  → data_capture Fyn (or director in asset_capture)
  → LLM emits ONE create_protection_policy (Aviva, life_insurance)
  → LLM emits text: "Added Aviva life £300k and Vitality critical illness £100k"
  → post-LLM MultiEntityGapFiller (once extracted per W1) scans ack text
  → detects Vitality / critical illness / £100,000 pattern in text but no matching tool call
  → emits deterministic create_protection_policy (Vitality, critical_illness)
  → frontend queue serialises: fill 1 → navigate → fill form → save → fill 2 → navigate → fill form → save
  → both policies persisted
  → capture_complete with records_created=[{life_insurance_policy, 42}, {critical_illness_policy, 43}]
```

**Flow 5 — Resume from mid-onboarding**

```
user lands on /onboarding/welcome with onboarding_completed=false, onboarding_fyn_step='base_employment', prior AiMessages exist
  → Onboarding.vue mounted() → POST /action {action:'resume'}
  → director::handleResumeAction
  → emits welcome-back greeting: "Welcome back, John. Last time we were noting your employment situation. Continue or start over?"
  → action_bubbles: true in message metadata
  → user clicks Continue → POST /action {action:'continue'} → director re-emits STATE_BASE_EMPLOYMENT
  OR user clicks Start over → POST /action {action:'restart'} → handleRestartAction wipes AiMessages + resets onboarding_fyn_step='path_choice'
```

**Flow 6 — Preview user (data entry blocked)**

```
preview user (is_preview_user=true) asks anything that would require a write
  → orchestrator detects is_preview_user before invoking data_capture
  → emits SSE preview_cta with message "I can't save data in preview mode — sign up and I'll capture this straight away"
  → no persona transition
  → no LLM call

Belt-and-braces:
  → AdvicePromptBuilder contains <preview_mode> instructions telling advice NOT to emit delegate_to_capture
  → PreviewWriteInterceptor middleware excludes /api/ai-chat/conversations from write-blocking (allows read/chat) but all create_* handlers refuse preview users
```

**Flow 7 — Action cancel mid-capture**

```
user is in capturing state with pending_advice_question set, types "never mind"
  → orchestrator pre-invocation cancel-pattern check on raw message before data_capture invocation
  → matches /^(stop|cancel|never mind|forget it|nah|skip)/i (from config/fyn.cancel_patterns)
  → flips persona_state.current='advice', drops pending_advice_question
  → invokes advice persona with system-injected note: "The user cancelled the capture. Acknowledge briefly."
  → advice acknowledges and returns control
```

### 6.4 Prompt layer inventory (combined)

Advice persona (post-onboarding) — **10 layers** from `AdvicePromptBuilder` (unchanged from renamed `SystemPromptBuilder`):
1. Core Identity
2. Compliance & Rules
3. FCA Process Instructions (with preview-mode or data-creation-guidance sub-branch)
4. User Profile
5. Financial Context
6. Existing Records
7. Data Completeness (+ 7b Review Due)
8. Query Knowledge (+ 8b Required Tools + Triggers)
9. KYC Check Result
10. Current Context

**All 10 layers retained** — the persona split does NOT modify the advice prompt content (FR-M4). It renames the builder class and scopes tool availability.

Data-capture persona (post-onboarding) — **shorter DataCapturePromptBuilder** (~500 tokens):
1. Capture intro (reason from CaptureContext)
2. Entity-type-specific instructions
3. Required fields reminder
4. Multi-entity affordance (Phase B from multi-entity plan)
5. Single-sentence acknowledgment guardrail
6. No off-script topics
7. handoff-out condition

Onboarding director — uses **`OnboardingPromptBuilder`** (separate from persona prompts):
- `asset_capture` state delegates to `CoordinatingAgent::chatWithPromptOverride` with a focus-specific capture prompt
- non-capture states run deterministic handlers with `retry_text` (no LLM)
- `grouped_extract` states use restricted prompt + `tool_choice=required`

### 6.5 Observability + evals target end-state

| Signal | Source | Purpose |
|---|---|---|
| Per-turn persona tag | `ai_messages.persona` column | Admin audit; count advice vs capture turns per user |
| Per-turn tool-call summary | `ai_messages.metadata.tool_calls[]` | Debug + cost attribution |
| Per-turn token usage | `ai_messages.{input_tokens,output_tokens}` | Budget + efficiency tracking |
| xAI cache hit rate | `ai_messages.metadata.{cached_tokens,cache_hit_rate}` | xAI prompt caching verification |
| **Anthropic cache hit rate** (currently missing — verdict G10) | `ai_messages.metadata.cache_creation_input_tokens` + `cache_read_input_tokens` | Provider parity |
| **reasoning_tokens** (currently missing — verdict G11) | `ai_messages.metadata.reasoning_tokens` | Accurate xAI budget accounting |
| Classifier fast-path decisions | `Log::info [FynPersonaOrchestrator]` | Drift detection |
| Handoff transitions | `Log::info [FynPersonaOrchestrator]` state_change events | Handoff success rate |
| Handoff failures | `Log::warning` malformed / timeout | Debug + alerting |
| StructuredResponseValidator violations | `ai_messages.metadata.validation_violations[]` | Prompt quality signal |
| Fact-parking extractions | `ai_conversations.onboarding_parked_facts` JSON + `hydrated_from_parking:true` marker on SSE | Onboarding efficiency metric |
| Skip-link + action-endpoint usage | `AiChatController::action` logs | UX instrumentation |
| Multi-entity gap-fill fires | new log line in `MultiEntityGapFiller` (once extracted) | Fragility detection for acknowledgment-regex |
| User thumbs up/down (currently missing — verdict G25) | new `ai_message_ratings` table | Quality feedback |
| Admin UI for audit trail (currently missing — verdict G20) | new Vue admin page consuming `AiAuditController` endpoints | Debug tooling |
| Eval harness (currently missing — verdict G1) | `tests/Eval/FynEvalTest.php` + golden conversation dataset | Prompt/model regression guard |

---

## 7. Dependency / touch-point index

This answers "if I change X, what else do I need to verify?". Every entry cross-references files, bug patterns, tests, and downstream effects. The user's specific concern: *"the system is interconnected to the degree it is often a small change compounds across the app and ends up being a breaking bug, which is impossible to trace"*.

Organised by subsystem. Cross-ref IDs: **T#** = touch-point, **BP#** = bug pattern from April 16 analysis.

### Bug patterns (from April 16 `fynChatAnalysis.md`, refs throughout)

- **BP1** — Selection / state not persisted. Bubble pick doesn't update `users.*`, downstream logic sees stale state.
- **BP2** — LLM text leak through a deterministic lane. Restricted-prompt delegated turns leak chatty model text.
- **BP3** — All-or-nothing capture. Handler rejects partial payloads; every retry re-asks for the full set.
- **BP4** — Data destination mismatch. Fyn writes column A, dashboard reads column B.

### T1 — System prompt layers (`AdvicePromptBuilder` — was `SystemPromptBuilder`)

**File**: `app/Services/AI/AdvicePromptBuilder.php` (52KB, 10 layers)

**Depends on:**
- `TaxConfigService` (tax year, bands, allowances)
- `PrerequisiteGateService` (data completeness state)
- `AdviceReviewService` (review-due detection)
- `QueryClassifier` output (classification result)
- `KycGateChecker` output (kycResult dict)
- `FinancialPlanningKnowledge` constants (knowledge domain chunks)
- `QuerySchemas` constants (per-type tool/trigger/record-type maps)

**Affects:** every advice turn. Token cost per turn. Quality of all advice-type responses.

**Change-risk:**
- Layer 1 `{$firstName}` injection — **verdict security gap** — sanitise to `[A-Za-z\s'-]` before use
- Layer 2 banned-acronym list is 1,200 characters — moving to post-hoc validator replace would save prompt tokens per turn (G8-adjacent optimisation)
- Layer 5 `<financial_context>` top-8 recommendations with decision traces can balloon on holistic queries — cap at 3 full + 5 titles
- Layer 6 `<existing_records>` has no pagination — a user with 30 investments gets all 30. Cap at 5 per type (verdict §4.7)
- Layer 9 `<kyc_status>` "BLOCKED" body is 400+ tokens — could be shorter

**Watch out for:**
- Any change to layers 4/5/6 invalidates cache (60/120s keys in `ai_financial_context_{userId}` / `ai_existing_records_{userId}`)
- Changing layer order breaks xAI prompt caching (**xAI doc: prefix must be stable**)
- BP2: adding text to any layer that the model is told not to reference may leak into output

**Tests affected**: `AdvicePromptBuilderPersonaSplitTest`, `QueryKnowledgeTest`, `KycGateCheckerTest`. **Browser tests**: all 13 matrix rows + persona-split Scenarios 1-6.

---

### T2 — Data-capture prompt (`DataCapturePromptBuilder`)

**File**: `app/Services/AI/Prompts/DataCapturePromptBuilder.php` (5.7KB, 110 lines)

**Depends on:**
- `CaptureContext` value object (reason, entity_types, fields_needed, pending_advice_question, originating_focus)
- Multi-entity rule block (line 80) — reused from `OnboardingPromptBuilder::assetCaptureInstructions`

**Affects:** every post-onboarding capture turn. Handoff quality. Multi-entity success rate.

**Change-risk:**
- BP2 — this prompt says "one-sentence acknowledgment" but B-9 shows LLM emits multi-paragraph. Either tighten or add post-stream filter (mirror FR-M14).
- Layer ordering — multi-entity rule MUST stay at top (per plan Phase B).
- Tool-list is provider-filtered in `FynPersonaInvoker::buildToolList` — changes to whitelist in `config/fyn_personas.php` ripple here.

**Watch out for:**
- W1 tech-debt — gap-fill is duplicated across 3 paths; changing this prompt without updating the other 2 paths causes drift.
- `originating_focus` is only set when orchestrator is in onboarding mode — post-onboarding this is null; prompt must not assume it.

**Tests affected:** `DataCapturePromptBuilderTest`, `AssetCaptureMultiEntityTest`, future `PersonaSplit/InlineCaptureFlowTest`. Browser: multi-entity matrix.

---

### T3 — Onboarding prompt (`OnboardingPromptBuilder`)

**File**: `app/Services/Onboarding/OnboardingPromptBuilder.php`

**Depends on:**
- Focus (journey / focus selection from `onboarding_fyn_selection`)
- User profile state (for retraction block)
- Multi-entity rule (Phase B, line 97)
- `assetCaptureInstructions` + `toolsForFocus` pairing

**Affects:** `asset_capture` states in the director. Grouped_extract states via restricted variant.

**Change-risk:**
- BP2 — off-script filter relies on character-level `?` matching; LLM bypasses by omitting `?` (22 April Test 6). Semantic filter needed (`/property|mortgage|rent|income/` per suggested fix).
- BP3 — retraction block tells LLM to emit `update_profile` / `update_record` on contradiction. Never browser-tested end-to-end (FR-M17).

**Watch out for:**
- Adding new focus-types means new case in `toolsForFocus` AND new intro in `buildAssetCaptureIntro`.
- Changing restricted prompt for grouped_extract must NOT break the "tool_choice=required" expectation.

**Tests affected:** `AssetCaptureOffScriptFilterTest`, `AssetCaptureMultiEntityTest`, future `Onboarding/RetractionTest`. Browser: Row 1 (protection), Row 6 (estate family).

---

### T4 — Tool definitions (`AiToolDefinitions` / `XaiToolDefinitions`)

**Files**:
- `app/Services/AI/AiToolDefinitions.php` (974 lines, Anthropic)
- `app/Services/AI/XaiToolDefinitions.php` (888 lines, xAI strict mode)

**Depends on:**
- `HandoffContract` constants
- Preview-mode filter via `getTools(bool $isPreviewMode)`
- `QueryClassifier` (for query-scoped tool-list building)
- `FynPersonaRegistry` (for persona-scoped tool-list building via `FynPersonaInvoker::buildToolList`)

**Affects:** every LLM turn. Multi-entity success rate. Tool selection accuracy.

**Change-risk:**
- BP3 — partial capture rules. `capture_personal_details` + `capture_spouse_details` still all-or-nothing (F6 from April 20 comprehensive check). Need same template as `capture_work_details` fix.
- Tool description drift between providers — changes need to apply to BOTH files (multi-entity Phase A kept them in sync).
- Strict mode (xAI) — nullable enums must use `anyOf` pattern, not `['string','null']` + `enum`. Breaking this passes in CI but fails at runtime.
- Adding a tool requires: (a) definition in both files, (b) handler in `CoordinatingAgent::executeTool`, (c) registry entry in `config/fyn_personas.php` if persona-split is live, (d) `PrerequisiteGateService::canExecuteTool` check.

**Watch out for:**
- T2 and T3 reference tool names by string — typos between persona registry and tool definitions silently register a non-existent tool. `FynPersonaRegistryTest` integrity test catches this.
- `create_family_member` multi-entity affordance was "separate turns" — cleaned up 22 April; don't regress.
- `create_property` has a **genuine** constraint (navigate interrupts form fill) — DON'T remove that; multi-entity Phase A narrowed it correctly.

**Tests affected:** `FynPersonaRegistryTest` (integrity check), `AssetCaptureMultiEntityTest`. Every persona-split feature test once they're written. Browser: all 14 multi-entity rows.

---

### T5 — Chat orchestration (`FynPersonaOrchestrator` + `FynPersonaInvoker`)

**Files**:
- `app/Services/AI/FynPersonaOrchestrator.php` (415 lines)
- `app/Services/AI/FynPersonaInvoker.php` (518 lines)

**Depends on:**
- `QueryClassifier` (fast-path decision)
- `FynPersonaRegistry` (persona lookup)
- `CoordinatingAgent::chatWithPromptOverride` (LLM invocation)
- `HandoffContract` (internal tool-name constants)
- `ai_conversations.persona_state` (JSON state machine)
- `CaptureContext` value object
- Multi-entity gap-fill logic (duplicated — W1 tech-debt)

**Affects:** every post-onboarding chat turn. Handoff success rate. Persona transition integrity.

**Change-risk:**
- State drift — if `persona_state.current` is left inconsistent (e.g. set to capturing but `pending_advice_question` cleared), user gets stuck. Capture-mode timeout (6 turns) is the recovery.
- Cancel-pattern regex in `config('fyn.cancel_patterns')` — changing this ripples to users mid-capture.
- Invoker's text-event buffer for data-capture turns (enables off-script filter) — breaking this would let LLM text leak.
- Handoff tool stripping — if the invoker doesn't strip `delegate_to_capture` / `capture_complete` from SSE, user sees internal tool names.

**Watch out for:**
- W1 — gap-fill logic is duplicated HERE, in `AiChatController`, AND in `OnboardingChatDirector`. Change one, change all three.
- Preview short-circuit — checked BEFORE transitioning to capturing (`FynPersonaOrchestrator.php:83`). Removing this would let preview users through.
- `FYN_CLASSIFIER_FAST_PATH=false` is the kill switch — must keep working.

**Tests affected:** `FynPersonaOrchestratorTest`, `FynPersonaInvokerTest`, future `PersonaSplit/*` 8 feature tests (all missing currently).

---

### T6 — Onboarding director (`OnboardingChatDirector`)

**File**: `app/Services/Onboarding/OnboardingChatDirector.php` (1,985 lines — **W2 tech-debt, split candidate**)

**Depends on:**
- `OnboardingStateMachine` (state definitions + transitions)
- `OnboardingValueInterpreter` (bubble matching, DOB parsing, marital parsing)
- `OnboardingPromptBuilder` (asset-capture prompt)
- `OnboardingFactExtractor` (parking)
- `HouseholdProvisioner` (spouse linking — B-2 subject)
- `SpouseLinkingService` (linked-user creation, collision detection)
- `CoordinatingAgent::chatWithPromptOverride` + `executeTool`
- Multi-entity gap-fill (duplicated — W1)

**Affects:** the ENTIRE onboarding flow. Every state emits SSE events + bubble choices + layout events.

**Change-risk:**
- **Huge file** — hard to scan for related logic. Bug-pattern cousins (BP1 in add_more, BP3 in captures) could exist elsewhere undetected.
- State machine keys are string constants — typos silently mis-transition.
- Layout events (`onboarding_layout_change`) must be emitted on EVERY pause entry/exit — missing one leaves the user on wrong route.
- Parking hydration short-circuit (Phase 11 Item 4) bypasses LLM — breaks if `OnboardingFactExtractor` regex fails silently.
- B-7 (LPA status drop), B-8 (advice bypass), B-9 (data-capture soft) are LLM-compliance issues that live partly in this director's prompt handoffs.

**Watch out for:**
- W4 — `AppLayout` remounts on route change (per REPORT-2 Part A FR-M21 underlying issue). Profile-review pause stores `preProfileRoute` on AppLayout's `data()`, which is lost on remount — B-6 is the symptom. Router push + mount-time rescue mitigates but doesn't fix.
- Three dispatch paths share duplicated gap-fill logic (W1). Director is one of them.
- W3 — unused imports + unused parameters in this file.

**Tests affected:** `OnboardingChatDirectorFixesTest`, `OnboardingStateMachineTest`, `StateMachineWalkthroughTest`, 6 future `Onboarding/*` feature tests (all missing). Browser: Tests 1-13 matrix.

---

### T7 — Vuex store (`aiChat.js`)

**File**: `resources/js/store/modules/aiChat.js`

**Depends on:**
- `aiChatService.js` (HTTP/SSE)
- `aiFormFill.js` (form queue)
- `AppLayout.vue` (reads onboardingLayout)
- `AiChatPanel.vue` (reads personaMode, messages, streamingText)
- `FynOnboardingChat.vue` (reads onboardingLayout, skipLink, quickReplies)

**State keys (current):**
- `isOpen`, `showHistory`, `conversations[]`, `currentConversation`, `messages[]`
- `streaming`, `streamingText`, `loading`, `loadingConversations`
- `error`, `tokenLimitReached`, `tokenResetAt`, `secondsUntilReset`
- `personaMode` (new — advice|capturing)
- `onboardingLayout` (new — wide|standard)
- `skipLink`, `quickReplies`, `actionBubbles` (new — via SSE events)
- `pendingNavigation`, `prefilledPrompt`, `pendingJourneyPrompt`, `abortController`

**SSE handlers:** `content`, `title`, `navigation`, `fill_form`, `entity_created`, `token_limit`, `error`, `done`, `onboarding_field_captured`, `onboarding_advance`, `onboarding_layout_change`, `persona_state_change`, `capture_complete`, `preview_cta`, `skip_link`, `quick_replies`, `action_bubbles`, `conversation_created`

**Affects:** every UI surface that renders chat.

**Change-risk:**
- Missing SSE handler = silent drop. Adding a new SSE type requires handler in 2 places: `sendMessage` action + `startOnboardingConversation` action.
- BP4 — mismatched keys between backend SSE and frontend state leak bugs (W1 gap-fill event structure needs careful coordination).
- Race guards in `aiFormFill/startFill` (see T8) depend on Vuex events firing in the right order.

**Watch out for:**
- On logout, `aiChat/reset` must clear all new state fields — forgetting one leaks preview-mode state into real-user session.
- `recentCompleteFill` flag in `aiFormFill.js` prevents cross-fill cancellation — don't remove.

**Tests affected:** Vuex unit tests (if they exist — probably don't). Browser: persona-split Scenarios, onboarding Path A + B.

---

### T8 — Form fill queue (`aiFormFill.js`)

**File**: `resources/js/store/modules/aiFormFill.js` (257 lines)

**Depends on:**
- Vuex `aiChat.js` (SSE events)
- Route navigation (`SET_PENDING_NAVIGATION` → router watcher)
- Every form component that has `isOnboarding` / `pendingFill` handling

**Affects:** every `fill_form` SSE event from any tool call. Multi-entity capture success (the queue serialises cross-tool bursts).

**Change-risk:**
- Per-fill navigation was a fix — `aiChat.js` used to clobber earlier fill routes. Don't regress.
- `recentCompleteFill` race guard (30s window) prevents form close handlers cancelling next queue item.
- `ENTITY_LABELS` map must include every new `create_*` entity type (will + LPA added on persona-split).
- Fallback timer (30s) emits a chat message and advances queue — if a form is slow to mount, user gets spurious timeout message.

**Watch out for:**
- Cross-tool multi-entity (the plan's Row 14) depends entirely on this queue handling different routes for different entity types. Verified but still has novel failure modes.
- `STEP_FIELD_MAP` tells the form which fields to pre-fill on which step — new tools with multi-step forms need entries here.

---

### T9 — App layout (`AppLayout.vue`)

**File**: `resources/js/layouts/AppLayout.vue`

**Depends on:**
- `AiChatPanel.vue` (docked aside)
- `AiChatButton.vue` (floating trigger)
- Vuex `aiChat.onboardingLayout` (for wide/standard widths)
- Vuex `aiChat.isOnboardingActive` (for blur detection)
- Vue Router (`$router.push('/profile')` on pause)
- Preserved route (`preProfileRoute`) on `data()`

**Affects:** desktop chat UX. Dashboard blur. Profile review pause.

**Change-risk:**
- **W4 architectural** — AppLayout is imported INSIDE each view. Route changes destroy + remount AppLayout, which loses `preProfileRoute` + causes chat scroll rewind (B-5 mitigated) + first-pause return nav fallback (B-6 mitigated).
- Width literals — spec went 525→896→712/356. Don't re-introduce dropped literals.
- Dashboard blur + chat-aside width are coupled — both computed from `onboardingLayout`.

**Watch out for:**
- **Proper fix is hoisting AppLayout above `<router-view>`.** That changes routing architecture — non-trivial, touches every view component. But papering over with mitigations will cost more in long-run debugging.
- Mobile viewport — wide-chat collapses to full-width; different visual treatment.

**Tests affected:** no automated tests for this component. Browser: profile-review pauses (Tests 1-13 all exercise them).

---

### T10 — AI chat controller (`AiChatController`)

**File**: `app/Http/Controllers/Api/AiChatController.php`

**Endpoints:**
- `GET /api/ai-chat/conversations` / `{id}` / `/token-usage` — read (unchanged)
- `POST /api/ai-chat/conversations` — create (unchanged)
- `DELETE /api/ai-chat/conversations/{id}` — soft delete (unchanged)
- `POST /api/ai-chat/conversations/{id}/messages` — **3-way match**: director / orchestrator / coordinatingAgent
- `POST /api/ai-chat/conversations/{id}/action` — **NEW** (FR-M18)
- `POST /api/ai-chat/onboarding/start` — **NEW**

**Depends on:**
- `OnboardingChatDirector` (when `$inOnboarding`)
- `FynPersonaOrchestrator` (when `$splitEnabled`)
- `CoordinatingAgent` (fallback)
- `PreviewWriteInterceptor` exclusions (`api/ai-chat/onboarding` in `EXCLUDED_ROUTES`)
- `config('onboarding.fyn_flow_enabled')` + `config('fyn.persona_split_enabled')`

**Affects:** the whole chat system's entry routing.

**Change-risk:**
- The 3-way `match` is deceptively simple. `$inOnboarding` check combines THREE conditions (`onboarding_completed=false`, `onboarding_fyn_step!=null`, config flag). Changing one silently changes which branch takes.
- W1 — gap-fill is duplicated HERE too. When `CoordinatingAgent::chat` is invoked (flag off), gap-fill still needs to run.

**Watch out for:**
- Adding a new endpoint means updating `PreviewWriteInterceptor::EXCLUDED_ROUTES` (if it should work for preview users) and `routes/api.php`.
- Rate limiting: `throttle:20,1` applies to all chat endpoints — long conversations can hit this.
- SSE `Connection: keep-alive` — if Apache config drops, streaming breaks silently.

---

### T11 — Persistence (models, migrations)

**Models:**
- `App\Models\AiConversation` — adds `persona_state` JSON, `onboarding_parked_facts` JSON
- `App\Models\AiMessage` — adds `persona` enum('advice','data_capture') nullable
- `App\Models\Estate\Will` — adds `residuary_beneficiary`, `guardian_for_minors`, `specific_gifts` columns (nullable)
- `App\Models\User` — adds `onboarding_fyn_step`, `onboarding_fyn_path`, `onboarding_fyn_selection`, `onboarding_fyn_context` columns
- `App\Models\Estate\LastingPowerOfAttorney` + `App\Models\Estate\LpaAttorney` — unchanged (tool handlers use them)

**Migrations (all additive, non-destructive):**
- `2026_04_21_*_add_persona_state_to_ai_conversations.php`
- `2026_04_21_*_add_onboarding_parked_facts_to_ai_conversations.php`
- `2026_04_21_*_add_persona_to_ai_messages.php`
- `2026_04_21_*_add_will_columns.php`
- (earlier onboarding migrations from `onboardingFyn` branch)

**Change-risk:**
- Enum migrations have caused past problems (memory of `AutoRiskCalculatorTest` risk_level truncation). Adding new enum values requires care.
- JSON columns — no foreign-key integrity; structure is implicit. `persona_state` has a specific shape; director + orchestrator must agree.
- Backfill — new columns are nullable for existing rows. Don't assume populated.

**Watch out for:**
- On rollback (flag off), JSON columns are still written to. Reading them requires the new columns to exist — so migrations must ship BEFORE code that writes them.
- `RefreshDatabase` in tests drops + re-migrates — always seed before browser testing (MEMORY rule).

---

### T12 — Feature flags

**File**: `config/fyn.php`, `config/onboarding.php`

- `FYN_PERSONA_SPLIT` (default false) — gates orchestrator
- `FYN_CLASSIFIER_FAST_PATH` (default true) — gates classifier fast-path within orchestrator
- `FYN_CAPTURE_MAX_TURNS` (default 6) — capture-mode timeout
- `onboarding.fyn_flow_enabled` (default true) — gates director-driven onboarding

**Affects:** every chat turn for flag decisions.

**Change-risk:**
- Flag-off path must work. `FR-S3` says rollback is a flag flip; tested by the default `CoordinatingAgent::chat` branch in the 3-way match.
- Persona-split flag-off but classifier fast-path flag on → classifier runs but orchestrator doesn't — wasted work, no harm, but log noise.

**Watch out for:**
- Feature flags are per-environment in `.env`. Dev will have `FYN_PERSONA_SPLIT=true`, production may have it off for the first few days. Different behaviour per environment makes debugging harder.

---

### T13 — Preview user safety

**File**: `app/Http/Middleware/PreviewWriteInterceptor.php`

- **Excluded routes** (preview users reach controller): `api/ai-chat/conversations`, `api/ai-chat/onboarding`, new `api/ai-chat/conversations/{id}/action`
- **Tool schema filter** (preview-mode filter in `AiToolDefinitions::getTools(bool $isPreviewMode)`): drops all write tools
- **Orchestrator short-circuit** (`FynPersonaOrchestrator`): emits `preview_cta` before invoking data_capture
- **Advice prompt layer**: `<preview_mode>` instruction tells advice NOT to emit `delegate_to_capture`

**Affects:** preview-user chat experience. Onboarding demo data preservation.

**Change-risk:**
- Three layers of defence. Removing any one creates a gap.
- Adding a new write tool means adding it to the `getTools(true)` filter in BOTH tool definition files.
- Adding a new route that preview users can reach means adding to `EXCLUDED_ROUTES`.

---

### T14 — The multi-entity gap-fill itself (W1 duplication hotspot)

**Location**: duplicated inline in:
1. `AiChatController::sendMessage` / `CoordinatingAgent::chat` path
2. `FynPersonaInvoker::invoke`
3. `OnboardingChatDirector::handleAssetCaptureTurn` (and related)

**Depends on:**
- LLM's acknowledgment text (regex scan for entity names + values)
- `CoordinatingAgent::executeTool` (to emit missing tool calls)
- `AiToolDefinitions` / `XaiToolDefinitions` (to know what entity types map to what tools)

**Affects:** multi-entity capture success rate across ALL paths.

**Change-risk:**
- **Fragility from regex parsing** — model drift (LLM phrasing changes between versions or with prompt tweaks) breaks the gap-fill silently.
- **Three copies** — any fix/bugfix must apply to all three. Easy to miss one. Tech debt W1 demands extraction.
- Acknowledgment patterns are entity-specific — `"Added Aviva life £300,000"` vs `"Created a savings account at Halifax with £10k"` need different regex. Extension to new entities is non-trivial.

**Watch out for:**
- If the LLM emits `capture_complete` before gap-fill runs, the summary won't include the gap-filled records. Orchestrator should run gap-fill BEFORE persisting `capture_complete`.

**Tests affected:** `AssetCaptureMultiEntityTest` (regression guard for Protection), future multi-entity matrix tests for Rows 2-14.

---

### T16 — Consent service interaction (NEW — enterprise-verdict cross-doc reloop)

**File**: `app/Services/GDPR/ConsentService.php`

**Depends on:**
- `UserConsent` model + `user_consents` DB table
- `UserConsent::CURRENT_VERSIONS` constant — version-per-consent-type

**Affects every chat request that enters `AiChatController::sendMessage`.** Currently NOT called from the controller (enterprise-verdict C5).

**Change-risk:**
- If you add a consent check, you must handle "user has no consent record" vs "user withdrew consent" vs "user's consent is at an older version" — each needs a user-visible branch.
- If you add a new consent type (e.g. `ai_processing_extended` for special category data flowing to LLM), every existing user is "unknown" and needs a consent-capture interstitial.

**Watch out for:**
- Onboarding flow may need a consent bump before the first Fyn turn.
- Preview users — do they need to accept consent? (Probably yes, because their chat still goes to third-party LLMs.)

---

### T17 — Audit log integrity (NEW — enterprise-verdict cross-doc reloop)

**Files**:
- `app/Models/AuditLog.php` + `audit_logs` table (exists)
- `app/Agents/CoordinatingAgent.php:705` — the `[AI-AUDIT]` channel-single write
- `app/Models/AiMessage.php` — post-insert mutability
- `app/Models/AiAdviceLog.php` — same

**Depends on:**
- `Log::channel('single')` — plain file
- MySQL tables (mutable)

**Change-risk (enterprise-verdict C7 + new C10):**
- **Write-tool-only audit**: adding or renaming a read tool means the audit expands or contracts in the same change — watch for scope drift.
- **Moving audit to an append-only store**: requires migration of existing `ai_messages` + `AiAdviceLog` data, or a two-store approach (legacy + new).
- **Hash-chain implementation**: if you add `prev_hash` column, every new row depends on prior row's hash — a single corrupted row breaks the chain. Need recovery tooling.

**Watch out for:**
- GDPR erasure request: if audit log is immutable, you cannot delete an individual user's audit rows. Solution: pseudonymise (replace user_id with hash) on erasure, keep the integrity chain.
- Retention: 7 years for GDPR events, 7 years for regulated advice. But: the average user's 7 years of chat × daily usage = gigabytes. Storage + search cost matters.

---

### T18 — Privacy Policy ↔ code alignment (NEW)

**Files**:
- `resources/js/views/Public/PrivacyPolicyPage.vue` (319 lines)
- `resources/js/views/Public/TermsOfServicePage.vue` (396 lines)
- Any file naming a third-party processor in config (`config/services.php`)

**Depends on:** every external API call your code makes.

**Change-risk:**
- **Adding a new third-party processor** (e.g. a new LLM provider, a new email vendor) requires BOTH the code change AND Privacy Policy update — shipping one without the other is Article 13 exposure.
- **Removing a processor** doesn't require policy removal (you can still have a policy entry that references a decommissioned processor for historical data), but clarity is better.
- Changes to `config/services.php` should trigger a Privacy Policy review checklist.

**Watch out for:**
- xAI is currently NOT in the Privacy Policy despite being used as an active chat provider. This is enterprise-verdict C1 — Sprint 0.4.
- Document processing uses a SEPARATE Anthropic integration (per policy §3h) — check whether the policy's Anthropic disclosure covers BOTH chat and document extraction.

---

### T19 — Special category data flow (NEW)

**Fields**: `users.health_status`, `users.smoking_status`, `protection_profiles.health_status`

**Depends on:**
- `ProtectionPlanService.php`, `RetirementActionDefinitionService.php`, `DecumulationPlanner.php`, `LifeStageService.php` — all read these fields
- `orchestrateAnalysis` output (fed to `SystemPromptBuilder` layer 5)

**Affects:** whether special category data (UK GDPR Article 9) flows to third-country LLM processors.

**Change-risk (enterprise-verdict C6):**
- Adding a field that derives from health_status to any module's analysis output — if that output flows to layer 5, health-data effectively leaves the UK/EEA.
- Privacy Policy §5 currently says "We do not share health data with any third party" — **code and policy may be out of sync TODAY**.

**Watch out for:**
- Retirement projections use life-expectancy adjustments based on smoking status. If the adjusted figure is in the prompt, the smoking-status information is implicitly disclosed.
- Protection analysis returns gap analysis based on health status. Same concern.

**Remediation:** audit `orchestrateAnalysis` output path. Either (a) strip derived fields before prompt assembly, (b) capture explicit specific consent and update policy, or (c) process special-category data server-side only and send only sanitised outputs to LLMs.

---

### T20 — Provider failover (NEW)

**Files**:
- `config/services.php` — dual-provider config
- `app/Services/AI/XaiClient.php` + `Anthropic\Client` singleton
- `app/Traits/HasAiGuardrails.php::getAiProvider` — cache-backed selection

**Depends on:** vendor availability. Currently no failover — if xAI returns 5xx, chat errors.

**Change-risk:**
- Adding retry-then-failover logic means the response metadata needs to track which provider actually served the turn (for billing / audit).
- Failover mid-conversation: if turn N goes to xAI and turn N+1 falls over to Anthropic, the conversation history + `x-grok-conv-id` cache effectively resets.

**Watch out for:**
- Cost: falling over to Anthropic (higher cost) when xAI is degraded could blow budget.
- Compliance: user's data now goes to a provider the user may not have been told about in a specific chat context (though both are in the DPA register, ideally).

---

### T15 — Eval harness (currently missing — verdict G1)

**Location:** will be `tests/Eval/FynEvalTest.php` + seed dataset (new)

**Would depend on:**
- Mocked LLM (or real xAI call with `RefreshDatabase`)
- Golden conversations in `tests/Eval/fixtures/`
- Assertions: classification match, tools called match, `£` amount present, no banned acronyms, response length within bounds, navigation target correct

**Would affect:** CI pipeline (adds runtime). Every prompt/tool/model change becomes measurable.

**Change-risk if built:**
- Seed dataset needs to cover all 22 query types × 5 preview personas (minimum) = 110 golden conversations. Generating + curating this is the hard bit.
- Mocked LLM test ≠ real LLM behaviour. Needs periodic re-run against live provider to detect drift.

**Watch out for:**
- Adding to CI runtime — keep it under 5 minutes total.
- Flaky tests from LLM stochasticity if live — use mocks by default, live only on nightly.

---

### Cross-subsystem dependency matrix

Quick lookup: "I'm changing X, which touch-points are at risk?"

| Change | Touches directly | Ripple risks |
|---|---|---|
| Advice prompt layer (any of 10) | T1 | Cache invalidation (xAI prefix stability), cost, quality regression, possible BP2 leak — run T15 eval + multi-entity browser matrix |
| Data-capture prompt | T2 | Multi-entity success (T14), BP2 leak risk, handoff-back timing |
| Onboarding prompt | T3 | Asset-capture off-script (BP2), retraction (FR-M17 untested), gap-fill (T14) |
| Any tool definition | T4 | Registry integrity (T5 config), persona allow-list (T5), provider sync (both files), gap-fill (T14) needs entity-type awareness |
| New tool | T4 + T11 (handler) + T5 (registry) + T13 (preview filter) + T7/T8 (frontend queue entity label) | Update `PrerequisiteGateService::canExecuteTool` + navigation allow-list in `navigate_to_page` if there's a corresponding page |
| Orchestrator logic | T5 | Handoff contract (T4), state machine in `ai_conversations.persona_state`, preview short-circuit (T13) |
| Director state machine | T6 | Layout events (T9), parking hydration (T11 JSON), Tool handlers (T4), BP1 cousin audit |
| Vuex SSE handler | T7 | Every UI surface (T9/T10/related components), aiFormFill queue (T8) |
| Form fill queue | T8 | Multi-entity cross-tool success, route navigation races, timeout message spam |
| AppLayout | T9 | B-5/B-6 mitigation integrity, every view via imports, onboarding wide/standard widths |
| Controller route | T10 | Preview exclusions (T13), flag logic (T12), 3-way match correctness |
| Migration | T11 | Model fillable, feature flag defaults, rollback compat |
| Feature flag default | T12 | Rollback path (FR-S3), dev vs prod behaviour gap |
| Preview filter | T13 | All three defence layers — remove one = gap |
| Multi-entity gap-fill | T14 | T2/T3 (prompt coupling), T5 (invoker), T6 (director) — **W1 demands extraction** |
| ConsentService integration (NEW) | T16 | T10 controller route (needs check), T13 preview filter, registration flow |
| Audit log write | T17 | T10 controller, T5 orchestrator, T6 director — every AI write path writes to audit |
| Any new third-party processor (NEW) | T18 | Privacy Policy + ToS + DPA register + `config/services.php` + runtime check |
| Change to health data usage (NEW) | T19 | Every module service consuming health_status, layer 5 prompt assembly, privacy policy §5 |
| Provider selection logic (NEW) | T20 | T5 invoker, cost tracking, audit metadata, failover behaviour |
| Document extraction pipeline (NEW — T21 after Loop 3) | T21 | `AIExtractionService` + its 6 prompts, `DocumentProcessor`, provider selection, file handling, Privacy Policy §8, Article 9 health-in-documents |
| Python agent sidecar (NEW — T22 after Loop 3) | T22 | `scripts/fynla_agent/`, `AgentInternalController`, `AGENT_INTERNAL_TOKEN`, external invocation context |
| AgentInternalController endpoints (NEW — T23 after Loop 3) | T23 | 6 endpoints, shared-secret auth, `user_id` impersonation by query param, no audit |
| Third-party analytics (NEW — T24 after Loop 3) | T24 | `analyticsService.js` (Plausible), Privacy Policy contradiction, consent banner, event content |
| Push notifications (NEW — T25 after Loop 3) | T25 | `PushNotificationService` (FCM/Google), Privacy Policy disclosure, daily-insight content |

### T21 — Document extraction pipeline

**Files**:
- `app/Services/Documents/AIExtractionService.php` (965 lines)
- `app/Services/Documents/DocumentProcessor.php`
- `app/Http/Controllers/Api/DocumentController.php`
- `app/Services/Documents/FieldMappers/*`

**Depends on:**
- Admin `ai_provider` cache
- Hardcoded `claude-3-5-haiku-20241022` constant on Anthropic path
- xAI `vision_model` config on xAI path
- `DocumentExtractionLog` for audit

**Affects:** every user document upload.

**Change-risk:**
- Privacy Policy disclosure must match processor routing — changing provider code changes what must be disclosed
- Model upgrade changes extraction behaviour — needs regression against a corpus of user documents
- Prompt modifications change what the model extracts — needs prompt-versioning + testing
- Adding new document type (e.g. estate documents) means new prompt + new field mapper + privacy-impact check

**Watch out for:**
- Raw user documents contain arbitrary PII including health declarations (pension scheme docs often do)
- No post-extraction sanitiser like `StructuredResponseValidator`
- Adversarial PDF/image attacks against vision model

---

### T22 — Python Agent Sidecar

**Files**: `scripts/fynla_agent/` + `scripts/run_agent.py`

**Depends on:**
- `AGENT_INTERNAL_TOKEN` shared secret
- Anthropic Python SDK
- Laravel's `AgentInternalController` endpoints

**Affects:** task types `holistic_plan`, `scenario`, `deep_recommendations`.

**Change-risk:**
- Invocation model is CLI with JSON input including API key — any change needs to move key to env or secure store
- Pydantic schemas — changes affect caller's parsing expectations
- Anthropic-only — adding xAI support requires parallel Python client path

**Watch out for:**
- If Python agent is invoked from scripts/cron NOT in the Laravel repo, changes here may not be visible to non-Python engineers
- `hooks.py` fails-open — removing this fallback risks breaking the agent when Laravel is slow

---

### T23 — AgentInternalController

**File**: `app/Http/Controllers/Api/AgentInternalController.php` (282 lines)

**Endpoints**: 6 (see system-map §24.3)

**Depends on:**
- `AgentTokenAuth` middleware
- Module agents (`ProtectionAgent`, `SavingsAgent`, etc.)
- `TaxConfigService`
- `PrerequisiteGateService`
- `CoordinatingAgent::orchestrateAnalysis`

**Affects:** Python agent sidecar callbacks; any internal process with the shared secret.

**Change-risk:**
- Adding a new endpoint extends the shared-secret's trust boundary
- Changing the auth model (e.g. token list, IP allow-list) requires coordination with Python code
- User-id handling — any new endpoint should consider whether it's appropriate to accept user_id as param

**Watch out for:**
- No rate limiting — aggressive callers can exhaust worker capacity
- No audit logging per endpoint — SAR responses would miss these data accesses

---

### T24 — Third-party analytics (Plausible)

**File**: `resources/js/services/analyticsService.js`

**Depends on:**
- Window-level `plausible()` global (loaded by `resources/views/app.blade.php` or similar)
- `VITE_PLAUSIBLE_DOMAIN` env var

**Affects:**
- Every chat open → `chat_opened` event
- Every message send → `chat_message_sent` event (includes message length)
- Every page view
- Every module visit
- Device info on first pageview

**Change-risk:**
- Privacy Policy contradicts current use — any fix requires either removing Plausible OR updating policy
- Adding new event types increases the surface to disclose
- Consent model — GDPR Recital 30 + Article 4(4) debate around whether analytics needs consent banner (for Plausible which doesn't use cookies, the answer is less clear — but needs formal analysis)

**Watch out for:**
- Preview users fire analytics events — is that intended?
- Event content — `message_length` is a side-channel about user behaviour

---

### T25 — Push notifications (FCM)

**File**: `app/Services/Mobile/PushNotificationService.php`

**Depends on:**
- `fcm.server_key` config
- Google FCM endpoint (`https://fcm.googleapis.com/fcm/send`)
- `NotificationPreference` model (user opt-in)
- `DeviceToken` model

**Affects:** daily insight push + any other push type added.

**Change-risk:**
- Undisclosed third party — any push feature expansion requires Privacy Policy update
- FCM payload includes user first name — more PII = more disclosure need
- Opt-in management — revoking consent via `NotificationPreference` must stop pushes immediately

**Watch out for:**
- FCM payload bytes transit via Google → user device; no UK-residency guarantee for push content
- If notification body content ever becomes LLM-generated (currently static), this becomes a fourth AI surface

---

## 8. Unified remedial roadmap

Prioritised single list. Merges: verdict sprints (where they haven't been addressed on the branch), persona-split completion, multi-entity matrix, the B-X bugs, architectural debt, eval harness.

### Sprint 0 — Prep + enterprise criticals (1–2 days — REVISED 24 April after enterprise-verdict)

**Before anything else** — technical prep AND the enterprise criticals from `enterprise-verdict.md` Part H. Sprint 0 is no longer ½ day.

| # | Task | Effort | Outcome |
|---|---|---|---|
| 0.1 | **Rebase `feature/fyn-persona-split` onto current `main`** | 2–4 hrs incl. conflict resolution | Feature branch up-to-date with 72 commits of main drift. Expect conflicts in `AppLayout.vue`, `CoordinatingAgent.php`, `router/index.js` (session 66/67 UI bundle overlap), possibly lifecycle files. |
| 0.2 | Full Pest run after rebase | 30 min | Confirm 2,448 tests still pass post-rebase. |
| 0.3 | Close **PR #214** (`onboardingFyn`) as superseded | 5 min | Remove branch from GitHub after confirming persona-split has all its commits. |
| **0.4** | **[Enterprise C1] Add xAI to Privacy Policy** §7 and §8 | 1 day (includes legal review) | Third-party processor disclosure complete. Stops Article 13(1)(e) exposure. |
| **0.5** | **[Enterprise C3] Tighten `update_record` per-entity field whitelist** | 1 day | Replace 2-field blocklist with per-entity allowlist. Block `Trust.settlor`, `Mortgage.start_date`, `FamilyMember.relationship` from LLM writes. |
| **0.6** | **[Enterprise C4] Add `delete_record` confirmation pattern** | 4 hrs | Two-call confirm: first returns preview, second with `confirmed:true` deletes. |
| **0.7** | **[Enterprise C5] Add `ConsentService::hasConsent` runtime check in `AiChatController::sendMessage`** | 2 hrs | Return 403 with CTA if user has withdrawn data-processing consent. |
| **0.8** | **[Enterprise H1] Sanitise user-controlled prompt fields** — `first_name`, `surname`, `employer`, `occupation`, `family_member` names, `goal` names | 4 hrs | Strip to `[A-Za-z0-9\s'.-]` before interpolation into layer 1/4/5/6 prompt text. |
| **0.9** | **[Enterprise H14] Investigate Python agent sidecar** (`AgentTokenAuth`, `/api/internal/agent/*` routes) | 4 hrs | Confirm scope: legacy / dev-only / in-use. If in-use, commission separate map + enterprise verdict. If unused, remove routes + middleware. |

**Sprint 0 gate**: 0.1-0.3 unblock all downstream dev work. 0.4-0.9 unblock commercial use / wider rollout. Tasks 0.4-0.9 can run in parallel once 0.1 completes; they do NOT depend on each other.

**Sprint 0 additional — Loop 3 enterprise findings (24 April Part K):**

| # | Task | Effort | Outcome |
|---|---|---|---|
| **0.10** | **[Enterprise C11–C13] Add xAI + Plausible + FCM to Privacy Policy §7 / §8** — or cease using each until added | 1 day (incl legal review) | Policy covers all actually-used processors |
| **0.11** | **[Enterprise C14] Audit `orchestrateAnalysis` for health-data flow** — trace every field in layer 5 back to source; strip health-derived OR update Privacy Policy §5 | 1 day | Article 9 compliance restored |
| **0.12** | **[Enterprise H16 + H18] Resolve Python Agent Sidecar status** — confirm active / dead; if active, document invocation + move API-key-via-argv to env-var-based injection; if dead, remove routes + middleware + scripts | 1 day | Third AI surface either audited or eliminated |
| **0.13** | **[Enterprise M41] Remove stale OpenAI config block** from `config/services.php` | 30 min | Reduced attack surface |
| **0.14** | **[Enterprise M42] Decide document extraction model strategy** — pin `claude-3-5-haiku-20241022` explicitly OR upgrade to match chat model | 2 hrs | Behavioural consistency + documented rationale |
| **0.15** | **[Enterprise C10 + H15] Add audit log coverage for AgentInternalController + read tools** — `[AI-AUDIT-INTERNAL]` log channel per endpoint | 4 hrs | Every AI data access auditable for SAR response |

**Sprint 0 revised priorities after CSJ direction (24 April Part L):** CSJ has explicitly de-prioritised Privacy Policy updates in favour of getting persona-split shipped. Tasks 0.4 and 0.10-0.14 move to Sprint 4. Sprint 0 now focuses on the technical blockers + Q1/Q8/Q9 resolutions:

| # | Task | Effort | Source |
|---|---|---|---|
| **0.16** | **[Part L Q1] Remove Python Agent SDK sidecar** — delete `scripts/fynla_agent/`, `scripts/run_agent.py`, `scripts/requirements.txt`, `app/Http/Controllers/Api/AgentInternalController.php`, `app/Http/Middleware/AgentTokenAuth.php`, `/api/internal/agent/*` routes, `AGENT_INTERNAL_TOKEN` env + config. Unless CSJ confirms an external caller | 1 hr | Reduces attack surface; no SDK benefit over what PHP chat already has |
| **0.17** | **[Part L Q8] Remove stale OpenAI config block** from `config/services.php` + `.env.example` | 5 min | Cleanup |
| **0.18** | **[Part L Q9] Begin AI DB audit migration** — create `ai_tool_executions` table + migrate `[AI-AUDIT]` file log writes in `CoordinatingAgent::executeTool` to DB inserts + add `operation: read|write` column. Read-tool extension can land later but write-tool DB log is Sprint 0 | 1 day | Aligns with CSJ's stated intent to log all AI to DB per user |

Tasks 0.4 / 0.10 / 0.11 / 0.13 / 0.14 / 0.15 move to Sprint 4 (commercial-readiness).

### Sprint 1 — Quick wins + measurement (1 week)

Both verdict Sprint 1 AND persona-split completion prep.

| # | Task | Effort | Source | Dependencies |
|---|---|---|---|---|
| 1.1 | **Temperature drop** — xAI 0.7→0.3, Anthropic pass explicit 0.3 | 10 min | Verdict G6 | None |
| 1.2 | **Anthropic cache metrics** — persist `cache_creation_input_tokens`, `cache_read_input_tokens` in `ai_messages.metadata` | 1 hr | Verdict G10 | None |
| 1.3 | **`reasoning_tokens` tracking** — extract from xAI usage response, add to `metadata` + token budget accounting | 30 min | Verdict G11 | None |
| 1.4 | **Sanitise-after-validate order** — validate raw response first, then sanitise for display | 10 min | Verdict G15 | None |
| 1.5 | **First-name sanitisation** — strip to `[A-Za-z\s'-]` before layer 1 injection | 30 min | Verdict security | None |
| 1.6 | **Delete dead `advanced_chat_model` branch** OR repoint to Sonnet 4.6 for Pro+complex (A/B) | 30 min–1 day | Verdict G3 revised | Measurement decision |
| 1.7 | **Eval harness MVP** — `tests/Eval/FynEvalTest.php` + 30 seed conversations covering 22 query types × 3 personas (not 5 — keep tight) + assertions (classification, tools, £ amount, banned acronyms, length) | 3 days | Verdict G1 | None |
| 1.8 | **Log-aggregation for PRD KPIs** — structured logs in `FynPersonaInvoker::invoke`, `FynPersonaOrchestrator::dispatch` for token-count, persona, tool-count, classifier-decision, handoff-transition | 1 day | PRD §3 KPI measurement | None |
| 1.9 | **Weekly drift audit artisan command** (FR-S2 from PRD, still unbuilt) | 1 day | PRD FR-S2 | 1.7 eval harness |

Sprint 1 outcome: Branch is in sync with main. Instrumentation ready. Quick verdict wins landed. Eval harness can score anything we change from here on.

### Sprint 2 — Close persona-split gaps (1–1.5 weeks)

| # | Task | Effort | Source | Dependencies |
|---|---|---|---|---|
| 2.1 | **Fix B-2 spouse household_id** — verify `HouseholdProvisioner` wiring, add `Onboarding/SpouseSkipTest` + household-linking unit test | 1 day | B-2 | None |
| 2.2 | **Fix B-3 family_member.age** — compute from DOB at save time | 2 hrs | B-3 | None |
| 2.3 | **Fix B-4 children DOB → YYYY-01-01** — use today as month-day default, or prompt confirmation at profile-review pause | 4 hrs | B-4 | None |
| 2.4 | **Fix B-7 LPA status drop** — tool description tightening + handler validates and preserves user-stated status | 2 hrs | B-7 | None |
| 2.5 | **Fix B-8 advice bypasses delegate** — advice-persona prompt tightening; forbid navigate_to_page + fill_form for write-intent messages | 4 hrs | B-8 | None |
| 2.6 | **Fix B-9 data-capture prompt enforcement** — either tighten one-sentence rule OR add post-stream filter mirroring FR-M14 | 4 hrs | B-9 | None |
| 2.7 | **Back-fill 11 missing Feature tests** (§3.4) | 3 days | REPORT-2 | Sprint 0 done |
| 2.8 | **Complete 13-test browser matrix** — Tests 1 (resume), 2-13 (focus variants, journey variants, cross-tool, multi-entity per module) | 2 days | 23 April handover | 2.7 helpful but not blocker |
| 2.9 | **Partial capture on `capture_personal_details` + `capture_spouse_details`** — apply the same partial-capture fix that landed for `capture_work_details` in April 20. F6 from April 20 comprehensive check. | 1 day | Comprehensive check F6 | None |
| 2.10 | **Run verdict G8** — add example usage + boundary notes to remaining tool descriptions (especially `create_asset`/`create_chattel`/`create_business_interest` boundaries, all `update_*` tools) | 1 day | Verdict G8 | None |

Sprint 2 outcome: All known bugs fixed. Test coverage up to spec. All 13 browser matrix rows PASS with verbatim transcripts.

### Sprint 3 — Ship to dev + structural fixes (1 week)

**Sprint 3 deploy gate (added 24 April post-enterprise-verdict)**: deploy to dev is only authorised once Sprint 0 tasks 0.4–0.9 AND Sprint 1 evals are complete. No user-facing AI chat deployment without Privacy Policy updated for xAI, `update_record` whitelist active, `delete_record` confirmed, consent check enforced, and user-controlled-field sanitisation applied.

| # | Task | Effort | Source | Dependencies |
|---|---|---|---|---|
| 3.1 | **Extract `MultiEntityGapFiller`** — single service consumed by all 3 dispatch paths. 3 unit tests + integration test | 1.5 days | W1 tech debt | None |
| 3.2 | **Ship to dev (`csjones.co/fynla`)** — deploy guide mirrors previous patterns | 0.5 day | — | Sprints 0-2 done + Sprint 0 enterprise criticals complete |
| 3.3 | **Smoke test on dev** (flag on + flag off paths) | 1 day | — | 3.2 |
| 3.4 | **Verdict G4 — native tool-use history** — rebuild `HasAiChat::buildMessageHistory` with proper `tool_use`/`tool_result` turns instead of `[Context: ...]` fold-in | 2–3 days | Verdict G4 + enterprise data minimisation | None |
| 3.5 | **Verdict G2 — Evaluator-optimiser loop** — on high/critical validator violation, regenerate once with corrective system message | 1 day | Verdict G2 | 1.7 eval harness helpful |
| 3.6 | **Verdict G5 — LLM fallback classifier** — when regex returns `general` and no route match, call small LLM classifier, cache 24h keyed on message hash | 1.5 days | Verdict G5 + enterprise SM-9 (consent bypass risk) | None |
| **3.7** | **[Enterprise C10] Extend `[AI-AUDIT]` to read tools** — `get_module_analysis`, `list_records`, `get_tax_information` | 4 hrs | enterprise-verdict Part J | None |
| **3.8** | **[Enterprise M38] Verify SSE + Apache** — `.htaccess` `mod_deflate` excludes `text/event-stream`; if not, add `SetEnvIfNoCase Content-Type "text/event-stream" no-gzip` | 2 hrs | enterprise-verdict SM-3 | None |
| **3.9** | **[Enterprise M39] Fix `getTodayTokenUsage` over-counting** — count today's increment only, not full totals of conversations updated today | 4 hrs | enterprise-verdict SM-6 | None |

Sprint 3 outcome: On dev, tested, with the highest-ROI verdict improvements layered in. Compliance criticals closed. `dev → main` PR can be opened.

### Sprint 4 — Production hardening + enterprise infrastructure (2 weeks — REVISED 24 April)

Sprint 4 now includes enterprise hardening items flagged in `enterprise-verdict.md` Part F. Dev→prod only happens once enterprise-verdict Criticals C7 (tamper-evident audit), C8 (DPIA), C2 (FCA analysis) are addressed.

| # | Task | Effort | Source | Dependencies |
|---|---|---|---|---|
| 4.1 | **Dev → main PR + production deploy** | 1 day | — | Sprint 3 green + Sprint 4 criticals below done |
| 4.2 | **Flag on in production** (staged, monitor 24h for orchestrator warnings) | 0.5 day | PRD §11 | 4.1 |
| 4.3 | **`AppLayout` hoist above `<router-view>`** — fixes W4 + B-5/B-6 root cause | 2–3 days | REPORT-2 | Production stable |
| 4.4 | **Verdict G9 — structured output for recommendations** — JSON schema for advice responses; UI renders record cards instead of Markdown parsing | 3 days | Verdict G9 | — |
| 4.5 | **Verdict G7 — parallel tool execution** (only if measured benefit on holistic queries) | 2 days if done | Verdict G7 | Measurement first |
| 4.6 | **Verdict G12 — reasoning summary streaming UX** | 1 day | Verdict G12 | — |
| 4.7 | **Verdict G14 — conversation summary memory** for long sessions | 2 days | Verdict G14 | — |
| 4.8 | **Verdict G13 — `MAX_TOOL_CALLS_PER_TURN` lift to 8 for holistic + per-tool-type budgets** | 1 hr | Verdict G13 | — |
| 4.9 | **Verdict G25 — thumbs up/down feedback** | 1 day | Verdict G25 | — |
| **4.10** | **[Enterprise C7] Tamper-evident audit store** — append-only table with `prev_hash` column OR WORM external store; backfill existing `ai_messages` + `AiAdviceLog` if feasible | 2–3 days | enterprise-verdict C7 | Legal input on retention |
| **4.11** | **[Enterprise C8] DPIA for AI chat feature** — produce doc per UK GDPR Article 35, publish in vault, review cadence | 1 day (+ legal) | enterprise-verdict C8 | — |
| **4.12** | **[Enterprise C2] FCA regulatory analysis** — commission formal legal opinion (advice vs guidance, Consumer Duty mapping, COBS applicability); document outcome; update prompt language if needed | External engagement | enterprise-verdict C2 | Legal firm engagement |
| **4.13** | **[Enterprise C6] Audit `orchestrateAnalysis` for health-derived fields in prompt path** — either strip or capture explicit specific consent | 1 day | enterprise-verdict C6 | — |
| **4.14** | **[Enterprise H2] Vendor DPA register in vault** — Anthropic + xAI + SiteGround + mail.fynla.org DPAs; include copies or references | 4 hrs | enterprise-verdict H2 | — |
| **4.15** | **[Enterprise H3] Provider failover** — if xAI returns 5xx or times out 3× in 5 min, fall back to Anthropic for next turns. Feature-flag gated. Log provenance per turn. | 2 days | enterprise-verdict H3 | — |
| **4.16** | **[Enterprise H7] Incident response plan + ICO 72h breach-notification procedure** | 1 day | enterprise-verdict H7 | — |
| **4.17** | **[Enterprise H8] Cost circuit breaker** — org-level daily £ budget, anomaly detection, Slack alert threshold | 4 hrs | enterprise-verdict H8 | — |
| **4.18** | **[Enterprise C9] Sentry (or equivalent) for `Log::error` + 5xx + structured `[AI-AUDIT]` events** | 1 day | enterprise-verdict C9 | — |
| **4.19** | **[Enterprise H6] Patch 6 high-severity npm vulns** (vite 8 + `@capacitor/cli` 8 upgrades) | 2–4 hrs window | enterprise-verdict H6 | Full regression afterward |
| **4.20** | **[Enterprise M37] `FynInsightCard` copy rewrite** — don't imply AI personalisation for deterministic rotation (Consumer Duty "clear, fair, not misleading") | 2 hrs | enterprise-verdict SM-2 / M37 | Marketing sign-off |
| **4.21** | **[Enterprise M40] LLM-titled or scrubbed conversation titles** — stop storing raw user input as title (privacy) | 2 hrs | enterprise-verdict SM-10 / M40 | — |
| **4.22** | **[Part L] Privacy Policy update for Fyn AI processors** — add xAI (chat + document extraction). Reconcile §5 (health data) with actual AI prompt flow. Scope is Fyn-AI-specific only; app-wide Privacy Policy concerns (Plausible, Meta Pixel, AWIN, FCM) are tracked in a separate app-wide audit — NOT this plan. | 1 day incl legal | enterprise-verdict C1, C14 |
| **4.25** | **[Part L Q3] Full health-data trace through `orchestrateAnalysis`** — walk every numerical field in layer 5 back to source, document which fields are influenced by `health_status`/`smoking_status`, decide per-field: strip or disclose | 1 day | enterprise-verdict C14 |
| **4.28** | **[Part L Q9 extension] Complete AI DB audit migration** — add read-tool logging to the `ai_tool_executions` table (started in Sprint 0.18). Also migrate admin provider-switch logs from `Log::info` to `audit_logs` table (event_type='admin') | 1 day | enterprise-verdict C10 |

**Scope note (Part M 24 April):** Tasks 4.23, 4.24, 4.26, 4.27 from an earlier draft have been **removed** from this plan because they are app-wide concerns (Meta Pixel, AWIN, Plausible general, Google Firebase DPA), not Fyn-AI-specific. If CSJ wants those addressed, they belong in a separate app-wide compliance audit.

Sprint 4 outcome: Production stability. Architectural debts closed. Full verdict catchup. **Commercial-regulated readiness contingent on C2 legal opinion outcome.**

### Sprint 5 — Cleanup + polish (ongoing)

| # | Task | Effort |
|---|---|---|
| 5.1 | Extract `OnboardingCaptureAckBuilder` + `OnboardingGapFiller` from director (W2, 1,985 lines) | 2 days |
| 5.2 | `CoordinatingAgent` split per verdict R1 | 3–5 days |
| 5.3 | Admin UI for `AiAuditController` (G20) | 1 day |
| 5.4 | Mobile wide-chat + blur parity (FR-N2) | 3 days |
| 5.5 | Advisor persona as third persona (future release — unblocked by persona-split) | 2 weeks |
| 5.6 | Structural refactor of `HasAiChat::chat()` per verdict R2 | 2 days |
| 5.7 | Extract tool schemas to per-tool files per verdict R3 | 2 days |
| 5.8 | `StaticFynChat` unification (G23), `FynInsightCard`/`MobileFynCard` branding (G24), remove unused `ai_chat_enabled` column (G18), LLM-titled conversations (G19), preview budget cap (G26) | Nit cleanup 1–2 days |

---

## 9. Open questions before spec / plan / PRD rewrite

These are what I need from you before I can write the next-level docs.

### 9.1 Strategy

1. **Merge order confirmation** — `feature/fyn-persona-split` → rebase onto main → onboarding + persona-split ship together as one PR to dev, then separate PR dev→main? Or stage: orchestration first, multi-entity fix as follow-up? The recommendation is single PR because the branches are entangled.
2. **Eval harness scope** — is 30 golden conversations enough for MVP, or do you want 100+ from day one? Larger dataset = slower CI, but better regression detection. My default is 30 to start, grow as we learn what breaks.
3. **Temperature decision** — I recommend 0.3 for both xAI and Anthropic (verdict G6). Are you comfortable with that, or do you want a UI preview-persona variant at different temps for comparison?
4. **Evaluator-optimiser scope** — regenerate on high/critical violation only, or also on medium? Cost is ~1% extra LLM calls for high only; scaling to medium doubles that.
5. **LLM fallback classifier** — fire only when regex returns `general`, or also as a second-opinion check when regex is confident? First is cheap; second catches misclassifications but doubles classifier cost.

### 9.2 Scope

6. **Mobile parity** — PRD §7 explicitly defers mobile wide-chat + blur. Keep deferred, or pull into Sprint 5?
7. **Structured output for recommendations (G9)** — big UX change (Markdown → record cards). Do you want this in Sprint 4 or deferred further?
8. **Admin audit UI (G20)** — do you want a polished admin view for `AiAuditController` endpoints, or leave as "API exists, wire up later"?
9. **`AppLayout` hoist (W4)** — architectural refactor, touches every view component. Sprint 4 or deferred?
10. **Advisor persona (future)** — is that still a Q3 priority, or has the business case shifted?

### 9.3 Verdict-specific

11. **Historical conversation history rewrite (G4)** — changing `buildMessageHistory` to native tool-use format requires backfilling existing conversations OR decision to leave historical messages with the old text-fold. Your call.
12. **Structured recommendations format (G9)** — JSON schema design: freeform or one of the specific Anthropic/xAI schemas? Personally leaning toward a Fynla-defined schema via xAI structured outputs.
13. **LPA status compliance (B-7) fix approach** — tool description only (lightweight) or add a handler-side regex detection for "registered"/"draft"/"activated" in user text + tool schema enforcement (heavier but more robust)?

### 9.4 Process

14. **Test-vs-feature balance** — Sprint 2 allocates 3 days to 11 missing Feature tests + 2 days to browser matrix. Are those rigidly sequential, or parallelise-able across agents?
15. **Deploy cadence** — Sprint 3 ships to dev once. Sprint 4 ships to production once. Do you want nightly dev deploys during Sprint 2–3 as things land, or a single end-of-sprint drop?

### 9.5 Enterprise / compliance (ADDED 24 April after enterprise-verdict cross-doc reloop)

16. **Consent enforcement approach** — add `ConsentService::hasConsent` check in `AiChatController::sendMessage` (return 403 with CTA), or run as a pre-flight on conversation creation and mark the conversation consent-OK? Runtime check is safer; pre-flight is faster.
17. **DPIA scope** — DPIA for the Fyn chat feature only, or covering the whole AI surface (chat + document extraction + daily insights + Python agent sidecar if in use)? Broader DPIA is more work but more defensible.
18. **FCA regulatory positioning** — who commissions the legal opinion? Timeline? The answer affects whether the Fyn prompt language needs rewriting (`"You think like a qualified financial planner"` is advice-shaped).
19. **DPA register location** — in vault (private) or in repo (public)? Policies say "request copies via support" — maintaining a canonical internal register is the prerequisite.
20. **Audit integrity implementation** — hash-chain on existing `ai_messages` table (in-DB), or external WORM service (S3 Object Lock, dedicated audit provider)? Cost + complexity + regulator-acceptance tradeoff.
21. **Provider failover policy** — fail over on 5xx? On 429? Parallel to which provider? Mid-conversation or new-conversation-only? Cost implication if failover goes from cheap xAI to pricier Anthropic.
22. **Python agent sidecar scope** — is it in use? Legacy? Dev-only? Answer gates whether Sprint 0.9 is "investigate and confirm unused → remove" or "investigate and map → separate enterprise verdict pass".
23. **`FynInsightCard` positioning** — relabel as "Daily tip" / "From Fynla"? Or wire it up to actually be LLM-generated for consistency with the Fyn brand? Marketing + product decision.
24. **Health-data LLM flow approach** — strip derived fields from prompt path (simpler, limits advice quality), OR capture explicit specific consent (more work, keeps feature strength)?
25. **Tamper-evident audit retention** — 7 years for advice-type rows; do we retain general chat for the same duration or a shorter default (say 2 years)?

---

## 10. What to do right now

Concretely, after reading this doc:

1. **Answer §9 Open questions** (at least strategy 1–5 and scope 6–10) so the integrated spec / plan / PRD rewrite has scope locked.
2. **Decide rebase vs new branch** — is rebasing `feature/fyn-persona-split` onto `main` the right call, or do we cherry-pick the key commits onto a fresh `feature/fyn-integrated` branch? Rebase is simpler if conflicts are manageable; cherry-pick is cleaner-history but slower.
3. **Kick off Sprint 0 prep** — the rebase + Pest run are the gate for all downstream work.
4. **Once Sprint 0 is done**, authorise Sprint 1 as one unit — the 10-minute fixes (1.1–1.5) and eval MVP (1.7) can all land in a single long session.

After Sprint 1 closes, we rewrite the **new spec / plan / PRD** using this doc + the verdict + the system map as sources, with the scope decisions from §9 applied. That becomes the authoritative rollout plan.

---

## 11. Summary

Fyn is further along than its `main` branch suggests. The persona-split + onboarding UX overhaul is 70%+ implemented with honest, well-tested code on `feature/fyn-persona-split`. The multi-entity gap-fill works end-to-end. Fact parking works. Resume flow works. The infrastructure is sound.

What it lacks is (a) test coverage for 11 scenarios, (b) completion of the 12 remaining browser-matrix rows, (c) the handful of non-critical B-X bugs, (d) rebasing onto a main that's moved substantially, (e) everything verdict Sprint 1 flagged (measurement, eval harness, temperature, cache metrics), and (f) the architectural debts deferred during the sprint.

The order is: rebase first, Sprint 1 quick wins + eval harness in parallel, close remaining B-X bugs in Sprint 2, ship to dev in Sprint 3, production + structural fixes in Sprint 4.

Every change in every sprint should reference **§7 touch-point index** before editing, to avoid the compounding-change problem the user flagged. The three dispatch paths with duplicated gap-fill (W1) are the single highest source of that risk — extracting the `MultiEntityGapFiller` service early in Sprint 3 is the best way to reduce compounding risk going forward.

When this is all landed, Fyn becomes:

- **Usefully shippable for the feature story** — the user problems in the PRD are delivered end-to-end
- **Measurably improving** — the eval harness + KPI logs mean we can prove whether changes help
- **Maintainable** — duplicate logic extracted, director split, AppLayout hoisted
- **Commercial-regulated-ready contingent on Sprint 4 criticals** — DPIA produced, FCA opinion received, xAI disclosed, audit tamper-evident, consent enforced

**The feature-usefulness claim is independent of the commercial-readiness claim.** After Sprint 2, the feature works end-to-end. After Sprint 3, it's deployable to dev. After Sprint 4, it's commercially defensible. All three milestones matter.

Grade trajectory:

- Against **verdictFyn.md (v1 Anthropic-article rubric)**: B+ today → A after Sprint 3 → A+ after Sprint 5
- Against **enterprise-verdict.md (regulated UK consumer financial software bar)**: C- today → B after Sprint 0 criticals → B+ after Sprint 4 → A with external pen-test + SOC 2 / ISO 27001 (out of scope of this plan)

---

## 12. Architecture correction — two Fyns, not three (24 April, CSJ direction)

**CSJ's stated intent, confirmed explicitly**: every time a user needs to enter information — during onboarding OR post-onboarding — use **Onboarding Fyn**. Do not duplicate tools, prompts, or effort by creating a separate post-onboarding data-capture persona.

**The persona-split branch (`feature/fyn-persona-split`) built the wrong architecture.** It introduced a `data_capture` persona with its own prompt builder (`DataCapturePromptBuilder`), its own registry entry, its own tool ownership, its own off-script filter — duplicating everything Onboarding Fyn already does. Three actors (onboarding director + advice persona + data_capture persona) where two were asked for.

### 12.1 Correct architecture (two Fyns)

```
AiChatController::sendMessage
    │
    ├── if onboarding not complete        → Onboarding Fyn (director, state machine, capture tools)
    │
    └── elseif $splitEnabled              → FynPersonaOrchestrator
                                            │
                                            ├── state: advice      → Advice Fyn (AdvicePromptBuilder, read-only tools, delegate_to_capture)
                                            │
                                            └── state: capturing   → Onboarding Fyn   ◀── same code path as initial onboarding
                                                                    (OnboardingChatDirector's capture flow,
                                                                     OnboardingPromptBuilder, create_* tools,
                                                                     FR-M14 filter, gap-fill — all the same machinery)
```

**Handoff contract (unchanged from CSJ's ask)**:
- Advice → Onboarding (capture): `delegate_to_capture` tool call — stripped from SSE, intercepted by orchestrator, transitions `persona_state.current = 'capturing'`
- Onboarding → Advice (handback): `capture_complete` tool call — stripped from SSE, resets `persona_state.current = 'advice'`, re-invokes advice with the original question primed if `pending_advice_question` was set

**What this simplifies vs the as-built three-persona model**:

| Was (three-persona) | Is (two-persona) |
|---|---|
| `AdvicePromptBuilder` (post-onboarding advice) | Same — kept |
| `DataCapturePromptBuilder` (post-onboarding capture) | **Deleted** — redundant with `OnboardingPromptBuilder` |
| `OnboardingPromptBuilder` (onboarding capture) | Same — handles capture for BOTH onboarding and post-onboarding inline capture |
| `config/fyn_personas.php` with 2 entries | Kept, but `data_capture` entry points to the ONBOARDING stack, not a new stack |
| `FynPersonaRegistry` | Kept — routes persona → onboarding stack vs advice stack |
| `FynPersonaInvoker` | Kept — invokes the registered persona |
| `FynPersonaOrchestrator` | Kept — two-mode router (advice ↔ onboarding-in-capture-mode) |
| `HandoffContract::DELEGATE_TO_CAPTURE` | Kept |
| `HandoffContract::CAPTURE_COMPLETE` | Kept |
| `ai_conversations.persona_state` JSON column | Kept |
| Post-onboarding create_* tools exposed to `data_capture` persona | **Removed** — create_* tools stay owned by Onboarding Fyn; the orchestrator routes capture turns to Onboarding Fyn instead of a separate persona |
| Separate off-script filter in `FynPersonaInvoker` | **Removed** — use the existing filter in `OnboardingChatDirector::handleAssetCaptureTurn` |
| Separate multi-entity gap-fill logic in `FynPersonaInvoker` | **Removed** — use the existing gap-fill in Onboarding Fyn's capture handler |

### 12.2 Code changes implied

**Deletions from `feature/fyn-persona-split`**:
- `app/Services/AI/Prompts/DataCapturePromptBuilder.php` — entire file
- `tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php` — entire file
- `app/ValueObjects/CaptureContext.php` — likely redundant (onboarding already has its own context via `onboarding_parked_facts` + state); fold into director's capture-turn handler
- `tests/Unit/ValueObjects/CaptureContextTest.php` — follows
- Gap-fill code in `FynPersonaInvoker::invoke` — delete, delegate to onboarding's existing gap-fill
- Off-script filter duplicated in `FynPersonaInvoker` — delete, delegate to onboarding's existing filter
- Post-onboarding preview CTA logic — moves to advice persona (still valid) and to Onboarding Fyn preview mode (already exists)
- `data_capture` entries in `config/fyn_personas.php` that reference write-tool allow-lists — replace with a single entry that routes to onboarding's capture flow

**Kept but restructured**:
- `FynPersonaOrchestrator::dispatch` — when state flips to `capturing`, invoke `OnboardingChatDirector::handleInlineCaptureTurn($user, $conversation, $captureContext, $userMessage)` (new method wrapping the existing capture handlers) instead of `FynPersonaInvoker::invoke('data_capture', ...)`
- `OnboardingChatDirector` — add `handleInlineCaptureTurn` entry point that reuses the existing capture machinery but takes a `CaptureContext`-style input from the orchestrator (reason, entity_types, fields_needed)

**Net effect on branch state**: ~500 LOC deletion across service + tests + registry config. No user-facing change. Cleaner architecture. One capture implementation. One prompt. One set of write tools. One gap-fill. One off-script filter.

### 12.3 Sprint 0 additions

Adding to Sprint 0:

| # | Task | Effort |
|---|---|---|
| **0.19** | **Collapse three-persona architecture to two-persona** — delete `DataCapturePromptBuilder` + its test; delete `CaptureContext` VO + test (or retain as data-only payload if clean); update `config/fyn_personas.php` so `data_capture` registry entry routes to `OnboardingChatDirector` handler; add `OnboardingChatDirector::handleInlineCaptureTurn(User, AiConversation, array $captureContext, string $message)` that wraps existing capture machinery; update `FynPersonaOrchestrator::runCaptureTurn` to invoke the director method instead of `FynPersonaInvoker::invoke('data_capture', ...)`. Preserves the advice ↔ capture handoff UX but removes the duplicate stack. | 1–2 days incl tests |

### 12.4 Enterprise grade impact

Removing the duplicate stack doesn't change regulatory/compliance grades (C1–C14, C10, C11 etc. all stay). It DOES change maintainability grade from 🟠 High Risk → 🟡 Gap because one less 2,000-line god file equivalent is being carried in the codebase. Test count drops slightly.

Net grade: still D+ (45/100) — the architectural simplification matters for code hygiene, not for the regulatory gaps.

### 12.5 What to do first

Before resuming the integrated plan's Sprint 0 (rebase, etc.), run the three-persona → two-persona collapse. It's the biggest-leverage simplification on the branch and the rest of the plan reads cleaner afterward. 1–2 days, no user-facing change.

---

*End of integrated plan, revised 24 April 2026 to record the CSJ-corrected two-Fyn architecture. Next step: execute Sprint 0.19 (collapse) as the first persona-split correction, THEN answer §9 open questions to lock the rest of the plan.*
