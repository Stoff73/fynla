# Fyn v2 — Canonical Two-Fyn Contract

> **BRANCH: `feature/fyn-persona-split`.** All implementation builds on this branch.

This statement is the source of truth for every doc, spec, plan, PRD, and task list in this workstream. It appears verbatim at the top of every artefact.

---

**FYN HAS TWO STATES.**

**ONBOARDING FYN** takes a user through the onboarding flow using bubbles for the user to choose the path, and guides them through the flow they choose. It accepts multi-line information and **SAVES AND WRITES** it to the database so user information is persisted. It has memory: any additional information already entered is not asked about again, but is resurfaced to the user at the right time to give a view of intelligence. If a user leaves at any point in the conversation, the next time they log in Onboarding Fyn picks up from where they left off (example only, not the whole scope: *"Good afternoon CSJ — last time we were busy entering your family details, you told me about X. Do you want to continue from where we left off?"* Yes / No bubble). Journeys are mapped according to what the user wants and where they enter onboarding from. Onboarding Fyn also receives handovers from Advice Fyn for any outstanding information needed to produce guidance. **Onboarding Fyn is the ONLY state that enters or edits information.**

**ADVICE FYN** takes a user request, fetches the user's information, and answers that request using the recommendation engine, the risk module, and every other module or system in the app as needed. Examples only, not the whole scope:

- *"Where's my invoice?"* → Advice Fyn checks subscription status and navigates to the subscription page, confirming the subscription.
- *"Should I contribute more to my ISA?"* → Advice Fyn uses the recommendation engine to surface the guidance the engine produces and navigates to the portfolio page.

Advice Fyn covers tax optimisation (income tax, asset splitting between spouses, etc.), and all other guidance across every module as per the financial planning remit, classification system, recommendation engine, and all the investment, retirement, protection, estate engines and modules. **The ONLY thing Advice Fyn does NOT do is enter or edit information** — that is Onboarding Fyn's job.

**THE USER NEVER SEES THE HANDOFF, OR FEELS THE SWITCH**, between the two states.

---

## What this means for code

- One dispatch decision in `AiChatController::sendMessage`: onboarding or advice, based on `users.onboarding_completed`.
- Onboarding Fyn = the existing `OnboardingChatDirector` (promoted) with a new `handleInlineCapture` entry point for post-onboarding captures.
- Advice Fyn = a new `AdviceFyn` class wrapping the advice-side prompt + chat loop + read-only tool list.
- No `FynPersonaOrchestrator`, no `FynPersonaInvoker`, no `FynPersonaRegistry`, no `DataCapturePromptBuilder`.
- `HandoffContract` constants and `CaptureContext` VO are kept.
- Zero SSE events visible to the frontend that distinguish the two states. No `persona_state_change` event. No capturing pill. Input placeholder invariant.

## What this means for the user

- Onboarding feels like a friendly guided flow with clickable choices and open-text questions.
- Advice feels like a conversational assistant that knows their situation, answers with real data + engine-generated guidance, and navigates them to the right module page.
- When Advice Fyn needs more information to answer something, the request for that information arrives as a natural continuation of the conversation — no "switching to capture mode" preamble, no sudden bubbles.
- Resuming on a new device / session / after a disconnect picks up exactly where the user left off.

## What this means for evaluation

- `01-invariants.md` breaks this contract into ~35 falsifiable invariants. Each invariant has a specific test.
- `fyn-rubrics.md §B` contains 75 golden conversations that exercise the contract end-to-end.
- Scenario category `09-canonical-behaviour` (10 scenarios) is the core canonical-contract test set. Any regression in that category blocks merge.

---

*Source of truth. Do not paraphrase when copying into other docs — paste verbatim.*

---

# Plan — `12-sprint-2-plan.md` (Sprint 2: batch-shaped capture tools across 18+ entity types)

> **Canonical contract:** [`../spec/00-canonical.md`](../spec/00-canonical.md).
> **Branch:** all implementation commits on `feature/fyn-persona-split` (or `feature/csj/sprint2-<subtask>`). Sprint 2 starts only after Sprint 1 merged.
> **Sources:**
> - Source spec: [`../spec/12-sprint-2-plan.md`](../spec/12-sprint-2-plan.md)
> - Audit evidence: [`../audit-evidence.md`](../audit-evidence.md)
> - Audit synthesis: [`../audit-synthesis.md`](../audit-synthesis.md)
> - Rubrics: [`../fyn-rubrics.md`](../fyn-rubrics.md)

**Goal (per source spec):** Add 14 batch-shaped capture tools so every one of the 18+ entity types accepts multi-entity input. Retire `AssetCaptureEntityExtractor` conditional on sustained <2% gap-fill fire rate. Expand Rubric B to 65+ scenarios + enable Mode 2 weekly cron. End state Rubric-A ~22/40 🟠 Limited beta (upper). Catalogue grows from 40/40 → 54/54.

**Pre-flight gate:** Sprint 1 merged; Rubric-A ≥17/40; Rubric-B Mode 1 green at 30 scenarios.

---

### S2.1 — `capture_protection_policies` (prototype)

- **Objective:** Register `capture_protection_policies(policies: [...])` on both providers; implement handler that persists `LifeInsurancePolicy` / `CriticalIllnessPolicy` / `IncomeProtectionPolicy` rows via single `DB::transaction`; all-or-none on failure.
- **Spec reference:** Source spec Task 2.1 + INV-2.8.2, INV-2.7.1.
- **Files affected:**
  - MODIFY `app/Services/AI/AiToolDefinitions.php` — add tool per spec lines 103-131 (strict schema with `policy_type` enum `life|critical_illness|income_protection`, `additionalProperties: false`, `minItems: 1`).
  - MODIFY `app/Services/AI/XaiToolDefinitions.php` — `wrapTool` with `strict: true`.
  - MODIFY `app/Agents/CoordinatingAgent.php::executeTool` — `handleCaptureProtectionPolicies($input, $user, $isPreview)` per spec lines 138-162 (match on `policy_type` → model `::create`, collect `['type','id']` entity_ids, return `['success' => true, 'entity_ids' => $ids]`).
  - CREATE `tests/Feature/AI/BatchCapture/ProtectionPoliciesTest.php` — (1) persists all items, (2) rolls back on mid-batch failure.
  - CREATE `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/protection_batch_3x.yaml` — 3 policies → one `capture_protection_policies` tool call → 3 rows.
- **Acceptance test:** Pest both tests green. Parity test `tests/Architecture/ToolCatalogueParityTest.php` passes at 41 (40 Sprint 0 + 1 new) before next task.
- **Out of scope:** Converting `handleCreateProtectionPolicy` singular tool to batch-only (both coexist).

---

### S2.2 — `capture_savings_accounts`

- **Objective:** Batch tool persisting multiple `SavingsAccount` rows; all-or-none on failure.
- **Spec reference:** Source spec Task 2.2 + INV-2.8.2.
- **Files affected:**
  - MODIFY `AiToolDefinitions.php`, `XaiToolDefinitions.php` — +1 tool with `accounts: [{account_name, provider, account_type, balance, interest_rate}]`.
  - MODIFY `CoordinatingAgent.php::executeTool` — new handler invoking `StoreSavingsAccountRequest` rules per row + `DB::transaction` around `SavingsAccount::create` calls.
  - CREATE `tests/Feature/AI/BatchCapture/SavingsAccountsTest.php`.
  - CREATE Eval scenario `03-multi-entity/savings_batch_3x.yaml`.
- **Acceptance test:** Tests green; parity 42/42.
- **Out of scope:** Duplicate-account detection across prior rows (gap-fill dedup remains per INV-2.9.5 in the singular path).

---

### S2.3 — `capture_pensions`

- **Objective:** Batch tool for `DCPension` / `DBPension` per `pension_type`; single transaction.
- **Spec reference:** Source spec Task 2.3 + INV-2.8.2.
- **Files affected:** `AiToolDefinitions.php`, `XaiToolDefinitions.php` +1 tool; `CoordinatingAgent.php::handleCapturePensions`; `tests/Feature/AI/BatchCapture/PensionsTest.php`; scenario `03-multi-entity/pensions_batch_2x.yaml`.
- **Acceptance test:** Tests green; parity 43/43.
- **Out of scope:** State-pension forecasts. Scheme-specific metadata beyond `provider`, `pot_value`, `monthly_contribution`.

---

### S2.4 — `capture_investment_accounts`

- **Objective:** Batch tool for multiple `InvestmentAccount` rows.
- **Spec reference:** Source spec Task 2.4.
- **Files affected:** Tool defs +1; handler; `tests/Feature/AI/BatchCapture/InvestmentAccountsTest.php`; scenario.
- **Acceptance test:** Tests green; parity 44/44.
- **Out of scope:** Holdings nested inside accounts (Task 2.14).

---

### S2.5 — `capture_properties_mortgages`

- **Objective:** Paired batch tool; each item is `{property: {...}, mortgage: {...}}`; persists `Property` then `Mortgage` with FK back to property in a single transaction.
- **Spec reference:** Source spec Task 2.5.
- **Files affected:** Tool defs +1 with nested shape; handler creates property row, then mortgage row with `property_id`; `tests/Feature/AI/BatchCapture/PropertiesMortgagesTest.php`; scenario.
- **Acceptance test:** Both rows exist; mid-failure rolls back both.
- **Out of scope:** Multiple mortgages per property. Buy-to-let income metadata beyond `Property.property_type`.

---

### S2.6 — `capture_trusts`

- **Objective:** Batch tool for `Trust` rows; triggers `TrustObserver` per row for estate-recalc.
- **Spec reference:** Source spec Task 2.6.
- **Files affected:** Tool defs +1; handler; `tests/Feature/AI/BatchCapture/TrustsTest.php`; scenario.
- **Acceptance test:** Rows persist; observer fires.
- **Out of scope:** Settlor linking across trusts (`Trust.settlor` is forbidden-to-update per INV-2.7.3).

---

### S2.7 — `capture_family_members`

- **Objective:** Batch tool; spouse branch delegates to `SpouseLinkingService::linkOrCreateSpouse` (preserving bidirectional permissions); dependants via direct `FamilyMember::create`.
- **Spec reference:** Source spec Task 2.7 + `app/Services/Onboarding/SpouseLinkingService.php:1-367`.
- **Files affected:** Tool defs +1; handler dispatches to `SpouseLinkingService` or `FamilyMember::create` per `relationship`; `tests/Feature/AI/BatchCapture/FamilyMembersTest.php`; scenario.
- **Acceptance test:** Spouse row creates `User`, `FamilyMember`, `SpousePermission` bidirectional; dependant row creates only `FamilyMember`.
- **Out of scope:** Cross-household linking. Existing spouse re-link.

---

### S2.8 — `capture_goals`

- **Objective:** Batch tool for `Goal` rows; triggers goal-contribution observers.
- **Spec reference:** Source spec Task 2.8.
- **Files affected:** Tool defs +1; handler; scenario; test.
- **Acceptance test:** Rows persist; `TracksGoalContributions` trait behaviour unchanged.
- **Out of scope:** Goal linkage to accounts (done separately via update_record).

---

### S2.9 — `capture_life_events`

- **Objective:** Batch tool for `LifeEvent` rows; triggers `LifeEventMonteCarloObserver`.
- **Spec reference:** Source spec Task 2.9.
- **Files affected:** Tool defs +1; handler; scenario; test.
- **Acceptance test:** Rows persist; Monte Carlo recalc observer fires.
- **Out of scope:** Scenario inference from goals (handled in existing UI).

---

### S2.10 — `capture_chattels`

- **Objective:** Batch tool for `Chattel` rows.
- **Spec reference:** Source spec Task 2.10.
- **Files affected:** Tool defs +1; handler; scenario; test.
- **Acceptance test:** Rows persist.
- **Out of scope:** Separate valuation workflow.

---

### S2.11 — `capture_business_interests`

- **Objective:** Batch tool for `BusinessInterest` rows.
- **Spec reference:** Source spec Task 2.11.
- **Files affected:** Tool defs +1; handler; scenario; test.
- **Acceptance test:** Rows persist.
- **Out of scope:** BPR valuation triggers.

---

### S2.12 — `capture_liabilities`

- **Objective:** Batch tool for `App\Models\Estate\Liability` rows.
- **Spec reference:** Source spec Task 2.12.
- **Files affected:** Tool defs +1; handler; scenario; test.
- **Acceptance test:** Rows persist.
- **Out of scope:** Auto-classifying liability type beyond enum.

---

### S2.13 — `capture_estate_gifts`

- **Objective:** Batch tool for `App\Models\Estate\Gift` rows; honours taper relief via existing model logic.
- **Spec reference:** Source spec Task 2.13.
- **Files affected:** Tool defs +1; handler; scenario; test.
- **Acceptance test:** Rows persist.
- **Out of scope:** Cross-checking gift dates against existing records.

---

### S2.14 — `capture_holdings`

- **Objective:** Batch tool for `Holding` rows keyed by `account_name` (parent investment account lookup); fails fast if parent account missing.
- **Spec reference:** Source spec Task 2.14.
- **Files affected:** Tool defs +1; handler looks up `InvestmentAccount::where('user_id',$user->id)->where('account_name',$parent)` or errors with `parent_account_not_found`; scenario; test.
- **Acceptance test:** Rows persist under correct parent; missing parent → error.
- **Out of scope:** Auto-creating parent investment account.

---

### S2.15 — Tool-description multi-entity affordance

- **Objective:** Update every singular `create_*` tool description (both providers) with `" Prefer capture_<entity>_batch when the user mentions multiple items in one message."` so the LLM picks batch when appropriate.
- **Spec reference:** Source spec Task 2.15 (ACI improvement).
- **Files affected:** `app/Services/AI/AiToolDefinitions.php`, `XaiToolDefinitions.php`.
- **Acceptance test:** Parity test still green at 54/54. Rubric-B multi-entity scenarios show LLM choosing batch tools when given list-style inputs (measured via `AssertionHelpers::assertToolCallsMatch`).
- **Out of scope:** Removing singular tools. Renaming existing tools.

---

### S2.16 — Eval harness expansion to 65+ scenarios + Mode 2 cron

- **Objective:** Author 8 prompt-injection + 5 regulatory + 4 provider-parity + 6 preview-persona + 8 more canonical-behaviour scenarios for a total of 65+; record real-provider fixtures; enable Mode 2 weekly cron.
- **Spec reference:** Source spec Task 2.16 + INV-2.13.1.
- **Files affected:**
  - CREATE scenario YAMLs + fixtures under `tests/Feature/Fyn/Eval/scenarios/{06-prompt-injection, 07-regulatory, 08-provider-parity, 02-preview-personas, 09-canonical-behaviour}/`.
  - MODIFY `app/Console/Kernel.php` — schedule `fyn:eval:run --mode=real` weekly (e.g. Sunday 02:00).
  - CREATE `app/Console/Commands/FynEvalRunCommand.php` if not already created in Sprint 1 Task 1.1 — wraps `EvalRunner::run` in Mode 2 over every scenario.
- **Acceptance test:** Mode 1 green at 65+. First weekly Mode 2 run ≥97%. `EvalScenarioCountTest` per-category minima pass.
- **Out of scope:** Back-dating weekly runs. Running Mode 2 on every PR (expensive; weekly cron is sufficient per spec).

---

### S2.17 — Ratchet Rubric-B floors

- **Objective:** Raise `recall_floor` + `precision_floor` in `config/fyn_eval.php` per CSJ decision 3 in `audit-synthesis.md §8` — protection 95→98, savings 95→98, mortgage 95/95→100/100.
- **Spec reference:** Source spec Task 2.17 + `audit-synthesis.md §8`.
- **Files affected:**
  - MODIFY `config/fyn_eval.php`.
  - Commit message tag `EVAL_FLOOR_RAISE: protection 95→98 — reason: Sprint 2 batch tools ready`.
- **Acceptance test:** Rubric-B eval stays green at new floors. `EvalFloorIntegrityTest` passes.
- **Out of scope:** Lowering any floor (requires `EVAL_FLOOR_LOWER:` tag per INV-2.13.4). Raising floors for categories where batch tools not yet shipped.

---

### S2.18 — Retire `AssetCaptureEntityExtractor` (conditional)

- **Objective:** If gap-fill fire rate sustained <2% over 2-week Mode-2 window, delete `AssetCaptureEntityExtractor` + its test + its call sites in `OnboardingChatDirector`; otherwise defer to Sprint 3.
- **Spec reference:** Source spec Task 2.18 + INV-2.8.2 trailing condition.
- **Files affected (delete):**
  - `app/Services/Onboarding/AssetCaptureEntityExtractor.php:1-665`.
  - `tests/Unit/Services/Onboarding/AssetCaptureEntityExtractorTest.php`.
  - Remove `emitGapFillFromCaptureContext` body from `OnboardingChatDirector` (keep empty generator yield OR delete call site).
- **Acceptance test:** Mode-2 eval stays green at post-retirement state; architecture grep confirms no residual references.
- **Out of scope:** Forcing retirement under 2% gate (defer if gate not met).

---

### S2.19 — Sprint 2 Playwright matrix (BS-17 batch-tool extension)

- **Objective:** Parameterise BS-17 using Pest `->with(...)` dataset over 14 batch-tool variants (protection, savings, pensions, investments, properties+mortgages, trusts, family_members, goals, life_events, chattels, business_interests, liabilities, estate_gifts, holdings); regression-run the 24 base scenarios; total matrix 38 runs.
- **Spec reference:** Source spec Task 2.19 + `spec/03-test-strategy.md §Per-sprint-scenario-index`.
- **Files affected:**
  - MODIFY `tests/Browser/scenarios/BS-17-multi-entity-persist.php` — add dataset provider with each variant's `{entityType, entities, modulePageRoute}` per spec lines 296-313.
  - Screenshots in `docs/sprint-2-verification/BS-17-<variant>/`.
- **Acceptance test:** `./vendor/bin/pest --testsuite=Browser --filter=BS-` → 24 base + 14 BS-17 variants = 38 PASS.
- **Out of scope:** Adding scenarios beyond BS-17 variants (Sprint 3 re-runs, Sprint 4 adds BS-25).

---

### S2.20 — Sprint 2 verification rollup

- **Objective:** Publish Sprint 2 verification: full Pest green, parity 54/54, Rubric-B Mode 1 100% at 65+, Mode 2 ≥97% first weekly, Browser matrix 38/38, Rubric-A re-score ~22/40.
- **Spec reference:** Source spec §Sprint-2-verification + `spec/01-invariants.md §verification` "Post Sprint 2".
- **Files affected:**
  - `docs/sprint-2-verification/rubric-a-score.md`.
  - PR body linking to verification evidence.
- **Acceptance test:** Rubric-A ≥22/40. No canonical §0 violation. No hard-fail floor violation.
- **Out of scope:** Dev deploy (Sprint 3). Production hardening (Sprint 4).

---

*End of plan for Sprint 2. Sprint 3 follows — local-first verification + dev deploy to `csjones.co/fynla`.*

**Post-sprint priorities:** see `15-post-sprint-priorities-plan.md` for the lifestyle + campaign landing-pages workstream, queued after Sprints 0-4 hit GREEN.
