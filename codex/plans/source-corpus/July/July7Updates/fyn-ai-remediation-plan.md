# Fyn / AI Remediation — Implementation Plan

**Spec:** [fyn-ai-remediation-spec.md](fyn-ai-remediation-spec.md) · **Audit:** [fyn-ai-blindspot-map.md](fyn-ai-blindspot-map.md) · **Playbook:** [fyn-ai-prompting-playbook.md](fyn-ai-prompting-playbook.md)
**For:** Opus 4.8 implementation. Each task: files, change, tests, acceptance, dependencies, /m note. **Nothing is implemented yet.**

## How to use this plan

- Branch per task-group off `dev` (`feature → dev → main`). Group tasks per PR as marked; lean cadence (Rule 17) between groups, full loop-until-correct (Rule 14) within one.
- **Before any Fyn work**: read `April/April24Updates/spec/00-canonical.md`, the blindspot map, and the memory files `feedback_advice_fyn_is_read_only`, `reference_dual_provider_tool_catalogue`, `reference_tool_schema_description_governs_llm_defaults`, `reference_legacy_refuses_advice_capture_journey`.
- **⚠ CONFIRM markers** = get CSJ's one-line sign-off before that task, not after.
- Every corpus/prompt byte change regenerates its golden master IN THE SAME COMMIT.
- Live verification is csjones (deploy per `deploy/DEPLOY.md` + verify-m skill); the 19079 repro user class = completed onboarding, retirement data present, zero goals.

---

## PR-1 — Gating principle: data-needed-only (WS-F1.1, F1.2) [P0]

**T1.1 Need-matrix in QuerySchemas.** `app/Constants/QuerySchemas.php`: add `REQUIRED_DATA` per query type (mirror of REQUIRED_TOOLS) naming the user-data prerequisites for that question type (e.g. RETIREMENT_READINESS → dob, income, ≥1 pension; AFFORDABILITY → expenditure). This is the single source of truth for gating.
**T1.2 KYC primary-only + need-matrix.** `app/Services/AI/KycGateChecker.php`: `check()` gates on the PRIMARY type's REQUIRED_DATA only; delete the all-modules loop (`:64-79`) and the blanket universal expenditure/employment blocks (`:115-152`) — universality now comes from the matrix (types that need them, list them). Goals data may appear in REQUIRED_DATA only for goals-primary types.
**T1.3 Narrow GOALS_PROGRESS.** `app/Constants/QuerySchemas.php:367`: pattern must not fire when the sentence targets retirement/pension/house/savings ("on track to retire", "on track for retirement" → retirement). Add classifier unit tests for the ambiguous set.
**T1.4 Advice-mode instruction text.** `KycGateChecker::buildPromptText` (`:257-272`), `AdvicePromptBuilder.php:1062-1067`, `CoordinatingAgent.php:903` (blocked tool result), `PrerequisiteGateService::buildCompletenessContext:327`: in advice mode, replace "call navigate_to_page" with plain-text signposting ("tell the user to open <label> (<route>)"). **⚠ CONFIRM** the plain-text default vs allowing read-only navigation.
**Tests:** classifier matrix tests; KYC unit tests (goal-less retirement passes; DOB-less retirement blocks with only-that-gap); prompt-assembly test asserting no stripped tool is ever named in an advice prompt.
**Acceptance:** goal-less user with pension data asking "Am I on track for retirement?" → KYC pass, no goals directive anywhere in the assembled prompt.
**/m:** server-side only; verify the repro on /m chat too after PR-3 deploy.

## PR-2 — Loop guards + validator collapse (WS-F1.3) [P0]

**T2.1 xAI history rebuild.** `app/Traits/HasAiChat.php:556-558`: per-iteration assistant message carries ONLY that iteration's streamed text (track `$iterationText` separately from `$fullResponse`); Anthropic branch untouched.
**T2.2 Tool-call dedupe.** Same loop: keep `executedCalls[name][sha1(json(normalisedArgs))]`; on repeat → skip execution, push synthetic tool-result "Result already provided above — do not call this tool again this turn", increment `toolCallCount`.
**T2.3 Repetition collapse.** `app/Services/AI/StructuredResponseValidator.php::sanitise()`: collapse ≥3 consecutive normalised-identical sentence/paragraph repeats to one; add `repetition_collapsed` violation entry (metadata) when triggered. Applies to every persona/provider.
**T2.4 Cap-pass reset.** `HasAiChat.php:829-833`: when the final tools-disabled pass runs, reset the response buffer so the clean pass REPLACES accumulated text rather than appending.
**Tests:** unit per guard; integration test replaying a scripted 3-identical-tool-call turn asserting single execution + single paragraph persisted; full suite.
**Acceptance:** scripted degeneration turn persists one paragraph, ≤1 execution per identical call; no behaviour change on clean cassettes.
**/m:** none (server-side).

## PR-3 — Overlays + eval + live verify (WS-F1.4, F1.5) [P0, closes the incident]

**T3.1 ⚠ CONFIRM then flip a1/a2.** `fyn-memory/procedural/system_prompt_overlay/general/{a1,a2}.md` → `active: true`; regenerate PromptOverlay golden masters (`CAPTURE_PROMPT_OVERLAY_GOLDEN=1`) same commit.
**T3.2 Degeneration eval.** New graded scenario (repetition class): gate-refusal/gap turn asserting the refusal text appears once and identical tool calls ≤1. Wire under 09-canonical (or new 10-degeneration).
**T3.3 Live loop (Rule 14).** Deploy PR-1..3 to csjones; repro the 19079 question as a goal-less user on web AND /m; assert one clean reply + `ai_messages` row sane; keep looping until GREEN.
**Dependencies:** PR-1, PR-2.

## PR-4 — grok-4.3 reconciliation (WS-F2.1)

**T4.1** Verify `XAI_CHAT_MODEL` on csjones + prod envs = `grok-4.3`; reconcile if not (env-only; never commit credentials).
**T4.2** Unskip `tests/Feature/Fyn/Eval/CassetteModelProvenanceTest.php`; confirm cassettes under `fixtures/xai/grok-4.3` replay green; re-record any that don't.
**T4.3** Update the stale memory file (`feedback_fyn_model_choice_is_deliberate.md`) to record the 2026-07-07 grok-4.3 ruling.

## PR-5 — OpenAI gpt-5-nano wiring (WS-F2.2)

**T5.1 Client.** `app/Services/AI/OpenAiClient.php` (sibling of `XaiClient`): OpenAI PHP SDK, `api.openai.com`, `OPENAI_API_KEY`, `config('services.openai.chat_model', 'gpt-5-nano')`. **Params (verify against current OpenAI docs first):** `max_completion_tokens` ✓; do NOT send `temperature` (GPT-5 reasoning models reject non-default); `reasoning_effort: 'minimal'`; streaming + `stream_options.include_usage`.
**T5.2 Provider switch.** Wherever `$isXai` branches (`HasAiChat`, `Planner`, `ConversationSummariser`, `ProposedFactSynthesiser`, guardrails, cost accounting): generalise to a provider enum {xai, anthropic, openai} — openai reuses the OpenAI-wire code path with client + param map swapped. `AI_PROVIDER=openai` selects it globally; add per-component override config keys (`fyn.provider_overrides.planner` etc.), default unset. **⚠ CONFIRM which components (if any) route to nano at launch — wiring ships dormant otherwise.**
**T5.3 Corpus alias.** `ProceduralCorpus::active($id, 'openai')` resolves to the `.xai.md` variant (provider alias table) — no third file set. Document in the corpus README.
**T5.4 Golden masters + parity.** New `OpenAiToolSchemaGoldenMasterTest` + fixtures; extend `ToolCatalogueParityTest` to 3 providers.
**T5.5 Cost + budget.** Pricing entry for gpt-5-nano (fetch current OpenAI pricing at implementation time — do not trust training-data prices); guardrails token budget applies unchanged.
**T5.6 Eval smoke.** Record 01-query-types under `openai/gpt-5-nano`; add to the eval matrix.
**Acceptance:** `AI_PROVIDER=openai` locally: full advice turn streams with tools + cost; switch back = env change only; suite green with all three golden master sets.
**/m:** none (server-side; /m shares the endpoint).

## PR-6 — Gate truth (WS-F3)

**T6.1 GateRoutes map.** New `app/Services/GateRoutes.php` (or const class): every gate-emittable destination → {web route, /m route|null, label}. All readiness services + KYC + completeness + blocked-tool text consume it. Kill every dead path from the map's §1.3 list.
**T6.2 Route-resolution test.** Pest test iterating the map: web path exists in `resources/js/router/index.js`; /m path exists in `resources/mobile/router.js` or is explicitly marked web-only.
**T6.3 assessAll 7 modules.** `PrerequisiteGateService.php:341-350`: add goals + tax assessments (build lightweight readiness for both); remove the `?? 100` default (`:315`).
**T6.4 Dead scenario gate.** `:199` → `create_what_if_scenario`; add comment delegating write-tool safety to WRITE_TOOLS/GroundGate; make the create-list comment honest.
**T6.5 ⚠ CONFIRM (D8) tier gate.** If confirmed: `handleFinancialPlan` checks the same feature gate as the REST holistic route; teaser-style refusal text for non-pro users.

## PR-7 — State hygiene (WS-F4, D9 required)

**T7.1** `app/Services/Onboarding/OnboardingService.php` (three completion methods): also clear `active_campaign`, `onboarding_fyn_path`, `onboarding_fyn_selection`, and `paused_at_step` inside `onboarding_fyn_context` (null the key, keep other context). Mirror `emitDoneTurn:3889-3893`.
**T7.2** `resources/mobile/mixins/onboardingChat.js:47-49`: `onboardingActive` = `(completed===false || !!active_campaign) && fyn_step !== null` — matching `AiChatController::routesToOnboardingDirector`.
**T7.3** Amend `April/April24Updates/spec/00-canonical.md` dispatch section to document the active_campaign re-entry disjunct as canonical.
**T7.4** Extend `tests/Feature/Onboarding/WizardCompletionTest.php`: stale-campaign wizard-finisher asserts all four fields cleared.
**/m:** T7.2 is the /m fix; live-verify a wizard-finisher with stale campaign sees no onboarding chrome on /m.

## PR-8 — Surface parity (WS-F5; Rule 19)

**T8.1** `resources/mobile/api.js` + `mixins/onboardingChat.js`: handle `token_limit`, `consent_required`, `handoff_error`, typed `error.message` with specific user-facing copy (plain text, no icons/emoji).
**T8.2** /m handlers for `entity_created`, `capture_complete`, `skip_link` (+ the skip action post).
**T8.3** Web `aiChat.js`: `level_up` case → `gamification/queueCelebration`.
**T8.4** `OnboardingChatDirector::handleInlineCapture`: deterministic failure text on `['error'=>true]` create results (mirror `handleAssetCaptureTurn:2909-2920`); then either consume or delete the orphan `capture_write_result` event.
**T8.5 (optional)** tool_use progress indicator on both surfaces, or remove the emission.
**Tests:** SSE fixture tests per event per surface; live: force each event on csjones (queue lock via tinker, token budget exhaustion, malformed handoff) and see the specific message on /m.

## PR-9 — Cache & figure truth (WS-F6)

**T9.1** `CacheInvalidationService::invalidateForUser`: add `investment_analysis_`; `RecommendationCacheObserver`: pass real analysis keys per agent (kill the v1_* no-op).
**T9.2** UI write paths → `AdvicePromptCacheInvalidator::forUser` (hook into the existing observer layer; do NOT scatter calls through controllers).
**T9.3** `ComposedTaxPlanService`: clear memo entry on capture write in-request (or version-stamp key).
**T9.4** `MobileDashboardAggregator`: reconcile docblock vs `CACHE_TTL` (**⚠ CONFIRM intended TTL**).
**T9.5 ⚠ CONFIRM (D12) then implement retirement canon**: all summary cards (web dash, /m dash, /m module, Fyn tool payload) read the ruled engine; other engines' surfaces get explicit basis labels.
**Tests:** Fyn-created investment account reflected in `get_module_analysis(investment)` same turn; salary edit via UI reflected in next Fyn turn; seeded DB/DC user shows one figure across surfaces.

## PR-10 — Corpus, scores & eval integrity (WS-F7)

**T10.1** Content-parity test across corpus variants (params/defaults/required/enums) — must FAIL on `savings/create_savings_account` divergence first, then fix the divergence deliberately (decide which description is right — remember: descriptions drive model defaults).
**T10.2** `AiToolDefinitions::onboardingExtractionTools` + `ProceduralCorpus::active`: provider-correct corpus under xai/openai (no anthropic default fallthrough).
**T10.3 ⚠ CONFIRM (D16)**: strip financial-quality `*_score` from `summariseToolAnalysis` payloads (update `ToolResultContract` REQUIRED_KEYS); extend `removeScores()` allowlist (efficiency/completeness/tax_efficiency/urgency/drift/optimization/impact/ease/alignment/total/module_scores). Never touch level/percentile.
**T10.4** Eval fill: 07-regulatory (adviser-line presence, no product names, hedging) + 06-prompt-injection (nickname injection via tool results, "ignore instructions" in user msg) — ≥3 scenarios each; then 08-provider-parity across the 3 providers.
**T10.5** `php artisan fyn:snapshots:regen` — regenerates FynSystemPrompt snapshot + PromptOverlay golden masters + tool-schema fixtures behind explicit flags.

## PR-11 — Compliance & lifecycle (WS-F8) — **decision-gated, do not start without CSJ**

Menu for CSJ (D14/D15), then tasks: adviser-line auto-append; product-name detector; violations admin queue; sanitise strips emoji/acronyms; AI-store erasure composition (coordinate with the sibling whole-app GDPR workstream — do not double-implement the user-row purge); tool-result sanitisation; episodic figure guard; learning-flag hard-gate on promotion.

---

## Sequencing & dependencies

```
PR-1 ─┬─► PR-3 (needs 1+2) ──► live GREEN on the incident
PR-2 ─┘
PR-4 ──► PR-5 (provider abstraction builds on reconciled xAI)
PR-6, PR-7, PR-8, PR-9 — independent of each other; after PR-3
PR-10 — after PR-5 (parity covers 3 providers)
PR-11 — last, decision-gated
```

**Confirm-before-start list (one line each from CSJ):** T1.4 signpost default · T3.1 overlay flip · T5.2 nano routing at launch · T6.5 tier gate · T9.4 TTL · T9.5 retirement engine · T10.3 score strip · all of PR-11.

## Document history

- 2026-07-10 - corrected QuerySchemas source path after dev-line verification.
