# Bug Fix & Dynamic Tax Year Deploy Guide

**Date:** 2 April 2026 (Session 30)
**Branch:** `bugs`
**Status:** Ready for PR + merge + build + deploy

---

## What Changed

### Part 1: Bug Fixes (3 PDF bug reports)

| # | Bug | Fix | File(s) |
|---|-----|-----|---------|
| 1 | Retirement page "Other Assets" box cut off at ~1118px | Added `min-width: 0` on grid children + 1024px responsive breakpoint | `RetirementIncomeTab.vue` |
| 2 | Estate planning internal error (TypeError on production) | Changed `AssetLiquidityAnalyzer::classifyAsset()` type hint from `Asset` to `object`, added null coalescing for stdClass properties | `AssetLiquidityAnalyzer.php` |
| 3 | Projected Net Income not clickable / can't see breakdown | Added visible "View income breakdown" CTA link on the income planner card | `PensionList.vue` |
| 4 | VCT form too complicated + generic save error | Hid contributions section for VCT/EIS; show field-level validation errors instead of generic message | `StandardInvestmentFields.vue`, `AssetsStep.vue` |
| 5 | Journey selection not persisted from onboarding to dashboard | Stage now saved immediately on card selection, not just on "Start My Journey" click | `FocusAreaSelection.vue` |
| 6 | Pension retirement age defaults to 67 instead of user's stated age | DC pension form always re-fetches user profile to get latest retirement age | `DCPensionForm.vue` |
| 7 | Number input scroll interference | Already fixed (global wheel handler in `app.js`) | No change needed |
| 8 | Tenants in Common "Other" co-owner exits property wizard | Guard in `handleSubmit()` prevents accidental form submission on intermediate steps | `PropertyForm.vue` |
| 9 | Error messages persist across tab switches in onboarding | Already fixed (watch on `activeTab` clears error) | No change needed |

### Part 2: Dynamic Tax Year Overhaul

Eliminated ALL hardcoded "2025/26" tax year strings and hardcoded tax values (ISA £20,000, pension £60,000, CGT £3,000, etc.) across the entire codebase. Created centralised `getCurrentTaxYear()` utility. On April 6 the app will automatically display "2026/27" everywhere.

**37 frontend files + 2 backend files updated.**

---

## Files to Upload

### PHP Files (3 files)

```
app/Http/Controllers/Api/EstateController.php
app/Services/Estate/AssetLiquidityAnalyzer.php
app/Services/Onboarding/OnboardingService.php
```

### Frontend Build (must rebuild)

All Vue/JS changes are compiled into `public/build/`. Run:

```bash
./deploy/fynla-org/build.sh
```

Then upload the entire `public/build/` directory.

---

## Deploy Steps

### 1. Build locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload via SiteGround File Manager

Upload to `~/www/fynla.org/public_html/`:

| Local Path | Remote Path |
|-----------|-------------|
| `public/build/` | `public_html/public/build/` |
| `app/Http/Controllers/Api/EstateController.php` | `public_html/app/Http/Controllers/Api/EstateController.php` |
| `app/Services/Estate/AssetLiquidityAnalyzer.php` | `public_html/app/Services/Estate/AssetLiquidityAnalyzer.php` |
| `app/Services/Onboarding/OnboardingService.php` | `public_html/app/Services/Onboarding/OnboardingService.php` |

### 3. SSH cache clear

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Production Test Checklist

### Bug Fixes

- [ ] **Bug 1:** Navigate to `/net-worth/retirement` > click income card > resize browser to ~1100px width. "Other Assets" section should NOT be cut off.
- [ ] **Bug 2:** Navigate to `/estate` as Brett (user 491). Page should load without "internal error". IHT planning tab should display correctly.
- [ ] **Bug 3:** On `/net-worth/retirement`, the income planner card should show "View income breakdown including all pensions and assets" link. Clicking it opens the full income tab with pension + DB + state pension breakdown.
- [ ] **Bug 4:** In onboarding Step 5 > Investments, select VCT. Form should NOT show "Regular Contributions" section. If save fails, error message should show specific field errors (e.g., "Provider is required") not just "Failed to save investment account".
- [ ] **Bug 5:** Register a new account > select "Protecting and Growing" journey > skip to dashboard. Dashboard should show "Continue Journey" (not "Start a Planning Journey") and the correct stage.
- [ ] **Bug 6:** In onboarding, enter retirement age 60 in the Income step. Then in Step 5 > Retirement > Add Pension (DC), the "Planned Access Age" field should default to 60, not 67.
- [ ] **Bug 8:** In onboarding Step 5 > Properties, select Tenants in Common > Other co-owner > type a name > press Enter. The property wizard should advance to the next step, NOT exit the wizard.

### Dynamic Tax Year

- [ ] Dashboard tax allowance cards show the correct current tax year (not hardcoded "2025/26")
- [ ] `/net-worth/retirement` > Annual Allowance Tracker dropdown shows dynamic tax years
- [ ] `/net-worth/cash` > Add Account > select ISA > ISA subscription text shows current tax year
- [ ] `/calculators` > Income Tax calculator description shows current tax year
- [ ] `/learn/tax/pension-annual-allowance` page heading shows current tax year
- [ ] `/estate` > NRB/RNRB tracker subtitle shows current tax year
- [ ] Onboarding sidebar quickStats show current tax year (not "2025/26")

### Regression

- [ ] Existing preview personas load correctly (peak_earners, retired_couple)
- [ ] Cash page: Add Account modal opens as centred overlay
- [ ] Liabilities page: Mortgage cards show joint ownership info + clickable
- [ ] Dashboard: Donut chart segments clickable to module pages
- [ ] Investment detail: Direct URL redirects to investments list

---

## Notes

- The `taxConfig.js` frontend constants file still has hardcoded fallback VALUES (£20,000 ISA etc.) — these are intentional fallbacks when the API is unavailable. The file header documents this.
- The `retired_couple.json` persona data has "2025/26" in gift notes — this is static fixture data, not user-facing.
- A stop hook (`tax-hardcode-check.sh`) now runs after every Claude Code session to catch any future hardcoded tax values.
