# Report 2 — Implementation Status

**Date:** 2026-04-22 (session 3 compile)
**Branch:** `feature/fyn-persona-split`
**Scope:** FR-by-FR audit of the persona-split + onboarding-UX release against the actual code, the actual DB, and the actual browser behaviour. Three buckets: **IMPLEMENTED**, **PARTIAL**, **MISSING / OMITTED**. All evidence is file:line or verbatim output.

---

## Executive summary

- **26 Must-have FRs:** all have code touching the right file, but 2 are known-broken in the browser (FR-M14 profile-review pause render order; see Bugs B-1 and B-5), and 1 has a known schema/linking gap (FR-M14 spouse capture household linking; see B-2).
- **3 Should-have FRs:** 2 satisfied, 1 deferred (drift audit command — not built).
- **Tests:** 4 of 20 planned files present — **16 missing.**
- **Browser D1 matrix (multi-entity):** 0 of 14 rows run to completion. Row 1 protection **failed** when re-tested by session 3.
- **Known bugs in live code:** 8 listed at the bottom, 5 are open, 3 were fixed this session.

---

## Part A — FR-by-FR status

### FR-M1 — AiChatController 3-way route

**IMPLEMENTED.** `app/Http/Controllers/Api/AiChatController.php:156-168`:

```php
$inOnboarding = $user->onboarding_completed === false
    && $user->onboarding_fyn_step !== null
    && (bool) config('onboarding.fyn_flow_enabled', true);
$splitEnabled = (bool) config('fyn.persona_split_enabled', false);
$generator = match (true) {
    $inOnboarding => $this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute),
    $splitEnabled => $this->orchestrator->dispatch($user, $conversation, $message, $currentRoute),
    default => $this->coordinatingAgent->chat($user, $conversation, $message, $currentRoute),
};
```

Matches PRD §5 FR-M1 and spec §Controller wiring verbatim.

---

### FR-M2 — FynPersonaOrchestrator + persona_state transitions + capture_max_turns

**IMPLEMENTED.** `app/Services/AI/FynPersonaOrchestrator.php` (15 KB). Emits `preview_cta` SSE on preview users (line 375), dispatches by state, respects `capture_max_turns` from config. Verified browser-side in `browser-test-conversations.md` Scenarios 1–6 (session 2).

---

### FR-M3 — FynPersonaInvoker

**IMPLEMENTED.** `app/Services/AI/FynPersonaInvoker.php` (9.3 KB). Calls `CoordinatingAgent::chatWithPromptOverride` with `toolsListOverride` (correct 8-arg signature per AMENDMENTS §F).

---

### FR-M4 — SystemPromptBuilder → AdvicePromptBuilder rename

**IMPLEMENTED.** `app/Services/AI/AdvicePromptBuilder.php` (52 KB) exists; `app/Services/AI/SystemPromptBuilder.php` absent. `grep SystemPromptBuilder app --include='*.php'` returns zero results. Rename is clean.

---

### FR-M5 — DataCapturePromptBuilder + CaptureContext VO

**IMPLEMENTED.** `app/Services/AI/Prompts/DataCapturePromptBuilder.php` (5.7 KB); `app/ValueObjects/CaptureContext.php` (3.6 KB). Multi-entity rule block at DataCapturePromptBuilder.php:80.

---

### FR-M6 — FynPersonaRegistry + config/fyn_personas.php

**IMPLEMENTED.** Registry at `app/Services/AI/FynPersonaRegistry.php` (2.7 KB); config at `config/fyn_personas.php` (91 lines, both `advice` and `data_capture` populated with verified tool lists — every tool name exists in both `AiToolDefinitions` and `XaiToolDefinitions`).

---

### FR-M7 — delegate_to_capture / capture_complete tools + HandoffContract

**IMPLEMENTED.** `app/Services/AI/HandoffContract.php` (1.2 KB) defines the constants. Tools registered in both `AiToolDefinitions.php` and `XaiToolDefinitions.php`. Stripping verified at `FynPersonaInvoker.php` handoff-filter loop.

---

### FR-M8 — Classifier fast-path + isAdviceShaped

**IMPLEMENTED.** `QuerySchemas::isAdviceShaped` at `app/Constants/QuerySchemas.php:703`. Orchestrator invokes it when `FYN_CLASSIFIER_FAST_PATH=true`. Verified in `browser-test-conversations.md` Scenario 2 (fast-path fires) and Scenario 3 (`isAdviceShaped` blocks fast-path).

---

### FR-M9 — create_will / update_will + wills schema columns

**IMPLEMENTED.**
- DB columns verified: `executor_name`, `residuary_beneficiary`, `guardian_for_minors`, `specific_gifts` all present on `wills` table (`SHOW COLUMNS FROM wills`).
- Model fillable updated: `app/Models/Estate/Will.php:24-27`.
- Tool definitions: `AiToolDefinitions.php:887` and `XaiToolDefinitions.php:696`.
- Handlers: `CoordinatingAgent::handleCreateWill` at line 2254, `::handleUpdateWill` at line 2299.
- Cache invalidation: `$this->invalidateUserCache($user->id)` called in both handlers (line 2289, 2348).

Deviation caught and logged in `findings-implementation.md`: LPA `status` enum had an extra `revoked` value in the plan which wasn't in the migration; dropped in the shipped code.

---

### FR-M10 — create_power_of_attorney / update against existing LastingPowerOfAttorney

**IMPLEMENTED.**
- Uses existing `App\Models\Estate\LastingPowerOfAttorney` + `LpaAttorney` per AMENDMENTS §C. No new model/migration/controller.
- Tool definitions in both files, tool schema uses `lpa_type` with values `property_financial` / `health_welfare`.
- Handlers at `CoordinatingAgent::handleCreatePowerOfAttorney` (line 2358) and `::handleUpdatePowerOfAttorney`.
- Cache invalidation in place.
- Browser verified in `browser-test-conversations.md` Scenario 6 (LPA id=5 + LpaAttorney row persisted transactionally).

Known LLM-compliance quirk (not a code bug): the LLM dropped `status: registered` in one test — handler saved `status: draft` by default. Logged in CSJTODO.

---

### FR-M11 — `ai_messages.persona` nullable enum

**IMPLEMENTED.** `SHOW COLUMNS FROM ai_messages WHERE Field='persona'` returns `enum('advice','data_capture') YES NULL`. Populated via `AiMessage::create` in both director and orchestrator. Frontend reads it in `browser-test-conversations.md` verification tables.

---

### FR-M12 — `ai_conversations.persona_state` + `onboarding_parked_facts` JSON

**IMPLEMENTED.** Both columns present:
```
persona_state  json  YES  NULL
onboarding_parked_facts  json  YES  NULL
```

Orchestrator reads/writes `persona_state`. Director's `OnboardingFactExtractor` merges into `onboarding_parked_facts`. Verified live in session 3 via `browser-test-onboarding-full-flow.md` parking example (Angela + Sam + Eli parked correctly).

---

### FR-M13 — AiConversationFactory + AiMessageFactory

**IMPLEMENTED.** Both factory files exist. `AiConversation.php:8` and `AiMessage.php:7` import `HasFactory`; classes use `use HasFactory;`.

---

### FR-M14 — STATE_PROFILE_REVIEW_FAMILY / EXPENDITURE

**IMPLEMENTED (state-machine level) / PARTIAL (UX).**
- Constants at `OnboardingStateMachine.php:66` and `:68`.
- State definitions at `:180` (family) and `:243` (expenditure).
- Director extensions emit `onboarding_layout_change { mode: 'standard' }` + `{ mode: 'wide' }` on entry/exit.
- Touches `app/Services/Onboarding/OnboardingChatDirector.php` (7 matches on `profile_review_*`).

**Partial because:** the in-chat summary (ProfileReviewPanel) was spec-required but dropped this session; the new review surface is `UserProfile.vue` reached via the AppLayout route push. The pause visually works after session-3 fixes, but the first-cycle return nav still falls back to `/dashboard` rather than the pre-pause route on some transitions (see Bug B-6 below).

---

### FR-M15 — STATE_BASE_EMPLOYMENT_MORE + Full-time rename + Other removed

**IMPLEMENTED.**
- Constant at `OnboardingStateMachine.php:64`.
- Employment bubbles at `:195` show `['id' => 'employed', 'label' => 'Full-time']` + self_employed / part_time / retired / unemployed. No `'Other'`.
- Multi-job loop verified in `browser-test-onboarding-full-flow.md` Turn 11/12 (Yes loops back, No advances).

---

### FR-M16 — Spouse skip link + action endpoint

**IMPLEMENTED.**
- `skip_link` metadata emitted by director (8 matches).
- Vuex `SET_SKIP_LINK` mutation + getter in `aiChat.js`.
- `AiChatPanel.vue` renders raspberry-500 underlined button.
- Action endpoint handler `handleSkipAction` at `OnboardingChatDirector.php:337`.
- Verified in `browser-test-onboarding-full-flow.md` Edge case — Skip link section.

---

### FR-M17 — Conversational retraction

**IMPLEMENTED (prompt layer) / UNTESTED (end-to-end effect).**
- Retraction block present in `OnboardingPromptBuilder.php:141-146`.
- `update_profile` / `update_record` appended to every focus tool list at line 83.
- The retraction effect (LLM emitting `update_profile` when user says "actually my DOB is 12 March 1985") is covered by the prompt, but was not exercised in any browser test run (session 1 handover noted DOB retraction was flagged but not exercised; session 2 handover noted same; session 3 did not retest).

---

### FR-M18 — POST /action + PreviewWriteInterceptor excluded

**IMPLEMENTED.**
- Route: `routes/api.php:1186` `Route::post('/conversations/{id}/action', [AiChatController::class, 'action'])`.
- Controller method: `AiChatController::action` (referenced at line 373 `$inOnboarding` branch).
- Middleware exclusion: `PreviewWriteInterceptor.php:67` includes `'api/ai-chat/onboarding'` (the broader exclusion covers the action endpoint under the same prefix).
- Verified end-to-end in `browser-test-onboarding-full-flow.md` Edge cases (resume, continue, restart, skip).

---

### FR-M19 — OnboardingFactExtractor + parking merge

**IMPLEMENTED.** `app/Services/Onboarding/OnboardingFactExtractor.php` (11 KB). Director calls `$this->factExtractor->extractAndPark($conversation, $message)` at `OnboardingChatDirector.php:95`. Parking column populated correctly (verified in browser-test Turn 3 — spouse + dependants + marital_status all parked).

**Note:** session 3 patched the internal `parseDateOfBirth` regex so DOB extracts from longer messages (`53f42c0`). This makes the fact-extractor path more reliable.

---

### FR-M20 — Resume flow + welcome-back greeting

**IMPLEMENTED.** `handleResumeAction` at `OnboardingChatDirector.php:276`. Emits welcome-back greeting with `Continue` + `Start over` action bubbles, using `describeStep($step, $user)` for the reference label. Verified in browser-test Edge case — Resume action section.

---

### FR-M21 — FynOnboardingChat.vue + AppLayout blur + router push

**PARTIAL — fixed in session 3, architectural issue remains.**
- Component `FynOnboardingChat.vue` exists.
- Widths are now **712px wide / 356px standard** in `AppLayout.vue:240-245` (session 3 change). This replaces the spec's 896/525 literals — see Report 1 §3.
- Dashboard blur at `AppLayout.vue:236`: `return this.onboardingLayout === 'standard' ? '' : 'filter blur-[4px] pointer-events-none';`
- Router push on layout change at `AppLayout.vue:274-292` (session 3 change). Pushes `/profile` on `standard`, stored route (or `/dashboard` fallback) on `wide`.
- `isOnboardingRoute` check at `AppLayout.vue:216-226` covers both `/onboarding/*` and `isOnboardingActive` Vuex flag (so `/dashboard?openFyn=journey` path works).

**Underlying architectural issue:** `AppLayout` is imported inside each view (`Dashboard.vue:904`, `UserProfile.vue`, etc.), so every route change destroys and remounts `AppLayout` including the docked aside and `AiChatPanel`. Spec line 480 incorrectly assumed `<aside>` is outside `<router-view>`. Session 3 added a mount-time `scrollToLastUserMessage` call in `AiChatPanel.mounted()` as a rescue (`55a13f8`), but the proper fix — hoisting AppLayout above router-view — is outstanding.

---

### FR-M22 — ProfileReviewPanel.vue

**DROPPED this session.** Session 3 commit `d5d1127` removed the import/render from `FynOnboardingChat.vue`. The file remains in the repo but unused. Spec/plan/PRD updated to mark FR-M22 DROPPED.

See Report 1 §1 for the full explanation.

---

### FR-M23 — Vuex personaMode + onboardingLayout state

**IMPLEMENTED.**
- State initialised at `aiChat.js:29-30` (`personaMode: 'advice'`, `onboardingLayout: 'wide'`).
- `SET_PERSONA_MODE` mutation at line 132, `SET_ONBOARDING_LAYOUT` at line 136.
- SSE handlers at line 497 (sendMessage) and line 719 (startOnboardingConversation) commit both.
- Getters present.

---

### FR-M24 — Capturing pill + record cards + preview CTA + placeholder swap

**IMPLEMENTED.**
- Capturing pill "Updating your records" at `AiChatPanel.vue:290-295` (non-docked) and `:577-582` (docked). horizon-500 text on savannah-100, no icon/spinner per CLAUDE.md §14.
- Placeholder swap via `inputPlaceholder` computed at line 714 and `dockedInputPlaceholder` at line 720.
- Preview CTA rendered at `:298-303` and `:585-590` (router-link, raspberry primary style).
- Record cards — capture_complete SSE handler commits records; AiChatPanel renders them via the messages v-for (multiple references at line 10+ to `capture_complete` role in the message renderer).

---

### FR-M25 — Preview-mode advice prompt

**IMPLEMENTED.**
- Advice prompt has a `<preview_mode>` block at `AdvicePromptBuilder.php:140-148`.
- Orchestrator short-circuits preview users before LLM call: `FynPersonaOrchestrator.php:83` and `:128` check `is_preview_user`, emit `preview_cta` SSE at line 375-377.
- Verified in `browser-test-conversations.md` Scenario 4.

---

### FR-M26 — Feature flags

**IMPLEMENTED.** `config/fyn.php`:
```
persona_split_enabled       => env('FYN_PERSONA_SPLIT', false)
classifier_fast_path_enabled => env('FYN_CLASSIFIER_FAST_PATH', true)
capture_max_turns           => env('FYN_CAPTURE_MAX_TURNS', 6)
cancel_patterns             => array
```

Only two user-facing flags as promised in AMENDMENTS §J.

---

### FR-S1 — Observability logs

**IMPLEMENTED.** `Log::info` and `Log::warning` calls present in both `FynPersonaInvoker.php` and `FynPersonaOrchestrator.php`. Orchestrator logs every state transition, handoff interception, preview short-circuit, classifier fast-path decision, cancel pattern match. Confirmed via `audit-pre-test.md` R1 check.

---

### FR-S2 — Weekly drift audit artisan command

**NOT IMPLEMENTED.** No scheduled command exists in `app/Console/Commands/` for fast-path drift auditing. Flagged in `audit-pre-test.md` line 52.

---

### FR-S3 — Rollback mechanics (flag-off fallback)

**IMPLEMENTED.** `AiChatController` 3-way match's `default` branch falls back to `CoordinatingAgent::chat()` when `FYN_PERSONA_SPLIT=false`. Persona_state columns are nullable so flag-off reads work unchanged. Not browser-verified in session 2 or 3, but covered by the `coordinatingAgent` default path already in production.

---

## Part B — April 20 release FRs (previous onboarding wave)

All shipped on `onboardingFyn`, merged into `feature/fyn-persona-split`:

| # | Summary | Status | Evidence |
|---|---|---|---|
| April-M9 | Preview block (middleware) | ✅ | `PreviewWriteInterceptor.php:67` excludes `api/ai-chat/onboarding` |
| April-M10 | DOB-only / marital-only partial capture | ✅ | `OnboardingValueInterpreter::parseDateOfBirth` + `parseMaritalFromText` present; session 3 extended parser to pull dates from longer messages |
| April-M11 | Journey remap (Protecting and growing → protection) | ✅ | `OnboardingPromptBuilder` references `protection` focus; journey handoff verified in `browser-test-onboarding-full-flow.md` |
| April-M12 | Expenditure sync (users.* + ExpenditureProfile) | ✅ | `CoordinatingAgent::handleSetExpenditure` updates both |
| April-M13 | Spouse-email collision exception | ✅ | `SpouseCollisionException` present, `SpouseLinkingService` throws it, director shows distinct error |
| April-M14 | Off-script filter | ✅ | `OnboardingChatDirector::handleAssetCaptureTurn` buffered filter present |
| April-M15 | Trust CLT observer | ✅ | `app/Observers/TrustObserver.php` present |

---

## Part C — Multi-entity fix (plan-multi-entity-capture.md, Phases A/B/C)

### Phase A — Tool description cleanup

**IMPLEMENTED.**
- `grep -c "Do NOT.*call.*other creation tools" Xai/AiToolDefinitions.php` → 0 remaining hits.
- `grep -c "multiple times in the same turn" Xai/AiToolDefinitions.php` → 34 instances (affordances added).
- `create_family_member` no longer says "separate turns" — confirmed.
- `create_property` exclusion narrowed to `navigate_to_page` / `get_module_analysis`.
- `set_expenditure` reframed.

### Phase B — Prompt builder strengthening

**IMPLEMENTED.**
- `OnboardingPromptBuilder::assetCaptureInstructions` has MULTI-ENTITY RULE block at the top (line 97).
- `DataCapturePromptBuilder::captureInstructions` mirrors it (line 80).
- `FcaProcessInstructions` has multi-entity example at line 121.

### Phase C — Advice path fallback

**IMPLEMENTED.** `FcaProcessInstructions::getDataCreationGuidance` softened to "tool(s)" and has a RIGHT multi-entity example.

### Phase D — Verification

**NOT DONE.**
- Browser matrix `browser-test-multi-entity.md` aborted at Turn 6 of the walkthrough (row 1 of the 14-row matrix) in session 2.
- Session 3 re-ran row 1 on branch HEAD + my changes: Aviva life saved, Vitality critical illness dropped. The live bug that motivated the plan is **still present.** See Bug B-1 below.

### Phase D2 — Mocked-LLM feature tests

**NOT DONE.** `tests/Feature/Fyn/MultiEntity/` directory does not exist. Zero automated coverage of the matrix.

---

## Part D — Test coverage

### Unit tests planned (6)

| File | Status |
|---|---|
| `tests/Unit/Services/AI/FynPersonaOrchestratorTest.php` | ✅ |
| `tests/Unit/Services/AI/FynPersonaRegistryTest.php` | ✅ |
| `tests/Unit/Services/AI/FynPersonaInvokerTest.php` | ❌ MISSING |
| `tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php` | ❌ MISSING |
| `tests/Unit/Services/Onboarding/OnboardingFactExtractorTest.php` | ✅ |
| `tests/Unit/ValueObjects/CaptureContextTest.php` | ✅ |

**Score: 4 of 6.**

### Feature tests planned (14)

| File | Status |
|---|---|
| `tests/Feature/AI/PersonaSplit/KycGateFlowTest.php` | ❌ MISSING |
| `tests/Feature/AI/PersonaSplit/InlineCaptureFlowTest.php` | ❌ MISSING |
| `tests/Feature/AI/PersonaSplit/CancelMidCaptureTest.php` | ❌ MISSING |
| `tests/Feature/AI/PersonaSplit/CaptureTimeoutTest.php` | ❌ MISSING |
| `tests/Feature/AI/PersonaSplit/ClassifierFastPathTest.php` | ❌ MISSING |
| `tests/Feature/AI/PersonaSplit/PreviewModeTest.php` | ❌ MISSING |
| `tests/Feature/AI/PersonaSplit/CreateWillToolTest.php` | ❌ MISSING |
| `tests/Feature/AI/PersonaSplit/CreatePowerOfAttorneyToolTest.php` | ❌ MISSING |
| `tests/Feature/Onboarding/ProfileReviewPauseTest.php` | ❌ MISSING |
| `tests/Feature/Onboarding/SpouseSkipTest.php` | ❌ MISSING |
| `tests/Feature/Onboarding/MultiJobCaptureTest.php` | ❌ MISSING |
| `tests/Feature/Onboarding/RetractionTest.php` | ❌ MISSING |
| `tests/Feature/Onboarding/OnboardingResumeTest.php` | ❌ MISSING |
| `tests/Feature/Onboarding/FactParkingTest.php` | ❌ MISSING |

**Score: 0 of 14.** `tests/Feature/AI/PersonaSplit/` directory does not exist. Existing `tests/Feature/Onboarding/` has 5 files — `AssetCaptureMultiEntityTest.php`, `JourneyApiTest.php`, `JourneyFlowTest.php`, `StartOnboardingEndpointTest.php`, `StateMachineWalkthroughTest.php` — all pre-existing; none of the six new UX tests the plan called for.

**Combined test score: 4 of 20 planned files present. 16 missing.**

---

## Part E — Open bugs

### B-1 — Multi-entity capture still broken

**Severity:** P0.
**Symptom:** User message "I have Aviva life insurance £300,000 and Vitality critical illness £100,000" → only Aviva `LifeInsurancePolicy` row created; no `CriticalIllnessPolicy` row. Fyn's TEXT reply claims both recorded (hallucination).
**Reproduction:** session 3 today, testgamma@example.com at `asset_capture(protection)`.
**Expected:** 2 tool calls (`create_protection_policy × 2` or `create_life_insurance_policy` + `create_critical_illness_policy`). Actual: 1 tool call.
**Root cause:** Prompt layer says "emit multiple tool calls per turn" but the live xAI model is still emitting one. Phases A/B/C prompt changes are NECESSARY BUT NOT SUFFICIENT.
**Planned fix:** Not yet planned. Needs prompt-effectiveness investigation, possibly a mocked-response test harness, possibly a deterministic post-LLM fallback that looks at Fyn's ack text and emits the missing tool calls.
**Fix status:** Open.

### B-2 — Spouse capture doesn't set household_id

**Severity:** P1.
**Symptom:** After `base_spouse` capture completes (Laura, id=166), both primary user (testgamma id=165) and spouse (id=166) have `household_id = NULL`.
**Reproduction:** Session 3 today, testgamma@example.com after spouse turn.
**Impact:** The whole "plan together" value prop depends on this link.
**Fix status:** Open. Not attempted this session.

### B-3 — FamilyMember.age column NULL

**Severity:** P1.
**Symptom:** For Laura (spouse), Sam (child), Emily (child) — DOB is saved correctly but the `age` column is NULL. Any UI code reading `age` will show blanks.
**Reproduction:** Session 3 today.
**Fix status:** Open.

### B-4 — Children DOB defaulted to YYYY-01-01 when age provided

**Severity:** P2.
**Symptom:** "Sam aged 8 and Emily aged 5" → Sam DOB saved as `2018-01-01`, Emily as `2021-01-01`. Ages are correct; calendar dates are approximations.
**Fix status:** Open. Behaviour-by-design per capture handler; acceptable if the user confirms at the profile-review pause.

### B-5 — Chat scroll rewinds to start of conversation on pause transitions (mostly fixed)

**Severity:** Was P1 in session 3 (user blocker), partially fixed.
**Symptom:** Entering or leaving a profile-review pause caused `AiChatPanel` to remount (see Report 1 §5) and scrollTop reset to 0, showing the welcome message instead of the current prompt.
**Fix shipped:** `55a13f8` — `AiChatPanel.mounted()` now anchors to the last user bubble via `scrollToLastUserMessage()`.
**Residual risk:** the watcher approach in `AiChatPanel` for `onboardingLayout` changes also scrolls, but that path only fires for same-route layout flips, which are rare.
**Fix status:** Mitigated (mount-time fix). Root architectural cause (AppLayout remount on every route change) is not fixed.

### B-6 — First-pause return navigation fell through on session 2/3 test

**Severity:** P2.
**Symptom:** First profile-review pause cycle — on `mode='wide'` return — the router did not push back to pre-pause route; stayed on `/profile` until the next route change.
**Mitigation:** Session 3 added `/dashboard` fallback in the watcher. Subsequent cycles verified working.
**Root cause:** `preProfileRoute` stored on AppLayout's `data()` is lost when AppLayout remounts between pause entry and pause exit (Report 1 §5). The fallback papers over this.
**Fix status:** Mitigated. Proper fix requires the AppLayout refactor.

### B-7 — LPA status=registered dropped by LLM

**Severity:** P2 (LLM compliance).
**Symptom:** Logged in `browser-test-conversations.md` Scenario 6 and CSJTODO. User says "registered", handler saves `status: draft` by default.
**Fix status:** Open. Tool-description tightening recommended but not done.

### B-8 — Advice Fyn prefers `navigate_to_page` + `fill_form` over `delegate_to_capture`

**Severity:** P2 (LLM compliance).
**Symptom:** `browser-test-conversations.md` Scenario 3. When user asks "add my SIPP, is that enough?", advice Fyn navigates to the retirement page and pre-fills the form instead of emitting `delegate_to_capture`. Not a bug per se (advice has `navigate_to_page` in its allowed tools), but it sidesteps the persona split's main mechanism.
**Fix status:** Open. Prompt tightening recommended.

### B-9 — Data-capture prompt guardrail soft on format

**Severity:** P2 (LLM compliance).
**Symptom:** `browser-test-conversations.md` Scenario 2 observation. Data-capture persona emits multi-paragraph advice text alongside the capture. The "one-sentence acknowledgment" guardrail in `DataCapturePromptBuilder` is not being enforced.
**Fix status:** Open. Either tighten the prompt or add a post-stream filter mirroring FR-M14's off-script filter.

---

## Part F — Three session-3 fixes already landed

| Fix | Commit | Summary |
|---|---|---|
| Profile-review pause routes to /profile | `0812300` | AppLayout watcher pushes `/profile` on `standard`, back to pre-pause route (or `/dashboard` fallback) on `wide`. Widths normalised to 712/356. |
| Drop in-chat ProfileReviewPanel | `d5d1127` | UserProfile.vue is the review surface now; no duplicate in-chat summary. Spec/plan/PRD updated. |
| DOB multi-field first-turn parsing | `53f42c0` | `OnboardingValueInterpreter::parseDateOfBirth` now extracts date substrings from longer messages and defaults UK DMY for slashed dates. "My DOB is 12 March 1985 and I am married" now captures both fields in a single turn via the parked-facts hydration path. |
| Chat scroll anchors to latest turn on mount | `55a13f8` | `AiChatPanel.mounted()` calls `scrollToLastUserMessage()` if Vuex shows user bubbles exist. Rescues the remount case where scrollTop=0 showed the welcome. |

---

## Part G — Net delivery status

| Area | Planned | Delivered | Notes |
|---|---|---|---|
| Must-have FRs (M1–M26) | 26 | 26 code-present | 3 have known bugs (B-1 multi-entity, B-2 spouse household, B-5/B-6 layout lifecycle mitigated) |
| Should-have FRs | 3 | 2 | FR-S2 drift audit not built |
| Unit tests | 6 | 4 | FynPersonaInvokerTest + DataCapturePromptBuilderTest missing |
| Feature tests | 14 | 0 | Entire `tests/Feature/AI/PersonaSplit/` directory absent |
| Multi-entity D1 matrix rows | 14 | 0 run to completion, 1 failed | Phase A/B/C shipped but ineffective against live model |
| Session 3 today | Fix profile-review pause + ProfileReviewPanel + DOB drop + scroll rewind | 4 commits, 4 bugs mitigated; 5 bugs still open | See B-1..B-9 |

**The release is not deploy-ready as-is.** Key blockers:

1. B-1 multi-entity — the original top-priority bug is still present.
2. Zero Feature tests — no automated guard against regression on any of the 14 new persona-split scenarios or the 6 new onboarding UX scenarios.
3. Architectural cost — FR-M21's "aside outside router-view" assumption is wrong, papered over with mount-time rescues.
4. B-2 spouse household linking — silent data fidelity bug that undermines a primary value prop.

Recommended order: fix B-1 first (the original task), then add automated Feature tests to guard the 20 missing behaviours, then tackle B-2/B-3/B-4 data fidelity, then the AppLayout refactor.
