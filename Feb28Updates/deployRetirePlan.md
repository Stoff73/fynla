# Retirement Plan — Per-Pension Actions, Reactive Charts & Income Gap Fix

## Overview

Retirement Plan now has per-pension grouped actions with reactive line charts, a portfolio projection line graph, and correct income gap calculations that account for early retirement before State Pension Age. All projection timeframes use the user's years to retirement. Terminology updated from "Defined Contribution" to "Pension" throughout.

---

## What Changed

### Backend

**Per-pension recommendations** — Contribution-related actions (employer match, start contributions) are now generated per pension account with `scope: 'account'`, `account_id`, and `account_name`. Portfolio-level actions (contribution increase, tax relief, adjust retirement age) carry `scope: 'portfolio'`.

**Per-pension growth projections** — Each DC pension gets a line chart comparing current trajectory vs with-actions. Uses actual fund value, contribution amounts, platform fees, and years to retirement.

**Income gap fix for early retirement** — When target retirement age is before State Pension Age, the income gap at retirement now correctly excludes State Pension income. Two-phase gap reported:
- Gap at retirement (pensions only, no state pension)
- Gap after SPA (when state pension kicks in)

For peak_earners (David, age 49, retiring at 60, SPA 67):
- Income at retirement: £46,141/year (excludes £11,502 state pension)
- Gap at retirement: £28,859/year
- Income after SPA: £57,643/year
- Gap after SPA: £17,357/year

**What-if projection fix** — The projected DC value "with actions" now correctly adds future value of additional contributions on top of the already-projected retirement value, instead of re-projecting from scratch (which missed existing contributions).

**ContributionOptimizer fix** — No longer subtracts state pension from target income when user retires before SPA, so the required additional contribution is calculated against the full target.

**Action title mapping** — Actions now have proper short titles via `match()` instead of using the full recommendation message as the heading.

**Executive summary** — Explains the two-phase income gap for early retirees: shortfall at retirement without state pension, then reduced shortfall when state pension begins.

### Frontend

**Per-pension grouped layout** — Single pension: actions + chart inline. Multiple pensions: per-pension sections with charts, then portfolio actions, then portfolio line graph with what-if metrics.

**PensionGrowthProjectionChart** — New component (same pattern as AccountFeeProjectionChart). Reactive interpolation based on enabled action ratio. Badge shows `+£X at retirement`.

**RetirementGroupedActions** — New component replacing flat PlanActionsList. Handles single vs multiple pension layouts with reactive charts and portfolio projection.

**Income Gap at Retirement** — Current Situation metric now shows the gap at retirement age, with sub-text showing the reduced gap after SPA when retiring early.

**Terminology** — "Defined Contribution" replaced with "Pension" in all user-facing text.

---

## Files to Upload

### Backend — Modified (3)

```
app/Agents/RetirementAgent.php
app/Services/Plans/RetirementPlanService.php
app/Services/Retirement/ContributionOptimizer.php
```

### Frontend — New (2)

```
resources/js/components/Plans/Retirement/PensionGrowthProjectionChart.vue
resources/js/components/Plans/Retirement/RetirementGroupedActions.vue
```

### Frontend — Modified (3)

```
resources/js/components/Plans/Retirement/RetirementCurrentSituation.vue
resources/js/components/Plans/Retirement/RetirementPlanContent.vue
resources/js/components/Plans/Retirement/RetirementWhatIfControls.vue
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
app/Agents/RetirementAgent.php
app/Services/Plans/RetirementPlanService.php
app/Services/Retirement/ContributionOptimizer.php
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

1. Login as peak_earners → `/plans/retirement`
2. **Two pension sections**: Global Finance Corp Pension (with chart, 11 years) and David's SIPP (with action + chart)
3. **SIPP action**: "Start Pension Contributions" (critical priority) with +£35,251 badge
4. **Portfolio actions**: Increase Contributions (£4,231.92/month), Optimise Tax Relief (£9,000 saving), Consider Adjusting Retirement Age
5. **Portfolio line graph**: Shows total pension pot growth over 11 years with +£71,602 badge
6. **Income Gap at Retirement**: Shows £28,859/year (not £17,357) with sub-text "£17,357/year from age 67"
7. **Executive summary**: Explains 7-year gap between retirement at 60 and SPA at 67
8. **What-if metrics**: Current income £46,141, gap £28,859. With actions income £50,795, gap £24,205
9. **Toggle SIPP action off** → SIPP chart lines overlap, badge hides
10. **Toggle back on** → chart restores projection with actions
11. Test with young_family (single pension) → no portfolio split, actions + chart inline
12. Test with entrepreneur (single SIPP) → no portfolio split

---

## File Count Summary

| Category | New | Modified | Total |
|----------|-----|----------|-------|
| Backend PHP | 0 | 3 | 3 |
| Frontend Vue | 2 | 3 | 5 |
| Build output | 1 directory | - | 1 |
| **Total** | **2** | **6** | **8 + build** |
