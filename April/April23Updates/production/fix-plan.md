---
type: fix-plan
date: 2026-04-23
branch: prodHotFix
target-env: production (fynla.org)
status: APPROVED by CSJ — implementing now
links:
  - findings.md
---

# Fix Plan — Expired-Trial Checkout Loop

## Scope decision

Hotfix only. Enforce the correct UX ordering on expired-trial + grace-period login, and make the Revolut checkout page reachable. Do **not** attempt the `AppLayout`-above-`<router-view>` refactor in this branch — that is separate work.

## Correct UX order (per CSJ)

1. Expired-trial user logs in and lands on `/dashboard`.
2. **`DataRetentionOverlay` is the primary surface.** It shows "Your Subscription Has Expired" with the countdown and two CTAs:
   - **Subscribe Now** → opens `PlanSelectionModal` on top.
   - **Delete All Data & Start Again** → existing deletion flow.
3. In `PlanSelectionModal`, user picks a plan → navigates to `/checkout?plan=X&cycle=Y`.
4. On `/checkout`, neither `DataRetentionOverlay` nor `PlanSelectionModal` renders — Revolut widget is visible.

The current bug:
- Both overlays render simultaneously on Dashboard (wrong order).
- Both re-render on `/checkout` on top of the Revolut widget (blocks payment).

## Answers to pre-implementation questions (CSJ, 2026-04-23)

1. Prod verification stops at "widget visible + `/api/payment/create-order` returns 200". **No live card charge** on production.
2. Automated regression test + Playwright manual — both.
3. Local → production direct. Skip csjones.co/fynla dev stage. Critical hotfix.
4. Delete the test user (`bugrepro_expired_2026_04_23@fynla.org`) after fix verified on prod.

## Changes (three frontend files)

### Change 1 — `AppLayout.vue`: suppress both overlays on `/checkout`, remove auto-show of plan modal for grace-period users

**File:** `resources/js/layouts/AppLayout.vue`

1. **Add `isOnCheckoutRoute` computed.** Insert in `computed` block (after `activePlanSlug`, ~line 189):

   ```js
   isOnCheckoutRoute() {
     return this.$route.path === '/checkout' || this.$route.name === 'Checkout';
   },
   ```

2. **Add `planModalDismissable` data property** (default `false`). In `data()` (~line 149):

   ```js
   planModalDismissable: false,
   ```

3. **Change `DataRetentionOverlay` render gate (line 36):**

   ```vue
   <DataRetentionOverlay
     v-if="isAuthenticated && !isPreviewMode && !isOnCheckoutRoute"
     @subscribe="handleSubscribeFromOverlay"
   />
   ```

4. **Change trial-expired `PlanSelectionModal` (lines 88–93):**

   ```vue
   <!-- Trial Expired — plan selection.
        Suppressed on /checkout because the user is already in the subscribe flow.
        Dismissable when opened from DataRetentionOverlay (grace-period flow). -->
   <PlanSelectionModal
     v-if="showTrialExpiredModal && !isOnCheckoutRoute"
     :dismissable="planModalDismissable"
     @select="handlePlanSelect"
     @close="handleTrialModalClose"
   />
   ```

5. **Modify `checkTrialStatus` method (~line 310)** to only auto-show the plan modal for expired users who are **not** in the grace period. Grace-period users go through `DataRetentionOverlay` first.

   ```js
   async checkTrialStatus() {
     if (this.isPreviewMode) return;
     try {
       const response = await api.get('/payment/trial-status');
       this.subscriptionData = response.data;
       if (!response.data.has_subscription) return;
       const status = response.data.status;
       // For grace-period users, DataRetentionOverlay is the primary surface.
       // PlanSelectionModal opens via its "Subscribe Now" emit.
       // Only auto-show the plan modal for non-grace expired users.
       if (status !== 'trialing' && status !== 'active' && !response.data.is_in_grace_period) {
         this.planModalDismissable = false;
         this.showTrialExpiredModal = true;
       }
     } catch {
       // Silently fail
     }
   },
   ```

6. **Add two new methods** in `methods`:

   ```js
   handleSubscribeFromOverlay() {
     this.planModalDismissable = true;
     this.showTrialExpiredModal = true;
   },

   handleTrialModalClose() {
     this.showTrialExpiredModal = false;
   },
   ```

### Change 2 — `DataRetentionOverlay.vue`: "Subscribe Now" emits event instead of navigating

**File:** `resources/js/components/Payment/DataRetentionOverlay.vue`

1. **Replace the `<router-link>` "Subscribe Now" (lines 47–52)** with a button that emits:

   ```vue
   <button
     @click="$emit('subscribe')"
     class="btn-primary w-full text-center block py-3 text-base font-semibold"
   >
     Subscribe Now
   </button>
   ```

2. **Declare `emits` option** on the component (top-level, next to `name`):

   ```js
   export default {
     name: 'DataRetentionOverlay',
     emits: ['subscribe'],
     setup() { ... },
   };
   ```

### Change 3 — Regression test

Add Vitest component test: `resources/js/tests/unit/AppLayout.checkout-overlays.spec.js` (new file) — mount `AppLayout` with route `/checkout`, stub the `api.get('/payment/trial-status')` response as `{has_subscription: true, status: 'expired', is_in_grace_period: true, payment_enabled: true}`, assert neither `PlanSelectionModal` nor `DataRetentionOverlay` is present in the DOM.

(If we don't have Vitest + route stub infrastructure wired for layouts, fall back to a Pest feature test that smoke-loads the checkout page with an expired-trial factory-built user and asserts no `data-testid="trial-expired-modal"` / `data-testid="data-retention-overlay"` in the HTML. We may need to add those `data-testid` attributes as part of this change.)

## Out of scope (explicit)

- `AppLayout`-above-`<router-view>` refactor.
- Touching backend, DB, migrations, env.
- Changing the `/checkout` route, `CheckSubscription` middleware, or feature gating.
- Changing the upgrade-variant `PlanSelectionModal` (second modal at `AppLayout.vue:96`) — it's for active subscribers.
- Changing the `Settings.vue:163` bug that pushes to `/payment/checkout` (wrong path) — separate bug, not part of this loop.

## Verification plan

### Step 1 — Local (localhost:8000)

1. `php artisan db:seed` (always).
2. Create a local expired+grace user via tinker:
   ```php
   $u = User::factory()->create(['email' => 'localexpired@example.com', 'email_verified_at' => now()]);
   Subscription::create([
     'user_id' => $u->id, 'plan' => 'standard', 'billing_cycle' => 'yearly',
     'status' => 'expired', 'amount' => 10000,
     'trial_ends_at' => now()->subDays(2),
     'data_retention_starts_at' => now()->subDays(1),
   ]);
   ```
3. `./dev.sh` already running. Check local `APP_PAYMENT_ENABLED` — if not `true`, `DataRetentionOverlay` won't render so we can't fully verify grace-period path locally. If it's `false`, still verify the `/checkout` suppression path.
4. Playwright: navigate `/login`, fill `localexpired@example.com` / `password`, fetch verification code from DB, submit.
5. Expect on `/dashboard`: only `DataRetentionOverlay` (if `payment_enabled=true` locally); **no** `PlanSelectionModal` auto-shown.
6. Click Subscribe Now → expect `PlanSelectionModal` opens (dismissable).
7. Click Choose Plan (Student) → expect URL `/checkout?plan=student&cycle=yearly`, Revolut widget visible, **no** overlays.
8. Check console + network: `/api/payment/create-order` returns 200.

### Step 2 — Build for production

```bash
./deploy/fynla-org/build.sh
```

Generate deploy guide by diffing against `main`:

```bash
git diff main..HEAD --name-only -- '*.php' '*.vue' '*.js' '*.css'
```

Write `April23Updates/production/deploy-fix-2026-04-23.md` listing every touched file AND `public/build/` contents. Mirror to `fynlaBrain/April/April23Updates/production/`.

### Step 3 — Production deploy (fynla.org)

1. Upload `public/build/` + the two changed Vue files to `~/www/fynla.org/public_html/`.
2. SSH in, `cache:clear && config:clear && view:clear && route:clear && optimize`.
3. Log in as `bugrepro_expired_2026_04_23@fynla.org` via Playwright.
4. Expect on `/dashboard`: only `DataRetentionOverlay`, no auto PlanSelectionModal.
5. Click Subscribe Now → PlanSelectionModal opens.
6. Click Choose Plan (any) → URL has `?plan=X&cycle=Y`, Revolut widget visible, neither overlay present.
7. In Network panel, confirm `POST /api/payment/create-order` returns 200 with a token.
8. Do **not** complete the live payment. Close browser tab (NOT browser per `feedback_never_close_browser.md`).
9. Tear down test user:
   ```bash
   ssh-fynla php artisan tinker --execute="
   \$u=\App\Models\User::where('email','bugrepro_expired_2026_04_23@fynla.org')->first();
   if(\$u){ \$u->subscription()->delete(); \$u->delete(); echo 'deleted'; } else { echo 'already gone'; }
   "
   ```
10. Monitor `storage/logs/laravel.log` for 15 min.

## Rollback plan

Three file changes, no DB / env / schema touch.

```bash
git revert <hotfix-sha>
./deploy/fynla-org/build.sh
# re-upload public/build/ + AppLayout.vue + DataRetentionOverlay.vue
```

## Estimated time

- Implementation: ~15 min.
- Local test: ~20 min.
- Build + deploy guide: ~5 min.
- Production upload + smoke-test + log watch: ~25 min.
- **Total: ~65 min** from now.
