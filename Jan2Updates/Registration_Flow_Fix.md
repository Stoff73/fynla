# Registration Flow Fix - January 2, 2026

## Issues Reported

User reported multiple bugs with the email verification registration flow:

1. **Email not being sent** - Verification emails not arriving
2. **Modal disappears on outside click** - Clicking outside the verification modal closes it and it cannot be reopened
3. **Timer too short** - 60 seconds is not enough time to check email and enter code
4. **"Email already taken" error** - After cancelling registration (before verification), the email is blocked from re-registering

## Root Cause Analysis

The original flow created a `User` record immediately on registration, before email verification:

```
Old Flow:
1. User submits registration form
2. User record created in database (email now "taken")
3. Verification code sent to email
4. If user cancels or code expires, user record remains (unverified)
5. User cannot re-register with same email
```

## Solution: Pending Registration Pattern

New flow uses a separate `pending_registrations` table:

```
New Flow:
1. User submits registration form
2. PendingRegistration record created (or updated if exists)
3. Verification code sent to email
4. User can cancel and start over (pending record gets overwritten)
5. Only when code is verified does the actual User record get created
6. PendingRegistration record is deleted after successful verification
```

## Files Changed

### New Files
- `database/migrations/2026_01_02_171718_create_pending_registrations_table.php`
- `app/Models/PendingRegistration.php`

### Modified Files
- `app/Http/Controllers/Api/AuthController.php`
  - `register()` - Creates PendingRegistration instead of User
  - `verifyCode()` - Creates User from PendingRegistration when verified
  - `resendCode()` - Handles pending registration resend
- `resources/js/components/Auth/VerificationCodeModal.vue`
  - Removed timer (no code expiry)
  - Removed backdrop click handler (modal stays open)
  - Added `pendingId` prop for registration type
- `resources/js/views/Register.vue`
  - Uses `pending_id` instead of `user_id`

## Database Schema

```sql
CREATE TABLE pending_registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE,
    first_name VARCHAR(255),
    middle_name VARCHAR(255) NULL,
    surname VARCHAR(255),
    password VARCHAR(255),  -- Hashed
    verification_code VARCHAR(6),
    registration_source VARCHAR(255) NULL,  -- 'preview' or null
    preview_persona_id VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

## Key Features

| Feature | Implementation |
|---------|---------------|
| No timer | Verification codes don't expire |
| Re-registration | `PendingRegistration::createOrUpdate()` overwrites existing |
| Modal stays open | Removed `@click="handleClose"` from backdrop |
| Clean slate | User record only created after verification |

## Deployment Steps

### Files to Upload (via SiteGround File Manager)

**Backend Files:**
- `app/Http/Controllers/Api/AuthController.php`
- `app/Models/PendingRegistration.php` (NEW)
- `app/Mail/VerificationCode.php`
- `database/migrations/2026_01_02_171718_create_pending_registrations_table.php` (NEW)
- `resources/views/emails/verification-code.blade.php`

**Frontend Files:**
- `resources/js/components/Auth/VerificationCodeModal.vue`
- `resources/js/views/Register.vue`
- `public/build/` (entire folder - contains compiled JS/CSS)

### After Upload

Run migration via SSH or SiteGround's Terminal:
```bash
cd ~/public_html
php artisan migrate
```

## Email Debugging

### Issue Found: SMTP Authentication Failure

The logs show:
```
Failed to authenticate on SMTP server with username "noreply@fynla.org"
535 Incorrect authentication data
```

### Possible Causes

1. **Wrong password** - Check `.env` MAIL_PASSWORD value on production
2. **Server blocking** - Some SMTP servers block connections from certain IPs
3. **Account locked** - Too many failed attempts may have locked the account
4. **SSL/TLS mismatch** - Try port 587 with TLS instead of 465 with SSL

### Solutions

**For Local Development:**
```bash
# Use log mailer to see emails in storage/logs/laravel.log
MAIL_MAILER=log
```

**For Production (SiteGround):**
1. Login to SiteGround Site Tools
2. Go to Email > Email Accounts
3. Verify `noreply@fynla.org` exists and reset password if needed
4. Update `.env` on production with correct password
5. Try both port configurations:
   ```
   # Option 1: SSL (port 465)
   MAIL_PORT=465
   MAIL_ENCRYPTION=ssl

   # Option 2: TLS (port 587)
   MAIL_PORT=587
   MAIL_ENCRYPTION=tls
   ```

**Test SMTP Credentials:**
```bash
# On production server, test with tinker
php artisan tinker
>>> Mail::raw('Test email', function($m) { $m->to('test@example.com')->subject('Test'); });
```

**Check Logs:**
```bash
tail -100 storage/logs/laravel.log | grep -i "mail\|smtp\|verification"
```

## Testing

```bash
# Test registration API
curl -X POST "http://localhost:8000/api/auth/register" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "first_name": "Test",
    "surname": "User",
    "email": "test@example.com",
    "password": "Test@123456",
    "password_confirmation": "Test@123456"
  }'

# Check pending registrations table
SELECT * FROM pending_registrations;
```

## Commit

`7ff4848` - feat: Implement pending registration flow for email verification
