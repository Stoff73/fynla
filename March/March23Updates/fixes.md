# Fixes — 23 March 2026

## 1. PropertyForm Edit 422 (FIXED & DEPLOYED)

**Bug:** Editing any property and clicking Save returned a 422 validation error.

**Root cause:** `lease_remaining_years` was sent as `{}` (empty object). Laravel's `PropertyResource` uses `$this->when(tenure_type === 'leasehold', ...)` which returns a `MissingValue` object for non-leasehold properties. When serialised to JSON this becomes `{}`. PHP receives it as `[]` which fails the `integer` validation rule in `UpdatePropertyRequest`.

Additionally, the form sent empty strings for nullable date/string fields (e.g. `purchase_date: ''`), though Laravel's `ConvertEmptyStringsToNull` middleware handles these automatically.

**Fix:** Added payload cleaning in `PropertyForm.vue` `handleSubmit()`:
- Converts empty strings to `null` for nullable date and string fields
- Nullifies `lease_remaining_years` when tenure is not leasehold, or when value is a non-scalar (empty object)

**File changed:** `resources/js/components/NetWorth/Property/PropertyForm.vue` (lines 2033-2046)

**Verified:** Browser tested on both dev server and production (fynla.org). Edit → Save Property with empty Purchase Date now succeeds.

---

## 2. Dashboard Cards Filtered by Life Stage (FIXED & DEPLOYED)

**Bug:** When a user had `life_stage = 'university'` (Starting Out journey), the dashboard hid Net Worth, Investments, Retirement, Property, Protection cards and showed Student Debt / Budget Tracker instead — even though the user had real data in those modules.

**Root cause:** `isStudentPersona` computed in `Dashboard.vue` returned `true` for ANY user with `life_stage === 'university'`, not just preview student personas. This swapped out the standard dashboard cards for student-specific ones.

**Fix:** Changed `isStudentPersona` to only apply to actual preview student personas:
```javascript
// Before (WRONG)
isStudentPersona() {
  return this.currentUser?.preview_persona_id === 'student'
    || this.currentUser?.life_stage === 'university';
}

// After (CORRECT)
isStudentPersona() {
  return this.currentUser?.preview_persona_id === 'student'
    && this.currentUser?.is_preview_user === true;
}
```

**Design principle:** The journey/life stage only affects the onboarding wizard steps and progress tracking. It should never filter or hide dashboard content. Dashboard cards are shown based on data presence (`hasInvestmentData`, `hasProtectionData`, etc.).

**File changed:** `resources/js/views/Dashboard.vue` (line 922-925)

---

## 3. Sidebar Journey 0% Flash on Page Load (FIXED & DEPLOYED)

**Bug:** On every hard page load (browser navigation, refresh), the sidebar briefly showed "Journey: 0% complete" before updating to the correct percentage.

**Root cause:** The sidebar rendered the progress section immediately using Vuex state, which initialises with `stepCompleteness: {}`. The `progressPercentage` getter returns 0 when completeness data is empty. The correct value only appears after the async `GET /api/life-stage/progress` API call completes.

**Fix:** Added a `showProgress` computed in `SideMenu.vue` that checks both `currentStage` and `!lifeStageLoading`. The progress ring, progress bar, and percentage text are hidden until the life-stage data has loaded, preventing the 0% flash.

**File changed:** `resources/js/components/SideMenu.vue` (lines 32, 51, 67, 245, 529)

---

## How the Journey / Completeness / Dashboard System Works

For reference, here is how these three systems connect:

### Journey (Life Stage)
- Set during onboarding or via API (`POST /api/life-stage/set`)
- Valid values: `university`, `early_career`, `mid_career`, `peak`, `retirement`
- Stored on `users.life_stage`
- Controls: onboarding wizard steps, form field visibility, learning content, sidebar colours
- Does NOT control: which dashboard cards are visible (that's data-driven)

### Completeness Tracking
- Backend (`LifeStageService::getStepCompleteness()`) checks actual DB data for each step's fields
- Returns per-step: `{ status, filled_count, total_count, percentage }`
- `progressPercentage` = total filled fields / total fields across all steps
- "X of Y steps complete" = steps where status = 'complete' / total steps
- Refreshed via `GET /api/life-stage/progress` on page load and after onboarding step saves

### Dashboard Cards
- `isCardVisible()` always returns `true` — cards show based on data presence
- Each card has its own `v-if` condition (e.g. `hasInvestmentData`, `hasProtectionData`)
- The `dashboard.cards` array in `lifeStageConfig.js` is defined but not used for filtering
- Only exception: `isStudentPersona` (now preview-only) swaps budget/student cards for net-worth
