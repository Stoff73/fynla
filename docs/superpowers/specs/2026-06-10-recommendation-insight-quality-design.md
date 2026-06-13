# Recommendation Engine & Fyn Insight Quality — Design Spec

**Date:** 2026-06-10
**Status:** Design approved in dialogue (2026-06-10); written spec awaiting CSJ review
**Owner:** CSJ
**Tracks:** Two-track — Track 1 lands on `dev` now; Track 2 lands on `coala` and merges with it.

---

## 1. Problem statement

Fynla's purpose is personal financial planning: when a user enters information (or Fyn asks for it), the app surfaces strategies, recommendations, and insights into their financial lives that they would not otherwise have known — gated by KYC so advice is only given when the relevant facts (employment status, income and income type, tax wrappers held, marital status) are known.

Testing on 2026-06-09 (user `test@phailanx.co.uk`, grok-4.3, savetax guided flow — "the Azlan transcript") showed the current iteration falls short. CSJ's selected failure modes:

1. **Generic, no "aha"** — strategies anyone could Google, not specific to the user's income band, marriage, and wrappers.
2. **Not proactive** — answers the literal question only; never volunteers the missing strategy.
3. **Visible tool churn / messy chain-of-thought** — robotic ack bubbles, raw dumps, hedging boilerplate.

Observed in the transcript (beyond the selected modes):

- **A direct user question was ignored.** "What's salary sacrifice?" received "Got it — recording those now." — the capture state machine railroaded over a teachable moment that was also the highest-value strategy for a £110k earner.
- **The single most obvious move never surfaced.** £81,000 in taxable savings at 3.25%, £19,900 of ISA allowance unused — "wrap £20k before April" was computed (`IsaTopUpStrategy` preconditions all pass: interest £2,632 > PSA £500, transferable £19,900, saving ≈ £259/yr) but voiced nowhere.
- **Mushy arithmetic.** "Around £90000" over 3 years was ambiguous (per-year would mean annual-allowance *charges*, not headroom); no clarification sought; "£120,000 unused", "top up £40,000", "reclaim £16,000" appeared with no visible working and an unexplained cap.
- **No synthesis.** Strategies dribbled out one per capture step; never consolidated into a ranked plan with a combined total.
- **Ack defects.** Double-ack concatenation ("Got it — recording those now.Recorded."), and an ack claiming "recording" on a turn with zero tool calls.

## 2. Verified current state

There is no single recommendation catalogue — there are **three partial ones**, plus two wiring defects.

### Layer 1 — DB action-definition catalogue (March 2026)

Six `{module}_action_definitions` tables (estate, retirement, investment, savings, tax, protection): `key`, `source`, `title_template`/`description_template`/`action_template` (with `{placeholder}` substitution), `category`, `priority` enum, `scope`, `what_if_impact_type`, `trigger_config` JSON, `is_enabled`, `sort_order`. Seeders, admin controllers, admin UI. Each module's `*ActionDefinitionService` maps `trigger_config.condition` → a private PHP evaluator method.

**Defect 1 — orphaned tax service.** `TaxActionDefinitionService::evaluateActions` (`app/Services/Tax/TaxActionDefinitionService.php:40`) has zero callers in `app/`. Its five evaluators (`isa_not_maxed`, `pension_carry_forward_available`, `spousal_transfer_beneficial`, `cgt_allowance_unused`, `high_dividend_in_gia`) are dead code attached to a seeded table nobody reads.

### Layer 2 — code strategy registry (June 2026, savetax)

Thirteen `app/Services/Tax/Strategies/*` classes implementing the `TaxStrategy` contract (`generate(TaxStrategyContext): StrategyRecommendation[]`), with real quantifiers, `TaxStrategyMath` (marginal rates, PSA by band, ISA subscription estimates), and household modes (`single` / `dual_earner` / `single_earner_couple`) including the 9-band `IncomeBandStrategy` decision tree. Composed by `TaxStrategyCalculator` (runs all 13, sorts category → priority) for the `/tax-strategy` terminal page and the savetax campaign flow.

**Duplication with Layer 1:** `isa_not_maxed` ↔ `IsaTopUpStrategy`; `pension_carry_forward_available` ↔ `PensionAACarryForwardStrategy`; `spousal_transfer_beneficial` ↔ `JointSavingsStrategy`/`AssetShiftingBundleStrategy`/`CrossSpouseBundleStrategy`. Same strategies, different copy, different maths depth, different surfaces.

**Reach defect:** `RecommendationsAggregatorService` aggregates six modules — tax is not one of them. The 13 strategies never reach dashboards, `/m` next-actions, or Fyn's `get_recommendations`. The conversational savetax flow voices a hardcoded per-state subset (e.g. the "£40,000 of unused tax allowances" copy at `app/Services/Onboarding/OnboardingStateMachine.php:509`) with no final synthesis, so strategies the calculator produces (Azlan's ISA wrap) can be silently dropped: greedy per-step selection with no global view.

### Layer 3 — prompt knowledge (`app/Constants/FinancialPlanningKnowledge.php`)

~1,600–1,800 tokens of UK planning concepts: `INCOME_CLASSIFICATIONS`, `PENSION_KNOWLEDGE`, `INVESTMENT_TAX_WRAPPERS`, `ESTATE_PLANNING_CONCEPTS`, `PROTECTION_CONCEPTS`, `RECOMMENDATION_FRAMEWORK` ("decision trees across 6 modules" — how Fyn should explain recommendations), and `AFFORDABILITY_RULES` (check surplus, emergency fund, high-interest debt, competing goals, relevant-UK-earnings cap **before** recommending any contribution).

**Defect 2 — dropped in the unified cutover.** `buildKnowledgeBlock` is private to the legacy 12-layer path (`app/Services/AI/AdvicePromptBuilder.php:189`, `:1113`); `FynContextAssembler` (unified, live default since 2026-05-17) has no knowledge layer. Live Fyn runs with **no affordability rules and no recommendation framework**. Same regression family as the known billing-layer gap; per CSJ's standing law a deleted legacy layer with no assembler replacement is a critical parity regression.

### Other verified facts

- Pension contribution history **is** stored canonically: `capture_pension_history` → `PensionNormaliser` → `PensionStore::captureInputHistory` (`app/Agents/CoordinatingAgent.php:4090`).
- KYC machinery is structurally sound: `KycGateChecker` (universal: DOB, marital status, employment status, income, expenditure) + `PrerequisiteGateService` + per-module `*DataReadinessService` (blocking/warning/info), enforced in chat prompt + tool dispatch, the aggregator, and `/m` unlock cards. The gaps are **missing fields**, not missing gates: salary-sacrifice availability/employer match, explicit ISA subscriptions this tax year, spouse pension existence, student loan plan, child benefit exposure.
- Fyn advice loop today: unified prompt (~4.3k tokens static), context 3.5–5.5k tokens on advice turns (all modules' detail injected even for module-scoped queries), temperature 0, `reasoning_effort: 'none'`, tool caps 8/5/3, free-running tool loop, `get_recommendations` returns a raw ranked array with a one-line tool description.
- Ack copy sources: `app/Services/AI/Fyn/FynCaptureTurnInstructions.php:48`, `app/Services/Onboarding/OnboardingPromptBuilder.php:173`; re-narration noted at `app/Services/Onboarding/OnboardingChatDirector.php:1908`.
- The `missing_amounts` response validator fires false positives on capture-ack turns (visible in the Azlan transcript metadata).

## 3. Locked decisions

1. **Two-track.** Track 1 (dev, now): catalogue reconciliation, KYC fields, behavioural fixes, prompt/tool wins — all branch-independent or additive. Track 2 (coala): house_view corpus, recommendation pointer, planner heuristics, capture overlays.
2. **Surface priority:** guided flows (savetax/onboarding) → free-form advice chat → recommendations UI (web + `/m`).
3. **Tiered boldness:** mechanical claims (allowance arithmetic, taper maths, carry-forward) are stated directly and quantified with the user's numbers; judgement claims (investment choice, trusts, drawdown) stay hedged; FCA signposting everywhere.
4. **Approach: reconcile the existing catalogues — do not create a fourth.** The March DB registry has the right architecture, the June strategy classes have the right maths, the knowledge layer has the right LLM guidance; they were never reconciled.
5. Eval laws apply: evals drive the full HTTP journey; failing assertions mean fix the code; no mirror users.

## 4. Design — substance layer (reconcile the catalogues)

**1a. One id namespace, one substance per strategy.** The DB action-definition row remains the registry entry (copy templates, category, priority, enable/sort — admin-manageable). The June strategy-class pattern becomes the single evaluator/quantifier. `*ActionDefinitionService` evaluator methods that duplicate a June strategy delegate to that strategy class; the orphaned tax definitions are wired through `TaxStrategyCalculator`. Every strategy exists exactly once; chat, terminal page, dashboards, and Fyn read the same substance.

**1b. Catalogue metadata on the existing tables** (new columns, seeder-maintained, admin-visible):
- `claim_tier` — `mechanical` | `judgement`; drives directive vs hedged voicing.
- `required_data` JSON — typed data-point keys a strategy needs before it can be assessed. Unmet requirements do not silently skip the strategy; they surface as an unlock prompt ("answer X to unlock a strategy worth roughly £Y").
- `sequencing` JSON — `do_before: []`, `conflicts_with: []` (e.g. own-ISA wrap before gifting all savings to spouse; debt repayment before taxable saving).

**1c. `HouseholdFinancialContext` builder.** One service computing what engines currently lack: both spouses' marginal rates (wrapping `TaxStrategyMath::bandRateFor`), allowance-usage grids both sides (ISA subscriptions this year, pension annual allowance + carry-forward from `PensionStore` history, PSA, dividend, CGT exemption), wrappers held both sides, debt position. Consumed by tax strategies, estate trust strategies (combined nil-rate bands), retirement action definitions (marginal-rate-scaled relief), and the `NonEarnerSpousePensionStrategy` (replacing the hardcoded £720 with actual spouse circumstances).

**1d. Tax becomes the seventh aggregator module.** `RecommendationsAggregatorService` gains a `tax` module backed by `TaxStrategyCalculator`, gated by the existing `tax_optimisation` prerequisite gate. This single wiring change un-orphans Layer 1's tax table and puts every tax strategy on dashboards, `/m` next-actions, and `get_recommendations`.

**1e. `StrategyPlanComposer`.** The synthesis layer: takes all eligible strategies + sequencing metadata, resolves conflicts, orders them, and produces a ranked plan with per-strategy working and a combined total ("together: ~£7,540 this year"). Consumed by the savetax final turn, the `/tax-strategy` page, and `get_recommendations`.

**1f. Restore the knowledge layer to the unified prompt.** Re-add as a classification-scoped `FynContextAssembler` layer (the `QueryKnowledge::getForClassification` per-domain retrieval already supports scoping), built additively in coala's injection style to keep the merge trivial. `RECOMMENDATION_FRAMEWORK` is updated to describe the reconciled catalogue. Affordability rules return to every advice turn.

**New strategy candidates** (each a strategy class + tests; CSJ approves the final set): debt-before-savings, spouse-ISA-after-gift, contribution-method comparison (salary sacrifice vs personal vs employer-match maximisation — including explaining salary sacrifice when asked), marriage allowance (with ineligibility explanation where relevant), child benefit taper, emergency-fund proportionality, premium-bonds reality check.

## 5. Design — surface integrations

### 5A. Guided flows (priority 1)

- **A1. Answer-the-user-first.** A user question is never ack-and-advanced past. Capture-turn instructions gain a hard rule (answer fully first, then resume capture); the state machine supports a non-advancing answer turn: answer → re-prompt the current step; advance only when the step's data arrives.
- **A2. Kill ack noise.** No standalone ack bubbles; acks merge into the next prompt as one bubble ("Recorded. Now, your savings outside an ISA…"). Fix the double-ack concatenation. Emit an ack only when a write actually occurred.
- **A3. Catalogue-driven per-step voicing.** The 9-state flow shape stays; each step's strategy moment is computed, not hardcoded: after capture, run the strategies whose inputs changed and voice the top mechanical-tier result with quantified working. Hardcoded state-machine strategy copy becomes template-from-catalogue so chat, terminal page, and dashboards never disagree.
- **A4. Final synthesis turn.** The flow ends with the `StrategyPlanComposer` output voiced as a ranked plan — ordered, conflict-resolved, combined total, one-line working per item — and persisted so `/tax-strategy` shows exactly what Fyn said. Dropped per-step strategies (Azlan's ISA wrap) re-surface here by construction.

### 5B. Free-form advice chat (priority 2)

- **B1. `get_recommendations` returns the composed plan** (top strategies with working, sequencing, claim tiers, plus a locked-strategy count), not a raw array. Tool description rewritten: when to call it, how to present it (surface top 3–5, quote rationale, offer the rest).
- **B2. Voicing rules in the unified prompt.** Mechanical-tier: direct, quantified, visible working ("£110,000 − £10,000 contribution = £100,000, restoring your full Personal Allowance — worth ~£6,000"); quote tax figures retrieved from `get_tax_information`. Judgement-tier: hedged + signposted. One bounded proactivity rule: after answering, Fyn may surface the single highest-value unsurfaced strategy when relevant — capped at one per turn.
- **B3. Context scoping.** Module-scoped questions get module-filtered financial detail (target ~60% context reduction on those turns); composed strategy *headlines* remain holistic on every advice turn — cross-module insight is the product.

### 5C. Recommendations UI (priority 3 — mostly free via 1d)

Web cards and `/m` next-actions consume the same composed output: quantified saving, "why this number" working, sequence position. `NextActionsService`'s unlock-card pattern extends from module-level to strategy-level ("Answer one question — does your employer offer salary sacrifice? — to unlock a strategy worth roughly £700/year"). Rules 12 (no scores) and 15 (no icons) apply to all card content.

### 5D. KYC capture extensions

**No orphan capture:** a new field exists only if at least one strategy's `required_data` consumes it. Candidate fields from the strategy set: employer salary-sacrifice availability + match structure; explicit ISA subscriptions this tax year (the flow already asks; give the answer a proper schema home — `estimateIsaSubscriptionsThisYear` currently estimates); spouse pension existence; and — only if the corresponding strategies are approved — student loan plan and child benefit exposure. Each field slots into existing capture flows and readiness services; gates then gate on it.

## 6. Eval & quality harness

- **Golden scenarios from preview personas** (they span the income ladder + marital statuses). The Azlan transcript replayed is golden scenario #1; add one each for peak_earners, young_saver, entrepreneur, retired_couple. Each scenario defines: **must-surface** (Azlan: ISA wrap, salary-sacrifice explanation, carry-forward with the £90k ambiguity clarified), **must-answer** (no railroading), **must-synthesise** (final plan with combined total), **must-not** (standalone acks, "recording" claims on zero-write turns, unquantified strategy claims).
- **Two tiers.** Tier 1: deterministic Pest tests on every strategy class — eligibility matrix × quantifier arithmetic vs hand-computed cases per persona (no LLM). Tier 2: HTTP-driven conversation evals through the real journey (full HTTP user journey, Sanctum bypass token, no mirror user), asserting on SSE + DB + final text.
- **Harness repair first.** Verify current state of the April-28 P0 bugs (request-scoped trace collector returning empty, `tool_calls[*].name = "unknown"`, HTTP call-count mismatch) and fix what remains. Give the `missing_amounts` validator capture-turn awareness.

## 7. Track 2 — coala integration

- **house_view corpus authored from the catalogue:** one corpus file per strategy id — narrative, methodology rationale, sequencing reasoning, claim tier. Un-pauses Task 10 content authoring with a concrete source; CSJ compliance-reviews one artefact set serving both tracks.
- **`RecommendationHandler` pointer** returns the `StrategyPlanComposer` structured plan instead of a bullet list.
- **Planner heuristics:** routing guidance in the planner prompt (recommendation intent → fetch composed plan; strategy locked by missing data → ask the unlock question).
- **Capture-turn overlays:** A1/A2 behavioural rules mirrored as procedural overlay `.md` files so they ride the corpus under FynLoop.
- **Conflict containment:** 1f is additive and styled like coala's own injection layers.

## 8. Testing & verification

Tier-1 strategy unit tests; per-persona aggregator/composer feature tests; browser scenarios (Playwright, Rule #14 loop-until-green) for the guided-flow fixes including an Azlan-replica end-to-end run; existing ~1.5k savetax tests stay green; `/tax-strategy` page output gets a per-persona golden master so chat and page cannot diverge silently.

## 9. Scope boundaries

**In:** Sections 4–7; estate/retirement spouse-awareness via `HouseholdFinancialContext`.
**Out (reported, not built):** Monte Carlo/stochastic projections; dashboard layout redesign; any change to the write-safety contract (AdviceFyn stays read-only; writes stay behind `delegate_to_capture`); new modules; embeddings (sparse-only per CoALA decisions).
**Verify before building (do not assume):** estate 7-year gift lookback (a `GiftingStrategy` service exists — check before claiming the gap); where the Azlan "£100 ISA this year" answer landed; current state of the eval P0 bugs; whether `FinancialPlanningKnowledge` is consumed anywhere else that constrains edits.

## 10. Success criteria

1. Azlan-replica eval passes its must-surface / must-answer / must-synthesise / must-not assertions end-to-end in the browser.
2. All persona golden scenarios green; Tier-1 strategy maths tests green; existing savetax suite green.
3. The same strategy substance is observably identical across chat, `/tax-strategy`, web dashboard, and `/m` for a seeded persona.
4. Every mechanical-tier claim in a sampled conversation shows its working and quotes tool-retrieved tax figures.
5. CSJ's manual re-test of the Azlan journey reports the failure modes resolved.
