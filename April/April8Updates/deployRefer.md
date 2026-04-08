# Deploy Guide — Refer a Friend (8 April 2026)

**Branch:** `referFriend` merged to `main`

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
2. Verify "Refer a Friend" button appears in top nav
3. Click it — modal shows referral code and email input
4. Send an invitation — verify success message and "Pending" status in referral list
5. Check email was sent (server logs or mailbox)
6. Open `/register?ref=CODE` in incognito — verify registration works and referral_code is stored
