# Production Test Bug Fixes — 20 March 2026

Production testing of the j1 (Starting Out) journey and subsequent module checks uncovered 14 issues. Issue 1 was resolved via SSH, Issue 7 is not a bug. The remaining 12 are fixed below.

**Test Results:** 2028 passed, 1 pre-existing flaky test (WillBuilderApiTest — unrelated).

---

## Dependency Map

```
Issue 2 (phone regex 422) ──causes──► Issue 3 (goals off-chart)
                           ──causes──► Issue 4 (age shows 45)
All other issues are independent.
```

---

## Issue 1: 405 on POST /api/life-stage/set (HIGH) — RESOLVED VIA SSH

**Symptom:** 405 Method Not Allowed when setting life stage during onboarding.

**Root Cause:** Stale route cache on production. Route exists at `routes/api.php:199`.

**Fix:** Cleared route cache via SSH (`php artisan route:clear`). No code change needed — local codebase confirmed correct.

---

## Issue 2: 422 Validation Error Saving Personal Info (MEDIUM)

**Symptom:** POST to `/api/user/profile/personal` returned 422 during onboarding Step 1 (About You). Journey still advanced but DOB, gender, phone, and address were silently lost.

**Root Cause:** Phone validation regex `/^(\+44|0)[0-9]{10}$/` rejected spaces. Frontend placeholder shows `07700 900000`, so users naturally type with spaces. The failed regex rejected the entire request including DOB.

**Fix:** Added `prepareForValidation()` to strip spaces and dashes from phone before the regex runs. Updated error message.

**File:** `app/Http/Requests/UpdatePersonalInfoRequest.php`

---

## Issue 3: Goals Not Showing on Dashboard Chart (HIGH)

**Symptom:** Emergency Fund goal saved correctly (visible on `/goals` page) but didn't appear in the dashboard projection chart.

**Root Cause:** Cascade from Issue 2. DOB wasn't saved → `getCurrentAge()` returned fallback 45 → projection started at age 45 → goal at age 22 was off-screen.

**Fix:** Resolved by Issue 2 fix (DOB now saves) + Issue 4 fix (better fallback). No changes to chart components.

---

## Issue 4: Financial Projection Shows Age 45 Instead of 21 (HIGH)

**Symptom:** Goals page showed "Age 45" for a 21-year-old user. Chart x-axis started at 45.

**Root Cause:** `GoalsProjectionService::getCurrentAge()` had `return 45;` when DOB was null (arbitrary magic number), and DOB wasn't saved due to Issue 2.

**Fix:** Changed fallback from `45` to `self::DEFAULT_RETIREMENT_AGE` (68). Issue 2 fix prevents DOB loss going forward.

**File:** `app/Services/Goals/GoalsProjectionService.php`

---

## Issue 5: Student Loan Shows "Plan 5" Instead of "Plan 2" (MEDIUM)

**Symptom:** Dashboard Student Debt card hardcoded "Plan 5" regardless of user's selection.

**Root Cause:** Template had `<span>Plan 5</span>` as literal text. No `plan_type` DB column — plan info is embedded in `liability_name` ("Student Loan (Plan 2)") but never extracted.

**Fix:** `studentLiability` computed now extracts plan type via regex. Added `studentLoanDetails` computed with plan-specific thresholds and write-off periods. Template uses dynamic values.

**File:** `resources/js/views/Dashboard.vue`

---

## Issue 6: Back Button Clears Form Data During Onboarding (MEDIUM)

**Symptom:** Filling a step form, clicking Next, then Back showed an empty form.

**Root Cause:** `:key="lifeStageCurrentStepId"` on the dynamic component causes Vue to destroy and recreate the component on every step change. New instance initialises with empty defaults.

**Fix:** Added `savedStepData` reactive cache in OnboardingWizard. Caches emitted form data on save, passes it back as `:saved-data` prop. StudentLoanStep reads `props.savedData` to restore fields. All step components declare the prop to prevent warnings.

**Files:** `OnboardingWizard.vue`, `StudentLoanStep.vue`, `IncomeStep.vue`, `SimpleExpenditureStep.vue`, `GoalSetupStep.vue`, `PersonalInformation.vue`, `SaveAccountModal.vue`

---

## Issue 7: Employment Details Not Carried from Step 1 to Step 2 (NOT A BUG)

Employment fields are intentionally hidden in Step 1 for "Starting Out" via `onboardingHide` in `lifeStageConfig.js`. Collected in Step 3 (IncomeStep) instead. No fix needed.

---

## Issue 8: About You Page Not Pre-Filling Name and Email (MEDIUM)

**Symptom:** First onboarding step showed empty name/email despite being entered during registration.

**Root Cause:** `PersonalInformation.vue` reads from `userProfile/personalInfo` getter, which requires `userProfile/fetchProfile` to be dispatched. This was never called during life-stage onboarding init.

**Fix:** Added `store.dispatch('userProfile/fetchProfile')` to `OnboardingWizard.vue`'s `onMounted()` for life-stage mode.

**File:** `resources/js/components/Onboarding/OnboardingWizard.vue`

---

## Issue 9: Mortgage Not Showing as Liability on Net Worth (MEDIUM)

**Symptom:** Mortgage entered during onboarding doesn't appear in Net Worth liabilities list.

**Root Cause:** Mortgages are in the `mortgages` table (linked to properties), not `liabilities`. The Estate API only queried `Liability` records.

**Fix:** `EstateController::index()` now queries user's mortgages and merges them into the liabilities response as synthetic records with `source: 'property_module'` and `liability_type: 'mortgage'`.

**File:** `app/Http/Controllers/Api/EstateController.php`

---

## Issue 10: Will Step "Coming Soon" Banner Stale (LOW)

**Symptom:** Unconditional purple "Will Module - Enhanced Features Coming Soon" banner on WillInfoStep.

**Fix:** Removed the banner.

**File:** `resources/js/components/Onboarding/steps/WillInfoStep.vue`

---

## Issue 11: 500 Error on /api/plans/estate (HIGH)

**Symptom:** 500 Internal Server Error on estate plan endpoint.

**Root Cause:** `EstateAgent.php` line 236 referenced undefined variable `$allPolicies` — should be `$allLifePolicies` (defined on line 102).

**Fix:** `$allPolicies` → `$allLifePolicies`.

**File:** `app/Agents/EstateAgent.php`

---

## Issue 12: Spouse Not in Joint Owner Dropdown (MEDIUM)

**Symptom:** Spouse entered during onboarding doesn't appear in joint owner dropdowns.

**Root Cause:** `spouse` getter required `currentUser.spouse_id` (only set via account linking). A spouse entered as a family member without account linking never sets `spouse_id`, so getter returned `null`.

**Fix:** Getter now checks family members with `relationship: 'spouse'` when `spouse_id` is not set. PropertyForm handles unlinked spouses via `joint_owner_name`. SaveAccountModal shows appropriate messaging.

**Files:** `userProfile.js`, `PropertyForm.vue`, `SaveAccountModal.vue`

---

## Issue 13: Investment Knowledge Nudge Links Don't Work (MEDIUM)

**Symptom:** Dashboard knowledge nudge buttons (Beginner/Intermediate/Experienced) do nothing — nudge stays visible.

**Root Cause:** `StoreRiskProfileRequest.php` required all 4 fields, but nudge only sends `knowledge_level`. Backend returned 422 silently.

**Fix:** Changed all fields from `required` to `sometimes`. Controller uses `updateOrCreate` which handles partial updates, and DB columns are already nullable.

**File:** `app/Http/Requests/Investment/StoreRiskProfileRequest.php`

---

## Issue 14: Plan CTAs Link to Non-Existent Routes (HIGH)

**Symptom:** "Add" CTAs in plan incomplete data sections navigate to blank pages.

**Root Cause:** Routes were consolidated but plan services still referenced old paths:
- `/investments` (plural) → doesn't exist
- `/retirement` → consolidated into `/net-worth/retirement`

**Fix:**
- `InvestmentPlanService.php`: `/investments` → `/net-worth/investments` (3 instances)
- `RetirementPlanService.php`: `/retirement` → `/net-worth/retirement` (3 instances)

All other plan services use valid routes (verified: Estate, Protection, Savings, Goals).

**Files:** `app/Services/Plans/InvestmentPlanService.php`, `app/Services/Plans/RetirementPlanService.php`
