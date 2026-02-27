# Deployment Guide: Side Navigation Menu

## Rebuild Required: YES (frontend)

New Vue components were added and existing ones modified. A frontend rebuild is required.

```bash
./deploy/fynla-org/build.sh
```

## Summary

Added a collapsible left-side navigation menu that persists across all authenticated pages. The menu provides direct access to all modules without returning to the Dashboard. The logo has been moved from the Navbar into the side menu.

### Features
- Expanded mode: icon + label (224px wide)
- Collapsed mode: icon only with tooltips (64px wide)
- Collapse/expand state persisted in localStorage
- Mobile: hidden by default, hamburger toggle opens as full-height overlay
- Active state highlighting based on current route
- Sections: Main, Planning, Advanced, Plans & Actions, Account, Support, Admin (conditional)

## New Files to Upload

```
resources/js/assets/favicon.png
resources/js/components/SideMenu.vue
resources/js/components/SideMenuIcon.vue
resources/js/components/SideMenuItem.vue
resources/js/components/SideMenuMobileToggle.vue
resources/js/components/SideMenuSection.vue
```

## Modified Files to Upload

```
resources/js/layouts/AppLayout.vue
resources/js/components/Navbar.vue
resources/js/services/holisticService.js
resources/js/views/NetWorth/NetWorthDashboard.vue
```

### AppLayout.vue Changes
- Added SideMenu and SideMenuMobileToggle components
- Content wrapper now has dynamic left margin (`sm:ml-56` expanded / `sm:ml-16` collapsed) to accommodate the side menu
- Added localStorage persistence for collapsed state

### Navbar.vue Changes
- Removed logo (moved to side menu)
- Removed Dashboard link (available in side menu)
- Removed mobile hamburger button and mobile menu (replaced by side menu mobile toggle)
- Layout changed from `justify-between` to `justify-end`

### holisticService.js Changes
- Fixed double `/api/api/` prefix on all endpoints (was causing 405 errors)
- All paths changed from `/api/holistic/...` to `/holistic/...`

### NetWorthDashboard.vue Changes
- Removed the entire old sidebar navigation (template, data, computed, methods, watchers, CSS)
- Net Worth module pages now use only the new left side menu for navigation

## No Database Changes

No migrations or seeders required.

## Post-Deploy

Clear caches after uploading:

```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```
