# Technical Debt Register

This file tracks technical debt items that should be addressed in future development cycles.

---

## High Priority

*No high priority items at this time.*

---

## Medium Priority

### PR #303 mobile taxConfig migration — untested on real iOS build

**Added:** 2026-05-16

**What:** PR #303 (`mobile-taxconfig-migration`, merged to `dev` 2026-05-16) migrated the last 3 `@/constants/taxConfig` importers in `resources/js/mobile/` to the `taxConfig` Vuex store. It was browser-verified only on dev-served `/m/*` routes and a plain production `npm run build` — it was **NOT** run through `./deploy/mobile/build-ios.sh` (`npx cap sync ios`) and **NOT** verified on an iOS simulator or device.

**Risk:** The Capacitor WKWebView wrapper exercises the same Vue components but a different asset/MIME path. Per CLAUDE.md mobile section + `feedback_ios_testing_checklist`, mobile-touching changes must pass the full iOS build chain before reaching csjones/main.

**Files involved:**
- `resources/js/mobile/views/RetirementDetail.vue`
- `resources/js/mobile/views/EstateDetail.vue`
- `resources/js/mobile/views/LearnHub.vue`
- `resources/js/mobile/views/LearnTopicDetail.vue`
- `resources/js/mobile/learn/learnTopics.js`

**Action required when mobile work resumes (sub-project 3 — mobile-first surface):** Run `./deploy/mobile/build-ios.sh`, open `ios/App/App.xcworkspace`, and verify on the simulator/device:
- `/m/learn` + `/m/learn/pensions` — dynamic tax values (£60,000 AA, State Pension weekly, active tax year) interpolate correctly
- `/m/module/retirement` — Annual allowance accordion falls through to `taxConfig.pensionAnnualAllowance`
- `/m/module/estate` — `nrb`/`rnrb` resolve via `taxConfig` store
- Zero console errors, no blank screen, no `'image/png' is not a valid JavaScript MIME type'`

Block sub-project 3 acceptance until this is green on a real iOS build.

---

*No other medium priority items at this time.*

---

## Low Priority

*No low priority items at this time.*

---

## Completed

### 5. Preview Persona Data - Consolidated ✅

**Resolved:** 2026-01-29

**Resolution:** The JSON files were already the single source of truth for the PHP seeder (PreviewUserSeeder reads from JSON). The duplication was in `preview.js` which had a separate `PERSONA_METADATA` object.

**Changes Made:**
- Added `netWorthRange` and `focus` fields to all 6 persona JSON files
- Removed duplicate `PERSONA_METADATA` from preview.js
- Created `getPersonaMetadata()` helper to derive metadata from imported JSON
- Added `PERSONA_ORDER` array to control display order in selector

**Files Changed:**
- `resources/js/data/personas/*.json` (6 files) - Added netWorthRange and focus
- `resources/js/store/modules/preview.js` - Removed duplicate metadata

**Results:**
- JSON files are now the single source of truth for ALL persona data
- No more duplicate name/tagline/description between JSON and preview.js
- Future persona changes only need to update JSON files

---

### 4. Risk Color Constants - Consolidated ✅

**Resolved:** 2026-01-29

**Resolution:** Consolidated all risk level color definitions into `designSystem.js` as single source of truth:

**Added to designSystem.js:**
- `RISK_TAILWIND_CLASSES` - Tailwind classes for bg, text, border, and combined
- `RISK_DISPLAY_NAMES` - Display names for all risk levels including legacy
- `RISK_ABBREVIATED_LABELS` - Short labels (Low, L-Med, Med, U-Med, High)
- `RISK_DESCRIPTIONS` - Tooltip descriptions for each level
- `RISK_LEGACY_MAP` - Maps cautious/balanced/adventurous to new system
- `getRiskClasses(level)` - Helper to get Tailwind classes
- `getRiskDisplayName(level)` - Helper to get display name
- `normalizeRiskLevel(level)` - Helper to normalize legacy values

**Updated Files:**
- `riskService.js` - Now delegates to designSystem.js helpers
- `RiskBadge.vue` - Now imports and uses centralized constants

**Results:**
- Single source of truth for risk level styling
- Future color changes only require updating designSystem.js
- Consistent behavior across all components

---

### 3. ExpenditureForm.vue - Component Extraction ✅

**Resolved:** 2026-01-29

**Resolution:** Extracted repeated UI patterns into reusable child components:
- `ExpenditureSection.vue` (~80 lines) - Collapsible section with header and totals
- `ExpenditureGridRow.vue` (~90 lines) - Multi-column grid row for values
- `ExpenditureCategoryCard.vue` (~115 lines) - Edit mode card with fields
- `CurrencyInputField.vue` (~60 lines) - Standardized £ input

**Results:**
- ExpenditureForm.vue reduced from ~2,519 to ~2,100 lines
- Used `:deep()` selectors for centralized value alignment styling
- Financial Commitments section now uses ExpenditureSection component

**Bug Fixed:** Retired budget spouse total was always showing zero due to incorrect calculation. `retiredHouseholdTotalMonthly` was set equal to `retiredTotalMonthly` instead of summing user + spouse.

**Files Created:**
- `resources/js/components/Shared/CurrencyInputField.vue`
- `resources/js/components/UserProfile/ExpenditureSection.vue`
- `resources/js/components/UserProfile/ExpenditureGridRow.vue`
- `resources/js/components/UserProfile/ExpenditureCategoryCard.vue`

---

### 2. Investment AccountForm.vue - Large Component ✅

**Resolved:** 2026-01-29

**Resolution:** Extracted the three major account-type-specific sections into separate child components:
- `PrivateInvestmentFields.vue` (~650 lines) - Private Company, Crowdfunding
- `EmployeeShareSchemeFields.vue` (~600 lines) - SAYE, CSOP, EMI, Unapproved Options, RSU
- `StandardInvestmentFields.vue` (~400 lines) - ISA, GIA, Bonds, VCT, EIS, NS&I, Other

**Results:**
- AccountForm.vue reduced from ~2,643 to ~1,007 lines (62% reduction)
- Each child component uses v-model pattern for two-way data binding
- Adding new account types is now simpler
- Changes to one account type category won't affect others

**Files Created:**
- `resources/js/components/Investment/PrivateInvestmentFields.vue`
- `resources/js/components/Investment/EmployeeShareSchemeFields.vue`
- `resources/js/components/Investment/StandardInvestmentFields.vue`

---

### 1. IHTPlanning.vue - Duplicate Tables ✅

**Resolved:** 2026-01-29

**Resolution:** Extracted the duplicate IHT calculation tables into three reusable components:
- `IHTCalculationTable.vue` (~550 lines) - Main table component with all calculation rows
- `IHTAssetBreakdown.vue` (~213 lines) - Expandable asset section for user/spouse
- `IHTLiabilityBreakdown.vue` (~213 lines) - Expandable liability section for user/spouse

**Results:**
- IHTPlanning.vue reduced from ~3,146 to 1,558 lines (50% reduction)
- Eliminated ~787 lines × 2 of duplicate table code
- Single source of truth for IHT table rendering
- Both married/non-married scenarios now use the same component with normalized props

**Files Created:**
- `resources/js/components/Estate/IHTCalculationTable.vue`
- `resources/js/components/Estate/IHTAssetBreakdown.vue`
- `resources/js/components/Estate/IHTLiabilityBreakdown.vue`

---

---

## Notes

- Priority levels: High (blocking or significant risk), Medium (should address soon), Low (nice to have)
- Estimated effort is rough guidance, actual may vary
- Add new items at the top of their priority section
- Include file paths, line numbers where relevant, and date added
