# Deployment Notes - January 27, 2026

---

## UI Fix: Login Timeout Message - Solid Orange Colour

**Branch:** main

**Status:** Ready for deployment

### Description

Changed the session timeout message on the login page from mustard/amber (pastel) to a solid orange background with white text for better visibility.

### Changes Made

Updated the inactivity message styling:

| Element | Before (Amber/Mustard) | After (Solid Orange) |
|---------|------------------------|----------------------|
| Background | `bg-amber-50` | `bg-orange-500` |
| Border | `border-amber-200` | `border-orange-600` |
| Icon | `text-amber-600` | `text-white` |
| Text | `text-amber-800` | `text-white font-medium` |

### Files Changed

**Frontend (Rebuild Required):**

| File | Change Type |
|------|-------------|
| `resources/js/views/Login.vue` | Modified |

---

## Rebuild Required: YES

Frontend Vue file changed. Run the build script before uploading.

```bash
./deploy/fynla-org/build.sh
```

---

## Upload Checklist

### Step 1: Run Build

```bash
cd /Users/Chris/Desktop/fynla
./deploy/fynla-org/build.sh
```

### Step 2: Upload Built Assets

Upload the entire `public/build/` directory to:

```
~/www/fynla.org/public_html/public/build/
```

### Step 3: Clear Cache (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear
```

---

## Verification

1. Log in to the application
2. Wait for session to timeout (15 minutes) OR manually trigger by navigating to `/login?reason=inactivity`
3. The timeout message should display with:
   - Solid orange background (`#f97316` / orange-500)
   - White clock icon
   - White text: "Your session has expired due to inactivity. Please sign in again."

---
