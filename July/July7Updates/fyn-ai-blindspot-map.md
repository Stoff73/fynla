# Fyn / AI Blindspot Map — 2026-07-07

Six parallel read-only exploration passes over dev tip `e16ea5f` (worktree audit; no code changed).
Domains: prompts/corpus/evals · advice loop/output · gating/KYC · write-states/campaigns · recommendations/actions · compliance/memory.
Every finding carries `file:line` evidence from the agents' passes. Tags: [severity][confidence].

**Companion doc:** `fyn-ai-prompting-playbook.md` (how to prompt Claude to fix each cluster).

---

## 0. The ×80 repetition bug — complete causal chain (now fully understood)

The csjones incident (`ai_messages` id 19079: one gate-refusal paragraph ×80, 3 identical `get_tax_information` calls) is not one bug. It is **five layers failing in sequence**, and every layer is independently fixable:

1. **Classifier cross-contamination** — "Am I on track for retirement?" matched `RETIREMENT_READINESS` (primary) AND the over-broad `GOALS_PROGRESS` pattern `/\b(am\s+i|are\s+we)\s+on\s+track\b/i` (`QuerySchemas.php:367`) as secondary → `goals` entered the KYC module set (`QuerySchemas.php:759`).
2. **KYC all-modules blocking** — `KycGateChecker` blocks the WHOLE turn if ANY classified module is blocked (`KycGateChecker.php:64-79,235`). Goal-less user → goals gate → turn-level "Do NOT give advice". Meanwhile Layer 7 said "Retirement: READY (90.9%)" — the model saw READY and BLOCKED simultaneously.
3. **Impossible instruction set** — the prompt MANDATED `get_tax_information(pension_allowances)` + `(state_pension)` (`QuerySchemas.php:453-457` → `AdvicePromptBuilder.php:1160`) while the KYC block ordered it to call `navigate_to_page` — **a tool stripped from the advice catalogue** (`AdviceFyn.php:190,516`). The model was told to advise-not-advise, call-tools-don't-advise, and use a tool it cannot see.
4. **Loop amplification (xAI-only)** — on each tool-call continuation the assistant history message is built from the **accumulated** `$fullResponse` (`HasAiChat.php:556-558`), so iteration 2 is fed iteration 1's repetition as its own prior output; temp 0, no `frequency_penalty`/`presence_penalty`/`stop` (`HasAiChat.php:308-316`); no identical-tool-call dedupe (`:572-812`); ~26 copies/completion × 3 iterations ≈ 80. The Anthropic branch rebuilds content blocks fresh and does NOT have this.
5. **No output backstop** — `StructuredResponseValidator` is log-only, no repetition rule (`StructuredResponseValidator.php:206-235`); `AckSentenceDeduper` (the one deduper) runs only for `data_capture` persona (`HasAiChat.php:918`). **The authored fix exists and is OFF**: overlays `a1-answer-first.md` + `a2-ack-hygiene.md` ("never stacked, concatenated, or repeated acknowledgements") both `active: false` (`fyn-memory/procedural/system_prompt_overlay/general/*:6`).

**Fix bundle (pending CSJ decisions D1–D3):** narrow the GOALS_PROGRESS pattern / make KYC block primary-module-only · resolve the navigate_to_page contradiction · per-iteration history rebuild on the xAI branch (match Anthropic) · identical tool-call dedupe · repetition collapse in sanitise() · activate a1/a2 + regen golden masters · add a graded eval scenario for this class.

---

## 1. Instruction–tool incoherence (the model is set up to fail)

| # | Finding | Evidence | Tag |
|---|---------|----------|-----|
| 1.1 | KYC blocks on ANY secondary-matched module; "on track" drags goals into every retirement/holistic question | `QuerySchemas.php:367`, `KycGateChecker.php:64-79,235` | [CRIT][high] |
| 1.2 | KYC/blocked instructions + completeness lines + canExecuteTool blocked-results all order the model to call `navigate_to_page` — stripped from the advice catalogue | `KycGateChecker.php:263-270`, `AdvicePromptBuilder.php:1066`, `CoordinatingAgent.php:903` vs `AdviceFyn.php:190,516` | [HIGH][high] |
| 1.3 | Dead routes fed to the model: readiness form_links (`/profile/personal`, `/retirement/pensions`, `/investment/risk-profile`, `/estate/assets`, …) don't exist in the router; Savings is the only module with real routes; /m routes even worse (no `/profile`, `/valuable-info` equivalents) | readiness services *:37-177; `PrerequisiteGateService.php:327`; `resources/mobile/router.js` | [HIGH][high] |
| 1.4 | `assessAll` omits Goals+Tax → prompt fabricates "Goals: READY (100% complete)" from the `?? 100` default; BLOCKED lines lose detail | `PrerequisiteGateService.php:341-350`, `:315` | [HIGH][high] |
| 1.5 | KYC universal (employment_status + expenditure hard-block ALL advice) contradicts module readiness (same fields warning-only) → READY+BLOCKED in one prompt | `KycGateChecker.php:115-152` vs `RetirementDataReadinessService.php:232,267` | [MED][high] |
| 1.6 | Scenario gate checks `run_what_if_scenario` — the real tool is `create_what_if_scenario`; gate is dead code | `PrerequisiteGateService.php:199` vs `XaiToolDefinitions.php:83` | [HIGH][high] |
| 1.7 | `canExecuteTool` pass-by-default: only 3 tools gated; explicit create_* list reads exhaustive but isn't | `PrerequisiteGateService.php:195-213` | [MED][high] |
| 1.8 | Fyn `generate_financial_plan` bypasses the freemium tier gate the REST holistic API enforces | `routes/api.php:1055,1412`, `CoordinatingAgent.php:953` | [MED][med] |
| 1.9 | `EmptyDataGuard` is dead code under unified — zero-data users have no explicit guard block | `EmptyDataGuard.php` (0 callers), `AdvicePromptBuilder.php:162-164` | [LOW][med] |

## 2. Advice-loop robustness

| # | Finding | Evidence | Tag |
|---|---------|----------|-----|
| 2.1 | xAI-branch accumulated-history bug (see §0.4) — root amplifier | `HasAiChat.php:556-558` | [CRIT][high] |
| 2.2 | No identical tool-call dedupe; no repetition guard anywhere; no decoder penalties | `HasAiChat.php:572-812,308-316`; validator `:206-235` | [CRIT][high] |
| 2.3 | Empty completion persists an empty assistant row + still emits done → blank bubble on reload | `HasAiChat.php:836,922,967` | [HIGH][med] |
| 2.4 | Silent mid-stream truncation: clean provider close mid-completion → mid-sentence answer persisted, no marker | `HasAiChat.php:373` | [MED][high] |
| 2.5 | `tool_use` progress SSE emitted, consumed by NEITHER surface — silent gap during multi-tool turns | `HasAiChat.php:588,807`; no handler in web//m | [MED][high] |
| 2.6 | Planner adds a full extra LLM round-trip per advice turn; slow planner = up to ~120s on "thinking" | `FynLoop.php:157-231`, `Planner.php:141-176`, `XaiClient.php:67` | [MED][med] |
| 2.7 | Inflight lock (300s) vs turn duration race → two live streams possible; stuck `processing` rows wedge the conversation (TTL sweep only expires `queued`) | `AiChatController.php:207,412,479`; `ConcurrentTurnQueue.php:137` | [MED][med] |
| 2.8 | Retry: one-shot, iteration-0-only, no jitter | `HasAiChat.php:517-523` | [LOW][med] |

## 3. Surface parity decay (web vs /m SSE contract)

| # | Finding | Evidence | Tag |
|---|---------|----------|-----|
| 3.1 | /m collapses `token_limit` / `consent_required` / `handoff_error` / `error.message` to one generic "trouble responding" | `onboardingChat.js:220-305,361`, `api.js:88-108` | [HIGH][high] |
| 3.2 | Web never renders `level_up` from chat SSE (no case; `queueCelebration` uncalled) — /m-only celebration | `AiChatController.php:302-313`; `aiChat.js` switch; `gamification.js:32` | [HIGH][high] |
| 3.3 | `capture_write_result` (write-failure signal) dropped by BOTH frontends; advice→inline-capture failures depend solely on LLM narration (WP-1 P0 only met on delegated path) | `HasAiChat.php:770-776`, `OnboardingChatDirector.php:4003-4150` vs `:2909-2920` | [HIGH][high] |
| 3.4 | /m drops `entity_created`/`capture_complete` — capture confirmations invisible on mobile | `onboardingChat.js:220-305` | [HIGH][med] |
| 3.5 | /m `onboardingActive` predicate omits the `fyn_step!==null` term — diverges from every backend seam | `onboardingChat.js:47-49` vs `AiChatController.php:958-963` | [HIGH][high] |
| 3.6 | `base_spouse` skip affordance (`skip_link`) unhandled on /m — married /m users can't skip spouse capture | `onboardingChat.js`; `aiChat.js:641`; StateMachine 421-424 | [MED][med] |

## 4. Write-state hygiene & campaigns

| # | Finding | Evidence | Tag |
|---|---------|----------|-----|
| 4.1 | **#615 follow-up needed**: wizard/quick/skip completion methods null `fyn_step` but NOT `active_campaign`/`onboarding_fyn_path`/`selection`/`paused_at_step` — chat-led completions clear all; stale `active_campaign` re-opens /m onboarding chrome + 409 on /start | `OnboardingService.php:1054-1099` vs `OnboardingChatDirector.php:3442-3446,3889-3893` | [HIGH][high] |
| 4.2 | Dispatch predicate is centralised (`routesToOnboardingDirector`) but carries an `active_campaign` OR-disjunct the canonical 3-part predicate doesn't document | `AiChatController.php:958-963`; `00-canonical.md` | [MED][high] |
| 4.3 | No user-level lock: fresh simultaneous /start on 2 surfaces creates 2 onboarding conversations; director state writes can interleave with advice turns (`$user->refresh()` mid-turn) | `AiChatController.php:207,789`; `OnboardingChatDirector.php:326,2952` | [MED][med] |
| 4.4 | Legacy funnel rows default to the savetax campaign write flow (`?? 'savetax'`) | `AiChatController.php:727-732` | [LOW][high] |
| 4.5 | Kill-switch mid-flow (`fyn_flow_enabled=false`) parks capture users in advice silently; `FYN_PROMPT_ARCH=legacy` turns in-flight write-handoffs into security refusals (known memory) | `AiChatController.php:645,962`; `OnboardingChatDirector.php:4026-4031` | [LOW][med] |
| 4.6 | `handleInlineCapture` unknown entity_types falls back to `?? 'savings'` framing (mis-route, not deflect) | `OnboardingChatDirector.php:4029-4031` | [MED][low] |
| 4.7 | `skipIfNotEmployed` tests `['full_time','part_time']` but the bubble id is `'employed'` — employed savetax users may silently skip workplace-pension capture (hinges on `parseEmploymentFromText` normalisation — VERIFY) | `OnboardingStateMachine.php:461,1414,2073-2076,2130` | [MED][low] |
| 4.8 | `emitDoneTurn` unconditionally overwrites `onboarding_completed_at` on re-entry (sibling method guards) | `OnboardingChatDirector.php:3887-3896` vs `:3402,3448` | [LOW][high] |
| 4.9 | Duplicate-checker "all exist" short-circuit can drop the genuinely-new account in a mixed existing+new message | `RecordDuplicateChecker.php:74-91`; `OnboardingChatDirector.php:2662-2676` | [LOW][med] |

## 5. Caches, staleness & the three retirement engines

| # | Finding | Evidence | Tag |
|---|---------|----------|-----|
| 5.1 | `investment_analysis_{uid}` orphaned from `CacheInvalidationService` — Fyn-written investment data stale up to 24h on every surface incl. Fyn's own tools; web-form writes fresh (controller clears explicitly) | `CacheInvalidationService.php:79-84`; `CoordinatingAgent.php:157,957-958`; `InvestmentAgent.php:70,362-366` | [HIGH][high] |
| 5.2 | THREE retirement-income engines: agent 4%-SWR (`PensionProjector.php:210-217`) vs Monte Carlo (`RetirementProjectionService.php:141+`) vs materialised scheme quote (`RetirementPlanService.php:331`, `PensionStore.php:452`) → the observed £26,000 (web plan card) vs £25,764 (/m card) is real engine divergence, not rounding | see refs | [HIGH][med-high] |
| 5.3 | Fyn prompt caches (`ai_financial_context` 120s / `ai_existing_records` 60s) invalidated ONLY by Fyn's own write path — UI edits leave Fyn answering from stale context | `AdvicePromptCacheInvalidator` single call site `CoordinatingAgent:1082` | [MED][high] |
| 5.4 | Observer→agent invalidation targets keys agents never write (`v1_*` vs `{module}_analysis_`); freshness survives only via the Coordinating override → fragile | `RecommendationCacheObserver.php:52`, `BaseAgent.php:93-97` | [MED][med] |
| 5.5 | Request-scoped `ComposedTaxPlanService` memo can voice a pre-capture plan inside one turn (synthesis vs /tax-strategy disagreement) | `ComposedTaxPlanService.php:40`, `OnboardingChatDirector.php:1145-1152` | [MED][med] |
| 5.6 | `MobileDashboardAggregator` docblock says 5-minute cache; constant is 86400 (24h) | `MobileDashboardAggregator.php:30,36,59` | [MED][high] |

## 6. Recommendations & actions integrity

| # | Finding | Evidence | Tag |
|---|---------|----------|-----|
| 6.1 | Fyn tool payloads carry banned `*_score` fields (contract REQUIRES `adequacy_score`); no tool-path equivalent of `removeScores()` → Rule-12 leak vector into chat | `ToolResultContract.php`; `CoordinatingAgent.php:1770-1802,1922` | [HIGH][med] |
| 6.2 | `removeScores()` allowlist misses many emitted keys (`efficiency_score`, `tax_efficiency_score`, `completeness_score`, `urgency_score`, `drift_score`, …) → /m UI leak | `ModuleSummaryController.php:114-123` | [MED-HIGH][high] |
| 6.3 | Web `GET /recommendations` never merges `RecommendationTracking` → completed actions reappear pending; `?status=completed` empty; contradicts WP-2 one-model contract | `RecommendationsController.php:42`; `RecommendationsAggregatorService.php:302`; `NextActionsService.php:271-286` | [MED-HIGH][high] |
| 6.4 | Raw-path `recommendation_id = sha1(module|text)` — figure-bearing copy → id churn → completed reappears + points re-award; goals always takes this path | `RecommendationsAggregatorService.php:57-61,293`; `RecommendationTrackingObserver.php:29` | [MED-HIGH][med] |
| 6.5 | Gated modules: web index shows empty; /m shows unlock cards — different emptiness semantics | `RecommendationsAggregatorService.php:64`; `NextActionsService` | [MED][high] |
| 6.6 | Milestone nudge asymmetry: capture turn acknowledges only ISA/estate/mortgage detectors; net-worth/goal milestones mint on dashboard read → Fyn silent on a milestone the dashboard celebrates | `OnboardingChatDirector.php:4131-4145`; `MilestoneDetectionService.php:231` | [LOW-MED][med] |

## 7. Compliance enforcement & data lifecycle

| # | Finding | Evidence | Tag |
|---|---------|----------|-----|
| 7.1 | ALL regulatory rules are prompt-only: adviser signpost, no-product-recs, hedging, risk warnings, no-market-timing, tax-from-tool — zero server backstop; validator never blocks (by design docstring) | `ComplianceRules.php:34-40`; `StructuredResponseValidator.php:22-23` | [HIGH][high] |
| 7.2 | Emoji/acronym/jargon violations detected but shipped anyway (sanitise doesn't strip them) | `StructuredResponseValidator.php:100-127` vs `:206-235` | [MED][high] |
| 7.3 | Nobody reads violation logs — no review queue/dashboard; `AdviceReviewService` is a data-change detector, not compliance review | metadata single-writer; `AdvicePromptBuilder.php:1087` | [LOW][high] |
| 7.4 | Auto retention purge (7yr) misses `ai_messages`/`ai_conversations`/`ai_advice_log`/`point_awards`/`ai_abort_events`/funnel+onboarding columns/per-user semantic facts; user soft-delete → FK cascades never fire | `RetentionPurgeService` getDeletionOrder | [HIGH][high] |
| 7.5 | Three erasure paths (self-service, `fyn:user:erase`, retention purge) — none complete, none composed; self-service deletes NO chat/memory data | `AccountDeletionService.php:87-126`; `FynUserErase.php` | [HIGH][med] |
| 7.6 | "Delete my Data" nulls 3 columns, claims full deletion | `GDPRController.php:570-575` | [MED][high] |
| 7.7 | ai_messages verbatim `system_prompt`+`assembled_context` double-stored with the episodic blob; cold-archive saves nothing on the hot DB | migrations; `EpisodeBlobData.php:50-56`; `FynEpisodicColdArchive.php:14` | [MED][high] |
| 7.8 | Tool-result channel re-injects user-controlled strings (account nicknames) UNsanitised — bypasses `UserContentSanitiser::wrap` | `HasAiChat.php:790-819` vs `AdvicePromptBuilder.php:756-996` | [MED][med] |
| 7.9 | Episodic store has no figure guard (pointer-model violation, latent while rubric is `draft`); learning promotion path is flag-agnostic + figure-unvalidated | `FynMemoryStore.php:209-239`; `SemanticFactPromoter.php:20` | [MED][med] |
| 7.10 | Audit chain: append failures swallowed → a never-appended event is undetectable | `CoordinatingAgent.php:1041-1052`; `HasAiChat.php:1042-1045` | [LOW][med] |

## 8. Verification infrastructure holes

| # | Finding | Evidence | Tag |
|---|---------|----------|-----|
| 8.1 | Five eval categories are empty shells (prompt-injection, regulatory, provider-parity, cancel-timeout, preview-personas); repetition class has no graded scenario | `tests/Feature/Fyn/Eval/scenarios/**` | [HIGH][high] |
| 8.2 | Cassettes + config default = `grok-4.3`; production runs grok-4-1-fast; provenance test skipped → Mode-1 replay evals don't cover the prod model | `config/services.php:42`; fixtures dirs; provenance test :36,96 | [MED][high] |
| 8.3 | Corpus parity test compares tool NAMES only — live `.md`/`.xai.md` divergence exists now (`current_account` default xai-only); PR-#582 bug class can recur green | `ToolCatalogueParityTest:11-41`; savings corpus files | [HIGH][high] |
| 8.4 | Under xAI, onboarding/campaign extraction tools are built from the ANTHROPIC `.md` corpus (provider default, no fallback) → `.xai.md` fixes never reach live capture turns | `AiToolDefinitions.php:151,408-417`; `ProceduralCorpus::active:43-49` | [HIGH][med] |
| 8.5 | Write-surface gate reasons over the Anthropic catalogue while runtime serves xAI; missing `.xai.md` degrades silently | `ToolActionMapper:31,71-75`; `XaiToolDefinitions:141-146` | [MED][med] |
| 8.6 | PromptOverlay golden master: 4 thin variants, none exercising billing/KYC/semantic/live_data layers; goes stale the moment a1/a2 activate | `PromptOverlayGoldenMasterTest:91` | [MED][med] |
| 8.7 | `FynSystemPrompt` snapshot has no regeneration tooling (manual mirror) | `FynSystemPromptTest:7-12` | [LOW][med] |
| 8.8 | STALE MEMORY CORRECTION: `proposed-fyn-refusal-carveout.patch` does not exist in the tree — its carve-out text already shipped inline (`FynSystemPrompt.php:36`, `CoreIdentity.php:34`); a "post-stream refusal-recovery guard" remains deferred in CSJTODO | agent grep, clean tree | [INFO] |

---

## 9. DECISIONS ONLY CSJ CAN MAKE (consolidated, deduped)

**Repetition-fix bundle (unblocks P0):**
- **D1.** Should KYC block on the PRIMARY module only (not every secondary keyword match)? And should goals gate ANY advice at all, or only `goals_progress`-primary turns?
- **D2.** Activate the a1-answer-first + a2-ack-hygiene overlays now (accepting golden-master regen)?
- **D3.** Server-side degeneration guards (tool-call dedupe + repetition collapse + xAI history rebuild) — green-light as one PR?

**Model/provider truth:**
- **D4.** Which xAI model is canonical — `grok-4.3` (config default + cassettes) or `grok-4-1-fast` (stated runtime)? Re-record cassettes + unskip provenance accordingly?
- **D5.** Should the xAI path get decoder hygiene (frequency_penalty or equivalent) given temp-0 greedy decoding?

**Gating semantics:**
- **D6.** Canonical route set for gate "navigate to" links (align readiness form_links to real SPA routes + define /m equivalents)?
- **D7.** Universal KYC hard-blockers (employment_status, expenditure) — blockers or warnings?
- **D8.** Should `assessAll` include Goals + Tax? Should Fyn's `generate_financial_plan` respect the freemium tier gate?

**State hygiene:**
- **D9.** #615 follow-up: null `active_campaign`/`fyn_path`/`selection`/`paused_at_step` on wizard/quick/skip completion (matching chat-led)? Fix /m `onboardingActive` to include the fyn_step term?

**Surfaces:**
- **D10.** /m error surfacing: must /m show specific messages (token limit, consent, handoff error) instead of the generic line? Should web celebrate level-ups from chat? Are capture confirmations (`entity_created`) required on /m?
- **D11.** Deterministic failure surfacing on the advice→inline-capture path (not LLM-narrated) — required?

**Data figures:**
- **D12.** Which retirement-income engine is canonical per surface (4% SWR vs Monte Carlo vs materialised scheme quote)? Or are all three "correct in context" with labels?
- **D13.** Should non-Fyn write paths invalidate Fyn's prompt caches (kill the 120s staleness)? Add `investment_analysis_` to central invalidation?

**Compliance posture:**
- **D14.** Which regulatory rules need a server backstop (auto-append adviser line? product-name detector? block vs log)? Is a violation review queue required for SYSC evidence?
- **D15.** Erasure composition: should self-service deletion invoke full chat/memory erasure? Extend retention purge to the missed stores? Fix the "Delete my Data" 3-column claim?
- **D16.** Rule-12 for tool payloads: strip `*_score` fields from what the model sees, or rely on prompt suppression? Extend the `removeScores()` allowlist?

**Eval investment:**
- **D17.** Fill the five empty eval categories before the next prompt change? Add corpus content-parity (not just names) and a `.xai.md`-for-onboarding-tools decision (8.4)?

---

## 10. Suggested sequencing (pending the decisions above)

- **P0 — the repetition family** (D1-D3, D5): classifier pattern + KYC scope + navigate_to_page contradiction + loop guards + a1/a2 activation + one graded eval. This is "Fyn stops visibly misbehaving".
- **P1 — trust the answer**: dead gate routes (D6), assessAll gaps (D8), retirement engine canon (D12), investment cache (D13), #615 follow-up (D9), /m error+capture surfacing (D10-D11).
- **P2 — trust the system**: compliance backstops + violation queue (D14), erasure composition (D15), score-strip (D16), corpus parity + model provenance (D4, D17), concurrency locks (4.3), planner latency (2.6).

*Generated 2026-07-07 by the six-agent blindspot pass; source transcripts in the session task outputs.*
