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

*Last updated: 27 April 2026 — session 100 end.*

*Session 100 shipped Sprint 1 S1.3 + S1.4 + S1.5 + S1.6.a end-to-end, plus a tracking-block addition to `April/April24Updates/plan/11-sprint-1-plan.md` (mirroring Sprint 0's format). 19 new files, ~2,000 LOC, 44/44 Pest in the new test files (138 assertions). 2 commits on `feature/fyn-persona-split` pushed to origin (`a41143c` eval fixture re-record, `425c54f` Sprint 1 features).*

*Previous session: 27 April 2026 — session 99 end (Tasks 1, 2, 3, 3b — eval/live divergence fix, commit `279bd9b`). The Task-5 expectation ("re-record remaining 9 fixtures") is now formally deferred per the architectural blocker note added to the Sprint 1 plan; recording resumes after S1.6.b lands.*

---

## Session 100 — Sprint 1 progressed S1.3 → S1.6.a

### Completed this session

#### Eval recording verification (continuation of session 99)

- [x] Re-recorded `advice_protection_cover` (eval session #18, both providers live) — confirmed Tasks 1, 2, 3, 3b held: both Anthropic Haiku 4.5 and xAI grok-4-1-fast-reasoning correctly call `get_module_analysis` (was 2× FAIL in session 98). Captured prompt verified with `<financial_context>` + `<existing_records>` + `<data_completeness>` PRESENT, `<new_user_state>` + `<billing_guidance>` ABSENT. Commit `a41143c`.
- [x] **Architectural-blocker discovery — output-shape contract gap.** CSJ inspection of session #18 surfaced cross-provider response drift (Anthropic produced bullet-structured "missing data" list, xAI produced flat prose). Traced to: `CoordinatingAgent::summariseToolAnalysis` (line 3298) silently drops everything outside a 15-key whitelist before the LLM sees it; `StructuredResponseValidator` is a regex scrubber not a structure validator (own docblock line 21 admits this); response shape is governed by soft prompt cues, not a contract. Documented in plan §"Output-shape contract — S1.6 scope extension".

#### Sprint 1 plan tracker (mirroring Sprint 0 format)

- [x] Added `## Status (updated 2026-04-27)` block to `April/April24Updates/plan/11-sprint-1-plan.md` between pre-flight gate and S1.1, with S1.1–S1.10 + S1.2.a–S1.2.k sub-rows + commit SHAs. Synced to `fynlaBrain/April/April24Updates/plan/`.

#### S1.3 — Conversation index schema + summariser job

- [x] Migration `database/migrations/2026_05_02_000001_add_conversation_index_columns.php` adds 5 columns to `ai_conversations` (`summary`, `topics`, `entities_mentioned`, `intents_stated`, `summarised_at` + index).
- [x] `app/Models/AiConversation.php` — extended `$fillable` + `$casts`.
- [x] `app/Services/AI/ConversationSummariser.php` — service uses xAI grok-4-1-fast-non-reasoning with structured `response_format: json_object`. Failures logged + skipped (regenerable data).
- [x] `app/Jobs/ConversationSummariserJob.php` — `ShouldQueue` wrapper, dispatched on `STATE_DONE` from `OnboardingChatDirector::emitDoneTurn`.
- [x] `app/Console/Commands/SummariseStaleConversationsCommand.php` — `ai:conversations:summarise-stale` artisan command, scheduled every 30 minutes via `Console\Kernel`.
- [x] **Resume-contract carve-out**: scheduler skips conversations whose owner has `onboarding_completed=false` AND `metadata.source='fyn_onboarding'` so an idle mid-flow onboarding never gets summarised before the user resumes (canonical Two-Fyn §0 invariant explicitly tested).
- [x] 8/8 Pest in `tests/Feature/AI/ConversationIndexPopulationTest.php`.

#### S1.4 — `MemoryRetrieverService` + `<known_facts>` block

- [x] `app/Services/AI/MemoryRetrieverService.php` — 4-layer fall-through (authoritative DB → parked facts → current conversation extractor re-run → conversation index from prior summarised conversations). `renderKnownFactsBlock` produces canonical `<known_facts>` block with `Do not ask the user for any field above.` suffix.
- [x] Injected into `OnboardingPromptBuilder::buildAssetCapturePrompt`, `OnboardingChatDirector::buildGroupedExtractPrompt`, and `AdvicePromptBuilder::build` (Layer 3d). `HasAiChat::buildSystemPrompt` accepts conversation + passes through.
- [x] 11/11 Pest in `tests/Unit/Services/AI/MemoryRetrieverServiceTest.php`.
- [x] 4/4 Pest in `tests/Unit/Services/Onboarding/KnownFactsBlockTest.php`.
- [x] Eval scenario `09-canonical-behaviour/memory-no-repeat-ask.yaml` authored (recording deferred per S1.2.k).

#### S1.5 — `search_conversation_index` tool

- [x] Added to `AiToolDefinitions` (Anthropic) + `XaiToolDefinitions` (xAI, `strict: true`) with `topic_keywords[]` + `entity_types[]` parameters.
- [x] `CoordinatingAgent::handleSearchConversationIndex` — queries `AiConversation::forUser → whereNotNull('summary') → whereJsonContains('topics', …) | whereJsonContains('entities_mentioned', ['type' => …])`, capped at 10, ordered by `last_message_at` desc, excludes active conversation.
- [x] NOT in `AdviceFyn::WRITE_TOOLS` — naturally read-only in advice mode.
- [x] 10/10 Pest in `tests/Feature/AI/SearchConversationIndexTest.php`.
- [x] Eval scenario `09-canonical-behaviour/cross-conversation-surface.yaml` authored (recording deferred per S1.2.k).

#### S1.6.a — `advice_response` SSE event + `AdviceResponsePanel.vue` (Phase 1)

- [x] `app/Services/AI/AdviceResponseSchema.php` — strict validator (REQUIRED_KEYS, VALID_PRIORITIES, FCA_SIGNPOSTING constant). Throws on any structural drift.
- [x] `app/Services/AI/AdviceResponseSchemaException.php`.
- [x] `app/Services/AI/AdviceResponseComposer.php` — pure-function composer maps `orchestrateAnalysis` engine output → `headline / key_figures / breakdowns / recommendations / next_steps / signposting`. Priority normalisation across int + string inputs; recommendations capped at 5; next_steps deduped + capped at 5.
- [x] `AdviceFyn::wrapStream` intercepts upstream `done` event for advice-type classifications, calls composer + validator, yields `advice_response` before `done`. Schema failures log + skip — never break the stream.
- [x] `resources/js/components/Shared/AdviceResponsePanel.vue` — design-system compliant (raspberry/violet/savannah/neutral priority badges, no decorative icons per CLAUDE.md Rule #14).
- [x] `aiChat.js` `case 'advice_response':` appends a `role: 'advice_response'` message with whole event as metadata.
- [x] `AiChatPanel.vue` registers component + renders branch through existing `handleNavigation`.
- [x] 11/11 Pest in `tests/Feature/Fyn/AdviceResponseSseShapeTest.php`.

### NOT done — outstanding

#### S1.6.b — Per-agent output contract (next up)

- [ ] Replace `CoordinatingAgent::summariseToolAnalysis` 15-key whitelist (`extractKeyMetrics` line 3320) with a per-agent typed shape. Each `Agent::analyze()` returns first-class `missing_for_quality_advice` + structured gaps. `summariseToolAnalysis` returns the agent output verbatim, schema-checked against the per-agent contract.
- [ ] Replace `StructuredResponseValidator` (regex scrubber, "logs and flags") with a real blocking validator on tool results.
- [ ] Closes the lossy-summarisation half of the output-shape contract gap. Once landed alongside S1.6.a, S1.2.k unblocks naturally.
- [ ] Tracked as S1.6.b in `April/April24Updates/plan/11-sprint-1-plan.md` Status block.

#### Browser verification of S1.6.a (not yet done)

- [ ] **CRITICAL — `AdviceResponsePanel.vue` rendering NOT browser-tested.** Code-level signal is GREEN (Pest 11/11) but the visual rendering of headline / key-figures grid / breakdowns / recommendations / next-steps / signposting has not been verified in Playwright per CLAUDE.md browser-testing rules. Drive an advice-type query (e.g. "Am I covered enough for protection?") on `john@example.com` locally and confirm the panel renders with the right design-system classes (raspberry CTAs, savannah-100 tiles, italic horizon-400 signposting).

#### Sprint 1 in plan order

- [ ] **S1.7** — Expand eval to 30 scenarios (depends on S1.2.k unblock — i.e. after S1.6.b lands).
- [ ] **S1.8** — Advice Fyn response-mode classifier (`classifyResponseMode` + `engineCallLevel` static maps over every `QuerySchemas` constant).
- [ ] **S1.9** — Sprint 1 Playwright matrix: BS-03 (known-facts no-repeat-ask), BS-08 (advice factual net-worth), BS-09 (advice recommendation ISA — exercises `advice_response` SSE + Vue panel), BS-24 (cross-conversation-surface) + regression of BS-01 to BS-23.
- [ ] **S1.10** — Sprint 1 verification rollup (Pest GREEN + Rubric-B Mode 1 30/30 + Browser 24/24 + Rubric-A re-score 17-18/40 🟠).

#### S1.2.k — Re-record remaining 9 fixtures (still deferred)

- [ ] `advice_savings_emergency`, `advice_investment_isa`, `advice_retirement_contribution`, `advice_estate_iht`, `advice_goals_affordability`, `protection_2x_known_providers`, `protection_2x_unknown_providers`, `savings_3x_mixed`, `pensions_2x_schemes`. Picks up naturally after S1.6.b ships the per-agent output contract.

---

## Context for Next Session

**Branch:** `feature/fyn-persona-split` — clean working tree (only pre-existing untouched files in `.claude/`, `CSJ-CAMPAIGN-LANDING-PLAN.md`, `docs/manuals/` — none from this session).

**Two commits pushed today:**
- `a41143c` — `chore(eval): re-record advice_protection_cover fixture (session #18)`
- `425c54f` — `feat(fyn): Sprint 1 S1.3 + S1.4 + S1.5 + S1.6.a`

**Next session should pick up at S1.6.b** — the per-agent output contract that closes the architectural blocker. Source of truth: `April/April24Updates/plan/11-sprint-1-plan.md` Status block + "Output-shape contract — S1.6 scope extension" footer note. The plan is gitignored — vault mirror at `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/plan/11-sprint-1-plan.md` (synced this session).

**Mandatory pre-work for next session:**

1. Read this file top-to-bottom.
2. Read `April/April24Updates/plan/11-sprint-1-plan.md` Status block + "Output-shape contract — S1.6 scope extension" footer.
3. Read `app/Agents/CoordinatingAgent.php` lines 3298 + 3320 — the lossy `summariseToolAnalysis` + `extractKeyMetrics` 15-key whitelist that S1.6.b is replacing.
4. Read each module agent's `analyze()` to scope the typed-array contract:
   - `app/Agents/ProtectionAgent.php`
   - `app/Agents/SavingsAgent.php`
   - `app/Agents/InvestmentAgent.php`
   - `app/Agents/RetirementAgent.php`
   - `app/Agents/EstateAgent.php`
   - `app/Agents/GoalsAgent.php`
5. Run `php artisan db:seed --force` (CLAUDE.md mandatory pre-flight).
6. Confirm Pest baseline holds via targeted runs on the touched suites; full sweep optional.

---

## Pest baseline (deferred from session 99 — still applies)

3+2 = 5 pre-existing failures all sharing the same root cause: `App\Agents\CoordinatingAgent::classifyComplexity(): Argument #2 ($conversationDepth) must be of type int, null given, called in /Users/CSJ/Desktop/fynla/app/Traits/HasAiChat.php on line 130`.

Failing tests:
- `tests/Feature/AI/AssistantHonestyOnWriteFailureTest::it AdviceFyn passes assistant honesty text through unchanged when a write tool fails`
- `tests/Feature/AI/ConsentRuntimeCheckTest::it allows sendMessage to stream when ai_chat consent is granted`
- `tests/Feature/AI/ConsentRuntimeCheckTest::it emits consent_required SSE and closes the stream when consent is withdrawn`
- `tests/Feature/Fyn/AdviceFynRoutesWritesViaHandoffTest` (2 tests — same root cause)

Cause: in-memory `AiConversation` whose `message_count` is null (default Laravel cast doesn't fill non-DB defaults). Fix: either set `message_count = 0` on the in-memory conversation in those test setups, or change the `classifyComplexity` signature to accept `?int $conversationDepth = 0` and coerce. Verified pre-existing by stashing session-99 changes and reproducing — also re-verified pre-existing in session 100 with my S1.6 changes stashed. Not blocked by any S1.x work.

---

## Tech debt — deferred

Session 100 deferred the `/tech-debt-session` audit. Substantial new surface (~2,000 LOC across 19 files) deserves its own focused turn rather than rushing it as part of session-end. Run before merging `feature/fyn-persona-split → dev`. Files in scope are everything in commit `425c54f`.

---

## Deploy status

- **Production (`fynla.org`):** main untouched this session.
- **Dev (`csjones.co/fynla`):** dev untouched this session.
- **`feature/fyn-persona-split`:** pushed to origin via 2 commits today. NOT deployed anywhere yet — sits behind the deferred `feature → dev` PR.

When the next deploy happens (whenever feature → dev merges), the migration `2026_05_02_000001_add_conversation_index_columns.php` runs and adds 5 cols + index to `ai_conversations`. Reseed not required for that migration. The new `ai:conversations:summarise-stale` schedule entry runs every 30 min via Laravel scheduler — confirm `php artisan schedule:list` shows it after deploy.

---

## Pattern reminder for ALL BS-NN runs (do not deviate)

1. Sign out + clear browser session storage (or use the seeded john path for advice-mode-only tests).
2. Landing page → "Quick start with Fyn" CTA → fresh registration with a unique email (when an end-to-end onboarding walk is required).
3. Verify MFA via the pending registration's `verification_code` from DB. Type each digit individually with `browser_press_key`.
4. Drive bubbles + buttons via `browser_click` against the FynQuickReplies button `ref` from `browser_snapshot`. NEVER `browser_evaluate(...).click()`.
5. Free text via `browser_type` against textarea `ref` + `submit:true`.
6. After ANY code change, re-test from Step 1.
