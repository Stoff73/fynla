# Deploy: Print/Save PDF — All Plan Types

## Summary

Updated `planPrintMixin.js` to support structured print output for **all plan types**: Estate, Protection, Investment, and Retirement. Each plan type now renders its full on-screen content in the print/save PDF, including plan-specific executive summaries, personal information grids, current situation sections, action cards with cascading projection charts (Investment/Retirement), coverage bar charts (Protection), and what-if comparison metrics.

## Files Changed

| File | Change |
|------|--------|
| `resources/js/components/Plans/Shared/planPrintMixin.js` | Multi-plan print support with type-specific builders, cascading line charts, SVG rendering |

## Upload Steps

1. Build locally:
   ```bash
   ./deploy/fynla-org/build.sh
   ```

2. Upload via SiteGround File Manager:
   - `public/build/` directory → `~/www/fynla.org/public_html/public/build/`

3. SSH and clear caches:
   ```bash
   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
   cd ~/www/fynla.org/public_html
   php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
   ```

## What Changed

### Plan Type Detection
- Replaced `isEstatePlan()` with `detectPlanType(plan)` — returns `estate`, `protection`, `investment`, `retirement`, `structured`, or `generic`
- Three routing methods (`buildCurrentSituationByType`, `buildActionsByType`, `buildWhatIfByType`) dispatch to plan-specific builders

### Protection Plan Print
- `buildProtectionCurrentSituationHtml()` — Coverage Analysis cards (Life/Critical Illness/Income Protection) with need/have/gap, progress bars, "How we calculated" breakdowns, Existing Policies, Debt Exposure
- `buildSimpleActionsHtml()` — Simple action cards sorted by priority (matches PlanActionsList.vue)
- `buildProtectionWhatIfHtml()` — Bar chart comparing current vs projected coverage + metric rows (gaps, additional premium)
- `computeProtectionProjectedScenario()` — Replicates frontend projection from enabled actions

### Investment Plan Print
- `buildInvestmentCurrentSituationHtml()` — Investment Accounts, Savings Accounts, Key Indicators (Emergency Fund, ISA Used, ISA Remaining)
- `buildGroupedActionsHtml()` — Actions grouped by account with **cascading projection line charts** after each action (matches InvestmentGroupedActions.vue)
- `buildInvestmentWhatIfHtml()` — Metric rows: Total Wealth, Annual Fees, Emergency Fund, Additional Monthly Savings, At Retirement

### Retirement Plan Print
- `buildRetirementCurrentSituationHtml()` — DC Pensions, DB Pensions, State Pension, Key Metrics (Years to Retirement, Income Gap, Pension Value)
- `buildGroupedActionsHtml()` — Actions grouped by pension with **cascading projection line charts** after each action (matches RetirementGroupedActions.vue)
- `buildRetirementWhatIfHtml()` — Metric rows: Projected Annual Income, Income Gap, Total Pension Value, At Retirement, Additional Monthly Contribution

### Cascading Projection Charts (NEW)
- `projectSeries()` — Replicates year-by-year projection logic from CascadingActionChart.vue (compound growth with annual contributions)
- `buildLineChartHtml()` — Renders static SVG line chart with "Before" vs "After this action" series, grid lines, axis labels, and "+£X at retirement" badge
- `fmtCurrencyCompact()` — Compact currency formatting for chart Y-axis (£150k, £1.2M)
- Cascading accumulation: each action's "before" reflects all higher-priority enabled actions

### Estate Plan Print (unchanged from previous deploy)
- Structured executive summary, personal information grid, IHT calculation table, detailed actions, what-if comparison

### Shared Features (all plans)
- Structured executive summary with greeting, introduction, coverage/goals tables, actions summary
- Personal information grid (4 cards, 4th card varies by plan type)
- Running page header/footer on every page
- `@page { margin: 0 }` removes browser default date/URL text

## Testing

Test with **peak_earners** (David & Sarah Mitchell) preview persona:
- **Protection Plan** → Print/Save — coverage analysis, policies, bar chart, action cards
- **Investment Plan** → Print/Save — accounts, action cards with line charts, what-if metrics
- **Retirement Plan** → Print/Save — pensions, action cards with line charts, what-if metrics
- **Estate Plan** → Print/Save — verify unchanged (regression)
