# Fyn Chat — Floating Overlay

**Date:** 3 April 2026
**Branch:** `FynChat`
**Status:** DEPLOYED

---

## What Changed

The Fyn chat panel now floats above the dashboard instead of being part of the layout flow.

### Before
- Expanded chat pushed dashboard content left with a 285px right margin
- Dashboard cards shifted position when chat opened/closed

### After
- Expanded chat overlays the dashboard with `position: fixed` and `shadow-xl` for depth
- Dashboard content stays in place when chat opens — no layout shift
- Collapsed chat strip (40px) still reserves a small right margin so dashboard cards don't sit behind it

## File Changed

```
resources/js/layouts/AppLayout.vue
```

### Changes (2 lines)

1. **Line 40** — Removed `lg:mr-[285px]` class when chat is expanded. Main content no longer shifts right.
2. **Line 53** — Added `shadow-xl` to expanded chat `<aside>` for visual separation from dashboard content.

## Deploy

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` to `~/www/fynla.org/public_html/public/build/`

No PHP files changed. No cache clear needed (frontend-only).
