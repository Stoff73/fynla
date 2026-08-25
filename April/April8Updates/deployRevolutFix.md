# Deploy Guide — Revolut Payment Fix

**Date:** 8 April 2026
**Status:** DEPLOYED to production
**Issue:** Family plan payments failing — `users.plan` enum missing `'family'`, causing transaction rollback on payment confirmation. Money taken by Revolut but subscription never activated, no emails sent.

---

## Production Already Fixed (via SSH)

These changes were applied directly to production during this session:

1. `users.plan` enum updated: `ALTER TABLE users MODIFY COLUMN plan ENUM('free','student','standard','family','pro') NOT NULL DEFAULT 'free'`
2. User 545 (brett@fynla.org) activated: payment 26 completed, subscription active (Family yearly), invoice FYN-INV-000005 generated, both emails sent
3. User 444 (chris@fynla.org) plan corrected: `users.plan` set to `pro` (was `standard`, subscription was already `pro/active`)
4. 5 stale pending payments soft-deleted (IDs: 5, 6, 20, 24, 25)

---

## Files to Upload

### PHP Files

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/PaymentController.php` | Soft-deletes prior pending payment for same user/plan/cycle before creating new order (prevents orphaned payments on discount code widget reload) |
| `database/migrations/2026_04_09_000001_add_family_to_users_plan_enum.php` | Adds `'family'` to `users.plan` enum (already applied on production via direct ALTER) |

### Upload Paths

```
app/Http/Controllers/Api/PaymentController.php
  → ~/www/fynla.org/public_html/app/Http/Controllers/Api/PaymentController.php

database/migrations/2026_04_09_000001_add_family_to_users_plan_enum.php
  → ~/www/fynla.org/public_html/database/migrations/2026_04_09_000001_add_family_to_users_plan_enum.php
```

---

## SSH Commands After Upload

```bash
cd ~/www/fynla.org/public_html
php artisan migrate
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize
```

Note: The migration will report "Nothing to migrate" if the enum ALTER was already applied directly (which it was). This is safe — the migration file is needed so the migration table stays in sync.

---

## No Build Required

No frontend changes. No `./deploy/fynla-org/build.sh` needed.

---

## Verification

After upload and cache clear:

1. Check migration ran: `php artisan migrate:status | grep family`
2. Confirm enum: `php artisan tinker --execute="echo json_encode(DB::select(\"SHOW COLUMNS FROM users WHERE Field = 'plan'\"));"`
3. Confirm user 545 still active: `php artisan tinker --execute="echo json_encode(DB::table('users')->where('id',545)->select('plan')->first());"`

---

## Root Cause

Migration `2026_03_30_000002_add_family_to_subscriptions_plan_enum.php` added `'family'` to `subscriptions.plan` but never updated `users.plan`. When `confirmPayment()` ran `$user->update(['plan' => 'family'])`, MySQL threw `Data truncated for column 'plan'`, the entire DB transaction rolled back (undoing subscription activation too), and no emails were sent. Revolut had already charged the customer.
