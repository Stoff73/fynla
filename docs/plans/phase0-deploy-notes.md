# Phase 0 Deploy Notes — Mobile App Instrumentation

**Date:** 10 March 2026
**Branch:** `feature/mobile-app-phase0`
**Version:** v0.8.4-phase0

---

## Summary

Phase 0 adds analytics instrumentation, auth token storage abstraction, mobile API v1 route group, and two new UI banners — all in preparation for the Fynla mobile app.

## Pre-Deploy: Environment Variables

Add to production `.env`:

```
# Analytics (Plausible Cloud)
ANALYTICS_ENABLED=true
PLAUSIBLE_DOMAIN=fynla.org
```

## Files to Upload

### PHP Files (upload to `~/www/fynla.org/public_html/`)

| File | Action |
|------|--------|
| `config/analytics.php` | NEW — upload |
| `app/Http/Middleware/IdentifyMobileClient.php` | NEW — upload |
| `app/Http/Middleware/SecurityHeaders.php` | MODIFIED — replace |
| `app/Http/Kernel.php` | MODIFIED — replace |
| `app/Providers/RouteServiceProvider.php` | MODIFIED — replace |
| `routes/api_v1.php` | NEW — upload |
| `resources/views/app.blade.php` | MODIFIED — replace |
| `tests/Unit/Services/Onboarding/JourneyFieldResolverTest.php` | MODIFIED — replace |

### Frontend Build (rebuild required)

Run locally:
```bash
./deploy/fynla-org/build.sh
```

Upload entire `public/build/` directory — replaces existing build. This includes all JS changes:
- `tokenStorage.js` (NEW)
- `analyticsService.js` (NEW)
- `OfflineBanner.vue` (NEW)
- `MobileSurveyBanner.vue` (NEW)
- Modified: `api.js`, `authService.js`, `aiChatService.js`, `sessionLifecycleService.js`, `auth.js` store, `preview.js` store, `app.js`, `Login.vue`, `Register.vue`, `AppLayout.vue`, `AiChatPanel.vue`, `router/index.js`

### Files NOT to Upload

- `.env.example` — reference only, update production `.env` manually
- `resources/js/CLAUDE.md` — dev documentation only
- `.claude/worktrees/` — dev artifacts, do not upload

## Post-Deploy: SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Verification Checklist

- [ ] Visit https://fynla.org — page loads normally
- [ ] Login → verify auth flow unchanged
- [ ] Check browser Network tab → Plausible script loads (`plausible.io/js/script.js`)
- [ ] Check Plausible dashboard → page views appearing
- [ ] Survey banner appears for authenticated users (not preview)
- [ ] Click survey response → banner dismisses, doesn't reappear on refresh
- [ ] AI chat opens → check Plausible for `chat_opened` event
- [ ] Visit `/api/v1/health` → returns JSON `{"success": true, ...}`
- [ ] Logout/login cycle → auth works identically to before
- [ ] Preview personas still work via landing page

## Rollback

If issues arise, re-upload the previous `public/build/` directory and restore the previous versions of the 6 modified PHP files. Remove the `ANALYTICS_ENABLED` env var.
