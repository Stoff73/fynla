# Feature Gating — Design Spec

**Date:** 31 March 2026
**Branch:** `productionReview`
**Status:** Approved design, ready for implementation

---

## Overview

Implement tier-based feature gating so users can only access features available on their subscription plan. Locked features remain visible in the sidebar but are greyed out with a hover tooltip showing the required tier and an upgrade link. Backend middleware enforces the same restrictions on API routes.

---

## Tier-to-Feature Mapping

### Student (base tier)
- Dashboard, Net Worth
- Bank Accounts, Income, Expenditure
- Investments, Retirement, Risk Profile
- Protection
- Goals, Life Events, Plans, Journeys, Actions

### Standard (adds to Student)
- Property
- Liabilities
- Personal Valuables
- Business
- Letter to Spouse / Expression of Wishes
- What If Scenarios
- Coordination (no dedicated sidebar item — this refers to cross-module coordination features accessed via the Coordinating Agent backend; no frontend gating needed)

### Family (adds to Standard)
- Family members (ability to add family members / spouse linking)
- No separate sidebar item — gates the family member CRUD operations

### Pro (adds to Family)
- Estate Planning
- Will
- Trusts
- Power of Attorney
- Holistic Plan

### Access Rules
- **Trial users (7-day):** Full access to all features regardless of chosen plan
- **Preview users:** Full access (demo mode)
- **Expired trial / grace period:** Existing CheckSubscription behaviour (read-only access, plan selection modal)

---

## Frontend Design

### SideMenuItem Changes

Add two new props to `SideMenuItem.vue`:

| Prop | Type | Default | Purpose |
|------|------|---------|---------|
| `locked` | Boolean | `false` | Whether the item is gated for the current user |
| `requiredPlan` | String | `''` | Plan name to display in tooltip (`'Standard'`, `'Pro'`) |

When `locked` is true:
- Render as a `<div>` instead of `<router-link>` (no navigation)
- Apply greyed-out styling: `color: #ccc` equivalent via Tailwind (`text-neutral-300`), `cursor-not-allowed`
- No hover highlight (no `hover:bg-savannah-100`)
- Show tooltip on hover (positioned to the right of the item)
- Do not emit `navigate` event

### Tooltip

Dark tooltip (`bg-horizon-600 text-white`) appears on hover, positioned to the right:

```
Available on {Plan} plan
Upgrade now →
```

"Upgrade now" is a `<router-link>` to `/settings?tab=subscription`.

Implementation: CSS-only tooltip using a wrapper `<div>` with `group` class and a hidden child that shows on `group-hover`. No JavaScript tooltip library needed.

### Collapsed Sidebar

When the sidebar is collapsed and an item is locked:
- Show the same greyed-out icon
- Tooltip appears on hover (same as expanded, positioned to the right of the icon)

### Feature Map Constant

New file: `resources/js/constants/featureGating.js`

```javascript
// Tier hierarchy — higher index = more access
export const PLAN_TIERS = ['student', 'standard', 'family', 'pro'];

// Sidebar route → minimum required tier
export const FEATURE_TIER_MAP = {
  // Standard+
  '/net-worth/property': 'standard',
  '/net-worth/liabilities': 'standard',
  '/net-worth/chattels': 'standard',
  '/net-worth/business': 'standard',
  '/planning/what-if': 'standard',

  // Pro
  '/estate': 'pro',
  '/estate/will-builder': 'pro',
  '/trusts': 'pro',
  '/estate/power-of-attorney': 'pro',
  '/holistic-plan': 'pro',
};

// Human-readable plan names for tooltip
export const PLAN_LABELS = {
  student: 'Student',
  standard: 'Standard',
  family: 'Family',
  pro: 'Pro',
};

/**
 * Check if a plan meets the minimum tier requirement.
 */
/**
 * Check if a plan meets the minimum tier requirement.
 */
export function hasFeatureAccess(userPlan, requiredTier) {
  if (!userPlan || !requiredTier) return true;
  return PLAN_TIERS.indexOf(userPlan) >= PLAN_TIERS.indexOf(requiredTier);
}

/**
 * Check if a route (path + query) is gated. Handles both plain paths
 * and the special case of /valuable-info?section=letter.
 */
export function getRequiredTier(path, query = {}) {
  // Check special query-based routes first
  if (path === '/valuable-info' && query.section === 'letter') return 'standard';
  // Then check path-based routes (startsWith for sub-routes like /estate/*)
  for (const [routePath, tier] of Object.entries(FEATURE_TIER_MAP)) {
    if (path === routePath || path.startsWith(routePath + '/')) return tier;
  }
  return null; // Not gated
}
```

Note: The Letter to Spouse route uses a query parameter (`/valuable-info?section=letter`) rather than a unique path, so it needs special handling in both the sidebar (checked inline) and the router guard (via `getRequiredTier`).

### SideMenu Changes

`SideMenu.vue` adds a computed `userPlan`:

```javascript
const userPlan = computed(() => {
  // Trial and preview users get full access
  if (isPreviewMode.value) return 'pro';
  if (!props.subscriptionData) return 'pro'; // No subscription data = ungated (payments disabled)
  if (props.subscriptionData.status === 'trialing') return 'pro';
  return props.subscriptionData.plan || 'student';
});
```

Each gated `SideMenuItem` gets:

```vue
<SideMenuItem
  icon="home-modern"
  label="Property"
  to="/net-worth/property"
  :collapsed="effectiveCollapsed"
  :active="isActive('/net-worth/property')"
  :locked="!hasFeatureAccess(userPlan, 'standard')"
  requiredPlan="Standard"
  @navigate="closeMobile"
/>
```

### Router Guard (optional, defence-in-depth)

Add a check in the router's `beforeEach` guard: if a user navigates directly to a gated route (e.g. via URL), redirect to `/dashboard` with a toast notification. This prevents URL-based bypass of the sidebar gating. Uses the same `FEATURE_TIER_MAP` and `hasFeatureAccess` function.

---

## Backend Design

### CheckFeatureAccess Middleware

New file: `app/Http/Middleware/CheckFeatureAccess.php`

```php
class CheckFeatureAccess
{
    private const PLAN_ORDER = ['student', 'standard', 'family', 'pro'];

    public function handle(Request $request, Closure $next, string $requiredPlan): Response
    {
        // Feature flag: when payments disabled, let everyone through
        if (!config('app.payment_enabled', false)) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        // Preview users bypass
        if ($user->is_preview_user) {
            return $next($request);
        }

        // Load subscription
        if (!$user->relationLoaded('subscription')) {
            $user->load('subscription');
        }

        // Trial users get full access
        if ($user->onTrial()) {
            return $next($request);
        }

        // Check tier
        $userPlan = $user->subscription?->plan ?? 'student';
        $userTier = array_search($userPlan, self::PLAN_ORDER);
        $requiredTier = array_search($requiredPlan, self::PLAN_ORDER);

        if ($userTier === false || $requiredTier === false || $userTier < $requiredTier) {
            return response()->json([
                'error' => 'upgrade_required',
                'message' => 'This feature requires the ' . ucfirst($requiredPlan) . ' plan or higher.',
                'required_plan' => $requiredPlan,
            ], 403);
        }

        return $next($request);
    }
}
```

### Middleware Registration

In `app/Http/Kernel.php`, add to `$middlewareAliases`:

```php
'feature' => \App\Http\Middleware\CheckFeatureAccess::class,
```

### Route Gating

In `routes/api.php`, wrap existing route groups with the feature middleware:

**Standard tier routes:**
```php
Route::middleware(['auth:sanctum', 'feature:standard'])->group(function () {
    // Property routes
    // Liability routes
    // Chattel (valuables) routes
    // Business routes
    // Letter to spouse routes
    // What-if scenario routes
});
```

**Family tier routes:**
```php
Route::middleware(['auth:sanctum', 'feature:family'])->group(function () {
    // Family member CRUD routes (not the read — users can see existing family members)
    // POST/PUT/DELETE on family members
});
```

**Pro tier routes:**
```php
Route::middleware(['auth:sanctum', 'feature:pro'])->group(function () {
    // Estate routes
    // Will routes
    // Trust routes
    // Power of Attorney routes
    // Holistic plan routes
});
```

Note: Existing routes stay where they are — the `feature:X` middleware is added alongside `auth:sanctum`, not replacing it. The `CheckSubscription` middleware continues to handle active/expired/grace period logic separately.

### Family Tier — Special Case

The Family tier doesn't gate sidebar items — it gates the ability to **create/update family members**. This means:
- `GET /api/family-members` — allowed for all tiers (read existing data)
- `POST /api/family-members` — gated to `feature:family`
- `PUT /api/family-members/{id}` — gated to `feature:family`
- `DELETE /api/family-members/{id}` — gated to `feature:family`

Frontend: The "Add Family Member" button in the family section should be disabled/greyed with a tooltip for users below Family tier.

---

## Files Changed

### New Files
- `resources/js/constants/featureGating.js` — tier map and access check function
- `app/Http/Middleware/CheckFeatureAccess.php` — backend tier enforcement

### Modified Files
- `resources/js/components/SideMenuItem.vue` — add `locked` + `requiredPlan` props, tooltip, conditional rendering
- `resources/js/components/SideMenu.vue` — add `userPlan` computed, pass locked/requiredPlan to gated items
- `app/Http/Kernel.php` — register `feature` middleware alias
- `routes/api.php` — add `feature:X` middleware to route groups
- `resources/js/router/index.js` — add route guard for gated routes (redirect to dashboard)

### No Changes
- No database migrations
- No model changes
- No new API endpoints
- CheckSubscription middleware unchanged

---

## Testing

### Pest Tests
- Test `CheckFeatureAccess` middleware: student blocked from standard routes, standard allowed, pro allowed for all
- Test trial user bypasses feature gate
- Test preview user bypasses feature gate
- Test payments-disabled config bypasses feature gate
- Test 403 response format includes `required_plan`

### Browser Testing
- Log in as each tier (student, standard, pro) and verify correct items are greyed
- Hover locked items and verify tooltip appears with correct tier name
- Verify "Upgrade now" link navigates to subscription settings
- Verify trial user sees all items unlocked
- Verify preview personas see all items unlocked
- Verify direct URL navigation to gated route redirects to dashboard
- Test collapsed sidebar tooltip behaviour
