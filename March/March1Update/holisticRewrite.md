# Holistic Plan: Tabs to Flowing Vertical Layout

## Problem
The holistic plan page uses a tab-based layout with 7 tabs while every other plan uses a flowing vertical layout. Tabs hide data behind clicks, making the plan feel empty and disconnected. The backend returns rich cross-module data but the frontend buries it in tabs.

## Solution
Remove tabs entirely and stack all sections vertically, matching the pattern used by Estate Plan, Retirement Plan, Investment Plan, etc.

## Section Order (Flowing Layout)

1. **ExecutiveSummary** - overview, health status, strengths, vulnerabilities, priorities
2. **FinancialSnapshot** [NEW] - net worth, assets, liabilities, monthly cash flow
3. **ModuleSummaries** - 6 module cards in a grid
4. **PrioritizedRecommendations** - all cross-module actions by timeline
5. **CashFlowAllocationChart** - surplus allocation + chart + table
6. **NetWorthProjectionChart** - baseline vs optimised
7. **RiskAssessment** - risk level, areas, mitigation
8. **Conflicts alert** - only shown if detected

## Files Modified

| File | Action |
|------|--------|
| `resources/js/views/HolisticPlan.vue` | Remove tabs, stack sections vertically |
| `resources/js/components/Holistic/FinancialSnapshot.vue` | NEW: Financial position overview |
| `resources/js/components/Holistic/CashFlowAllocationChart.vue` | Add PlanSectionHeader |
| `resources/js/components/Holistic/NetWorthProjectionChart.vue` | Add PlanSectionHeader |
| `resources/js/components/Holistic/RiskAssessment.vue` | Add PlanSectionHeader, remove h3 |
| `resources/js/components/Holistic/PrioritizedRecommendations.vue` | Add PlanSectionHeader |
| `resources/js/components/Holistic/ModuleSummaries.vue` | Add PlanSectionHeader, remove h3 |

## Files Removed (imports only)
- EstateSummarySection.vue - removed from HolisticPlan imports (estate data shown in ModuleSummaries)
- GoalsSummarySection.vue - removed from HolisticPlan imports (goals data shown in ModuleSummaries)

## Backend Fixes (discovered during testing)

The frontend rewrite revealed that all module data except estate was showing zeros. Root cause: `CoordinatingAgent.collectModuleAnalysis()` stored raw agent responses without normalising to the flat format `HolisticPlanner` expects. Each agent returns data in different nested structures (some use `$this->response()` wrapper, some don't).

| File | Fix |
|------|-----|
| `app/Agents/CoordinatingAgent.php` | Added 4 mapping methods (`mapProtectionAnalysis`, `mapSavingsAnalysis`, `mapInvestmentAnalysis`, `mapRetirementAnalysis`) + 4 default analysis methods. Updated `collectModuleAnalysis()` to normalise all agent responses. Added `generateRecommendations()` calls for savings, investment, retirement. |
| `app/Http/Controllers/Api/HolisticPlanningController.php` | Fixed recommendation_text mapping: `$rec['title'] ?? $rec['description']` added before fallback keys. Was falling through to "No description". |
| `app/Services/Coordination/HolisticPlanner.php` | Fixed goals key mismatch: `total_active` → `total_goals` (lines 667, 695). GoalsAgent returns `total_goals` but HolisticPlanner read `total_active`. |
| `resources/js/store/modules/holistic.js` | Fixed `actionPlan` getter to merge `action_plan_summary` into returned object. Summary counters were all showing 0. |

## Compliance Checks
- No scores in UI (Rule 12)
- No amber/orange colours (Rule 9) - yellow shortfall recommendations changed to blue
- No acronyms in user-facing text (Rule 10)
- PlanSectionHeader used for all section headers (design system compliance)
