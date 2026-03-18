# Onboarding Test Log — Starting Out Journey

**Date:** 2026-03-17
**Status:** IN PROGRESS — API routes fixed, need to retest from scratch

## Issues Found & Fixed

### Issue 1: Full budget form in onboarding
- **Found at:** Step 4 (Spending)
- **Problem:** ExpenditureForm.vue loaded with 25 categories, retirement budget tabs
- **Fix:** Changed to SimpleExpenditureStep.vue — single "Total Monthly Spending" field
- **Commit:** `57e2fb5`

### Issue 2: "Focus area not set" error
- **Found at:** Step 3 (Income) Continue click
- **Problem:** Legacy onboarding endpoint requires `onboarding_focus_area` — not set in life stage mode
- **Fix:** LifeStageService.setStage now sets a default focus_area per stage
- **Commit:** `c200a9d`

### Issue 3: Onboarding data not saved
- **Found at:** Dashboard load after completing all steps
- **Problem:** handleLifeStageStepSave just advanced steps — never called APIs to save data
- **Fix:** Added API calls per step type in handleLifeStageStepSave
- **Commit:** `7275579`

### Issue 4: Wrong API routes for data persistence
- **Found at:** Step 5 (Savings) — 405 Method Not Allowed
- **Problem:** Routes were guessed incorrectly (e.g. /savings instead of /savings/accounts)
- **Fix:** Verified all routes against `php artisan route:list` and corrected
- **Commit:** `e946811`

### Issue 5: JourneyProgressHero hidden on empty dashboard
- **Found at:** Dashboard load
- **Problem:** v-if condition `currentStage && !showEmptyDashboard` hid hero when no data
- **Fix:** Changed to just `currentStage`
- **Commit:** `7275579`

### Issue 6: "Edit Personal Information" heading in onboarding
- **Found at:** Step 1
- **Problem:** Form heading said "Edit" when user is entering for first time
- **Fix:** Shows "About You" when context === 'onboarding'
- **Commit:** `28f6023`

### Issue 7: Cancel/Save buttons visible in onboarding
- **Found at:** Step 1
- **Problem:** PersonalInformation.vue's own Cancel/Save buttons visible alongside wizard's Skip/Continue
- **Fix:** Hidden when context === 'onboarding'
- **Commit:** `28f6023`

### Issue 8: Duplicate nav buttons on deprecated steps
- **Found at:** Steps 3, 4 (IncomeStep, SimpleExpenditureStep)
- **Problem:** Both the component and wizard showed Back/Skip/Continue
- **Fix:** Wizard hides its nav when `stepHasOwnNav` is true
- **Commit:** `57e2fb5`

### Issue 9: Goals step form blank
- **Found at:** Step 6
- **Problem:** GoalFormModal is a modal that needs to be triggered — doesn't render inline
- **Status:** KNOWN — user can add goals from dashboard. Needs proper goal form for onboarding.

## Steps Completed in Test
1. ✅ Registration + verification code from DB
2. ✅ Welcome screen with 5 stage cards
3. ✅ Starting Out journey map (exact v6 coordinates)
4. ✅ Step 1: About You — student fields, no address, correct sidebar
5. ✅ Step 2: Student Loan — liability form, £42k entered
6. ✅ Step 3: Income — employment form, part-time barista entered
7. ✅ Step 4: Spending — simple monthly total, £850 entered
8. ✅ Step 5: Savings — Cash ISA £1,200 entered (save failed — route fixed)
9. ⚠️ Step 6: Goals — blank form (GoalFormModal doesn't render inline)
10. ⚠️ Dashboard — loaded but showed empty state (data not persisted — now fixed)

## Next Steps
- Delete test user, restart full test with corrected API routes
- Verify data persists and dashboard shows student data
- Then test remaining 4 journeys
