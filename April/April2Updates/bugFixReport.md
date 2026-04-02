# Bug Fix Report — Session 30

**Date:** 2 April 2026
**Branch:** `bugs`
**Reporter:** Brett Isenberg (user 491) via 3 PDF bug reports
**Tester:** Claude (Playwright browser testing on localhost:8000)

---

## Bug Reports Audited

| PDF | Bugs Reported | Bugs Fixed | Already Fixed | UX Suggestions |
|-----|--------------|------------|---------------|----------------|
| BugReportApril1.pdf | 7 | 5 | 2 | 0 |
| bugsApril2One.pdf | 6 | 1 | 1 | 4 |
| bugsApril2Two.pdf | 8 | 0 | 8 | 0 |

**Total: 21 items reported, 6 new fixes, 10 already fixed, 4 UX suggestions (noted, not implemented), 1 additional fix (dynamic tax year overhaul)**

---

## Bugs Fixed This Session

### Bug 1: Retirement page "Other Assets" box cut off at narrow viewport

**Reported in:** BugReportApril1.pdf (17:42:32)
**Page:** `/net-worth/retirement` (income tab)
**Viewport:** 2560x1080 screen, 1118x810 viewport
**Problem:** The "Other Assets" section on the right side of the income sources layout gets cut off halfway across when the browser window is narrowed. The two-column CSS Grid (`1fr 1fr`) doesn't constrain child content, causing the right column to overflow past the viewport.
**Root cause:** CSS Grid children have a default `min-width: auto` which prevents them from shrinking below their content size. Combined with the ~350px sidebar navigation eating viewport width, the content area is only ~770px but the grid children try to be wider.
**Fix:**
- Added `min-width: 0` on `.sources-assets-row > *` to allow grid children to shrink
- Added 1024px responsive breakpoint to collapse `.sources-assets-row` and `.summary-grid` to single column
**File:** `resources/js/components/Retirement/RetirementIncomeTab.vue`
**Verified:** Playwright — resized viewport to 1118x810, both columns now equal width (371px each), neither extends past viewport.

---

### Bug 2: Estate planning section showing internal error

**Reported in:** BugReportApril1.pdf (17:42:53)
**Page:** `/estate` (IHT Planning tab)
**Problem:** Estate planning shows "internal error" for users with investment accounts.
**Root cause (from production logs):**
```
TypeError: AssetLiquidityAnalyzer::classifyAsset(): Argument #1 ($asset) must be of type
App\Models\Estate\Asset, stdClass given
```
The `EstateAssetAggregatorService::gatherUserAssets()` returns `stdClass` objects (via `(object) [...]` casting) for investment accounts, properties, savings, etc. But `AssetLiquidityAnalyzer::classifyAsset()` had a strict `Asset` type hint.
**Fix:**
- Changed `classifyAsset(Asset $asset)` to `classifyAsset(object $asset)`
- Changed `isProbablyMainResidence(Asset $asset)` to `isProbablyMainResidence(object $asset)`
- Added null coalescing (`??`) on all property accesses: `$asset->id ?? null`, `$asset->is_main_residence ?? false`, `$asset->asset_name ?? 'Unknown Asset'`, `$asset->asset_type ?? 'other'`
- Removed unused `use App\Models\Estate\Asset` import
**File:** `app/Services/Estate/AssetLiquidityAnalyzer.php`
**Verified:** Tinker — `analyzeAssetLiquidity()` returns 4 liquid + 11 semi-liquid + 2 illiquid assets with zero errors/warnings for peak_earners persona.

---

### Bug 3: Projected Net Income not clickable — can't see income breakdown

**Reported in:** BugReportApril1.pdf (17:43:38)
**Page:** `/net-worth/retirement`
**Problem:** User cannot click the "Projected Net Income" value to see what makes it up, including final salary (Defined Benefit) pension amounts.
**Investigation:** The income planner card WAS already clickable (`@click="setActiveTab('income')"`) with `cursor: pointer` and hover effects, but there was no visual indication that clicking would show a breakdown.
**Fix:** Added a visible CTA link at the bottom of the income planner card: "View income breakdown including all pensions and assets" with a raspberry-coloured arrow icon.
**Files:** `resources/js/components/NetWorth/PensionList.vue`
**Verified:** Playwright — CTA text visible, clicking the card opens RetirementIncomeTab with "Back to Pensions", "Income Sources" section showing DB pensions + state pension + DC withdrawals.

---

### Bug 4: VCT input section too complicated + general error preventing save

**Reported in:** BugReportApril1.pdf (17:44:26)
**Page:** `/net-worth/retirement` (onboarding investments tab)
**Problem:** VCT (Venture Capital Trust) form has too many fields and shows a generic "Failed to save investment account" error when validation fails, without telling the user which fields are missing.
**Root cause:** The `StandardInvestmentFields` component shows a "Regular Contributions" section for VCT, which is irrelevant (VCTs are bought as shares, not contributed to regularly). The error handler in `AssetsStep.vue` caught 422 validation errors but displayed a generic message instead of field-level errors.
**Fix:**
1. Added `isTaxReliefType` computed property to hide contributions section for VCT/EIS account types
2. Updated error handler to extract and display field-level validation errors from the API response
**Files:**
- `resources/js/components/Investment/StandardInvestmentFields.vue` — hide contributions for VCT/EIS
- `resources/js/components/Onboarding/steps/AssetsStep.vue` — field-level error messages

---

### Bug 5: Journey selection not persisted from onboarding to dashboard

**Reported in:** BugReportApril1.pdf (19:44:53)
**Page:** `/dashboard` (after onboarding)
**Problem:** User selected "Protecting and Growing" journey during onboarding, then skipped/timed out to dashboard. Dashboard showed "Starting Out" journey instead of "Protecting and Growing". Also: buttons showed "Start a Planning Journey" instead of "Continue Journey".
**Root cause:** The stage was only persisted to the backend when the user clicked "Start My Journey" (`startJourney()` → `$emit('stage-selected')`). Simply selecting a stage card set `selectedStage` locally but didn't save it. If the user skipped to dashboard before clicking "Start My Journey", the stage was lost.
**Fix:** Added `this.$store.dispatch('lifeStage/setStage', stageId)` inside `selectStage()` so the stage is persisted immediately when the user selects a card.
**File:** `resources/js/components/Onboarding/FocusAreaSelection.vue`
**Note:** The `EmptyDashboard` component already correctly shows "Continue Journey" vs "Start a Planning Journey" based on whether `currentStage` is set. The fix ensures the stage is always set.

---

### Bug 6: Pension retirement age defaults to 67 instead of user's stated age

**Reported in:** bugsApril2One.pdf (09:08:00)
**Page:** Onboarding Step 5 > Retirement > Add Pension (DC)
**Problem:** User entered retirement age of 60 in the "About You" step (IncomeStep), but when adding a DC pension in Step 5, the "Planned Access Age" field defaulted to 67 (state pension age) instead of 60.
**Root cause:** The `DCPensionForm.vue` mounted hook checked `if (!this.$store.state.userProfile?.incomeOccupation)` before fetching the profile. During onboarding, the profile was already loaded (from an earlier step) but WITHOUT the retirement age (because the user hadn't filled it in yet at that point). The condition evaluated to false, so the profile wasn't re-fetched, and `profileRetirementAge` was null.
**Fix:** Changed the conditional fetch to always re-fetch: `await this.$store.dispatch('userProfile/fetchProfile')` — this ensures the latest `target_retirement_age` from the Income step is available.
**File:** `resources/js/components/Retirement/DCPensionForm.vue`

---

### Bug 8: Tenants in Common "Other" co-owner causes wizard to exit

**Reported in:** bugsApril2One.pdf (10:01:44)
**Page:** Onboarding Step 5 > Properties > Ownership step
**Problem:** Selecting Tenants in Common with "Other" co-owner, typing a name, and clicking the inner "Next" button sometimes exits the wizard entirely and jumps to the Investments tab. Intermittent but causes data loss.
**Root cause:** When the user types in the "co-owner name" text input and presses Enter, the browser's default behaviour submits the `<form>`. The form's `@submit.prevent="handleSubmit"` fires, and since the basic validation (property type, address, current value, ownership) passes, the form saves and the parent closes it — advancing to the next tab.
**Fix:** Added a guard at the top of `handleSubmit()`: if not in edit mode and not on the final step, call `nextStep()` instead of saving. This prevents accidental form submission from advancing past intermediate wizard steps.
**File:** `resources/js/components/NetWorth/Property/PropertyForm.vue`

---

## Already Fixed (Verified in Browser)

These bugs from `bugsApril2Two.pdf` were fixed in session 29 and verified via Playwright this session:

| Bug | Fix | Verification |
|-----|-----|-------------|
| Add Account modal opens below fold | `<Teleport to="body">` in CashOverview.vue | Modal renders as centred `<dialog>` element |
| Institution name truncated, no tooltip | `:title` attribute on all 4 account-name spans | DOM inspection confirms `title` attributes present |
| No Premium Bonds £50k validation | Validation in `handleSubmit()` of SaveAccountModal | Entered £75k → warning shown, submission blocked |
| Liabilities mortgages don't show joint ownership | Backend: ownership data in EstateController. Frontend: joint info in LiabilityCard | Cards show "Joint (50% yours)", "Total Balance", "Your Share" |
| Mortgage card not clickable in liabilities | Click handler + cursor:pointer in LiabilityCard | Click navigated to `/net-worth/property` |
| Dashboard pie chart not clickable | Route mapping + click handler on SVG circles | Click navigated to `/net-worth/retirement` |
| Investment detail page blank | Redirect to `/net-worth/investments` when no account prop | Direct URL redirects, 0 console errors |
| No holdings on projected value | Same fix as above (investment-detail redirect) | Same verification |

## Already Fixed (No Change Needed)

| Bug | Status |
|-----|--------|
| Number input scroll interference | Global wheel handler in `app.js` (lines 52-57) already deployed |
| Error messages persist across tab switches | Watch on `activeTab` clears `error.value` in AssetsStep.vue |

---

## UX Suggestions (Noted, Not Implemented)

These were feature requests, not bugs:

1. **Cash account prompts during onboarding** — Suggest common account types based on user profile (e.g., "Most people your age have an emergency fund")
2. **Spouse view switcher on dashboard** — "View as Sarah" toggle in navigation
3. **ISA allowance shows 2025/26 tax year** — FIXED as part of the dynamic tax year overhaul (see below)
4. **No cash accounts added prompt** — More prominent guidance when Cash tab is empty in onboarding

---

## Additional Fix: Dynamic Tax Year Overhaul

**Not from bug reports — triggered by the ISA tax year UX suggestion.**

Eliminated ALL hardcoded "2025/26" tax year strings and hardcoded financial values (ISA £20,000, pension £60,000, CGT £3,000, personal allowance £12,570, etc.) across the entire codebase.

**What was done:**
- Created `getCurrentTaxYear()` utility in `resources/js/utils/dateFormatter.js`
- Replaced hardcoded "2025/26" with dynamic `getCurrentTaxYear()` in 37 frontend files
- Replaced hardcoded tax values with imports from `resources/js/constants/taxConfig.js`
- Fixed 2 backend files to use `TaxConfigService` instead of hardcoded years
- Fixed stale CGT allowance (£12,300 from 2022/23 → £3,000 current) in RebalancingCalculator
- Added stop hook (`tax-hardcode-check.sh`) to catch future violations
- Added memory rule (`feedback_never_hardcode_tax_values.md`)
- Updated design guide to v1.3.0 with Dynamic Financial Values section

**Files changed (39 frontend + 2 backend):** See `bugIssueDeploy.md` for full list.

**Why this matters:** The UK tax year changes to 2026/27 on 6 April 2026 (4 days away). Without this fix, every tax year reference on the site would show stale "2025/26" after April 5.

---

## Issues Found During Testing

1. **ChrisUserSeeder fails** — `protection_profiles.annual_income` has no default value. Pre-existing issue, not related to this session's changes. The seeder mostly completes; only ChrisUserSeeder and AdvisorClientSeeder are affected.

2. **WillBuilderApiTest intermittent failure** — Expects `"full_name": "James Carter"` but seeded data gets stale after full test suite runs. Passes when run independently after reseeding. Test isolation issue.

3. **Pest test suite: 7 failures in UserMetricsServiceTest** — All test isolation issues from database state interference. Pass when run independently.

---

## Production Test Checklist

See `bugIssueDeploy.md` for the full deployment steps and production test checklist.
