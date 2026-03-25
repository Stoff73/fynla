# Deploy — Countdown Clock (24 March 2026)

Countdown timer in the top navigation bar, counting down to **9 April 2026 at 12:00**. Shows for all users including preview personas. Displays as `DD : HH : MM : SS` with horizon-500 badges. Auto-removes when target date passes.

## Files to Upload

### Frontend (rebuild required)

Run locally first:
```bash
./deploy/fynla-org/build.sh
```

Then upload the built assets:
```
public/build/  →  ~/www/fynla.org/public_html/public/build/
```

### Backend

```
app/Http/Middleware/SecurityHeaders.php  →  ~/www/fynla.org/public_html/app/Http/Middleware/SecurityHeaders.php
```

> **Note:** The SecurityHeaders change only adds port 5174 to the local dev CSP whitelist. It has no effect in production but should be uploaded to keep files in sync.

### Files NOT to upload (local dev only)

These files were changed for the second dev server setup and must **not** go to production:

- `dev.sh` — local dev server script for port 8001/5174
- `vite.config.js` — port changed to 5174 (production builds use deploy scripts)
- `resources/js/services/api.js` — dynamic port for local dev
- `.env` — local config with port 8001
- `package-lock.json` — no dependency changes

## Post-Upload (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## What Changed

| File | Change |
|------|--------|
| `resources/js/components/Navbar.vue` | Added countdown timer (template + setup logic with 1-second interval) |
| `app/Http/Middleware/SecurityHeaders.php` | Added port 5174 to local dev CSP (no production impact) |

## Removal

The countdown auto-hides after 9 April 2026 12:00. To remove the code entirely after that date, delete the countdown block from `Navbar.vue` (template div + setup logic for `countdown`, `countdownInterval`, `updateCountdown`).
