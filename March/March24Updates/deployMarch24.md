# Deploy — 24 March 2026 Updates

## Changes

### 1. Countdown Timer (committed to main)
Countdown clock in the dashboard top nav counting down to **9 April 2026 at 12:00**. Displays as `DD : HH : MM : SS` with horizon-500 badges. Centred between page title and user menu. Shows for all users including preview personas. Auto-removes when target date passes.

- **File:** `resources/js/components/Navbar.vue`

### 2. Investments Info Box → Tooltip (fixBugs branch)
Replaced the large purple info box on the investments dashboard with a small 'i' icon next to the "Investments" heading. Hovering the icon shows the same Bloomberg/Morningstar message as a tooltip.

- **File:** `resources/js/components/NetWorth/InvestmentList.vue`

### 3. Dev Server for fixBugs Directory
Created a second dev server setup so `fynla-fixBugs` can run alongside the main `fynla` directory without conflicts.

- Laravel: port 8001 (instead of 8000)
- Vite: port 5174 (instead of 5173)
- Only infrastructure files changed — no production impact

## Files to Upload (Production)

### Frontend (rebuild required)

```bash
./deploy/fynla-org/build.sh
```

Upload built assets:
```
public/build/  →  ~/www/fynla.org/public_html/public/build/
```

### Backend

No backend files need uploading for these changes.

### Files NOT to upload (local dev only)

- `dev.sh` — local dev server script (gitignored)
- `vite.config.js` — port changed for local dev
- `resources/js/services/api.js` — dynamic port for local dev
- `app/Http/Middleware/SecurityHeaders.php` — port 5174 CSP for local dev
- `.env` — local config
- `package-lock.json` — no dependency changes

## Post-Upload (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```
