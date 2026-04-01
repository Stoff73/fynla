# CSJTODO — Fynla

*Last updated: 31 March 2026 — sessions 21-23*
*Previous session: 31 March 2026 session 20 (subscription upgrade proration)*

---

## Sessions 21-23 (31 March) — Production Review + Feature Gating + Journey Links

### Completed This Session
- [x] Production review: all PHP files, frontend build, migrations verified matching via MD5
- [x] **Feature gating (PR #175):** CheckFeatureAccess middleware, greyed sidebar items with tooltip, 10 Pest tests, backend route enforcement
- [x] **Journey link (PR #176):** Stage page "Start your journey" CTAs → registration → journey map
- [x] **Security fix:** Register/login now reset ALL Vuex stores + always clear stored auth token (prevented data leakage between users)
- [x] Fixed tooltip clipping (Teleport to body), stage ID mapping (mid_career, peak), CTA text
- [x] All deployed and browser tested on production
- [x] Vault synced, git history updated, session-end skill rewritten

### Feature Gating — NOW DONE
- [x] Feature access gating per tier (was listed as "not yet done" in previous CSJTODO)
- [ ] Recurring billing / auto-renewal (currently one-time Revolut orders only)

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

### All Sessions Through 23 — DEPLOYED & VERIFIED
- Feature gating: PR #175 merged, deployed, production tested (student tier greyed items, tooltip, pro full access)
- Journey links: PR #176 merged, deployed, production e2e tested (stage CTA → register → journey map)
- Security fix (Vuex state + token leak): included in PR #176

### PR #177 — Resource Pages Redesign (merged 1 April)
- Deploy guide: March/March31Updates/deployResourcePages.md
- Requires: build, 4 PHP files upload, sitemap upload, cache clear
- No migrations needed
- Contact form requires mail config on production

## Context for Next Session

All work through PR #176 is deployed and production tested. PR #177 (resource pages redesign) merged to main — needs building and deploying. Contact form requires mail config on production server.
