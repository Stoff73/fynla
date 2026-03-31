# Subscription Task List

**Date:** 31 March 2026
**Execution:** Sequential, no sub-agents. Done by me, checked by me, tested by me.
**Status:** Sections 1-4 COMPLETE. Section 5 (deploy) pending.

---

## Section 1: Clean Up — COMPLETE

### 1.1 Remove Stale Worktree
- [x] Verified admin analytics code already on main (commit `cc484aa`)
- [x] Removed worktree directory + ran `git worktree prune`
- [x] Deleted orphan branch: `git branch -D worktree-agent-a9856e54`
- [x] Verified: `git worktree list` shows only main worktree

### 1.2 Fix Navbar Progress Bar
**File:** `resources/js/components/Navbar.vue:38`
- [x] Changed `(100 - trialData.progress) + '%'` to `trialData.progress + '%'`
- [x] Browser verified: trial user progress bar fills correctly

### 1.3 Countdown Timer
- [x] NOT TOUCHED — April 9 2026 countdown timer remains

---

## Section 2: Backend — Upgrade Endpoint — COMPLETE

### 2.1 Migration
**File:** `database/migrations/2026_03_31_144649_add_upgrade_from_plan_to_payments_table.php`
- [x] Added `upgrade_from_plan` nullable string column to `payments` table
- [x] Migration ran successfully

### 2.2 Update Subscription Model Fillable
**File:** `app/Models/Subscription.php`
- [x] Added `'status'` and `'revolut_order_id'` to `$fillable` array

### 2.3 Update Payment Model Fillable
**File:** `app/Models/Payment.php`
- [x] Added `'upgrade_from_plan'` to `$fillable` array

### 2.4 Add upgradeSubscription Method
**File:** `app/Http/Controllers/Api/PaymentController.php`
- [x] Added `PLAN_ORDER` constant: `['student', 'standard', 'family', 'pro']`
- [x] Added `upgradeSubscription(Request $request): JsonResponse`
- [x] Validates plan, checks active subscription, checks higher tier
- [x] Proration: yearly = `(price_diff / 12) * months_remaining`, monthly = full diff
- [x] Creates Payment with `upgrade_from_plan` set
- [x] Creates Revolut order, returns `{ token, order_id, upgrade_amount, new_plan, months_remaining }`

### 2.5 Modify confirmPayment for Upgrades
**File:** `app/Http/Controllers/Api/PaymentController.php`
- [x] Detects upgrade via `$payment->upgrade_from_plan`
- [x] Upgrade: keeps `current_period_start` and `current_period_end` unchanged
- [x] Non-upgrade: sets fresh period dates (existing behaviour)

### 2.6 Add Route
**File:** `routes/api.php`
- [x] Added `POST /api/payment/upgrade` with `throttle:10,1`

### 2.7 Verify Backend
- [x] `php artisan route:list --path=payment/upgrade` — route shows
- [x] `php -l PaymentController.php` — no syntax errors
- [x] `php artisan db:seed` — all seeders pass

---

## Section 3: Frontend — Upgrade Flow — COMPLETE

### 3.1 PlanSelectionModal — Emit Upgrade Flag
**File:** `resources/js/components/Payment/PlanSelectionModal.vue`
- [x] `handleSelect()` emits `{ plan, billingCycle, isUpgrade: !!this.currentPlan }`

### 3.2 AppLayout — Route Upgrades
**File:** `resources/js/layouts/AppLayout.vue`
- [x] `handlePlanSelect` routes with `&upgrade=true` when `isUpgrade` is true

### 3.3 SubscriptionManagement — Add Upgrade + Pass currentPlan
**File:** `resources/js/components/UserProfile/SubscriptionManagement.vue`
- [x] Added `currentPlanForModal` computed (returns plan slug if active, else null)
- [x] PlanSelectionModal receives `:current-plan="currentPlanForModal"`
- [x] Added "Upgrade" button (visible only for active non-pro subscribers)
- [x] `handlePlanSelect` routes with `&upgrade=true` when upgrading

### 3.4 CheckoutPage — Handle Upgrade Mode
**File:** `resources/js/views/Auth/CheckoutPage.vue`
- [x] Added `isUpgrade` computed from `route.query.upgrade === 'true'`
- [x] Upgrade mode calls `POST /api/payment/upgrade` instead of `create-order`
- [x] Stores `upgradeAmount` and `monthsRemaining` from backend
- [x] Order summary shows "Upgrade Summary" with prorated cost + months remaining
- [x] Success modal says "Upgrade Successful"
- [x] Heading says "Upgrade Payment" instead of "Payment Method"

### 3.5 Verify Frontend Compiles
- [x] No compile errors in Vite
- [x] No red errors in browser console (only pre-existing unrelated error)

---

## Section 4: Testing — COMPLETE

### 4.1 Pest Tests — 9/9 PASS
**File:** `tests/Feature/Payment/UpgradeSubscriptionTest.php`
- [x] Yearly Standard→Pro, ~6 months in — correct prorated amount
- [x] Yearly Standard→Family, 3 months in — correct prorated amount
- [x] Monthly Standard→Pro — full month price difference
- [x] Cannot upgrade to same plan — 422
- [x] Cannot upgrade to lower plan — 422
- [x] Cannot upgrade without active subscription — 403
- [x] Unauthenticated requests — 401
- [x] confirmPayment keeps period dates for upgrade payments
- [x] confirmPayment sets new period dates for non-upgrade payments

### 4.2 Seed Database
- [x] `php artisan db:seed` — all seeders pass

### 4.3 Browser Testing (Playwright) — ALL PASS

#### Trial User (john@example.com)
- [x] Logged in, entered verification code from DB
- [x] "Choose a Plan" visible in top nav with trial countdown
- [x] "Choose a Plan" visible in sidebar
- [x] Progress bar fills correctly (not inverted)
- [x] Clicked "Choose a Plan" → modal opened
- [x] All 4 plans visible (Student £30, Standard £100, Family £150, Pro £200)
- [x] Modal IS dismissable (Close button + Cancel button present)
- [x] Selected Standard → clicked Continue → routed to `/checkout?plan=standard&cycle=yearly` (no upgrade param)
- [x] Checkout shows: Plan = Standard, Total = £100.00, heading = "Payment Method"
- [x] Revolut widget loaded (Revolut Pay, Card, Google Pay)

#### Active Standard User (set via tinker: 3 months in, 9 months remaining)
- [x] Dashboard: "Upgrade Now" in nav (no trial info, no "Choose a Plan")
- [x] "Upgrade Now" in sidebar
- [x] Profile → Subscription tab shows: Plan = Standard, Billing = yearly, Amount = £100.00, Next Renewal = 31 December 2026, Active badge
- [x] "Upgrade" button visible
- [x] Clicked Upgrade → modal opened with title "Upgrade Your Plan"
- [x] Only Family + Pro shown (Standard filtered out)
- [x] Selected Pro → clicked Continue → routed to `/checkout?plan=pro&cycle=yearly&upgrade=true`
- [x] Checkout shows: "Upgrade Summary", Plan = Pro, Prorated = 9 months remaining, Upgrade Cost = £74.97
- [x] "Prorated difference until your next renewal" text shown
- [x] Heading = "Upgrade Payment"

#### Active Pro User (set via tinker)
- [x] Dashboard: NO "Upgrade Now" in nav
- [x] NO upgrade link in sidebar — only "Sign Out"
- [x] Profile → Subscription tab: NO "Upgrade" button — only "Cancel Subscription"

#### Expired User (set via tinker: status=expired)
- [x] Dashboard: non-dismissable modal "Your Trial Has Ended" appeared immediately
- [x] NO close button on modal
- [x] NO cancel button on modal
- [x] Only buttons: Monthly, Yearly, Continue — user must choose a plan

#### Cross-check
- [x] Trial: nav = "Choose a Plan", sidebar = "Choose a Plan"
- [x] Active non-pro: nav = "Upgrade Now", sidebar = "Upgrade Now"
- [x] Active Pro: nav = neither, sidebar = hidden
- [x] Countdown timer (April 9 2026) present and ticking in all states

---

## Section 5: Deployment — NOT YET DONE

### 5.1 Build
- [ ] `./deploy/fynla-org/build.sh`

### 5.2 Deploy Guide
- [ ] Generate file list from `git diff` (NEVER from memory)
- [ ] Write to `March/March31Updates/deploySubscriptionUpgrade.md`
- [ ] Include: all changed files, migration command, cache clear commands

### 5.3 Upload
- [ ] User uploads files via SiteGround File Manager

---

## Files Modified

| File | Change |
|------|--------|
| `resources/js/components/Navbar.vue` | Fix progress bar inversion (1 line) |
| `resources/js/components/Payment/PlanSelectionModal.vue` | Emit `isUpgrade` flag |
| `resources/js/layouts/AppLayout.vue` | Route upgrades with `&upgrade=true` |
| `resources/js/components/UserProfile/SubscriptionManagement.vue` | Upgrade button + currentPlan prop + upgrade routing |
| `resources/js/views/Auth/CheckoutPage.vue` | Upgrade mode: different endpoint, summary, headings |
| `app/Http/Controllers/Api/PaymentController.php` | `upgradeSubscription()` + `confirmPayment()` upgrade handling |
| `app/Models/Payment.php` | `upgrade_from_plan` fillable |
| `app/Models/Subscription.php` | `status` + `revolut_order_id` fillable |
| `routes/api.php` | `/payment/upgrade` route |
| `database/migrations/2026_03_31_144649_*` | `upgrade_from_plan` column on payments |
| `tests/Feature/Payment/UpgradeSubscriptionTest.php` | 9 new tests |
