# January 16, 2026 Updates

## Landing Page Improvements

### Hero Section - Rotating Text Fix
Fixed spacing issue where the word "Estate" in the rotating hero text had a visible gap before "Planning".

**Change Made** (`LandingPage.vue`):
- Changed `.hero-word-container` text alignment from `left` to `right`
- Shorter words like "Estate" now align correctly with consistent spacing before "Planning"

---

### Hero Section - Interactive Stats Cards
Made the stats cards in the hero section clickable with appropriate navigation.

**Changes Made** (`LandingPage.vue`):
- **"5 Planning Modules"** card: Scrolls to the planning modules section
- **"UK Tax Optimised"** card: Scrolls to the key features section
- **"100% Secure & Private"** card: Links to new `/security` page
- **"Free Demo Access"** card: Opens the persona selection modal (same as "Try the Demo" button)
- Added hover effects to all clickable cards
- Added `scrollToSection()` method for smooth scrolling
- Added `id="planning-modules"` and `id="key-features"` to target sections

---

### Hero Section - Layout Optimisation
Reduced hero section size so all content fits on the page without scrolling.

**Changes Made** (`LandingPage.vue`):
- Reduced minimum height from `90vh` to `75vh`
- Reduced vertical padding from `py-20` to `py-12`
- Reduced badge margin from `mb-8` to `mb-6`
- Reduced title margin from `mb-6` to `mb-4`
- Reduced description margin from `mb-10` to `mb-6`
- Reduced buttons margin from `mb-16` to `mb-8`
- Moved scroll indicator from absolute positioning to normal flow below the cards

---

## New Security Page

### Summary
Created a comprehensive Security & Privacy page accessible at `/security`.

**New File** (`resources/js/views/Public/SecurityPage.vue`):
- Hero section with emerald/teal gradient theme
- Overview cards: Encrypted Connections, UK Data Residency, Your Data Your Control
- Detailed sections covering:
  - Authentication & Account Security (MFA, session management, brute-force protection, password security)
  - Data Protection & Encryption (encryption at rest, key management, encrypted backups, secrets management)
  - Access Control (RBAC, least privilege, internal access logging)
  - Auditability & Monitoring (audit logs, immutable logs, suspicious activity alerts)
  - GDPR & Privacy Compliance (right to erasure, data minimisation, consent tracking, data export)
  - API & Application Security (rate limiting, token security, permission scoping)
  - Business Continuity (disaster recovery, uptime monitoring)
- Important disclaimer about not being regulated financial advice
- Contact section with email link

**Router Update** (`resources/js/router/index.js`):
- Added lazy-loaded `SecurityPage` component import
- Added `/security` route with `meta: { public: true }`

---

## Router - Scroll Behaviour

### Summary
Added scroll behaviour to ensure all pages load at the top when navigating.

**Change Made** (`resources/js/router/index.js`):
- Added `scrollBehavior` function to router configuration
- New page navigation scrolls to top
- Browser back/forward restores previous scroll position
- Hash links scroll smoothly to the target element

---

## Calculators Page Restyle

### Summary
Restyled the Calculators page to match the updated landing page design pattern.

**Changes Made** (`CalculatorsPage.vue`):
- Reduced hero section (removed min-height constraint)
- Moved calculator selector cards into hero section with glass styling
- Added inline SVG icons with distinct colours for each calculator type:
  - Income Tax: Blue
  - Retirement: Teal
  - IHT: Amber
  - CGT: Emerald
  - Pension Contribution: Purple
- Updated CTA section with glass card styling and animated background
- Removed old blob animation styles

---

## Learning Centre Restyle

### Summary
Restyled the Learning Centre page to match the updated design pattern.

**Changes Made** (`LearningCentre.vue`):
- Reduced hero section with pulse animation background
- Moved category navigation buttons into hero section with glass styling
- Added coloured inline SVG icons for each category:
  - Basics: Indigo
  - Protection: Emerald
  - Retirement: Amber
  - Estate: Rose
  - Investment: Violet
  - Tax: Blue
- Updated CTA section with glass card styling and animated background
- Removed old unused blob animation styles

---

## Version Update to v0.5.1

### Summary
Updated the application version to v0.5.1 with comprehensive changelog covering all changes from January 6-16.

**Files Updated:**
- `resources/js/components/Footer.vue` - Version v0.4.5 → v0.5.1
- `resources/js/layouts/PublicLayout.vue` - Version v0.4.5 → v0.5.1
- `CLAUDE.md` - Version v0.4.5 → v0.5.1
- `resources/js/views/Version.vue` - Added v0.5.1 release notes

**Changelog Sections Added:**
- UI Refresh & New Features
- Estate & Retirement Module Updates
- Dashboard & UX Improvements
- Forms & Data Entry
- Bug Fixes
- Technical Changes

---

## Security Page - Contact Email Fix

### Summary
Fixed the Contact Us button on the Security page to open email to the correct address.

**Change Made** (`SecurityPage.vue`):
- Changed `mailto:security@fynla.org` to `mailto:info@fynla.org`

---

## Sitemap Page for Google Indexing

### Summary
Created a sitemap page and XML sitemap for search engine indexing.

**New Files:**
- `resources/js/views/Public/SitemapPage.vue` - User-friendly HTML sitemap page
- `public/sitemap.xml` - XML sitemap for search engine crawlers

**Changes Made:**
- Added `/sitemap` route in `router/index.js`
- Added Sitemap link in `Footer.vue` (logged-in users)
- Added Sitemap link in `PublicLayout.vue` (public pages, Legal section)
- Updated `public/robots.txt` with sitemap reference

**Sitemap Page Sections:**
- Main Pages (Home, Calculators, Learning Centre, Security, Version, Help)
- Account (Login, Register, Forgot Password)
- Planning Modules (requires login)
- Calculators (all calculator types)

---

## Files Changed

- `resources/js/views/Public/LandingPage.vue` - Hero improvements and card links
- `resources/js/views/Public/SecurityPage.vue` - New file, contact email fix
- `resources/js/views/Public/SitemapPage.vue` - New file
- `resources/js/router/index.js` - Security route, sitemap route, scroll behaviour
- `resources/js/views/Public/CalculatorsPage.vue` - Restyled with glass effects and coloured icons
- `resources/js/views/Public/LearningCentre.vue` - Restyled with glass effects and coloured icons
- `resources/js/components/Footer.vue` - Version update, sitemap link
- `resources/js/layouts/PublicLayout.vue` - Version update, sitemap link
- `resources/js/views/Version.vue` - v0.5.1 changelog
- `CLAUDE.md` - Version update
- `public/sitemap.xml` - New file
- `public/robots.txt` - Sitemap reference
