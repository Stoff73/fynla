---
tags:
  - april-2026
  - deploy
  - bug-fix
date: 2026-04-09
---

# Deploy Guide — Preview Spouse Toggle Fix

Back to [[April Index]]

## Summary

Two bugs preventing preview persona spouse toggle from working correctly:

1. Toggle button stuck after first use — clicking "View as James" did nothing
2. Dashboard data not refreshing — showed stale spouse data after switching back

## Root Causes

**Bug 1 (toggle stuck):** `PreviewBanner.vue` set `switchingSpouse = true` on click but only reset it in the `catch` block. Since `switchPersona` succeeds (no error thrown), the flag stayed `true`. The component persists across SPA navigation (it's in the layout), so subsequent clicks hit the `if (this.switchingSpouse) return` guard and silently did nothing. Same bug existed on `confirmPersonaSwitch`.

**Bug 2 (stale data):** `Dashboard.vue` only called `loadAllData()` on first mount (guarded by `dataLoaded` flag). When persona switch navigates via `router.replace`, Vue Router reuses the component — `dataLoaded` stays `true`, so data is never refetched for the new user.

## Fixes

1. Moved flag resets from `catch` to `finally` blocks in both `handleSpouseToggle()` and `confirmPersonaSwitch()`
2. Added user ID change detection in Dashboard watcher — when `user.id` changes, `loadAllData()` fires again

## Files Changed

### Frontend Only (build assets)

| File | Change |
|------|--------|
| `resources/js/components/Preview/PreviewBanner.vue` | `switchingSpouse` and `switching` reset moved to `finally` blocks |
| `resources/js/views/Dashboard.vue` | Watch `currentUser.id` change to reload all dashboard data on persona switch |

## Upload Steps

### 1. Upload build assets

Upload `public/build/` directory to:
```
~/www/fynla.org/public_html/public/build/
```

### 2. Clear caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## No PHP changes. No migrations. No seeding required.

## Testing

1. Go to fynla.org landing page → click "See our demo" → select James & Emily Carter
2. On dashboard, click "View as Emily" → should switch to Emily's view
3. Click "View as James" → should switch back to James's view
4. Toggle back and forth multiple times — should work every time with correct data each time
5. Verify data values change (e.g. Carter: James net worth ~£92k vs Emily ~£69k)
6. Test Mitchells (David & Sarah) — same toggle behaviour
7. Also test switching between different personas from the dropdown
