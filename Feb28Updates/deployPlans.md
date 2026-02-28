# Plans Feature Deployment Guide

## Overview

Comprehensive, interactive planning system with 6 sections per plan: Executive Summary (personalised letter format), Current Situation, Toggleable Actions, What-If Scenarios with horizontal bar charts, Dynamic Conclusion, and PDF export. Plans are auto-generated from existing user data.

**Plan Types:** Investment & Savings, Protection, Retirement, and one plan per user Goal.

**Post-Deploy Changes (28 Feb):**
- Executive summaries rewritten as personalised letters ("Dear David, ...") across all 4 plan types — no metric cards
- Protection Plan rebuilt to use `ComprehensiveProtectionPlanService` (same engine as the protection module)
- Coverage Analysis cards show Need/Have/Gap with "How we calculated your need" breakdowns
- What-If recalculation fixed — toggling actions now rebuilds projections correctly via `applyActionFilter`
- All scores removed from Plans feature (project-wide rule added to CLAUDE.md and designStyle.md)
- Plan page header spacing fixed
- Horizontal bar charts added to What-If Comparison sections
- `ComprehensiveProtectionPlanService::buildCurrentCoverage()` fixed — Collection/array compatibility for policy counting
- Action toggle switches fixed — removed `v-preview-disabled` from `PlanActionCard.vue` (toggling is read-only planning)
- Vuex `toggleAction` mutation fixed — now updates `plan.actions[].enabled` for UI reactivity (not just `actionStates`)
- **Life cover calculation changed** — `CoverageGapAnalyzer::calculateHumanCapital()` now uses sustainable drawdown (annual income need / 0.047) instead of old 10x multiplier formula. This gives the capital needed so the family can draw income indefinitely at 4.7% withdrawal rate
- `ProtectionCurrentSituation.vue` — label updated to "Income replacement capital (at 4.7% drawdown)"
- Protection Plan conclusion rewritten — `ProtectionPlanService::buildProtectionConclusion()` now names specific actions, amounts, gap reductions and remaining shortfalls instead of generic text. Updates dynamically when actions are toggled on/off

---

## Files to Upload

### Backend - New Files (7)

```
app/Services/Plans/BasePlanService.php
app/Services/Plans/InvestmentPlanService.php
app/Services/Plans/ProtectionPlanService.php
app/Services/Plans/RetirementPlanService.php
app/Services/Plans/GoalPlanService.php
app/Services/Plans/WhatIfCalculator.php
app/Http/Controllers/Api/Plans/PlanController.php
```

### Backend - Modified Files (4)

```
routes/api.php
app/Services/Protection/ComprehensiveProtectionPlanService.php
app/Services/Protection/RecommendationEngine.php
app/Services/Protection/CoverageGapAnalyzer.php
```

### Frontend - New Files (23)

**Vuex Store:**
```
resources/js/store/modules/plans.js
```

**Shared Components (14):**
```
resources/js/components/Plans/Shared/PlanPageLayout.vue
resources/js/components/Plans/Shared/PlanSectionHeader.vue
resources/js/components/Plans/Shared/PlanLoadingState.vue
resources/js/components/Plans/Shared/PlanErrorState.vue
resources/js/components/Plans/Shared/PlanExecutiveSummary.vue
resources/js/components/Plans/Shared/PlanActionCard.vue
resources/js/components/Plans/Shared/PlanActionsList.vue
resources/js/components/Plans/Shared/PlanWhatIfMetricRow.vue
resources/js/components/Plans/Shared/PlanWhatIfComparison.vue
resources/js/components/Plans/Shared/PlanWhatIfChart.vue
resources/js/components/Plans/Shared/PlanConclusion.vue
resources/js/components/Plans/Shared/PlanMissingDataPrompt.vue
resources/js/components/Plans/Shared/PlanDashboardCard.vue
resources/js/components/Plans/Shared/planPrintMixin.js
```

**Investment Plan (3):**
```
resources/js/components/Plans/Investment/InvestmentPlanContent.vue
resources/js/components/Plans/Investment/InvestmentCurrentSituation.vue
resources/js/components/Plans/Investment/InvestmentWhatIfControls.vue
```

**Protection Plan (3):**
```
resources/js/components/Plans/Protection/ProtectionPlanContent.vue
resources/js/components/Plans/Protection/ProtectionCurrentSituation.vue
resources/js/components/Plans/Protection/ProtectionWhatIfControls.vue
```

**Retirement Plan (3):**
```
resources/js/components/Plans/Retirement/RetirementPlanContent.vue
resources/js/components/Plans/Retirement/RetirementCurrentSituation.vue
resources/js/components/Plans/Retirement/RetirementWhatIfControls.vue
```

**Goal Plan (3):**
```
resources/js/components/Plans/Goals/GoalPlanContent.vue
resources/js/components/Plans/Goals/GoalCurrentSituation.vue
resources/js/components/Plans/Goals/GoalWhatIfControls.vue
```

**View Wrappers (4):**
```
resources/js/views/Plans/InvestmentPlan.vue
resources/js/views/Plans/ProtectionPlan.vue
resources/js/views/Plans/RetirementPlan.vue
resources/js/views/Plans/GoalPlan.vue
```

### Frontend - Modified Files (3)

```
resources/js/router/index.js
resources/js/services/plansService.js
resources/js/store/index.js
```

### Frontend - Replaced Files (1)

```
resources/js/views/Plans/PlansDashboard.vue
```

### Documentation - Modified Files (2)

```
CLAUDE.md
designStyle.md
```

### Build Output

```
public/build/    (entire directory - built via ./deploy/fynla-org/build.sh)
```

---

## Upload Order

### Step 1: Build locally

```bash
./deploy/fynla-org/build.sh
```

### Step 2: Upload PHP files

Upload to `~/www/fynla.org/public_html/`:

```
app/Services/Plans/BasePlanService.php
app/Services/Plans/InvestmentPlanService.php
app/Services/Plans/ProtectionPlanService.php
app/Services/Plans/RetirementPlanService.php
app/Services/Plans/GoalPlanService.php
app/Services/Plans/WhatIfCalculator.php
app/Http/Controllers/Api/Plans/PlanController.php
app/Services/Protection/ComprehensiveProtectionPlanService.php
app/Services/Protection/RecommendationEngine.php
app/Services/Protection/CoverageGapAnalyzer.php
routes/api.php
```

**Note:** Create the `app/Services/Plans/` directory on the server if it doesn't exist. The `app/Http/Controllers/Api/Plans/` directory should already exist from the previous InvestmentSavingsPlanController.

### Step 3: Upload build output

Upload `public/build/` directory to:
```
~/www/fynla.org/public_html/public/build/
```

### Step 4: SSH and clear caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## New API Routes

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/api/plans/{type}` | Generate plan (investment/protection/retirement) |
| GET | `/api/plans/goal/{goalId}` | Generate goal plan |
| POST | `/api/plans/{type}/recalculate` | Recalculate with toggled actions |
| POST | `/api/plans/goal/{goalId}/recalculate` | Recalculate goal plan |
| DELETE | `/api/plans/{type}/clear-cache` | Clear plan cache |
| GET | `/api/plans/statuses` | Dashboard readiness per type |

Legacy route `/api/plans/investment-savings` is preserved for backward compatibility.

---

## Frontend Routes

| Path | Component | Description |
|------|-----------|-------------|
| `/plans` | PlansDashboard | Dashboard with 3 module cards + goal cards |
| `/plans/investment` | InvestmentPlan | Full investment & savings plan |
| `/plans/protection` | ProtectionPlan | Full protection plan |
| `/plans/retirement` | RetirementPlan | Full retirement plan |
| `/plans/goal/:goalId` | GoalPlan | Goal-specific plan |

`/plans/investment-savings` redirects to `/plans/investment`.

---

## Key Changes (28 Feb Session)

### Executive Summary — Personalised Letters

All 4 plan types now generate a personal letter narrative instead of impersonal text + metric cards:

- **Protection:** "Dear David, ... You are 52 years old, married to Sarah. Your dependants are James (child, age 14) and Sophie (child, age 11)... You have 2 existing policies covering life insurance, but our analysis has identified shortfalls in your critical illness and income protection coverage..."
- **Investment:** "Dear David, ... You currently hold 3 investment accounts valued at £185,000 and 2 savings accounts totalling £42,000... Your risk profile is Moderate..."
- **Retirement:** "Dear David, ... At 52 years old, you have 13 years until your target retirement age of 65. Your pension arrangements include Company Pension (valued at £210,000) and NHS Pension (projected at £18,500 per year)..."
- **Goal:** "Dear David, ... You are working towards saving £25,000, and so far you have saved £8,500 — that is 34% of your target..."

### Protection Plan — Full Rebuild

`ProtectionPlanService` now delegates to `ComprehensiveProtectionPlanService` (the same engine used by the protection module) instead of reinventing logic through the agent/gap analyzer chain.

**Coverage Analysis cards** show Need / Have / Gap with progress bars and status badges, plus expandable "How we calculated your need" breakdowns:
- **Life Insurance:** Income replacement + outstanding debts + education funding + final expenses = total need, less existing cover = shortfall
- **Critical Illness:** 3 × gross annual income = need, less existing cover = shortfall
- **Income Protection:** 70% of net monthly income = need, less existing cover = shortfall

**Existing Policies** section now correctly lists policies (fixed `is_array()` bug in `ComprehensiveProtectionPlanService::buildCurrentCoverage()` — Laravel Collections from agent were failing the `is_array()` check, replaced with `collect()`).

### What-If Recalculation Fix

`BasePlanService::applyActionFilter()` added and used by all 4 plan services. When actions are toggled, `enabled_action_ids` are passed through `generatePlan()` options so the what-if data is rebuilt with the correct action set, rather than filtering after the fact.

### Scores Removed

All score badges, score metrics, score formatting, and score-based narratives removed from the Plans feature. Documented as project-wide rule in CLAUDE.md (Rule 12) and designStyle.md.

### Horizontal Bar Charts

New `PlanWhatIfChart.vue` component using ApexCharts renders current vs projected values as side-by-side horizontal bars above the metric rows in the What-If Comparison section.

### Action Toggle Fixes

Two bugs prevented action toggles from working in preview mode:

1. **`v-preview-disabled` removed** from `PlanActionCard.vue` — toggling plan actions is a read-only planning operation, not a write that should be blocked in preview mode.
2. **Vuex `toggleAction` mutation fixed** in `plans.js` — was only updating `actionStates` tracking object but never updating `plan.actions[].enabled`, which the UI reads from. Now updates both for proper reactivity.

### Life Cover Calculation — Sustainable Drawdown

`CoverageGapAnalyzer::calculateHumanCapital()` changed from the old "human capital" rule-of-thumb (`net income × 10 × min(years to retirement, 10)`) to the correct sustainable drawdown approach:

**New formula:** `Annual Income Need / 0.047` (4.7% withdrawal rate)

This gives the lump sum capital the family needs to draw income indefinitely after the user's death. Life cover is for the **family's** income need after death; income protection replaces income while the user is **alive**.

For David Mitchell: £14,136/year net income difference / 0.047 = **£300,755** life cover capital (down from the old ~£1.4M).

Files changed:
- `app/Services/Protection/CoverageGapAnalyzer.php` — core formula
- `resources/js/components/Plans/Protection/ProtectionCurrentSituation.vue` — label: "Income replacement capital (at 4.7% drawdown)"
- `appMapping/currentState/Protection.md` — documentation
- `tests/Unit/Services/Protection/CoverageGapAnalyzerTest.php` — all 26 tests pass

### Protection Plan Conclusion — Action-Specific

`ProtectionPlanService::buildProtectionConclusion()` replaces the generic `BasePlanService::generateDynamicConclusion()` for protection plans. The new conclusion:

- **Names specific actions** and their coverage amounts ("adding £235,000 critical illness cover, and adding £5,500 per month income protection")
- **Quantifies impact** ("your total coverage gap would be fully closed" or "would reduce from £301,005 to £235,000")
- **Lists remaining gaps** for disabled actions ("leaving the following gaps: a critical illness shortfall of £235,000")
- **Shows estimated premium** ("The estimated additional monthly premium would be £588")
- **Updates dynamically** on every recalculation when actions are toggled on/off

---

## Verification After Deploy

1. Navigate to `/plans` — should show 3 module plan cards + goal plan cards
2. Click **Investment Plan** — executive summary should address user by name, list accounts, mention risk profile
3. Click **Protection Plan** — should show coverage gaps with Need/Have/Gap cards, "How we calculated" breakdowns, existing policies listed, personalised letter narrative
4. Click **Retirement Plan** — should name specific pensions with values, State Pension with NI years, income gap analysis
5. Toggle an action off — counter should update ("1 of 2 actions enabled"), toggle should visually switch off
6. Click **Recalculate** — what-if chart, metrics, and conclusion should all update with specific action details
7. Toggle all actions off → Recalculate — projected should match current exactly, conclusion should list remaining gaps
8. Toggle all back on → Recalculate — gaps should be fully closed, conclusion should name all actions and amounts
9. Click **Print/Save PDF** — should open print dialog with formatted plan
10. Verify life insurance "Income replacement capital" shows 4.7% drawdown formula (not years to retirement)
11. Test with multiple personas (peak_earners, young_family, young_saver)
12. Verify no scores appear anywhere in any plan
13. Verify horizontal bar charts appear in What-If sections

---

## File Count Summary

| Category | New | Modified | Total |
|----------|-----|----------|-------|
| Backend PHP | 7 | 4 | 11 |
| Frontend Vue/JS | 23 | 3 + 1 replaced | 27 |
| Documentation | 0 | 2 | 2 |
| Tests | 0 | 1 | 1 |
| Build output | 1 directory | - | 1 |
| **Total** | **30** | **11** | **41 + build** |
