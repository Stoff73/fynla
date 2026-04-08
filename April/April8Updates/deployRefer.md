# Deploy Guide — Refer a Friend (8 April 2026)

**Branch:** `referFriend` merged to `main`
**Commits:** 11 (10 feature + 1 docs)

---

## Pre-deploy: Build locally

```bash
git checkout main && git pull
./deploy/fynla-org/build.sh
```

---

## Files to upload via SiteGround File Manager

### New PHP files

```text
app/Http/Controllers/Api/ReferralController.php
app/Mail/ReferralInvitationEmail.php
app/Models/Referral.php
app/Services/Payment/ReferralService.php
database/migrations/2026_04_08_150001_add_referral_columns_to_users_table.php
database/migrations/2026_04_08_150002_create_referrals_table.php
database/migrations/2026_04_08_150003_add_referral_code_to_pending_registrations_table.php
```

### Modified PHP files

```text
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/PaymentController.php
app/Models/PendingRegistration.php
app/Models/User.php
routes/api.php
```

### New Blade template

```text
resources/views/emails/referral-invitation.blade.php
```

### Frontend build (upload entire directory)

```text
public/build/
```

---

## SSH commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migrations (3 new: users columns, referrals table, pending_registrations column)
php artisan migrate

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize

# Verify routes
php artisan route:list --path=referral
```

---

## No new environment variables required

---

## Post-deploy verification

1. Log in as a paid subscriber
2. Verify "Refer a Friend" button appears in top nav (only for active subscribers, not trial)
3. Click it — modal shows referral code and email input
4. Send an invitation — verify success message and "Pending" status in referral list
5. Try sending to your own email — should reject with "cannot refer yourself"
6. Try sending to the same email again — should reject with "already invited"
7. Check email was received (subject: "[Name] thinks you'd like Fynla")
8. Open the register link from the email in incognito
9. Register the new user — verify `referred_by_code` is stored on the user
10. New user goes to checkout and pays
11. **After payment:** verify BOTH subscriptions were extended:
    - Monthly purchase: +1 week for both
    - Annual purchase: +1 month for both
12. Referrer opens "Refer a Friend" modal — referral should now show "Subscribed" status

---

## How it works

1. Paid subscriber clicks "Refer a Friend" in top nav
2. Modal shows their unique code (FYN-XXXXX) and an email input
3. Fynla emails the friend with the code + register link (`/register?ref=CODE`)
4. Friend registers — referral code stored on their user record via `pending_registrations`
5. Friend goes to checkout and pays — `confirmPayment()` calls `ReferralService::applyReferralBonus()`
6. Both the referrer's and referee's `current_period_end` are extended
7. Referral record marked as `converted` with `bonus_applied = true`

---

## Bugs fixed during development

1. **ReferralModal not registered** in Navbar `components` — imported but not listed, modal didn't render
2. **`PendingRegistration::createOrUpdate()`** didn't pass `referral_code` — code was lost during registration
3. **`addMonth()` on null** — referrer's `current_period_end` could be null if subscription model was stale; added null guard
4. **`catch (\Exception)` didn't catch PHP `Error`** — changed to `catch (\Throwable)` so referral bonus crash doesn't kill the entire `confirmPayment()` response
5. **Stale model data** — both referee and referrer subscriptions need `load('subscription')` before extending, since `confirmPayment()` updates the DB in a transaction but the in-memory models are stale
