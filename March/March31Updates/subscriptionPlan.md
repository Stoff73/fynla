# Subscription System — Fix, Complete & Deploy

**Date:** 31 March 2026
**Status:** IMPLEMENTED — Sections 1-4 complete, tested (9/9 Pest, full Playwright). Section 5 (deploy) pending.
**Scope:** Fix broken subscription logic, implement upgrade proration, deploy

---

## What Exists vs What Should Exist

### The Correct Flow (as designed)

1. **Register** → Pro 7-day trial → full system access
2. **Trial expires** → user logs in → **non-dismissable** modal on dashboard → choose a plan → Revolut checkout
3. **During trial** → "Choose a Plan" in top nav + sidebar → dismissable modal → Revolut checkout
4. **After buying a plan** → no trial timer, "Choose a Plan" becomes "Upgrade Now" (except Pro)
5. **Pro subscribers** → no upgrade buttons anywhere
6. **User profile subscription tab** → plan name, renewal date, renewal amount
7. **Upgrade flow** → Standard sees Family + Pro, Family sees Pro only → prorated amount charged

### What's Currently Broken

| Issue | File | What's Wrong |
|-------|------|-------------|
| Progress bar inverted | `Navbar.vue:38` | `(100 - progress)` should be just `progress` — bar starts full and empties instead of filling |
| No upgrade proration | `PaymentController.php` | No backend endpoint for mid-cycle upgrades. User clicking "Upgrade" goes to full-price checkout |
| SubscriptionManagement doesn't filter plans | `SubscriptionManagement.vue:317` | Modal shown without `currentPlan` prop — shows all 4 plans instead of just higher tiers |
| No Upgrade button on subscription tab | `SubscriptionManagement.vue` | Active state only shows "Cancel Subscription" — no way to upgrade from the profile page |
| confirmPayment resets period dates | `PaymentController.php:243` | Sets `current_period_start => now()` on every payment — upgrades should keep existing period |
| Stale worktree | `.claude/worktrees/agent-a9856e54` | 33 commits behind main, has uncommitted admin analytics code — fragmented, needs cleanup |

### What's Working Correctly

- Trial detection and countdown display
- Non-dismissable modal for expired trials
- "Choose a Plan" button in nav (trial users)
- "Upgrade Now" button in nav (active non-pro users)
- SideMenu context-aware buttons (trial = "Choose a Plan", active = "Upgrade Now", pro = hidden)
- PlanSelectionModal plan filtering logic (when `currentPlan` prop is passed)
- Revolut checkout flow with retry logic
- Subscription cancellation
- Billing history display
- Countdown timer (April 9 2026 12:00) — intentional, DO NOT REMOVE

---

## Proration Formula

**User's stated formula:**
> Take the difference from the current plan up to the new plan, divided by 12, and minus the months that have used the plan for.

### Yearly Billing
```
annual_diff = new_plan_yearly_price - current_plan_yearly_price
monthly_diff = annual_diff / 12
months_used = full months since current_period_start
months_remaining = 12 - months_used (minimum 1)
upgrade_cost = monthly_diff * months_remaining
```

**Example:** Standard yearly (10,000p) → Pro yearly (20,000p), 4 months in
- annual_diff = 20,000 - 10,000 = 10,000p
- monthly_diff = 10,000 / 12 = 833p
- months_remaining = 12 - 4 = 8
- upgrade_cost = 833 * 8 = 6,664p (£66.64)

### Monthly Billing
```
upgrade_cost = new_plan_monthly_price - current_plan_monthly_price
```
Full month difference — takes effect this billing cycle.

**Prices use launch pricing when available** (via `getLaunchPriceForCycle()`).

---

## Architecture

### New Backend Endpoint
```
POST /api/payment/upgrade
  Body: { plan: 'pro', billing_cycle: 'yearly' }
  Auth: Required (auth:sanctum)
  Validates: plan is higher tier, user has active subscription
  Returns: { token, order_id, upgrade_amount, new_plan, months_remaining }
```

### Upgrade Payment Flow
```
User clicks "Upgrade Now" (nav/sidebar/subscription tab)
  → PlanSelectionModal opens (filtered to higher plans)
  → User selects plan + billing cycle
  → Route to /checkout?plan=X&cycle=Y&upgrade=true
  → CheckoutPage calls POST /api/payment/upgrade (not create-order)
  → Backend calculates prorated amount, creates Revolut order
  → User pays prorated amount via Revolut widget
  → onSuccess calls POST /api/payment/confirm
  → Backend detects upgrade (upgrade_from_plan field on Payment)
  → Updates plan but KEEPS current period dates unchanged
  → User returned to dashboard with upgraded plan
```

### Data Flow
```
Subscription model:
  plan: 'standard' → 'pro'     (updated)
  amount: 10000 → 20000        (updated to new plan's full price for next renewal)
  current_period_start: unchanged
  current_period_end: unchanged
  billing_cycle: unchanged

Payment record:
  plan_slug: 'pro'              (new plan)
  upgrade_from_plan: 'standard' (old plan — marks this as upgrade)
  amount: 6664                  (prorated amount charged)

User model:
  plan: 'pro'                   (denormalised, updated)
```
