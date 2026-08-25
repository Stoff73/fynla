# Deploy Guide — PR #203 (GA4 Events, Meta Pixel, Dashboard Video & UI Fixes)

**DEPLOYED** 9 April 2026

**Date:** 9 April 2026
**PR:** #203 (`ga-updates` branch, merged to main)
**Commit range:** `ba6ada5..53826e8`

---

## Summary

- GA4 event tracking added to registration, login, onboarding, and CTA clicks
- Meta Pixel integration in `app.blade.php`
- Dashboard video replaces homepage GIF walkthrough (2.4MB GIF to 499KB MP4)
- Journey progress hero: "Continue Journey" and "Start a new journey" side by side
- Cash & Savings card: sparkline removed, accounts expanded by default, limit increased to 5

---

## Files to Upload

### Frontend Build (required)

Upload the entire `public/build/` directory:

```
public/build/ --> ~/www/fynla.org/public_html/public/build/
```

### New Binary Asset

```
public/images/fynla-dashboard-walkthrough.mp4 --> ~/www/fynla.org/public_html/public/images/
```

### Backend (Blade template)

```
resources/views/app.blade.php --> ~/www/fynla.org/public_html/resources/views/
```

---

## Full File List (from git diff)

| File | Change | Upload? |
|------|--------|---------|
| `public/build/*` | Rebuilt | Yes (full directory) |
| `public/images/fynla-dashboard-walkthrough.mp4` | New file (499KB) | Yes |
| `resources/views/app.blade.php` | Meta Pixel added | Yes |
| `resources/js/components/Journey/JourneyProgressHero.vue` | Layout change | No (compiled into build) |
| `resources/js/components/Onboarding/OnboardingWizard.vue` | GA4 events | No (compiled into build) |
| `resources/js/views/Dashboard.vue` | Savings card changes | No (compiled into build) |
| `resources/js/views/Login.vue` | GA4 events | No (compiled into build) |
| `resources/js/views/Public/LandingPage.vue` | Video swap | No (compiled into build) |
| `resources/js/views/Register.vue` | GA4 events | No (compiled into build) |
| `.claude/settings.json` | Ignored (local config) | No |

---

## SSH Commands (post-upload)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## No Migrations

No database changes in this PR.

---

## Notes

- **Meta Pixel** fires unconditionally (no cookie consent gate) with hardcoded pixel ID `1878962689749080`. Consider gating behind cookie consent in a follow-up.
- **CSP headers:** `SecurityHeaders.php` may need `connect.facebook.net` and `www.facebook.com` added to CSP directives for the Meta Pixel to work. Verify after deploy.
- **No composer changes** — no need to run `composer install` on server.
