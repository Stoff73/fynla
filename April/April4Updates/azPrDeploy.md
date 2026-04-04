# Deploy Guide — PR #188 (adhoc-changes-1)

**Date:** 4 April 2026
**PR:** #188 — feat: Fyn chat improvements, plan selection redesign, sign-out fix
**Author:** Phailanx
**Status:** Merged to main

---

## What Changed

- **Fyn chat panel**: 25% wider (docked 285 to 356px, floating 420 to 525px), content no longer shifts on expand/collapse, refined shadow
- **Journey onboarding via Fyn**: clickable journey stage options in chat for `?openFyn=journey` flow
- **Meet Fyn homepage section**: redesigned with expandable details, "Quick start with Fyn" CTA
- **Settings - Account Status**: shows current plan with trial days remaining, "Choose a Plan" button
- **Plan Selection Modal**: backdrop blur, per-card "Choose Plan" buttons, current plan highlighted
- **Sign-out behaviour**: public pages stay on current page after sign out

## Files to Upload

### Frontend only - no PHP changes

All changes are in `public/build/` (already built).

**Changed source files (for reference):**

| File | Change |
|------|--------|
| `resources/js/components/Navbar.vue` | Sign-out stays on public pages |
| `resources/js/components/Payment/PlanSelectionModal.vue` | Redesigned modal |
| `resources/js/components/Shared/AiChatPanel.vue` | Wider panel, journey options |
| `resources/js/components/SideMenu.vue` | Sign-out stays on public pages |
| `resources/js/layouts/AppLayout.vue` | Wider docked chat, removed content shift |
| `resources/js/store/modules/aiChat.js` | Journey prompt state |
| `resources/js/views/Dashboard.vue` | Handle openFyn=journey query |
| `resources/js/views/Public/LandingPage.vue` | Meet Fyn redesign |
| `resources/js/views/Register.vue` | Route to dashboard with Fyn on register from homepage |
| `resources/js/views/Settings.vue` | Account Status + Plan Selection Modal |

## Upload Steps

### 1. Upload build assets

Upload the entire `public/build/` directory to:

```
~/www/fynla.org/public_html/public/build/
```

### 2. Clear caches (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Post-deploy fix applied locally

- `.claude/settings.json` — Reverted Windows path (`C:/Users/phail/...`) back to Mac path (`/Users/CSJ/Desktop/fynla/...`) for tax-hardcode-check hook. This file should NOT be uploaded to production.

## No migrations required

All changes are frontend only.
