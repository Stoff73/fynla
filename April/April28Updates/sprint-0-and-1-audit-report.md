# Sprint 0 + Sprint 1 audit — pre-regression checkpoint

> **Branch:** `feature/fyn-persona-split` — 218 commits ahead of `origin/main`, working tree clean.
> **Generated:** 2026-04-28 (session 111, evening) at `/effort max`, before re-running BS-NN scenarios.
> **Trigger:** CSJ saw a parked-memory regression in onboarding ("married to agnela" → Fyn asked for spouse's name despite parked-memory and a GREEN BS-06). Pause Sprint 1, audit Sprint 0+1 deltas before re-walking S0.16 BS-NN.
> **Scope:** Compare what the spec/plan asked, what CSJTODO + plan status tables reported as DONE, what the code/git actually contains, and the deltas. Identify likely root cause(s) of the Angela regression. Stop and wait for CSJ instructions.

---

## 0. Executive summary

| Question | Answer (short) |
|---|---|
| Is Sprint 0 reported done? | Yes — all 17 task headings ticked (S0.1–S0.17), plus 30 explicit recovery sub-tasks (S0.5.r through S0.5.z). |
| Is Sprint 0 actually done? | **Largely yes** in code, but **two structural gaps** — (i) BS-06 was browser-tested only on the marital-status leg, never on the spouse-name leg that the Angela regression hits; (ii) the rubric-a-score landed at 12/40 not 13–15, with three "cusp" dimensions. |
| Is Sprint 1 reported done? | Partially. S1.1, S1.3, S1.4, S1.5, S1.6.b, S1.8 ticked. S1.6.a deliberately **REMOVED** (advice_response panel). S1.2.l, S1.2.k, S1.7 (a–i), S1.9, S1.10 OPEN. |
| Is Sprint 1 actually done where ticked? | Yes for the listed commits, but the **focus drifted**: sessions 102–110 (last 6 days) were almost entirely on the eval workstream (HTTP-driven rewrite, Doc A audit, mitchell scenarios), not on Sprint 1's S1.7 deliverables. |
| Did anything outside Sprint 0/1 land? | Yes — newsletter signup feature (PR #238 on `feature/phailanx/news-rss-lifecycle-emails`), shipped on a parallel branch by sessions 71/72. Not part of Sprint 0/1, but in flight. |
| Is the Angela regression a true regression? | **Most likely no — it's a discovery, not a regression.** BS-06 + S1.4 only proved parked-facts flush + `<known_facts>` injection on the *marital_status* path. The spouse-name leg (parked-fact → known_facts → LLM honours instruction) was never browser-tested end-to-end with the user typing the spouse's name in the personal turn. The regex (`OnboardingFactExtractor::extractSpouse:132`) requires a capitalised first letter; "agnela" with a lowercase "a" simply does not match. Even if the user typed "Angela" capitalised, the LLM is still soft-asked not to repeat — the contract leans on prompt instruction, not deterministic suppression. Three plausible failure modes detailed in §5. |
| Should we re-walk all BS-NN? | **Not yet.** The branch is fine; the bug is real but narrow. Recommendation in §6 — first triage the regression with one targeted Playwright walk + DB inspection, decide whether it is a code fix or a test-coverage fix, then decide whether the BS-NN re-walk is justified. A full 17-scenario re-walk against unchanged code would burn ~half a day and is unlikely to surface anything beyond the Angela case. |

---

## 1. What was ASKED (specs and plans)

Source documents — all live in `April/April24Updates/spec/` and `April/April24Updates/plan/`:

- `spec/00-canonical.md` (48 lines) — two-Fyn contract verbatim. **Onboarding Fyn writes; Advice Fyn is read-only.** Canonical §0.
- `spec/01-invariants.md` (500 lines) — ~35 falsifiable invariants. INV-2.2.6 (parked-facts flush), INV-2.2.3 (no-repeat-ask), INV-2.11.1 (`<known_facts>` block) are directly relevant to the Angela regression.
- `spec/03-test-strategy.md` (647 lines) — dual-layer Pest + Playwright BS-NN strategy.
- `plan/10-sprint-0-plan.md` (1,665 lines) — 16 tasks for two-Fyn collapse + reliability + audit chain. Goal: Rubric-A 13–15/40 🔴 → 🟠.
- `plan/11-sprint-1-plan.md` (691 lines, then expanded) — eval harness + 3-store memory model + `<known_facts>` + `search_conversation_index` + advice_response SSE. Goal: Rubric-A 17–18/40 🟠. **Expanded mid-flight** by `April27Updates/eval-expectations-rewrite.md` to 58 YAMLs across 9 categories.

### Sprint 0 ask — 16 + 1 verification tasks

| Task | Ask in one line |
|---|---|
| S0.1 | Rebase persona-split onto main; resolve ~16 conflict hotspots. |
| S0.2 | Delete OpenAI config + Python sidecar + agent.token middleware. |
| S0.3 | Two-Fyn collapse: create `AdviceFyn`; extend `OnboardingChatDirector::handleInlineCapture`; **delete** `FynPersonaOrchestrator` + `Invoker` + `Registry` + `DataCapturePromptBuilder`; rewrite `AiChatController::sendMessage` dispatch. |
| S0.4 | Remove `persona_state_change` SSE + capturing pill + conditional placeholder. |
| S0.5.a–p | Convert 16 of 17 `fill_form` handlers to direct-write (DB::transaction). |
| S0.5.q | Coverage / observer / rollback tests. |
| S0.6 | Billing tools: `get_subscription_status`, `list_invoices`, `get_current_plan`. |
| S0.7 | `update_record` allowlist + strict schema. |
| S0.8 | `delete_record` two-phase confirmation. |
| S0.9 | Consent runtime check. |
| S0.10 | User-content sanitisation + structural separation. |
| S0.11.1–6 | Reliability bundle: atomic token budget, SSE abort, idempotency middleware, provider-swap lock, gap-fill DB dedup, generateTitle sanitation. |
| S0.12 | Hash-chain audit migration + service + verify-chain command + retention job + admin view. |
| S0.13 | CoreIdentity rewrite + FCA signposting suffix. |
| S0.14 | Out-of-remit canonical refusal. |
| S0.15 | Coverage tests for 7 small invariants — INV-2.2.4/5/6, 2.4.3, 2.6.1/2, 2.7.4. |
| S0.16a | Browser harness skeleton + 20 BS-NN stubs (no execution). |
| S0.16b | Interactive Playwright execution of all 20 BS-NN scenarios. |
| S0.16c | Re-walk BS-01, 02, 04, 06, 07, 10 after AiChatPanel refactor. |
| S0.17 | Sprint 0 verification rollup — Rubric-A re-score 13–15/40 🟠. |

### Sprint 1 ask — 10 tasks (later expanded)

| Task | Ask in one line |
|---|---|
| S1.1 | Eval harness scaffold + 9 scenario directories + 2 architecture meta-tests. |
| S1.2 | First 10 scenarios (6 query-type + 4 multi-entity) + JSONL fixtures. |
| S1.3 | Conversation index schema + `ConversationSummariserJob`. |
| S1.4 | `MemoryRetrieverService` (4-layer) + `<known_facts>` block injection. |
| S1.5 | `search_conversation_index` tool. |
| S1.6 | `advice_response` SSE + `AdviceResponsePanel.vue`. |
| S1.7 | Expand to 30 scenarios — 16 query-type + 6 multi-entity + 5 handoff + 3 cancel/timeout + 2 prompt-injection. |
| S1.8 | Advice Fyn response-mode + engine-call-level classifiers. |
| S1.9 | Sprint 1 Playwright matrix — BS-03, 08, 09, 24 + BS-01–23 regression. |
| S1.10 | Sprint 1 verification rollup — Rubric-A 17–18/40 🟠. |

The expansion (April 27, session 102) added:
- S1.2.l — rewrite all 10 existing YAMLs against the actual classifier shape.
- S1.7.a–j — 6 new helpers, 6 architecture meta-tests, 4 canonical scenarios, 14 state-machine YAMLs, 14 handoff YAMLs, 16 resume YAMLs, hard-gate verification doc.

---

## 2. What was REPORTED done (CSJTODO + plan status table)

### Sprint 0 — reported 100% done

The plan status table (`10-sprint-0-plan.md` lines 72–124) shows every S0.X with `[x]`. Recovery sub-tasks added during execution:

- **S0.5.r/s/t/u/v/w/x/y/z** — 9 BS-driven rollups discovered while running S0.16b. All ticked. Each carries a delivery note explaining the bug uncovered and the fix.
- **S0.16b** — final tally: **17 GREEN** (BS-01, 02, 04, 06, 07, 10, 11, 12, 13, 14, 15, 16, 17, 19, 20, 21, 23) · **1 PARTIAL** (BS-18, third assertion deferred to post-deploy on Apache) · **1 DROPPED** (BS-22 — no UI consent toggle exists) · **1 DEFERRED** (BS-05 → moved to post-sprint priorities).
- **S0.16c** — re-walked 6 (BS-01, 02, 04, 06, 07, 10) post-AiChatPanel refactor (`ffc9c3f`). All GREEN.
- **S0.17 verification rollup** — session 96 (2026-04-26):
  - Pest: 2,972 passed / 12,549 assertions / 0 failures / 412.79s.
  - Architecture: 16 passed / 303 assertions / 0 failures.
  - `php artisan ai:audit:verify-chain` → `chain_valid: true, tip_hash: 36251a0fcc03a986692bf16c450da1f8b21587fb82e48cdd6b3d503fc88561ab, row_count: 76`.
  - Rubric-A re-score: **12/40 🔴 Pre-launch** — one point shy of the 13–15 target. D4, D6, D7 each one sub-criterion away from level 3.

### Sprint 1 — reported partial

| Task | Reported state | Notes |
|---|---|---|
| S1.1 | ✅ DONE | `30ca5fa` — harness + 9 dirs + 2 meta-tests. |
| S1.2.a–j | ✅ DONE | 10 YAMLs authored; 1 fixture recorded; eval recording infra (forensic store, admin viewer, model-aware fixtures, eval:record artisan). |
| S1.2.k | ⏸ DEFERRED | 9 fixtures pending — discovered the YAML expectations themselves are wrong against the live classifier. |
| S1.2.l | ⏸ OPEN | Rewrite 10 YAMLs against per-classification REQUIRED_TOOLS + multi-label classifier shape. |
| S1.3 | ✅ DONE | `425c54f` — `ConversationSummariserJob` + 5 new index columns + scheduler with carve-out. |
| S1.4 | ✅ DONE | `425c54f` — `MemoryRetrieverService` (4 layers) + `renderKnownFactsBlock` injected into 3 prompt builders. |
| S1.5 | ✅ DONE | `425c54f` — `search_conversation_index` tool. |
| S1.6.a | ❌ REMOVED (session 101) | `e373505` — advice_response panel deleted; "every component must justify its existence". The structured-output value lives in S1.6.b instead. |
| S1.6.b | ✅ DONE | `edfd2dd` — per-agent tool-result contract + `ToolResultContract` + agent-side `missing_for_quality_advice`. |
| S1.7 | ❌ NOT STARTED | All 9 sub-tasks open. Rewrite report is published; no rewrite work shipped. |
| S1.8 | ✅ DONE | `74ead16` — response-mode + engine-call-level static maps. |
| S1.9 | ❌ NOT STARTED | Sprint 1 Playwright matrix (BS-03, 08, 09, 24 + regression). |
| S1.10 | ❌ NOT STARTED | Sprint 1 verification rollup. Hard-gated on S1.7 + S1.9. |

### What sessions 102–110 actually did (the recent 6 days)

| Session | Date | Work |
|---|---|---|
| 102 | 27 Apr | Eval expectations rewrite report (1,690 lines, 14 sections); CSJTODO + tech-debt; CLAUDE.md metrics; commits `517875b`, `c29ea2a` (re-record session #21), `89611d4` (legacy delta restore), `d5f5ebb` (calibrate YAMLs), `1dfcb3c` (S1.2.l), `e00c540` (S1.7.d Path A). |
| 103–107 | 27–28 Apr | Eval HTTP-driven rewrite — 16 commits authoring `EvalHttpDriver`, `EvalSseConsumer`, `EvalAuthController`, `EvalTraceCollector`, `EvalTraceListener`, `EngineCalled`/`GateChecked`/`AgentDecision` events, JSON Schema, mitchell JSON scenarios, dashboard panels, persona-aware preview reset. |
| 108 | 28 Apr morning | maxAuditEval.md audit; canonical 0.1 + 0.2 corrections; remove pre/post-flight persona reset from EvalHttpDriver. |
| 109 | 28 Apr late | maxAuditEval P0 + P1 + P2 ship; cache-trace-via-file; SSE key fix; HTTP count = 4; **bonus engineering fix** — `AdvicePromptBuilder` no longer pays full-orchestrate cost on every chat send (eval was correctly flagging wasted compute). |
| 110 | 28 Apr evening | §5.4(a) wire 13 EngineCalled emits; §5.6 reset orchestration; §4.1 Doc A surgical edits; mitchell_advice_protection_cover side-by-side green. |

Bottom line: **the last 6 days were eval scaffolding, not Sprint 1 acceptance work.** S1.2.k, S1.2.l, S1.7.a–i, S1.9, S1.10 have NOT moved.

### Out-of-scope work also shipped this week

- Sessions 71–72 (parallel branch `feature/phailanx/news-rss-lifecycle-emails`, PR #238) — RSS news hub + landing-page restoration + newsletter double-opt-in subscribe + admin panel + 23 tests. Not Sprint 0/1, not on `feature/fyn-persona-split`. Pending dev deploy.
- Multiple older PRs still open (#212, #214 onboardingFyn, #220 tech-debt, #221, #223). Memory `project_pr214_with_persona_split.md` notes #214 may be superseded by `feature/fyn-persona-split`.

---

## 3. What is ACTUALLY DONE (verified against code + git)

### Sprint 0 — code-verified

| Artefact | Verified | Evidence |
|---|---|---|
| `AdviceFyn.php` | ✅ exists | `app/Services/AI/AdviceFyn.php` |
| `OnboardingChatDirector::handleInlineCapture` | ✅ exists | grep'd in `app/Services/Onboarding/OnboardingChatDirector.php`. |
| `FynPersonaOrchestrator/Invoker/Registry/DataCapturePromptBuilder` | ✅ deleted | grep returns 0 hits in `app/`. |
| `OnboardingFactExtractor` | ✅ exists | 5-bucket regex extractor. **§5 — primary suspect for the Angela regression.** |
| `MemoryRetrieverService` | ✅ exists | 4-layer fall-through; injected into `OnboardingPromptBuilder::buildAssetCapturePrompt`, `OnboardingChatDirector::buildGroupedExtractPrompt:1551`, `AdvicePromptBuilder::build`. |
| `ai_audit_events` migration + service + verify-chain command | ✅ exists | `1d61a47`. |
| 16 direct-write handlers in `CoordinatingAgent` | ✅ shipped | 16 commits between `b7a881d` and `87bb04f`. |
| BS-NN stubs (20 files) | ✅ exist | `tests/Browser/scenarios/BS-{01,02,04,05,06,07,10–23}-*.php`. BS-03, 08, 09, 24 deliberately absent (Sprint 1 territory). |
| BS-NN screenshots | ✅ committed | `docs/sprint-0-verification/BS-{01..23}/` populated. BS-06 has 6 from session 87 + 2 from session 95 (`s95-*.png`). |
| Rubric-A score doc | ✅ exists | `docs/sprint-0-verification/rubric-a-score.md` — 12/40. |

### Sprint 1 — code-verified

| Artefact | Verified | Evidence |
|---|---|---|
| `tests/Feature/Fyn/Eval/` harness | ✅ exists | scaffold + 9 category dirs. |
| 10 YAMLs in `01-query-types/` + `03-multi-entity/` | ✅ exist | original + post-rewrite. |
| Mitchell JSON scenarios | ✅ exist | `f13208c` — 10 mitchell scenarios; superseded YAMLs deleted. |
| `EvalHttpDriver`, `EvalSseConsumer`, `EvalAuthController`, `EvalTraceCollector` | ✅ exist | `3378f03`, `ab00ded`, `84e43c7`, `8e0bb16`. |
| `MemoryRetrieverService::renderKnownFactsBlock` | ✅ exists | line 303. Injects with `Do not ask the user for any field above.` suffix. |
| `search_conversation_index` tool | ✅ exists | both Anthropic + xAI definitions; handler in `CoordinatingAgent`. |
| `AdviceFyn::classifyResponseMode` + `engineCallLevel` | ✅ exists | static maps; 15 unit tests. |
| `ToolResultContract` | ✅ exists | + 6 module agents emit `missing_for_quality_advice`. |
| `advice_response` panel | ❌ deleted | as planned in S1.6.a (session 101). YAMLs still reference dead `expected_advice_response` keys. |
| 6 new architecture meta-tests for YAML integrity | ⚠️ 5 of 6 | `dc962f0`. Missing 6th — likely `EvalScenarioTimingBudgetIsPathAwareTest` (per session-110 todo). |
| AssertionHelpers extension (S1.7.a) | ❌ not started | new keys (`expected_response_mode`, `expected_kyc_state`, etc.) not in helpers. |
| 4 new canonical-behaviour YAMLs (S1.7.c) | ❌ not started | none authored. |
| 14 state-machine YAMLs (S1.7.e) | ❌ not started | none authored. |
| 14 handoff YAMLs (S1.7.f) | ❌ not started | none authored. |
| 16 resume YAMLs (S1.7.g) | ❌ not started | none authored. |
| BS-03, BS-08, BS-09, BS-24 (S1.9) | ❌ not started | files do not exist in `tests/Browser/scenarios/`. |
| Sprint 1 verification doc (S1.10) | ❌ not started | no `docs/sprint-1-verification/` directory. |

---

## 4. DELTAS — reported done vs actually done

### 4.1 Sprint 0 — small delta

| Item | Reported | Actual | Delta |
|---|---|---|---|
| Rubric-A score | 13–15/40 🟠 target | 12/40 🔴 actual | **−1 point** below target. Three cusp-of-level-3 dimensions (D4, D6, D7) named in the rubric doc. Recommended fix from rubric doc: author `docs/audit-retention-policy.md` (single page) to push D4 to 13/40. **Not done.** |
| BS-18 acceptance | "PARTIAL GREEN" | Same | Third assertion deferred to one post-deploy walk on csjones.co/fynla. **Not done.** |
| BS-22 | "DROPPED — no UI consent toggle" | Same | Memory `feedback_ai_chat_consent_no_toggle.md` confirms intent. No code work outstanding. |
| BS-05 | "DEFERRED to PSP-LS / PSP-S" | Same | Lifestyle landing pages workstream — not part of Sprint 0 scope. |
| Migration deploy debt | Not flagged in plan | Sessions 71/72 found 7 pending migrations from main + 9 persona-split migrations + 4 eval recording migrations. Session 109 ran them locally. | These are local-only — production hasn't seen any of the persona-split migrations. **Sprint 3 deploy-time concern.** |

### 4.2 Sprint 1 — large delta

The plan status table is honest about S1.7 / S1.9 / S1.10 being unstarted. The delta is in **focus**, not in **truth-claiming**:

- The April 27 expansion turned S1.7 from "20 more YAMLs" into "48 new + 10 rewrites + asserter + dashboard delta + 6 meta-tests + hard-gate doc". That is a **>3x scope inflation**.
- Sessions 102–110 spent 6 days on the eval **infrastructure** (HTTP driver, trace collector, persona reset orchestration, dashboard panels) and 0 days on the S1.7 YAML deliverables.
- The eval workstream surfaced and fixed three real engineering issues (cache-trace via file, unconditional orchestrate-on-every-chat, SSE event key) — those are **wins**, not waste. Memory `feedback_evals_surface_engineering_issues.md` was saved on 28 Apr to cement the principle.
- But the S1.10 acceptance criteria (Rubric-A 17–18/40 🟠 Limited beta) cannot be reached without S1.7 + S1.9. Sprint 1 is **not** mergeable today.

### 4.3 Cross-sprint deltas (where memory and code disagree)

| Memory entry | Says… | Code says… | Verdict |
|---|---|---|---|
| `project_eval_http_driven_rewrite_branch.md` | 16/16 tasks shipped commits; canonical-clean. 4 P0/P1 defects block Task 16. Session 108. | Sessions 109 + 110 cleared all 4 P0/P1 + ship §5.4(a) + §5.6. | Memory **stale** by 2 sessions. Should be updated to "Acceptance gate GREEN on mitchell_advice_protection_cover; 9 mitchell scenarios pending re-record; S1.7.a is the next bottleneck." |
| `feedback_eval_canonical_contract.md` | Reset only on actual data change; Sanctum bypass token IS the mechanism. Issued 2026-04-28. | `EvalRecordCommand::resetPersonaIfMutating` ships in `184fa4c`; matches contract. | **Aligned.** |
| `feedback_advice_fyn_is_read_only.md` | AdviceFyn = zero write tools; writes flow via `delegate_to_capture` → `handleInlineCapture`. | `AdviceFyn::WRITE_TOOLS` constant strips writes; deterministic `WriteIntentClassifier` in `S0.5.w` routes around the LLM. | **Aligned.** Contract intact. |

---

## 5. The Angela regression — root cause analysis

### 5.1 The user-reported behaviour

> "I started an onboard through the fyn cta, and typews in **married to agnela**, ans Fyn promptly asked for my spouses name, despite a confirmation that parked memory is in place, the test for this was green and everything was in order."

### 5.2 What the test actually proves

`tests/Feature/Onboarding/ParkedFactsFlushTest.php` (5 cases, S0.15.3) and BS-06 (Bryony Stoneleigh, sessions 87 + 95) only assert:

- `marital_status` parking + flush at `STATE_BASE_PERSONAL`.
- Sibling buckets (spouse, dependants, employment, expenditure) survive a personal commit.
- Bucket clears to NULL when last bucket is flushed.

**Neither test exercises the spouse first-name parking → known_facts → LLM-honours-instruction path.**

The BS-06 docblock makes this explicit (lines 60–74): *"`OnboardingFactExtractor::extractPersonal` only parks `marital_status`, `age_hint`, and `date_of_birth` — it does NOT parse first_name from free-text"*. The stub even rejects the original assertion that asserted otherwise as describing *"a behaviour that does not exist in production"*. The test was rewritten to assert only what the personal extractor actually does.

So when CSJ says "the test for this was green" — yes, the test that exists is green. **There has never been a test for "user types `married to <Name>`, system parks the name, system does not re-ask".** That contract is **only** covered by S1.4's `MemoryRetrieverService` + the soft `<known_facts>` instruction in the LLM prompt.

### 5.3 Code path for "married to agnela" at base_personal

1. `AiChatController::sendMessage` dispatches to `OnboardingChatDirector::handleUserMessage` (because `users.onboarding_completed = false`).
2. `handleUserMessage:90` saves the message.
3. `handleUserMessage:101` runs `OnboardingFactExtractor::extractAndPark($conversation, $message)`.
4. Inside `extractAndPark`, `extract($message)` runs all 5 bucket extractors:
   - `extractPersonal` matches `\bmarried\b` → `personal.marital_status = 'married'`. ✅ Parked.
   - **`extractSpouse:132` runs `/\b(?:married\s+to|wife|husband|spouse|partner)\s+([A-Z][a-z]{2,20})\b/`** — note **`[A-Z]`**.
     - If the user typed **"Angela"** with a capital A → match → `spouse.first_name = 'Angela'`. Parked.
     - If the user typed **"angela"** or **"agnela"** with a lowercase a → **NO match**. **Spouse bucket NOT parked.**
5. State is `STATE_BASE_PERSONAL`, turn_type is `grouped_extract`.
6. `hydrateFromParking` runs — only hydrates personal bucket (`buildPersonalInputFromParking:1145`); does not write `users.first_name` for spouse and is unrelated to the spouse bucket.
7. If `personal.date_of_birth` is also parked, the personal capture commits and the state advances to `STATE_BASE_SPOUSE`. **In the user's case, no DOB was given**, so the LLM is invoked via `handleGroupedExtractTurn` → `buildGroupedExtractPrompt(STATE_BASE_PERSONAL, 'capture_personal_details')`. The LLM gets the `<known_facts>` block injected and is asked to extract DOB + marital_status from "married to agnela".
8. The LLM either:
   - Extracts `marital_status='married'`, leaves DOB empty, and returns an extraction-error → director's retry message *"I still need date of birth"* → state stays at `STATE_BASE_PERSONAL`. The user's spouse's name does NOT cause Fyn to ask for spouse's name yet.
   - **OR** the LLM, seeing "married to agnela" and the `<known_facts>` block possibly containing `spouse_first_name: "Angela"` (only if the regex matched), generates a conversational follow-up like *"Got that you're married. What's your date of birth, and what's your spouse's name?"* — even though spouse's name is in known_facts.

### 5.4 Three plausible failure modes (in decreasing likelihood)

**(A) — Lowercase typo killed the regex.** The user's literal typing *"agnela"* (with a lowercase 'a' due to typo or because the chat input does not auto-capitalise) does not match `[A-Z][a-z]{2,20}`. The spouse bucket is **never parked**. `<known_facts>` does not contain `spouse_first_name`. Fyn correctly asks for spouse's name at `STATE_BASE_SPOUSE`. **This is the most likely cause and is a discovery, not a regression.** No previous test covered it.

**(B) — LLM ignored the known_facts soft instruction.** The user typed "Angela" capitalised, the regex matched, the spouse bucket parked, `<known_facts>` injected. The LLM is *instructed* not to ask for fields in known_facts (suffix: *"Do not ask the user for any field above."*) but instructions are not invariants. xAI grok-4-1-fast, in particular, is documented in CSJTODO and `April27Updates/eval-system-vs-live-flow-audit.md` as not always honouring soft prompt cues. **This is the failure mode CSJ has repeatedly named** ("we DO NOT rely on the LLM" — S0.5.w architectural finding, session 87). The deterministic-server-side write classifier (`WriteIntentClassifier` in S0.5.w) was the response on the write side; there is **no equivalent** on the onboarding-prompt-suppression side.

**(C) — A real regression in MemoryRetrieverService injection.** `git log -- app/Services/AI/MemoryRetrieverService.php` shows only one commit (`425c54f`, the S1.3+S1.4 ship). `git log -- app/Services/Onboarding/OnboardingFactExtractor.php` shows only one commit (`b7204ff`, the original Phase 11 ship). `git log -- app/Services/Onboarding/OnboardingChatDirector.php` since `425c54f` shows two changes: `dbdaa77` (resume conversation lookup pivots on metadata.source) and `5b65a7b` (multi-word first_name preserve). Neither touches the parking → known_facts pipeline. **Regression in the pipeline itself is unlikely.** A regression could exist in `buildGroupedExtractPrompt` ordering — e.g. if the known_facts block lands AFTER the `<instructions>` heredoc, the LLM may treat the instructions as dominant. Lines 1561–1582 show the block is concatenated *after* the heredoc, which is by design and is the same shape as what shipped GREEN on session 95.

**(D) — User typed before STATE_BASE_PERSONAL.** If the user typed "married to Angela" while still on `STATE_PATH_CHOICE` or `STATE_JOURNEY_SELECTION`, `extractAndPark` still fires (line 101 runs unconditionally), but the state handler does not advance — `interpretAnswer` would mark the path-choice answer as un-parseable, retry without advancing, and the parked spouse fact would survive into base_spouse. This case actually *should* work — the spouse bucket would be in `<known_facts>` when the user later reaches `base_spouse`. **Unless (B) is also at play.**

### 5.5 What the regression is NOT

- Not a database/seed issue — the mechanism is purely in-memory between `extractAndPark` and `renderKnownFactsBlock`.
- Not a refactor regression from `ffc9c3f` — that touched `AiChatPanel.vue` only.
- Not an eval-workstream regression — sessions 102–110 only modified eval infrastructure + `AdvicePromptBuilder::buildFinancialContext` cache key + `analyzeRelevantModules`. None of those code paths run during onboarding.

### 5.6 To confirm the cause without fixing yet

One targeted Playwright walk:

1. Fresh registration via `/register?from=fyn` → MFA → dashboard.
2. At `STATE_BASE_PERSONAL`, type **exactly** "I was born on 1 May 1979 and I'm married to Angela" (capital A, with DOB so the state will advance).
3. Inspect `ai_conversations.onboarding_parked_facts` BEFORE the next turn — should contain `spouse: {first_name: "Angela"}` (mode A confirmed if absent).
4. Inspect the `<known_facts>` block written into the LLM prompt for the next turn — should contain `spouse_first_name: "Angela"`.
5. Observe Fyn's `STATE_BASE_SPOUSE` prompt:
   - If Fyn does NOT ask for the name → contract honoured, the original CSJ regression was mode (A) lowercase typo.
   - If Fyn DOES ask for the name → mode (B) LLM-soft-instruction failure. This is the deterministic-suppression gap CSJ flagged conceptually in S0.5.w.

Repeat with lowercase "angela" to confirm mode (A) directly.

---

## 6. Recommendations (before re-walking S0.16 BS-NN)

The user's instinct ("re-run all S0.16 BS-NN scenarios from the beginning") is **likely overkill** given the evidence:

- The branch has not been touched in any onboarding code path since session 95 (the last BS-NN re-walk).
- The only changes since are:
  - `425c54f` — S1.3 + S1.4 + S1.5 + S1.6.a (added MemoryRetrieverService, removed advice_response panel).
  - `e373505` — S1.6.a removal.
  - `edfd2dd` — S1.6.b ToolResultContract.
  - `74ead16` — S1.8 classifiers.
  - 4 multi-word first-name + resume-lookup fixes (`5b65a7b`, `dbdaa77`, etc.).
  - All eval-workstream commits (no production-code touch beyond `AdvicePromptBuilder::buildFinancialContext` cache key + `analyzeRelevantModules`).
- No commits modify `OnboardingFactExtractor`, `OnboardingChatDirector::handleUserMessage`, the parked-facts state map, or the AiChatPanel since BS-NN went GREEN.

### Recommended ordering

1. **Confirm cause first** — one Playwright walk (§5.6) to isolate mode (A) vs (B) vs (C). 30 minutes max.
2. **If mode (A)** — the lowercase regex sensitivity is real but is a discovery, not a regression. Two narrow fixes are reasonable:
   - Lowercase the input copy before the spouse regex match (mirror existing personal/dependants which are case-insensitive via `/i` flag).
   - Add a Pest case to `tests/Unit/Services/Onboarding/OnboardingFactExtractorTest.php` for `married to angela`, `married to ANGELA`, `wife angela`, `husband DAVE`.
   - Add a BS-NN amendment (or a new BS-26 stub) for "user types name lowercase, parking still catches it".
3. **If mode (B)** — this is the LLM-soft-instruction architectural gap. Two options, neither narrow:
   - **(B1)** Add a deterministic pre-LLM gate in `handleGroupedExtractTurn` for `STATE_BASE_SPOUSE`: if `spouse_first_name` is in known_facts, skip the LLM round-trip entirely and synthesise a `capture_spouse_details(first_name=<parked>)` tool call with `dob`/`email` left empty for the user to fill in the next turn. Mirrors the `WriteIntentClassifier` pattern from S0.5.w.
   - **(B2)** Tighten the prompt — make the `<known_facts>` instruction harder, e.g. *"REQUIRED: do not include any of the fields above in your response. If you do, the user will see a duplicate question and lose trust."* Soft. Fragile.
4. **If mode (C)** — wider blast radius. Investigate `buildGroupedExtractPrompt` carefully and the LLM payload before assuming.
5. **Then, only if cause is fixed**, re-walk **only BS-06** with the spouse-name-lowercase amendment to prove the fix. Skip the other 16 — they would simply re-pass and burn half a day. The other BS-NN cover unrelated invariants.
6. **Schedule S1.7 + S1.9 + S1.10** — these are the genuine sprint-1-acceptance gaps. The eval-workstream sprint is functionally complete (acceptance gate green for mitchell_advice_protection_cover both providers); the remaining work is YAML authoring + 6th meta-test + 4 BS-NN scenarios.

### What I have NOT done (and will not until you say)

- No code changes.
- No browser walks.
- No DB inspection.
- No git diff against any other branch.
- No memory updates.
- No new TaskCreate stack beyond the audit tasks I just completed.

### Open questions for CSJ

1. Did you type **"Angela"** capitalised or **"agnela"** with a lowercase 'a'? (Decides A vs B in §5.4.)
2. Did the message contain a date of birth, or only the marital line? (Decides whether the state advanced past BASE_PERSONAL.)
3. Did Fyn's reply specifically say "What's your spouse's name?" or did it say something more like *"I got that you're married. What's your date of birth, and what's your spouse's name?"* — the second is the LLM mid-extraction-retry pattern at base_personal, not a known_facts violation at base_spouse. (Decides whether known_facts was even consulted.)
4. Do you want the BS-NN re-walk regardless? If so I will run them all; the recommendation above is to scope it down, but you own the decision.

---

## 7. Stop point

Per CSJ instruction: *"Before you start testing this, I want a full review of what has been done… write the report to '/Users/CSJ/Desktop/fynla/April/April28Updates' with all deltas, before we start testing, then stop and wait for my instructions."*

Report written. Standing by for your call.
