# Verification Code Expiry Fix - January 13, 2026

## Issue
Login verification codes were expiring after 60 seconds, causing "Invalid or expired verification code" errors when users took longer than a minute to check their email and enter the code.

## Changes Made

### File: `app/Models/EmailVerificationCode.php`

**1. Extended expiry time (Lines 98 & 116)**
- **Before:** `'expires_at' => Carbon::now()->addSeconds(60),`
- **After:** `'expires_at' => Carbon::now()->addYear(), // No practical expiry`

**2. Removed expiry check in findValidCode (Line 133)**
- **Before:** Query included `->where('expires_at', '>', now())`
- **After:** Expiry check removed - codes remain valid until used or a new one is generated

## Files to Upload

Upload to production server:
```
app/Models/EmailVerificationCode.php
```

## SSH & Deployment Instructions

### 1. Connect to Server
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
```

### 2. Navigate to Project
```bash
cd ~/www/fynla.org/public_html
```

### 3. Upload File (from local machine)
```bash
scp -P 18765 -i ~/.ssh/production app/Models/EmailVerificationCode.php u2783-hrf1k8bpfg02@ssh.fynla.org:~/www/fynla.org/public_html/app/Models/
```

### 4. Clear Caches (on server)
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

Or use the combined command:
```bash
php artisan optimize:clear
```

## Verification
After deployment, test login flow:
1. Enter valid credentials
2. Wait for verification code email
3. Enter code (even after several minutes)
4. Should successfully log in without "expired" error

## Commit
```
fix: Remove 60-second expiry from email verification codes
```
