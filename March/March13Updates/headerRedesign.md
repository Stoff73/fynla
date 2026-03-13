# Mobile Header Redesign — 13 March 2026

## What Changed

Replaced the text-based page title header with a cleaner design featuring:
- Centred Fynla logo (`/images/logos/LogoHiResFynlaDark.png`) in the header bar
- User initials circle (top left, horizon-500 background) on root tab pages — taps navigate to settings
- Back chevron replaces the initials circle on sub-pages
- Right side is a spacer to balance the layout

## Login Screen

- Replaced small favicon (`favicon.png`) + "Fynla" heading with full `LogoHiResFynlaDark.png` at `h-20`
- Kept "Your financial planning companion" tagline

## Case Sensitivity Fix

The image filename on disk is `LogoHiResFynlaDark.png` (capital L) but was initially referenced as `logoHiResFynlaDark.png` (lowercase). iOS/Capacitor is case-sensitive for asset paths — fixed to match the actual filename.

## Files Changed

| File | Change |
|------|--------|
| `resources/js/mobile/MobileHeader.vue` | Complete rewrite — logo + initials/back |
| `resources/js/mobile/layouts/MobileLayout.vue` | Simplified props, added `navigateToSettings()` |
| `resources/js/mobile/views/MobileLoginScreen.vue` | Logo swap, heading removed |
