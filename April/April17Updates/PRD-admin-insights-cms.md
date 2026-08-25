# PRD — Admin Insights CMS

**Project:** Admin Insights CMS
**Owner:** CSJ
**Status:** Draft
**Date:** 17 April 2026
**Spec:** `/Users/CSJ/Desktop/fynla/docs/superpowers/specs/2026-04-17-admin-insights-cms-design.md`
**Plan:** `/Users/CSJ/Desktop/fynla/docs/superpowers/plans/2026-04-17-admin-insights-cms.md`
**Codebase audit:** Completed 17 April 2026 — 11 🔴 conflicts, 7 🟡 ambiguities, 4 🟢 gaps identified and resolved in the amended spec/plan. See Risks & Dependencies for residual concerns.

---

## 1. Context & Why

### Problem

Today's insights section on fynla.org is entirely hardcoded. The 8 published articles each live in their own Vue component under `resources/js/views/Public/insights/`. The hub page (`InsightsHubPage.vue`) maintains a hand-written array of all 8 articles. The landing page (`LandingPage.vue`) has a *second* hand-written array of the 3 most recent for its "Latest insights" section.

Three concrete friction points:

- **Every new article requires engineering.** Writing an article means a new Vue component, a new route entry, a seeded image in `@/assets/insights/`, and a build + deploy. The cycle time from draft-in-Google-Docs to live-on-site is measured in hours, not minutes.
- **Two arrays drift.** The hub and landing arrays aren't linked. An article shown on the hub might be missing from the landing page's top-three because someone forgot to update the second array. Already happened once; it will happen again.
- **Tax-year-sensitive content goes stale silently.** Articles that reference "the ISA allowance is £20,000" become incorrect on 6 April when allowances change. There's no mechanism to keep these numbers alive.

### Business case

- **Content velocity directly drives organic traffic.** Articles are SEO-bound, and each one published strengthens fynla.org's domain authority against Moneyhub, PensionBee, etc. Reducing article publish time from hours to minutes means CSJ (or a non-engineering admin) can ship responses to budget announcements, allowance changes, and news cycles without waiting for a deploy window.
- **Unblocks non-engineering authors.** Currently only engineers can publish articles. With the CMS, a marketing hire or advisor contributor could publish without touching code.
- **Removes a silent integrity risk.** Tax-year-aware stat blocks in articles pull live values from `TaxConfigService`, so articles survive tax-year rollovers without an audit.

### Strategic fit

This is cross-cutting admin infrastructure, not a financial module. It touches no agents, no domain services, no user-facing calculators. It sits next to the existing `AdminController` and `/admin/*` routes, extending the admin surface to content management.

Connects to recent work:
- Session 56's **Awin affiliate tracking** shipped two new insight pages (`stocks-shares-isa-uk`, `how-much-to-retire-uk`) as bespoke Vue files. Any future article in that pattern costs the same engineering time. The CMS turns that cost from hours to minutes.
- Session 57's **email template redesign** and the emerging editorial voice on fynla.org both assume article content will scale. This unblocks that scaling.

---

## 2. Target Persona

**Primary:** CSJ (or a future marketing/content admin) — the person authoring and publishing articles. Currently blocked by the need for engineering involvement.

**Secondary:** All end-user personas (young_family, peak_earners, widow, entrepreneur, young_saver, retired_couple) benefit indirectly — they get more timely, more frequent, and more accurate insight content. No direct UX change for end users beyond improved content velocity.

**Infrastructure note:** The feature has no user-facing UI change on the read side — visitors to `/insights/*` see the same URLs, the same visual design, the same SEO behaviour. The landing page's "Latest insights" section changes from a 3-card grid to a 2/3 featured + 1/3 supporting bento layout matching the hub, but this is a minor visual refresh, not a persona-specific change.

---

## 3. Success Metrics (KPIs)

| Metric | Baseline | Target | Measurement |
|--------|----------|--------|-------------|
| Time from draft to published article | ~3-6 hours (engineering involvement) | Under 10 minutes end-to-end | CSJ self-reports first 3 real articles authored through the CMS after launch |
| Article publish volume | ~1-2 per month | 4+ per month within 3 months of launch | Count of `insight_articles` where `is_bespoke = false` AND `status = 'published'` |
| Hub/landing array drift incidents | 1+ observed in last 6 months | 0 | Manual audit — no longer possible since both render from one source |
| Tax-year accuracy of live articles after rollover | Unknown — requires manual audit | 100% of `tax_year_stat` block values refresh automatically | Run automated test after rollover; confirm `TaxYearStatBlock` renders current-year values without edit |
| Admin-only article preview leaks | 0 | 0 | Pest feature test confirms `?preview=true` requires `is_admin`; monitor Laravel logs for unauthorised access attempts |

---

## 4. User Stories & Scenarios

### User stories

- As **the admin (CSJ)**, I want to **open a blank or template-based editor and write a new article with structured blocks** so that **I can publish without opening Vue source files or deploying**.
- As **the admin**, I want to **schedule an article to publish at a specific future time** so that **I can draft on Sunday evening and have it go live at 9am Monday without being at my desk**.
- As **the admin**, I want to **flag one article as "Featured" and have it automatically replace whatever was featured before** so that **the landing page hero stays fresh without leaving two articles featured at once**.
- As **the admin**, I want to **save a good article's block structure as a template** so that **future articles of the same shape start from a proven layout rather than a blank canvas**.
- As **the admin**, I want to **edit the metadata of the 8 bespoke articles (title, summary, tags, hero image, featured flag)** so that **the hub page and landing hero can feature them alongside new CMS-authored articles without breaking their bespoke Vue rendering**.
- As **a site visitor**, I want to **read an insight article at `/insights/{slug}` with the same SEO quality and visual polish as the existing 8 bespoke articles** so that **article content feels cohesive regardless of whether it's CMS-authored or a bespoke page**.
- As **a site visitor on the landing page**, I want to **see a featured article prominently in the "Latest insights" section** so that **I know what's new without having to click through to the hub**.

### Key scenarios

**Scenario 1 — Admin publishes a new article from scratch:**
1. CSJ logs in as admin, clicks **Insights** in the admin panel sidebar.
2. Clicks **New article**, picks "Blank" from the template picker.
3. Fills in title ("Spring Budget 2026 — What Changed"), subtitle, summary, category ("tax-changes"), tags (["Budget", "Tax changes"]).
4. Uploads a hero image; the system resizes it to card (800×450 WebP) and thumb (200×200 WebP) automatically.
5. Adds blocks: a `key_takeaways` at the top, several `heading` + `paragraph` pairs, a `tax_year_stat` block showing the current ISA allowance (value pulled live from `TaxConfigService`), and a `cta_button` linking to the retirement calculator.
6. Clicks **Save draft** — article saved to DB, URL updates to `/admin/insights/{id}/edit`.
7. Clicks **Preview** — new tab opens `/insights/spring-budget-2026-what-changed?preview=true` showing the article as it will appear. Only CSJ's admin session can see this.
8. Clicks **Publish** — status changes to `published`, `published_at` set to now, article immediately visible at the public URL.
9. Toggles **Featured** on — the previously featured article is automatically unfeatured. Landing page's big tile now shows the new article after cache invalidation (generation counter bumped automatically by the observer).

**Scenario 2 — Admin schedules an article:**
1. CSJ drafts an article on Sunday evening.
2. Instead of Publish, clicks the dropdown and picks **Schedule…**. Date-time picker opens.
3. Sets "Monday 09:00".
4. Saves. Status remains `draft`, `scheduled_at` set to Monday 09:00.
5. `PublishScheduledInsightsJob` runs every 5 minutes via the existing Fynla cron. At 09:00-09:05, it promotes the article to `published`.

**Scenario 3 — Admin edits a bespoke article's metadata:**
1. CSJ clicks "The Ultimate Guide to ISAs in the UK" in the admin list (flagged with a "Bespoke" badge).
2. Editor opens with the field panel visible but the block canvas replaced by a `BespokeArticleNotice` component: _"This article is rendered by a bespoke Vue component (`IsaGuideUkPage`). To edit the article's content, update the component file directly in `resources/js/views/Public/insights/IsaGuideUkPage.vue`. You can still edit the title, summary, tags, hero image, and featured status here."_
3. CSJ updates the summary and toggles featured. Clicks save. Metadata updates; the Vue component stays untouched.

**Scenario 4 — Unhappy path: visitor accesses a draft without admin permission:**
1. Visitor (not logged in as admin) browses to `/insights/spring-budget-2026-what-changed?preview=true` for an article still in draft.
2. `InsightController::show()` checks: is `?preview=true` present AND is the requester an admin? No — returns 404.
3. Visitor sees the "Article not found" fallback.

**Scenario 5 — Unhappy path: paragraph HTML contains a script tag:**
1. Admin pastes `<p>Normal text <script>alert('xss')</script></p>` into a paragraph block.
2. On save, `StoreInsightArticleRequest::prepareForValidation()` runs `strip_tags()` with an allowlist of `<strong><em><a><br>` — the `<p>` and `<script>` are removed.
3. Stored value: `Normal text alert('xss')` (plain text).
4. On render, `ParagraphBlock.vue` passes the stored value through DOMPurify as a second defence layer. No XSS executed.

---

## 5. Functional Requirements

Prioritised using MoSCoW.

### Must-have

- **FR-M1: Three new tables.** `insight_articles`, `insight_templates`, `insight_article_revisions` with the schemas defined in the spec. _Touches: 3 migrations in `database/migrations/`._
- **FR-M2: 8 bespoke articles' metadata migrated to DB.** `ExistingInsightsMetadataSeeder` inserts rows with `is_bespoke=true` so hub/landing can treat them uniformly. _Touches: `database/seeders/ExistingInsightsMetadataSeeder.php`._
- **FR-M3: Admin CRUD endpoints.** Create, read, update, delete, publish, archive, feature, unfeature, resync-template, list revisions, restore revision. All under `/api/admin/insights/articles/*` with `['auth:sanctum', 'permission:admin.access']` middleware. _Touches: `Api\Admin\InsightArticleController`._
- **FR-M4: Template CRUD.** Save from article, list, rename, delete. `permission:admin.access` gated. _Touches: `Api\Admin\InsightTemplateController`._
- **FR-M5: Image upload.** Multipart upload at `/api/admin/insights/images`. Generates original + card (800×450 WebP) + thumb (200×200 WebP) using `intervention/image`. _Touches: `InsightImageService`, `Api\Admin\InsightImageController`._
- **FR-M6: Public article endpoints.** `GET /api/insights`, `GET /api/insights/featured`, `GET /api/insights/{slug}`. Only returns `status=published` articles to non-admin. Admins with `?preview=true` can see drafts. _Touches: `Api\Public\InsightController`._
- **FR-M7: Block renderer + 11 block types.** Heading, paragraph, list, image, pull quote, callout, divider, CTA button, tax year stat, related articles, key takeaways. Each has a dedicated Vue component. _Touches: `resources/js/components/Insights/blocks/`._
- **FR-M8: Public article page.** `InsightArticlePage.vue` renders at the catch-all `/insights/:slug` route, fetching by slug from the public API. _Touches: `resources/js/views/Public/insights/InsightArticlePage.vue`, router update._
- **FR-M9: Hub page DB-driven.** `InsightsHubPage.vue` replaces its hardcoded array with Vuex-driven data fetched from `/api/insights`. _Touches: `InsightsHubPage.vue`._
- **FR-M10: Landing page 2/3+1/3 hero.** `LandingPage.vue` "Latest insights" section becomes featured-first bento layout matching the hub, fetched from `/api/insights/featured`. _Touches: `LandingPage.vue`._
- **FR-M11: Admin list + editor.** `ArticleListPage.vue` (table with filters), `ArticleEditor.vue` (split layout with field panel + block canvas), `BlockPickerModal.vue`, `TemplateListPage.vue`. _Touches: `resources/js/views/Admin/Insights/`._
- **FR-M12: Bespoke article notice in editor.** When `is_bespoke=true`, hide the block canvas and show `BespokeArticleNotice.vue`; allow metadata-only edits. _Touches: editor + new component._
- **FR-M13: Featuring rules.** At most one article featured at a time. Toggling on auto-unfeatures the previous one via `InsightArticleService::setFeatured()` inside a DB transaction. Landing page falls back to most recent published when nothing is featured. _Touches: service + `InsightController::featured()`._
- **FR-M14: Scheduled publishing.** `PublishScheduledInsightsJob` runs every 5 minutes via existing Fynla cron; promotes drafts whose `scheduled_at` has passed. _Touches: job + `app/Console/Kernel.php`._
- **FR-M15: Revision history.** Every article save writes a row to `insight_article_revisions` via `InsightArticleObserver` (observer owns the write; service does NOT write revisions). Admin can list revisions and restore any of them. _Touches: observer + service + admin endpoints._
- **FR-M16: SEO meta injection.** `InsightsSeoMetaInjector` middleware pushes `<title>`, `<meta name="description">`, Open Graph, Twitter Card, and JSON-LD Schema.org Article tags into the `@stack('head')` placeholder in `app.blade.php`. Bespoke articles bypass (their Vue files own their own meta). _Touches: middleware + `app.blade.php` + view composer._
- **FR-M17: HTML sanitisation, two layers.** Backend: `StoreInsightArticleRequest::prepareForValidation()` sanitises paragraph/callout HTML using PHP `strip_tags` with a restricted tag allowlist + manual `on*` attribute stripping + `javascript:` URL stripping. Frontend: `ParagraphBlock.vue` and `CalloutBlock.vue` pass rendered HTML through DOMPurify on render. _Touches: form request + block components._
- **FR-M18: `SanitizeInput` middleware preserves `body_blocks` HTML.** Without this, the global middleware strips all HTML from paragraph/callout blocks before the form request sees them. _Touches: `app/Http/Middleware/SanitizeInput.php`._
- **FR-M19: Tax-year stat block.** `TaxYearStatBlock.vue` pulls live values from `@/constants/taxConfig` (with fallback to backend via `TaxConfigService` for non-mirrored stats). 5 stat keys supported: `isa_annual_allowance`, `personal_allowance`, `pension_annual_allowance`, `iht_nil_rate_band`, `cgt_annual_allowance`. Values refresh automatically on tax-year rollover. _Touches: block component + `taxConfig.js`._
- **FR-M20: AdminPanel sidebar integration.** Add an "Insights" tab to `AdminPanel.vue`'s `navItems` so admins can reach `/admin/insights` from the admin navigation. _Touches: `AdminPanel.vue`._
- **FR-M21: Feature flag `VITE_INSIGHTS_CMS_ENABLED`.** Production deploys Phases 1-3 (backend only) with flag = `false`. After migration + seeder run + API verified, flip to `true` and redeploy frontend. Fallback code in `InsightsHubPage.vue` and `LandingPage.vue` uses the hardcoded array when the flag is false. _Touches: both build scripts + router + two public pages._
- **FR-M22: Cache invalidation via generation counter.** Observer increments `insights.list_version` on any change; list endpoint embeds the current version in its cache key so every paginated page invalidates atomically. _Touches: observer + `InsightController::index()`._

### Should-have

- **FR-S1: Admin-only draft preview.** `?preview=true` query param on public article routes returns draft content only when requester is admin. Non-admins get 404. _Touches: `InsightController::show()`._
- **FR-S2: Pest test coverage.** Unit tests for all services (article, template, image, SEO, block validator), feature tests for all admin and public endpoints, architecture tests for route ordering and tax-value hardcoding. _Touches: `tests/Unit/Services/Insights/`, `tests/Feature/Api/`, `tests/Architecture/`._
- **FR-S3: Audit logging.** All admin mutations recorded via existing `Auditable` trait to `audit_logs` table. No new audit surface. _Touches: `InsightArticle` + `InsightTemplate` models._
- **FR-S4: Constructor-level admin middleware.** Each new admin controller adds `$this->middleware('permission:admin.access')` in its constructor as defence in depth, mirroring `AdminController`. _Touches: 3 admin controllers._
- **FR-S5: Tailwind safelist update.** Add `bg-horizon-50` to safelist so `CalloutBlock.vue`'s `info` variant renders correctly. _Touches: `tailwind.config.js`._

### Nice-to-have

- **FR-N1: Template re-sync.** Admin can re-apply the current state of an article's original template, with warning that content overrides will be replaced. _Touches: `InsightArticleService::resyncFromTemplate()` + admin button._
- **FR-N2: Global CSS reuse.** All admin UI uses existing `.card`, `.card-lg`, `.modal-*`, `.badge-*` classes from `resources/css/app.css` rather than redefining them. _Touches: all new admin Vue components — enforced by convention, not a separate task._

### Deferred / Out of scope (documented here to prevent scope creep)

- **Sitemap integration.** No existing `SitemapController` in Fynla. Adding one is out of scope; deferred to a follow-up task. Articles remain internally discoverable via hub + landing + backlinks.
- **Public comment system.**
- **Multi-author review/approval workflow.**
- **A/B testing of article variants.**
- **Automated AI content generation.**
- **Migrating bespoke article bodies into the CMS** — only metadata migrated; bodies stay in Vue files.

---

## 6. User Flow & UX/Design

### Flow — admin publishes a new article

```
/admin (sidebar has "Insights" entry — FR-M20)
  └─ click "Insights"
       └─ /admin/insights (ArticleListPage)
            ├─ filter by status / category / featured
            └─ click "+ New article"
                 └─ /admin/insights/new (ArticleEditor)
                      ├─ Template picker: "Blank" or saved templates
                      ├─ Field panel (left ~35%): title, subtitle, summary, category,
                      │    tags, hero image upload, SEO overrides (collapsed)
                      └─ Block canvas (right ~65%): + Add block → BlockPickerModal
                           └─ 11 block types as tiles → pick one → inserted at cursor
                      ├─ [Save draft] → API POST → /admin/insights/{id}/edit
                      ├─ [Preview] → opens /insights/{slug}?preview=true in new tab
                      └─ [Publish] → API publish endpoint → status=published,
                                     published_at=now, auto-unfeature previous if is_featured=true
```

### Flow — visitor reads an article

```
GET /insights/{slug}
  ├─ Vue Router: if {slug} matches one of the 8 named bespoke routes (declared BEFORE catch-all)
  │    → bespoke Vue component renders (untouched by this feature)
  └─ else matches /insights/:slug catch-all
       ├─ InsightArticlePage.vue fetches /api/insights/{slug}
       ├─ Laravel middleware InsightsSeoMetaInjector runs on the matching web.php route
       │    (declared BEFORE /{any} SPA catch-all — enforced by architecture test)
       │    → pushes per-article meta tags to @stack('head') in app.blade.php
       ├─ Article renders: hero image, title, subtitle, blocks via ArticleBlockRenderer
       └─ 404 if article not found OR is_bespoke=true (safety net; named route should have caught first)
```

### Flow — visitor on landing page

```
GET /
  └─ LandingPage.vue
       └─ "Latest insights" section
            ├─ If VITE_INSIGHTS_CMS_ENABLED=true (FR-M21):
            │    ├─ fetches /api/insights/featured
            │    │    (cached with insights.featured key, busted by observer)
            │    └─ renders 2/3 featured tile (is_featured=true, or fallback: most recent)
            │       + 1/3 stacked (next 2 most recent)
            └─ If flag=false: renders legacy 3-card grid from hardcoded array
```

### UX/Design notes

- **Design system:** `fynlaDesignGuide.md` v1.4.0 (not v1.3.0 as originally specified — codebase audit confirmed v1.4.0 is current). Palette: `raspberry-*` for CTAs, `horizon-*` for text/nav, `spring-*` for success/tips, `violet-*` for warnings, `savannah-*` for hover, `eggshell-*` for backgrounds, `neutral-*` for muted text. No amber, orange, `primary-*`, `secondary-*`, or `gray-*` tokens.
- **Callout block variants** map to design palette: `info` → `horizon` (subtle blue-grey), `tip` + `success` → `spring` (green), `warning` → `violet` (purple).
- **Reusable components:** Editor and list page reuse existing `.card`, `.card-lg`, `.card-sm`, `.modal-overlay`, `.modal`, `.badge-warning`, `.badge-success`, `.badge-info`, `.btn-primary`, `.btn-secondary` from `resources/css/app.css`. Never redefine these in component `<style scoped>`.
- **New components** (all new, no existing reuse opportunity):
  - Public: `InsightArticlePage`, `ArticleBlockRenderer`, 11 `*Block.vue` per-block components
  - Admin: `ArticleListPage`, `ArticleEditor`, `BlockPickerModal`, `BespokeArticleNotice`, `TemplateListPage`, 11 `Edit*Block.vue` components
- **Responsive behaviour:** Standard responsive — no special treatment. Editor uses a 5-column grid on `lg:` breakpoint (field panel ~35%, canvas ~65%) and stacks vertically below.
- **Accessibility:**
  - Block canvas reorder buttons (↑ ↓ ×) have `aria-label` attributes
  - Form fields use labelled inputs
  - Preview opens in a new tab (preserves back button behaviour)
  - Keyboard: save/publish shortcuts not in scope for v1
- **Reference artefacts:**
  - Spec: `docs/superpowers/specs/2026-04-17-admin-insights-cms-design.md`
  - Plan: `docs/superpowers/plans/2026-04-17-admin-insights-cms.md`
  - Current-state reference: `fynlaBrain` vault — the existing 8 bespoke articles in `resources/js/views/Public/insights/` show the editorial voice the CMS should support.

---

## 7. Out of Scope

- **Sitemap integration.** Fynla has no `SitemapController` today. Adding Laravel-controlled sitemap generation is out of scope for this feature; insight articles remain discoverable via internal links.
- **Migration of bespoke article bodies.** Only the 8 bespoke articles' *metadata* (title, summary, category, tags, image, featured flag) is migrated to the DB. Their body content stays in Vue files. Admin UI shows a notice pointing to the Vue file.
- **Public comments / user-generated content on articles.**
- **Multi-author workflow.** No review queues, approvals, assignments. One admin owns an article end to end.
- **A/B testing / article variants.**
- **AI content generation.** Not writing articles for the admin.
- **WYSIWYG rich text inside blocks.** Paragraph blocks accept restricted HTML (`<strong><em><a><br>`) only. No contenteditable editor.
- **Version-by-version diff view of revisions.** The plan stores every revision and allows restore-to-revision, but there's no UI for a side-by-side diff. Restore is "replace current with revision N".
- **Nonce-based CSP for inline JSON-LD.** Existing Fynla uses `unsafe-inline` in CSP (tracked separately in the security backlog). The JSON-LD block inherits that existing posture; no CSP change for this feature.
- **HTMLPurifier dependency.** Replaced with PHP `strip_tags` + allowlist; one less heavy dep.

---

## 8. Risks & Dependencies

### Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Route precedence breaks: the catch-all `/insights/:slug` ends up before the 8 named insight routes, so bespoke articles render through the generic template and lose their layout | Low | High (visible regression on 8 existing articles) | Architecture test enforces ordering in both `router/index.js` and `routes/web.php`. Code review checks. A comment in `router/index.js` flags the constraint. |
| `SanitizeInput` middleware strips paragraph HTML silently if Task 16b is skipped | Low | High (all article body content becomes plain text) | Explicit feature test `SanitizeInputInsightsTest` asserts HTML round-trips through the admin endpoint. |
| Double revision writes — if the service's `update()` isn't cleaned up per Task 9's amendment, every save writes 2 revisions | Low | Low (not broken, just noisy) | Service test `writes a revision on every update` asserts exactly 1 revision per update; will fail loudly if the bug reappears. |
| Admin-preview leak — `?preview=true` without the admin check lets anyone see draft content | Low | High (premature content disclosure) | Pest feature test asserts non-admin `?preview=true` returns 404. |
| Image storage growth — original + 2 WebP derivatives per article, ~2 MB total each, grows unbounded | Low | Low (hosting cost) | Monitor `storage/app/public/insights/` size quarterly. At 100+ articles, consider CDN or S3 offload. |
| Cache invalidation misses an edge case (e.g. featured article archive doesn't bust the featured cache key) | Medium | Low (temporarily stale landing page until cache expiry) | Observer's `saved()` hook covers all status changes including archive. Manual verification in browser test checklist. |
| Paragraph/callout HTML XSS — admin account compromised, attacker pastes script into a paragraph | Low | Medium (stored XSS scoped to article readers) | Two-layer sanitisation: backend `strip_tags` + allowlist + `on*` attribute strip + `javascript:` URL strip, frontend DOMPurify on render. Admin account security is orthogonal and handled by existing Fynla auth. |
| Feature flag flip on production surfaces unknown issue — e.g. an article references an image path that doesn't exist on production | Low | Medium (hub shows broken tiles) | Phased deploy: flip flag on, smoke-test immediately, flip off if broken. Fallback to hardcoded array stays in code during the transition. |

### Technical dependencies

**New external dependencies (both must be installed before the corresponding task):**
- `intervention/image` (composer) — image resizing + WebP conversion in `InsightImageService`. Not currently in Fynla.
- `dompurify` (npm) — frontend HTML sanitisation in `ParagraphBlock.vue` and `CalloutBlock.vue`. Not currently in Fynla.

**Existing patterns relied on (already in Fynla):**
- `Auditable` trait (`app/Traits/Auditable.php`) — admin mutations written to `audit_logs`
- `TaxConfigService` (backend) + `@/constants/taxConfig` (frontend) — tax year + allowance values for `TaxYearStatBlock`
- `permission:admin.access` middleware (Fynla's RBAC) — admin route gating
- Fynla cron schedule (`app/Console/Kernel.php`) — hosts `PublishScheduledInsightsJob` without additional setup
- `resources/css/app.css` global classes — reused by admin UI
- Existing Sanctum auth — public API reads current user from session/token to check `is_admin` for preview mode

### Sequencing dependencies

- **Runs AFTER:** None — this feature is self-contained.
- **Runs BEFORE:** Future content-related work. A "content admin" role (distinct from full admin) would fit naturally on top of this foundation. Sitemap integration is a reasonable follow-up that would inherit the `InsightArticle` model.
- **Migration + seeder ordering:** `insight_templates` must migrate before `insight_articles` (FK constraint). Timestamps in the plan reflect this: `090001_create_insight_templates_table`, `090002_create_insight_articles_table`, `090003_create_insight_article_revisions_table`.

### Residual concerns from codebase audit

Two items from the validation report are intentionally accepted rather than fully resolved:

1. **`PreviewWriteInterceptor` ordering note.** The spec originally explained why no `EXCLUDED_ROUTES` entry was needed in terms of admin middleware running first. The audit confirmed the interceptor actually runs *before* the admin check. The functional outcome is unchanged (preview users are never admins, so the interceptor's fake-success response is benign on admin routes), but the reasoning differs. The spec has been corrected; no code change needed.

2. **Stat key naming.** The spec uses lowercase snake_case (`isa_annual_allowance`) in `tax_year_stat` blocks, while `taxConfig.js` exports SCREAMING_SNAKE_CASE (`ISA_ANNUAL_ALLOWANCE`). `TaxYearStatBlock.vue` carries a small (~6-line) mapping table. This is deliberate — block data stays human-readable in the JSON, and the mapping is cheap.

Everything else from the audit has been applied as spec/plan amendments.

---

## 9. Document History

| Date | Change | By |
|------|--------|-----|
| 17 April 2026 | Initial draft | prd-writer skill |
| 17 April 2026 | Codebase audit completed — 11 🔴 conflicts + 7 🟡 ambiguities + 4 🟢 gaps resolved in amended spec and plan | prd-writer skill |
