# CSJTODO — Fynla

*Last updated: 30 March 2026 — session 18*
*Previous session: 30 March 2026 session 17 (content merge, code review, tech debt)*

---

## Session 18 (30 March) — Admin User Metrics Dashboard + Subscription Tiers

### Completed
- [x] Added "Family" subscription tier (between Standard and Pro)
- [x] Updated pricing: full prices + launch discount prices for all 4 tiers
- [x] Created 2 migrations (family enum, launch price columns)
- [x] Updated SubscriptionPlanSeeder with 4 tiers and launch pricing
- [x] Updated public PricingPage.vue with 4-column layout, strikethrough pricing, launch discount badge
- [x] Built UserMetricsService with 5 analytics query methods
- [x] Built UserMetricsController with 5 API endpoints
- [x] Created 6 Vue components (SnapshotCards, TrialBreakdown, PlanBreakdown, ActivityCharts, ActivityTable, UserMetrics)
- [x] Added "User Metrics" tab to AdminPanel.vue
- [x] 34 Pest tests (20 unit + 14 feature) — all passing
- [x] Browser tested: admin metrics tab (all sections + period selector), pricing page (monthly/yearly toggle)
- [x] Completeness report written to March/March30Updates/
- [x] Deploy guide written to March/March31Updates/deployAdminUser.md
- [x] Branch pushed to origin/adminUserView
- [x] Vault synced (git history, March index, monthly commits, Home.md)
- [x] CLAUDE.md and README.md metrics updated (Vue 646, Services 215, Controllers 90)

### Not Yet Done
- [ ] Merge adminUserView branch into main
- [ ] Deploy to production (see March/March31Updates/deployAdminUser.md)
- [ ] Feature access gating per tier (CheckSubscription middleware only checks active status, not plan-specific features)

### Context
Content branch has journey page redesign committed and pushed (2 commits: `e5ecd2bb`, `ae92ab8c`). All changes are frontend-only. Deploy notes at `March/March28Updates/deploy.md`. The mockup file `public/mockup-starting-out.html` should be deleted before production deploy.

---

## Session 30 March 2026 — Resource pages, journey maps, persona modal, footer

### Completed This Session
- [x] All resource page headers updated to match pricing/calculator style (48 pages)
- [x] JourneyMap component with curvy SVG path on all 5 journey pages
- [x] "What do I need to start my journey?" section with 6 need cards per stage
- [x] Journey map labels fixed (Assets, Liabilities capitalised)
- [x] Persona selection modal redesigned (category grouping, SVG icons, two-row layout)
- [x] Footer social media icons (YouTube, Facebook, Instagram, TikTok) with HelloFynla
- [x] Insights page redesign (article cards, category filters, light pink stay updated)
- [x] Article pages updated (back link, badges, light blue related, pink CTA)
- [x] Learn page headers standardised across 28 pages
- [x] Feature detail pages header updated via FeaturePageLayout component
- [x] Homepage header links reordered (View video | Ask Fyn | See our demo)
- [x] Journey page CTA text changed to "Start your journey"
- [x] Small steps section background changed to light pink
- [x] Mobile layout fixes for homepage and calculators

### New Outstanding Items
- [x] Create PR from resources-pages branch to main — PR #177
- [ ] Delete mockup HTML files from public/ before deploy (insights, learn, journey, persona, mobile)
- [ ] Browser test persona selection modal on desktop and mobile
- [ ] Browser test contact form submission (requires mail config on production)
- [ ] Merge PR #177 and deploy (see March/March31Updates/deployResourcePages.md)
- [ ] Submit updated sitemap.xml to Google Search Console after deploy
- [ ] Monitor old comparison page URLs for 404s (/fynla-vs-moneyhub, /fynla-vs-projectionlab, /fynla-vs-voyant)

### Context
Resources-pages branch has 25 commits today (662a512f latest). PR #177 created. 421 files changed. Deploy notes at `March/March31Updates/deployResourcePages.md`.

---

## Session 31 March 2026 — Public pages redesign, new pages, centralised FAQs, contact form

### Completed This Session
- [x] Features page: eggshell bg, light-pink/light-blue columns, harvey balls, comparison links
- [x] GA tag moved to app.blade.php head for full site coverage
- [x] SEO meta descriptions and structured data schemas on learn pages
- [x] Comparison pages: platform names replace competitors, new slugs, redesigned sections
- [x] Glossary page: full-width, pink letter boxes, larger fonts
- [x] FAQ page: full-width, centralised FAQ data (constants/faqData.js), intro, pink section boxes
- [x] Demo modal opens in-place on current page (PublicLayout global modal)
- [x] Security page: full redesign with palette colours, tick icons, horizon notice
- [x] Our approach page: pink content boxes, horizon-blue values grid
- [x] One platform page: homepage-style feature/journey cards, centre-aligned
- [x] Financial companion page: new hero, IFA vs Fynla pricing comparison
- [x] Getting started page: new title, "Fynla is as easy as 1-2-3" steps section
- [x] Sentence case applied across all titles and buttons site-wide
- [x] Advisors page: new page with hero, intro, feature cards, sign-up CTA with smooth scroll
- [x] Contact page: contact form with captcha, Ask Fyn box, horizon email cards
- [x] ContactFormController backend with rate limiting and email routing
- [x] Insights hub: light-pink hover on articles
- [x] Footer: FAQs→/faq, Learning Centre→Guides and explainers at /learn, adviser scroll links
- [x] Mega menu: "One Platform Story"→"One platform", "Not Tied to an Adviser"→"Your financial companion"
- [x] Terms & Privacy pages: full-width content
- [x] Deleted LearningCentre.vue (route redirects to /learn)
- [x] Sitemap updated with all 60+ public URLs
- [x] PR #177 created
- [x] Production build completed (6.9M)

### Outstanding
- [ ] Merge PR #177 and deploy
- [ ] Test contact form on production (requires mail config)
- [ ] Submit sitemap to Google Search Console
- [ ] Monitor old comparison URLs for 404s

---

## Outstanding — Tech Debt Deferred (from techDebtDeferred.md)

### God Class Decomposition (CRITICAL — Large effort, ~40-60 hours)
- [ ] SavingsActionDefinitionService: 3,675 lines
- [ ] RetirementActionDefinitionService: 2,701 lines
- [ ] ProtectionActionDefinitionService: 2,349 lines
- [ ] InvestmentController: 1,067 lines
- [ ] Dashboard.vue: 2,124 lines
- [ ] CalculatorsPage.vue: 2,432 lines

### Float-to-Decimal Migration (HIGH — Blocked)
- [ ] 60+ financial fields across 12 models use 'float' cast
- [ ] Blocked: decimal:2 returns strings, breaks 50+ test assertions
- [ ] Estimated: 1 full sprint

### Test Coverage (HIGH — 20+ hours)
- [ ] Currently 19% (41/214 services tested)
- [ ] Target: 40% coverage

### NPM Vulnerabilities (CRITICAL — 8 hours)
- [ ] 9 high-severity CVEs
- [ ] Needs careful testing of PWA and iOS mobile after update

### Other Deferred
- [ ] Off-palette Tailwind in Risk module (30+ files)
- [ ] Hardcoded hex in SVG/styles (40+ instances)
- [ ] DB facade in 8 controllers
- [ ] Vuex state bloat in investment.js and netWorth.js

## Carried Forward

### Grok AI Migration
- [ ] Get xAI API key
- [ ] Complete AI form fill testing (Steps 4-10)
- [ ] Phase 5: remove Anthropic SDK, delete Python scripts, update legal text

### Known Issues
- [ ] DB pension field mapping mismatch (employer_name vs scheme_name)
- [ ] Expenditure form fill doesn't animate through form
- [ ] property_sale life event: Grok creates property record (double navigation)

## Deploy Status

### Branch: resources-pages (PR #177 — NOT merged to main)
- Deploy guide: March/March31Updates/deployResourcePages.md
- Requires: merge to main, build, 4 PHP files upload, sitemap upload, cache clear
- No migrations needed
- Contact form requires mail config on production

### Branch: adminUserView (NOT merged to main)
- Deploy guide: March/March31Updates/deployAdminUser.md
- Requires: merge to main, build, PHP upload, 2 migrations, seeder, cache clear

### Previous (session 17 — also not yet deployed)
- Deploy guides: March/March30Updates/reviewDeploy.md + techDeploy.md
- Requires: 48 PHP files, migration (Payment SoftDeletes), reseed, rebuild, cache clear

## Context for Next Session

Session 31 March completed a comprehensive public pages redesign on the resources-pages branch (PR #177, 421 files). Key changes: all public pages restyled to consistent design system, competitor names replaced with platform categories on comparison pages (with new URL slugs), centralised FAQ data, new advisors page, contact form with backend, demo modal opens in-place, sentence case across site, sitemap updated with 60+ URLs. The branch needs merging to main and deploying. Old comparison page URLs (/fynla-vs-moneyhub etc.) have router-level redirects but should be monitored for SEO impact. Contact form requires mail config on production server. Three branches pending deploy: resources-pages, adminUserView, and session 17 fixes.

## Files to Review
- March/March31Updates/deployResourcePages.md — Resource pages deploy guide
- March/March31Updates/deployAdminUser.md — Admin user metrics deploy guide
- resources/js/constants/faqData.js — Centralised FAQ data (single source of truth)
- app/Http/Controllers/Api/ContactFormController.php — New contact form backend
