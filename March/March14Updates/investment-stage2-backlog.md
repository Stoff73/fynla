# Investment Recommendations: Stage 2 Backlog

**Created:** 2026-03-13
**Context:** Remaining duplication/redundancy issues not addressed in stage 1 (constant consolidation). These require architectural decisions or broader refactoring.

---

## 1. Two `calculateDiversificationScore()` Algorithms

**Files:**
- `app/Services/Investment/PortfolioAnalyzer.php:374` — penalty-based on percentage concentration
- `app/Services/Investment/DiversificationAnalyzer.php:257` — HHI-based with concentration metrics

**Issue:** Same portfolio produces different scores depending on which path runs. The `InvestmentAgent` uses `PortfolioAnalyzer` version. The `DiversificationAnalyzer` version is only used within its own `analyze()` output.

**Decision needed:** Which algorithm is authoritative? Should `PortfolioAnalyzer` delegate to `DiversificationAnalyzer`, or vice versa?

---

## 2. Three `calculateTaxEfficiencyScore()` Methods

**Files:**
- `app/Services/Investment/TaxEfficiencyCalculator.php:135` — % of portfolio in tax-sheltered accounts (0-100 int)
- `app/Services/Investment/ContributionOptimizer.php:396` — weights ISA/pension/GIA contributions (private)
- `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php:512` — deduction-based scoring with grade/interpretation (private)

**Issue:** All measure "tax efficiency" but produce incomparable results. The name collision makes the codebase confusing.

**Decision needed:** Are these genuinely different metrics that should be renamed (e.g. `calculateTaxShelterRatio`, `calculateContributionEfficiency`, `calculateTaxOptimizationGrade`)? Or should they converge into one?

---

## 3. Tax Loss Harvesting via Two Parallel API Paths

**Files:**
- `app/Services/Investment/TaxEfficiencyCalculator.php:94` — `identifyHarvestingOpportunities()`, called by `InvestmentAgent`
- `app/Services/Investment/Tax/CGTHarvestingCalculator.php` — separate endpoint for `TaxOptimization.vue`

**Issue:** Both surface the same harvesting opportunities through different code paths and API endpoints. Results could diverge.

**Decision needed:** Should `TaxEfficiencyCalculator` delegate to `CGTHarvestingCalculator`, or should one be removed?

---

## 4. ISA Usage Calculation Incomplete in InvestmentAgent

**Files:**
- `app/Agents/InvestmentAgent.php:81` — only counts investment ISAs
- `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php:187` — counts both investment + savings ISAs

**Issue:** The agent's `isa_remaining` value is inaccurate for users with cash ISAs. Recommendations based on ISA allowance may overstate available allowance.

**Fix:** `InvestmentAgent` should include savings ISA subscriptions in its calculation, matching `TaxOptimizationAnalyzer`.

---

## 5. Shell Wrapper Components (Dead Layers)

**Files:**
- `resources/js/components/Investment/Recommendations.vue` — wraps `InvestmentRecommendationsTracker.vue`
- `resources/js/components/Investment/WhatIfScenarios.vue` — wraps `WhatIfScenariosBuilder.vue`

**Issue:** Both shells only check for accounts then render the child. The children duplicate loading/error states. The shells add no value.

**Fix:** Remove the shells, mount the child components directly from the parent view/tab.

---

## 6. `formatNumber()` Triplicated in Tax Vue Components

**Files:**
- `resources/js/components/Investment/TaxOptimization.vue:274`
- `resources/js/components/Investment/TaxOptimizationOverview.vue:238`
- `resources/js/components/Investment/TaxOptimizationRecommendations.vue:228`

**Issue:** Identical method copy-pasted. Violates project rule to always use `currencyMixin`.

**Fix:** Remove local `formatNumber()` from all three, use `currencyMixin` methods instead.

---

## 7. `getCurrentTaxYear()` Reimplemented in Component

**Files:**
- `resources/js/components/Investment/TaxOptimization.vue:258`

**Issue:** Reinvents date calculation already available in `resources/js/utils/dateFormatter.js` (`getTaxYearStart()`, `getTaxYearEnd()`).

**Fix:** Import from `dateFormatter.js`.

---

## 8. `InvestmentAgent::generateRecommendations()` Deliberately Crippled

**Files:**
- `app/Agents/InvestmentAgent.php:141` — calls `evaluateAgentActions()` with empty arrays and `userId = 0`
- `app/Services/Plans/InvestmentPlanService.php:70` — calls same method with full data

**Issue:** Two tiers of recommendations from the same engine depending on code path. The agent path fires fewer triggers.

**Decision needed:** Should the agent produce the full recommendation set? Or is the reduced set intentional for performance/UX reasons?

---

## 9. Allocation Deviation Calculated Three Times

**Files:**
- `app/Services/Investment/AssetAllocationOptimizer.php:115` — `calculateDeviation()`, threshold >15% total
- `app/Services/Investment/Rebalancing/DriftAnalyzer.php:28` — `analyzeDrift()`, HHI-style drift score
- `app/Services/Investment/ModelPortfolio/ModelPortfolioBuilder.php:68` — `compareWithModel()`, threshold >10% per class

**Issue:** Three independent implementations of "how far is the portfolio from target". After stage 1 they share the same target constants, but still run different algorithms with different thresholds and outputs.

**Decision needed:** Should one service be authoritative and the others delegate? Or are they serving genuinely different purposes (quick check vs detailed analysis vs model comparison)?

---

## 10. `generateRebalancingTrades()` Duplicated

**Files:**
- `app/Services/Investment/AssetAllocationOptimizer.php:160` — filters to items >5% of portfolio
- `app/Services/Investment/Rebalancing/DriftAnalyzer.php:156` — calculates priority levels

**Issue:** Both produce buy/sell instructions to reach target allocation with different filtering and output formats.

**Decision needed:** Should one be removed? Or consolidated into a single rebalancing trade generator?
