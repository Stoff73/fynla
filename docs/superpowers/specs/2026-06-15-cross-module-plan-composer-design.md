# Cross-Module Plan Composer — CoALA-Native Design

**Date:** 2026-06-15
**Status:** Design — awaiting CSJ spec review (brainstorming → writing-plans)
**Author flow:** brainstormed with CSJ 2026-06-15
**Branch target:** off `dev` (CoALA substrate; landed via PR #550, 2026-06-13)
**Canonical architecture this sits inside:** the four-stage Fyn agent flow (`feedback_coala_agent_flow_canonical.md`; Track 2 spec §1) + the v0.5 pointer model + `fynla-coala-implementation-plan.md` v0.5. **This spec does not change that flow** — it applies it to cross-module planning.

---

## 1. Problem & goal

Today only the **tax** module has a *composed plan*: `ComposedTaxPlanService` joins the `TaxStrategyCalculator`'s quantified recommendations with catalogue metadata (`claim_tier`, `required_data`, `sequencing` on `tax_action_definitions` rows where `source='strategy'`) and runs them through the pure `StrategyPlanComposer` to produce one ordered, conflict-aware plan plus a *locked* list (strategies whose `required_data` aren't available, surfaced as unlock prompts — never silently skipped). Fyn reaches it through the `recommendations` **pointer** → `RecommendationHandler` → the `FynLoop`.

The other six modules (Protection, Savings, Investment, Retirement, Estate, Goals) have only `source='agent'` recommendation rows with `trigger_config` conditions, consumed by each module Agent's `generateRecommendations()`. Their `required_data`/`sequencing` columns exist (migration `2026_06_10_100001`) but are **null by design** — *because there is no consumer*. Authoring them in isolation would be inert.

**Goal:** generalize the composer so **every module** gets the tax treatment (ordered, conflict-aware, claim-tier-voiced plan + locked-strategy unlock prompts), **and** add a cross-module layer that (a) ranks the combined plan by **affordability** (finite surplus is a shared constraint), and (b) treats **goals & life events** as cross-module planning objects. Make the currently-inert non-tax metadata meaningful by giving it a real consumer — and do it **CoALA-natively**, not as a service bolted beside the loop.

### Why CoALA-native (the correction)
An earlier draft of this design extended `ComposedTaxPlanService` bottom-up as a `Coordination` service with bespoke surface wiring — the exact mistake `feedback_coala_agent_flow_canonical.md` warns against (mapping from storage implementations rather than from the framework applied to the whole app). This spec instead maps every layer onto the CoALA substrate: **live sources reached via pointers/handlers**, **catalogue knowledge as a semantic + procedural + DB triple**, composition invoked **through the FynLoop**, and recommendation outcomes **captured to episodic memory and recalled** to shape future ranking. The v0.5 **pointer model** governs throughout: a composed plan is live-owned data and is **never frozen into a prompt or the corpus** — Fyn fetches it at the moment of need (lean-prompt law).

---

## 2. Decisions locked in brainstorming

| # | Decision | Rationale |
|---|----------|-----------|
| D1 | **Build the consumer first, then author metadata.** | Authoring metadata with no consumer is inert (CLAUDE.md scope discipline). |
| D2 | **Hybrid shape:** per-module composed plans (foundation) **+** cross-module coordination (affordability + goals/life-events). | CSJ: per-module needed, but affordability and goals/life-events genuinely span modules. |
| D3 | **One combined spec** covering both layers. | CSJ choice; de-risked because most cross-module substrate already exists. |
| D4 | **Affordability behaviour = rank + annotate** (`fits` / `partially_fits` / `beyond_current_surplus`, with running surplus consumed). Never drop. | Mirrors the existing tax "locked → unlock prompt, never silently skip" principle. |
| D5 | **Cross-module composite plan = tool-only pointer** (explicit ask), not prefetch. | CSJ: keep it on demand, don't fatten every turn's prefetch. |
| D6 | **Episodic learning = capture + recall now; promotion deferred to Phase 6.** | Capture (`writeEpisode`) + recall (`recallContext`) are Phase 2/5 and exist; episode→semantic promotion + dense recall are Phase 6 (not built, explicitly deferred). |

### Recommended defaults (overridable at spec review)
- **D7 (DTO cost field, first-class):** add `requiredMonthlyCost: ?float` (and `requiredLumpSum: ?float`) to `StrategyRecommendation`, nullable/default-null. Affordability is now core, not a per-module `extra[]` concern. *Alternative: keep in `extra[]` — rejected as it makes affordability ranking depend on string keys.*
- **D8 (metadata model, mirror tax):** add `strategy_type` + `source='strategy'` rows to the five non-tax `*_action_definitions` tables and key metadata off a stable per-module strategy-type set. *Alternative: author on existing `source='agent'` rows keyed by `key` — rejected as it couples plan metadata to legacy agent rows.*
- **D9 (goal↔event binding, out of scope):** reuse existing substrate (`Goal.assigned_module`, the `blocks` dependency graph, `LifeEventIntegrationService::EVENT_MODULE_MAP`); do **not** add a `goal_id` to `LifeEventAllocation` in this spec. Flagged as a follow-up.

---

## 3. What already exists (the substrate — keep, don't rebuild)

**Composition core (module-agnostic already):**
- `StrategyPlanComposer::compose($recommendations, $metadata, $locked)` — pure function: saving-descending sort → `do_before` sequencing → graph-aware conflict resolution → combined total → locked passthrough. Its only tax coupling is the `IsaAllowanceAllocator` shared-allowance flags read from `$rec->extra[]`.
- `StrategyRecommendation` DTO — the common currency: `type, category, priority, title, description, estimatedAnnualTaxSaved, requiresAdvice, extra[]`, with `fromArray()`/`toArray()`.
- `ComposedTaxPlanService::forUser()` — the tax-bound joiner (calculator + `TaxActionDefinition` rows + `HouseholdFinancialContext::availability()` → composer). Static helpers `extractStrategyIds()` and `planDigest()` are parity-pinned and consumed by Fyn — **must stay byte-stable for tax**.

**CoALA wiring (the tax composer is already in the loop):**
- `Pointers/Handlers/RecommendationHandler` (id `recommendations`) wraps `ComposedTaxPlanService::forUser()`, returns a `FetchResult` (value + `digest` byte-identical to `planDigest()` + `extra.strategy_ids`/`locked_strategy_ids`). Pointer def `fyn-memory/procedural/pointers/recommendations.md`, mode `tool`.
- `FetchHandler` interface (`id()`, `fetch(FetchContext): FetchResult`); `FetchHandlerRegistry` (closed whitelist bound in `AppServiceProvider`); `FetchDispatcher` (resilient — handler exception degrades to null, never breaks a turn); `PointerRegistry` (parses `pointers/*.md`, fail-closed, handler id must be registered).
- `FynLoop::run()` (planner-driven, flag-gated off) + `stream()` (reasoner path, live); `FynContextAssembler::build()` runs **prefetch** pointer matching and injects `<live_data>`; tool-mode pointers are fetched on explicit ask. Planner procedure `fyn-memory/procedural/pointers/recommendation-routing.md` already routes planning turns.
- `GroundGate::blocksWriteSurface()` — read-only advice surface blocks `AdviceFyn::WRITE_TOOLS`.

**Affordability (exists, unwired from the composer):**
- `ResolvesIncome` (gross/net annual), `ResolvesExpenditure` (3-tier monthly fallback), `DisposableIncomeAccessor::getMonthlyForUser()`.
- `GoalAffordabilityService` — per-goal `monthly_surplus`, `available_surplus`, `affordability_ratio`, 7 tiers, life-event-adjusted, `analyzeAllGoals()`.
- `CashFlowCoordinator::calculateAvailableSurplus()`, `calculateCommittedContributions()` (DC pension + protection premiums + savings), and `optimizeContributionAllocation()` (priority-order waterfall across modules with shortfall tracking).

**Goals & life events (exists):**
- `Goal.assigned_module` (+ `module_override`), `GoalAssignmentService::determineModule()`, the `dependsOn()`/`dependedOnBy()` `blocks` graph, savings/investment account links.
- `LifeEvent` (income/expense/life-change types, amount, certainty, status), `LifeEventAllocation` (account-level), `LifeEventIntegrationService::EVENT_MODULE_MAP` (event→primary+secondary modules), `LifeEventCashFlowService` (year-indexed cash flows).

**Memory stores (exists):**
- Semantic: `fyn-memory/semantic/house_view/*.md` (21 tax narratives), `SemanticRetriever` (sparse), reindex `fyn:semantic:reindex`.
- Procedural: `fyn-memory/procedural/{pointers,tool_schema,...}`, `ProceduralCorpusLoader`, `FynMemoryStore::procedures()`.
- Episodic: `EpisodeBlobWriter`, `EpisodeRetriever`, `FynMemoryStore::writeEpisode()`/`recallContext()`, RUBRIC at `fyn-memory/episodic/RUBRIC.md`.

---

## 4. Architecture — three layers mapped to CoALA

### Layer A — Live sources (working-memory feeds; never frozen)

1. **`ModuleStrategySource` interface** (new): `recommendations(User): StrategyRecommendation[]`, `metadataRows(): Collection`, `availability(User): array<string,bool>`. One implementation per module. Tax's wraps `TaxStrategyCalculator` + `TaxActionDefinition` + `HouseholdFinancialContext`.
2. **`ComposedModulePlanService`** (generalized from `ComposedTaxPlanService`): takes a `ModuleStrategySource`, joins recs + metadata + availability, calls the **unchanged** `StrategyPlanComposer`. Tax is refactored onto it with **zero behaviour change**, pinned by the existing tax suite + `planDigest`/`extractStrategyIds` parity.
3. **`CompositePlanService`** (new, cross-module): gathers all per-module composed plans, then applies:
   - **Affordability rank + annotate (D4):** pull available surplus (`CashFlowCoordinator`/`DisposableIncomeAccessor`), rank items by impact, walk the running surplus, tag each `fits` / `partially_fits` / `beyond_current_surplus` with `surplus_consumed_to_here`. Never drop.
   - **Cross-module sequencing/conflicts:** the composer's `do_before`/`conflicts_with`, now permitted to reference strategy types in other modules (e.g. `pension_aa_carry_forward` before `isa_topup`).
   - **Goals as demands:** each active goal's `monthly_contribution` competes for surplus (via `GoalAffordabilityService`); a cross-module goal groups the relevant module strategies under it (using `assigned_module` + the `blocks` graph).
   - **Life events as time-phased modifiers:** near-term events shift available capital/surplus in their window (`LifeEventCashFlowService` + `EVENT_MODULE_MAP`).

`StrategyRecommendation` gains `requiredMonthlyCost`/`requiredLumpSum` (D7); null cost = informational, consumes no surplus.

### Layer B — Pointers + handlers (the FynLoop seam)

- **Per-module plan handlers** (generalize `RecommendationHandler`): either one parameterized handler keyed by module, or one per module. Each returns a `FetchResult` with the parity digest + strategy-id provenance. Pointer defs `fyn-memory/procedural/pointers/<module>-plan.md` (prefetch+tool where a module page benefits; tool for the rest).
- **`CrossModulePlanHandler`** (new): wraps `CompositePlanService`. Pointer def `fyn-memory/procedural/pointers/cross-module-plan.md`, **mode `tool` (D5)**, triggers like "what should I do", "can I afford", "overall plan".
- Register all new handlers in the `FetchHandlerRegistry` whitelist (`AppServiceProvider`). Each new pointer requires its `.md` def (fail-closed) — no PHP-array tool edits.

### Layer C — Catalogue knowledge as a semantic + procedural + DB triple

This is the correction that makes the metadata meaningful. Each strategy becomes:
- **Semantic** (`fyn-memory/semantic/house_view/*.md`): the source-less *narrative* — what it is, when it applies, why Fynla quantifies it so, where it sits in sequence, claim tier/voicing. Author per non-tax module (tax already has 21). **No figures, no user data** (pointer model).
- **Procedural** (`pointers/*.md` + planner routing): how to fetch + how the planner routes a planning turn to it.
- **DB** (`*_action_definitions`, `source='strategy'`, `strategy_type`-keyed — D8): the *mechanical* compute-input — `claim_tier`, `required_data` (drives locked/unlock via `availability()`), `sequencing` (do_before/conflicts_with). The structured shadow of the semantic narrative.

The original "author non-tax `required_data`/`sequencing`" task is therefore **DB metadata + house_view narrative + pointer**, per module — the full triple, not bare rows.

### Layer D — Episodic learning loop (capture + recall now; promotion = Phase 6)

- **Capture (exists):** recommendation outcomes — accept/decline/defer, durable preferences ("prioritise retirement over ISA") — are rubric-salient (decision/commitment/durable-preference, salience ≥3) → `learn` action → `FynMemoryStore::writeEpisode`.
- **Recall (exists):** `recallContext(userId)` (already in the planner system prompt) surfaces those episodes on later turns to **shape ranking and voicing** in `CompositePlanService` (e.g. de-rank a repeatedly-declined strategy; honour a stated module preference in tie-breaks).
- **Deferred to Phase 6 (D6):** episode→`SemanticFact` promotion (human-reviewed), procedure-amendment proposals, dense similarity recall. Out of scope here.

### Layer E — Invocation & write-safety
Plan composition is **read-side** — it surfaces strategies; *acting* on one is a separate `ground` write action gated by `GroundGate`. The composer pointers are therefore safe on the read-only advice surface by construction (no `WRITE_TOOLS`).

---

## 5. Data model & code changes

| Change | Location | Notes |
|--------|----------|-------|
| Add `requiredMonthlyCost`/`requiredLumpSum` | `StrategyRecommendation` DTO | Nullable; `fromArray`/`toArray` round-trip; tax sets null (no behaviour change). |
| `ModuleStrategySource` interface + 6 impls | `app/Services/Coordination/PlanSources/` (or per module) | Tax wraps existing calculator; non-tax wrap their `generateRecommendations()` output via adapters. |
| `ComposedModulePlanService` | `app/Services/Coordination/` | Generalized `ComposedTaxPlanService`; tax refactored onto it, digest-pinned. |
| `CompositePlanService` | `app/Services/Coordination/` | Cross-module: affordability + sequencing + goals + life-events + episodic recall. |
| Migration: `strategy_type` + `source='strategy'` rows | 5 non-tax `*_action_definitions` tables | Mirror tax (`2026_06_10_100001` only added `strategy_type` to tax). |
| Seed non-tax strategy metadata | new/extended `*ActionDefinitionSeeder` | `claim_tier`/`required_data`/`sequencing` per module strategy. |
| Extend `availability()` vocabulary | `HouseholdFinancialContext` (+ per-module providers) | Non-tax keys: e.g. `life_cover_in_force`, `emergency_fund_months`, `will_in_place`, `lpa_registered`, `portfolio_allocation`. |
| Per-module + cross-module handlers | `app/Services/AI/Pointers/Handlers/` | Register in `FetchHandlerRegistry`. |
| Pointer defs | `fyn-memory/procedural/pointers/*.md` | Per-module + `cross-module-plan.md` (tool). |
| house_view narratives | `fyn-memory/semantic/house_view/*.md` | Per non-tax strategy; reindex on deploy. |

### Per-module adapter effort (from codebase mapping)
| Module | Rec source | Effort | Note |
|--------|-----------|--------|------|
| Retirement | `RetirementActionDefinitionService::evaluateAgentActions` | Medium | Adapt service output → DTO. |
| Savings | `SavingsActionDefinitionService` (+ inline fallback) | Medium | Same pattern. |
| Investment | `InvestmentActionDefinitionService` | Medium | Same pattern. |
| Protection | `RecommendationEngine` (inline, `priority_score` 1–5) | Higher | Map procedural output → DTO. |
| Estate | `EstateAgent` step methods (inline procedural) | Higher | Wrap step output → DTO. |

---

## 6. Surfaces (Rule #19 — web **and** `/m`)

Surfacing is via the **loop/pointers**, not bespoke wiring:
- **Fyn** (web + `/m`, one shared backend): generalized handlers expose every module's plan + the cross-module plan (tool). `/m` rides the same backend — no `resources/mobile/` change for the chat path.
- **`RecommendationsAggregatorService`** is the wire-through chokepoint where tax already plugs in (line ~192); route non-tax modules through `ComposedModulePlanService` there. This feeds the dashboard, `NextActionsService` (`/m`), and `/holistic-plan` automatically.
- **`/holistic-plan`** is the natural home for the cross-module composite view (affordability-ranked, sequenced, goal/life-event-aware) — web + `/m`.
- Per-module pages mirror `/tax-strategy` where a module page benefits.

---

## 7. Build sequence (detailed in the plan)

1. **DTO cost fields + generalize composer.** `ModuleStrategySource` + `ComposedModulePlanService`; refactor tax onto it with **zero behaviour change**, pinned by the tax suite + `planDigest`/`extractStrategyIds` parity. (Highest-risk step — tax is live on prod.)
2. **Per-module sources + adapters** (Retirement/Savings/Investment first, then Protection/Estate) + the `strategy_type`/`source='strategy'` migration + author DB metadata + extend the `availability()` vocabulary.
3. **Catalogue triple:** author house_view narratives + pointer defs per module; register handlers; reindex.
4. **`CompositePlanService`** — affordability rank+annotate (wire `CashFlowCoordinator`), cross-module sequencing/conflicts, goals-as-demands, life-events-as-modifiers, episodic recall in ranking; `CrossModulePlanHandler` (tool).
5. **Surfaces** — `RecommendationsAggregatorService` wire-through, `/holistic-plan` composite view, Fyn handlers, `/m` parity, dashboard.
6. **Tests + golden masters + browser E2E** (web + `/m`).

---

## 8. Testing & risk

- **Tax refactor (Phase 1) is the highest risk** (live on prod). Pin with the existing tax suite + a `planDigest` byte-equality assertion **before** any new module is added. No tax behaviour change permitted.
- Each module source gets a `ComposedModulePlanTest` mirroring the tax test.
- `CompositePlanService` gets unit cases for affordability tiers, cross-module sequencing, goal-as-demand, life-event windows, and episodic-recall ranking effects.
- Pointer/handler registration: each new pointer requires a paired registry test (handler registered, mode correct) per the GroundGate/pointer PR-checklist convention.
- Browser E2E per Rule #14 on web **and** `/m`: ask Fyn for a module plan + the cross-module plan; verify ranking/locked/affordability annotation; verify a captured preference changes ranking next session.
- Golden-master regen for any new tool-schema/pointer corpus files.

---

## 9. Out of scope / deferred

- **Phase 6 learning actions** — episode→semantic promotion (human-reviewed), procedure-amendment proposals, dense similarity recall (`fynla-coala-implementation-plan.md:804`).
- **Goal↔event binding** (`goal_id` on `LifeEventAllocation`) — follow-up (D9).
- **Phase 3** working-memory VO consolidation — separate refactor.
- **Option A** shell deletion; planner default flip; A1/A2 overlay flip-to-active; any change to `FynSystemPrompt::text()` (byte-invariant) or the write-safety contract.
- **Prod deploy** — CSJ's call, after dev + csjones green.

---

## 10. Success criteria

1. Every module produces a composed plan (ordered, conflict-aware, claim-tier-voiced, with locked-strategy unlock prompts), reached via a pointer/handler through the FynLoop — verified web + `/m`.
2. The cross-module composite plan (tool) ranks by affordability with `fits`/`partially_fits`/`beyond_current_surplus` annotations and the running surplus; goals enter as demands; near-term life events shift the window. Nothing silently dropped.
3. Tax behaviour is byte-identical post-refactor (`planDigest` parity green; tax suite green).
4. Non-tax catalogue knowledge authored as the full triple (DB metadata + house_view narrative + pointer) per module; `fyn:semantic:reindex`/`fyn:pointers:reindex` clean.
5. A recommendation outcome captured as an episode is recalled next session and demonstrably shapes ranking/voicing (capture+recall only; no promotion).
6. Full suite green; browser E2E green on web **and** `/m`.
