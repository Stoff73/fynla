# Technical Debt Register

This file tracks technical debt items that should be addressed in future development cycles.

---

## High Priority

### 1. IHTPlanning.vue - Duplicate Tables

**File:** `resources/js/components/Estate/IHTPlanning.vue`

**Lines:** ~2500 lines total

**Issue:** The component contains two nearly identical IHT calculation tables:
- Table 1 (lines 324-1101): For married users WITH spouse data linked
- Table 2 (lines 1130-1898): For non-married users OR married without spouse link

Each table is ~770 lines, meaning ~1500 lines of near-duplicate code.

**Impact:**
- Maintenance burden: Changes must be made in two places (easy to miss one)
- Bug risk: Inconsistent updates between tables (as seen with color changes)
- File size: Component is difficult to navigate and understand
- Build size: Unnecessary code duplication

**Recommended Fix:**
Extract the table into a reusable `IHTCalculationTable.vue` component that accepts props for:
- `data` - The calculation data (either secondDeathData or ihtData)
- `isMarried` - Boolean for married-specific rendering
- `hasSpouseLink` - Boolean for spouse link status
- `projections` - Projection data object

**Estimated Effort:** 4-6 hours

**Date Added:** 2026-01-29

---

## Medium Priority

### 2. Investment AccountForm.vue - Large Component

**File:** `resources/js/components/Investment/AccountForm.vue`

**Issue:** The account form component handles too many account types with extensive conditional rendering. Adding Employee Share Schemes and Private Investments made it even larger.

**Recommended Fix:**
- Extract account-type-specific form sections into separate components
- Use a factory pattern or composition to load relevant sections
- Consider separate form components for major account type categories

**Estimated Effort:** 6-8 hours

**Date Added:** 2026-01-29

---

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

*Move items here when resolved, with date and brief resolution notes.*

---

## Notes

- Priority levels: High (blocking or significant risk), Medium (should address soon), Low (nice to have)
- Estimated effort is rough guidance, actual may vary
- Add new items at the top of their priority section
- Include file paths, line numbers where relevant, and date added
