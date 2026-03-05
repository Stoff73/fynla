# Holistic Plan Rewrite — Task Breakdown

**Reference:** `March4Updates/holisticPlanUpdate.md`
**Estimated files:** 7 new, 2 rewritten, 2 modified, 11 deleted

**Required agents/skills/commands for this task:**
- **`/feature-dev`** — Use for all component creation tasks (Tasks 2–7)
- **`premium-ui-designer`** agent — Use after each component is functional to polish UI, add micro-interactions, and ensure design system compliance
- **`security-reviewer`** agent — Use after Task 7 (view rewrite) to audit data flow and ensure no sensitive data leakage
- **`Explore`** agent — Use at the start of each task to verify existing component interfaces before coding
- **`tax-compliance-reviewer`** agent — Use after Task 6 to verify no hardcoded values in priority area calculations
- **`./vendor/bin/pest`** — Run after every task to catch regressions early
- **`php artisan db:seed`** — Run before and after every testing phase
- **`./dev.sh`** — Run to verify compile after each task
- **`designStyle.md`** — Read before any UI work (CLAUDE.md Rule 11)

---

## Task 1: Delete Old Holistic Infrastructure

**Goal:** Remove the old holistic system that duplicates individual plan work.

**Use:** `Explore` agent to confirm all imports/references to old holistic files before deleting.

### Checklist

- [x] Run `Explore` agent to find all imports of `holistic.js` store, `holisticService.js`, and Holistic components
- [x] Delete `resources/js/store/modules/holistic.js`
- [x] Delete `resources/js/services/holisticService.js`
- [x] Delete `resources/js/components/Holistic/ExecutiveSummary.vue`
- [x] Delete `resources/js/components/Holistic/FinancialSnapshot.vue`
- [x] Delete `resources/js/components/Holistic/ModuleSummaries.vue`
- [x] Delete `resources/js/components/Holistic/PrioritizedRecommendations.vue`
- [x] Delete `resources/js/components/Holistic/CashFlowAllocationChart.vue`
- [x] Delete `resources/js/components/Holistic/NetWorthProjectionChart.vue`
- [x] Delete `resources/js/components/Holistic/RiskAssessment.vue`
- [x] Delete `resources/js/components/Holistic/EstateSummarySection.vue`
- [x] Delete `resources/js/components/Holistic/GoalsSummarySection.vue`
- [x] Modify `resources/js/store/index.js` — Remove `holistic` module import and registration
- [x] Modify `resources/js/router/index.js` — Update holistic plan route breadcrumb
- [x] Remove any other references found by `Explore` agent (e.g., dashboard cards, nav links that import old components)
- [x] Run `./dev.sh` — verify compiles without errors
- [x] Run `./vendor/bin/pest` — verify no test regressions

---

## Task 2: Create Helper Components

**Goal:** Build the reusable wrapper and split components needed by the orchestrator.

**Use:** `/feature-dev` skill. Read `designStyle.md` before starting. Use `Explore` agent to review `InvestmentCurrentSituation.vue` and `PlanSectionHeader.vue` interfaces.

### 2a. `resources/js/components/Plans/Holistic/HolisticModuleSection.vue`

- [x] Read `designStyle.md` for section header colours and spacing
- [x] Read `resources/js/components/Plans/Shared/PlanSectionHeader.vue` for prop interface
- [x] Create component with `PlanSectionHeader` internally
- [x] Props: `title` (String, required), `subtitle` (String, default null), `color` (String, default 'gray')
- [x] Default slot for content
- [x] Verify: renders with each colour variant (blue, green, purple, teal, gray)

### 2b. `resources/js/components/Plans/Holistic/HolisticInvestmentSituation.vue`

- [x] Read `resources/js/components/Plans/Investment/InvestmentCurrentSituation.vue` for exact markup
- [x] Create component — investment accounts only
- [x] Shows: Investment accounts list with name, type, provider, holdings count, value
- [x] Shows: Total Investment Value footer row
- [x] Shows: Asset allocation, fee analysis, diversification, tax wrappers indicators (if present)
- [x] Props: `situation` (Object, required)
- [x] Uses: `currencyMixin` (Rule 6 — no local formatCurrency)
- [x] No amber/orange colours (Rule 9)
- [x] Verify: renders correctly with peak_earners investment data

### 2c. `resources/js/components/Plans/Holistic/HolisticSavingsSituation.vue`

- [x] Create component — savings accounts only
- [x] Shows: Savings accounts list with institution, type, interest rate, balance
- [x] Shows: Total Savings Value footer row
- [x] Shows: Emergency Fund indicator (months + colour: ≥6 green, ≥3 blue, <3 red)
- [x] Shows: ISA Used + ISA Remaining indicators
- [x] Props: `situation` (Object, required)
- [x] Uses: `currencyMixin`
- [x] No amber/orange colours (Rule 9)
- [x] Verify: renders correctly with peak_earners savings data

### Task 2 Testing

- [x] Run `./dev.sh` — compiles without errors
- [x] Run `./vendor/bin/pest` — no regressions
- [ ] Use `premium-ui-designer` agent to review visual quality and design system compliance

---

## Task 3: Create Executive Summary Component

**Goal:** Build the holistic-specific executive summary that lists included plans.

**Use:** `/feature-dev` skill. Use `Explore` agent to review existing executive summary components (RetirementExecutiveSummary, InvestmentExecutiveSummary, etc.) for pattern consistency.

### `resources/js/components/Plans/Holistic/HolisticExecutiveSummary.vue`

- [x] Read existing exec summary components for pattern reference
- [x] Read `designStyle.md` for card styling
- [x] Create card component using `PlanSectionHeader` with title "Executive Summary"
- [x] Greeting: "Dear {firstName}," (from personalInfo)
- [x] Introduction paragraph: explains this holistic plan brings together the individual plans
- [x] **Plan list:** Bullet list of included plans with display names:
  - `protection` → "Protection Plan"
  - `investment` → "Investment & Savings Plan"
  - `retirement` → "Retirement Plan"
  - `estate` → "Estate Plan"
- [x] Only list plans that are actually available (not null/failed)
- [x] Closing paragraph with personalised context
- [x] Props: `availablePlans` (Array, required), `personalInfo` (Object, default null)
- [x] No acronyms in user-facing text (Rule 10)
- [x] British spelling (Rule: Coding Standards)
- [x] No scores in UI (Rule 12)

### Task 3 Testing

- [x] Verify: renders correctly with all 4 plans available
- [ ] Verify: renders correctly with only 2 plans available
- [ ] Verify: renders correctly with only 1 plan available
- [x] Run `./dev.sh` — compiles without errors
- [x] Run `./vendor/bin/pest` — no regressions

---

## Task 4: Create Priority Area Component

**Goal:** Build the new priority allocation feature.

**Use:** `/feature-dev` skill. Use `Explore` agent to review `DistributionAccount.php` and how `cascade_params.additional_monthly` is set in plan services. Use `tax-compliance-reviewer` agent after implementation to verify no hardcoded tax values.

### `resources/js/components/Plans/Holistic/HolisticPriorityArea.vue`

- [x] Read `designStyle.md` for badge colours, progress bars, and table styling
- [x] Read `app/Services/Plans/DistributionAccount.php` for allocation logic reference
- [x] Read plan service files to verify `cascade_params` and `impact_parameters` field shapes
- [x] Create component using `PlanSectionHeader` with title "Priority Allocation", colour teal
- [x] Props: `allActions` (Array, required), `monthlyDisposableIncome` (Number, required)
- [x] Computed `sortedActions`:
  - [x] Filter to enabled actions only
  - [x] Goal-sourced actions first (where `source === 'goal'`)
  - [x] Then tax optimisation actions (category includes 'tax', 'ISA', 'allowance')
  - [x] Then by priority: critical (1) > high (2) > medium (3) > low (4)
- [x] Computed `allocatedActions`: map sortedActions with running total
  - [x] `monthlyCost`: `cascade_params?.additional_monthly || impact_parameters?.monthly_premium_estimate || impact_parameters?.premium || (estimated_impact ? estimated_impact / 12 : 0)`
  - [x] `runningTotal`: cumulative sum of monthlyCost
  - [x] `withinBudget`: runningTotal ≤ monthlyDisposableIncome
- [x] Monthly disposable income header with formatted currency
- [x] Budget progress bar (total allocated / total available)
- [x] Numbered action list with:
  - [x] Module badge (protection=purple, investment=blue, retirement=green, estate=gray)
  - [x] Priority badge (reuse pattern from PlanActionCard)
  - [x] Action title
  - [x] Monthly cost (right-aligned, formatted via currencyMixin)
  - [x] Running total
  - [x] Greyed out with "Exceeds available income" if withinBudget is false
- [x] Summary footer: "X of Y actions affordable within current disposable income"
- [x] Uses: `currencyMixin` (Rule 6)
- [x] No amber/orange colours (Rule 9)
- [x] No hardcoded monetary values — all from plan data
- [x] No scores in UI (Rule 12)

### Task 4 Testing

- [x] Verify: correct sort order (goals first, tax second, priority third)
- [x] Verify: running total accumulates correctly
- [x] Verify: actions beyond budget are visually distinguished
- [x] Verify: progress bar reflects allocation percentage
- [x] Verify: handles empty actions array gracefully
- [x] Verify: handles zero disposable income gracefully
- [ ] Run `tax-compliance-reviewer` agent to verify no hardcoded values
- [x] Run `./dev.sh` — compiles without errors
- [x] Run `./vendor/bin/pest` — no regressions
- [ ] Use `premium-ui-designer` agent to polish the priority area visuals

---

## Task 5: Create Conclusion Component

**Goal:** Aggregate conclusion data from all plans.

**Use:** `/feature-dev` skill. Use `Explore` agent to review `PlanConclusion.vue` for pattern reference.

### `resources/js/components/Plans/Holistic/HolisticConclusion.vue`

- [x] Read `resources/js/components/Plans/Shared/PlanConclusion.vue` for pattern reference
- [x] Collect essential actions (priority 1-2) and optional actions (priority 3+) from all plans
- [x] Group by module with module label badges
- [x] Show total action count and enabled action count
- [x] Summary text contextualised to holistic plan (not "retirement goal" specific)
- [x] Props: `conclusions` (Object — keyed by plan type), `allActions` (Array — merged + tagged)
- [x] No acronyms in user-facing text (Rule 10)
- [x] British spelling

### Task 5 Testing

- [x] Verify: renders with data from 4 plans
- [ ] Verify: renders with data from 1 plan
- [x] Verify: essential vs optional split is correct
- [x] Verify: module badges display correctly
- [x] Run `./dev.sh` — compiles without errors
- [x] Run `./vendor/bin/pest` — no regressions

---

## Task 6: Create Main Orchestrator

**Goal:** Build the central content component that composes everything together.

**Use:** `/feature-dev` skill. Use `Explore` agent to verify prop interfaces of all reused components (RetirementGroupedActions, InvestmentGroupedActions, EstateGroupedActions, PlanActionsList, PlanWhatIfComparison, ProtectionWhatIfControls, InvestmentPersonalInformation, all CurrentSituation components).

### `resources/js/components/Plans/Holistic/HolisticPlanContent.vue`

- [x] Use `Explore` agent to confirm prop interfaces for all reused child components
- [x] Props: `protectionPlan`, `investmentPlan`, `retirementPlan`, `estatePlan` (all Object, default null)
- [x] Computed `availablePlanTypes`: array of type strings for non-null plans
- [x] Computed `personalInfo`: from first available plan's `personal_information`
- [x] Computed `mergedActions`: all actions from all plans, each tagged with `_module` field
- [x] Computed `monthlyDisposableIncome`: from any plan's `personal_information.monthly_disposable`
- [x] Computed `conclusionsMap`: `{ protection: plan.conclusion, ... }` for non-null plans
- [x] Computed `computedProtectionProjectedScenario`: replicate from ProtectionPlanContent lines 83-137
- [x] Data `chartMetrics`: protection coverage types array
- [x] Section 1: `HolisticExecutiveSummary` with available plans and personal info
- [x] Section 2: `InvestmentPersonalInformation` :info="personalInfo" (shown once)
- [x] Section 3 — Current Situation:
  - [x] Protection → `ProtectionCurrentSituation` (v-if protectionPlan)
  - [x] Investment → `HolisticInvestmentSituation` (v-if investmentPlan)
  - [x] Savings → `HolisticSavingsSituation` (v-if investmentPlan)
  - [x] Retirement → `RetirementCurrentSituation` (v-if retirementPlan)
  - [x] Estate → `EstateCurrentSituation` (v-if estatePlan)
- [x] Section 4 — Recommended Actions:
  - [x] Protection → `PlanActionsList` + `PlanWhatIfComparison`/`ProtectionWhatIfControls` with `computedProtectionProjectedScenario`
  - [x] Investment & Savings → `InvestmentGroupedActions` with toggle + funding source events
  - [x] Retirement → `RetirementGroupedActions` with pension_projections, toggle + funding source events
  - [x] Estate → `EstateGroupedActions` with toggle events
- [x] Section 5: `HolisticPriorityArea` with merged actions and disposable income
- [x] Section 6: `HolisticConclusion` with conclusions map and merged actions
- [x] Toggle events: attach correct `planKey` before emitting to parent
- [x] Funding source events: attach correct `planKey` before emitting to parent
- [x] Emits: `toggle-action` (with `{ planKey, actionId }`), `update-funding-source` (with `{ planKey, ... }`)

### Task 6 Testing

- [x] `php artisan db:seed`
- [x] Run `./dev.sh`
- [ ] Login as peak_earners preview persona
- [ ] Verify: all 5 current situation sections render
- [ ] Verify: all 4 action sections render with action cards
- [ ] Verify: toggle a retirement action → cascading chart updates
- [ ] Verify: toggle a protection action → what-if bar chart updates
- [ ] Verify: toggle an investment action → cascading chart updates
- [ ] Verify: toggle an estate action → what-if updates
- [ ] Verify: priority area shows all actions with correct sort and allocation
- [ ] Verify: conclusion aggregates from all plans
- [ ] Verify: no console errors
- [x] Run `./vendor/bin/pest` — no regressions
- [ ] Run `security-reviewer` agent to audit data flow
- [ ] Run `tax-compliance-reviewer` agent to check for hardcoded values
- [x] `php artisan db:seed`

---

## Task 7: Rewrite Holistic Plan View

**Goal:** Replace the view to use the plans store instead of the holistic store.

**Use:** `/feature-dev` skill. Use `Explore` agent to review `RetirementPlan.vue` as the pattern to follow.

### `resources/js/views/HolisticPlan.vue`

- [x] Read `resources/js/views/Plans/RetirementPlan.vue` for view pattern
- [x] Use `PlanPageLayout` wrapper (title="Holistic Financial Plan", subtitle, loading, error, print button)
- [x] Import and use `planPrintMixin`
- [x] Local state: `holisticLoading: true`, `holisticError: null`, `planErrors: {}`
- [x] On mount: `loadAllPlans()` — fetch all 4 plans via `Promise.allSettled`
  - [x] Fetch: `protection`, `investment`, `retirement`, `estate` in parallel
  - [x] Track per-plan errors (non-fatal — show whatever loaded)
  - [x] Set `holisticLoading = false` after all settle
- [x] Computed properties:
  - [x] `protectionPlan`, `investmentPlan`, `retirementPlan`, `estatePlan` via `getPlan` getter
  - [x] `anyPlanLoaded`: at least one plan is not null
  - [x] `loading`: uses local `holisticLoading` (not shared store loading)
  - [x] `error`: `holisticError`
- [x] Methods:
  - [x] `loadAllPlans()` — parallel fetch with Promise.allSettled
  - [x] `handleToggle({ planKey, actionId })` — dispatch `plans/toggleAction`
  - [x] `handleUpdateFundingSource({ planKey, ...payload })` — dispatch `plans/updateActionFundingSource`
  - [x] `handlePrint()` — call `this.printHolisticPlan(plans)` from mixin
- [x] Pass all plans to `HolisticPlanContent`
- [x] Route toggle and funding source events correctly

### Task 7 Testing

- [x] `php artisan db:seed`
- [x] Run `./dev.sh`
- [ ] Navigate to `/holistic-plan` as peak_earners
- [ ] Verify: loading state shown during fetch
- [ ] Verify: all plan sections render after load
- [ ] Verify: page handles one plan failing gracefully (others still shown)
- [ ] Verify: "Back to Plans" button works
- [ ] Verify: refresh button reloads all plans
- [ ] Verify: no console errors
- [x] Run `./vendor/bin/pest` — no regressions
- [ ] Run `security-reviewer` agent to audit the view
- [x] `php artisan db:seed`

---

## Task 8: Add Print/Save Support

**Goal:** Add holistic plan printing to the existing print mixin.

**Use:** `/feature-dev` skill. Use `Explore` agent to review existing print methods in `planPrintMixin.js` (`buildProtectionCurrentSituationHtml`, `buildRetirementCurrentSituationHtml`, `buildInvestmentCurrentSituationHtml`, `buildEstateCurrentSituationHtml`, `buildGroupedActionsHtml`, `buildSimpleActionsHtml`, `buildPersonalInformationHtml`, `buildStructuredExecutiveSummaryHtml`, `buildConclusionHtml`).

### Modify `resources/js/components/Plans/Shared/planPrintMixin.js`

- [x] Read existing `planPrintMixin.js` to understand `printPlan()` flow and all builder methods
- [x] Read `designStyle.md` for print styling conventions
- [x] Add `printHolisticPlan(plans)` method:
  - [x] Entry point — accepts `{ protection, investment, retirement, estate }` (any can be null)
  - [x] Calls `buildHolisticPlanHtml(plans)` to generate HTML
  - [x] Opens print window, loads logo, triggers print (same pattern as `printPlan`)
- [x] Add `buildHolisticPlanHtml(plans)` method:
  - [x] Title page: "Holistic Financial Plan" + date + user name
  - [x] Executive summary: lists included plan types
  - [x] Personal information: call `buildPersonalInformationHtml()` once, from first available plan
  - [x] Current Situation sections with module headers:
    - [x] Protection: call `buildProtectionCurrentSituationHtml()` (if plan exists)
    - [x] Investment: call `buildInvestmentCurrentSituationHtml()` (filtered/adjusted for investment only)
    - [x] Savings: render savings accounts table + emergency fund + ISA
    - [x] Retirement: call `buildRetirementCurrentSituationHtml()`
    - [x] Estate: call `buildEstateCurrentSituationHtml()`
  - [x] Actions sections with module headers:
    - [x] Protection: call `buildSimpleActionsHtml()` + `buildProtectionWhatIfHtml()`
    - [x] Investment & Savings: call `buildGroupedActionsHtml()` (handles both)
    - [x] Retirement: call `buildGroupedActionsHtml()`
    - [x] Estate: call `buildEstateActionsHtml()` or `buildActionsHtml()`
  - [x] Priority Area: call new `buildHolisticPriorityAreaHtml()`
  - [x] Conclusion: aggregated conclusion HTML
- [x] Add `buildHolisticPriorityAreaHtml(plans)` method:
  - [x] HTML table: #, Module, Action, Priority, Monthly Cost, Running Total
  - [x] Same sort logic as `HolisticPriorityArea.vue`
  - [x] Budget progress bar via CSS
  - [x] Uses `escapeHtml()` for all user content (XSS prevention)
  - [x] Uses `fmtCurrency()` for all monetary values
- [x] Running header/footer on every page (same pattern as other plans)
- [x] Page break controls: `page-break-inside: avoid` on sections

### Task 8 Testing

- [x] `php artisan db:seed`
- [x] Run `./dev.sh`
- [ ] Login as peak_earners preview persona
- [ ] Navigate to `/holistic-plan`
- [ ] Click "Print / Save PDF"
- [ ] Verify: title page renders with correct title and date
- [ ] Verify: executive summary lists plan names
- [ ] Verify: personal info renders once
- [ ] Verify: protection current situation renders with coverage cards
- [ ] Verify: investment accounts table renders
- [ ] Verify: savings accounts table renders
- [ ] Verify: retirement pensions and metrics render
- [ ] Verify: estate IHT table renders
- [ ] Verify: protection actions render with what-if bar chart
- [ ] Verify: investment/retirement actions render with cascading line charts (SVG)
- [ ] Verify: estate actions render with gifting schedules
- [ ] Verify: priority area table renders with running totals
- [ ] Verify: conclusion renders
- [ ] Verify: running header/footer on every page
- [ ] Verify: no content cut off by page breaks
- [ ] Verify: all charts render as static SVG in print (not blank)
- [ ] Login as widow persona — verify print with fewer plans
- [x] Run `./vendor/bin/pest` — no regressions
- [x] `php artisan db:seed`

---

## Task 9: End-to-End Testing & Polish

**Goal:** Verify the complete holistic plan works across all personas, polish UI.

**Use:** `premium-ui-designer` agent for final visual polish. Run full test suite. Browser test all personas.

### Cross-Persona Testing

- [x] `php artisan db:seed`
- [x] `./dev.sh`

**peak_earners (David & Sarah Mitchell):**
- [ ] All 4 plans load
- [ ] Executive summary lists all plans
- [ ] Personal info shown once with correct data
- [ ] Protection: coverage analysis, existing policies, actions with toggles, what-if bar chart (3 metrics)
- [ ] Investment: investment accounts table with values
- [ ] Savings: savings accounts, emergency fund months, ISA used/remaining
- [ ] Retirement: DC pensions, DB pensions, state pension, cascading what-if charts
- [ ] Estate: IHT calculation table (expandable per-owner), gifting schedules
- [ ] Priority Area: all actions ranked, goals first, running total vs disposable income
- [ ] Conclusion: aggregated essential + optional actions with module badges
- [ ] Toggle retirement action → cascading chart updates
- [ ] Toggle protection action → what-if bar chart updates
- [ ] Toggle investment action → cascading chart updates
- [ ] Toggle estate action → what-if updates
- [ ] Print/Save → full document renders correctly

**widow (Margaret Thompson):**
- [ ] Estate plan loads (single owner, transferred NRB/RNRB)
- [ ] Protection plan loads (if data exists)
- [ ] Missing plans handled gracefully (sections not shown)
- [ ] Print/Save works with partial plans

**entrepreneur (Alex Chen):**
- [ ] Single user (no spouse)
- [ ] Business interests visible in estate current situation
- [ ] No spouse column in personal info

**young_saver (John Morgan):**
- [ ] May not have estate plan (below age gate or threshold)
- [ ] Available plans shown, missing ones omitted
- [ ] Priority area adjusts to available actions only

**retired_couple (Robert & Patricia Williams):**
- [ ] Estate may show "not applicable" (below threshold)
- [ ] Retirement plan focuses on decumulation
- [ ] Handled gracefully in holistic view

**young_family (James & Emily Carter):**
- [ ] Mortgage-focused, workplace pensions
- [ ] All relevant sections display

### Design & Compliance

- [ ] Run `premium-ui-designer` agent for visual polish across all sections
- [x] No amber/orange colours anywhere (Rule 9)
- [x] All currency formatted via currencyMixin (Rule 6)
- [x] No acronyms in user-facing text (Rule 10)
- [x] No scores in UI (Rule 12)
- [x] British spelling in user-facing text
- [x] Design system compliance checked against `designStyle.md` (Rule 11)
- [x] No hardcoded tax values — all from plan data

### Final Verification

- [x] Run `./vendor/bin/pest` — all tests pass, no regressions
- [ ] Run `./vendor/bin/pint` — PSR-12 formatting clean (if any PHP changes)
- [x] `php artisan db:seed` — final seed

---

## Implementation Sequence Summary

| Order | Task | Description | Agents/Skills | Files | Status |
|-------|------|-------------|---------------|-------|--------|
| 1 | Task 1 | Delete old infrastructure | `Explore` | 11 deleted, 2 modified | **DONE** |
| 2 | Task 2 | Create helper components | `/feature-dev`, `premium-ui-designer` | 3 new | **DONE** |
| 3 | Task 3 | Create executive summary | `/feature-dev` | 1 new | **DONE** |
| 4 | Task 4 | Create priority area | `/feature-dev`, `tax-compliance-reviewer`, `premium-ui-designer` | 1 new | **DONE** |
| 5 | Task 5 | Create conclusion | `/feature-dev` | 1 new | **DONE** |
| 6 | Task 6 | Create orchestrator | `/feature-dev`, `security-reviewer`, `tax-compliance-reviewer` | 1 new | **DONE** |
| 7 | Task 7 | Rewrite view | `/feature-dev`, `security-reviewer` | 1 rewritten | **DONE** |
| 8 | Task 8 | Add print support | `/feature-dev`, `Explore` | 1 modified | **DONE** |
| 9 | Task 9 | E2E testing & polish | `premium-ui-designer` | — | **PENDING** (browser testing) |
