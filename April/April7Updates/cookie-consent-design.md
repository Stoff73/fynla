# Cookie Consent Banner — Design Spec

**Date**: 7 April 2026
**Status**: Approved

---

## Overview

A cookie consent banner that gates Google Analytics behind user consent. Plausible Analytics (cookie-free) is unaffected. Users who decline cookies cannot register until they accept.

## Cookie Consent Banner

### Appearance

- Bottom-centre overlay card (not full width, max-w-lg or similar)
- High z-index, subtle backdrop dim behind it
- Appears on first visit when no `cookie_consent` key exists in localStorage
- Does NOT appear for users who have already made a choice

### Content — Initial State

- Text: "We use cookies to help analyse how you use our site. You can accept or decline."
- Link to Privacy Policy (`/privacy`)
- Two buttons:
  - **Accept Cookies** — raspberry-500 CTA
  - **Decline Cookies** — outline/secondary style

### Behaviour — Accept

1. Store `cookie_consent: accepted` in localStorage
2. Dynamically load Google Analytics gtag script
3. Dismiss banner

### Behaviour — Decline (Warning State)

Clicking "Decline Cookies" transitions the banner to a warning state (same card, content changes):

- Text: "Without cookies, some features including registration will be unavailable. Google Analytics has been disabled."
- Two buttons:
  - **Accept Cookies** — raspberry-500 CTA (to change their mind)
  - **Continue Without Cookies** — outline/secondary (confirms decline)

On "Continue Without Cookies":
1. Store `cookie_consent: declined` in localStorage
2. Dismiss banner
3. GA never loads

## Google Analytics Gate

### Current State

`app.blade.php` has inline gtag script that loads immediately on every page:
```html
<script async src="https://www.googletagmanager.com/gtag/js?id=G-3Y8DL3QB09"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-3Y8DL3QB09');
</script>
```

### New Behaviour

- Remove the inline gtag script from `app.blade.php`
- Create `resources/js/utils/cookieConsent.js`:
  - `hasConsent()` — returns true if localStorage `cookie_consent === 'accepted'`
  - `acceptCookies()` — sets localStorage, dynamically injects gtag script into head, initialises GA
  - `declineCookies()` — sets localStorage to `declined`
  - `getConsentStatus()` — returns `'accepted'`, `'declined'`, or `null` (no choice yet)
  - `resetConsent()` — removes the localStorage key (for privacy settings page)
- GA ID (`G-3Y8DL3QB09`) stored as `VITE_GA_ID` env variable (not hardcoded in JS)
- Plausible script unchanged — stays in blade template, loads regardless of consent

## Registration Gate

### Location

`resources/js/views/Register.vue`

### Behaviour

When `getConsentStatus() !== 'accepted'`:

- Show a card above/instead of the registration form
- Text: "Cookies are required to create an account. They allow us to keep you securely signed in."
- CTA: **Accept Cookies & Continue** — calls `acceptCookies()`, then reveals the form
- The registration form is hidden until consent is given

When `getConsentStatus() === 'accepted'`:

- Normal registration flow, no changes

## Component Structure

### New Files

| File | Purpose |
|------|---------|
| `resources/js/components/Shared/CookieBanner.vue` | Banner component with initial and warning states |
| `resources/js/utils/cookieConsent.js` | Consent utility (localStorage, GA script injection) |

### Modified Files

| File | Change |
|------|--------|
| `resources/views/app.blade.php` | Remove inline gtag script |
| `resources/js/views/Register.vue` | Add consent gate above form |
| `resources/js/App.vue` or `resources/js/layouts/PublicLayout.vue` | Mount CookieBanner component |
| `.env` / `.env.example` | Add `VITE_GA_ID=G-3Y8DL3QB09` |

## Design System Compliance

- Raspberry-500 for accept CTA
- Horizon-500 for text
- Eggshell-500/white for card background
- Violet-500 for warning state border/accent
- Font: Segoe UI per design guide
- Rounded corners, shadow consistent with existing card patterns
- British English spelling throughout

## Persistence

- Choice stored in `localStorage` key `cookie_consent`
- Values: `'accepted'` or `'declined'`
- Persists across sessions — banner never shows again once a choice is made
- Privacy Settings page can offer a "Reset cookie preferences" option via `resetConsent()`
