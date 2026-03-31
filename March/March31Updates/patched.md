# Production Patches — Session 20 (31 March 2026)

These patches were applied directly on production because the SSH upload tool failed. The local codebase already had these changes (developed locally in session 20). This document records the sync verification performed at the end of the session.

---

## Sync Verification (31 March 2026)

### MD5 Hash Comparison

| File | Local | Production | Match |
|------|-------|------------|-------|
| `app/Http/Controllers/Api/PaymentController.php` | `fe85ce0f7d2c913636d94fff89a1cb16` | `fe85ce0f7d2c913636d94fff89a1cb16` | EXACT |
| `app/Models/Payment.php` | `fad034dec6de8b187d18dba513b3d319` | `fad034dec6de8b187d18dba513b3d319` | EXACT |
| `app/Models/Subscription.php` | `f2a605786e3ca9afae6827f37176cba5` | `c567bd36cda8ed5700cdab542e8efb0f` | ORDERING ONLY |
| `routes/api.php` | `16ec5fa1b2ba150e2aaa2b15707b911c` | `16ec5fa1b2ba150e2aaa2b15707b911c` | EXACT |

### Line Count Comparison

| File | Local | Production | Match |
|------|-------|------------|-------|
| `PaymentController.php` | 630 | 630 | YES |
| `Payment.php` | 43 | 43 | YES |
| `Subscription.php` | 136 | 136 | YES |
| `routes/api.php` | 1180 | 1180 | YES |

### Subscription.php Ordering Difference (cosmetic only)

**Local** (clean ordering — `status` and `revolut_order_id` in logical position):
```php
protected $fillable = [
    'user_id',
    'plan',
    'billing_cycle',
    'status',                    // line 22
    'amount',
    'trial_started_at',
    'trial_ends_at',
    'current_period_start',
    'current_period_end',
    'revolut_order_id',          // line 28
    'cancelled_at',
    'cancellation_reason',
    'data_retention_starts_at',
];
```

**Production** (sed-patched — `status` and `revolut_order_id` inserted before `data_retention_starts_at`):
```php
protected $fillable = [
    'user_id',
    'plan',
    'billing_cycle',
    'amount',
    'trial_started_at',
    'trial_ends_at',
    'current_period_start',
    'current_period_end',
    'cancelled_at',
    'cancellation_reason',
    'status',                    // line 29
    'revolut_order_id',          // line 30
    'data_retention_starts_at',
];
```

**Impact:** None. `$fillable` ordering does not affect functionality. Both contain identical fields. No action needed.

---

## Patch 1: PaymentController — PLAN_ORDER Constant

**File:** `app/Http/Controllers/Api/PaymentController.php`
**Location:** After `use SanitizedErrorResponse;` (line 28)

```php
private const PLAN_ORDER = ['student', 'standard', 'family', 'pro'];
```

**Local:** Present. **Production:** Present. **Sync:** EXACT MATCH.

---

## Patch 2: PaymentController — upgradeSubscription Method

**File:** `app/Http/Controllers/Api/PaymentController.php`
**Location:** Between `confirmPayment()` (line 156) and `trialStatus()` (line 403 on production)

Added the full `upgradeSubscription()` method (116 lines). Key logic:
- Validates user has active subscription
- Validates new plan is higher tier than current (using `PLAN_ORDER`)
- Calculates proration: yearly = `(price_diff / 12) * months_remaining`, monthly = full price diff
- Creates Payment record with `upgrade_from_plan` set to current plan slug
- Creates Revolut order for the prorated amount
- Returns token + order_id + upgrade_amount + months_remaining

**Local:** Present. **Production:** Present. **Sync:** EXACT MATCH.

---

## Patch 3: PaymentController — confirmPayment Upgrade Logic

**File:** `app/Http/Controllers/Api/PaymentController.php`
**Location:** Inside the `DB::transaction` closure in `confirmPayment()`

Changed the subscription update logic to:
1. Detect upgrades via `$isUpgrade = ! empty($payment->upgrade_from_plan)`
2. Calculate `$fullPrice` from `SubscriptionPlan` (launch price or standard price)
3. Build `$subscriptionUpdate` array instead of inline update
4. Only set `current_period_start` and `current_period_end` for new subscriptions, not upgrades

**Why:** Upgrades must keep the existing billing period dates (user already paid for the period).

**Local:** Present. **Production:** Present. **Sync:** EXACT MATCH.

---

## Patch 4: Payment Model — upgrade_from_plan fillable

**File:** `app/Models/Payment.php`
**Location:** `$fillable` array, after `'billing_cycle'`

Added `'upgrade_from_plan'`.

**Local:** Present. **Production:** Present. **Sync:** EXACT MATCH.

---

## Patch 5: Subscription Model — status and revolut_order_id fillable

**File:** `app/Models/Subscription.php`
**Location:** `$fillable` array

Added `'status'` and `'revolut_order_id'` to the fillable array.

**Why:** `confirmPayment()` does `$subscription->update(['status' => 'active', 'revolut_order_id' => $orderId, ...])`. Without these in `$fillable`, the updates silently fail.

**Local:** Present (lines 22, 28 — clean ordering). **Production:** Present (lines 29-30 — sed-patched ordering). **Sync:** FUNCTIONALLY IDENTICAL. Ordering difference is cosmetic.

---

## Patch 6: routes/api.php — Upgrade Route

**File:** `routes/api.php`
**Location:** Inside the `payment` route group, after the `/confirm` route (line 985)

```php
Route::post('/upgrade', [\App\Http\Controllers\Api\PaymentController::class, 'upgradeSubscription'])->middleware('throttle:10,1');
```

**Local:** Present. **Production:** Present. **Sync:** EXACT MATCH.

---

## Patch 7: Migration — upgrade_from_plan column

**File:** `database/migrations/2026_03_31_144649_add_upgrade_from_plan_to_payments_table.php`

Adds nullable `upgrade_from_plan` string column to `payments` table after `billing_cycle`.

**Local:** Present, migrated. **Production:** Present, migrated. **Sync:** BOTH MIGRATED.

---

## Summary

| Patch | Local | Production | Sync Status |
|-------|-------|------------|-------------|
| 1. PLAN_ORDER constant | Present | Present | EXACT |
| 2. upgradeSubscription method | Present | Present | EXACT |
| 3. confirmPayment upgrade logic | Present | Present | EXACT |
| 4. Payment upgrade_from_plan fillable | Present | Present | EXACT |
| 5. Subscription status/revolut_order_id fillable | Present | Present | FUNCTIONALLY IDENTICAL (ordering differs) |
| 6. Upgrade route | Present | Present | EXACT |
| 7. Migration | Migrated | Migrated | BOTH DONE |

**No local changes were needed.** All patches were already present in local code. The only difference is cosmetic ordering of `$fillable` fields in `Subscription.php`.
