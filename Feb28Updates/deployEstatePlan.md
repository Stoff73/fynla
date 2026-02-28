# Estate Plan — Inheritance Tax Mitigation with What-If Scenarios

## Overview

New Estate Plan at `/plans/estate` following the same pattern as Investment, Retirement, and Protection plans. Uses the EstateAgent's 7-step IHT mitigation recommendation engine to show charitable bequest, life cover, gifting, PET, and CLT strategies to reduce potential estate liability. Includes reactive what-if toggling.

Gate conditions: plan only generates if the user is age 35+ AND has an IHT liability > 0. Otherwise shows an appropriate info message.

Also fixes multiple pre-existing bugs in `EstateAgent` (wrong method names, missing User model relationships, incorrect IHT field mappings, TypeError handling).

---

## What Changed

### Backend

**EstatePlanService** (new) — Extends `BasePlanService`. Generates a full estate plan with:
- Gate checks: age < 35 returns "not applicable", IHT liability = 0 returns "not applicable"
- Executive summary: estate values, IHT liability, effective rate, NRB/RNRB allowances, spouse exemption, life cover in trust, number of mitigation strategies
- Current situation: estate value breakdown, IHT calculation details, asset liquidity breakdown, life cover analysis, charitable giving status vs 10% threshold
- Actions from `EstateAgent::generateRecommendations()` — charitable bequest, life cover, annual gifting, PET, CLT strategies
- What-if: current vs projected IHT liability with `frontend_calc_params` savings map for reactive frontend toggling
- Data completeness: checks for will, estate assets, life insurance (IHT profile removed — not required for plan generation)

**EstateAgent fixes** — Six pre-existing bugs fixed:
- `aggregateEstateAssets()` → `gatherUserAssets()` + new `buildAssetSummary()` helper
- `calculateIHT()` → `calculate()` (correct method name)
- `getPersonalizedStrategies()` → `generatePersonalizedTrustStrategy()` with correct parameters
- `identifyOpportunities()` → `calculateOptimalGiftingStrategy()` with correct parameters
- `$ihtCalculation['gross_estate']` → `$ihtCalculation['effective_rate']` (fixed 17M% effective rate bug)
- `catch (\Exception)` → `catch (\Throwable)` to prevent TypeErrors from propagating

**User model** — Added 3 missing relationships: `ihtProfile()` (HasOne), `assets()` (HasMany), `gifts()` (HasMany) — required by EstateAgent eager loading.

**PlanController & WhatIfCalculator** — Registered `EstatePlanService` in constructor, match statements, and statuses endpoint.

**Routes** — Added `estate` to the `where` type constraint on plan generate/recalculate/clear-cache routes.

### Frontend

**EstatePlan.vue** (new) — Plan page view using `PlanPageLayout`. Handles `not_applicable` gate with blue info box.

**EstatePlanContent.vue** (new) — Orchestrator: `PlanMissingDataPrompt` → `PlanExecutiveSummary` → `EstateCurrentSituation` → `EstateGroupedActions` → `PlanConclusion`.

**EstateCurrentSituation.vue** (new) — Five sections with purple `PlanSectionHeader`:
- Estate Value (gross, net, liabilities)
- Inheritance Tax (liability, NRB, RNRB, spouse exemption, effective rate)
- Asset Breakdown (liquid, semi-liquid, illiquid)
- Life Cover (in trust, not in trust, policy count)
- Charitable Giving (status vs 10% threshold, potential saving)

**EstateGroupedActions.vue** (new) — Flat action list (all portfolio-scope). Reactive what-if: toggling actions updates projected IHT liability using `frontend_calc_params.savings_map`. Side-by-side current/projected comparison.

**EstateWhatIfControls.vue** (new) — Four metrics: IHT Liability (currency), Effective Tax Rate (percentage), Estate to Beneficiaries (currency), Total Mitigation Savings (currency, projected only).

**PlansDashboard.vue** — Added purple Estate Plan card with house icon after Retirement card.

**Router** — Added `/plans/estate` route with lazy import and breadcrumb.

---

## Files to Upload

### Backend — New (1)

```
app/Services/Plans/EstatePlanService.php
```

### Backend — Modified (4)

```
app/Agents/EstateAgent.php
app/Http/Controllers/Api/Plans/PlanController.php
app/Models/User.php
app/Services/Plans/WhatIfCalculator.php
routes/api.php
```

### Frontend — New (5)

```
resources/js/views/Plans/EstatePlan.vue
resources/js/components/Plans/Estate/EstatePlanContent.vue
resources/js/components/Plans/Estate/EstateCurrentSituation.vue
resources/js/components/Plans/Estate/EstateGroupedActions.vue
resources/js/components/Plans/Estate/EstateWhatIfControls.vue
```

### Frontend — Modified (2)

```
resources/js/views/Plans/PlansDashboard.vue
resources/js/router/index.js
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
app/Services/Plans/EstatePlanService.php
app/Agents/EstateAgent.php
app/Http/Controllers/Api/Plans/PlanController.php
app/Models/User.php
app/Services/Plans/WhatIfCalculator.php
routes/api.php
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

1. Login as peak_earners (David Mitchell, age 49) → `/plans`
2. Estate Plan card visible on dashboard (purple, house icon)
3. Click → `/plans/estate` with loading state
4. **Executive Summary**: Estate valued at £1,618,450, IHT liability £179,180, effective rate 18.9%, NRB £325,000 + RNRB £175,000, £500,000 life cover in trust, 3 mitigation strategies
5. **Current Situation**: Estate breakdown, IHT details, asset liquidity, life cover, charitable status (below 10% threshold)
6. **Actions**: Charitable Bequest Opportunity (£44,918 saving), Existing Life Cover Available, Will Wishes Require Trust Structures
7. **What-If**: Toggle actions → IHT liability updates (£179,180 → £134,262 with all enabled)
8. **Conclusion**: Dynamic based on enabled actions
9. **No incomplete data warning** — David has all required data at 100%
10. Test with widow (Margaret Thompson) → Full plan with IHT £255,940, 4 actions
11. Test with entrepreneur (Alex Chen) → Full plan with IHT £83,672, 4 actions
12. Test with young_saver (age 25) → "not applicable" (under 35)
13. Test with young_family (James Carter) → "not applicable" (no IHT liability)
14. Test with retired_couple → "not applicable" (no IHT liability)

---

## File Count Summary

| Category | New | Modified | Total |
|----------|-----|----------|-------|
| Backend PHP | 1 | 5 | 6 |
| Frontend Vue | 5 | 2 | 7 |
| Build output | 1 directory | - | 1 |
| **Total** | **6** | **7** | **13 + build** |
