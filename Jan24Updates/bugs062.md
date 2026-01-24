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
