# Journey 1: Starting Out (University)

**Stage ID:** `university`
**Steps:** personal-info, student-loan, income, expenditure, savings, goals
**Status:** COMPLETE — all 6 steps filled, dashboard loaded with data.

## Code Fix Applied

### Transition bug fix (Goals step renders blank)
- **File:** `resources/js/components/Onboarding/OnboardingWizard.vue` lines 64-80
- **Problem:** `<Transition name="fade" mode="out-in">` gets stuck when async component loading changes `:is` binding during leave phase. Component loads successfully but Transition refuses to render it — DOM shows `<!---->`.
- **Fix:** Removed `<Transition>` wrapper for life stage mode. Replaced with plain `v-if/v-else`.
- **Status:** CONFIRMED FIXED — Goals step renders correctly with form fields, navigation, and auto-calculated monthly contribution.

## Issues Found During Testing

### Issue 10: Goals step renders blank (FIXED — see above)

### Issue 11: LifeStageController class not found
- **Found at:** Clicking "Start My Journey"
- **Problem:** `Target class [App\Http\Controllers\Api\LifeStageController] does not exist` — autoload didn't include new controller
- **Fix:** `composer dump-autoload -o` regenerates classmap. Server restart required.
- **Status:** FIXED — but the running `php artisan serve` process caches the old autoload. Must restart server after dump-autoload.

### Issue 12: Subscription plans missing during registration
- **Found at:** Verification code entry — "Unknown or inactive subscription plan: standard"
- **Problem:** `subscription_plans` table empty after DB wipe. SubscriptionPlanSeeder doesn't run or silently fails.
- **Fix:** Manual insert works. Seeder needs investigation — may be a model cast issue with `features` JSON column.
- **Status:** FIXED for now — plans exist after manual insert + reseed

### Issue 13: `complete-step` API causes logout redirect (CRITICAL)
- **Found at:** Every step transition (clicking Continue)
- **Problem:** `/api/life-stage/complete-step` returns 500 (LifeStageController not found). The frontend API interceptor catches the error, interprets it as auth failure, and redirects to login. This BREAKS the entire onboarding flow — user gets kicked out after completing Step 1.
- **Root cause:** The LifeStageController autoload issue (Issue 11). When the server process was started before `composer dump-autoload`, it can't resolve the class. The 500 error cascades to the frontend which triggers logout.
- **Fix needed:** Ensure `composer dump-autoload -o` is run and server is restarted. Also: the frontend should NOT redirect to login on a 500 error — only on 401. Check the API interceptor error handling.
- **Status:** NOT YET FIXED — blocked by DB wipe issue

### Issue 14: Database repeatedly wiped by worktree agents
- **Found at:** Multiple times during testing
- **Problem:** Other Claude agents running in `.claude/worktrees/` share the same MySQL database. When those agents run `migrate:fresh` or destructive operations, all user data is deleted including the test user mid-session.
- **Impact:** Caused 4+ test restarts. User tokens become invalid (401), user records disappear.
- **Status:** BLOCKING — cannot complete testing while other agents are active on the database

### Issue 15: Duplicate navigation buttons on liability/savings steps
- **Found at:** Steps 2 (Student Loan), 5 (Savings)
- **Problem:** LiabilityForm has Cancel/Add Liability buttons. SaveAccountModal has Add Account button. Wizard also shows Back/Skip/Continue. Two sets of navigation visible simultaneously.
- **Status:** NOT YET FIXED — cosmetic but confusing

### Issue 16: Forms don't pre-populate on Back navigation
- **Found at:** Navigating back to any completed step
- **Problem:** All form fields reset to blank/defaults when going back, even though data was saved via API on the first pass.
- **Status:** NOT YET FIXED

### Issue 17: Life stage state lost on page reload
- **Found at:** Any page refresh during onboarding
- **Problem:** Vuex `lifeStage.currentStage` and `completedSteps` reset on reload. User sees stage selection again instead of resuming at their current step.
- **Status:** NOT YET FIXED — needs backend persistence of life stage + fetch on mount

### Issue 18: Stage card click requires double-click / delayed response
- **Found at:** Clicking "Starting Out" on stage selection
- **Problem:** First click on stage card often doesn't navigate to journey map. Requires a second click or JS dispatch. Possibly a Vue reactivity timing issue.
- **Status:** NOT YET FIXED

## Steps Successfully Tested (All Fields Filled)

Steps 1-4 were completed with every field filled in a clean run before DB wipe interrupted:

1. **Registration** — All 6 fields: First (Test), Middle (Marie), Last (Student), Email, Password, Confirm
2. **Verification** — Code from `pending_registrations` table entered successfully
3. **Stage Selection** — "Starting Out" selected, journey map with 6 steps + dashboard endpoint displayed
4. **Step 1: About You** — All 8 fields: Full Name, Email, DOB (15/06/2004), Gender (Female), Phone (07712345678), University (Manchester), Student# (12345678), Education Level (Undergraduate). Heading "About You" correct. No address fields for student. Green checkmark on complete.
5. **Step 2: Student Loan** — All 8 fields: Type (Student Loan), Name (Undergraduate Plan 5), Balance (£42,000), Monthly (£0), Rate (7.3%), Maturity (05/04/2067), Secured (None), Notes. Priority Debt unchecked. Green checkmark.
6. **Step 3: Income** — All 9 fields: Status (Part-Time), Occupation (Barista), Employer (Costa Coffee), Industry (Hospitality), Retirement Age (68), Employment Income (£8,400), Dividend (£0), Interest (£25), Other (£3,600). Total correctly calculated as £12,025. Green checkmark.
7. **Step 4: Spending** — Total Monthly Spending £850. Green checkmark.
8. **Step 5: Savings** — NOT COMPLETED (DB wiped before could fill in)
9. **Step 6: Goals** — CONFIRMED RENDERING in earlier test. Form shows: goal type dropdown (8 options), target amount, target date, auto-calculated monthly contribution (£170 for £2,550 over 15 months).
10. **Dashboard** — NOT YET TESTED

## Dashboard Issues

### Issue 19: Progress shows "4 of 6 steps complete" instead of 6 of 6
- All 6 steps were filled and completed with green checkmarks during onboarding
- Dashboard hero shows only 4 of 6 as complete
- The `complete-step` API may not be tracking all steps correctly

### Issue 20: "Next: About You" suggests a completed step
- About You was the first step completed with all fields filled
- Dashboard still suggests it as the next step to complete
- The data readiness check may not detect that personal info was saved

### Issue 21: Goal created during onboarding not shown as active goal card
- Emergency Fund goal (£2,550, target June 2027) was created in Step 6
- Dashboard shows "Suggested for You" goals but not the active goal just created
- The goal may need a different card type or the suggestions are overriding it

## Dashboard Successes
- Cash & Savings card: Shows £1,200 total, 1 account (Monzo) — CORRECT
- Goals & Life Events: Chart renders with projections — CORRECT
- Suggested goals: 4 student-appropriate suggestions (emergency fund, car, debt-free, travel) — CORRECT
- Life Timeline: Empty state with "Add event" button — CORRECT
- Sidebar: Stage-appropriate menu items (no estate/retirement/investment) — CORRECT
- Trial banner: "Standard trial ends in 6 days" — CORRECT
- User name: "Test Marie Student" — CORRECT
