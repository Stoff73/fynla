---
tags:
  - mobile
  - ui
  - march-2026
---

# More Menu — Remove Modules Grid

**Date:** 2026-03-13
**Commit:** `8e5a91a`

## Change

Removed the Modules grid section from `MoreMenu.vue`. Modules are already accessible from the dashboard — duplicating them in the settings screen added clutter.

**Before:** Profile card → Modules grid (7 buttons) → Settings list → Log out → Version
**After:** Profile card → Settings list → Log out → Version

## File Changed

| File | Change |
|------|--------|
| `resources/js/mobile/views/MoreMenu.vue` | Removed modules grid, data array, and navigateToModule method (34 lines deleted) |
