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

*Last updated: 28 April 2026 — session 109 end (P0+P1+P2 maxAuditEval items shipped, both providers green on `mitchell_advice_protection_cover`, branch pushed).*

*Previous sessions: session 108 (canonical contract issued + 3 plan docs aligned + maxAuditEval.md verified audit). Session 107 (line-by-line audit `iLovetoLeavestuffOut.md` + removed pre-flight + post-flight resets from EvalHttpDriver, commit `dd2942f`). 27 April 2026 — session 106 (Task 11 + 13 + 14 + 15 + Task 16 dashboard polish), session 105 (Tasks 1–10 + 12), session 104 (designed the eval HTTP-driven rewrite).*

---

## ⚠️ HEY NEXT AGENT — START HERE

**`feature/fyn-persona-split` is GREEN on the maxAuditEval acceptance gate.** Both providers (anthropic, xai) cleared `mitchell_advice_protection_cover` end-to-end on `ba7fd33`: status=completed, http_log=4, real tool names, gradeEngineTrace PASS, persona user.id byte-identical. 31 commits ahead of `main`, all pushed.

**The next focused chunk is Sprint 1 / S1.7 fixture re-recording** (the 9 other mitchell scenarios + the broader S1.7 series). Don't re-record blindly — read `April/April28Updates/maxAuditEval.md` §7 (definition of done) and confirm the 4-call http_log + populated engine_trace for each new recording.

**Acceptance gate criteria (all met for protection_cover):**

| # | Criterion | Anthropic | xAI |
|---|---|---|---|
| 1 | `db:seed --class=PreviewUserSeeder` produces peak_earners with full data | ✅ | ✅ |
| 2 | `eval:record` runs end-to-end via HTTP loop, both providers' rows populated | ✅ | ✅ |
| 3 | `tool_calls[*].name` matches actual tool names | ✅ | ✅ |
| 4 | `get_module_analysis(protection)` returns happy path | ✅ | ✅ |
| 5 | Assistant text contains FCA signposting + real persona data | ✅ | ✅ |
| 6 | engine_trace contains 6 expected events | ✅ | ✅ |
| 7 | EvalDeltaBuilder grades both runs as PASS | ✅ | ✅ |
| Canonical | persona `users.id` byte-identical, no `preview:reset`, no `db:seed` | ✅ | ✅ |

**Branch is now 31 commits ahead of `main`** (28 from sessions 104–108 + 3 from session 109). All pushed to origin. Doc edits in sessions 108 + 109 are local-only (April/ is gitignored).

---

## What's outstanding (priority-ordered)

### P1 / Sprint 1 — pick up here

1. ⏳ **Re-record the other 9 mitchell scenarios.** Each costs LLM tokens. Run `php artisan eval:record mitchell_advice_<x> --providers=anthropic,xai` per scenario. Verify each one against `maxAuditEval.md` §7 acceptance criteria (status=completed, http_log=4, gradeEngineTrace PASS, persona user.id unchanged) before moving to the next.
2. ⏳ **Decide §5.4 spec-parity stance** — codify the simplification (option **b**, no code) OR implement the 13 missing emits (option **a**). Currently §5.4 in `maxAuditEval.md` recommends (b). If (b), remove `*_recommendation` strings from `EvalScenarioEngineTraceConsistencyTest::$validEngines:16-21` so the architecture meta-test reflects reality.
3. ⏳ **Add the AssertionHelpers HTTP helpers** (`assertSseStreamComplete`, `assertHttpStatusCodes`, `assertNoMiddlewareBypass`). Plan §3.1.
4. ⏳ **Convert `EvalRunner.php` to JSON-aware.** Currently scaffold-only (line 62). Plan §3.1.

### Future (when first mutating scenario lands)

5. ⏳ **Add canonical 0.1 reset orchestration** to `EvalRecordCommand::recordOne` after `EvalProviderRun::create()`. Pattern documented in Doc B Task 11 step 11.3 (revised 2026-04-28).
6. ⏳ **`Auditable::shouldAudit` must also gate on `bypass-preview-mode`** — otherwise eval mutating writes won't produce audit-chain rows. Currently in the explicit ignore-list of `PreviewBlockSitesCheckBypassTest` with a TODO comment. Drop from ignore-list and add the bypass check when the first mutating scenario is being designed.

---

## Session 109 (28 April 2026) — maxAuditEval P0 + P1 + P2 ship; acceptance gate GREEN

**Branch:** `feature/fyn-persona-split` (31 commits ahead of `main`, all pushed). 3 commits this session: `3062cf3`, `ba7fd33`.

### Completed this session

#### P0 — clear all 4 Task 16 acceptance-gate blockers (commit `3062cf3`)

- [x] **§5.1 — Drop the cross-request trace endpoint.** `EvalTraceCollector::persistForConversation()` writes the request-scoped trace to a 10-min file-cache entry from `AiChatController::sendMessage`'s `finally` block. `EvalHttpDriver` `Cache::pull`s it directly — no HTTP call. Removed `GET /api/eval/trace`, the trace HTTP call (was line 141 in driver), and `EvalAuthController::trace`. New unit-level test `tests/Feature/EvalTracePersistenceTest.php` covers cache write + non-write semantics.
- [x] **§5.2 — `EvalRecordCommand:316` SSE key fix.** Changed `$event['name']` → `$event['tool']` to match `HasAiChat:439–443/532–566` actual emit shape. Captured a regression test against the recorded fixture in `tests/Unit/Console/Commands/EvalRecordCommandToolExtractionTest.php`.
- [x] **§5.3 — HTTP call count = 4.** Auto-resolved by §5.1 (drop trace fetch). All 10 mitchell scenarios already declare `expected_http_log.calls = 4`.

#### Bonus engineering fix (the eval was correctly surfacing wasted compute)

- [x] **`AdvicePromptBuilder` no longer pays full-orchestrate cost on every chat send.** The 9 module-scoped scenarios' `must_not_contain: orchestrate_analysis` assertion was correctly flagging that `buildFinancialContext` was unconditionally calling `orchestrateAnalysis` to build the prompt context — wasted compute + wasted prompt tokens for module-scoped queries.
- [x] **`AdviceFyn::engineCallLevelFor(?string)` exposed as public static.** Reads the existing private `ENGINE_CALL_LEVEL_MAP`.
- [x] **`CoordinatingAgent::analyzeRelevantModules(int, ?array)`** sizes the analysis to classification's engine_call_level: `holistic` runs full `orchestrateAnalysis`, `module` runs only the relevant `{Module}Agent::analyze` calls (uses `QuerySchemas::getModulesForClassification`), `factual` skips module analysis entirely.
- [x] **`HasAiChat::buildSystemPrompt`** switched to `analyzeRelevantModules`.
- [x] **`AdvicePromptBuilder::buildFinancialContext` cache key** now includes classification primary so different query types don't share cached context.

#### P1 §5.5 — reconcile 3 mismatched primary classifications

- [x] **All 3 scenarios already match live classifier** (verified against `QueryClassifier::classify`); only Doc A §10.2 was stale.
- [x] **Doc A `eval-http-driven-rewrite-plan.md` §10.2 rows 3, 8, 9** updated with `[REVISED 2026-04-28]` annotations: investment_isa → `investment_tax`; goals_affordability → `goals_progress`; factual_net_worth → `general`. Question-text columns also brought in line with the actual scenario JSON files. (Local-only — April/ is gitignored.)

#### P1 §5.7 — HTTP-log panel (commit `ba7fd33`)

- [x] **Added to `EvalRecordings.vue`** (NOT inside `RunPanel.vue` — `session.http_log` is per-session not per-run; mixing both providers' calls inside each run-panel card would be redundant). Renders method / URL / status / duration / time with status-colour coding from the design system. ~50 LoC template + ~25 LoC script.
- [ ] **Not browser-tested** — code follows existing patterns (table styling, conditional rendering, null safety from `Array.isArray`); needs visual verification in the admin UI.

#### P1 §5.8 — 5 risk mitigations (commit `ba7fd33`)

- [x] **Token TTL.** `EvalAuthController::login` mints Sanctum tokens with `expiresAt = now + config('fyn_eval.token_ttl_minutes', 15)`. New `FYN_EVAL_TOKEN_TTL_MINUTES` env var. Defence in depth on top of the existing production-environment + route-registration refusals.
- [x] **`connectTimeout(5)` on every Http call** in `EvalHttpDriver` (login, create_conv, send_msg, logout) — fail fast on a hung dev server.
- [x] **Pre-flight health check** at the start of `EvalHttpDriver::run`: `GET /api/preview/personas` with `timeout(2)/connectTimeout(2)`. Surfaces a clean error if `./dev.sh` isn't running.
- [x] **Token cleanup at driver setup**: `PersonalAccessToken::where(name 'like' 'eval-%')` older than an hour deleted before each recording.
- [x] **`PreviewBlockSitesCheckBypassTest` broadened** from a hardcoded 3-file list to a recursive scan of `app/Http/Middleware/`, `app/Traits/`, `app/Agents/`. Files reading `is_preview_user` must also contain `bypass-preview-mode` unless explicitly on the ignore list. The list flags 4 read-context callers (CheckSubscription, CheckFeatureAccess, HasAiGuardrails plan-tier, Auditable audit-skip). Auditable is noted as needing the bypass check before the first mutating eval scenario lands so audit chains fire for eval writes.

#### P2 §4.2 / §4.3 — Doc B / Doc C alignment

- [x] **Doc B was already aligned** by session 108 — Task 10 step 10.3 (DELETE pre/post-flight reset) and Task 11 step 11.3 (ADD caller-side reset orchestration) carry the `[REVISED 2026-04-28]` markers. No further edits needed.
- [x] **Doc C `iLovetoLeavestuffOut.md`** got 3 inline annotations: §3.2 IMPACT (canonical 0.1 forbids pre-flight), §4.5 DIVERGENCE (both spec and impl plan were wrong, not just impl plan), §8 PLANNED (canonical 0.1 says reset gated on persisted db_writes diff, not on `is_mutating` flag alone). (Local-only.)

#### Live verification — both providers

- [x] **`mitchell_advice_protection_cover` end-to-end** for both anthropic + xai post-§5.8 hardening. status=completed, http_log=4, real tool names, `gradeEngineTrace` PASS, persona user.id unchanged (17 → 17), 8.6s anthropic / 12.4s xai.
- [x] **Test sweep:** 282 passed (1070 assertions), 0 failed, 1 skipped (manual integration), 23 deprecation warnings (all upstream Pest 8.5 reflection internals, none from our code).

#### Memory + housekeeping

- [x] Saved `feedback_evals_surface_engineering_issues.md` to memory — captures CSJ's principle that failing assertions surface real engineering issues; my first instinct ("drop the assertion") was wrong.
- [x] `MEMORY.md` index updated.
- [x] Migrations + reseed: 16 pending migrations were run (`php artisan migrate --force`) — 4 eval tables + 12 persona-split branch additive migrations + 1 small data migration (`clear_stale_persona_state` on `ai_conversations.persona_state`). Re-seeded after.

### Files written this session — git-tracked

**Code (10):**
- `app/Console/Commands/EvalRecordCommand.php` — P0.2 + docblock
- `app/Services/Eval/EvalTraceCollector.php` — `cacheKey()` + `persistForConversation()`
- `app/Services/Eval/EvalHttpDriver.php` — drop trace fetch + cache pull + 4 connectTimeout chains + pre-flight + token cleanup
- `app/Http/Controllers/Api/AiChatController.php` — `finally` block dumping trace to cache
- `app/Http/Controllers/Api/EvalAuthController.php` — drop `trace()` method + token expiresAt
- `routes/api.php` — drop `/api/eval/trace` route
- `app/Services/AI/AdviceFyn.php` — `engineCallLevelFor()` public static
- `app/Agents/CoordinatingAgent.php` — `analyzeRelevantModules()`
- `app/Traits/HasAiChat.php` — `buildSystemPrompt` switches to `analyzeRelevantModules`
- `app/Services/AI/AdvicePromptBuilder.php` — cache key includes classification primary
- `config/fyn_eval.php` — `token_ttl_minutes`
- `resources/js/components/Admin/EvalRecordings.vue` — HTTP-log panel

**Tests (5):**
- `tests/Unit/Console/Commands/EvalRecordCommandToolExtractionTest.php` *(new)*
- `tests/Feature/EvalTracePersistenceTest.php` *(new)*
- `tests/Feature/EvalAuthControllerTest.php` (replaced trace tests)
- `tests/Feature/Fyn/Eval/EvalHttpDriverTest.php` (count 5→4)
- `tests/Architecture/PreviewBlockSitesCheckBypassTest.php` (3-file list → recursive scan with ignore list)

**Fixtures (2):** Re-recorded `mitchell_advice_protection_cover.jsonl` for both `anthropic/claude-haiku-4-5-20251001` and `xai/grok-4-1-fast-reasoning`.

### Files written this session — local-only (April/ is gitignored)

- `April/April27Updates/eval-http-driven-rewrite-plan.md` — §10.2 rows 3, 8, 9 updated with [REVISED 2026-04-28] annotations
- `April/April28Updates/iLovetoLeavestuffOut.md` — 3 inline annotations cross-referencing canonical 0.1

### Context for next session

The acceptance gate is GREEN for `mitchell_advice_protection_cover` on both providers. Next batch is the other 9 mitchell scenarios. The cost is mostly LLM tokens (~$0.05–$0.10 per scenario per provider). Re-record one at a time, verify each via the `gradeEngineTrace` shape in `EvalDeltaBuilder`, before moving on. Several fixtures will surface new engineering issues (the way the protection_cover one surfaced the unconditional-orchestrate issue) — that's the eval working correctly. **Do not silence assertions to make recordings pass; investigate the underlying issue.** See `feedback_evals_surface_engineering_issues.md`.

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
- **`feature/fyn-persona-split`:** 31 commits ahead of main, all pushed. NOT deployed anywhere yet — sits behind the deferred `feature → dev` PR. Acceptance gate GREEN on `mitchell_advice_protection_cover` for both providers; remaining 9 mitchell scenarios still need re-recording before merge.

---

## Pattern reminder for ALL re-record runs (do not deviate, canonical-aligned)

1. **NEVER call `db:seed` after non-destructive migrations** (FK drops, additive columns, indexes). CLAUDE.md's reseed rule is for DESTRUCTIVE operations only.
2. **NEVER call `preview:reset` in the eval flow for non-mutating scenarios.** The bypass-preview-mode toggle is the mechanism. (Canonical 0.1.)
3. **If you find a FK violation in eval flow, the fix is in the reset behaviour, NOT in dropping the FK.**
4. **If a scenario specifies `is_mutating: false`, the persona must survive the recording byte-identical.** Verify by spot-checking `users.id` before and after.
5. **Diagnose before reverting.** When pre-flight reset wiped peak_earners between provider 1 and provider 2 in session 107, the fix was to remove the reset, not to compensate by adding more wipes / FK drops / reseeds.
6. **Do not re-introduce mirror users / `EvalUserSeeder` / `is_eval_user`.** (Canonical 0.2.) The eval logs in as the actual seeded `peak_earners` preview user; Sanctum bypass token is the mechanism.
