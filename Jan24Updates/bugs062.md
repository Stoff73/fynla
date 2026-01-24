# Bug Fixes — v0.6.2

## BUG: Goals Module — Modal Buttons Unclickable (z-stacking)

**Reported:** "Nothing works" on the goals module

**Root Cause:** CSS z-stacking issue in all three goal modal components. The backdrop overlay (`fixed inset-0` with `@click="close"`) was positioned above the modal panel (`inline-block`) because `position: fixed` creates a new stacking context. Without explicit z-index on the modal panel, the backdrop intercepted all clicks to the modal's buttons — including "Create Goal", "Record Contribution", and "Delete".

**Symptoms:**
- Clicking "Create Goal" does nothing (no network request, no error, no feedback)
- Form appears to work (fields fill in, auto-assignment shows) but submission never fires
- Calling `handleSubmit()` via browser console works correctly — proving backend is fine

**Investigation:**
- All backend services work (GoalsAgent, GoalProgressService, GoalAffordabilityService, GoalRiskService)
- All 30 API routes registered and responding correctly
- Vuex store actions and goalsService make correct API calls
- POST /api/goals returns 201 when called directly
- Issue isolated to click events not reaching modal button elements

**Fix:**
1. Added `relative z-10` to modal panel div in GoalFormModal, ContributionModal, and GoalsDashboard delete modal
2. Changed "Create Goal" button from `type="button" @click="handleSubmit"` to `type="submit"` (works with form's `@submit.prevent`)
3. Added validation error messages — form no longer fails silently when required fields are missing

**Files Changed:**
- `resources/js/components/Goals/GoalFormModal.vue`
- `resources/js/components/Goals/ContributionModal.vue`
- `resources/js/views/Goals/GoalsDashboard.vue`

---

## BUG: Goals Module — "On Track" shown for goals with 0% progress

**Reported:** All goals showing green "On track" status despite having 0% progress and no contributions

**Root Cause:** In `Goal.php` `getIsOnTrackAttribute()`, when a goal has just been created with no contributions:
- `expectedProgress = 0` (no time elapsed relative to total)
- `progress_percentage = 0`
- Check: `0 >= (0 - 10)` evaluates to `true`
- Result: falsely reports "on track"

The 10% margin was intended to allow goals slightly behind schedule to still count as on track, but it also allowed goals with zero progress to qualify.

Same issue in `GoalProgressService::calculateProgress()`: `progressDelta = 0 - 0 = 0`, and `0 >= -10` is `true`.

**Fix (Backend):**
1. `Goal.php`: Added early return `false` when `current_amount <= 0` — can't be on track with no savings
2. `GoalProgressService.php`: Changed condition to `$currentAmount > 0 && $progressDelta >= -10`

**Fix (Frontend):**
Added "Not started" state (gray styling) for goals with `current_amount <= 0`, distinct from "Behind" (orange):
1. `GoalsOverview.vue`: Added `isNotStarted()`, `getGoalStatusDotClass()`, `getGoalStatusLabel()`, `getGoalProgressBarClass()` methods
2. `GoalsByModule.vue`: Added same helper methods, updated status badge/dot/progress bar
3. `GoalCard.vue`: Added `isNotStarted` computed, updated `progressTextClass`, `progressBarClass`, `borderColorClass`, `statusText`, `statusBadgeClass`, `statusDotClass`

**Result:** Goals with no contributions show gray "Not started" status. Goals with contributions show green "On track" or orange "Behind" based on actual progress vs. expected timeline.

**Files Changed:**
- `app/Models/Goal.php`
- `app/Services/Goals/GoalProgressService.php`
- `resources/js/components/Goals/GoalsOverview.vue`
- `resources/js/components/Goals/GoalsByModule.vue`
- `resources/js/components/Goals/GoalCard.vue`
