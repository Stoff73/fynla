# Investment Decision Engine: Implementation Plan

> Gap analysis between `research-investment-engine.md` (v2.0) and the current codebase.
>
> **Date:** 2026-03-14 | **Based on:** 2 codebase audits (backend + frontend/config)

---

## Executive Summary

The Investment module has **strong analytical foundations** (44 calculation services, 58 Vue components, Monte Carlo simulations, fee analysis) but **lacks the orchestration pipeline** that turns analysis into structured recommendations. The current engine operates at approximately **30% capacity**.

**What exists:** Portfolio analysis, fee analysis, tax efficiency calculation, diversification scoring, 21 DB-driven action triggers, comprehensive frontend.

**What's missing:** The entire 10-phase recommendation pipeline — UserContextBuilder, DataReadinessService, LifeEventAssessmentService, GoalAssessmentService, SafetyCheckService, ContributionWaterfallService (the core 11-step allocation engine), PortfolioHealthCheckService, TransferRecommendationService (13 scans), SpouseOptimisationService, ConflictResolutionService, and OutputFormatterService.

**Additionally:** 6% growth rate hardcoded across 15+ services instead of using user's risk profile or TaxConfigService. 40+ threshold values missing from TaxConfigService.

### This Is a Different Scale to Other Modules

The other modules (Cash/Savings, Estate, Protection, Retirement) needed **targeted enhancements** to existing engines. The Investment module needs the **core recommendation engine built from scratch**. The existing `InvestmentActionDefinitionService` with 21 triggers provides secondary analysis — the primary engine (Phase 4 ContributionWaterfallService) that produces "where to put your money" recommendations does not exist.

---

## Architecture Decision

The research document describes a **sequential pipeline** of 10+ phases, each producing modifiers that feed downstream phases. This is fundamentally different from the trigger-based `InvestmentActionDefinitionService` pattern used by other modules. Both patterns are needed:

```
                    PRIMARY ENGINE (new — sequential pipeline)
                    =============================================
UserContextBuilder → DataReadiness → LifeEvents → Goals → Safety
    → ContributionWaterfall → Transfers → Spouse → Conflicts → Output

                    SECONDARY ENGINE (existing — trigger-based)
                    =============================================
InvestmentActionDefinitionService → 21 DB-driven triggers
    (portfolio health, fee alerts, tax efficiency, rebalancing)
```

**Why two engines, not one:**
1. The waterfall is a **sequential allocation algorithm** — surplus flows from step to step. This cannot be expressed as independent DB triggers.
2. The trigger-based system handles **portfolio analysis** alerts (fee drag, diversification, rebalancing) — these are independent checks, not sequential.
3. Both produce recommendations that are merged by ConflictResolutionService and deduplicated before output.

---

## Implementation Phases

### Phase 0: TaxConfigService Centralisation (Pre-requisite)
**Priority: CRITICAL**
**Files modified: 15+ | Files created: 0**

#### 0.1 Add Investment Constants to TaxConfigService Seeder

**File:** `database/seeders/TaxConfigurationSeeder.php`

Add new `investment` section:
```php
'investment' => [
    'asset_class_yields' => [
        'equity' => 0.02, 'stock' => 0.02,
        'bond' => 0.04, 'fixed_income' => 0.04,
        'reit' => 0.04, 'preferred_stock' => 0.05,
        'default' => 0.015,
        // NOTE: Must be extended to include return assumptions for ALL asset classes
        // used by HoldingsDataExtractor.php: uk_equity, international_equity,
        // emerging_markets, commodity, alternative, mixed, property.
        // Currently only income-producing yields (equity, bond, reit, preferred_stock) are defined.
    ],
    'fee_benchmarks' => [
        'low_cost_ocf' => 0.0015,        // 0.15%
        'high_ocf_threshold' => 0.0075,   // 0.75%
        'ocf_savings_threshold' => 0.005,  // 0.50%
        'turnover_assumption' => 0.10,     // 10% annual
    ],
    'portfolio_thresholds' => [
        'asset_concentration' => 0.60,     // > 60%
        'geographic_uk' => 0.70,           // > 70% UK
        'single_stock' => 0.15,            // > 15%
        'sector_concentration' => 0.30,    // > 30%
        'drift_medium' => 0.05,            // > 5%
        'drift_high' => 0.10,              // > 10%
        'rebalance_staleness_months' => 12,
    ],
    'waterfall' => [
        'premium_bonds_max' => 50000,
        'premium_bonds_min_age' => 16,
        'nsi_allocation_percent' => 0.10,  // 10% of remainder
        'nsi_minimum' => 25,
        'offshore_bond_minimum' => 10000,
        'onshore_bond_minimum' => 5000,
        'vct_eis_seis_max_portfolio_percent' => 0.10,
        'vct_eis_seis_min_allocation' => 1000,
        'vct_eis_seis_disposable_gate' => 0.10,
    ],
    'venture_capital' => [
        'seis' => ['relief' => 0.50, 'annual_limit' => 200000, 'min_hold_years' => 3, 'cgt_reinvestment' => 0.50],
        'eis' => ['relief' => 0.30, 'annual_limit' => 1000000, 'kic_limit' => 2000000, 'min_hold_years' => 3],
        'vct' => ['relief_current' => 0.30, 'relief_from_2026' => 0.20, 'annual_limit' => 200000, 'min_hold_years' => 5],
    ],
    'safety' => [
        'critical_debt_rate' => 0.15,      // > 15%
        'medium_debt_rate_low' => 0.05,    // 5-15%
        'medium_debt_rate_high' => 0.15,
        'mortgage_exception_rate' => 0.03, // < 3%
        'expected_return' => 0.05,         // 5% comparison
        'promotional_rate_warning_months' => 6,
    ],
    'transfers' => [
        'cash_excess_buffer_months' => 3,
        'interest_rate_switch_diff' => 0.005,
        'rate_expiry_warning_months' => 3,
        'cash_isa_transfer_minimum' => 1000,
        'tax_loss_harvesting_minimum' => 500,
        'isa_consolidation_trigger' => 2,
        'platform_count_trigger' => 3,
        'platform_fee_threshold' => 0.0045,
        'platform_fee_balance_threshold' => 50000,
        'nomination_staleness_years' => 2,
    ],
],
```

#### 0.2 Remove 6% Growth Rate Hardcoding (15+ Locations)

**Decision:** Replace ALL hardcoded growth rates with user's risk-based return from `RiskPreferenceService::getReturnParameters($riskLevel)['expected_return_typical']`. Where user has no risk profile, use `TaxConfigService::getAssumptions()['investment_growth']['balanced_portfolio']` (0.04) — never 0.06.

| File | Occurrences | Current | Replace With |
|------|------------|---------|-------------|
| `PortfolioStrategyService.php` | 1 | `0.06` | RiskPreferenceService |
| `Fees/OCFImpactCalculator.php` | 4 | `$expectedReturn = 0.06` | RiskPreferenceService |
| `Goals/ShortfallAnalyzer.php` | 4 | `$goal->expected_return ?? 0.06` | RiskPreferenceService or goal's custom rate |
| `Goals/GoalProgressAnalyzer.php` | 4 | `$goal->expected_return ?? 0.06` | RiskPreferenceService or goal's custom rate |
| `Tax/ISAAllowanceOptimizer.php` | 1 | `$amount * 0.06` | RiskPreferenceService |
| `Tax/BedAndISACalculator.php` | 1 | `$transferValue * 0.06` | RiskPreferenceService |
| `Tax/TaxOptimizationAnalyzer.php` | 2 | `$amount * 0.06` | RiskPreferenceService |
| `FeeAnalyzer.php` | 2 | `calculateCompoundSavings(..., 0.06)` | RiskPreferenceService |
| `ContributionOptimizer.php` | 2 (not 1) | `0.065` at lines 515, 518 | RiskPreferenceService |
| `Analytics/HoldingsDataExtractor.php` | 4 | `0.06` at lines 226, 230, 250, 253 — feeds Markowitz/efficient frontier analytics | TaxConfigService asset class returns (need extended seeder — see note) |
| `Analytics/PortfolioStatisticsCalculator.php` | 2 | `0.06` at lines 285, 302 | TaxConfigService asset class returns |
| `AssetLocation/TaxDragCalculator.php` | 2 (additional) | `0.06` at lines 65, 202 — separate from the yield issue in Phase 0.3 | RiskPreferenceService |

**Post-migration:** Run `grep -rn '0\.06[^0-9]' app/Services/Investment/` and create architecture test.

#### 0.3 Remove Hardcoded Dividend Yields

**File:** `app/Services/Investment/AssetLocation/TaxDragCalculator.php` (lines 269-274)

Replace inline yield estimates with TaxConfigService:
```php
// BEFORE:
$yields = ['equity' => 0.02, 'bond' => 0.04, 'reit' => 0.04, ...];

// AFTER:
$yields = $this->taxConfig->get('investment.asset_class_yields');
```

#### 0.4 Remove Income Fallback

**File:** `app/Services/Investment/AssetLocation/AssetLocationOptimizer.php` (line 93)

```php
// BEFORE:
$annualIncome = $user->gross_annual_income ?? 50000;

// AFTER — use ResolvesIncome trait, never a £50k default:
$annualIncome = $this->resolveGrossAnnualIncome($user);
// If null, flag as missing data in readiness gate (Phase 1)
```

---

### Phase 1: UserContextBuilder & Data Readiness Gate
**Priority: CRITICAL — prerequisite for all subsequent phases**
**Files created: 2**

#### 1.1 Create UserContextBuilder

**File:** `app/Services/Investment/Recommendation/UserContextBuilder.php`

Assembles all user data into a single context object consumed by every downstream phase.

UserContextBuilder should have TWO methods:
- `build(User $user)` — fetches everything from scratch (for standalone use)
- `buildFromExisting($investmentAnalysis, $savingsAnalysis, $accounts, $user)` — derives context from data already assembled by InvestmentPlanService (preferred path, avoids duplicate DB queries)

```php
class UserContextBuilder
{
    public function build(User $user): InvestmentContext
    // Returns structured object with:
    // - personal: age, gender, marital_status, employment_status, retirement_age, uk_resident
    // - financial: gross_income, net_income, monthly_expenditure, disposable_income, tax_band
    // - risk: risk_level, risk_tolerance, risk_capacity, investment_experience, esg_preference
    // - debt: debts (excl mortgage/student loan), high_interest, medium_interest
    // - emergency_fund: total, runway, target, shortfall
    // - allowances: isa_remaining, lisa_remaining, pension_aa_remaining, carry_forward, cgt_remaining, psa_remaining
    // - spouse: (if married) name, income, tax_band, isa_remaining, pension_aa, carry_forward
    // - portfolio: accounts, holdings, total_value, allocation

    public function buildFromExisting(
        array $investmentAnalysis,
        array $savingsAnalysis,
        Collection $accounts,
        User $user
    ): InvestmentContext
    // Derives context from data already assembled by InvestmentPlanService
    // Preferred path — avoids duplicate DB queries
}
```

All values fetched from existing models/services — no new data needed. Tax band fetched from DB (Income module source of truth). PSA from `TaxConfigService::getPersonalSavingsAllowance()`. All allowances from TaxConfigService.

**ISA allowance must account for ALL ISA types.** `isa_remaining` in the context must be calculated from both savings ISA subscriptions AND investment ISA subscriptions (the same £20,000 is shared). `InvestmentAgent::analyze()` already does this cross-check at lines 88-94. `UserContextBuilder.buildFromExisting()` must use this multi-source ISA remaining figure, not just investment accounts.

#### 1.2 Create DataReadinessService

**File:** `app/Services/Investment/Recommendation/DataReadinessService.php`

12 checks from research doc Section 3:

**Blocking (3):** date_of_birth, gross_annual_income, risk_profile
**Blocking (1):** monthly_expenditure
**Warning (4):** employment_status, protection_profile (if dependants), DC pensions, investment accounts
**Info (3):** spouse link, life events, savings accounts

Returns structured readiness response within normal envelope (same pattern as other modules).

---

### Phase 2: Life Event & Goal Assessment
**Priority: HIGH — feeds waterfall modifiers**
**Files created: 2**

#### 2.1 Create LifeEventAssessmentService

**File:** `app/Services/Investment/Recommendation/LifeEventAssessmentService.php`

Evaluates all active life events and produces modifiers:
- `blocked_wrappers[]` — wrappers blocked by life events (e.g., redundancy blocks offshore/onshore bonds)
- `prioritised_wrappers[]` — wrappers boosted by life events (e.g., new baby prioritises Junior ISA)
- `liquidity_priority` — boolean, forces liquid wrappers
- `affordability_override` — boolean, reduces contribution expectations
- Sub-action recommendations (e.g., "review emergency fund", "check CI claim eligibility")

Handles 20+ event types from research doc Section 4.2 + 3 derived events (approaching_retirement, windfall, pension_access_approaching).

#### 2.2 Create GoalAssessmentService

**File:** `app/Services/Investment/Recommendation/GoalAssessmentService.php`

Maps each active goal to suitable/blocked wrappers based on timeline:
- SHORT (<2yr): savings_account, cash_isa only
- MEDIUM (2-5yr): cash_isa, stocks_shares_isa
- LONG (5-10yr): stocks_shares_isa, pension
- VERY_LONG (>10yr): all wrappers

Auto-creates implicit emergency fund goal if shortfall exists.

---

### Phase 3: Safety Checks & Contribution Waterfall
**Priority: CRITICAL — the core recommendation engine**
**Files created: 2**

#### 3.1 Create SafetyCheckService

**File:** `app/Services/Investment/Recommendation/SafetyCheckService.php`

7 checks that can reduce the surplus available to the waterfall:
1. High-interest debt (>15%) → surplus = 0
2. Medium-interest debt (5-15%) → surplus reduced 50%
3. Emergency fund critical (<1 month) → surplus capped at 0
4. Emergency fund low (1-3 months) → surplus capped at 50%
5. Emergency fund building (3mo to target) → no cap, parallel
6. Protection gaps (dependants, no cover) → warning
7. Employer match available → always surface

All thresholds from `TaxConfigService::get('investment.safety.*')`.

**CRITICAL: SafetyCheckService must NOT produce standalone user-facing emergency fund recommendations.** Emergency fund action cards are owned by the Savings engine (`SavingsActionDefinitionService`). SafetyCheckService only:
1. Reduces the `remaining_surplus` figure passed to the waterfall
2. Adds context notes to waterfall recommendations (e.g., 'Surplus limited because emergency fund covers only 2 months')
3. Surfaces the employer match recommendation (always shown regardless of other safety checks)

It does NOT emit 'Build your emergency fund' or 'Emergency fund critical' as standalone action cards.

#### 3.2 Create ContributionWaterfallService

**File:** `app/Services/Investment/Recommendation/ContributionWaterfallService.php`

**This is the PRIMARY recommendation engine.** 11 sequential steps, each consuming surplus up to its wrapper limit, passing remainder to the next:

```
Step 1:  Lifetime ISA (25% bonus, £4k limit)
Step 2:  Stocks & Shares ISA (£20k limit, shared)
Step 3:  Pension current year (AA £60k, 3 affordability tiers)
Step 4a: Premium Bonds (£50k max, tax-free prizes)
Step 4b: NS&I Savings (10% of remainder, min £25)
Step 5:  Offshore Bond (min £10k, higher/additional rate only)
Step 6:  Onshore Bond (min £5k, top-slicing relief)
Step 7:  Pension Carry Forward (3-year window, lump sum only)
Step 8:  VCT/EIS/SEIS (max 10% portfolio, experienced investors only)
Step 9:  GIA catch-all (no limits)
```

Each step:
1. Checks skip conditions (age, allowance, life event blocks)
2. Calculates allocation amount (min of: remaining surplus, wrapper limit)
3. Generates recommendation with headline, explanation, personal_context
4. Reduces remaining surplus
5. Passes remainder to next step

All limits and thresholds from TaxConfigService. Skip conditions use modifiers from Phases 2a/2b. Personal context uses actual user numbers from UserContextBuilder.

---

### Phase 4: Transfer Scans & Spouse Optimisation
**Priority: MEDIUM — optimisation of existing holdings**
**Files created: 2**

#### 4.1 Create TransferRecommendationService

**File:** `app/Services/Investment/Recommendation/TransferRecommendationService.php`

13 independent scans from research doc Section 6:
1. Excess cash (above emergency target + buffer)
2. Bed & ISA (GIA → ISA, delegate to existing `BedAndISACalculator`)
3. Tax loss harvesting (delegate to existing `CGTHarvestingCalculator`)
4. PSA breach
5. Dividend allowance breach
6. Interest rate review
7. Goal-linked wrapper mismatch
8. Cash ISA → S&S ISA transfer
9. Pension consolidation
10. ISA consolidation
11. Platform consolidation
12. Small balance alert
13. Nomination staleness

Existing calculators (`BedAndISACalculator`, `CGTHarvestingCalculator`) are wired in as delegates — no duplication.

#### 4.2 Create SpouseOptimisationService

**File:** `app/Services/Investment/Recommendation/SpouseOptimisationService.php`

7 strategies from research doc Section 7:
1. CGT exemption sharing
2. ISA coordination
3. PSA optimisation
4. Pension coordination (higher-rate prioritisation)
5. Non-earning spouse pension
6. Marriage Allowance
7. IHT planning (combined estate > NRB+RNRB)

Gate: married/civil_partnership AND spouse linked. All thresholds from TaxConfigService.

---

### Phase 5: Conflict Resolution & Output
**Priority: MEDIUM**
**Files created: 2**

#### 5.1 Create ConflictResolutionService

**File:** `app/Services/Investment/Recommendation/ConflictResolutionService.php`

Merges recommendations from waterfall + triggers + transfers + spouse:
1. Surplus income priority (12-step ordering)
2. ISA allowance competition (LISA first, then S&S ISA)
3. Pension allowance competition (highest tax relief first)
4. Goal competition (priority × urgency)
5. Life event overrides (blocks trump triggers)
6. Deduplication (key = headline:wrapper)

#### 5.2 Create RecommendationOutputFormatter

**File:** `app/Services/Investment/Recommendation/RecommendationOutputFormatter.php`

Structured API response with sections: readiness, safety, waterfall, portfolio_health, transfers, spouse, compliance. Each recommendation includes: uuid, headline, explanation, personal_context, amount, priority, status, decision_path[], notes[].

#### 5.3 Launch Gate — Disable Overlapping Triggers

**LAUNCH GATE (mandatory before pipeline goes live):**

Disable these 6 `InvestmentActionDefinition` triggers in the seeder, as their functionality is now handled by the new pipeline services:

Surplus waterfall (replaced by ContributionWaterfallService):
- `surplus_exists_and_isa_remaining` → `is_enabled = false`
- `surplus_exceeds_isa` → `is_enabled = false`
- `surplus_exceeds_pension` → `is_enabled = false`

Transfer scans (replaced by TransferRecommendationService):
- `has_harvesting_opportunities` → `is_enabled = false`
- `has_isa_remaining_and_gia` → `is_enabled = false`
- `has_gia_no_isa` → `is_enabled = false`

These must be disabled in the same seeder run that deploys the new pipeline. Not doing so produces duplicate recommendations.

---

### Phase 6: Pipeline Orchestration
**Priority: HIGH — connects everything**
**Files modified: 2**

#### 6.1 Integrate Pipeline into InvestmentPlanService

**File:** `app/Services/Plans/InvestmentPlanService.php`

**DO NOT modify `InvestmentAgent::analyze()`.** It must retain its current response shape — `InvestmentPlanService`, `PlanController`, and `InvestmentController::analyze()` all depend on it.

The new pipeline integrates into `InvestmentPlanService::getRecommendations()`, replacing the current `actionDefinitionService->evaluateAgentActions()` call:

```php
public function getRecommendations(int $userId, ?array $preComputedData = null): array
{
    // Existing data assembly (already done by InvestmentPlanService):
    $investmentAnalysis = $preComputedData['investment'] ?? $this->investmentAgent->analyze($userId);
    $savingsAnalysis = $preComputedData['savings'] ?? $this->savingsAgent->analyze($userId);
    $accounts = ...; // already assembled

    // NEW: Build context from already-assembled data (no re-query)
    $context = $this->contextBuilder->buildFromExisting(
        $investmentAnalysis, $savingsAnalysis, $accounts, $user
    );

    // NEW: Run pipeline phases
    $readiness = $this->readinessService->assess($context);
    if (!$readiness['can_proceed']) return $readiness;

    $lifeEventModifiers = $this->lifeEventService->assess($context);
    $goalModifiers = $this->goalService->assess($context);
    $safetyResult = $this->safetyService->check($context);
    $waterfallRecs = $this->waterfallService->allocate(...);
    $transferRecs = $this->transferService->scan($context);
    $spouseRecs = $this->spouseService->optimise($context);

    // EXISTING: DB-driven trigger recommendations (secondary)
    // Only triggers NOT replaced by the pipeline still fire
    $triggerRecs = $this->actionDefinitionService->evaluateAgentActions(...);

    $merged = $this->conflictResolver->resolve(...);
    return $this->formatter->format($readiness, $safetyResult, $merged);
}
```

`InvestmentAgent::analyze()` and `InvestmentAgent::generateRecommendations()` remain UNCHANGED.

#### 6.2 Update InvestmentController

Update `recommendations()` endpoint to return the structured output from the full pipeline.

#### 6.3 Frontend Readiness Gate

The Investment frontend has zero handling for `can_proceed = false`. Add a task:
- When `can_proceed = false` in the recommendations response, display a readiness gate component listing what data is missing with links to the relevant input forms
- Reuse the pattern from `PlanDashboardCard`'s 'Data readiness' section if it exists
- The existing 'No recommendations available' empty state must be replaced with the actionable readiness display

---

## Dependency Graph

```
Phase 0 ──────────────────────────────────────────────┐
(TaxConfigService + 6% growth rate removal)            |
    |                                                   |
    v                                                   |
Phase 1 ──────────────────────────────────────────────┤
(UserContextBuilder + DataReadinessService)             |
    |                                                   |
    v                                                   |
Phase 2 ──────────────────────────────────────────────┤
(LifeEventAssessment + GoalAssessment)                 |
    |                                                   |
    v                                                   |
Phase 3 ──────────────────────────────────────────────┤
(SafetyChecks + ContributionWaterfall) ← CORE ENGINE  |
    |                                                   |
    v                                                   |
Phase 4 ──────────────────────────────────────────────┤
(TransferScans + SpouseOptimisation)                    |
    |                                                   |
    v                                                   |
Phase 5 ──────────────────────────────────────────────┤
(ConflictResolution + OutputFormatter)                  |
    |                                                   |
    v                                                   |
Phase 6 ──────────────────────────────────────────────┘
(InvestmentPlanService integration + controller + frontend readiness gate)
```

All phases are sequential — each depends on the previous.

---

## Files Created (New — 10 services)

| File | Purpose |
|------|---------|
| `app/Services/Investment/Recommendation/UserContextBuilder.php` | Assembles all user data into structured context |
| `app/Services/Investment/Recommendation/DataReadinessService.php` | 12-check readiness gate |
| `app/Services/Investment/Recommendation/LifeEventAssessmentService.php` | Life event modifiers (blocked/prioritised wrappers) |
| `app/Services/Investment/Recommendation/GoalAssessmentService.php` | Goal-to-wrapper mapping by timeline |
| `app/Services/Investment/Recommendation/SafetyCheckService.php` | 7 safety checks, surplus reduction |
| `app/Services/Investment/Recommendation/ContributionWaterfallService.php` | 11-step allocation engine (CORE) |
| `app/Services/Investment/Recommendation/TransferRecommendationService.php` | 13 transfer scans |
| `app/Services/Investment/Recommendation/SpouseOptimisationService.php` | 7 spouse strategies |
| `app/Services/Investment/Recommendation/ConflictResolutionService.php` | Merge, deduplicate, prioritise |
| `app/Services/Investment/Recommendation/RecommendationOutputFormatter.php` | Structured API response |

## Files Modified (Existing)

| File | Change |
|------|--------|
| `database/seeders/TaxConfigurationSeeder.php` | Add `investment.*` config (waterfall limits, safety thresholds, venture capital, portfolio thresholds, fee benchmarks, asset class yields, transfer scan thresholds). Disable 6 overlapping `InvestmentActionDefinition` triggers (Phase 5.3 launch gate) |
| `app/Services/Plans/InvestmentPlanService.php` | Integrate full recommendation pipeline into `getRecommendations()` (Phase 6.1) |
| `app/Http/Controllers/Api/InvestmentController.php` | Return structured pipeline output |
| `app/Services/Investment/PortfolioStrategyService.php` | Remove hardcoded 0.06, use RiskPreferenceService |
| `app/Services/Investment/Fees/OCFImpactCalculator.php` | Remove hardcoded 0.06 (4 locations) |
| `app/Services/Investment/Goals/ShortfallAnalyzer.php` | Remove hardcoded 0.06 (4 locations) |
| `app/Services/Investment/Goals/GoalProgressAnalyzer.php` | Remove hardcoded 0.06 (4 locations) |
| `app/Services/Investment/Tax/ISAAllowanceOptimizer.php` | Remove hardcoded 0.06 |
| `app/Services/Investment/Tax/BedAndISACalculator.php` | Remove hardcoded 0.06 |
| `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php` | Remove hardcoded 0.06 (2 locations) |
| `app/Services/Investment/FeeAnalyzer.php` | Remove hardcoded 0.06, 0.15% OCF, use TaxConfigService |
| `app/Services/Investment/ContributionOptimizer.php` | Remove hardcoded 0.065 (2 locations) |
| `app/Services/Investment/Analytics/HoldingsDataExtractor.php` | Remove hardcoded 0.06 (4 locations), use TaxConfigService asset class returns |
| `app/Services/Investment/Analytics/PortfolioStatisticsCalculator.php` | Remove hardcoded 0.06 (2 locations), use TaxConfigService asset class returns |
| `app/Services/Investment/AssetLocation/TaxDragCalculator.php` | Remove hardcoded yields AND hardcoded 0.06 growth rates (4 locations total), use TaxConfigService |
| `app/Services/Investment/AssetLocation/AssetLocationOptimizer.php` | Remove £50k income fallback, use ResolvesIncome |

## What This Plan Does NOT Include

1. **ComplianceSuitabilityService** — Consumer Duty checks deferred. Requires regulatory review.
2. **PortfolioHealthCheckService as separate service** — Existing analytics services (DiversificationAnalyzer, AssetAllocationOptimizer, FeeAnalyzer) already calculate these metrics. The trigger-based InvestmentActionDefinitionService already surfaces them as recommendations. No separate health service needed — would duplicate.
3. **Model field additions** — RiskProfile fields (capacity_for_loss, comfortable_with_illiquidity, etc.) and User fields (student_loan_plan, uk_resident) are noted in the research gaps document. These are separate data model work, not engine work.
4. **Frontend changes** — The existing RecommendationsSection.vue can display the new structured recommendations. Frontend work is limited to handling the new response shape.

## Testing Strategy

| Phase | Tests |
|-------|-------|
| Phase 0 | Architecture test: grep for 0.06 in Investment services. Run full test suite. |
| Phase 1 | Unit tests for UserContextBuilder assembly. Unit tests for 12 readiness checks. |
| Phase 2 | Unit tests for each of 20+ life event types. Unit tests for 6 goal types × 4 timelines. |
| Phase 3 | Unit tests for each safety check (surplus reduction). Integration tests for 11-step waterfall with various user profiles. |
| Phase 4 | Unit tests for each of 13 transfer scans. Unit tests for 7 spouse strategies. |
| Phase 5 | Unit tests for conflict resolution (priority ordering, deduplication). |
| Phase 6 | Integration test for full pipeline end-to-end. |
