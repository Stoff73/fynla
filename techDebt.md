# Technical Debt Register

This file tracks technical debt items that should be addressed in future development cycles.

---

## High Priority

*No high priority items at this time.*

---

## Medium Priority

### 3. Expenditure Form - Complex State Management

**File:** `resources/js/components/UserProfile/ExpenditureForm.vue`

**Issue:** Complex state management for handling joint vs separate expenditure, detailed vs simple entry, and person tabs. The form has grown organically and could benefit from refactoring.

**Recommended Fix:**
- Extract tab content into separate components
- Use Vuex or composables for form state
- Simplify conditional rendering logic

**Estimated Effort:** 4-6 hours

**Date Added:** 2026-01-29

---

## Low Priority

### 4. Risk Color Constants - Multiple Sources

**Files:**
- `resources/js/services/riskService.js`
- `resources/js/constants/designSystem.js`
- `resources/js/components/Shared/RiskBadge.vue`

**Issue:** Risk level colors are defined in multiple places. While they're now consistent, future changes require updating multiple files.

**Recommended Fix:**
- Consolidate all risk color definitions into `designSystem.js`
- Import from single source in all components
- Consider CSS custom properties for Tailwind classes

**Estimated Effort:** 2-3 hours

**Date Added:** 2026-01-29

---

### 5. Preview Persona Data - JSON Files

**Files:** `resources/js/data/personas/*.json`

**Issue:** Persona data is duplicated between JSON files (for frontend preview) and PHP seeders (for database). Changes must be made in both places.

**Recommended Fix:**
- Single source of truth for persona data
- Either generate JSON from PHP or vice versa
- Consider a build step to sync data

**Estimated Effort:** 3-4 hours

**Date Added:** 2026-01-29

---

## Completed

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
