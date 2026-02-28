# Investment Plan — Account-Level Actions & Reactive Charts

## Overview

Investment Plan now has per-account fee recommendations (not just portfolio-level), grouped action sections with reactive line charts, and a portfolio projection line graph replacing the old bar chart. All projection timeframes use the user's real data — retirement age and goal target dates.

---

## What Changed

### Backend

**Per-account fee recommendations** — Fee-related actions (reduce fees, review high-fee holdings) are now generated per investment account instead of at portfolio level. Each action carries `scope: 'account'`, `account_id`, and `account_name`.

**Per-account growth projections** — Each account gets a line chart comparing current fees vs reduced fees. The projection timeframe is determined by:
1. The latest linked goal's target date (e.g., "ISA Wealth Building" targeting 2035 = 10 years)
2. Falls back to years-to-retirement (e.g., age 49, retirement at 60 = 11 years)
3. Falls back to 10 years if neither is set

**Portfolio what-if uses years-to-retirement** — The portfolio-level projection now uses the user's `target_retirement_age` minus current age, not a hardcoded 5 years.

**Renamed data keys:**
- `projected_5yr_value` → `projected_value`
- `ten_year_difference` → `projection_difference`
- New: `projection_label` (e.g., "ISA Wealth Building" or "to retirement")

### Frontend

**Section reorder** — Account actions with charts appear first, portfolio actions below, portfolio line graph at the bottom.

**Bar chart replaced with line graph** — The `PlanWhatIfComparison` + `PlanWhatIfChart` (horizontal bar chart) is no longer used by the Investment Plan. Replaced with a portfolio-level line chart built directly into `InvestmentGroupedActions.vue`.

**Reactive account charts** — Toggling an account action on/off updates that account's chart in real time:
- All actions enabled → uses backend's reduced fee projection
- None enabled → chart flattens to match current fees line
- Partial → linear interpolation between current and reduced fees
- Badge updates to show the saving difference (or hides when no actions enabled)

---

## Files to Upload

### Backend — Modified (3)

```
app/Agents/InvestmentAgent.php
app/Services/Plans/BasePlanService.php
app/Services/Plans/InvestmentPlanService.php
```

### Frontend — New (2)

```
resources/js/components/Plans/Investment/AccountFeeProjectionChart.vue
resources/js/components/Plans/Investment/InvestmentGroupedActions.vue
```

### Frontend — Modified (2)

```
resources/js/components/Plans/Investment/InvestmentPlanContent.vue
resources/js/components/Plans/Investment/InvestmentWhatIfControls.vue
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
app/Agents/InvestmentAgent.php
app/Services/Plans/BasePlanService.php
app/Services/Plans/InvestmentPlanService.php
```

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

## Verification

1. Login as peak_earners → `/plans/investment`
2. **Section order**: Account actions appear first (with charts), then portfolio actions, then portfolio line graph with metrics
3. **Account chart timeframes**: David's ISA shows 10 years (linked to "ISA Wealth Building" goal), Joint GIA shows 11 years (to retirement)
4. **Portfolio chart**: Shows 11 years (to retirement at 60)
5. **Toggle account action off** → that account's chart line moves toward the current fees line, badge updates
6. **Toggle all off** → chart lines overlap (current = reduced), badge hidden
7. **Toggle back on** → chart restores reduced fee projection
8. **No bar chart** — the old horizontal bar chart in the What-If section is gone, replaced by the portfolio line graph
9. **Metric labels**: "At Retirement" (not "5-Year Projection")
10. Test with other personas (young_family, entrepreneur)

---

## File Count Summary

| Category | New | Modified | Total |
|----------|-----|----------|-------|
| Backend PHP | 0 | 3 | 3 |
| Frontend Vue | 2 | 2 | 4 |
| Build output | 1 directory | - | 1 |
| **Total** | **2** | **5** | **7 + build** |
