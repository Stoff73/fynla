# Patch Notes — PR #177: Resource Pages Redesign

**Version:** v0.9.4.0
**Date:** 1 April 2026
**Branch:** `resources-pages` → `main`
**PR:** #177 (17 commits, 131 files changed, +7,243 / -2,672)

---

## New Pages

- **Advisors page** (`/advisors`) — hero, intro, why Fynla boxes, homepage-style feature cards, sign-up CTA with smooth scroll
- **Contact page** (`/contact`) — contact form with maths captcha, Ask Fyn box, horizon-blue email cards, FAQ/demo/Fyn pink boxes
- **Backend:** `ContactFormController` with rate limiting, routes to appropriate email per reason

## Page Redesigns

### Features Page
- Eggshell background, light-pink Fynla column with 3px border, light-blue competitor columns
- Harvey balls for feature comparison, clickable column headers linking to comparison pages
- Row hover effect, footnote with comparison links

### Comparison Pages (5 pages)
- Platform names replace competitor names (centralisation, planning, investment)
- New URL slugs (old URLs redirect via router)
- Light-blue intro sections, pink "When to use" boxes
- Harvey balls table, guides CTA style matching Guides & Explainers

### Glossary Page
- Full-width layout, larger letter navigation
- Pink letter heading boxes, increased font sizes

### FAQ Page
- Full-width layout, intro paragraph
- Insights-style category filter
- Pink section title boxes, light-pink "Still have questions" section
- Missing FAQs added from feature/pricing pages

### Security & Privacy Page
- Full redesign with design system palette colours (replaced emerald/slate/blue gradients)
- Intro paragraph on security values, white boxes with horizon tick icons
- Alternating light-pink/light-blue section backgrounds
- Horizon blue notice section, dual CTA (contact + get started)

### Our Approach Page
- Pink content boxes, horizon-blue "We stand by our values" grid
- Sentence case applied

### One Platform Page
- Homepage-style feature cards, journey boxes matching homepage
- Centre-aligned titles, hero on two lines

### Financial Companion Page (formerly "Not Tied to an Adviser")
- New hero title, white adviser vs Fynla comparison boxes on pink background
- IFA vs Fynla pricing comparison with raspberry border on Fynla box

### Getting Started Page
- New title with subtitle
- "Fynla is as easy as 1-2-3" steps section

### Insights Hub
- Light-pink hover on article cards (no border)

### Terms & Privacy Pages
- Full-width content layout

## Guides & Explainers Redesign (formerly "Learn")

- Renamed from "Learn" to "Guides & Explainers" with category filter tabs
- Categories: Key Terms, Decision Support, Personal Journey Guides
- `GuideNav` component with category tabs and 3-column link grid on all 22 article pages
- `GuideArticleFooter` with back link, horizon blue resource boxes, light pink CTA
- Article pages: full width, white rounded container, eggshell content blocks
- "Got a question? Ask Fyn" link under recommendations
- Intro paragraph shared via GuideNav across all pages

## Dashboard Improvements

- Investment sparkline replaced with vertical bar chart per account
- Empty state CTA buttons on Investment and Pension pages
- Fyn chat button repositioned between Support and Account (toggle open/close)
- Dashboard status bar white divider lines removed
- 2FA notification redesigned as session-dismissable green banner (removed from navbar)
- `ModuleStatusBar` redesign: three-column layout (completed/outstanding/why)
- Net worth donut hover tooltip (teleported to body)
- Sparkline hover tooltips + left marker clipping fix
- Tax year label added to Allowances card
- `DashboardCard` subtitle prop, matched card heights per grid row

## Mobile Layout

- Homepage: brain GIF in its own white rounded box above caption cards on mobile
- Calculators: horizontal scrolling category pills replace vertical sidebar on mobile

## Site-Wide Changes

- **Sentence case** applied across all titles and buttons (16+ files)
- **Demo modal** opens in-place on current page (not homepage redirect) via `PublicLayout`
- **Centralised FAQ data** (`constants/faqData.js`) — single source of truth for 50+ FAQs across FaqPage, PricingPage, and 7 feature pages
- **Footer updates:** FAQs → /faq, Learning Centre → Guides and explainers at /learn, adviser scroll links, social media icons (YouTube, Facebook, Instagram, TikTok)
- **Mega menu:** "One Platform Story" → "One platform", "Not Tied to an Adviser" → "Your financial companion"
- **Sitemap** updated with all 60+ public URLs including new pages and slugs
- **GA tag** repositioned in `app.blade.php` head for full site coverage
- **SEO:** meta descriptions and structured data schemas on learn pages
- **Deleted:** `LearningCentre.vue` (route redirects to /learn)
- **Pricing page:** limited time offer banner, bold prices, nowrap price+period

## Journey Pages (all 5 stages)

- `JourneyMap` component with curvy SVG path in "What do I need" section
- 6 need cards per stage on light blue backgrounds
- Light pink small steps background
- Journey map uses stage-specific onboarding steps
- Persona selection modal redesigned: two-row layout, SVG icons, secondary palette backgrounds

## Merge Notes

6 conflicts resolved during merge to main:
- `AppLayout.vue`: kept branch's `toggle-chat` naming + main's `open-plan-modal` (feature gating)
- 4 stage pages: kept main's `?stage=X` register links (PR #176 journey links)
- `CSJTODO.md`: combined both branch deploy contexts

## Backend Changes

- `ContactFormController.php` — new controller with rate limiting (`throttle:3,5`)
- `PreviewWriteInterceptor.php` — contact form route excluded
- `routes/api.php` — contact form POST endpoint

## No Migrations Required

## Deploy Requirements

- Frontend build required (`./deploy/fynla-org/build.sh`)
- Upload: `public/build/`, 4 PHP files, `public/sitemap.xml`
- Contact form requires mail config on production
- Cache clear on server
