# Deploy Notes — 1 April 2026

**PR:** #177 (Resource Pages Redesign) + follow-up fixes
**Commits:** a09807f → cfd7ccf (19 commits)
**Files changed:** 131 (PR #177) + 2 (follow-up fix)

---

## What Was Deployed

### PR #177 — Resource Pages Redesign (17 commits)
Comprehensive redesign of all public-facing resource pages to design system v1.2.0 palette.

**New pages:**
- Advisors page (`/advisors`)
- Contact page (`/contact`) with backend form controller, maths captcha, rate limiting

**Redesigned pages:**
- Features page — eggshell bg, harvey balls, comparison table, column links
- FAQ page — centralised data (`faqData.js`), category filters, pink section boxes
- Security page — palette colours, intro text, tick icons, horizon notice
- Glossary page — full-width, larger letter nav, pink heading boxes
- Our Approach page — pink content boxes, horizon-blue values grid
- One Platform page — homepage-style cards, journey boxes, centre-aligned
- Financial Companion page — IFA vs Fynla pricing comparison
- Getting Started page — "as easy as 1-2-3" steps section
- Insights hub — light-pink hover on articles
- Terms & Privacy pages — full-width content
- All 5 comparison pages — platform names (not competitor names), new URL slugs
- All 22 learn/guide article pages — GuideNav + GuideArticleFooter components

**Dashboard improvements:**
- Investment sparkline → vertical bar chart per account
- Empty state CTA buttons on Investment and Pension pages
- Fyn chat button repositioned (toggle open/close)
- ModuleStatusBar three-column redesign
- Net worth donut hover tooltip
- DashboardCard subtitle prop, matched card heights

**Site-wide changes:**
- Sentence case across all titles and buttons
- Demo modal opens in-place on current page (not homepage redirect)
- Centralised FAQ data — single source of truth for 50+ FAQs
- Footer: FAQs → /faq, Learning Centre → Guides & Explainers, social icons
- Mega menu label updates
- Sitemap updated with 60+ public URLs
- Google Analytics tag moved to app.blade.php head
- SEO meta descriptions and structured data on learn pages
- LearningCentre.vue deleted (route redirects to /learn)
- Mobile layout: horizontal category pills on calculators, brain GIF box on homepage

**Journey pages:**
- JourneyMap component with curvy SVG path
- 6 need cards per stage
- Persona selection modal redesigned

### Follow-up Fix (commit cfd7ccf)
- Old comparison URL redirects: `/compare/fynla-vs-moneyhub` → new slug (3 URLs)
- CSP updated for Google Analytics (`googletagmanager.com`, `*.google-analytics.com`)

---

## Files Uploaded to Production

### PHP files (uploaded via SSH MCP)
1. `app/Http/Controllers/Api/ContactFormController.php` (new)
2. `app/Http/Middleware/SecurityHeaders.php` (CSP update)
3. `app/Http/Middleware/PreviewWriteInterceptor.php` (contact route excluded)
4. `resources/views/app.blade.php` (GA tag in head)
5. `routes/api.php` (contact form route — patched via sed)

### Other files
6. `public/sitemap.xml` (60+ URLs)

### Build directory
7. `public/build/` (291 asset files, 6.8MB — uploaded via GitHub release artifact)

### Server commands
```
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Merge Notes

PR #177 had 6 conflicts with main (resolved manually):
- `AppLayout.vue` — kept branch's `toggle-chat` + main's `open-plan-modal` (feature gating from PR #175)
- 4 stage pages — kept main's `?stage=X` register links (journey links from PR #176)
- `CSJTODO.md` — combined both contexts

Auto-merged cleanly: `Navbar.vue`, `router/index.js`, `routes/api.php`, `StartingOutPage.vue`

---

## Production Test Results

### Console errors: 0 (across all pages)

| Page | Status |
|------|--------|
| Homepage (fynla.org) | Pass |
| Features (harvey balls, table) | Pass |
| FAQ (category filters, 50+ FAQs) | Pass |
| Contact (form renders) | Pass |
| Advisors (hero, cards, CTA) | Pass |
| Security (redesigned palette) | Pass |
| Guides & Explainers hub | Pass |
| Comparison redirect (old → new slug) | Pass |
| Stage page (journey map, ?stage= CTA) | Pass |
| Journey link → /register?stage=early_career | Pass |
| /learning-centre → /learn redirect | Pass |
| Login + verification code | Pass |
| Dashboard (all cards, data, Fyn chat) | Pass |
| Subscription tab (Pro, Active, £200/yr) | Pass |

---

## No Migrations Required

## Notes
- Contact form requires mail config on production (SMTP). Form submits successfully but email delivery depends on SiteGround mail config.
- Old comparison URLs (`/compare/fynla-vs-moneyhub`, `/compare/fynla-vs-voyant`, `/compare/fynla-vs-projectionlab`) have client-side redirects. No server-side 301s — monitor SEO impact.
