# j1 Test Findings — Thomas Wilson (Starting Out)

**Date**: 20 March 2026
**Email**: j1@fynla.org
**Journey**: Starting Out (university) — 6 steps

## Bugs Found & Fixed During Testing

### BUG 1: Logout does not invalidate session (CRITICAL — FIXED)
- **File**: `app/Http/Controllers/Api/AuthController.php`
- **Issue**: The TransientToken fix (from earlier hotfix) prevented the 500 but skipped ALL session invalidation. After logout, the old session cookie remained valid. Registering a new user while logged in caused the onboarding to show the PREVIOUS user's data.
- **Fix**: Added `$request->session()->invalidate()` and `$request->session()->regenerateToken()` after token cleanup.
- **Deployed**: Yes

### BUG 2: Onboarding step save 500 — "Focus area not set" (CRITICAL — FIXED)
- **File**: `app/Services/LifeStage/LifeStageService.php`
- **Issue**: `onboarding_focus_area` was only set for `retirement` stage (mapped to 'estate'). All other stages got `null`. `OnboardingService::saveStepProgress()` throws when focus area is null.
- **Root cause**: Code review fix #10/#11 went too far — broke 4/5 stages.
- **Fix**: Map each life stage to an existing enum value: university→budgeting, early_career→goals, mid_career→family, peak→investment, retirement→estate.
- **Deployed**: Yes (two iterations — first attempted setting stage name directly, hit enum constraint)

### BUG 3: LifeStageService set focus_area to stage name — DB enum constraint (FIXED)
- **File**: `app/Services/LifeStage/LifeStageService.php`
- **Issue**: First fix attempt set `onboarding_focus_area = $stage` directly, but the DB column is an enum: `estate, protection, retirement, investment, tax_optimisation, budgeting, family, business, goals`. Stage names like `university` aren't valid enum values.
- **Fix**: Created a mapping from stage names to existing enum values.
- **Deployed**: Yes

## j1 Journey Results

### Phase 1: Registration — PASS
- Navigated to fynla.org/register
- Filled: Thomas Wilson, j1@fynla.org, Password1!
- Verification code entered successfully
- Redirected to onboarding

### Phase 2: Life Stage Selection — PASS
- All 5 stages visible
- Selected "Starting Out"
- Journey map showed 6 steps
- Clicked "Start My Journey"

### Phase 3: Onboarding Steps — PASS (all 6 steps)

| Step | Fields Filled | Saved |
|------|--------------|-------|
| 1. Personal Info | DOB: 2004-06-15, Gender: Male | ✅ |
| 2. Student Loan | Plan 2, Balance: £18,500 | ✅ |
| 3. Income | Part-Time, Costa Coffee, Hospitality, Employment £5,400/yr, Other £9,000/yr | ✅ |
| 4. Expenditure | Detailed: Food £200, Transport £80, Mobile £25, Subs £30, Clothing £50, Entertainment £150, Uni Fees £45, Charity £10, Other £10 = £600/mo | ✅ |
| 5. Assets | Nationwide Cash ISA £1,200 @ 3.5%, Monzo Easy Access £800 @ 4.0% (emergency fund) | ✅ |
| 6. Goals | Emergency Fund £1,800, target 30 Sep 2026 | ✅ |

Journey completed → redirected to dashboard.

### Phase 4: Dashboard — PASS
- "Good evening, Thomas" — correct
- "Starting Out · 6 of 6 steps complete" — correct
- Journey: 100%
- Student Debt card: £18,500, Plan 2, 7.3% interest, £27,295/yr threshold — correct
- Cash & Savings: £2,000 (Nationwide £1,200 + Monzo £800) — correct
- Goals & Life Events: Timeline chart ages 21-68 — correct
- Suggested goals: Build emergency fund, Save for a car, Graduate debt-free, Travel fund
- Screenshot: `j1-dashboard.png`

### Phase 5: Expenditure — PASS
- Current Budget tab loaded correctly
- Categories match entered data: Essential £280, Comms £55, Personal £200, Children £45, Other £10
- Manual Total: £590/month, £7,080/year

### Phase 5b: Income — MINOR ISSUE
- Main section shows "Total Annual Income: £5,400" (employment only)
- Income Definitions section shows correct total: £14,400 (Employment £5,400 + Other £9,000)
- **Issue**: "Other Income" not displayed as a line item in the main income breakdown
- Disposable income shows -£1,680 (based on £5,400 only, not £14,400)

### Phase 6: Module Screens

| Module | Status | Notes |
|--------|--------|-------|
| Savings/Bank Accounts | ✅ PASS | Nationwide £1,200 (Cash ISA), Monzo £800 (Easy Access) |
| Income | ⚠️ MINOR | Other income missing from main breakdown |
| Expenditure | ✅ PASS | All categories correct |
| Goals | ⚠️ ISSUE | Shows "feature still being developed" — goal from onboarding not visible |
| Risk Profile | ✅ PASS | Lower-Medium, 9 factors calculated, no 429 error |

### Phase 7: Data Persistence

| Data | Persisted | Notes |
|------|-----------|-------|
| Income | ✅ | Employment £5,400 + Other £9,000 in Income Definitions |
| Savings | ✅ | Both accounts with correct balances and rates |
| Student Loan | ✅ | Plan 2, £18,500 on dashboard |
| Expenditure | ✅ | All detailed categories preserved |
| Goals | ❌ | Emergency fund goal not visible on Goals page |

## Outstanding Issues (Not Fixed)

1. **Income page**: "Other Income" not shown as line item in main breakdown (MEDIUM)
2. **Goals page**: Goal created during onboarding not displayed on dedicated Goals page (MEDIUM)
3. **Sidebar journey %**: Intermittently shows 0% instead of 100% on some pages (LOW — race condition with life-stage/progress fetch)

## Overall: PASS with 2 medium issues
