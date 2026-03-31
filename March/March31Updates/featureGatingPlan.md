# Feature Gating Implementation Plan

**Status:** COMPLETE — Implemented and browser tested 31 March 2026
**Branch:** `productionReview`
**Commits:** `4568e5c`, `67cc5e7`
**Spec:** `March/March31Updates/featureGatingSpec.md`
**Deploy guide:** `March/March31Updates/deployGate.md`

---

## Summary

Tier-based feature gating for sidebar menu items and API routes. Locked features are greyed out with a hover tooltip showing the required plan and an upgrade link. Backend `CheckFeatureAccess` middleware enforces the same restrictions on API routes.

---

## Tasks — All Complete

### Task 1: Create `featureGating.js` constant
- [x] Created `resources/js/constants/featureGating.js`
- [x] Tier hierarchy: student < standard < family < pro
- [x] Route-to-tier map for 11 gated sidebar items
- [x] `hasFeatureAccess()` and `getRequiredTier()` helpers
- [x] Special handling for `/valuable-info?section=letter` (query param route)

### Task 2: Create `CheckFeatureAccess` backend middleware
- [x] Created `app/Http/Middleware/CheckFeatureAccess.php`
- [x] Registered `'feature'` alias in `app/Http/Kernel.php`
- [x] Bypasses: trial users, preview users, payments-disabled config
- [x] Returns 403 `{ error: 'upgrade_required', required_plan: '...' }`
- [x] Routes compile successfully

### Task 3: Pest tests for middleware
- [x] Created `tests/Feature/Middleware/CheckFeatureAccessTest.php`
- [x] 10/10 tests pass
- [x] Tests: tier blocking, tier hierarchy, trial bypass, preview bypass, payments-disabled bypass, error format, no-subscription default

### Task 4: Apply `feature:X` middleware to API routes
- [x] `feature:standard` on: properties, mortgages, business-interests, chattels, what-if-scenarios
- [x] `feature:standard` on: `PUT /user/letter-to-spouse` (individual route)
- [x] `feature:family` on: `POST/PUT/DELETE /user/family-members/*`
- [x] `feature:pro` on: estate (entire group), holistic
- [x] Routes compile, verified with `php artisan route:list`

### Task 5: Update `SideMenuItem.vue` — locked state + tooltip
- [x] Added `locked` and `requiredPlan` props
- [x] Locked items render as `<div>` (not `<router-link>`)
- [x] Greyed styling: `text-neutral-300 cursor-not-allowed`
- [x] Tooltip via `<Teleport to="body">` with fixed positioning (CSS-only approach was clipped by sidebar `overflow-y-auto`)
- [x] Tooltip shows "Available on {Plan} plan" + "Upgrade now →" link to `/settings?tab=subscription`
- [x] `@mouseenter`/`@mouseleave` handlers with 100ms delay for hovering tooltip link

### Task 6: Update `SideMenu.vue` — wire up locked props
- [x] Imported `hasFeatureAccess` from `featureGating.js`
- [x] Added `userPlan` computed: returns `'pro'` for preview/trial/no-data, otherwise `subscriptionData.plan`
- [x] Added `isLocked(tier)` helper
- [x] Wired up all 11 gated items with `:locked` and `requiredPlan`

### Task 7: Router guard for direct URL navigation
- [x] Imported `getRequiredTier`, `hasFeatureAccess` in `router/index.js`
- [x] Added feature gate check in `beforeEach` guard (defence-in-depth, backend is primary enforcement)
- [x] Redirects to dashboard for unpermitted tiers
- [x] Fails open if `subscriptionData` not yet loaded

### Task 8: Browser testing
- [x] Preview persona (young_family): all items clickable, no gating — bypass confirmed
- [x] Student/active user: 14 items accessible, 11 items greyed (7 Standard, 4 Pro)
- [x] Hover tooltip appears on greyed items with correct tier name
- [x] "Upgrade now →" link navigates to `/settings?tab=subscription`
- [x] Vite compiles without errors
- [x] 10/10 Pest middleware tests pass

---

## Bug Fix During Implementation

**Tooltip clipped by sidebar overflow** — The initial CSS-only tooltip (using `group-hover:opacity-100` with `absolute left-full` positioning) was invisible because the sidebar's scrollable container (`overflow-y-auto`) clipped content extending beyond its bounds. Fixed by switching to `<Teleport to="body">` with JavaScript-calculated `position: fixed` coordinates from `getBoundingClientRect()`. Commit `67cc5e7`.

---

## Files Changed

| File | Action |
|------|--------|
| `resources/js/constants/featureGating.js` | Created |
| `app/Http/Middleware/CheckFeatureAccess.php` | Created |
| `tests/Feature/Middleware/CheckFeatureAccessTest.php` | Created |
| `app/Http/Kernel.php` | Modified — added `'feature'` middleware alias |
| `routes/api.php` | Modified — added `feature:X` to 9 route groups/routes |
| `resources/js/components/SideMenuItem.vue` | Modified — locked state + teleported tooltip |
| `resources/js/components/SideMenu.vue` | Modified — userPlan computed + 11 items wired |
| `resources/js/router/index.js` | Modified — feature gate in beforeEach |
| `app/Models/Subscription.php` | Modified — fillable ordering synced with production |
| `CSJTODO.md` | Modified — deployment status updated |
