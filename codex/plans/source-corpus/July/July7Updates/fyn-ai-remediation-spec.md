# Fyn / AI Remediation — Specification

**Source audit:** [fyn-ai-blindspot-map.md](fyn-ai-blindspot-map.md) (six-agent pass, dev tip `e16ea5f`)
**Companion plan:** [fyn-ai-remediation-plan.md](fyn-ai-remediation-plan.md)
**Sibling programme:** [blindspot-remediation-spec.md](blindspot-remediation-spec.md) (whole-app; GDPR/queue/monitoring overlap noted per workstream)
**Date:** 2026-07-07 · **Status:** Ready to implement (Opus 4.8) · **Nothing here is implemented — spec + plan only.**

## CSJ rulings recorded 2026-07-07

| Ref | Ruling |
|---|---|
| D1 | **Goals never gate advice. Only the information needed to answer the specific question gates advice.** This is the general gating principle — it applies to the goals gate, the KYC module set, AND the universal employment/expenditure hard-blocks (D7): a data item blocks a turn only if the question being asked cannot be answered without it. |
| D2 | a1-answer-first + a2-ack-hygiene overlays: **default = activate** (they are the authored prompt-side fix for the repetition class). Confirm with CSJ at implementation before flipping. |
| D3 | Server-side loop guards: **approved, one PR is fine.** |
| D4 | **Canonical xAI model = `grok-4.3`.** Cassettes/provenance/envs reconcile to it. |
| D4b | **NEW: wire in OpenAI `gpt-5-nano` as a selectable provider** (see WS-2). |
| D9 | #615 follow-up (clear `active_campaign`/`onboarding_fyn_path`/`onboarding_fyn_selection`/`paused_at_step` on wizard-path completion + fix the /m `onboardingActive` predicate): **required** — Rule 19 parity bug. |
| Open | D8 (Fyn holistic vs tier gate), D12 (canonical retirement engine), D14/D15 (compliance backstops / erasure — coordinate with sibling programme), D16 (score-strip depth). Defaults specified per workstream; implementer must get CSJ confirmation where marked **⚠ CONFIRM**. |

---

## WS-F1 — Kill the repetition class (P0)

**What:** A goal-less (or any data-light) user asking an answerable question gets ONE clean answer or ONE clean "here's what I need" reply — never repeated paragraphs, never contradictory READY/BLOCKED instructions, never orders to use tools the model doesn't have.

**F1.1 Gating principle (D1).** Rework `KycGateChecker` + classifier module-scoping so a turn is gated ONLY on data required to answer the primary question:
- KYC evaluates the PRIMARY classified module's requirements only; secondary/implicit modules never block (they may add context, not blocks).
- Remove `goals` from every advice-blocking path. The goals gate may only block when the question's primary type is a goals type (`goals_progress`, goal CRUD guidance).
- Narrow `GOALS_PROGRESS` keyword pattern (`QuerySchemas.php:367`) so "on track for retirement/pension/house" does not classify as goals.
- Universal requirements (`KycGateChecker::checkUniversalRequirements`) become per-question-type: expenditure blocks only types that compute affordability/surplus; employment_status only types that need income context. Encode the need-matrix in `QuerySchemas` alongside REQUIRED_TOOLS so classification and gating share one source of truth.
- Acceptance: goal-less julycsj3-class user asking "Am I on track for retirement?" receives retirement advice (retirement data present, gate READY); the prompt contains NO goals BLOCKED directive; a user genuinely missing DOB/income for that question gets a single gap-reply pointing at a REAL route.

**F1.2 Instruction–tool coherence.** In advice mode, no prompt layer may instruct a tool that is not in the advice catalogue. Default resolution (**⚠ CONFIRM**): change KYC/blocked/completeness instruction text to "tell the user where to go in plain text" in advice mode; `navigate_to_page` stays capture-side. (Alternative if CSJ prefers: reclassify `navigate_to_page` as read-safe and allow it in advice.) Acceptance: grep-level test that assembled advice prompts never name a stripped tool; blocked-turn eval renders a plain-text signpost.

**F1.3 Loop guards (D3, one PR).**
- xAI branch: rebuild the per-iteration assistant history message from the CURRENT iteration's text only (mirror the Anthropic branch) — never the accumulated buffer (`HasAiChat.php:556-558`).
- Identical tool-call dedupe: same `name`+normalised-args already executed this turn → skip execution, inject an observation "result already provided above — do not call again", count it toward the cap.
- Repetition collapse in `StructuredResponseValidator::sanitise()`: collapse ≥3 consecutive repeats of the same normalised sentence/paragraph block to one, flag `repetition_collapsed` in metadata.
- Guard scope is provider-independent (applies to xAI, Anthropic, and the new OpenAI path).
- Acceptance: unit tests per guard; a replayed 19079-style cassette turn persists ONE paragraph; suite green.

**F1.4 Prompt overlays (D2, ⚠ CONFIRM then flip).** Set `active: true` on `a1-answer-first.md` + `a2-ack-hygiene.md`; regenerate the PromptOverlay golden masters in the same PR (the #585 lesson: snapshot regen never trails the change).

**F1.5 Eval.** Add a graded scenario (09-canonical or a new 10-degeneration category): gate-refusal turn asserting single-occurrence of the refusal text + ≤1 identical tool call. Runs under BOTH canonical providers (WS-2).

## WS-F2 — Provider truth: grok-4.3 canonical + OpenAI gpt-5-nano wired (D4/D4b)

**What:** One provider abstraction, three concrete providers (xAI grok-4.3 canonical, Anthropic existing, OpenAI gpt-5-nano new), all selectable by config; evals and golden masters cover what production runs.

**F2.1 grok-4.3 reconciliation.** `config/services.php` already defaults `grok-4.3`. Verify server envs (csjones + prod `.env` `XAI_CHAT_MODEL`) match; unskip `CassetteModelProvenanceTest`; cassettes stay/record under `xai/grok-4.3`. Update the stale project memory that names grok-4-1-fast as the runtime.

**F2.2 OpenAI gpt-5-nano wiring.** Scope = **wired and selectable**, not rerouted-by-default:
- Client: an `OpenAiClient` sibling of `XaiClient` (same OpenAI PHP SDK, `api.openai.com` base, `OPENAI_API_KEY`, `OPENAI_CHAT_MODEL=gpt-5-nano`). Note the GPT-5 family parameter differences and verify against current OpenAI docs at implementation: `max_completion_tokens` (already used), **temperature is restricted on GPT-5 reasoning models (fixed default — do NOT send `temperature: 0`)**, use `reasoning_effort: 'minimal'` (supported value name differs from xAI's `'none'`), consider `verbosity` low. Streaming + function calling as today.
- Provider switch: `AI_PROVIDER=openai` end-to-end (dispatch, tool catalogue, cost accounting, guardrails token budgets), plus a **per-component override** hook (e.g. planner or classifier on nano while advice stays grok) — config keys defined, default OFF. **⚠ CONFIRM with CSJ which turns (if any) route to nano at launch; wiring ships dormant otherwise.**
- Tool corpus: GPT-5 uses OpenAI function-calling — the same wire shape as xAI. Default (**⚠ CONFIRM**): OpenAI provider consumes the `.xai.md` corpus variant via a provider-alias in `ProceduralCorpus::active()` (no third file set) — revisit only if nano needs different schema descriptions in practice.
- Golden master: new `OpenAiToolSchemaGoldenMasterTest` fixture set; parity test extended to 3 providers (names now, content-parity per WS-F7).
- Cost: pricing entry for gpt-5-nano in the cost table (verify current OpenAI pricing at implementation — do not hardcode from memory); per-turn gbp_cost works unchanged.
- Eval: record at least the 01-query-types scenarios under `openai/gpt-5-nano` once wired.
- Acceptance: with `AI_PROVIDER=openai` locally, a full advice turn streams, tools fire, cost is accounted, suite green; flipping back to xAI is a pure env change.

## WS-F3 — Gate truth (routes, completeness, dead gates)

- **F3.1** Replace every dead readiness/KYC `form_link` with real router paths; define the /m mapping table (web path → /m path or "web-only, Fyn must say so"); single source (a `GateRoutes` map) consumed by all readiness services + KYC. Test asserts every emitted route resolves in the web router and the /m mapping table.
- **F3.2** `assessAll` covers all 7 modules (add goals+tax assessments); remove the `?? 100` fabrication; BLOCKED lines always carry field-level detail.
- **F3.3** Fix the dead scenario gate (`run_what_if_scenario` → `create_what_if_scenario`) and make the `canExecuteTool` create-list either exhaustive or explicitly delegated to the WRITE_TOOLS/GroundGate layer with a comment saying so.
- **F3.4 (⚠ CONFIRM D8)** Fyn `generate_financial_plan` vs freemium tier gate — default: enforce the same tier gate as the REST holistic route.

## WS-F4 — State hygiene (D9 required)

- **F4.1** `OnboardingService::{completeOnboarding, completeQuickOnboarding, skipToDashboard}` also clear `active_campaign`, `onboarding_fyn_path`, `onboarding_fyn_selection`, and `onboarding_fyn_context.paused_at_step` — byte-matching what `emitDoneTurn`/`emitTerminalNavigationTurn` clear. Extend `WizardCompletionTest`.
- **F4.2** /m `onboardingActive` predicate gains the `fyn_step !== null` term so it matches `routesToOnboardingDirector` exactly. Amend `00-canonical.md` to document the real predicate INCLUDING the `active_campaign` re-entry disjunct.
- **F4.3** (lower priority) Employment vocabulary check: verify `parseEmploymentFromText` normalises the `employed` bubble to a value `skipIfNotEmployed` accepts; add the missing test either way.

## WS-F5 — Surface parity (Rule 19 answers D10/D11 — not optional)

- **F5.1** /m renders specific messages for `token_limit`, `consent_required`, `handoff_error`, `error.message` (kill the generic "trouble responding" for typed events).
- **F5.2** /m handles `entity_created` + `capture_complete` (capture confirmations visible) and `skip_link` (spouse skip affordance).
- **F5.3** Web handles `level_up` from chat SSE (queueCelebration wiring).
- **F5.4** Deterministic write-failure surfacing on the advice→inline-capture path: server-side plain-text "I couldn't save that…" mirroring `handleAssetCaptureTurn`'s guarantee — never rely on LLM narration alone. (`capture_write_result` either gets consumers or is removed from the contract — pick one, don't leave a dead event.)
- **F5.5** (optional, small) Render `tool_use` progress or stop emitting it.

## WS-F6 — Cache & figure truth

- **F6.1** Add `investment_analysis_{uid}` to `CacheInvalidationService`; fix the observer→agent no-op keys (`RecommendationCacheObserver` passes the real analysis keys); correct the `MobileDashboardAggregator` docblock (or the constant — **⚠ CONFIRM intended TTL**).
- **F6.2** Non-Fyn write paths invalidate Fyn's prompt caches (UI form saves call `AdvicePromptCacheInvalidator::forUser` via the existing observer layer) — kills the "edit salary, Fyn quotes old figure for 2 min" class.
- **F6.3** `ComposedTaxPlanService` memo: drop memo entry on capture writes within the same request (or key the memo by a data-version stamp).
- **F6.4 (⚠ CONFIRM D12)** Retirement engine canon — decision required before implementation. Default proposal: agent 4%-SWR is canonical for all summary cards (web + /m + Fyn); the Monte Carlo page and materialised scheme quotes remain but MUST be visibly labelled as different bases. Acceptance: one seeded user shows one £ figure across dashboard cards and Fyn.

## WS-F7 — Corpus, Rule-12 payloads & eval integrity

- **F7.1** Content-level corpus parity test (params/defaults/required/enums) across `.md`/`.xai.md` (and the OpenAI alias) — must fail on the current `current_account` divergence before its fix.
- **F7.2** Onboarding/campaign extraction tools use the provider-correct corpus under xAI/OpenAI (fix the anthropic-default in `AiToolDefinitions`/`ProceduralCorpus::active`).
- **F7.3 (⚠ CONFIRM D16)** Score-strip for model-visible payloads — default: strip financial-quality `*_score` fields in `summariseToolAnalysis` (contract updated), extend `removeScores()` allowlist with the missed keys. Gamification level/percentile untouched (approved carve-out).
- **F7.4** Fill eval categories: 07-regulatory + 06-prompt-injection first (minimum 3 scenarios each), then 08-provider-parity (now 3 providers), 05-cancel-timeout, 02-preview-personas.
- **F7.5** Snapshot regen tooling: one artisan command regenerates the FynSystemPrompt snapshot + PromptOverlay golden masters (kills the manual-mirror class).

## WS-F8 — Compliance backstops & data lifecycle (decision-gated; coordinate with sibling programme)

**Do not start without CSJ (D14/D15).** The sibling whole-app plan owns the GDPR purge/user-row workstream; this WS adds only the AI-store specifics:
- F8.1 Server backstop menu for CSJ: (a) auto-append adviser signpost when absent; (b) product-name detector (log→block ladder); (c) violations admin queue (readers for the currently write-only metadata); (d) sanitise-level strip for emoji/acronyms.
- F8.2 AI-store erasure completeness: `ai_messages`/`ai_conversations`/`ai_advice_log`/`ai_abort_events`/funnel+onboarding columns/per-user semantic facts join whichever erasure path the sibling programme lands; `fyn:user:erase` and self-service deletion composed.
- F8.3 Tool-result channel sanitisation (user-controlled record names re-entering prompts) + figure guard on episodic `writeEpisode` before the rubric ever activates + hard-gate `SemanticFactPromoter` on `FYN_LEARNING_ENABLED`.

## Acceptance definition for the whole programme

1. The 19079 repro (goal-less user, retirement question) yields one clean advice answer on web AND /m, live on csjones.
2. Suite green; every corpus/prompt byte change lands with regenerated golden masters in the same PR.
3. `AI_PROVIDER` supports `xai` (grok-4.3, canonical), `anthropic`, `openai` (gpt-5-nano) — switchable by env alone.
4. No prompt layer instructs a tool absent from the active catalogue (tested).
5. Every gate route emitted to the model resolves on the surface the user is on (tested).
6. Fyn, dashboards and /m quote the same figures for the same user (retirement canon per D12 ruling).
