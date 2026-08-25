---
type: deploy-guide
date: 2026-04-23
branch: prodHotFix
target: fynla.org (production)
env: production
rebuilt: 2026-04-23 (5th build — /pricing Family card matched to plan modal)
---

# Deploy Guide — Checkout Loop + Student Plan + Feature-List + Discount Link + /pricing Parity (2026-04-23)

Five coordinated releases on one branch, rolled out in sequence across the day. Each release rebuilds `public/build/` from the same branch; only R2 changed PHP files (already on prod), so R3/R4/R5 are **frontend-only** — just `public/build/`.

## Releases covered

| # | Scope | Deployed | Contains |
|---|-------|----------|----------|
| R1 | Overlay hotfix | ✅ earlier today | AppLayout.vue + DataRetentionOverlay.vue changes |
| R2 | + Student eligibility filter | ✅ earlier today | R1 + User.php + PaymentController.php + PlanSelectionModal.vue |
| R3 | + Plan-card feature-list adjustments | ✅ earlier today | R2 + PlanSelectionModal.vue displayFeatures |
| R4 | + Remove "Have a discount code?" link from modal | ✅ earlier today | R3 + PlanSelectionModal.vue discount removal |
| **R5** | **+ /pricing Family card matches plan modal** | **⬅ this guide** | R4 + PricingPage.vue bullet additions |

## What changed (vs. `main` — cumulative across R1+R2+R3)

```
 app/Http/Controllers/Api/PaymentController.php     |  9 +++
 app/Models/User.php                                | 17 ++++++
 resources/js/components/Payment/DataRetentionOverlay.vue |  8 ++-
 resources/js/components/Payment/PlanSelectionModal.vue   | 66 +++++++++++++++++++---
 resources/js/layouts/AppLayout.vue                       | 40 +++++++++++--
 5 files changed, 123 insertions(+), 17 deletions(-)
```

## Behaviour summary (all three releases combined)

### A. Expired-trial checkout loop (R1 — live since morning)

1. On `/dashboard` and other auth routes, expired-trial users in grace period see **only** `DataRetentionOverlay` — no stacked `PlanSelectionModal`.
2. `DataRetentionOverlay` "Subscribe Now" is a button that opens `PlanSelectionModal` in place (preserves plan/cycle).
3. On `/checkout`, **neither** overlay renders.

### B. Student plan eligibility (R2 — live since mid-morning)

4. `PlanSelectionModal` hides the **Student** plan for users whose email does not end in `.ac.uk`. Eligible users see all 4 plans.
5. Backend `POST /api/payment/create-order` rejects `plan=student` from non-`.ac.uk` users with **422** and message: _"The Student plan is only available to UK university students. Please use your .ac.uk email address or choose a different plan."_
6. Public `/pricing` unchanged (still shows all 4 plans).
7. `/api/payment/plans` unchanged (still returns all 4).
8. `/checkout` has **no gate** — non-`.ac.uk` users who deep-link to `/checkout?plan=student` see the widget but `create-order` 422s.

### C. Plan-card feature-list adjustments (R3 — live since mid-afternoon)

9. **Standard plan card**: when the Student card is **hidden** (non-`.ac.uk` user), the bullet _"Everything in Student"_ is replaced inline with the Student feature list (Full financial dashboard, Protection module, Savings module, Goal tracking, Investment module, Retirement module) — so the card stands on its own. When Student is visible, the card is unchanged.
10. **Family plan card**: always appends two bullets at the end — _"Parents included"_ and _"Children for free"_ — regardless of Student eligibility.
11. **Student** and **Pro** cards: unchanged.

### D. Remove discount-code link from plan modal (R4 — live since late-afternoon)

12. `PlanSelectionModal` no longer shows the "Have a discount code?" button or its expanded input. The `CheckoutPage` discount-code field (already in place) is the single entry point — avoids two competing input surfaces.
13. Emit payload unchanged in shape — `discountCode: null` always now — so parents (`AppLayout`, `SubscriptionManagement`) continue to work with no edits.

### E. /pricing Family card matches plan modal (R5 — this release)

14. `/pricing` Family card bullets were previously `[Everything in Standard, Family module]`. Now: `[Everything in Standard, Family module, Parents included, Children for free]` — matches the in-app `PlanSelectionModal`. The bullets are hardcoded in `PricingPage.vue` (same pattern as the other plan cards on that page), so the change is purely additive template markup.

## Files to upload for R5

### Built assets (what you actually upload)

```
/Users/CSJ/Desktop/fynla/public/build/
```

(7.7 MB, 312 files) → `~/www/fynla.org/public_html/public/build/`. Overwrite all.

### Backend PHP files

**None.** R2's `User.php` + `PaymentController.php` are already live on prod and unchanged in R3/R4/R5.

### Files NOT to upload

- No `.env`, no `database/`, no `routes/`, no `config/`.
- No migrations.

## Upload steps

### Option A — SiteGround File Manager

1. Navigate to `~/www/fynla.org/public_html/public/build/` and replace with local `public/build/` contents.

### Option B — rsync

```
rsync -avz --delete \
  -e "ssh -p 18765 -i ~/.ssh/production" \
  /Users/CSJ/Desktop/fynla/public/build/ \
  u2783-hrf1k8bpfg02@ssh.fynla.org:~/www/fynla.org/public_html/public/build/
```

## Post-upload: clear caches

```
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize
```

## Smoke test (R5-focused — plus R4 sanity pass)

### Test R5 — /pricing Family card shows the new bullets

1. Open an incognito tab at `https://fynla.org/pricing` (no login required — it's the marketing page).
2. Locate the **Family** card.
3. **Expect four bullets**, in order:
   - Everything in Standard
   - Family module
   - Parents included
   - Children for free

### Test R4 — Discount code link is gone from modal (still valid)

1. Log in as `bugrepro_expired_2026_04_23@fynla.org`. (Claude fetches code.)
2. Dashboard → Subscribe Now → plan modal.
3. **Expect:** no "Have a discount code?" button between the monthly/yearly toggle and the plan cards.
4. Pick Standard → `/checkout?plan=standard&cycle=yearly`.
5. **Expect:** `CheckoutPage` order summary still shows the discount code input (unchanged).

### Legacy tests (still valid post-R5)

Test user 1 (non-`.ac.uk`) should still be on prod: `bugrepro_expired_2026_04_23@fynla.org / Password1!` (subscription was reset to `expired`+grace earlier, may now be in whatever state the last smoke-test left it — ping Claude to reset if needed).

### Test R3-NS — non-`.ac.uk` user, feature list adjusted

1. Log in as `bugrepro_expired_2026_04_23@fynla.org`. (Claude fetches code.)
2. If subscription status isn't `expired`+grace, ask Claude to reset via SSH.
3. Dashboard → `DataRetentionOverlay`. Click `Subscribe Now`.
4. **Expect plan modal with 3 cards**, no Student.
5. **Expect Standard card's bullets:**
   - Full financial dashboard
   - Protection module
   - Savings module
   - Goal tracking
   - Investment module
   - Retirement module
   - Personal Valuables
   - Business
   - Property
   - Letter to Spouse / Expression of Wishes
   - Coordination module
   - *(No "Everything in Student" anywhere.)*
6. **Expect Family card's bullets:**
   - Everything in Standard
   - Family module
   - Parents included
   - Children for free
7. **Expect Pro card** — unchanged from before.

### Test R3-S — `.ac.uk` user, Student visible and Standard reads "Everything in Student"

Create a fresh test user via SSH:

```
php artisan tinker --execute="
\$plan=\App\Models\SubscriptionPlan::where('slug','standard')->first();
\$amt=\$plan->launch_yearly_price ?? \$plan->yearly_price;
\$u=\App\Models\User::create([
  'name'=>'Student Test','first_name'=>'Student','last_name'=>'Test',
  'email'=>'bugrepro_student_r3_2026_04_23@kent.ac.uk',
  'password'=>\Illuminate\Support\Facades\Hash::make('Password1!'),
  'email_verified_at'=>now(),
]);
\App\Models\Subscription::create([
  'user_id'=>\$u->id,'plan'=>'standard','billing_cycle'=>'yearly',
  'status'=>'expired','amount'=>\$amt,
  'trial_ends_at'=>now()->subDays(2),
  'data_retention_starts_at'=>now()->subDays(1),
]);
echo 'uid='.\$u->id;
"
```

1. Log in as `bugrepro_student_r3_2026_04_23@kent.ac.uk`.
2. Dashboard → `DataRetentionOverlay`. Click `Subscribe Now`.
3. **Expect plan modal with 4 cards** including Student.
4. **Expect Standard card's bullets:**
   - Everything in Student
   - Personal Valuables
   - Business
   - Property
   - Letter to Spouse / Expression of Wishes
   - Coordination module
   - *(Note "Everything in Student" stays — it's only swapped when Student is hidden.)*
5. **Expect Family card's bullets:**
   - Everything in Standard
   - Family module
   - Parents included
   - Children for free
6. **Expect Student + Pro cards** — unchanged.

### Tear down Test R3-S user

```
php artisan tinker --execute="
\$u=\App\Models\User::where('email','bugrepro_student_r3_2026_04_23@kent.ac.uk')->first();
if(\$u){ \$u->subscription()->delete(); \$u->delete(); }
"
```

(Leave `bugrepro_expired_2026_04_23@fynla.org` in place per CSJ's earlier instruction.)

## Monitor

```
tail -f storage/logs/laravel.log
```

15 min after upload.

## Rollback

Three file changes total across R1/R2/R3, all in JS + two PHP files. No DB / env.

### Just R3 (revert feature-list logic, keep R1+R2)

```
cd /Users/CSJ/Desktop/fynla
git checkout -- resources/js/components/Payment/PlanSelectionModal.vue
# Re-apply only the R2 filter changes — see the prodHotFix commit messages once committed
# Or: checkout PlanSelectionModal.vue to its R2 state from an earlier build artifact
./deploy/fynla-org/build.sh
# Re-upload public/build/
```

### Full revert (R1+R2+R3)

```
cd /Users/CSJ/Desktop/fynla
git checkout -- resources/js/components/Payment/PlanSelectionModal.vue \
                resources/js/components/Payment/DataRetentionOverlay.vue \
                resources/js/layouts/AppLayout.vue \
                app/Models/User.php \
                app/Http/Controllers/Api/PaymentController.php
./deploy/fynla-org/build.sh
# Re-upload public/build/ + the two PHP files
```

## Branch / commit plan

Once CSJ confirms R3 is verified on prod:

1. Commit cumulative changes on `prodHotFix`:
   ```
   git add app/Models/User.php \
           app/Http/Controllers/Api/PaymentController.php \
           resources/js/components/Payment/DataRetentionOverlay.vue \
           resources/js/components/Payment/PlanSelectionModal.vue \
           resources/js/layouts/AppLayout.vue
   git commit -m "fix(checkout): unblock expired-trial users + gate Student plan + polish plan modal

   Three production fixes on the subscription path, shipped together:

   1. Expired-trial checkout loop: PlanSelectionModal +
      DataRetentionOverlay were stacking on /checkout, blocking the
      Revolut widget. Suppressed on /checkout; DataRetentionOverlay is
      the primary surface on /dashboard and its 'Subscribe Now' button
      opens PlanSelectionModal in place (preserves plan/cycle).

   2. Student plan eligibility: filtered to UK university students
      (.ac.uk email). Frontend hides the Student card and backend
      rejects POST /api/payment/create-order with plan=student from
      ineligible users (422). Public /pricing unchanged.

   3. Plan-card copy polish: Standard card inlines the Student feature
      list when Student is hidden (so the card stands alone). Family
      card always shows 'Parents included' + 'Children for free'.

   Root cause + repro: April/April23Updates/production/findings.md
   Deploy notes: April/April23Updates/production/deploy-fix-2026-04-23.md"
   ```
2. Push `prodHotFix` to origin.
3. Open PR `prodHotFix` → `main` (hotfix exception).
4. After merge, delete `prodHotFix`.

## Vault mirror

```
/Users/CSJ/Desktop/fynlaBrain/April/April23Updates/production/deploy-fix-2026-04-23.md
```

## Sign-off checklist

### R1 — deployed earlier
- [x] Dashboard for expired-trial user shows only DataRetentionOverlay
- [x] Subscribe Now opens PlanSelectionModal (dismissable)
- [x] `/checkout` clean — Revolut widget visible, `create-order` 200

### R2 — deployed earlier
- [x] Non-`.ac.uk` user sees 3 plan cards (Student hidden)
- [x] `POST /api/payment/create-order {plan:student}` returns 422 for non-`.ac.uk`
- [x] `.ac.uk` user sees all 4 plan cards + Student checkout reaches widget + 200

### R3 — deployed earlier
- [x] Test R3-NS: Standard card bullets match the 11-item list
- [x] Test R3-NS: Family card shows Parents included + Children for free
- [x] Test R3-S: Standard card still says "Everything in Student"
- [x] Test R3-S: Family card shows Parents included + Children for free

### R4 — deployed earlier
- [x] Test R4: "Have a discount code?" button absent from plan modal
- [x] CheckoutPage still shows discount code input (unchanged)

### R5 — this release
- [ ] `public/build/` re-uploaded to `~/www/fynla.org/public_html/public/build/`
- [ ] Cache/config/view/route cleared + `optimize` run
- [ ] Test R5: `/pricing` Family card shows all 4 bullets (Everything in Standard, Family module, Parents included, Children for free)
- [ ] `storage/logs/laravel.log` watched for 15 min
- [ ] Vault mirror refreshed
- [ ] Cumulative commit + PR opened `prodHotFix → main`
