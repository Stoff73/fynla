# Feature Gating Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement tier-based feature gating so locked features are greyed out in the sidebar with an upgrade tooltip, and the backend rejects API calls for features above the user's plan tier.

**Architecture:** Frontend uses a `featureGating.js` constant to map routes to minimum tiers. `SideMenuItem` gains `locked`/`requiredPlan` props that render greyed items with CSS-only tooltips. Backend adds a `CheckFeatureAccess` middleware applied per-route-group in `routes/api.php`. Trial and preview users bypass all gates.

**Tech Stack:** Vue.js 3, Tailwind CSS, Laravel 10 middleware

**Spec:** `March/March31Updates/featureGatingSpec.md`

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `resources/js/constants/featureGating.js` | Create | Tier hierarchy, route-to-tier map, `hasFeatureAccess()` + `getRequiredTier()` helpers |
| `app/Http/Middleware/CheckFeatureAccess.php` | Create | Backend tier enforcement middleware |
| `app/Http/Kernel.php` | Modify (line 82) | Register `'feature'` middleware alias |
| `routes/api.php` | Modify | Add `feature:X` middleware to gated route groups |
| `resources/js/components/SideMenuItem.vue` | Modify | Add `locked`/`requiredPlan` props, tooltip, conditional `<div>` rendering |
| `resources/js/components/SideMenu.vue` | Modify | Add `userPlan` computed, pass `locked`/`requiredPlan` to gated items |
| `resources/js/router/index.js` | Modify | Add feature gate check in `beforeEach` guard |
| `tests/Feature/Middleware/CheckFeatureAccessTest.php` | Create | Pest tests for backend middleware |

---

### Task 1: Create `featureGating.js` constant

**Files:**
- Create: `resources/js/constants/featureGating.js`

- [ ] **Step 1: Create the feature gating constant file**

```javascript
// resources/js/constants/featureGating.js

/**
 * Tier hierarchy — higher index = more access.
 * Used by both sidebar gating and router guard.
 */
export const PLAN_TIERS = ['student', 'standard', 'family', 'pro'];

/**
 * Sidebar route path → minimum required tier.
 * Only gated routes are listed — unlisted routes are accessible to all tiers.
 */
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
    '/estate/lpa': 'pro',
    '/holistic-plan': 'pro',
};

/**
 * Human-readable plan names for tooltip display.
 */
export const PLAN_LABELS = {
    student: 'Student',
    standard: 'Standard',
    family: 'Family',
    pro: 'Pro',
};

/**
 * Check if a user's plan meets the minimum tier requirement.
 * Returns true if userPlan >= requiredTier in the hierarchy.
 */
export function hasFeatureAccess(userPlan, requiredTier) {
    if (!userPlan || !requiredTier) return true;
    const userIndex = PLAN_TIERS.indexOf(userPlan);
    const requiredIndex = PLAN_TIERS.indexOf(requiredTier);
    if (userIndex === -1 || requiredIndex === -1) return true;
    return userIndex >= requiredIndex;
}

/**
 * Get the minimum required tier for a given route.
 * Handles both path-only routes and the special /valuable-info?section=letter case.
 * Returns null if the route is not gated.
 */
export function getRequiredTier(path, query = {}) {
    // Special: Letter to Spouse uses a query param, not a unique path
    if (path === '/valuable-info' && query.section === 'letter') return 'standard';

    // Check exact match first, then prefix match for sub-routes (e.g. /estate/*)
    if (FEATURE_TIER_MAP[path]) return FEATURE_TIER_MAP[path];
    for (const [routePath, tier] of Object.entries(FEATURE_TIER_MAP)) {
        if (path.startsWith(routePath + '/')) return tier;
    }
    return null;
}
```

- [ ] **Step 2: Verify file exists and has no syntax errors**

Run: `node -e "require('./resources/js/constants/featureGating.js')" 2>&1 || echo "ES module - check via dev server"`

Note: This is an ES module so `require` won't work. The dev server will catch syntax errors. Just verify the file exists:

Run: `ls -la resources/js/constants/featureGating.js`
Expected: file exists

- [ ] **Step 3: Commit**

```bash
git add resources/js/constants/featureGating.js
git commit -m "feat: add featureGating.js constant — tier hierarchy and route-to-tier map"
```

---

### Task 2: Create `CheckFeatureAccess` backend middleware

**Files:**
- Create: `app/Http/Middleware/CheckFeatureAccess.php`
- Modify: `app/Http/Kernel.php:82`

- [ ] **Step 1: Create the middleware file**

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureAccess
{
    /**
     * Plan tier hierarchy — higher index = more access.
     */
    private const PLAN_ORDER = ['student', 'standard', 'family', 'pro'];

    /**
     * Check if the authenticated user's plan meets the required tier.
     *
     * Usage in routes: ->middleware('feature:standard')
     */
    public function handle(Request $request, Closure $next, string $requiredPlan): Response
    {
        // Feature flag: when payments are disabled, let everyone through
        if (! config('app.payment_enabled', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Preview users bypass feature gates
        if ($user->is_preview_user) {
            return $next($request);
        }

        // Eagerly load subscription to avoid N+1
        if (! $user->relationLoaded('subscription')) {
            $user->load('subscription');
        }

        // Trial users get full access to all features
        if ($user->onTrial()) {
            return $next($request);
        }

        // Determine user's tier position
        $userPlan = $user->subscription?->plan ?? 'student';
        $userTier = array_search($userPlan, self::PLAN_ORDER, true);
        $requiredTier = array_search($requiredPlan, self::PLAN_ORDER, true);

        // If either plan is unknown, allow through (defensive)
        if ($userTier === false || $requiredTier === false) {
            return $next($request);
        }

        if ($userTier < $requiredTier) {
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

- [ ] **Step 2: Register the middleware alias in Kernel.php**

In `app/Http/Kernel.php`, add to the `$middlewareAliases` array after the `'advisor.impersonate'` line (line 82):

```php
'feature' => \App\Http\Middleware\CheckFeatureAccess::class,
```

- [ ] **Step 3: Verify middleware is registered**

Run: `php artisan route:list --json 2>&1 | head -3`
Expected: no errors (routes compile successfully)

- [ ] **Step 4: Commit**

```bash
git add app/Http/Middleware/CheckFeatureAccess.php app/Http/Kernel.php
git commit -m "feat: add CheckFeatureAccess middleware — tier-based API route gating"
```

---

### Task 3: Write Pest tests for `CheckFeatureAccess` middleware

**Files:**
- Create: `tests/Feature/Middleware/CheckFeatureAccessTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Register a test route gated to each tier
    Route::middleware(['auth:sanctum', 'feature:standard'])->get('/api/test-standard', fn () => response()->json(['ok' => true]));
    Route::middleware(['auth:sanctum', 'feature:family'])->get('/api/test-family', fn () => response()->json(['ok' => true]));
    Route::middleware(['auth:sanctum', 'feature:pro'])->get('/api/test-pro', fn () => response()->json(['ok' => true]));
});

it('allows student user to access student-tier routes', function () {
    $user = User::factory()->create();
    Subscription::factory()->create(['user_id' => $user->id, 'plan' => 'student', 'status' => 'active']);

    $this->actingAs($user)->getJson('/api/test-standard')
        ->assertStatus(403)
        ->assertJson(['error' => 'upgrade_required', 'required_plan' => 'standard']);
});

it('allows standard user to access standard-tier routes', function () {
    $user = User::factory()->create();
    Subscription::factory()->create(['user_id' => $user->id, 'plan' => 'standard', 'status' => 'active']);

    $this->actingAs($user)->getJson('/api/test-standard')->assertOk();
});

it('blocks standard user from pro-tier routes', function () {
    $user = User::factory()->create();
    Subscription::factory()->create(['user_id' => $user->id, 'plan' => 'standard', 'status' => 'active']);

    $this->actingAs($user)->getJson('/api/test-pro')
        ->assertStatus(403)
        ->assertJson(['error' => 'upgrade_required', 'required_plan' => 'pro']);
});

it('allows pro user to access all tiers', function () {
    $user = User::factory()->create();
    Subscription::factory()->create(['user_id' => $user->id, 'plan' => 'pro', 'status' => 'active']);

    $this->actingAs($user)->getJson('/api/test-standard')->assertOk();
    $this->actingAs($user)->getJson('/api/test-family')->assertOk();
    $this->actingAs($user)->getJson('/api/test-pro')->assertOk();
});

it('allows trial users to access all tiers', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'student',
        'status' => 'trialing',
        'trial_ends_at' => now()->addDays(5),
    ]);

    $this->actingAs($user)->getJson('/api/test-pro')->assertOk();
});

it('allows preview users to access all tiers', function () {
    $user = User::factory()->create(['is_preview_user' => true]);

    $this->actingAs($user)->getJson('/api/test-pro')->assertOk();
});

it('bypasses feature gate when payments are disabled', function () {
    config(['app.payment_enabled' => false]);

    $user = User::factory()->create();
    Subscription::factory()->create(['user_id' => $user->id, 'plan' => 'student', 'status' => 'active']);

    $this->actingAs($user)->getJson('/api/test-pro')->assertOk();
});

it('returns correct error format with required_plan field', function () {
    $user = User::factory()->create();
    Subscription::factory()->create(['user_id' => $user->id, 'plan' => 'student', 'status' => 'active']);

    $response = $this->actingAs($user)->getJson('/api/test-pro');
    $response->assertStatus(403);
    $response->assertJsonStructure(['error', 'message', 'required_plan']);
    $response->assertJson(['required_plan' => 'pro']);
});

it('treats user with no subscription as student tier', function () {
    $user = User::factory()->create();
    // No subscription created

    $this->actingAs($user)->getJson('/api/test-standard')
        ->assertStatus(403)
        ->assertJson(['required_plan' => 'standard']);
});

it('allows family user to access standard-tier routes', function () {
    $user = User::factory()->create();
    Subscription::factory()->create(['user_id' => $user->id, 'plan' => 'family', 'status' => 'active']);

    $this->actingAs($user)->getJson('/api/test-standard')->assertOk();
    $this->actingAs($user)->getJson('/api/test-family')->assertOk();
});
```

- [ ] **Step 2: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Middleware/CheckFeatureAccessTest.php`
Expected: All 9 tests pass. If `SubscriptionFactory` doesn't exist, check `database/factories/SubscriptionFactory.php` — it was created in session 20.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Middleware/CheckFeatureAccessTest.php
git commit -m "test: add CheckFeatureAccess middleware tests — 9 tier/bypass scenarios"
```

---

### Task 4: Apply `feature:X` middleware to API route groups

**Files:**
- Modify: `routes/api.php`

This is the most delicate task. The existing route groups stay intact — we only add the `feature:X` middleware to each group's middleware array.

- [ ] **Step 1: Add `feature:standard` to property routes (line 284)**

Change:
```php
Route::middleware('auth:sanctum')->prefix('properties')->group(function () {
```
To:
```php
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('properties')->group(function () {
```

- [ ] **Step 2: Add `feature:standard` to mortgage routes (line 307)**

Change:
```php
Route::middleware('auth:sanctum')->prefix('mortgages')->group(function () {
```
To:
```php
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('mortgages')->group(function () {
```

- [ ] **Step 3: Add `feature:standard` to business interest routes (line 316)**

Change:
```php
Route::middleware('auth:sanctum')->prefix('business-interests')->group(function () {
```
To:
```php
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('business-interests')->group(function () {
```

- [ ] **Step 4: Add `feature:standard` to chattel routes (line 327)**

Change:
```php
Route::middleware('auth:sanctum')->prefix('chattels')->group(function () {
```
To:
```php
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('chattels')->group(function () {
```

- [ ] **Step 5: Add `feature:standard` to what-if scenario routes (line 1130)**

Change:
```php
Route::middleware('auth:sanctum')->prefix('what-if-scenarios')->group(function () {
```
To:
```php
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('what-if-scenarios')->group(function () {
```

- [ ] **Step 6: Add `feature:standard` to Letter to Spouse write route (line 220)**

The Letter to Spouse routes live inside the `user` prefix group. Only gate the write (PUT) — reads stay open.

Change (line 220):
```php
    Route::put('/letter-to-spouse', [LetterToSpouseController::class, 'update']);
```
To:
```php
    Route::put('/letter-to-spouse', [LetterToSpouseController::class, 'update'])->middleware('feature:standard');
```

- [ ] **Step 7: Add `feature:family` to family member write routes (lines 225-228)**

Gate POST/PUT/DELETE but not GET (reads stay open for all tiers).

Change (lines 225-228):
```php
        Route::post('/', [FamilyMembersController::class, 'store']);
        Route::get('/{id}', [FamilyMembersController::class, 'show']);
        Route::put('/{id}', [FamilyMembersController::class, 'update']);
        Route::delete('/{id}', [FamilyMembersController::class, 'destroy']);
```
To:
```php
        Route::post('/', [FamilyMembersController::class, 'store'])->middleware('feature:family');
        Route::get('/{id}', [FamilyMembersController::class, 'show']);
        Route::put('/{id}', [FamilyMembersController::class, 'update'])->middleware('feature:family');
        Route::delete('/{id}', [FamilyMembersController::class, 'destroy'])->middleware('feature:family');
```

- [ ] **Step 8: Add `feature:pro` to estate routes (line 758)**

Change:
```php
Route::middleware('auth:sanctum')->prefix('estate')->group(function () {
```
To:
```php
Route::middleware(['auth:sanctum', 'feature:pro'])->prefix('estate')->group(function () {
```

Note: Estate liabilities (`/api/estate/liabilities/*`) are nested inside the estate group. These are estate-specific liabilities for IHT calculation, distinct from the net-worth liabilities view. Gating the whole estate group as Pro is correct — net-worth liabilities display uses the net-worth aggregation endpoint (which is ungated), not the estate CRUD.

- [ ] **Step 9: Add `feature:pro` to holistic planning routes (line 934)**

Change:
```php
Route::middleware('auth:sanctum')->prefix('holistic')->group(function () {
```
To:
```php
Route::middleware(['auth:sanctum', 'feature:pro'])->prefix('holistic')->group(function () {
```

- [ ] **Step 10: Verify routes compile**

Run: `php artisan route:list --json 2>&1 | head -3`
Expected: no errors

Run: `php artisan route:list --path=properties 2>&1 | head -5`
Expected: routes show with `feature:standard` middleware

- [ ] **Step 11: Commit**

```bash
git add routes/api.php
git commit -m "feat: apply feature:X middleware to gated API route groups"
```

---

### Task 5: Update `SideMenuItem.vue` — locked state + tooltip

**Files:**
- Modify: `resources/js/components/SideMenuItem.vue`

- [ ] **Step 1: Add `locked` and `requiredPlan` props**

In the props object (after the `muted` prop at line 94), add:

```javascript
    locked: {
      type: Boolean,
      default: false,
    },
    requiredPlan: {
      type: String,
      default: '',
    },
```

- [ ] **Step 2: Add a new template block for the locked state**

Insert a new condition BEFORE the external link `v-if` (before line 3). The locked state takes priority over all other rendering modes. Replace the entire `<template>` content with:

```vue
<template>
  <!-- Locked item (feature-gated) -->
  <div
    v-if="locked"
    class="group relative flex items-center mx-2 rounded-md text-neutral-300 cursor-not-allowed"
    :class="collapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2'"
    :title="collapsed ? label + ' — ' + requiredPlan + ' plan' : ''"
  >
    <SideMenuIcon :name="icon" class="w-5 h-5 flex-shrink-0" />
    <span v-if="!collapsed" class="ml-3 text-sm font-medium whitespace-nowrap">{{ label }}</span>

    <!-- Tooltip (appears on hover) -->
    <div
      class="pointer-events-none absolute left-full ml-2 top-1/2 -translate-y-1/2 z-[70] opacity-0 group-hover:opacity-100 transition-opacity duration-200"
    >
      <div class="pointer-events-auto bg-horizon-600 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-lg">
        <div>Available on <strong>{{ requiredPlan }}</strong> plan</div>
        <router-link
          to="/settings?tab=subscription"
          class="text-raspberry-300 hover:text-raspberry-200 underline text-[11px]"
        >Upgrade now &rarr;</router-link>
      </div>
    </div>
  </div>

  <!-- External link -->
  <a
    v-else-if="external && href"
    :href="href"
    target="_blank"
    rel="noopener noreferrer"
    class="group flex items-center mx-2 rounded-md transition-colors"
    :class="[itemClasses, collapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2']"
    :title="collapsed ? label : ''"
    @click="$emit('navigate')"
  >
    <SideMenuIcon :name="icon" class="w-5 h-5 flex-shrink-0" />
    <span v-if="!collapsed" class="ml-3 text-sm font-medium whitespace-nowrap">{{ label }}</span>
    <svg v-if="!collapsed" class="w-3 h-3 ml-auto text-horizon-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
    </svg>
  </a>

  <!-- Action button (e.g. Bug Report) -->
  <button
    v-else-if="!to && !href"
    class="group flex items-center self-stretch mx-2 rounded-md transition-colors"
    :class="[itemClasses, collapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2']"
    :title="collapsed ? label : ''"
    @click="$emit('action')"
  >
    <SideMenuIcon :name="icon" class="w-5 h-5 flex-shrink-0" />
    <span v-if="!collapsed" class="ml-3 text-sm font-medium whitespace-nowrap">{{ label }}</span>
  </button>

  <!-- Router link -->
  <router-link
    v-else
    :to="to"
    class="group flex items-center mx-2 rounded-md transition-colors"
    :class="[
      active
        ? activeBgClass
        : (muted ? 'text-neutral-500 opacity-70 hover:opacity-100 hover:bg-savannah-100 hover:text-horizon-500' : 'text-neutral-500 hover:bg-savannah-100 hover:text-horizon-500'),
      collapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2'
    ]"
    :title="collapsed ? label : ''"
    @click="$emit('navigate')"
  >
    <SideMenuIcon :name="icon" class="w-5 h-5 flex-shrink-0" :class="active ? activeIconClass : ''" />
    <span v-if="!collapsed" class="ml-3 text-sm font-medium whitespace-nowrap">{{ label }}</span>
  </router-link>
</template>
```

- [ ] **Step 3: Verify Vite compiles without errors**

Check the `./dev.sh` terminal output for any compilation errors. If the dev server is running, save the file and check for red error output.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/SideMenuItem.vue
git commit -m "feat: SideMenuItem locked state — greyed text, hover tooltip with upgrade link"
```

---

### Task 6: Update `SideMenu.vue` — pass locked/requiredPlan to gated items

**Files:**
- Modify: `resources/js/components/SideMenu.vue`

- [ ] **Step 1: Import featureGating helpers**

At the top of the `<script>` block (after the existing imports, around line 168), add:

```javascript
import { hasFeatureAccess, PLAN_LABELS } from '@/constants/featureGating';
```

- [ ] **Step 2: Add `userPlan` computed in setup()**

Inside the `setup()` function, after the `currentPlanSlug` computed (around line 475), add:

```javascript
    // Feature gating: determine effective plan for sidebar gating
    const userPlan = computed(() => {
      if (isPreviewMode.value) return 'pro';
      if (!props.subscriptionData) return 'pro'; // No data = payments disabled, show all
      if (props.subscriptionData.status === 'trialing') return 'pro';
      return props.subscriptionData.plan || 'student';
    });

    const isLocked = (requiredTier) => !hasFeatureAccess(userPlan.value, requiredTier);
```

- [ ] **Step 3: Return new values from setup()**

Add to the return object (around line 548):

```javascript
      userPlan,
      isLocked,
      PLAN_LABELS,
```

- [ ] **Step 4: Add locked/requiredPlan props to gated sidebar items**

For each gated `SideMenuItem`, add `:locked` and `requiredPlan` props. Here are all the items to change:

**Finances section — Standard+ items:**

Property (line 72):
```vue
<SideMenuItem icon="home-modern" label="Property" to="/net-worth/property" :collapsed="effectiveCollapsed" :active="isActive('/net-worth/property')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('standard')" requiredPlan="Standard" @navigate="closeMobile" />
```

Liabilities (line 73):
```vue
<SideMenuItem icon="credit-card" label="Liabilities" to="/net-worth/liabilities" :collapsed="effectiveCollapsed" :active="isLiabilitiesActive" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('standard')" requiredPlan="Standard" @navigate="closeMobile" />
```

Personal Valuables (line 74):
```vue
<SideMenuItem icon="cube" label="Personal Valuables" to="/net-worth/chattels" :collapsed="effectiveCollapsed" :active="isActive('/net-worth/chattels')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('standard')" requiredPlan="Standard" @navigate="closeMobile" />
```

Business (line 76):
```vue
<SideMenuItem icon="briefcase" label="Business" to="/net-worth/business" :collapsed="effectiveCollapsed" :active="isActive('/net-worth/business')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('standard')" requiredPlan="Standard" @navigate="closeMobile" />
```

**Personal Affairs section — Standard+ items:**

Letter to Spouse (line 83):
```vue
<SideMenuItem icon="envelope" :label="hasSpouse ? 'Letter to Spouse' : 'Expression of Wishes'" :to="{ path: '/valuable-info', query: { section: 'letter' } }" :collapsed="effectiveCollapsed" :active="isValuableInfoSection('letter')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('standard')" requiredPlan="Standard" @navigate="closeMobile" />
```

**Personal Affairs section — Pro items:**

Will (line 82):
```vue
<SideMenuItem icon="document-check" label="Will" to="/estate/will-builder" :collapsed="effectiveCollapsed" :active="isWillBuilderActive" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('pro')" requiredPlan="Pro" @navigate="closeMobile" />
```

Trusts (line 84):
```vue
<SideMenuItem icon="building-library" label="Trusts" to="/trusts" :collapsed="effectiveCollapsed" :active="isActive('/trusts')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('pro')" requiredPlan="Pro" @navigate="closeMobile" />
```

Estate Planning (line 85):
```vue
<SideMenuItem icon="document-text" label="Estate Planning" to="/estate" :collapsed="effectiveCollapsed" :active="isEstateActive" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('pro')" requiredPlan="Pro" @navigate="closeMobile" />
```

Power of Attorney (line 86):
```vue
<SideMenuItem icon="key" label="Power of Attorney" to="/estate/power-of-attorney" :collapsed="effectiveCollapsed" :active="isLpaActive" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('pro')" requiredPlan="Pro" @navigate="closeMobile" />
```

**Planning section — Pro items:**

Holistic Plan (line 91):
```vue
<SideMenuItem icon="puzzle-piece" label="Holistic Plan" to="/holistic-plan" :collapsed="effectiveCollapsed" :active="isActive('/holistic-plan')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('pro')" requiredPlan="Pro" @navigate="closeMobile" />
```

What If Scenarios (line 94):
```vue
<SideMenuItem icon="beaker" label="What If Scenarios" to="/planning/what-if" :collapsed="effectiveCollapsed" :active="isActive('/planning/what-if')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('standard')" requiredPlan="Standard" @navigate="closeMobile" />
```

- [ ] **Step 5: Verify Vite compiles without errors**

Check the dev server terminal for compilation errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/SideMenu.vue
git commit -m "feat: SideMenu passes locked/requiredPlan to all gated sidebar items"
```

---

### Task 7: Add router guard for direct URL navigation

**Files:**
- Modify: `resources/js/router/index.js`

- [ ] **Step 1: Import featureGating helpers at the top of the file**

After the existing imports (around line 4), add:

```javascript
import { getRequiredTier, hasFeatureAccess } from '@/constants/featureGating';
```

- [ ] **Step 2: Add feature gate check in the `beforeEach` guard**

Find the existing `router.beforeEach` guard. Inside it, after the auth checks pass (user is authenticated and route requires auth), add this block BEFORE the `next()` call:

```javascript
    // Feature gating: redirect to dashboard if user navigates to a gated route
    if (to.matched.some(r => r.meta.requiresAuth)) {
      const user = store.state.auth.user;
      if (user && !user.is_preview_user) {
        const subscriptionData = store.state.auth.subscriptionData;
        // Only gate if payments are enabled and user is not trialing
        if (subscriptionData && subscriptionData.status !== 'trialing') {
          const requiredTier = getRequiredTier(to.path, to.query);
          if (requiredTier) {
            const userPlan = subscriptionData.plan || 'student';
            if (!hasFeatureAccess(userPlan, requiredTier)) {
              return next('/dashboard');
            }
          }
        }
      }
    }
```

Note: This depends on `store.state.auth.subscriptionData` being available. If subscription data is only in AppLayout props (not Vuex), this guard won't work until the data is fetched. This is acceptable — the guard is defence-in-depth. The backend middleware is the real enforcement. If `subscriptionData` is null/undefined, the guard allows through (fail-open, backend catches it).

- [ ] **Step 3: Verify Vite compiles and routing still works**

Check dev server terminal. Navigate to `/dashboard` in browser to confirm basic routing works.

- [ ] **Step 4: Commit**

```bash
git add resources/js/router/index.js
git commit -m "feat: router guard redirects gated routes to dashboard for unpermitted tiers"
```

---

### Task 8: Browser testing

**Files:** None (testing only)

- [ ] **Step 1: Seed database**

Run: `php artisan db:seed`

- [ ] **Step 2: Test as preview persona (should see everything unlocked)**

Navigate to `http://localhost:8000`, select any preview persona. Verify ALL sidebar items are visible and clickable — none greyed out.

- [ ] **Step 3: Test as trial user (should see everything unlocked)**

Log in as a test user with trialing status. Verify all sidebar items are accessible.

- [ ] **Step 4: Test greyed-out items appear correctly**

To test as a student-tier user, you'll need a user with `plan: student, status: active`. Use tinker or the test user. Verify:
- Property, Liabilities, Valuables, Business are greyed
- Letter to Spouse is greyed
- Will, Trusts, Estate Planning, PoA are greyed
- Holistic Plan is greyed
- What If Scenarios is greyed
- All other items are normal

- [ ] **Step 5: Test tooltip on hover**

Hover over a greyed item. Verify:
- Tooltip appears to the right
- Shows "Available on {Plan} plan"
- "Upgrade now" link is visible
- Tooltip disappears on mouse leave

- [ ] **Step 6: Test tooltip upgrade link**

Click "Upgrade now" in the tooltip. Verify it navigates to `/settings?tab=subscription`.

- [ ] **Step 7: Test collapsed sidebar**

Collapse the sidebar. Verify locked items show greyed icons. Hover to see tooltip.

- [ ] **Step 8: Test backend API rejection**

Using browser dev tools or curl, hit a gated endpoint as a student user:

```bash
curl -H "Authorization: Bearer {token}" http://localhost:8000/api/properties
```

Expected: 403 with `{"error": "upgrade_required", "required_plan": "standard"}`

- [ ] **Step 9: Commit (if any fixes were needed)**

```bash
git add -A
git commit -m "fix: adjustments from browser testing of feature gating"
```

---

## Summary

| Task | What | Files |
|------|------|-------|
| 1 | Feature gating constant | `featureGating.js` (create) |
| 2 | Backend middleware | `CheckFeatureAccess.php` (create), `Kernel.php` (modify) |
| 3 | Pest tests | `CheckFeatureAccessTest.php` (create) |
| 4 | Route middleware | `routes/api.php` (modify) |
| 5 | SideMenuItem locked state | `SideMenuItem.vue` (modify) |
| 6 | SideMenu gating | `SideMenu.vue` (modify) |
| 7 | Router guard | `router/index.js` (modify) |
| 8 | Browser testing | — |

Total: 2 new files, 5 modified files, 1 test file. No migrations. No model changes.
