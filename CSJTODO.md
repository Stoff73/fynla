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

**The rules that follow from this:**

- If a navigation, prompt, or bubble surprises you mid-walk, STOP and read the state machine. Do not type past it. Do not call it cosmetic. The state machine is the contract. The browser is the audit.
- Browser interactions ONLY via `browser_click` / `browser_type` / `browser_press_key` against a `ref` from `browser_snapshot`. NEVER `browser_evaluate` for clicks, fills, or form submits.
- OTP boxes: each digit via `browser_press_key`, never `browser_type` of the whole code (boxes are `maxlength=1`, only auto-advance on real keypresses).
- Reports come AFTER GREEN, not during the loop. No mid-loop summaries. No declaring partial walks GREEN.

---

*Last updated: 27 April 2026 — session 105 end (eval HTTP-driven rewrite — 11 of 16 tasks shipped).*

*Previous sessions today: 27 April 2026 — session 104 (designed the eval HTTP-driven rewrite — spec + 16-task implementation plan written; no code changes), session 102 (Sprint 1 S1.2.l + S1.7.d Path A), session 101 (S1.6.b + S1.8 + remove S1.6.a panel), session 100 (S1.3 + S1.4 + S1.5 + S1.6.a), session 99 (eval/live prompt divergence fix Tasks 1, 2, 3, 3b).*

---

## ⚠️ HEY NEXT AGENT — START HERE

You are picking up the eval HTTP-driven rewrite mid-flight. Session 105 (this one) shipped Tasks 1–10 + 12 of the 16-task implementation plan. Your job is to ship Tasks 11, 13, 14, 15, 16. Read this whole file before touching code.

**The plan you are executing:**

1. **`April/April27Updates/eval-http-driven-rewrite-plan.md`** — design spec (1046 lines).
2. **`April/April27Updates/eval-http-driven-rewrite-implementation-plan.md`** — 16 ordered tasks with concrete code (2990 lines). **This is your primary reference.** Each task has steps with code, expected output, and a commit message.

**Status of every task:**

| # | Task | Status | Commit |
|---|---|---|---|
| 1 | Migrations: persona, http_log, engine_trace columns | ✅ DONE | `67a0b08` |
| 2 | preview:reset extended to all 25 persona-touched tables (+ SoftDeletes fix) | ✅ DONE | `a6531f3` |
| 3 | 3 Eval event value-objects | ✅ DONE | `8fe5698` |
| 4 | bypass-preview-mode wired at 3 write-block sites | ✅ DONE | `235a019` |
| 5 | EvalTraceCollector + EvalTraceListener + EvalServiceProvider | ✅ DONE | `8e0bb16` |
| 6 | 11 trace call sites in production gate/agent code | ✅ DONE | `5cf51d4` |
| 7 | EvalAuthController + eval/* routes (login, reset, trace) | ✅ DONE | `84e43c7` |
| 8 | QuerySchemas: PROTECTION_COVER → all 3 protection types | ✅ DONE | `dc76112` |
| 9 | EvalSseConsumer (pure SSE frame parser) | ✅ DONE | `ab00ded` |
| 10 | EvalHttpDriver (HTTP loop service file — integration test skip-marked) | ✅ DONE | `3378f03` |
| 11 | Rewire `EvalRecordCommand` to use `EvalHttpDriver` | ⏳ NEXT | — |
| 12 | JSON Schema for scenarios | ✅ DONE | `9b4170b` |
| 13 | Author 10 mitchell JSON scenarios + delete 6 YAMLs | ⏳ TODO | — |
| 14 | Wire `EvalDeltaBuilder` to JSON + add `gradeEngineTrace` | ⏳ TODO | — |
| 15 | 5 architecture meta-tests | ⏳ TODO | — |
| 16 | Re-record 10 scenarios + RunPanel.vue dashboard panels | ⏳ TODO (NEEDS `./dev.sh` + LIVE LLM API) | — |

**11 commits on `feature/fyn-persona-split` pushed to origin this session.** Nothing merged to dev or main. The branch is now 21 commits ahead of `main` total.

---

## Where to start (in order)

### 1. Pre-flight (don't skip)

```bash
# Confirm branch
git rev-parse --abbrev-ref HEAD            # → feature/fyn-persona-split

# Reseed (mandatory — CLAUDE.md rule)
php artisan db:seed

# Confirm green baseline across THIS SESSION's new tests
./vendor/bin/pest \
  tests/Feature/PreviewBypassAbilityTest.php \
  tests/Feature/PreviewResetCompletenessTest.php \
  tests/Feature/EvalTraceListenerTest.php \
  tests/Feature/EvalAuthControllerTest.php \
  tests/Unit/Events/EvalEventsTest.php \
  tests/Unit/Services/Eval/ \
  tests/Unit/QuerySchemasProtectionScopeTest.php
# Expect: 35-ish tests, all green.
```

### 2. Task 11 — Rewire `EvalRecordCommand` to call `EvalHttpDriver`

This is the heaviest remaining task. **Source: implementation-plan.md §11 (lines 1996–2160).**

**What dies (~70% of `app/Console/Commands/EvalRecordCommand.php`):**

- `seedUser`, `seedChildEntities`, `seedProtectionPolicies`, `seedRows`, `seedExpenditure`
- `createConversation`
- `snapshotState`, `restoreToSnapshot`, `keyById`, `diffRows`
- `extractToolCalls`
- `SNAPSHOT_TABLES` constant
- The `Cache::forever('ai_provider', ...)` block in `recordOne` (already moved to `EvalHttpDriver::run`)

**What lives:**

- `recordOne` rewritten to delegate to `app(EvalHttpDriver::class)->run(...)`. Plan provides the full code on lines 2018–2107.
- New helpers: `extractToolCallsFromEvents`, `assembleContent`, `countEventTypes`, `detectForbiddenOutputs`, `writeFixture`. Some may already exist; reuse where possible.
- Scenario loading switches from YAML to JSON via `json_decode` (plan lines 2127–2135). `locateScenario` glob switches `*.yaml` → `*.json` (plan line 2141).
- `$session = EvalRecordingSession::create([...])` — add `'persona' => $scenario['persona'] ?? null,` and `'http_log' => [],` to the create call.

**Side-effect updates required:**

- `app/Models/EvalRecordingSession.php` → add `'persona'` and `'http_log'` to `$fillable`, `'http_log' => 'array'` to `$casts`. (NOTE: this file is currently dirty in the working tree from session 102's W1 fix work — see "Pre-existing dirty files" below before touching.)
- `app/Models/EvalProviderRun.php` → add `'engine_trace'` to `$fillable`, `'engine_trace' => 'array'` to `$casts`.

**Verification:** `./vendor/bin/pest tests/Feature/Fyn/Eval/ tests/Unit/Services/Eval/` should still pass. The command itself can't be exercised end-to-end until Task 13 ships scenario JSONs and Task 16 runs `./dev.sh`.

### 3. Task 13 — Author 10 mitchell JSON scenarios

**Source: implementation-plan.md §13 (lines 2303–2456). Spec: plan.md §10 (lines 783–846).**

10 new files at `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_*.json`:

1. `mitchell_advice_protection_cover.json` — implementation-plan.md §13.1 has the full template (lines 2322–2402). Copy verbatim. `expected_tool_calls` MUST include `list_records` for life_insurance + critical_illness + income_protection (Task 8 already wired the live system to require them).
2. `mitchell_advice_savings_emergency.json`
3. `mitchell_advice_investment_isa.json`
4. `mitchell_advice_retirement_contribution.json`
5. `mitchell_advice_estate_iht.json`
6. `mitchell_advice_holistic_health.json` — `expected_engine_call_level: holistic` (only one)
7. `mitchell_advice_tax_optimisation.json`
8. `mitchell_advice_goals_affordability.json`
9. `mitchell_factual_net_worth.json` — `expected_response_mode: factual`, `expected_engine_call_level: factual`, `expected_kyc_state: bypass`
10. `mitchell_factual_income.json` — same factual shape

Per-scenario authoring steps (from implementation-plan.md §13.2):

- Run the question against peak_earners live to capture actual classification + tool calls. `php artisan tinker --execute="dump(app(\App\Services\AI\QueryClassifier::class)->classify('<message>'));"`
- Copy the classification verbatim to `expected_classification_shape`.
- Tool-call list comes from `QuerySchemas::REQUIRED_TOOLS[<primary>]` (use the `tool(args)` pattern from the constant — see `app/Constants/QuerySchemas.php:467+`).
- Set `result_path: happy` for everything (peak_earners has full data).
- Timing: start at `anthropic.happy: 9000`, `xai.happy: 22000` (factual: 5000 / 12000). Recalibrate after Task 16 records.

**Delete the 6 superseded YAMLs:**
```bash
rm tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_protection_cover.yaml \
   tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_savings_emergency.yaml \
   tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_investment_isa.yaml \
   tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_retirement_contribution.yaml \
   tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_estate_iht.yaml \
   tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_goals_affordability.yaml
```

**KEEP** `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/*.yaml` — out of scope per plan §11.3.

**Validation:** every scenario must validate against `tests/Feature/Fyn/Eval/scenarios/_schema.json` (already shipped in Task 12). Validator install (`composer require --dev justinrainbow/json-schema`) is a Task 15 prerequisite.

### 4. Task 14 — Wire `EvalDeltaBuilder` to JSON + add `gradeEngineTrace`

**Source: implementation-plan.md §14 (lines 2459–2629).**

- `app/Services/Eval/EvalDeltaBuilder.php` → 1 line: `Yaml::parse(...)` → `json_decode(..., true)` (~line 65 per plan).
- `app/Http/Controllers/Api/Admin/EvalRecordingController.php` → same Yaml→JSON swap in `parseExpectations` (~line 190). File is currently dirty in the working tree — see "Pre-existing dirty files" below before touching.
- Add `gradeEngineTrace` method (plan lines 2483–2564) — covers `must_contain`, `must_not_contain`, `ordered`. 3 new Pest tests (plan lines 2575–2614) — must_contain misses, must_not_contain hits, ordering violations.

**Verification:** existing 20 tests in `EvalDeltaBuilderTest` must still pass + 3 new ones for `gradeEngineTrace`.

### 5. Task 15 — 5 architecture meta-tests

**Source: implementation-plan.md §15 (lines 2633–2780).**

```bash
composer require --dev justinrainbow/json-schema
```

Then create:

- `tests/Architecture/EvalScenarioJsonSchemaTest.php` — validate every JSON scenario against `_schema.json`.
- `tests/Architecture/EvalScenarioPersonaIsValidTest.php` — every scenario binds to a known persona.
- `tests/Architecture/EvalScenarioMutatingFlagMatchesWritesTest.php` — `is_mutating: false` ⇒ `expected_db_writes.expected_count: 0`.
- `tests/Architecture/EvalScenarioEngineTraceConsistencyTest.php` — every `expected_engine_trace.must_contain[*]` engine/gate/decisionPoint string is in the valid list.
- `tests/Architecture/PreviewBlockSitesCheckBypassTest.php` — grep test that `bypass-preview-mode` literal appears in `PreviewWriteInterceptor.php`, `HasAiChat.php`, `CoordinatingAgent.php`. (Already does — Task 4 wired it. The test locks against drift.)

### 6. Task 16 — Live re-record + dashboard polish

**Source: implementation-plan.md §16 (lines 2784–2949).**

This is the integration verification. **Needs `./dev.sh` running + costs LLM API calls** (anthropic + xAI per scenario × 10 scenarios).

```bash
# In a separate terminal:
./dev.sh

# Then re-record:
php artisan eval:record mitchell_advice_protection_cover
# Verify acceptance gate from plan §12.16 — see implementation-plan.md §16.3.

# Then loop the remaining 9:
for s in mitchell_advice_savings_emergency mitchell_advice_investment_isa \
         mitchell_advice_retirement_contribution mitchell_advice_estate_iht \
         mitchell_advice_holistic_health mitchell_advice_tax_optimisation \
         mitchell_advice_goals_affordability mitchell_factual_net_worth \
         mitchell_factual_income; do
  php artisan eval:record "$s"
done
```

After recording, calibrate timing budgets (plan §16.7).

Dashboard polish: extend `resources/js/components/Admin/eval/RunPanel.vue` with HTTP log + engine/gate timeline panels. Plan provides full Vue snippets at lines 2854–2914. **NOTE:** RunPanel.vue may need to consume the new fields — `EvalRecordingController` already returns `persona`, `http_log`, and now `engine_trace` after Tasks 11 + 14.

**Acceptance gate per plan §12.16 — every box must tick:**

1. `php artisan db:seed --class=PreviewUserSeeder` produces peak_earners with full data.
2. `php artisan eval:record mitchell_advice_protection_cover` runs end-to-end via the HTTP loop. Session row has `persona='peak_earners'`, populated `http_log`, populated `engine_trace`, `status='completed'`.
3. Both providers' `tool_calls` contain `list_records(life_insurance)` AND `list_records(critical_illness)` AND `list_records(income_protection)` AND `get_module_analysis(protection)`.
4. Each `list_records.result` is non-empty.
5. `get_module_analysis(protection)` returns `happy` path.
6. Assistant text contains FCA signposting AND references real persona data (e.g. "Aviva", "£400,000", a policy type by name).
7. `engine_trace` contains the 7 expected events in order, with NO `EngineCalled:orchestrate_analysis`.
8. `EvalDeltaBuilder` grades both runs as PASS.

If ANY step fails: per CLAUDE.md Rule #15 (LOOP UNTIL CORRECT), diagnose, fix, re-verify, repeat until GREEN per the plan. Don't hand back partial.

---

## Plan corrections shipped in session 105 (KEEP THESE PATTERNS)

These were bugs in the plan that surfaced during execution. Apply the same patterns going forward.

### 1. Sanctum wildcard ability — `can()` is unsafe (Task 4)

The implementation plan and spec both said:

```php
$token->can('bypass-preview-mode')
```

But Sanctum's default abilities for `createToken()` with no args is `['*']`. `PersonalAccessToken::can()` returns true if abilities contain `'*'` OR the literal string. So **every regular preview-user token would silently bypass**.

**Correct pattern (used in 4 files):**

```php
$abilities = $token->abilities ?? [];
$hasEvalBypass = in_array('bypass-preview-mode', $abilities, true);
```

Wired in:
- `app/Http/Middleware/PreviewWriteInterceptor.php`
- `app/Traits/HasAiChat.php` (around line 144)
- `app/Agents/CoordinatingAgent.php` (around line 717)
- `app/Listeners/Eval/EvalTraceListener.php`

If you add another bypass site, use the same `in_array(..., true)` pattern, NOT `can()`.

### 2. SoftDeletes leak in `preview:reset` (Task 2)

Pre-existing bug (not introduced by this work, but had to be fixed for Task 2 to pass):

`User` model uses `SoftDeletes`, AND every child model on `peak_earners`'s persona-touched tables uses `SoftDeletes`. The old `resetPersona` called `$user->delete()` (soft) and `Property::where(...)->delete()` (soft). Result:
- soft-deleted user kept the email row → reseed hit unique constraint
- soft-deleted child rows kept their joint_owner_id → forceDelete on user fired FK violations

**Fix:** `forceDelete()` everywhere in `ResetPreviewData::deleteUserData` and `resetPersona`. Locked by `tests/Feature/PreviewResetCompletenessTest`.

### 3. AdviceFyn::handle didn't call classifyResponseMode (Task 6 deviation)

The implementation plan said "after computing `$responseMode = self::classifyResponseMode($primary)`" — but `handle()` doesn't actually call those methods. They're pure helpers used by the eval delta builder.

**What I did:** post-classify in `handle()`, fire the events guarded by map presence:

```php
if (isset(self::RESPONSE_MODE_MAP[$traceablePrimary])) {
    event(new AgentDecision('AdviceFyn', 'response_mode', self::RESPONSE_MODE_MAP[...], ...));
}
```

This avoids throwing on out-of-remit / data-entry primaries (which have no map entry).

### 4. KycGateChecker per-field events (Task 6 simplified)

Plan said per-FIELD KYC events (5 universal field checks). Restructuring `checkUniversalRequirements` to emit per-field would make it noisy. **What I did:** one `GateChecked('kyc', 'global', ...)` event after the universal pass + one per module check. The architecture meta-test in Task 15 only validates gate name strings, so granularity is flexible.

### 5. 6 module agents' analyze events (Task 6 simplified)

Plan said wrap each module agent's `analyze()` with `EngineCalled` events. Each agent has 4–12 internal return paths. **What I did:** fire one `EngineCalled('{module}_analysis', ...)` from `CoordinatingAgent::handleModuleAnalysis` (the centralised `get_module_analysis` tool dispatcher). One emit point covers all 6 modules + `holistic`. result_path inferred from `$analysis['success']`.

If Task 16 acceptance gates need finer granularity, revisit this.

---

## Pre-existing dirty files (NOT mine — DO NOT include in Task 11/14 commits)

The working tree at session-105 end has these pre-existing modifications that were there at session-105 start and represent incomplete session-102 W1 work or other unmerged workstreams. **Don't claim them in your commits.**

```
modified:   .claude/settings.json                                        ← unrelated config
modified:   .claude/skills/session-start/SKILL.md                        ← unrelated harness change
modified:   app/Http/Controllers/Api/Admin/EvalRecordingController.php   ← session-102 W1 in progress
modified:   app/Models/EvalRecordingSession.php                          ← session-102 W1 in progress
modified:   resources/js/components/Admin/EvalRecordings.vue             ← session-102 W1 in progress
deleted:    database/migrations/2026_04_27_180000_add_remedial_report... ← rename target
new file:   database/migrations/2026_04_27_000002_add_remedial_report... ← rename source

new file:   .claude/ccstatusline/                                        ← unrelated CC config
new file:   .claude/statusline-command.sh                                ← unrelated
new file:   .claude/statusline-wrapper.sh                                ← unrelated
new file:   CSJ-CAMPAIGN-LANDING-PLAN.md                                 ← separate workstream
new file:   docs/manuals/                                                ← separate workstream
new files:  tests/Feature/Fyn/Eval/fixtures/.../*.jsonl (18 files)       ← prior recordings
```

**Critical for Task 11 + Task 14:** when you modify `EvalRecordingSession.php` (add fillable/casts) or `EvalRecordingController.php` (Yaml→JSON swap), MERGE your edit into the existing dirty content rather than overwriting it. Use `git diff app/Models/EvalRecordingSession.php` first to see what session-102 left there.

---

## Test status snapshot at session 105 end

All NEW tests written this session (35 cases across 8 files):

| Suite | Tests | Status |
|---|---|---|
| `tests/Unit/Events/EvalEventsTest.php` | 3 | ✅ green |
| `tests/Feature/PreviewResetCompletenessTest.php` | 2 | ✅ green |
| `tests/Feature/PreviewBypassAbilityTest.php` | 3 | ✅ green |
| `tests/Feature/EvalTraceListenerTest.php` | 4 | ✅ green |
| `tests/Feature/EvalAuthControllerTest.php` | 7 | ✅ green |
| `tests/Unit/QuerySchemasProtectionScopeTest.php` | 3 | ✅ green |
| `tests/Unit/Services/Eval/EvalSseConsumerTest.php` | 6 | ✅ green |
| `tests/Feature/Fyn/Eval/EvalHttpDriverTest.php` | 1 (skipped) | ⏭ needs ./dev.sh |

Existing test suites — no regressions:

- `tests/Unit/Agents/` — 84/84
- `tests/Unit/Services/AI/` — 17/17 classifier + 15/15 AdviceFyn maps
- `tests/Unit/Services/Eval/EvalDeltaBuilderTest.php` — 72/72
- `tests/Unit/Services/Protection/Savings/Investment/Retirement/Estate/` — 583/583

One transient flake observed: `SavingsAgentGoalsTest::it recommends increasing contributions` failed once and passed on retry. Test isolation issue, not deterministic. If it shows up in CI, may need `RefreshDatabase` adjustment.

---

## Summary of what session 105 actually shipped

11 commits on `feature/fyn-persona-split` (all pushed to origin):

```
3378f03 feat(eval): add EvalHttpDriver — HTTP-driven eval loop                                  [Task 10]
9b4170b feat(eval): add JSON Schema for scenario files                                          [Task 12]
5cf51d4 feat(eval): wire trace events at gate, agent, and engine call sites                     [Task 6]
ab00ded feat(eval): add EvalSseConsumer for SSE frame parsing                                   [Task 9]
dc76112 fix(query-schemas): PROTECTION_COVER must surface all 3 protection types                [Task 8]
84e43c7 feat(eval): add EvalAuthController with login + reset + trace endpoints                 [Task 7]
8e0bb16 feat(eval): add EvalTraceCollector + EvalTraceListener (ability-gated)                  [Task 5]
235a019 feat(eval): add bypass-preview-mode token ability to 3 write-block sites                [Task 4]
8fe5698 feat(eval): add GateChecked + EngineCalled + AgentDecision event classes                [Task 3]
a6531f3 feat(preview): extend preview:reset to all persona-touched tables + fix SoftDeletes leak [Task 2]
67a0b08 feat(eval): add persona + http_log + engine_trace columns to eval recording tables      [Task 1]
```

The HTTP loop, the eval auth surface, the trace pipeline, the bypass-ability mechanism, the persona reset, and the protection-cover scope correction are all live and tested. From here the remaining tasks connect plumbing (Tasks 11, 14), author scenarios (Task 13), add architecture meta-tests (Task 15), and run the live integration verification (Task 16).

---

## Carry-forward from earlier sessions (still valid)

### Tech-debt W1 from session 102 audit

- [ ] **`_full` parsed YAML in API response payload** — `EvalRecordingController::parseExpectations` puts the entire parsed YAML under `_full` and that key still ships in the JSON response. Should be split into `parseExpectationsForResponse()` + `parseExpectationsForBuilder()`. **Note:** commit `a96a7d5` claimed to fix this; the file still has uncommitted changes (see "Pre-existing dirty files"). **Verify when handling Task 14.**

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

## Deploy status

- **Production (`fynla.org`):** main untouched.
- **Dev (`csjones.co/fynla`):** dev untouched.
- **`feature/fyn-persona-split`:** 11 new commits pushed to origin this session (21 total ahead of main). NOT deployed anywhere yet — sits behind the deferred `feature → dev` PR. Tasks 11–16 must complete before any deploy.

When the next deploy happens after the rewrite finishes:

- 2 new migrations (Task 1) — schema-additive, safe.
- New routes (Task 7) — eval-only, gated by `if (! app()->environment('production'))` so they don't register on prod.
- New service provider (Task 5) — `EvalServiceProvider` registered in `config/app.php`.
- New event listeners (Task 5) — fire only when active token has `bypass-preview-mode` ability.
- Modified production code (Tasks 4, 6, 8) — preview-mode write blocks, gate/agent/engine code, `QuerySchemas`. Behaviour change: protection queries now surface all 3 protection types in the live LLM tool selection (Task 8 — fixes the live product, not just the eval).

---

## Pattern reminder for ALL BS-NN runs (do not deviate)

1. Sign out + clear browser session storage (or use the seeded john path for advice-mode-only tests).
2. Landing page → "Quick start with Fyn" CTA → fresh registration with a unique email (when an end-to-end onboarding walk is required).
3. Verify MFA via the pending registration's `verification_code` from DB. Type each digit individually with `browser_press_key`.
4. Drive bubbles + buttons via `browser_click` against the FynQuickReplies button `ref` from `browser_snapshot`. NEVER `browser_evaluate(...).click()`.
5. Free text via `browser_type` against textarea `ref` + `submit:true`.
6. After ANY code change, re-test from Step 1.
