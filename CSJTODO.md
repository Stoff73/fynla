# CSJTODO — Fynla

---

## ⛔ NON-NEGOTIABLE PRE-FLIGHT — READ BEFORE TOUCHING THE BROWSER

**Understand what you are testing. Get the context. EVERY TIME.**

Before driving ANY BS-NN walk (or any onboarding / chat / state-machine flow), you MUST read these files end-to-end. No skimming. No "I'll figure it out from the snapshot". Verification is a CONTRACT — you cannot verify a contract you have not read.

**Mandatory reading list, in this order:**

1. The BS-NN docblock you are about to drive (`tests/Browser/scenarios/BS-NN-*.php`) — every assertion, every spec amendment, every prior delivery note.
2. `app/Services/Onboarding/OnboardingStateMachine.php` — every state, every prompt, every transition, every bubble label.
3. `app/Services/Onboarding/OnboardingChatDirector.php` — what each state EMITS to SSE.
4. `resources/js/store/modules/aiChat.js` — what the frontend DOES with each SSE event.
5. `resources/js/layouts/AppLayout.vue` — how the layout REACTS to `onboardingLayout` flips.
6. `resources/js/components/Shared/AiChatPanel.vue` + `AiChatPanelShell.vue` — the actual chat panel body.

---

## ⛔ THE EVAL CANONICAL CONTRACT — BINDING (read before touching the eval)

**Source of truth: `April/April28Updates/maxAuditEval.md` §0.** Issued by CSJ on 2026-04-28 (session 108). Memory mirror: `feedback_eval_canonical_contract.md`.

### Canonical 0.1 — Reset only fires when data has actually changed.

A "change" means the eval wrote, edited, added, or deleted a row owned by the persona user.

- **Non-mutating evals** (advice, navigation, factual, classification, information requests) **DO NOT** reset. Ever. Not pre-flight, not post-flight, not in finally, not between providers, not after the session.
- **Mutating evals** reset **only after** the captured change + result are persisted to `eval_recording_sessions` + `eval_provider_runs`. Reset returns the persona to its pre-eval state.
- All 10 current mitchell scenarios are non-mutating (`is_mutating: false`, `expected_db_writes.expected_count: 0`). **None of them invoke `preview:reset`.** Code on HEAD `dd2942f` is canonical-clean for all 10.

### Canonical 0.2 — No mirror user, no seed, no alteration.

The eval logs in as the actual seeded `peak_earners` preview user. NO mirror user. NO `EvalUserSeeder`. NO `is_eval_user` flag. The Sanctum token's `bypass-preview-mode` ability IS the mechanism that lets writes through (when needed, on mutating scenarios). Confirmed implemented in sessions 105-106 + canonical-aligned in session 108.

### What this means for code

- `EvalHttpDriver::run` does **not** reset (verified at HEAD `dd2942f`). It only restores the provider cache key in `finally`.
- Reset orchestration belongs in the **caller** (`EvalRecordCommand::recordOne`), runs AFTER `EvalProviderRun::create()` persists, conditional on `! empty($result['db_writes'])`. None of the 10 current scenarios trigger it.
- If you read CLAUDE.md "ALWAYS reseed after operations that modify or LOSE local DB data" and reach for `db:seed` — STOP. Schema-only migrations don't lose data. Non-mutating evals don't lose data.
- If you find a FK violation in eval flow, the fix is in the reset behaviour, NOT in dropping the FK.

### What was wrong in older docs (now fixed)

The earlier spec sections (Doc A §3.2 step 1, §4.5, §8.1, §8.4, §11.3, §14, §13.2 decision 4) and the implementation plan Task 10 step 10.3 contained text that contradicted the canonical (pre-flight reset, "always defensively", `is_eval_user` mirror user, `EvalUserSeeder`). All 20 violating lines were edited at session-108 end. **The plan documents you read now should match the canonical** — if you find any contradicting text in `April/April27Updates/eval-http-driven-rewrite-plan.md` or `April/April27Updates/eval-http-driven-rewrite-implementation-plan.md`, treat it as a regression and fix it.

---

*Last updated: 28 April 2026 — session 108 end (canonical contract issued + 3 plan docs aligned + maxAuditEval.md verified audit).*

*Previous sessions: session 107 (line-by-line audit `iLovetoLeavestuffOut.md` + removed pre-flight + post-flight resets from EvalHttpDriver, commit `dd2942f`). 27 April 2026 — session 106 (Task 11 + 13 + 14 + 15 + Task 16 dashboard polish), session 105 (Tasks 1–10 + 12), session 104 (designed the eval HTTP-driven rewrite).*

---

## ⚠️ HEY NEXT AGENT — START HERE

**Read `April/April28Updates/maxAuditEval.md` end-to-end before touching the eval.** It's a 458-line verified three-way audit anchored to the canonical contract (§0). Section 5 is the priority-ordered fix list; ship P0.1 + P0.2 + P0.3 to clear all 4 Task 16 acceptance-gate blockers.

**Code state on `feature/fyn-persona-split` HEAD `dd2942f`:**

- ✅ Canonical-clean for all 10 current scenarios (no resets, no mirror user, real preview user, Sanctum bypass token).
- 🚫 **3 P0 defects block Task 16 acceptance gate:**
  1. **Trace endpoint always returns empty** — `EvalServiceProvider:20` registers `EvalTraceCollector` as `$this->app->scoped()` (per-request); trace endpoint runs in a separate request, so collector is empty. **Fix:** persist trace via `Cache::put("eval_trace:{$conversationId}", ...)` at end of chat-send OR write directly to `eval_provider_runs.engine_trace`. Recommended: the latter — drops the trace_fetch HTTP call entirely (also closes P0.3).
  2. **`tool_calls[*].name === "unknown"`** — `EvalRecordCommand:316` reads `$event['name']` but actual SSE key is `'tool'` (verified `HasAiChat:441` + fixture line 6). **Fix:** change to `$event['tool'] ?? 'unknown'`.
  3. **HTTP call count mismatch** — all 10 mitchell scenarios assert `expected_http_log.calls=4`, driver makes 5. **Fix:** if P0.1 takes the "drop trace_fetch" path, this resolves automatically.

**Acceptance gate after P0 fixes:** the 7 expected events for `mitchell_advice_protection_cover` are all reachable from the current emit set (verified — see `maxAuditEval.md` §3.4). No additional fire-site work needed for the 10 current scenarios.

**Status of every task (canonical-aligned):**

| # | Task | Status | Commit |
|---|---|---|---|
| 1 | Migrations: persona, http_log, engine_trace columns | ✅ DONE | `67a0b08` |
| 2 | preview:reset extended to all 25 persona-touched tables (+ SoftDeletes fix) | ✅ DONE | `a6531f3` |
| 3 | 3 Eval event value-objects | ✅ DONE | `8fe5698` |
| 4 | bypass-preview-mode wired at 3 write-block sites (uses `in_array` correctly, not `can()`) | ✅ DONE | `235a019` |
| 5 | EvalTraceCollector + EvalTraceListener + EvalServiceProvider | ✅ DONE — **but P0.1 cross-request bug. Both spec & impl plan baked it in.** | `8e0bb16` |
| 6 | 11 trace call sites | 🔶 partial — KYC per-stage instead of per-field, `orchestrate_analysis` exit-only, 6 module-agent emits centralised, 6 recommendation-engine emits absent. **PrerequisiteGateService DOES fire at line 234 (Doc C audit was wrong).** All 10 current scenarios are still satisfiable. | `5cf51d4` |
| 7 | EvalAuthController + eval/* routes | ✅ DONE — **both controller-level AND route-level production gates present** (Doc C audit was wrong about route-level being missing) | `84e43c7` |
| 8 | QuerySchemas: PROTECTION_COVER → all 3 protection types | ✅ DONE | `dc76112` |
| 9 | EvalSseConsumer | ✅ DONE | `ab00ded` |
| 10 | EvalHttpDriver — canonical-clean (no resets) | ✅ DONE | `3378f03` + `dd2942f` |
| 11 | Rewire `EvalRecordCommand` | ✅ DONE — **P0.2 wrong-SSE-key bug at line 316** | `df51cd3` |
| 12 | JSON Schema for scenarios | ✅ DONE — `success` path missing from timing budget enum (factual scenarios don't grade timing) | `9b4170b` |
| 13 | Author 10 mitchell JSON scenarios + delete 6 YAMLs | 🔶 3 of 10 classifications diverge from spec §10.2 (investment_isa → investment_tax; goals_affordability → goals_progress; factual_net_worth → general). Live classifier output captured. P1 fix. | `f13208c` |
| 14 | Wire `EvalDeltaBuilder` to JSON + add `gradeEngineTrace` | ✅ DONE | `ab89fd4` |
| 15 | 5 architecture meta-tests | ✅ DONE — `PreviewBlockSitesCheckBypassTest` is too narrow (only checks 3 specific files); `EvalScenarioEngineTraceConsistencyTest::$validEngines` legalises engine names that never fire. P2. | `dc962f0` |
| 16 | Live re-record + RunPanel dashboard | 🔶 dashboard engine timeline DONE (`dac1a66`); HTTP log panel NOT added; **live recording blocked by P0.1 + P0.2 + P0.3** | `dac1a66` + `dd2942f` |

**Branch is now 28 commits ahead of `main`.** All pushed to origin. Doc edits in session 108 are local-only (April/ is gitignored).

---

## What needs to land before re-recording (priority-ordered, canonical-aligned)

### P0 (blockers — ship these first; clears all 4 Task 16 acceptance-gate failures)

1. ⏳ **Fix the trace cross-request persistence.** See P0.1 above. Recommended: write trace to `eval_provider_runs.engine_trace` directly from `HasAiChat::handleStream` and drop the trace_fetch HTTP call from `EvalHttpDriver::run`.
2. ⏳ **Fix `extractToolCallsFromEvents` SSE key.** `EvalRecordCommand:316` — change `$event['name']` to `$event['tool']`. Add a unit test loading the actual fixture.
3. ⏳ **Reconcile HTTP call count.** Falls out of P0.1 if you drop the trace_fetch. Otherwise update all 10 scenarios to `calls: 5`.

### P1 (spec parity — not gate blockers)

4. ⏳ **Decide on the 6 module-agent + 6 recommendation-engine `EngineCalled` emits.** Either implement (12 small additions) or codify the simplification (delete the engine names from `EvalScenarioEngineTraceConsistencyTest::$validEngines:16-21`). None of the 10 current scenarios assert on these — defer until needed.
5. ⏳ **Add HTTP-log panel to `RunPanel.vue`.** Plan §3.1 / §5.7. Data is already in the API response (`session.http_log` from `EvalRecordingController:118`). ~30-40 lines of template.
6. ⏳ **Reconcile 3 mismatched scenario classifications** (Task 13). Update spec §10.2 OR re-author user messages. Don't widen `KEYWORD_PATTERNS` without a live-product reason.
7. ⏳ **Add the AssertionHelpers HTTP helpers** (`assertSseStreamComplete`, `assertHttpStatusCodes`, `assertNoMiddlewareBypass`). Plan §3.1.
8. ⏳ **Convert `EvalRunner.php` to JSON-aware.** Currently scaffold-only (line 62). Plan §3.1.

### P2 (risk mitigations — Doc A §13)

9. ⏳ **Add `connectTimeout(5)` to every `Http::` call** in `EvalHttpDriver`. Lines 74, 86, 117, 142, 156. Already have `->timeout(N)`; chain `->connectTimeout(5)`.
10. ⏳ **Add Sanctum token TTL.** `EvalAuthController::login:58-61` set `expiresAt: now()->addMinutes(15)`.
11. ⏳ **Pre-flight server health check** in `EvalHttpDriver::run`. `Http::timeout(2)->get("{$baseUrl}/api/preview/personas")` — throw if non-200.
12. ⏳ **Token cleanup at driver setup.** Delete eval-tagged tokens older than 1h before login.
13. ⏳ **Make `PreviewBlockSitesCheckBypassTest` actually scan** for new write-block sites instead of checking 3 hard-coded paths.

### Future (when first mutating scenario lands)

14. ⏳ **Add canonical 0.1 reset orchestration** to `EvalRecordCommand::recordOne` after `EvalProviderRun::create()`. See `April/April27Updates/eval-http-driven-rewrite-implementation-plan.md` Task 11 step 11.3 (revised 2026-04-28).

---

## Session 108 (28 April 2026) — what shipped

**Zero git-tracked code commits.** All session-108 work was on gitignored documents (`April/` is in `.gitignore`).

### Completed this session

- [x] Wrote `April/April28Updates/maxAuditEval.md` — 458-line verified three-way audit comparing the rewrite plan (Doc A), the implementation plan (Doc B), the prior session-107 audit (Doc C), and the actual code on HEAD `dd2942f`. Anchored to the canonical contract issued by CSJ.
- [x] Identified 2 factual errors in the session-107 audit (Doc C): (1) `PrerequisiteGateService::canGetRecommendations` DOES fire `GateChecked` at line 234; (2) eval routes ARE wrapped in route-level production gate at `routes/api.php:1273`. Both verified in code.
- [x] Confirmed 4 real Task 16 blockers are all UNRELATED to the canonical: trace request-scope bug, wrong SSE key, HTTP call count mismatch, and missing fire sites (none of which block the 10 current scenarios).
- [x] Applied 20 surgical edits across the 3 plan documents to align with the canonical:
  - **Doc A** (`eval-http-driven-rewrite-plan.md`): 13 edits — pre-flight reset deleted from §3.2 pseudocode, finally-block reset moved out per canonical 0.1, §4.5 driver-invocation timing rewritten, §6.1 controller code switched from `is_eval_user`→`is_preview_user` and `EvalUserSeeder`→`PreviewUserSeeder`, §8.1 three-layer restoration replaced with caller-side post-recordOne layer, §8.4 "always reset-runs at start AND end defensively" sentence struck, §11.5 `EvalUserSeeder` list item struck, §13.1 risk 3 mitigation rewritten, §13.2 decisions 2 + 4 resolved per canonical, §14 acceptance criterion #2 rewritten to use `is_preview_user=true`.
  - **Doc B** (`eval-http-driven-rewrite-implementation-plan.md`): 4 edits — Task 10 step 10.3 pre-flight + post-flight reset blocks both replaced with `[REVISED]` annotations; Task 11 step 11.3 instructional bullet added requiring caller-side reset; the actual code block in step 11.3 now captures `EvalProviderRun::create()` return as `$run`, runs the conditional reset, and `return $run`s.
  - **Doc C** (`iLovetoLeavestuffOut.md`): 3 edits — POST-FACTO CORRECTIONS block at the top fixes the 2 factual errors and records the canonical supersession; Part E item 1 + item 4 inline corrections; footer annotated with canonical reference.
- [x] Saved canonical contract to project memory at `feedback_eval_canonical_contract.md`.
- [x] Updated `project_eval_http_driven_rewrite_branch.md` memory to reflect current status (16/16 tasks shipped, canonical-clean, 3 P0 blockers).
- [x] Updated `MEMORY.md` index with both new entries.
- [x] Updated CLAUDE.md metrics: PHP Services 269→272, Controllers 100→101.
- [x] Synced 6 changed/new docs to vault (`Doc A`, `Doc B`, `Doc C`, `maxAuditEval.md` + 2 carry-overs).
- [x] Created `Apr28.md` git-history file + updated Apr2026 Commits index + updated Home.md totals.
- [x] Added April 28 sessions block + April28Updates section to `April Index.md`.

### NOT done this session — outstanding

- [ ] All P0/P1/P2 items in "What needs to land" above. None of them were touched in session 108 — this was a documentation-only session.
- [ ] No re-recording attempted — gated on P0.1 + P0.2 + P0.3 landing first per canonical 0.1 + spec §12.16.

### Context for next session

The eval is canonical-clean in code. The plan docs are now canonical-aligned. The 4 real blockers are documented with priority-ordered fixes in `maxAuditEval.md` §5. Ship P0.1 (trace persistence), P0.2 (SSE key fix), P0.3 (call count) — that's a focused 1-2 hour chunk that clears all 4 acceptance-gate failures. Then re-record `mitchell_advice_protection_cover` to verify Task 16 §12.16 acceptance criteria 1-7. Only after that's green should you tackle P1 spec-parity items.

**Do not re-record before P0 ships.** Each recording costs LLM tokens; current bugs would produce fixtures with `tool_calls.name="unknown"` and empty `engine_trace` that have to be discarded after the fix.

---

## Carry-forward from earlier sessions (still valid)

### Tech-debt W1 from session 102 audit

- [ ] **`_full` parsed YAML in API response payload** — verified 2026-04-28: `EvalRecordingController:80` strips `_full` from response (`unset($expected['_full'])`); the internal use at line 270 is structural (controller passes `_full` to `EvalDeltaBuilder` via `$fullYaml`). No leak. Future tech-debt: split into `parseExpectationsForResponse()` + `parseExpectationsForBuilder()` for clarity. Not blocking.

### S1.7 sub-tasks — broader expansion

These pre-date the HTTP-driven rewrite. Some may be obsoleted by Tasks 11–16; others remain. Defer until after Task 16 ships.

- [ ] **S1.7.a** — Extend `AssertionHelpers` for the 48 NEW canonical-behaviour / state-machine / handoff / resume YAMLs.
- [ ] **S1.7.b** — 6 architecture meta-tests under `tests/Architecture/` (some overlap with Task 15).
- [ ] **S1.7.c** — 4 new canonical-behaviour YAMLs.
- [ ] **S1.7.d** — Path A++ — extend `EvalProviderRun` with `kyc_state`, `kyc_missing`, `tool_result_paths`, `engine_call_level_actual` columns for dashboard filtering.
- [ ] **S1.7.e** — 14 onboarding state-machine eval YAMLs + `--mode=deterministic` flag.
- [ ] **S1.7.f** — 14 write-tool-family handoff YAMLs.
- [ ] **S1.7.g** — 16 resume-after-disconnect YAMLs.
- [ ] **S1.7.h** — Re-record all fixtures.
- [ ] **S1.7.i** — Hard-gate verification doc `April/April27Updates/eval-rewrite-verification.md`.

### Notes flagged in session 102 (still apply)

- [ ] **#10 Modal-`will` regex FP in `ESTATE_PLANNING` keyword pattern.** `/\bwill(s)?\b/i` matches the modal verb in "How much inheritance tax will my estate pay?".
- [ ] **#11 `pensions_2x_schemes.yaml` `is_active` extraction.** Verify during S1.7.h fixture recording.

### Pest baseline — 3 pre-existing failures

Same root cause as previous sessions: `App\Agents\CoordinatingAgent::classifyComplexity(): Argument #2 ($conversationDepth) must be of type int, null given`.

- `tests/Feature/AI/AssistantHonestyOnWriteFailureTest::it AdviceFyn passes assistant honesty text through unchanged when a write tool fails`
- `tests/Feature/AI/ConsentRuntimeCheckTest::it allows sendMessage to stream when ai_chat consent is granted`
- `tests/Feature/AI/ConsentRuntimeCheckTest::it emits consent_required SSE and closes the stream when consent is withdrawn`

Cause: in-memory `AiConversation` whose `message_count` is null. Fix: set `message_count = 0` on the in-memory conversation, or change `classifyComplexity` signature to `?int $conversationDepth = 0` and coerce. Not blocked by any task.

---

## Pre-existing dirty files (NOT mine — leave them be)

The working tree has these pre-existing modifications carried over from sessions 102 / earlier. None were touched in sessions 107 or 108:

```
modified:   .claude/settings.json                                        ← unrelated config
modified:   .claude/skills/session-start/SKILL.md                        ← unrelated harness change
modified:   resources/js/components/Admin/EvalRecordings.vue             ← session-102 W1 in progress
deleted:    database/migrations/2026_04_27_180000_add_remedial_report... ← rename target
new file:   database/migrations/2026_04_27_000002_add_remedial_report... ← rename source
new file:   .claude/ccstatusline/                                        ← unrelated CC config
new file:   .claude/statusline-command.sh                                ← unrelated
new file:   .claude/statusline-wrapper.sh                                ← unrelated
new file:   CSJ-CAMPAIGN-LANDING-PLAN.md                                 ← separate workstream
new file:   docs/manuals/                                                ← separate workstream
new files:  tests/Feature/Fyn/Eval/fixtures/.../*.jsonl (20 files)       ← prior recordings + session-107 attempt
```

CLAUDE.md was edited this session for metrics correction (PHP Services + Controllers counts) but is small and intentional — reviewer may opt to commit or leave for next session.

---

## Deploy status

- **Production (`fynla.org`):** main untouched.
- **Dev (`csjones.co/fynla`):** dev untouched.
- **`feature/fyn-persona-split`:** 28 commits ahead of main, all pushed. NOT deployed anywhere yet — sits behind the deferred `feature → dev` PR. P0 + P1 items must complete before any deploy.

---

## Pattern reminder for ALL re-record runs (do not deviate, canonical-aligned)

1. **NEVER call `db:seed` after non-destructive migrations** (FK drops, additive columns, indexes). CLAUDE.md's reseed rule is for DESTRUCTIVE operations only.
2. **NEVER call `preview:reset` in the eval flow for non-mutating scenarios.** The bypass-preview-mode toggle is the mechanism. (Canonical 0.1.)
3. **If you find a FK violation in eval flow, the fix is in the reset behaviour, NOT in dropping the FK.**
4. **If a scenario specifies `is_mutating: false`, the persona must survive the recording byte-identical.** Verify by spot-checking `users.id` before and after.
5. **Diagnose before reverting.** When pre-flight reset wiped peak_earners between provider 1 and provider 2 in session 107, the fix was to remove the reset, not to compensate by adding more wipes / FK drops / reseeds.
6. **Do not re-introduce mirror users / `EvalUserSeeder` / `is_eval_user`.** (Canonical 0.2.) The eval logs in as the actual seeded `peak_earners` preview user; Sanctum bypass token is the mechanism.
